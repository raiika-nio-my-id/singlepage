const fs = require('fs');
const vm = require('vm');
const path = require('path');

const bridge = fs.readFileSync(path.join(__dirname, '../../public/js/player-name.js'), 'utf8');

const gate = `(function(){var C=window.JDNG_CFG||{},W=window,D=document,R=D.documentElement,K='jdng.'+(C.slug||location.hostname.split('.')[0]),cb=[],G=W.JDNG={name:'',done:false,on:function(f){if(G.done)f(G.name);else cb.push(f)}};
function st(s,k,v){try{if(v===undefined)return s.getItem(k)||'';s.setItem(k,v)}catch(e){}return ''}
function ss(k,v){var s;try{s=W.sessionStorage}catch(e){return ''}return st(s,k,v)}
function ls(k,v){var s;try{s=W.localStorage}catch(e){return ''}return st(s,k,v)}
var el=D.getElementById('jdng'),inp=D.getElementById('jdngN'),q=location.search,L=String((C.langKey&&ls(C.langKey))||(C.multi&&navigator.language)||R.lang||'id').toLowerCase().slice(0,2);
var TX={id:['Siapa namamu?','Nama ini dipakai di skor, room & daftar pemain.','Main','Isi nama dulu ya.'],en:["What's your name?",'Used for scores, rooms and player lists.','Play','Please enter your name.']};
var T=(TX[L]||TX.id).slice();
function clean(v){return String(v||'').replace(/[\\u0000-\\u001f<>]/g,'').replace(/\\s+/g,' ').trim().slice(0,C.max||24)}
function set(x,n){if(!x||x.value===n)return;x.value=n;try{x.dispatchEvent(new Event('input',{bubbles:true}));x.dispatchEvent(new Event('change',{bubbles:true}))}catch(e){}}
function fill(onlyEmpty){var n=G.name;if(!n)return;(C.inputs||[]).forEach(function(s){var a=D.querySelectorAll(s);for(var i=0;i<a.length;i++)if(!onlyEmpty||!a[i].value)set(a[i],n)})}
function finish(n,quiet){n=clean(n);if(!n)return;G.name=n;G.done=true;ss(K,n);(C.keys||[]).forEach(function(k){ls(k,n)});el.hidden=true;R.classList.remove('jdng-on');fill(false);var f=cb.splice(0);for(var i=0;i<f.length;i++)try{f[i](n)}catch(e){}try{W.dispatchEvent(new CustomEvent('jdng:name',{detail:n}))}catch(e){}}
function open(){if(G.done)return;el.hidden=false;R.classList.add('jdng-on');inp.value='';foc()}
function foc(){if(el.hidden)return;try{inp.focus({preventScroll:true})}catch(e){inp.focus()}}
D.getElementById('jdngI').textContent=C.icon||'';D.getElementById('jdngG').textContent=C.title||'';D.getElementById('jdngT').textContent=T[0];D.getElementById('jdngS').textContent=T[1];D.getElementById('jdngB').textContent=T[2];inp.maxLength=C.max||24;
if(C.accent)el.style.setProperty('--jdng-a',C.accent);
D.getElementById('jdngF').addEventListener('submit',function(e){e.preventDefault();var n=clean(inp.value);if(!n){D.getElementById('jdngE').textContent=T[3];foc();return}D.getElementById('jdngE').textContent='';finish(n)});
D.addEventListener('submit',function(e){var f=e.target;if(f&&f.classList&&f.classList.contains('jr-inv-form')){var x=f.querySelector('input');if(x&&clean(x.value)){G.name=clean(x.value);ss(K,G.name);G.done=true;}}},true);
var skip=false;try{skip=W.self!==W.top}catch(e){skip=true}
if(/^#(demo|snap)/i.test(location.hash)||/[?&]wallpaper\\b/i.test(q)||(navigator.webdriver&&!ls('jdng.force'))||(C.skip&&C.skip()))skip=true;
var saved=clean(ss(K)),inv=C.invite&&new RegExp('[?&]'+C.invite+'=').test(q);
if(saved){G.name=saved;G.done=true;D.addEventListener('DOMContentLoaded',function(){fill(true)})}
else if(skip){}
else if(inv){}
else{open()}
})();`;

function memory(initial) {
    const data = Object.assign({}, initial || {});

    return {
        getItem(key) {
            return Object.prototype.hasOwnProperty.call(data, key) ? data[key] : null;
        },
        setItem(key, value) {
            data[key] = String(value);
        },
        removeItem(key) {
            delete data[key];
        },
        key(index) {
            return Object.keys(data)[index] ?? null;
        },
        get length() {
            return Object.keys(data).length;
        },
        dump() {
            return Object.assign({}, data);
        },
    };
}

