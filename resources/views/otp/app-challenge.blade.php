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
    <button id="retry" type="button" hidden onclick="window.location.reload()">{{ __('otp.challenge_retry') }}</button>
    <noscript>{{ __('otp.challenge_load_failed') }}</noscript>
    <script>
        var challenge = document.getElementById('challenge');
        var unavailable = @js(__('otp.challenge_load_failed'));
        var appRequired = @js(__('otp.challenge_app_required'));
        var watchdog = window.setTimeout(function () { challengeError('script_timeout'); }, 20000);

        function notifyApp(type, token, code) {
            var message = { type: type, purpose: challenge.dataset.purpose };
            if (type === 'success') message.token = token;
            if (code) message.code = code;
            if (window.OtpChallenge && typeof window.OtpChallenge.postMessage === 'function') {
                try {
                    window.OtpChallenge.postMessage(JSON.stringify(message));
                    return;
                } catch (error) {
                    document.getElementById('status').textContent = appRequired + ' (bridge_error)';
                }
            } else if (type === 'success') {
                document.getElementById('status').textContent = appRequired + ' (bridge_missing)';
            }
        }

        function challengeError(code) {
            window.clearTimeout(watchdog);
            code = typeof code === 'string' && /^[a-z0-9_]{1,40}$/.test(code) ? code : 'challenge_error';
            document.getElementById('status').textContent = unavailable + ' (' + code + ')';
            document.getElementById('retry').hidden = false;
            notifyApp('error', null, code);
        }

        function challengeExpired() {
            window.clearTimeout(watchdog);
            document.getElementById('status').textContent = unavailable + ' (expired)';
            document.getElementById('retry').hidden = false;
            notifyApp('expired');
        }

        window.onOtpTurnstileReady = function () {
            window.clearTimeout(watchdog);
            if (!challenge.dataset.sitekey) {
                challengeError('configuration_missing');
                return;
            }
            watchdog = window.setTimeout(function () { challengeError('challenge_timeout'); }, 30000);
            try {
                window.turnstile.render(challenge, {
                sitekey: challenge.dataset.sitekey,
                action: 'otp_' + challenge.dataset.purpose,
                size: 'compact',
                callback: function (token) {
                    window.clearTimeout(watchdog);
                    document.getElementById('retry').hidden = true;
                    document.getElementById('status').textContent = '';
                    notifyApp('success', token);
                },
                'before-interactive-callback': function () { window.clearTimeout(watchdog); },
                'expired-callback': challengeExpired,
                'timeout-callback': challengeExpired,
                'error-callback': challengeError,
                });
            } catch (error) {
                challengeError('render_failed');
            }
        };
    </script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onOtpTurnstileReady&amp;render=explicit"
            async defer onerror="challengeError('script_load_failed')"></script>
</body>
</html>
