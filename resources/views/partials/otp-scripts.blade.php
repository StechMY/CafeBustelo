<script>
    document.addEventListener('DOMContentLoaded', () => {
        const messages = @json(__('otp'));
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const challenges = document.querySelectorAll('[data-otp-challenge]');

        window.initializeOtpChallenges = () => {
            challenges.forEach((challenge) => {
                if (!challenge.dataset.sitekey) return;
                const input = challenge.querySelector('[name="turnstile_token"]');
                const error = challenge.querySelector('[data-challenge-message]');
                challenge.widgetId = window.turnstile.render(challenge.querySelector('[data-otp-widget]'), {
                    sitekey: challenge.dataset.sitekey,
                    action: challenge.dataset.action,
                    size: challenge.clientWidth < 300 ? 'compact' : 'flexible',
                    'response-field': false,
                    callback: (token) => { input.value = token; error.textContent = ''; },
                    'expired-callback': () => { input.value = ''; },
                    'error-callback': () => {
                        input.value = '';
                        error.textContent = messages.challenge_required;
                        return true;
                    },
                });
            });
        };

        if (challenges.length) {
            const script = document.createElement('script');
            script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=initializeOtpChallenges&render=explicit';
            script.async = true;
            script.onerror = () => challenges.forEach((challenge) => {
                challenge.querySelector('[data-challenge-message]').textContent = messages.service_unavailable;
            });
            document.head.appendChild(script);
        }

        document.querySelectorAll('[data-otp-form]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (!form.querySelector('[name="turnstile_token"]')?.value) {
                    event.preventDefault();
                    form.querySelector('[data-challenge-message]').textContent = messages.challenge_required;
                }
            });
        });

        document.querySelectorAll('[data-otp-send]').forEach((button) => {
            button.addEventListener('click', async () => {
                if (button.disabled) return;
                const form = button.closest('form');
                const challenge = form.querySelector('[data-otp-challenge]');
                const input = challenge?.querySelector('[name="turnstile_token"]');
                const box = form.querySelector('[data-otp-message]');
                const phone = document.getElementById(button.dataset.phoneInput)?.value?.trim();
                if (!phone) return showMessage(box, messages.phone_required, false);
                if (!input?.value) return showMessage(box, messages.challenge_required, false);

                button.disabled = true;
                const label = button.textContent;
                button.textContent = messages.sending;
                let sent = false;
                try {
                    const response = await fetch(button.dataset.otpUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ phone, turnstile_token: input.value }),
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        showMessage(box, Object.values(data.errors || {})?.[0]?.[0]
                            || data.message || messages.sms_send_failed, false);
                        return;
                    }
                    sent = true;
                    showMessage(box, data.message || messages.sent, true);
                    let remaining = Math.max(1, Number(data.resend_seconds || 60));
                    button.textContent = `${remaining}s`;
                    const timer = setInterval(() => {
                        remaining -= 1;
                        if (remaining <= 0) {
                            clearInterval(timer);
                            button.disabled = false;
                            button.textContent = label;
                        } else {
                            button.textContent = `${remaining}s`;
                        }
                    }, 1000);
                } catch {
                    showMessage(box, messages.sms_send_failed, false);
                } finally {
                    // A token may have been consumed even if the request timed out or was rejected.
                    input.value = '';
                    if (window.turnstile && challenge.widgetId !== undefined) {
                        window.turnstile.reset(challenge.widgetId);
                    }
                    if (!sent) {
                        button.disabled = false;
                        button.textContent = label;
                    }
                }
            });
        });

        function showMessage(element, text, success) {
            if (!element) return;
            element.classList.remove('hidden');
            element.textContent = text;
            element.classList.toggle('text-[#003b7a]', success);
            element.classList.toggle('text-[#e4003a]', !success);
        }
    });
</script>
