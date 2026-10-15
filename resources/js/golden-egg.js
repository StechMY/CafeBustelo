import '../css/golden-egg.css';
import '@phosphor-icons/web/thin';
import { gsap } from 'gsap';

const root = document.querySelector('[data-golden-egg]');

if (root) {
    const config = JSON.parse(document.getElementById('golden-egg-config').textContent);
    const game = root.querySelector('[data-game]');
    const scene = root.querySelector('[data-scene]');
    const stage = root.querySelector('[data-stage]');
    const eggs = Array.from(root.querySelectorAll('[data-egg]'));
    const status = root.querySelector('[data-status]');
    const reveal = root.querySelector('[data-reveal]');
    const sheet = root.querySelector('[data-sheet]');
    const confirmation = root.querySelector('[data-confirmation]');
    const retry = root.querySelector('[data-retry]');
    const closeButton = root.querySelector('[data-close-game]');
    const hammer = root.querySelector('.ge-hammer');
    const glow = root.querySelector('.ge-draw-glow');
    const cabinet = root.querySelector('.ge-cabinet');
    const chanceCounter = root.querySelector('[data-chances]');
    const storageKey = `golden-egg-pending:${config.user_id}`;
    const money = value => {
        const [whole, fraction = ''] = String(value).split('.');
        return `${config.currency}\u00a0${whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}.${fraction.padEnd(2, '0').slice(0, 2)}`;
    };
    const percentOff = value => config.labels.percent_off.replace(':percent', Number(value));
    const resultValue = prize => prize.type === 'cash' ? money(prize.amount) : percentOff(prize.discount_percent);
    let busy = false;
    let pending = null;
    let drawTimeline;
    let entrance;
    let sheetTrigger;
    let confirmationEgg = null;
    let agentDisplayChances = Number(chanceCounter.textContent);

    try {
        pending = JSON.parse(sessionStorage.getItem(storageKey));
        if (pending && (!pending.request_id || !Number.isInteger(pending.egg_number) || pending.egg_number < 1 || pending.egg_number > 9)) pending = null;
    } catch { /* The draw still works when browser storage is unavailable. */ }

    function savePending(value) {
        pending = value;
        try {
            if (value) sessionStorage.setItem(storageKey, JSON.stringify(value));
            else sessionStorage.removeItem(storageKey);
        } catch { /* Keep the request in memory for retries. */ }
    }

    function canPlay() {
        return config.is_virtual || (config.enabled && config.remaining_chances > 0);
    }

    function updateControls() {
        eggs.forEach(egg => { egg.disabled = busy || (!pending && !canPlay()); });
        closeButton.disabled = busy;
        root.querySelectorAll('[data-show-rewards], [data-show-rules], [data-invite]').forEach(button => { button.disabled = busy; });
        root.querySelector('[data-again]').hidden = !canPlay();
        root.querySelector('[data-finish]').className = canPlay() ? 'ge-text-button' : 'ge-gold-button';
        chanceCounter.textContent = config.is_virtual ? agentDisplayChances : config.remaining_chances;
        retry.hidden = !pending || busy;
        if (!busy && !pending) {
            status.textContent = !config.is_virtual && !config.enabled ? config.labels.closed
                : !canPlay() ? config.labels.no_chances : '';
        }
    }

    function openGame() {
        if (!game.open) game.showModal();
        document.documentElement.classList.add('ge-playing');
        game.focus({ preventScroll: true });
        updateControls();
        if (pending && !busy) status.textContent = config.labels.request_failed;
        entrance?.kill();
        entrance = gsap.timeline({ defaults: { ease: 'power2.out' } })
            .fromTo(root.querySelector('.ge-game-intro'), { y: 12, opacity: 0 }, { y: 0, opacity: 1, duration: .65 })
            .fromTo(cabinet, { y: 14, opacity: 0 }, { y: 0, opacity: 1, duration: .8 }, .1)
            .fromTo(eggs, { y: 7, opacity: 0 }, { y: 0, opacity: 1, duration: .6, stagger: .045 }, .25)
            .fromTo(root.querySelector('.ge-game-footer'), { y: 8, opacity: 0 }, { y: 0, opacity: 1, duration: .5 }, .6);
    }

    function closeGame() {
        if (busy) return;
        window.location.assign(config.dashboard_url);
    }

    function openConfirmation(eggNumber) {
        if (busy) return;
        if (pending) return draw(pending.egg_number);
        if (!canPlay()) return;
        entrance?.progress(1).kill();
        confirmationEgg = eggNumber;
        root.querySelector('[data-confirm-egg-label]').textContent = config.labels.confirm_egg.replace(':number', eggNumber);
        scene.inert = true;
        confirmation.hidden = false;
        gsap.fromTo(confirmation.querySelector('.ge-confirm-card'), { y: 15, opacity: 0 }, { y: 0, opacity: 1, duration: .3, ease: 'power3.out' });
        confirmation.querySelector('[data-confirm-draw]').focus({ preventScroll: true });
    }

    function closeConfirmation(restoreFocus = true) {
        const egg = eggs[confirmationEgg - 1];
        confirmationEgg = null;
        confirmation.hidden = true;
        scene.inert = false;
        gsap.killTweensOf(confirmation.querySelector('.ge-confirm-card'));
        if (restoreFocus) egg?.focus({ preventScroll: true });
    }

    function resetStage() {
        drawTimeline?.kill();
        gsap.killTweensOf([cabinet, hammer, glow, ...eggs, ...root.querySelectorAll('.ge-egg-body')]);
        gsap.set([cabinet, ...eggs, ...root.querySelectorAll('.ge-egg-body')], { clearProps: 'all' });
        gsap.set(root.querySelectorAll('.ge-egg-frame'), { clearProps: 'all' });
        gsap.set([hammer, glow], { clearProps: 'all' });
        eggs.forEach(egg => egg.classList.remove('ge-selected'));
    }

    function hideReveal() {
        reveal.hidden = true;
        scene.inert = false;
        resetStage();
        updateControls();
        (eggs.find(egg => !egg.disabled) || closeButton).focus({ preventScroll: true });
    }

    function openSheet(panel, trigger) {
        if (busy) return;
        if (!game.open) openGame();
        sheetTrigger = trigger;
        root.querySelector('[data-prizes-panel]').hidden = panel !== 'prizes';
        root.querySelector('[data-rules-panel]').hidden = panel !== 'rules';
        root.querySelector('#ge-sheet-title').textContent = config.labels[panel === 'prizes' ? 'my_prizes' : 'rules'];
        scene.inert = true;
        sheet.hidden = false;
        gsap.fromTo(sheet.querySelector('.ge-sheet-card'), { y: 45, opacity: 0 }, { y: 0, opacity: 1, duration: .4, ease: 'power3.out' });
        sheet.querySelector('[data-close-sheet]').focus({ preventScroll: true });
    }

    function closeSheet() {
        sheet.hidden = true;
        scene.inert = false;
        (game.contains(sheetTrigger) ? sheetTrigger : closeButton).focus({ preventScroll: true });
    }

    function addToHistory(prize) {
        if (root.querySelector(`[data-draw-id="${prize.id}"]`)) return;
        root.querySelector('[data-history-empty]')?.remove();
        const item = document.createElement('div');
        item.className = 'ge-history-item';
        item.dataset.drawId = prize.id;
        const icon = document.createElement('i');
        icon.className = `ph-thin ${prize.type === 'cash' ? 'ph-wallet' : 'ph-ticket'}`;
        icon.setAttribute('aria-hidden', 'true');
        const content = document.createElement('div');
        const value = document.createElement('strong');
        value.textContent = resultValue(prize);
        const name = document.createElement('span');
        name.textContent = prize.name;
        content.append(value, name);
        const time = document.createElement('time');
        time.dateTime = prize.created_at;
        time.textContent = new Date(prize.created_at).toLocaleString(undefined, { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
        item.append(icon, content, time);
        root.querySelector('[data-history]').prepend(item);
        if (prize.type === 'coupon') {
            root.querySelector('[data-coupon-empty]')?.remove();
            const ticket = document.createElement('a');
            ticket.className = 'ge-ticket';
            ticket.href = config.purchase_url;
            const icon = document.createElement('i');
            icon.className = 'ph-thin ph-ticket';
            icon.setAttribute('aria-hidden', 'true');
            const value = document.createElement('strong');
            value.textContent = percentOff(prize.discount_percent);
            const note = document.createElement('span');
            note.textContent = config.labels.coupon_note;
            ticket.append(icon, value, note);
            root.querySelector('[data-coupon-list]').prepend(ticket);
        }
    }

    function setPrizeValue(element, prize) {
        const parts = resultValue(prize).split(',');
        element.replaceChildren();
        parts.forEach((part, index) => {
            if (index) element.append(document.createElement('wbr'));
            element.append(document.createTextNode(part + (index < parts.length - 1 ? ',' : '')));
        });
    }

    function fitPrizeValues() {
        if (reveal.hidden) return;
        const values = Array.from(reveal.querySelectorAll('[data-result-value], [data-cell-value]'));
        values.forEach(value => value.style.removeProperty('font-size'));
        const context = document.createElement('canvas').getContext('2d');
        const sizes = values.map(value => {
            const style = getComputedStyle(value);
            const baseSize = parseFloat(style.fontSize);
            context.font = `${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
            const textWidth = context.measureText(value.textContent).width
                + (parseFloat(style.letterSpacing) || 0) * (value.textContent.length - 1);
            const minimum = value.matches('[data-result-value]') ? 20 : 10;
            return Math.max(minimum, Math.min(baseSize, baseSize * (value.clientWidth - 2) / textWidth));
        });
        values.forEach((value, index) => { value.style.fontSize = `${sizes[index]}px`; });
    }

    function showPrize(prize) {
        // Display positions do not affect the award already chosen by the server.
        const otherPrizes = config.prizes.filter(item => item.id !== prize.prize_id);
        for (let i = otherPrizes.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [otherPrizes[i], otherPrizes[j]] = [otherPrizes[j], otherPrizes[i]];
        }
        const cells = root.querySelectorAll('[data-result-cell]');
        cells.forEach((cell, index) => {
            const selected = index === prize.egg_number - 1;
            const reward = selected ? prize : otherPrizes.shift();
            cell.classList.toggle('ge-result-selected', selected);
            cell.querySelector('[data-result-winner]').hidden = !selected;
            cell.querySelector('[data-cell-icon]').className = `ph-thin ${reward.type === 'cash' ? 'ph-wallet' : 'ph-ticket'}`;
            setPrizeValue(cell.querySelector('[data-cell-value]'), reward);
            cell.querySelector('[data-cell-type]').textContent = config.labels[reward.type];
            cell.setAttribute('aria-label', `${config.labels.confirm_egg.replace(':number', index + 1)}: ${resultValue(reward)}${selected ? `, ${config.labels.your_prize}` : ''}`);
        });
        root.querySelector('[data-result-type]').textContent = config.labels[prize.type];
        setPrizeValue(root.querySelector('[data-result-value]'), prize);
        root.querySelector('[data-result-name]').textContent = prize.name;
        root.querySelector('[data-result-note]').textContent = config.labels[`${prize.type}_received`];
        scene.inert = true;
        reveal.hidden = false;
        fitPrizeValues();
        gsap.timeline({ defaults: { ease: 'power3.out' } })
            .fromTo(reveal, { opacity: 0 }, { opacity: 1, duration: .45 })
            .fromTo(root.querySelector('.ge-reveal-egg'), { y: 24, scale: .86, opacity: 0 }, { y: 0, scale: 1, opacity: 1, duration: .9 }, .1)
            .fromTo(root.querySelectorAll('.ge-reveal-type, .ge-reveal-value, .ge-reveal-content h3, .ge-reveal-note'), { y: 18, opacity: 0 }, { y: 0, opacity: 1, duration: .7, stagger: .09 }, .3)
            .fromTo(root.querySelector('.ge-result-board'), { opacity: 0 }, { opacity: 1, duration: .5 }, .45)
            .fromTo(cells, { y: 6, opacity: 0 }, { y: 0, opacity: 1, duration: .45, stagger: .065 }, .6);
        root.querySelector(canPlay() ? '[data-again]' : '[data-finish]').focus({ preventScroll: true });
    }

    async function animateCrack(egg) {
        const body = egg.querySelector('.ge-egg-body');
        const sealed = egg.querySelector('.ge-egg-sealed');
        const one = egg.querySelector('.ge-egg-crack-one');
        const two = egg.querySelector('.ge-egg-crack-two');
        const shattered = egg.querySelector('.ge-egg-shattered');
        status.textContent = config.labels.cracking;
        await new Promise(resolve => {
            drawTimeline = gsap.timeline({ onComplete: resolve, defaults: { ease: 'power2.inOut' } });
            drawTimeline.set(hammer, { rotation: 22, x: 8, y: -18, opacity: 0 })
                .to(hammer, { opacity: 1, duration: .3 }, 0)
                .addLabel('first', .55)
                .to(hammer, { rotation: -43, x: 0, y: 0, duration: .16, ease: 'power3.in' }, 'first')
                .to(sealed, { opacity: 0, duration: .13 }, 'first+=.16')
                .to(one, { opacity: 1, duration: .13 }, 'first+=.16')
                .to(body, { rotation: -4, x: -3, duration: .06 }, 'first+=.16')
                .to(body, { rotation: 0, x: 0, duration: .32, ease: 'elastic.out(1,.35)' }, 'first+=.22')
                .to(hammer, { rotation: 18, y: -16, duration: .5 }, 'first+=.25')
                .to(glow, { opacity: .2, scale: .7, duration: .6 }, 'first+=.18')
                .addLabel('second', 1.65)
                .to(hammer, { rotation: -45, y: 0, duration: .18, ease: 'power3.in' }, 'second')
                .to(one, { opacity: 0, duration: .16 }, 'second+=.18')
                .to(two, { opacity: 1, duration: .16 }, 'second+=.18')
                .to(body, { rotation: 4, x: 4, duration: .07 }, 'second+=.18')
                .to(body, { rotation: 0, x: 0, duration: .4, ease: 'elastic.out(1,.3)' }, 'second+=.25')
                .to(hammer, { rotation: 28, y: -22, duration: .55 }, 'second+=.28')
                .to(glow, { opacity: .55, scale: 1, duration: .85 }, 'second+=.18')
                .addLabel('open', 2.9)
                .to(hammer, { rotation: -47, y: 1, duration: .2, ease: 'power4.in' }, 'open')
                .to(two, { opacity: 0, duration: .2 }, 'open+=.2')
                .to(shattered, { opacity: 1, duration: .35 }, 'open+=.2')
                .to(body, { scale: 1.04, duration: .18, yoyo: true, repeat: 1 }, 'open+=.2')
                .to(hammer, { rotation: -20, x: 65, y: -25, opacity: 0, duration: .5 }, 'open+=.3')
                .to(glow, { opacity: 1, scale: 1.45, duration: .55, ease: 'power2.out' }, 'open+=.2')
                .to(glow, { opacity: .35, duration: .13 }, 'open+=.8')
                .to(glow, { opacity: 1, scale: 1.65, duration: .15 }, 'open+=.93')
                .to(egg, { opacity: 0, y: '-=10', duration: .45 }, 'open+=1.1');
        });
    }

    async function draw(eggNumber) {
        if (busy || (!pending && !canPlay())) return;
        entrance?.progress(1).kill();
        resetStage();
        busy = true;
        const request = pending || { request_id: crypto.randomUUID(), egg_number: eggNumber };
        savePending(request);
        const egg = eggs[request.egg_number - 1];
        const stageBox = stage.getBoundingClientRect();
        const eggBox = egg.getBoundingClientRect();
        egg.classList.add('ge-selected');
        const focus = new Promise(resolve => gsap.to(egg, {
            x: stageBox.left + stageBox.width / 2 - eggBox.left - eggBox.width / 2,
            y: stageBox.top + stageBox.height / 2 - eggBox.top - eggBox.height / 2,
            scale: 2.2, duration: .75, ease: 'power3.inOut', onComplete: resolve,
        }));
        gsap.to(eggs.filter(other => other !== egg), { opacity: .12, scale: .95, duration: .5 });
        gsap.to(cabinet, { opacity: .4, duration: .65 });
        status.textContent = config.labels.preparing;
        updateControls();
        let prize;
        try {
            const response = await fetch(config.draw_url, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify(request), signal: AbortSignal.timeout(15000),
            });
            const body = await response.json();
            if (!response.ok || !body.success) {
                if (response.status >= 400 && response.status < 500) savePending(null);
                throw new Error(body.message || config.labels.request_failed);
            }
            prize = body.data.prize;
            config.remaining_chances = body.data.remaining_chances;
            if (config.is_virtual) {
                const jump = Math.floor(Math.random() * 9) + 1;
                agentDisplayChances = (agentDisplayChances - 1 + jump) % 10 + 1;
            }
            savePending(null);
        } catch (error) {
            busy = false;
            resetStage();
            updateControls();
            status.textContent = pending ? config.labels.request_failed : error.message;
            return;
        }
        addToHistory(prize);
        await Promise.all([focus, ...Array.from(egg.querySelectorAll('img')).map(img => img.decode().catch(() => {})), hammer.decode().catch(() => {})]);
        await animateCrack(egg);
        busy = false;
        updateControls();
        showPrize(prize);
    }

    eggs.forEach(egg => egg.addEventListener('click', () => openConfirmation(Number(egg.dataset.egg))));
    root.querySelector('[data-confirm-draw]').addEventListener('click', () => {
        if (confirmationEgg === null || busy) return;
        const eggNumber = confirmationEgg;
        closeConfirmation(false);
        draw(eggNumber);
    });
    root.querySelector('[data-cancel-confirmation]').addEventListener('click', () => closeConfirmation());
    confirmation.addEventListener('click', event => { if (event.target === confirmation) closeConfirmation(); });
    retry.addEventListener('click', () => pending && draw(pending.egg_number));
    closeButton.addEventListener('click', closeGame);
    root.querySelectorAll('[data-show-rewards]').forEach(button => button.addEventListener('click', () => openSheet('prizes', button)));
    root.querySelector('[data-show-rules]').addEventListener('click', event => openSheet('rules', event.currentTarget));
    root.querySelector('[data-close-sheet]').addEventListener('click', closeSheet);
    root.querySelector('[data-again]').addEventListener('click', hideReveal);
    root.querySelector('[data-finish]').addEventListener('click', hideReveal);
    game.addEventListener('cancel', event => {
        event.preventDefault();
        if (!busy) {
            if (!confirmation.hidden) closeConfirmation();
            else if (!reveal.hidden) hideReveal();
            else if (!sheet.hidden) closeSheet();
            else closeGame();
        }
    });
    root.querySelector('[data-invite]').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(config.invite_url);
            status.textContent = config.labels.copied;
        } catch {
            root.querySelector('[data-invite-link]').hidden = false;
            root.querySelector('#ge-invite-url').select();
            status.textContent = config.labels.copy_failed;
        }
    });
    window.addEventListener('resize', fitPrizeValues);
    document.fonts.ready.then(fitPrizeValues);
    openGame();
}
