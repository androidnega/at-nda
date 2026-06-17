/* Minimal service worker: drains offline attendance queue on Background Sync. */
'use strict';

var MARK_URL = '/web/attendance/mark';

self.addEventListener('install', function (event) {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('sync', function (event) {
    if (event.tag === 'attendance-sync') {
        event.waitUntil(drainPendingMarks());
    }
});

function openDb() {
    return new Promise(function (resolve, reject) {
        var req = indexedDB.open('atenda_attendance_v1', 1);
        req.onerror = function () { reject(req.error); };
        req.onsuccess = function () { resolve(req.result); };
    });
}

function listPending(db) {
    return new Promise(function (resolve, reject) {
        var tx = db.transaction('pending_marks', 'readonly');
        var req = tx.objectStore('pending_marks').getAll();
        req.onsuccess = function () { resolve(req.result || []); };
        req.onerror = function () { reject(req.error); };
    });
}

function removePending(db, id) {
    return new Promise(function (resolve, reject) {
        var tx = db.transaction('pending_marks', 'readwrite');
        var req = tx.objectStore('pending_marks').delete(id);
        req.onsuccess = function () { resolve(); };
        req.onerror = function () { reject(req.error); };
    });
}

function postMark(row) {
    return fetch(row.url || MARK_URL, {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': row.csrf,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(row.payload),
    }).then(function (res) {
        return res.text().then(function (text) {
            var data = {};
            try { data = text ? JSON.parse(text) : {}; } catch (e) {}
            return { ok: res.ok, data: data };
        });
    });
}

function drainPendingMarks() {
    return openDb().then(function (db) {
        return listPending(db).then(function (rows) {
            return rows.reduce(function (chain, row) {
                return chain.then(function () {
                    return postMark(row).then(function (result) {
                        if (result.ok && result.data && result.data.success) {
                            return removePending(db, row.id);
                        }
                    }).catch(function () {});
                });
            }, Promise.resolve());
        });
    });
}
