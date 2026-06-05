<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Secure Verification</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive,nosnippet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>

<div style="max-width:520px;margin:90px auto;font-family:Arial;text-align:center;">
    <h1>Secure Verification</h1>

    <p>Please verify your browser to continue.</p>

    <button id="verifyBtn" type="button">
        Continue
    </button>

    <div id="status" style="margin-top:16px;color:#777;font-size:13px;">
        Waiting for verification.
    </div>
</div>

<script>
    window.HUMAN_GATE = {
        verifyUrl: @json($verifyUrl),
        csrfToken: @json(csrf_token())
    };
</script>

<script src="{{ asset('js/human-gate.js') }}"></script>

</body>
</html>