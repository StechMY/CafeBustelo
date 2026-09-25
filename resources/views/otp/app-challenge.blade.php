<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Security verification</title>
</head>
<body>
    <p id="status" role="status">{{ __('otp.challenge_required') }}</p>
    <div id="challenge" data-sitekey="{{ config('turnstile.site_key') }}" data-purpose="{{ $purpose }}"></div>
    <script>
        const challenge = document.getElementById('challenge');
        const unavailable = @js(__('otp.service_unavailable'));

        function notifyApp(type, token) {
            const message = { type, purpose: challenge.dataset.purpose };
            if (type === 'success') message.token = token;
            if (window.OtpChallenge && typeof window.OtpChallenge.postMessage === 'function') {
                window.OtpChallenge.postMessage(JSON.stringify(message));
            } else {
                document.getElementById('status').textContent = unavailable;
            }
        }

        function challengeError() {
            document.getElementById('status').textContent = unavailable;
            notifyApp('error');
        }

        window.onOtpTurnstileReady = function () {
            if (!challenge.dataset.sitekey) {
                challengeError();
                return;
            }
            window.turnstile.render(challenge, {
                sitekey: challenge.dataset.sitekey,
                action: 'otp_' + challenge.dataset.purpose,
                size: 'compact',
                callback: (token) => notifyApp('success', token),
                'expired-callback': () => notifyApp('expired'),
                'timeout-callback': () => notifyApp('expired'),
                'error-callback': challengeError,
            });
        };
    </script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onOtpTurnstileReady&amp;render=explicit"
            async defer onerror="challengeError()"></script>
</body>
</html>
