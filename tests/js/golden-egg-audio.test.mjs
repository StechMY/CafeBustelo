import test from 'node:test';
import assert from 'node:assert/strict';
import { createGoldenEggAudio } from '../../resources/js/golden-egg-audio.js';

class Events {
    listeners = new Map();
    addEventListener(name, callback) {
        const callbacks = this.listeners.get(name) || [];
        callbacks.push(callback);
        this.listeners.set(name, callbacks);
    }
    emit(name, event = { target: { closest: () => null } }) {
        this.listeners.get(name)?.forEach(callback => callback(event));
    }
}

function setup({ preference, blocked = false, brokenFiles = false } = {}) {
    const button = new Events();
    button.attributes = {};
    button.icon = {};
    button.setAttribute = (key, value) => { button.attributes[key] = value; };
    button.querySelector = () => button.icon;
    const root = new Events();
    root.querySelector = () => button;
    const storage = new Map(preference ? [['golden-egg-sound', preference]] : []);
    globalThis.localStorage = { getItem: key => storage.get(key), setItem: (key, value) => storage.set(key, value) };
    globalThis.document = Object.assign(new Events(), { hidden: false });
    globalThis.window = new Events();
    globalThis.fetch = async () => {
        if (brokenFiles) throw new Error('Network unavailable');
        return { ok: true, arrayBuffer: async () => new ArrayBuffer(1) };
    };
    let context;
    window.AudioContext = class extends Events {
        state = blocked ? 'suspended' : 'running';
        blocked = blocked;
        sources = [];
        destination = {};
        constructor() { super(); context = this; }
        resume() {
            if (this.blocked) return Promise.reject(new Error('Autoplay blocked'));
            this.state = 'running';
            this.emit('statechange');
            return Promise.resolve();
        }
        suspend() {
            this.state = 'suspended';
            this.emit('statechange');
            return Promise.resolve();
        }
        decodeAudioData() { return Promise.resolve({ duration: 1 }); }
        createGain() { return { gain: {}, connect() {}, disconnect() {} }; }
        createBufferSource() {
            const source = {
                connect(node) { return node; },
                start() { this.started = true; },
                stop() { this.stopped = true; this.onended?.(); },
                disconnect() {},
            };
            this.sources.push(source);
            return source;
        }
    };
    const audio = createGoldenEggAudio(root, {
        audio: { ambience: '/music.mp3', hammer: '/hit.mp3' },
        labels: { sound_on: 'Enable', sound_off: 'Mute' },
    });
    return { audio, button, root, storage, context };
}

const loaded = () => new Promise(resolve => setImmediate(resolve));

test('blocked autoplay can start after a gesture; muting stops effects and survives other clicks', async () => {
    const { audio, button, root, storage, context } = setup({ blocked: true });
    await loaded();
    assert.equal(button.attributes['aria-pressed'], 'false');
    assert.equal(context.sources.filter(source => source.loop).length, 1);
    audio.playEffect('hammer');
    assert.equal(context.sources.length, 1);
    context.blocked = false;
    root.emit('click');
    assert.equal(button.attributes['aria-pressed'], 'true');
    audio.playEffect('hammer');
    const effect = context.sources.at(-1);
    assert.equal(effect.started, true);
    button.emit('click');
    assert.equal(effect.stopped, true);
    assert.equal(storage.get('golden-egg-sound'), 'off');
    root.emit('click');
    assert.equal(context.state, 'suspended');
});

test('remembered mute prevents an autoplay-allowed engine from playing on entry', async () => {
    const { audio, button, root, context } = setup({ preference: 'off' });
    await loaded();
    root.emit('click');
    audio.playEffect('hammer');
    assert.equal(context.state, 'suspended');
    assert.equal(button.attributes['aria-pressed'], 'false');
    assert.equal(context.sources.length, 1);
    button.emit('click');
    assert.equal(context.state, 'running');
});

test('backgrounding stops current effects and resumes only the existing music loop', async () => {
    const { audio, context } = setup();
    await loaded();
    audio.playEffect('hammer');
    const effect = context.sources.at(-1);
    document.hidden = true;
    document.emit('visibilitychange');
    assert.equal(context.state, 'suspended');
    assert.equal(effect.stopped, true);
    document.hidden = false;
    document.emit('visibilitychange');
    assert.equal(context.state, 'running');
    assert.equal(context.sources.filter(source => source.loop).length, 1);
    window.emit('pagehide');
    assert.equal(context.state, 'suspended');
});

test('missing audio files leave the game usable without unhandled playback errors', async () => {
    const { audio, context } = setup({ brokenFiles: true });
    await loaded();
    assert.doesNotThrow(() => audio.playEffect('hammer'));
    assert.doesNotThrow(() => audio.pause());
    assert.equal(context.sources.length, 0);
});
