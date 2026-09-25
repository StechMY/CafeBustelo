import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/views/otp/app-challenge.blade.php', import.meta.url), 'utf8')
    .match(/<script>([\s\S]*?)<\/script>/)[1]
    .replace("@js(__('otp.service_unavailable'))", JSON.stringify('Unavailable'));

function setup({ sitekey = 'public-key', bridge = true } = {}) {
    const state = { messages: [], options: null };
    const challenge = { dataset: { sitekey, purpose: 'bank_account' } };
    const status = { textContent: '' };
    const window = {
        turnstile: { render: (_, options) => { state.options = options; } },
    };
    if (bridge) window.OtpChallenge = { postMessage: (message) => state.messages.push(JSON.parse(message)) };
    vm.runInNewContext(source, {
        window, document: { getElementById: (id) => id === 'challenge' ? challenge : status },
    });
    window.onOtpTurnstileReady();
    return { state, status };
}

test('widget uses matching action and sends token through the documented bridge', () => {
    const { state } = setup();
    assert.equal(state.options.action, 'otp_bank_account');
    assert.equal(state.options.sitekey, 'public-key');
    state.options.callback('one-use-token');
    assert.deepEqual(state.messages, [{ type: 'success', purpose: 'bank_account', token: 'one-use-token' }]);
});

test('expiry, timeout and errors tell the App to discard its token', () => {
    const { state, status } = setup();
    state.options['expired-callback']();
    state.options['timeout-callback']();
    state.options['error-callback']();
    assert.deepEqual(state.messages, ['expired', 'expired', 'error'].map(type => ({ type, purpose: 'bank_account' })));
    assert.equal(status.textContent, 'Unavailable');
});

test('missing configuration or bridge does not produce a successful result', () => {
    const missingKey = setup({ sitekey: '' });
    assert.equal(missingKey.state.options, null);
    assert.deepEqual(missingKey.state.messages, [{ type: 'error', purpose: 'bank_account' }]);
    const missingBridge = setup({ bridge: false });
    missingBridge.state.options.callback('one-use-token');
    assert.deepEqual(missingBridge.state.messages, []);
    assert.equal(missingBridge.status.textContent, 'Unavailable');
});
