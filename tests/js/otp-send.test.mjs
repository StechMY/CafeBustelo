import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/views/partials/otp-scripts.blade.php', import.meta.url), 'utf8')
    .replace(/^<script>\s*/, '').replace(/\s*<\/script>\s*$/, '')
    .replace("@json(__('otp'))", JSON.stringify({
        challenge_required: 'Verify first', phone_required: 'Phone required',
        sending: 'Sending', sent: 'Sent', sms_send_failed: 'Send failed', service_unavailable: 'Unavailable',
    }));

function setup(fetchResponse = async () => ({ ok: true, json: async () => ({ success: true, resend_seconds: 2 }) })) {
    const handlers = {};
    const input = { value: '' };
    const message = { textContent: '', classList: { remove() {}, toggle() {} } };
    const challenge = {
        clientWidth: 270,
        dataset: { sitekey: 'public-test-key', action: 'otp_register' },
        querySelector: (selector) => selector === '[name="turnstile_token"]' ? input : message,
    };
    const form = {
        querySelector: (selector) => selector === '[data-otp-challenge]' ? challenge
            : selector === '[name="turnstile_token"]' ? input : message,
        addEventListener: (event, handler) => { handlers[event] = handler; },
    };
    const button = {
        disabled: false, textContent: 'Send OTP',
        dataset: { otpUrl: '/register/send-otp', phoneInput: 'phone' },
        closest: () => form,
        addEventListener: (event, handler) => { handlers[event] = handler; },
    };
    const state = { requests: [], resets: 0, timer: null, cleared: false };
    const context = {
        document: {
            addEventListener: (_, fn) => fn(),
            querySelector: () => ({ content: 'csrf-value' }),
            querySelectorAll: (selector) => selector === '[data-otp-challenge]' ? [challenge]
                : selector === '[data-otp-form]' ? [form] : [button],
            getElementById: () => ({ value: '0912345678' }),
            createElement: () => ({}),
            head: { appendChild() {} },
        },
        window: { turnstile: {
            render: (_, options) => { state.widget = options; return 'widget-1'; },
            reset: () => { state.resets += 1; },
        } },
        fetch: async (url, options) => { state.requests.push({ url, ...options }); return fetchResponse(); },
        setInterval: (fn) => { state.timer = fn; return 1; },
        clearInterval: () => { state.cleared = true; },
    };
    vm.runInNewContext(source, context);
    context.window.initializeOtpChallenges();
    return { handlers, input, message, button, state };
}

test('missing/expired challenge never reaches OTP endpoint', async () => {
    const { handlers, state, message } = setup();
    await handlers.click();
    assert.equal(state.requests.length, 0);
    assert.equal(message.textContent, 'Verify first');
    state.widget.callback('fresh-token');
    state.widget['expired-callback']();
    await handlers.click();
    assert.equal(state.requests.length, 0);
    assert.equal(state.widget.size, 'compact');
});

test('successful send includes token/CSRF and resets token while countdown runs', async () => {
    const { handlers, state, input, button } = setup();
    state.widget.callback('fresh-token');
    await handlers.click();
    assert.deepEqual(JSON.parse(state.requests[0].body), { phone: '0912345678', turnstile_token: 'fresh-token' });
    assert.equal(state.requests[0].headers['X-CSRF-TOKEN'], 'csrf-value');
    assert.equal(input.value, '');
    assert.equal(state.resets, 1);
    assert.equal(button.disabled, true);
    state.timer(); state.timer();
    assert.equal(button.disabled, false);
    assert.equal(button.textContent, 'Send OTP');
    assert.equal(state.cleared, true);
});

test('validation rejection clears token and permits retry with a fresh challenge', async () => {
    const { handlers, state, input, button, message } = setup(async () => ({
        ok: false, json: async () => ({ message: 'Validation failed', errors: { phone: ['Invalid phone'] } }),
    }));
    state.widget.callback('fresh-token');
    await handlers.click();
    assert.equal(message.textContent, 'Invalid phone');
    assert.equal(input.value, '');
    assert.equal(button.disabled, false);
    assert.equal(state.resets, 1);
    await handlers.click();
    assert.equal(state.requests.length, 1);
});

test('network failure clears token and restores button', async () => {
    const { handlers, state, button, input, message } = setup(async () => { throw new Error('offline'); });
    state.widget.callback('fresh-token');
    await handlers.click();
    assert.equal(input.value, '');
    assert.equal(state.resets, 1);
    assert.equal(button.disabled, false);
    assert.equal(message.textContent, 'Send failed');
});

test('double click does not submit two requests', async () => {
    let complete;
    const { handlers, state } = setup(() => new Promise(resolve => { complete = resolve; }));
    state.widget.callback('fresh-token');
    const pending = handlers.click();
    await handlers.click();
    assert.equal(state.requests.length, 1);
    complete({ ok: true, json: async () => ({ success: true }) });
    await pending;
});

test('password form requires a token before submitting', () => {
    const { handlers, state } = setup();
    let prevented = 0;
    handlers.submit({ preventDefault: () => { prevented += 1; } });
    assert.equal(prevented, 1);
    state.widget.callback('fresh-token');
    handlers.submit({ preventDefault: () => { prevented += 1; } });
    assert.equal(prevented, 1);
});
