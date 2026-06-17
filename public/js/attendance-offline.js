/**
 * Offline attendance queue (IndexedDB) + background sync hook.
 */
(function (global) {
    'use strict';

    var DB_NAME = 'atenda_attendance_v1';
    var STORE = 'pending_marks';
    var DB_VERSION = 1;

    function openDb() {
        return new Promise(function (resolve, reject) {
            var req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onerror = function () { reject(req.error); };
            req.onupgradeneeded = function () {
                var db = req.result;
                if (!db.objectStoreNames.contains(STORE)) {
                    var store = db.createObjectStore(STORE, { keyPath: 'id', autoIncrement: true });
                    store.createIndex('created_at', 'created_at', { unique: false });
                }
            };
            req.onsuccess = function () { resolve(req.result); };
        });
    }

    function enqueue(record) {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction(STORE, 'readwrite');
                var store = tx.objectStore(STORE);
                var row = Object.assign({}, record, {
                    created_at: Date.now(),
                    attempts: 0,
                });
                var req = store.add(row);
                req.onsuccess = function () {
                    resolve(req.result);
                };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function listAll() {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction(STORE, 'readonly');
                var req = tx.objectStore(STORE).getAll();
                req.onsuccess = function () { resolve(req.result || []); };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function remove(id) {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx = db.transaction(STORE, 'readwrite');
                var req = tx.objectStore(STORE).delete(id);
                req.onsuccess = function () { resolve(); };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function postMark(row) {
        return fetch(row.url, {
            method: 'POST',
            credentials: 'same-origin',
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
                return { ok: res.ok, status: res.status, data: data };
            });
        });
    }

    function drain(markUrl, getCsrf) {
        return listAll().then(function (rows) {
            var chain = Promise.resolve({ synced: 0, failed: 0 });
            rows.forEach(function (row) {
                chain = chain.then(function (stats) {
                    var csrf = typeof getCsrf === 'function' ? getCsrf() : row.csrf;
                    row.csrf = csrf || row.csrf;
                    row.url = row.url || markUrl;
                    return postMark(row).then(function (result) {
                        if (result.ok && result.data && result.data.success) {
                            return remove(row.id).then(function () {
                                stats.synced += 1;
                                return stats;
                            });
                        }
                        stats.failed += 1;
                        return stats;
                    }).catch(function () {
                        stats.failed += 1;
                        return stats;
                    });
                });
            });
            return chain;
        });
    }

    function registerBackgroundSync() {
        if (!('serviceWorker' in navigator)) return Promise.resolve(false);
        return navigator.serviceWorker.ready.then(function (reg) {
            if (!reg.sync) return false;
            return reg.sync.register('attendance-sync').then(function () { return true; });
        }).catch(function () { return false; });
    }

    function registerServiceWorker(swUrl) {
        if (!('serviceWorker' in navigator)) return Promise.resolve(false);
        return navigator.serviceWorker.register(swUrl, { scope: '/' }).catch(function () {
            return false;
        });
    }

    function bindOnlineDrain(markUrl, getCsrf) {
        function tryDrain() {
            if (!navigator.onLine) return;
            drain(markUrl, getCsrf);
        }
        global.addEventListener('online', tryDrain);
        tryDrain();
    }

    global.AttendanceOffline = {
        enqueue: enqueue,
        drain: drain,
        listAll: listAll,
        registerBackgroundSync: registerBackgroundSync,
        registerServiceWorker: registerServiceWorker,
        bindOnlineDrain: bindOnlineDrain,
        postMark: postMark,
    };
})(typeof window !== 'undefined' ? window : self);
