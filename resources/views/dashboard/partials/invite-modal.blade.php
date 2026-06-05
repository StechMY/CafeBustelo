<div
    x-show="inviteOpen"
    x-cloak
    x-transition.opacity
    class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 px-3 py-4"
    @keydown.escape.window="inviteOpen = false"
>
    <div
        x-show="inviteOpen"
        x-transition
        class="relative flex max-h-[92vh] w-full max-w-md flex-col overflow-hidden rounded-[2rem] bg-[#2148ff] text-white shadow-2xl"
        @click.outside="inviteOpen = false"
        x-data="{
            copied: false,
            inviteUrl: @js($inviteUrl),
            copy() {
                navigator.clipboard.writeText(this.inviteUrl);
                this.copied = true;
                setTimeout(() => this.copied = false, 1800);
            }
        }"
    >
        <div class="shrink-0 bg-[#2148ff] px-5 pb-3 pt-5 sm:px-6 sm:pt-6">
            <button
                type="button"
                class="absolute right-4 top-4 grid h-10 w-10 place-items-center rounded-full bg-white/15 text-2xl font-black text-white hover:bg-white/25"
                @click="inviteOpen = false"
                aria-label="{{ __('app.close') }}"
            >
                ×
            </button>

            <div class="pr-12">
                <div class="inline-flex rounded-full bg-[#ffd900] px-4 py-2 text-[10px] font-black uppercase tracking-[0.18em] text-[#003b7a]">
                    {{ __('dashboard.invite_team') }}
                </div>

                <h2 class="mt-4 text-3xl font-black uppercase leading-none sm:text-4xl">
                    {{ __('dashboard.invite_team') }}
                </h2>

                <p class="mt-3 text-sm font-bold leading-relaxed text-white/75">
                    {{ __('dashboard.invite_team_desc') }}
                </p>
            </div>
        </div>

        <div class="invite-modal-scroll flex-1 overflow-y-auto px-5 pb-4 sm:px-6">
            <div class="rounded-[1.5rem] bg-white p-4 shadow-lg">
                <div class="mx-auto max-w-[210px]">
                    <div class="overflow-hidden rounded-2xl bg-white">
                        {!! QrCode::format('svg')->size(210)->margin(1)->generate($inviteUrl) !!}
                    </div>
                </div>

                <div class="mt-3 text-center text-[10px] font-black uppercase tracking-wide text-[#003b7a]/60">
                    {{ __('dashboard.scan_to_register') }}
                </div>
            </div>

            <div class="mt-4 rounded-2xl bg-white/15 p-4">
                <div class="text-xs font-black uppercase text-white/70">
                    {{ __('dashboard.your_referral_code') }}
                </div>

                <div class="mt-1 break-all text-3xl font-black leading-tight">
                    {{ $user->referral_code }}
                </div>
            </div>

            <div class="mt-3 rounded-2xl bg-white/15 p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-xs font-black uppercase text-white/70">
                            {{ __('dashboard.invite_link') }}
                        </div>

                        <div class="mt-1 truncate text-xs font-bold text-white/85">
                            {{ $inviteUrl }}
                        </div>
                    </div>

                    <button
                        type="button"
                        class="shrink-0 rounded-full bg-white/15 px-3 py-2 text-[10px] font-black uppercase text-white"
                        @click="copy()"
                        x-text="copied ? '{{ __('dashboard.copied') }}' : '{{ __('app.copy') }}'"
                    ></button>
                </div>
            </div>
        </div>

        <div class="shrink-0 bg-[#2148ff] px-5 pb-5 pt-2 sm:px-6 sm:pb-6">
            <button
                type="button"
                class="w-full rounded-2xl bg-[#ffd900] px-5 py-3 text-sm font-black uppercase text-[#003b7a] transition hover:-translate-y-0.5 hover:shadow-lg"
                @click="copy()"
                x-text="copied ? '{{ __('dashboard.copied') }}' : '{{ __('dashboard.copy_invite_link') }}'"
            ></button>
        </div>
    </div>
</div>