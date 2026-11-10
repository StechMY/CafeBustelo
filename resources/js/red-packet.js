import '../css/red-packet.css';

const root = document.querySelector('[data-red-packet]');
if (root) {
    const config = JSON.parse(document.getElementById('red-packet-config').textContent);
    const buttons = [...root.querySelectorAll('[data-rp-open]')];
    const status = root.querySelector('[data-rp-status]');
    const counter = root.querySelector('[data-rp-chances]');
    const retry = root.querySelector('[data-rp-retry]');
    const result = root.querySelector('[data-rp-result]');
    const storageKey = `red-packet-pending:${config.user_id}`;
    let busy = false;
    let pending = null;
    let trigger = buttons[0];
    try {
        const saved = sessionStorage.getItem(storageKey);
        if (/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(saved || '')) pending = saved;
    } catch { /* In-memory retries still preserve the request ID. */ }
    const save = value => {
        pending = value;
        try { value ? sessionStorage.setItem(storageKey, value) : sessionStorage.removeItem(storageKey); } catch { /* Optional persistence. */ }
    };
    const active = () => config.enabled && (!config.ends_at || Date.now() < Date.parse(config.ends_at));
    function updateControls() {
        const canOpen = active() && (config.is_virtual || config.remaining_chances > 0);
        buttons.forEach(button => { button.disabled = busy || (!pending && !canOpen); });
        retry.hidden = !pending || busy;
        if (!busy && !pending) status.textContent = !active() ? config.labels.closed : !canOpen ? config.labels.no_chances : '';
        root.setAttribute('aria-busy', String(busy));
    }
    const uuid = () => {
        if (crypto.randomUUID) return crypto.randomUUID();
        const bytes = crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
        const hex = [...bytes].map(v => v.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`;
    };
    async function openPacket(event) {
        if (busy) return;
        if (!pending && (!active() || (!config.is_virtual && config.remaining_chances < 1))) return updateControls();
        trigger = event?.currentTarget || trigger;
        if (!pending) save(uuid());
        busy = true; updateControls();
        status.textContent = config.labels.opening;
        root.classList.add('is-opening');
        const animation = new Promise(resolve => setTimeout(resolve, 1100));
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 20000);
        try {
            const response = await fetch(config.url, {
                method: 'POST', signal: controller.signal,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ request_id: pending }),
            });
            const payload = await response.json();
            await animation;
            if (!response.ok || !payload.success) {
                if ([400, 422].includes(response.status)) save(null);
                throw new Error(response.status < 500 && payload.message ? payload.message : config.labels.retry);
            }
            save(null);
            config.remaining_chances = payload.data.remaining_chances;
            if (config.is_virtual) {
                const old = Number(counter.textContent);
                counter.textContent = ((old - 1 + 1 + Math.floor(Math.random() * 9)) % 10) + 1;
            } else counter.textContent = config.remaining_chances;
            const prize = payload.data.prize;
            root.querySelector('[data-rp-prize]').textContent = prize.prize_name;
            root.querySelector('[data-rp-reward-note]').textContent = config.labels[`${prize.prize_type}_received`];
            result.showModal();
            status.textContent = '';
        } catch (error) {
            status.textContent = error.name === 'AbortError' || error instanceof TypeError || error instanceof SyntaxError ? config.labels.retry : error.message;
        } finally {
            clearTimeout(timeout); busy = false;
            root.classList.remove('is-opening');
            const message = status.textContent;
            updateControls();
            if (message) status.textContent = message;
        }
    }
    buttons.forEach(button => button.addEventListener('click', openPacket));
    retry.addEventListener('click', openPacket);
    root.querySelector('[data-rp-close]').addEventListener('click', () => result.close());
    result.addEventListener('close', () => trigger?.focus());
    root.querySelector('[data-rp-copy]').addEventListener('click', async () => {
        const input = root.querySelector('#rp-invite-link');
        const feedback = root.querySelector('[data-rp-copy-status]');
        try { await navigator.clipboard.writeText(input.value); feedback.textContent = config.labels.copied; }
        catch { input.focus(); input.select(); feedback.textContent = config.labels.copy_manual; }
    });
    updateControls();
    if (pending) status.textContent = config.labels.retry;
    document.addEventListener('visibilitychange', () => { if (!document.hidden && !busy) updateControls(); });
}
