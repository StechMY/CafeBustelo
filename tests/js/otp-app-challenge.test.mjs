import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/views/otp/app-challenge.blade.php', import.meta.url), 'utf8')
    .match(/<script>([\s\S]*?)<\/script>/)[1]
    .replace("@js(__('otp.challenge_load_failed'))", JSON.stringify('Unavailable'))
    .replace("@js(__('otp.challenge_app_required'))", JSON.stringify('Open in app'));

function setup({ sitekey = 'public-key', bridge = true, ready = true, renderThrows = false } = {}) {
    const state = { messages: [], options: null, timers: new Map() };
    const challenge = { dataset: { sitekey, purpose: 'bank_account' } };
    const status = { textContent: '' };
    const retry = { hidden: true };
    let nextTimer = 0;
    const window = {
        setTimeout: (callback, delay) => { state.timers.set(++nextTimer, { callback, delay }); return nextTimer; },
        clearTimeout: (id) => state.timers.delete(id),
        turnstile: { render: (_, options) => {
            if (renderThrows) throw new Error('render failed');
            state.options = options;
        } },
    };
    if (bridge) window.OtpChallenge = { postMessage: (message) => state.messages.push(JSON.parse(message)) };
    vm.runInNewContext(source, {
        window, document: { getElementById: (id) => id === 'challenge' ? challenge : id === 'retry' ? retry : status },
    });
    if (ready) window.onOtpTurnstileReady();
    return { state, status, retry };
}

test('widget uses matching action and sends token through the documented bridge', () => {
    const { state } = setup();
    assert.equal(state.options.action, 'otp_bank_account');
    assert.equal(state.options.sitekey, 'public-key');
    state.options.callback('one-use-token');
    assert.deepEqual(state.messages, [{ type: 'success', purpose: 'bank_account', token: 'one-use-token' }]);
    assert.equal(state.timers.size, 0);
});

test('expiry, timeout and errors tell the App to discard its token', () => {
    const { state, status } = setup();
    state.options['expired-callback']();
    state.options['timeout-callback']();
    state.options['error-callback']();
    assert.deepEqual(state.messages, [
        { type: 'expired', purpose: 'bank_account' }, { type: 'expired', purpose: 'bank_account' },
        { type: 'error', purpose: 'bank_account', code: 'challenge_error' },
    ]);
    assert.equal(status.textContent, 'Unavailable (challenge_error)');
});

test('missing configuration or bridge does not produce a successful result', () => {
    const missingKey = setup({ sitekey: '' });
    assert.equal(missingKey.state.options, null);
    assert.deepEqual(missingKey.state.messages, [{ type: 'error', purpose: 'bank_account', code: 'configuration_missing' }]);
    const missingBridge = setup({ bridge: false });
    missingBridge.state.options.callback('one-use-token');
    assert.deepEqual(missingBridge.state.messages, []);
    assert.equal(missingBridge.status.textContent, 'Open in app (bridge_missing)');
});

test('script and challenge stalls show a retry with distinct diagnostics', () => {
    for (const ready of [false, true]) {
        const { state, status, retry } = setup({ ready });
        const timer = [...state.timers.values()][0];
        assert.equal(timer.delay, ready ? 30000 : 20000);
        timer.callback();
        assert.equal(retry.hidden, false);
        const code = ready ? 'challenge_timeout' : 'script_timeout';
        assert.equal(status.textContent, `Unavailable (${code})`);
        assert.deepEqual(state.messages, [{ type: 'error', purpose: 'bank_account', code }]);
    }
});

test('interactive challenges are not interrupted by the loading watchdog', () => {
    const { state } = setup();
    state.options['before-interactive-callback']();
    assert.equal(state.timers.size, 0);
    assert.deepEqual(state.messages, []);
});

test('Cloudflare errors remain visible without an App bridge and never leak arbitrary error text', () => {
    const { state, status, retry } = setup({ bridge: false });
    state.options['error-callback']('200500');
    assert.equal(status.textContent, 'Unavailable (200500)');
    assert.equal(retry.hidden, false);
    state.options['error-callback']('<secret error payload>');
    assert.equal(status.textContent, 'Unavailable (challenge_error)');
});

test('render exceptions produce an actionable diagnostic', () => {
    const { state, status, retry } = setup({ renderThrows: true });
    assert.equal(status.textContent, 'Unavailable (render_failed)');
    assert.equal(retry.hidden, false);
    assert.equal(state.timers.size, 0);
    assert.equal(state.messages[0].code, 'render_failed');
});
