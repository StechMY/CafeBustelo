export function createGoldenEggAudio(root, config) {
    const button = root.querySelector('[data-toggle-sound]');
    const AudioEngine = window.AudioContext || window.webkitAudioContext;
    if (!AudioEngine) {
        button.hidden = true;
        return { playEffect() {}, pause() {} };
    }

    const context = new AudioEngine();
    const buffers = {};
    const effects = new Set();
    let muted = false;
    try { muted = localStorage.getItem('golden-egg-sound') === 'off'; } catch { /* Keep this visit's choice in memory. */ }

    function updateButton() {
        const playing = !muted && context.state === 'running';
        const label = config.labels[playing ? 'sound_off' : 'sound_on'];
        button.setAttribute('aria-pressed', String(playing));
        button.setAttribute('aria-label', label);
        button.title = label;
        button.querySelector('i').className = `ph-thin ${playing ? 'ph-speaker-high' : 'ph-speaker-slash'}`;
    }

    function pause() {
        effects.forEach(source => source.stop());
        effects.clear();
        context.suspend().catch(() => {});
    }

    function resume() {
        if (muted || document.hidden) return;
        context.resume().catch(updateButton);
    }

    Object.entries(config.audio).forEach(async ([name, url]) => {
        try {
            const response = await fetch(url);
            if (!response.ok) return;
            buffers[name] = await context.decodeAudioData(await response.arrayBuffer());
            if (name === 'ambience') {
                const music = context.createBufferSource();
                music.buffer = buffers[name];
                music.loop = true;
                const gain = context.createGain();
                gain.gain.value = 0.22;
                music.connect(gain).connect(context.destination);
                music.start();
            }
        } catch { /* Unavailable audio must never interrupt the draw. */ }
    });

    function playEffect(name, volume = 0.7) {
        if (muted || document.hidden || context.state !== 'running' || !buffers[name]) return;
        const source = context.createBufferSource();
        source.buffer = buffers[name];
        const gain = context.createGain();
        gain.gain.value = volume;
        source.connect(gain).connect(context.destination);
        source.onended = () => {
            effects.delete(source);
            source.disconnect();
            gain.disconnect();
        };
        effects.add(source);
        source.start();
    }

    button.addEventListener('click', () => {
        muted = !muted && context.state === 'running';
        try { localStorage.setItem('golden-egg-sound', muted ? 'off' : 'on'); } catch { /* Storage is optional. */ }
        if (muted) pause();
        else resume();
        updateButton();
    });
    root.addEventListener('click', event => {
        if (!event.target.closest('[data-toggle-sound]')) resume();
    });
    context.addEventListener('statechange', updateButton);
    document.addEventListener('visibilitychange', () => document.hidden ? pause() : resume());
    window.addEventListener('pagehide', pause);
    window.addEventListener('pageshow', resume);
    updateButton();
    if (muted || document.hidden) pause();
    else resume();

    return { playEffect, pause };
}