function element(id) {
    return {
        id,
        hidden: id === 'jdng',
        value: '',
        textContent: '',
        maxLength: 0,
        style: { setProperty() {} },
        classList: { add() {}, remove() {}, contains() { return false; } },
        listeners: [],
        addEventListener(type, fn) {
            this.listeners.push({ type, fn });
        },
        focus() {},
        dispatchEvent() {
            return true;
        },
        contains() {
            return false;
        },
        querySelector() {
            return null;
        },
    };
}

function page(localSeed, sessionSeed, options) {
    const opts = options || {};
    const local = memory(localSeed);
    const session = memory(sessionSeed);
    const dialog = element('jdng');
    const input = element('jdngN');
    const form = element('jdngF');
    const nameInput = element('nameInput');
    const elements = {
        jdng: dialog,
        jdngN: input,
        jdngI: element('jdngI'),
        jdngG: element('jdngG'),
        jdngT: element('jdngT'),
        jdngS: element('jdngS'),
        jdngB: element('jdngB'),
        jdngE: element('jdngE'),
        jdngF: form,
        nameInput,
    };
    const docListeners = [];
    const sandbox = {
        console,
        setTimeout,
        clearTimeout,
        setInterval,
        clearInterval,
        JSON,
        Event,
        CustomEvent,
        localStorage: local,
        sessionStorage: session,
        navigator: { language: 'id', webdriver: !!opts.webdriver },
        location: { search: opts.search || '', hash: opts.hash || '', hostname: 'www.jadidaring.test' },
        document: {
            documentElement: {
                lang: 'id',
                classList: { add() {}, remove() {} },
                style: {},
            },
            getElementById(id) {
                return elements[id] || null;
            },
            querySelectorAll(selector) {
                return selector === '#nameInput' ? [nameInput] : [];
            },
            addEventListener(type, fn) {
                docListeners.push({ type, fn });
            },
        },
    };
    sandbox.window = sandbox;
    sandbox.self = sandbox;
    sandbox.top = sandbox;
    sandbox.globalThis = sandbox;
    sandbox.window.JDNG_CFG = {
        inputs: ['#nameInput'],
        keys: ['ut.name'],
        slug: 'ulartangga',
        max: 24,
        title: 'Ular Tangga Sakti',
        invite: 'room',
        accent: '#ffcf3f',
    };

    vm.createContext(sandbox);
    vm.runInContext(bridge, sandbox);
    vm.runInContext(gate, sandbox);
    docListeners.filter((listener) => listener.type === 'DOMContentLoaded').forEach((listener) => listener.fn({}));

    return {
        get hidden() {
            return dialog.hidden;
        },
        get done() {
            return sandbox.JDNG.done;
        },
        get name() {
            return sandbox.JDNG.name;
        },
        get input() {
            return nameInput.value;
        },
        get dialogInput() {
            return input.value;
        },
        local,
        session,
        submit(value) {
            input.value = value;
            const handler = form.listeners.find((listener) => listener.type === 'submit');
            if (!handler) {
                throw new Error('name gate submit handler missing');
            }
            handler.fn({ preventDefault() {} });
        },
    };
}

function assert(condition, message) {
    if (!condition) {
        throw new Error(message);
    }
}

const missing = page({}, {});
assert(missing.hidden === false, 'missing shared name should open the gate');
assert(missing.done === false, 'missing shared name should leave the gate unfinished');
assert(missing.local.dump()['jdng.name'] === undefined, 'an empty visit should not invent a name');

missing.submit('  Siti  ');
assert(missing.hidden === true, 'submitting a name should close the gate');
assert(missing.name === 'Siti', 'the gate should keep the cleaned name');
assert(missing.local.dump()['jdng.name'] === 'Siti', 'submitting should write jdng.name');
assert(missing.local.dump()['ut.name'] === 'Siti', 'submitting should still write the game key');
assert(missing.input === 'Siti', 'submitting should fill the game name field');

missing.local.setItem('ut.name', 'Adi');
assert(missing.local.dump()['jdng.name'] === 'Adi', 'renaming through the game key should update jdng.name');

const renamed = page(missing.local.dump(), missing.session.dump());
assert(renamed.hidden === true, 'the next game should skip the gate after a rename');
assert(renamed.name === 'Adi', 'the next game should reuse the renamed shared name');
assert(renamed.input === 'Adi', 'the next game should prefill the shared name');

