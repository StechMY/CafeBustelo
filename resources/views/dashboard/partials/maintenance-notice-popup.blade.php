@if ($showMaintenanceNotice)
    <div
        id="maintenance-notice-popup"
        class="fixed inset-0 z-[99998] flex items-center justify-center bg-[#001b38]/75 p-3 backdrop-blur-sm sm:p-5"
        role="dialog"
        aria-modal="true"
        aria-labelledby="maintenance-notice-title"
    >
        <div class="w-full max-w-2xl overflow-hidden rounded-[1.5rem] border-4 border-[#003b7a] bg-white shadow-[8px_8px_0_#003b7a] sm:rounded-[2rem] sm:shadow-[12px_12px_0_#003b7a]">
            <div class="relative bg-[#ffd900] px-5 py-5 sm:px-7 sm:py-6">
                <button
                    type="button"
                    data-maintenance-close
                    class="absolute right-4 top-4 flex h-9 w-9 items-center justify-center rounded-full bg-white/80 text-xl font-black text-[#003b7a] transition hover:bg-white"
                    aria-label="{{ __('app.close') }}"
                >
                    ×
                </button>

                <h2 id="maintenance-notice-title" class="pr-12 text-2xl font-black text-[#003b7a] sm:text-3xl">
                    {{ __('dashboard.maintenance_notice_title') }}
                </h2>
            </div>

            <div class="space-y-4 px-5 py-5 text-sm font-bold leading-7 text-[#003b7a]/85 sm:px-7 sm:py-6 sm:text-base">
                <p>{{ __('dashboard.maintenance_notice_intro') }}</p>

                <div class="space-y-3 rounded-2xl bg-[#fff8c7] p-4 ring-1 ring-[#ffd900] sm:p-5">
                    <p>⏰ {{ __('dashboard.maintenance_notice_period') }}</p>
                    <p>✅ {{ __('dashboard.maintenance_notice_resume') }}</p>
                </div>

                <p>{{ __('dashboard.maintenance_notice_reminder') }}</p>
            </div>

            <div class="border-t border-[#003b7a]/10 bg-[#f8fafc] px-5 py-4 sm:px-7 sm:py-5">
                <button
                    type="button"
                    data-maintenance-close
                    class="w-full rounded-2xl bg-[#003b7a] px-5 py-3.5 text-sm font-black uppercase tracking-wide text-white shadow-[0_5px_0_#001f40] transition hover:bg-[#002d5c] active:translate-y-1 active:shadow-none sm:text-base"
                >
                    {{ __('app.close') }}
                </button>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const popup = document.getElementById('maintenance-notice-popup');

                if (!popup) {
                    return;
                }

                document.body.style.overflow = 'hidden';

                const closePopup = () => {
                    popup.remove();
                    document.body.style.overflow = '';
                };

                popup.querySelectorAll('[data-maintenance-close]').forEach((button) => {
                    button.addEventListener('click', closePopup);
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && document.body.contains(popup)) {
                        closePopup();
                    }
                });
            });
        </script>
    @endpush
@endif
