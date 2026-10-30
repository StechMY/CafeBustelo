import { gsap } from 'gsap';

const root = document.querySelector('[data-treasure-chest]');

if (root) {
    const config = JSON.parse(document.getElementById('treasure-chest-config').textContent);
    const feedback = root.querySelector('[data-feedback]');
    const dialog = root.querySelector('[data-reveal]');
    const stage = dialog.querySelector('.tc-reveal-chest');
    const closedFrame = dialog.querySelector('.tc-frame-closed');
    const ajarFrame = dialog.querySelector('.tc-frame-ajar');
    const openFrame = dialog.querySelector('.tc-frame-open');
    const glow = dialog.querySelector('.tc-reveal-glow');
    const rays = dialog.querySelector('.tc-reveal-rays');
    const particles = dialog.querySelectorAll('.tc-particles span');
    const resultPanel = dialog.querySelector('.tc-reveal-result');
    let activity = config.activity;
    let busy = false;
    let reducedMotion = false;
    let revealTimeline;
    let trigger;

    const media = gsap.matchMedia();
    media.add({
        reduce: '(prefers-reduced-motion: reduce)',
        full: '(prefers-reduced-motion: no-preference)',
    }, (context) => {
        reducedMotion = context.conditions.reduce;
        if (reducedMotion) revealTimeline?.progress(1);
    });

    const chestName = number => number === 9
        ? config.labels.ultimate : config.labels.chest_number.replace(':number', number);

    const render = () => {
        root.querySelector('[data-total-keys]').textContent = activity.total_keys;
        root.querySelector('[data-closed]').hidden = activity.enabled;
        root.querySelector('.tc-grid').setAttribute('aria-busy', String(busy));
        for (const chest of activity.chests) {
            const card = root.querySelector(`[data-chest="${chest.number}"]`);
            const button = card.querySelector('[data-claim]');
            const label = config.labels[chest.claimed ? 'claimed' : chest.claimable ? 'claim' : 'locked'];
            card.classList.toggle('is-claimed', chest.claimed);
            card.classList.toggle('is-available', chest.claimable);
            card.querySelector('.tc-sprite').classList.toggle('tc-sprite-open', chest.claimed);
            card.querySelector('.tc-seal i').className = `ph-thin ${chest.claimed ? 'ph-check' : chest.claimable ? 'ph-key' : 'ph-lock-key'}`;
            button.disabled = busy || !chest.claimable;
            button.textContent = label;
            button.setAttribute('aria-label', `${chestName(chest.number)} · ${label}`);
        }
    };

    const reveal = (claim) => new Promise((resolve) => {
        const rewards = [];
        if (Number(claim.amount) > 0) rewards.push(`${Number(claim.amount).toLocaleString('en-US')} VND`);
        if (Number(claim.keys) > 0) rewards.push(config.labels.key_count.replace(':count', claim.keys));
        if (Number(claim.discount_percent) > 0) rewards.push(config.labels.percent_off.replace(':percent', Number(claim.discount_percent)));
        const list = dialog.querySelector('[data-reveal-rewards]');
        list.replaceChildren(...rewards.map(value => {
            const item = document.createElement('li');
            item.textContent = value;
            return item;
        }));
        dialog.querySelector('[data-reveal-number]').textContent = chestName(Number(claim.chest_number));
        dialog.querySelector('[data-reveal-status]').textContent = '';
        dialog.querySelectorAll('.tc-sprite').forEach(sprite => {
            sprite.classList.toggle('tc-sprite-ultimate', Number(claim.chest_number) === 9);
        });
        gsap.set(stage, { clearProps: 'transform' });
        gsap.set(closedFrame, { autoAlpha: 1 });
        gsap.set([ajarFrame, openFrame, glow, rays, resultPanel, particles], { autoAlpha: 0 });
        gsap.set([glow, rays, resultPanel, particles], { clearProps: 'transform' });
        resultPanel.inert = true;
        dialog.showModal();
        dialog.scrollTop = 0;
        document.documentElement.classList.add('tc-reveal-visible');

        const finish = () => {
            resultPanel.inert = false;
            dialog.querySelector('[data-reveal-status]').textContent = config.labels.success;
            resolve();
        };
        revealTimeline = gsap.timeline({ paused: true, defaults: { ease: 'power2.out' }, onComplete: finish });
        revealTimeline
            .fromTo(stage, { scale: .88, y: 16 }, { scale: 1, y: 0, duration: .35 })
            .to(stage, { rotation: -3, duration: .075, ease: 'sine.inOut' })
            .to(stage, { rotation: 3, duration: .075, repeat: 3, yoyo: true, ease: 'sine.inOut' })
            .to(stage, { rotation: 0, duration: .08 })
            .addLabel('open')
            .to(closedFrame, { autoAlpha: 0, duration: .12 }, 'open')
            .to(ajarFrame, { autoAlpha: 1, duration: .12 }, 'open')
            .fromTo(glow, { scale: .4, autoAlpha: 0 }, { scale: 1, autoAlpha: .65, duration: .4 }, 'open')
            .to(ajarFrame, { autoAlpha: 0, duration: .2 }, 'open+=.22')
            .to(openFrame, { autoAlpha: 1, duration: .22 }, 'open+=.22')
            .to(stage, { y: -9, scale: 1.04, duration: .35, ease: 'back.out(1.5)' }, 'open+=.22')
            .to(glow, { autoAlpha: .9, scale: 1.15, duration: .3 }, 'open+=.22')
            .fromTo(rays, { rotation: -18, scale: .7, autoAlpha: 0 }, { rotation: 12, scale: 1.1, autoAlpha: .8, duration: 1.1 }, 'open+=.22')
            .fromTo(particles, { x: 0, y: 0, scale: .4, autoAlpha: 0 }, {
                x: index => Math.cos(index * 2.399) * (65 + index % 5 * 18),
                y: index => -40 - Math.abs(Math.sin(index * 2.399)) * 150,
                rotation: index => index * 37,
                scale: 1, autoAlpha: 1, duration: .55, stagger: .012,
            }, 'open+=.22')
            .to(particles, { autoAlpha: 0, y: '-=22', duration: .45, stagger: .01 }, 'open+=.8')
            .fromTo(resultPanel, { autoAlpha: 0, y: 14 }, { autoAlpha: 1, y: 0, duration: .4 }, 'open+=.65')
            .to(glow, { autoAlpha: .5, duration: .4 }, 'open+=.95');
        if (reducedMotion) revealTimeline.progress(1);
        else revealTimeline.play();
    });

    dialog.querySelectorAll('[data-close-reveal]').forEach(button => {
        button.addEventListener('click', () => dialog.close());
    });
    dialog.addEventListener('close', () => {
        // Dismissing the animation never cancels an already-confirmed reward.
        revealTimeline?.progress(1).kill();
        revealTimeline = null;
        document.documentElement.classList.remove('tc-reveal-visible');
        const nextButton = root.querySelector('[data-claim]:not(:disabled)');
        (nextButton || root.querySelector('[data-page-title]')).focus({ preventScroll: true });
    });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) revealTimeline?.progress(1);
    });

    root.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-claim]');
        if (!button || button.disabled || busy) return;
        trigger = button;
        busy = true;
        feedback.textContent = '';
        render();
        button.textContent = config.labels.claiming;
        const card = button.closest('[data-chest]');
        const sprite = card.querySelector('.tc-sprite');
        card.classList.add('is-pending');
        const anticipation = reducedMotion ? null : gsap.to(sprite, { y: -4, duration: .22, repeat: 1, yoyo: true });
        try {
            const response = await fetch(config.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ chest_number: Number(button.dataset.claim) }),
            });
            const result = await response.json().catch(() => null);
            if (!response.ok || !result?.success) throw new Error(result?.message || config.labels.retry);
            activity = result.data.activity;
            render();
            await reveal(result.data.claim);
            feedback.textContent = config.labels.success;
        } catch (error) {
            feedback.textContent = error instanceof Error && error.message && !(error instanceof TypeError)
                ? error.message : config.labels.retry;
        } finally {
            anticipation?.kill();
            gsap.set(sprite, { clearProps: 'transform' });
            card.classList.remove('is-pending');
            busy = false;
            render();
            if (!dialog.open && trigger === button) {
                (button.disabled ? root.querySelector('[data-claim]:not(:disabled)') || root.querySelector('[data-page-title]') : button).focus({ preventScroll: true });
            }
        }
    });
}
