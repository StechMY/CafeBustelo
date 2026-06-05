@if ($shouldShowCheckInPopup)
    @php
        $checkInAmount = (float) ($currentVipLevel?->daily_check_in_amount ?? 0);
    @endphp

    <div
        x-data="{ open: true }"
        x-show="open"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 px-3 py-6"
        @keydown.escape.window="open = false"
    >
        <div
            x-show="open"
            x-transition
            class="relative w-full max-w-md overflow-hidden rounded-[2rem] bg-white shadow-2xl"
            @click.outside="open = false"
        >
            <button
                type="button"
                class="absolute right-4 top-4 z-20 grid h-10 w-10 place-items-center rounded-full bg-[#003b7a]/10 text-2xl font-black text-[#003b7a] hover:bg-[#003b7a] hover:text-white"
                @click="open = false"
                aria-label="{{ __('app.close') }}"
            >
                ×
            </button>

            <div class="bg-[#ffd900] px-5 pb-6 pt-7 text-[#003b7a] sm:px-6">
                <div class="inline-flex rounded-full bg-white/80 px-4 py-2 text-[10px] font-black uppercase tracking-[0.18em] shadow">
                    {{ __('checkin.daily_reward') }}
                </div>

                <h2 class="mt-4 text-4xl font-black uppercase leading-none">
                    {{ __('checkin.today_reward') }}
                </h2>

                <p class="mt-3 text-sm font-bold leading-relaxed text-[#003b7a]/70">
                    {{ __('checkin.popup_desc') }}
                </p>
            </div>

            <div class="p-5 sm:p-6">
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-[1.5rem] bg-[#003b7a] p-4 text-white">
                        <div class="text-[10px] font-black uppercase text-white/60">
                            {{ __('profile.current_vip') }}
                        </div>

                        <div class="mt-2 text-3xl font-black text-[#ffd900]">
                            LV{{ $currentVipLevel->level ?? 0 }}
                        </div>
                    </div>

                    <div class="rounded-[1.5rem] bg-[#e4003a] p-4 text-white">
                        <div class="text-[10px] font-black uppercase text-white/60">
                            {{ __('checkin.today_reward') }}
                        </div>

                        <div class="mt-2 break-words text-3xl font-black">
                            {{ config('app.currency', 'RM') }} {{ number_format($checkInAmount, 2) }}
                        </div>
                    </div>
                </div>

                <div class="mt-4 rounded-[1.5rem] bg-[#fff8c7] p-4 text-sm font-bold leading-relaxed text-[#003b7a]/70">
                    {{ __('checkin.hero_desc') }}
                </div>

                <form method="POST" action="{{ route('check-in.claim') }}" class="mt-5">
                    @csrf

                    <button
                        type="submit"
                        class="w-full rounded-2xl bg-[#003b7a] px-5 py-4 text-sm font-black uppercase text-white shadow-lg transition hover:-translate-y-0.5 hover:bg-[#e4003a]"
                    >
                        {{ __('checkin.claim_now') }}
                    </button>
                </form>

                <button
                    type="button"
                    class="mt-3 w-full rounded-2xl bg-[#f5f5f5] px-5 py-3 text-sm font-black uppercase text-[#003b7a]"
                    @click="open = false"
                >
                    {{ __('app.later') }}
                </button>
            </div>
        </div>
    </div>
@endif