const cleared = missing.local.dump();
delete cleared['jdng.name'];
delete cleared['ut.name'];
const askedAgain = page(cleared, missing.session.dump());
assert(askedAgain.hidden === false, 'clearing the shared name should ask again even if the tab session remains');

const shared = page({ 'jdng.name': 'Baru' }, { 'jdng.ulartangga': 'Lama' });
assert(shared.hidden === true, 'a shared name should skip the gate');
assert(shared.name === 'Baru', 'the shared name should win over a stale per-game session');
assert(shared.input === 'Baru', 'a shared name should prefill an empty game field');

const legacy = page({ 'ut.name': 'Budi' }, {});
assert(legacy.hidden === true, 'an existing per-game name should skip the gate');
assert(legacy.local.dump()['jdng.name'] === 'Budi', 'an existing per-game name should seed jdng.name');
assert(legacy.name === 'Budi', 'the seeded name should be the gate name');

const jsonName = page({ 'bumerangsakti.name': JSON.stringify('Siti') }, {});
assert(jsonName.hidden === true, 'a JSON player name should seed the shared name and skip the gate');
assert(jsonName.local.dump()['jdng.name'] === 'Siti', 'a JSON player name should be stored as plain text');
assert(jsonName.local.getItem('bumerangsakti.name') === JSON.stringify('Siti'), 'seeding should leave the JSON value intact');

const staleSession = page({}, { 'jdng.ulartangga': 'Lama' });
assert(staleSession.hidden === false, 'a session name without jdng.name should ask again');
assert(staleSession.local.dump()['jdng.name'] === undefined, 'a stale session should not become the shared name');

const themed = page({ 'jadidaring-theme': 'dark', 'jdng.force': '1' }, {});
assert(themed.local.getItem('jadidaring-theme') === 'dark', 'unrelated localStorage should pass through');
assert(themed.local.getItem('jdng.force') === '1', 'jdng.force should not be replaced by the player name');
themed.session.setItem('room-token', 'abc');
assert(themed.session.dump()['room-token'] === 'abc', 'unrelated sessionStorage should pass through');
assert(themed.local.dump()['jdng.name'] === undefined, 'unrelated writes should not create a player name');

themed.local.setItem('kopisenja.name', JSON.stringify('Raka'));
assert(themed.local.dump()['jdng.name'] === 'Raka', 'a JSON name write should update jdng.name');
assert(themed.local.getItem('kopisenja.name') === JSON.stringify('Raka'), 'a JSON name write should stay JSON for that game');
themed.local.setItem('kopisenja.name', JSON.stringify({ coins: 3 }));
assert(themed.local.dump()['jdng.name'] === 'Raka', 'a JSON object should not replace the shared name');
themed.local.setItem('ut.name', '   ');
assert(themed.local.dump()['jdng.name'] === 'Raka', 'an empty rename should not clear the shared name');

const aquarium = page({ 'jdng.name': 'Budi' }, {});
assert((aquarium.local.getItem('drawquarium.name') || '') === 'Budi', 'drawquarium should read the shared name when it has none');
assert((aquarium.session.getItem('jdng.drawquarium') || '') === 'Budi', 'drawquarium session check should see the shared name');
assert(Boolean(aquarium.local.getItem('drawquarium.name') && aquarium.session.getItem('jdng.drawquarium')), 'drawquarium should skip its gate');

const ownAquarium = page({ 'drawquarium.name': 'Adi' }, {});
assert(ownAquarium.hidden === true, 'drawquarium.name should count as an existing player name');
assert(ownAquarium.local.getItem('drawquarium.name') === 'Adi', 'a saved drawquarium name should stay that name');
assert(ownAquarium.session.getItem('jdng.langitbersama') === 'Adi', 'langitbersama should reuse the seeded name');

const webdriver = page({}, {}, { webdriver: true });
assert(webdriver.hidden === true, 'webdriver should still skip the gate unless jdng.force is set');
assert(webdriver.done === false, 'webdriver skip should not pretend a name was entered');

const forced = page({ 'jdng.force': '1' }, {}, { webdriver: true });
assert(forced.hidden === false, 'jdng.force should still force the prompt');

const wallpaper = page({}, {}, { search: '?wallpaper=1' });
assert(wallpaper.hidden === true, 'wallpaper mode should still skip the prompt');

console.log('player-name bridge ok');
