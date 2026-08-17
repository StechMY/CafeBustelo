@if ($showAccountPolicy)
    <div
        class="fixed inset-0 z-[99999] flex items-center justify-center bg-[#001b38]/80 p-3 backdrop-blur-sm sm:p-5"
        role="dialog"
        aria-modal="true"
        aria-labelledby="account-policy-title"
    >
        <div
            class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-[1.5rem] border-4 border-[#003b7a] bg-white shadow-[8px_8px_0_#003b7a] sm:max-h-[86vh] sm:rounded-[2rem] sm:shadow-[12px_12px_0_#003b7a]"
        >
            {{-- Header --}}
            <div class="shrink-0 bg-[#ffd900] px-4 py-4 sm:px-7 sm:py-6">
                <div
                    class="mb-2 inline-flex rounded-full bg-[#e4003a] px-3 py-1 text-[10px] font-black uppercase tracking-wide text-white sm:text-xs"
                >
                    {{ __('dashboard.account_policy_badge') }}
                </div>

                <h2
                    id="account-policy-title"
                    class="text-xl font-black leading-tight text-[#003b7a] sm:text-3xl"
                >
                    {{ __('dashboard.account_policy_title') }}
                </h2>

                <p class="mt-2 text-xs font-bold leading-relaxed text-[#003b7a]/70 sm:text-sm">
                    {{ __('dashboard.account_policy_subtitle') }}
                </p>
            </div>

            {{-- Scrollable policy body --}}
            <div
                class="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-white px-4 py-4 sm:px-7 sm:py-6"
            >
                <div class="space-y-4">
                    @foreach (__('dashboard.account_policy_paragraphs') as $index => $paragraph)
                        <div class="flex items-start gap-3">
                            <div
                                class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#003b7a] text-[10px] font-black text-white"
                            >
                                {{ $index + 1 }}
                            </div>

                            <p
                                class="text-sm font-bold leading-7 text-[#003b7a]/85 sm:text-[15px]"
                            >
                                {{ $paragraph }}
                            </p>
                        </div>
                    @endforeach
                </div>

                <div
                    class="mt-5 rounded-2xl border border-[#e4003a]/15 bg-red-50 p-4"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#e4003a] text-sm font-black text-white"
                        >
                            !
                        </div>

                        <p class="text-xs font-bold leading-6 text-red-700 sm:text-sm">
                            {{ __('dashboard.account_policy_warning') }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Fixed footer --}}
            <div
                class="shrink-0 border-t border-[#003b7a]/10 bg-[#f8fafc] px-4 py-4 sm:px-7 sm:py-5"
            >
                <form
                    method="POST"
                    action="{{ route('dashboard.account-policy.agree') }}"
                    id="account-policy-form"
                >
                    @csrf

                    <button
                        type="submit"
                        id="account-policy-agree-button"
                        class="w-full rounded-2xl bg-[#e4003a] px-5 py-3.5 text-sm font-black uppercase tracking-wide text-white shadow-[0_5px_0_#970026] transition hover:bg-[#c90032] active:translate-y-1 active:shadow-none sm:py-4 sm:text-base"
                    >
                        {{ __('dashboard.account_policy_agree') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const form = document.getElementById('account-policy-form');
                const button = document.getElementById('account-policy-agree-button');

                if (!form || !button) {
                    return;
                }

                // Prevent scrolling the dashboard behind the mandatory dialog.
                document.body.style.overflow = 'hidden';

                form.addEventListener('submit', () => {
                    button.disabled = true;
                    button.classList.add('cursor-not-allowed', 'opacity-70');

                    button.textContent = @json(__('dashboard.account_policy_processing'));
                });
            });
        </script>
    @endpush
@endif