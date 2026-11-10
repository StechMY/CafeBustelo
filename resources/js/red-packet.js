import '../css/red-packet.css';
import { gsap } from 'gsap';

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
    let resultTimeline;
    const packet = root.querySelector('.rp-packet');
    const closedFrame = root.querySelector('[data-rp-closed-frame]');
    const openFrame = root.querySelector('[data-rp-open-frame]');
    const glow = root.querySelector('[data-rp-glow]');
    const rain = [...root.querySelectorAll('.rp-rain span')];
    const openingTimeline = gsap.timeline({ paused: true, defaults: { ease: 'power2.out' } });
    openingTimeline
        .to(packet, { scale: .95, rotation: -5, duration: .2 }, 0)
        .to(packet, { rotation: 5, duration: .12, repeat: 3, yoyo: true, ease: 'sine.inOut' }, .2)
        .to(packet, { rotation: 0, scale: 1.04, y: -8, duration: .5 }, .68)
        .to(closedFrame, { autoAlpha: 0, duration: .32 }, .7)
        .to(openFrame, { autoAlpha: 1, duration: .42 }, .76)
        .fromTo(glow, { autoAlpha: 0, scale: .65 }, { autoAlpha: .8, scale: 1.25, duration: .6 }, .65)
        .fromTo(rain, {
            autoAlpha: 0, y: 0, rotation: i => i % 2 ? -22 : 18, scale: i => .55 + (i % 3) * .12,
        }, {
            autoAlpha: .85, y: 440, x: i => i % 2 ? 30 : -25, rotation: i => i % 2 ? 40 : -35,
            duration: 1.3, stagger: { each: .035, from: 'edges' }, ease: 'power1.in',
        }, .1)
        .to(rain, { autoAlpha: 0, duration: .25, stagger: .015 }, 1.4)
        .to(glow, { autoAlpha: .25, scale: 1.4, duration: .4 }, 1.35);
    const animateOpening = () => new Promise(resolve => {
        packet.style.willChange = 'transform';
        openingTimeline.eventCallback('onComplete', resolve);
        openingTimeline.restart();
    });
    const resetOpening = () => {
        openingTimeline.pause(0);
        gsap.set([packet, closedFrame, openFrame, glow, ...rain], { clearProps: 'transform,opacity,visibility,willChange' });
    };
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
        const animation = animateOpening();
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
            const resultArt = root.querySelector('[data-rp-result-art]');
            const art = config.prize_artwork[prize.prize_name] || ({ cash: 'cash', coupon: 'coupon' }[prize.prize_type] || 'gift');
            resultArt.className = `rp-art rp-art--${art}`;
            result.showModal();
            resultTimeline?.kill();
            resultTimeline = gsap.timeline({ defaults: { ease: 'power3.out' } })
                .fromTo(result.querySelector('.rp-result-inner'), { autoAlpha: 0, y: 22 }, { autoAlpha: 1, y: 0, duration: .38 })
                .fromTo(resultArt, { scale: .65, rotation: -8 }, { scale: 1, rotation: 0, duration: .7, ease: 'back.out(1.4)' }, 0);
            status.textContent = '';
        } catch (error) {
            status.textContent = error.name === 'AbortError' || error instanceof TypeError || error instanceof SyntaxError ? config.labels.retry : error.message;
        } finally {
            clearTimeout(timeout); busy = false;
            root.classList.remove('is-opening');
            resetOpening();
            const message = status.textContent;
            updateControls();
            if (message) status.textContent = message;
        }
    }
    buttons.forEach(button => button.addEventListener('click', openPacket));
    retry.addEventListener('click', openPacket);
    root.querySelector('[data-rp-close]').addEventListener('click', () => result.close());
    result.addEventListener('close', () => { resultTimeline?.kill(); trigger?.focus(); });
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

const ledger = document.querySelector('[data-rp-history]');
if (ledger) {
    const tabs = [...ledger.querySelectorAll('[data-rp-tab]')];
    const panels = [...ledger.querySelectorAll('[data-rp-panel]')];
    ledger.querySelector('.rp-history-tabs').setAttribute('role', 'tablist');
    tabs.forEach(tab => {
        tab.setAttribute('role', 'tab');
        tab.setAttribute('aria-controls', tab.hash.slice(1));
    });
    panels.forEach(panel => { panel.setAttribute('role', 'tabpanel'); panel.tabIndex = 0; });
    function selectTab(tab, updateHash = false) {
        tabs.forEach(item => {
            const selected = item === tab;
            item.setAttribute('aria-selected', String(selected));
            item.tabIndex = selected ? 0 : -1;
        });
        panels.forEach(panel => { panel.hidden = `#${panel.id}` !== tab.hash; });
        if (updateHash) history.replaceState(null, '', tab.hash);
    }
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', event => { event.preventDefault(); selectTab(tab, true); });
        tab.addEventListener('keydown', event => {
            let target;
            if (event.key === 'ArrowRight') target = tabs[(index + 1) % tabs.length];
            if (event.key === 'ArrowLeft') target = tabs[(index + tabs.length - 1) % tabs.length];
            if (event.key === 'Home') target = tabs[0];
            if (event.key === 'End') target = tabs.at(-1);
            if (!target) return;
            event.preventDefault(); selectTab(target, true); target.focus();
        });
    });
    const syncTab = () => selectTab(tabs.find(tab => tab.hash === location.hash) || tabs[0]);
    window.addEventListener('hashchange', syncTab);
    syncTab();
}
