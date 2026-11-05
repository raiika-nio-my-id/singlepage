/*
 * Shared player name for every game on www.
 *
 * The name gate in each game still asks "Siapa namamu?" and still stores
 * sessionStorage["jdng.<slug>"] plus that game's own key. Those keys were
 * isolated when each game had its own origin. On one origin they stay
 * separate, so every game asked again.
 *
 * This bridge runs before the gate. localStorage["jdng.name"] is the shared
 * name. A jdng.* session read returns it, so the gate stays closed. A write
 * from the gate, from a per-game name key, or from a JSON name field updates
 * it. Clearing localStorage (or removing the shared value and any saved
 * per-game name) makes the next game ask again.
 */
(function () {
    var root = typeof window !== 'undefined' ? window : globalThis;

    if (root.__jdngSharedName) {
        return;
    }

    root.__jdngSharedName = 1;

    var SHARED = 'jdng.name';
    var MAX = 24;
    var local;
    var session;

    try {
        local = root.localStorage;
    } catch (error) {
        return;
    }

    if (!local || typeof local.getItem !== 'function' || typeof local.setItem !== 'function') {
        return;
    }

    try {
        session = root.sessionStorage;
    } catch (error) {
        session = null;
    }

    var localGet = local.getItem.bind(local);
    var localSet = local.setItem.bind(local);

    function clean(value) {
        return String(value == null ? '' : value).replace(/[\u0000-\u001f<>]/g, '').replace(/\s+/g, ' ').trim().slice(0, MAX);
    }

    function isSessionKey(key) {
        return typeof key === 'string' && key.indexOf('jdng.') === 0 && key !== 'jdng.force' && key !== SHARED;
    }

    function isLocalNameKey(key) {
        if (typeof key !== 'string' || key === SHARED || key === 'jdng.force') {
            return false;
        }

        return /^[a-z0-9][a-z0-9-]{0,63}\.name$/.test(key) || /^[a-z0-9][a-z0-9-]{0,63}-name$/.test(key);
    }

    function rawName(value) {
        if (typeof value !== 'string') {
            return '';
        }

        var text = value.trim();

        if (text === '') {
            return '';
        }

        if (text.charAt(0) === '"') {
            try {
                var parsed = JSON.parse(text);

                return typeof parsed === 'string' ? clean(parsed) : '';
            } catch (error) {
                return '';
            }
        }

        if (text.charAt(0) === '{' || text.charAt(0) === '[' || text === 'null' || text === 'true' || text === 'false') {
            return '';
        }

        return clean(text);
    }

    function readShared() {
        try {
            return clean(localGet(SHARED));
        } catch (error) {
            return '';
        }
    }

    function writeShared(name) {
        try {
            if (name) {
                localSet(SHARED, name);
            }
        } catch (error) {
            // Private mode can reject writes. The gate still works for this tab.
        }
    }

    function promote() {
        if (readShared()) {
            return;
        }

        try {
            var index;
            var key;
            var name;

            for (index = 0; index < local.length; index += 1) {
                key = local.key(index);

                if (!isLocalNameKey(key)) {
                    continue;
                }

                name = rawName(localGet(key));

                if (name) {
                    writeShared(name);

                    return;
                }
            }
        } catch (error) {
            // Ignore a storage walk that a browser rejects.
        }
    }

    function wrap(storage, kind) {
        var get = storage.getItem.bind(storage);
        var set = storage.setItem.bind(storage);

        storage.getItem = function (key) {
            var current = null;

            try {
                current = get(key);
            } catch (error) {
                current = null;
            }

            if (kind === 'session' && isSessionKey(key)) {
                return readShared() || null;
            }

            if (kind === 'local' && isLocalNameKey(key) && (current == null || current === '')) {
                return readShared() || current;
            }

            return current;
        };

        storage.setItem = function (key, value) {
            set(key, value);

            var name = rawName(typeof value === 'string' ? value : String(value));

            if (!name) {
                return;
            }

            if ((kind === 'session' && isSessionKey(key)) || (kind === 'local' && isLocalNameKey(key))) {
                writeShared(name);
            }
        };
    }

    promote();
    wrap(local, 'local');

    if (session && typeof session.getItem === 'function' && typeof session.setItem === 'function') {
        wrap(session, 'session');
    }
})();
