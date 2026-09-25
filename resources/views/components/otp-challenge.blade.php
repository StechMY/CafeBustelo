@props(['purpose'])

<div data-otp-challenge data-sitekey="{{ config('turnstile.site_key') }}"
     data-action="otp_{{ $purpose }}" class="my-3">
    <div data-otp-widget></div>
    <input type="hidden" name="turnstile_token" value="">
    <p data-challenge-message class="mt-2 text-sm font-bold text-[#e4003a]" role="status">
        @if (blank(config('turnstile.site_key')))
            {{ __('otp.service_unavailable') }}
        @endif
    </p>
    @error('turnstile_token')
        <p class="mt-2 text-sm font-bold text-[#e4003a]">{{ $message }}</p>
    @enderror
</div>
