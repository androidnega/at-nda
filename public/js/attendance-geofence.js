/**
 * Client-side geofence engine for web attendance.
 * Mirrors server rules in App\Support\AttendanceLocation::passesGeofenceCheck.
 */
(function (global) {
    'use strict';

    var EARTH_RADIUS_M = 6371000;

    function toRad(d) {
        return (d * Math.PI) / 180;
    }

    function haversineMeters(lat1, lng1, lat2, lng2) {
        var dLat = toRad(lat2 - lat1);
        var dLng = toRad(lng2 - lng1);
        var a =
            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
            Math.sin(dLng / 2) * Math.sin(dLng / 2);
        var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return EARTH_RADIUS_M * c;
    }

    function num(v, fallback) {
        var n = Number(v);
        return Number.isFinite(n) ? n : fallback;
    }

    function passesGeofence(distanceM, allowedM, accuracyM, cfg) {
        cfg = cfg || {};
        var proximity = Math.max(1, num(cfg.proximityPassM, 4));
        if (distanceM <= proximity) {
            return true;
        }
        var allowed = allowedM;
        if (cfg.floorMatch) {
            allowed += Math.max(0, num(cfg.floorMatchBonusM, 30));
        }
        var cap = num(cfg.accuracySlackCapM, 120);
        var slack = accuracyM != null && Number.isFinite(accuracyM)
            ? Math.min(cap, Math.max(0, accuracyM))
            : 0;
        return distanceM <= (allowed + slack);
    }

    function median(values) {
        if (!values.length) return 0;
        var sorted = values.slice().sort(function (a, b) { return a - b; });
        var mid = Math.floor(sorted.length / 2);
        return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
    }

    /**
     * Drop readings whose position is far from the cluster median.
     */
    function rejectOutliers(samples) {
        if (samples.length < 3) return samples;
        var lats = samples.map(function (s) { return s.lat; });
        var lngs = samples.map(function (s) { return s.lng; });
        var medLat = median(lats);
        var medLng = median(lngs);
        var kept = samples.filter(function (s) {
            var d = haversineMeters(medLat, medLng, s.lat, s.lng);
            var tol = Math.max(25, (s.accuracy || 30) * 2.5);
            return d <= tol;
        });
        return kept.length ? kept : samples;
    }

    function refineSamples(samples) {
        if (!samples.length) return null;
        var filtered = rejectOutliers(samples);
        var totalW = 0;
        var sumLat = 0;
        var sumLng = 0;
        var bestAcc = Infinity;
        var sumAlt = 0;
        var altCount = 0;
        var lastTs = null;
        var speeds = [];

        filtered.forEach(function (s) {
            var acc = Math.max(1, s.accuracy || 50);
            var w = 1 / acc;
            totalW += w;
            sumLat += s.lat * w;
            sumLng += s.lng * w;
            if (acc < bestAcc) bestAcc = acc;
            if (s.altitude != null && Number.isFinite(s.altitude)) {
                sumAlt += s.altitude;
                altCount += 1;
            }
            if (lastTs != null && s.timestamp != null) {
                var dt = (s.timestamp - lastTs) / 1000;
                if (dt > 0.2 && dt < 30 && s.speed != null && Number.isFinite(s.speed)) {
                    speeds.push(s.speed);
                }
            }
            lastTs = s.timestamp || lastTs;
        });

        return {
            lat: sumLat / totalW,
            lng: sumLng / totalW,
            accuracy: bestAcc === Infinity ? 50 : bestAcc,
            altitude: altCount ? sumAlt / altCount : null,
            sampleCount: filtered.length,
            speeds: speeds,
        };
    }

    /**
     * Reject impossible jumps between consecutive samples (anti-spoof).
     */
    function detectSpoof(samples) {
        if (samples.length < 2) return null;
        for (var i = 1; i < samples.length; i++) {
            var a = samples[i - 1];
            var b = samples[i];
            var dt = ((b.timestamp || 0) - (a.timestamp || 0)) / 1000;
            if (dt <= 0 || dt > 60) continue;
            var dist = haversineMeters(a.lat, a.lng, b.lat, b.lng);
            var maxSpeed = 45; // m/s ~ 160 km/h — impossible on foot/campus
            if (dist / dt > maxSpeed) {
                return 'Location jumped too quickly between readings. Disable mock-location apps and try again.';
            }
        }
        return null;
    }

    function scoreConfidence(fix, anchor, allowedM, cfg) {
        if (!fix || !anchor || anchor.lat == null || anchor.lng == null) {
            return 0;
        }
        var distance = haversineMeters(anchor.lat, anchor.lng, fix.lat, fix.lng);
        var accuracy = fix.accuracy || 50;
        var passes = passesGeofence(distance, allowedM, accuracy, cfg);
        var distScore = Math.max(0, 1 - distance / Math.max(allowedM + accuracy, 1));
        var accScore = Math.max(0, 1 - accuracy / 120);
        var sampleScore = Math.min(1, (fix.sampleCount || 1) / 6);
        var stability = fix.sampleCount >= 3 ? 1 : 0.6;
        var raw = (distScore * 0.45) + (accScore * 0.35) + (sampleScore * 0.2);
        var confidence = passes ? Math.min(1, raw * stability + 0.15) : raw * 0.4;
        return {
            confidence: confidence,
            distanceM: distance,
            passes: passes,
            accuracyM: accuracy,
        };
    }

    function readCoords(position) {
        var c = position.coords;
        return {
            lat: c.latitude,
            lng: c.longitude,
            accuracy: c.accuracy || 0,
            altitude: c.altitude,
            altitudeAccuracy: c.altitudeAccuracy,
            speed: c.speed,
            heading: c.heading,
            timestamp: position.timestamp || Date.now(),
        };
    }

    function getOnePosition(opts) {
        return new Promise(function (resolve, reject) {
            if (!navigator.geolocation) {
                reject(new Error('Geolocation not supported'));
                return;
            }
            navigator.geolocation.getCurrentPosition(resolve, reject, opts);
        });
    }

    /**
     * Collect 5–8 readings over ~3s (extend to ~5s when accuracy stays poor).
     */
    function collectRefinedFix(options) {
        options = options || {};
        var minSamples = num(options.minSamples, 5);
        var maxSamples = num(options.maxSamples, 8);
        var budgetMs = num(options.budgetMs, 3200);
        var poorAccuracyM = num(options.poorAccuracyM, 50);
        var extendedBudgetMs = num(options.extendedBudgetMs, 5200);

        var highOpts = {
            enableHighAccuracy: true,
            maximumAge: 0,
            timeout: Math.min(8000, budgetMs + 2000),
        };

        var samples = [];
        var started = Date.now();
        var deadline = started + budgetMs;

        function sampleOnce() {
            return getOnePosition(highOpts).then(function (pos) {
                samples.push(readCoords(pos));
            }).catch(function () {
                // tolerate individual failures
            });
        }

        function loop() {
            if (samples.length >= maxSamples || Date.now() >= deadline) {
                return Promise.resolve();
            }
            return sampleOnce().then(function () {
                var last = samples[samples.length - 1];
                if (last && last.accuracy > poorAccuracyM && Date.now() < extendedBudgetMs) {
                    deadline = extendedBudgetMs;
                }
                return new Promise(function (r) {
                    setTimeout(r, 380);
                }).then(loop);
            });
        }

        // Warm-up read (discarded) reduces cold-start noise.
        return getOnePosition(highOpts).catch(function () {})
            .then(function () { return new Promise(function (r) { setTimeout(r, 350); }); })
            .then(loop)
            .then(function () {
                if (!samples.length) {
                    return getOnePosition(highOpts).then(function (p) {
                        samples.push(readCoords(p));
                    });
                }
            })
            .then(function () {
                while (samples.length < minSamples && Date.now() < extendedBudgetMs) {
                    // sync path not used — if still short, one more try
                    break;
                }
            })
            .then(function () {
                if (!samples.length) return null;
                var spoof = detectSpoof(samples);
                if (spoof) {
                    return { error: spoof, denied: false };
                }
                var refined = refineSamples(samples);
                if (!refined) return null;
                refined.readings = samples;
                return refined;
            })
            .catch(function (e) {
                if (e && e.code === 1) return { denied: true };
                return null;
            });
    }

    global.AttendanceGeofence = {
        EARTH_RADIUS_M: EARTH_RADIUS_M,
        haversineMeters: haversineMeters,
        passesGeofence: passesGeofence,
        collectRefinedFix: collectRefinedFix,
        scoreConfidence: scoreConfidence,
        refineSamples: refineSamples,
        detectSpoof: detectSpoof,
    };
})(typeof window !== 'undefined' ? window : self);
