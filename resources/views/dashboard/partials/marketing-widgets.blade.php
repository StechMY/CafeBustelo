

@if ($dashboardSlides->isNotEmpty())
    <section class="bg-[#f5f5f5]">
        <div class="mx-auto max-w-7xl px-3 py-5 sm:px-6 lg:px-8">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-black uppercase text-[#003b7a]">
                        {{ __('dashboard.featured_news') }}
                    </h2>

                    <p class="mt-1 text-sm font-bold text-[#003b7a]/55">
                        {{ __('dashboard.featured_news_desc') }}
                    </p>
                </div>

                <div class="hidden items-center gap-2 sm:flex">
                    <button type="button" class="dashboard-slide-prev grid h-10 w-10 place-items-center rounded-full bg-white text-[#003b7a] shadow ring-1 ring-black/5">
                        ←
                    </button>

                    <button type="button" class="dashboard-slide-next grid h-10 w-10 place-items-center rounded-full bg-[#003b7a] text-white shadow">
                        →
                    </button>
                </div>
            </div>

            <div class="swiper dashboard-swiper overflow-hidden rounded-[2rem]">
                <div class="swiper-wrapper">
                    @foreach ($dashboardSlides as $slide)
                        <div class="swiper-slide">
                            <button
                                type="button"
                                class="dashboard-slide-card group block w-full overflow-hidden rounded-[2rem] bg-white text-left shadow-xl ring-1 ring-black/5"
                                data-title="{{ e($slide->title) }}"
                                data-description="{{ e($slide->description ?? '') }}"
                                data-image="{{ $slide->image_url }}"
                                data-button-text="{{ e($slide->button_text ?? '') }}"
                                data-button-url="{{ e($slide->button_url ?? '') }}"
                            >
                                <div class="relative aspect-[16/7] min-h-[170px] overflow-hidden bg-[#003b7a] sm:aspect-[16/6]">
                                    <img
                                        src="{{ $slide->image_url }}"
                                        alt="{{ $slide->title }}"
                                        class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                                    >

                                    <div class="absolute inset-0 bg-gradient-to-r from-[#003b7a]/85 via-[#003b7a]/30 to-transparent"></div>

                                    <div class="absolute inset-y-0 left-0 flex w-full max-w-xl flex-col justify-center p-5 sm:p-8">
                                        <div class="mb-3 inline-flex w-fit rounded-full bg-[#ffd900] px-4 py-1.5 text-[10px] font-black uppercase tracking-[0.18em] text-[#003b7a]">
                                            {{ __('dashboard.tap_for_details') }}
                                        </div>

                                        <h3 class="line-clamp-2 text-3xl font-black uppercase leading-none text-white sm:text-5xl">
                                            {{ $slide->title }}
                                        </h3>

                                        @if ($slide->description)
                                            <p class="mt-3 line-clamp-2 text-sm font-bold leading-relaxed text-white/75 sm:text-base">
                                                {{ $slide->description }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </button>
                        </div>
                    @endforeach
                </div>

                <div class="swiper-pagination dashboard-slide-pagination !bottom-3"></div>
            </div>
        </div>
    </section>
@endif

@if ($dashboardPopup && $showDashboardPopup)
    <div
        id="dashboard-popup-data"
        data-title="{{ e($dashboardPopup->title) }}"
        data-description="{{ e($dashboardPopup->description ?? '') }}"
        data-image="{{ $dashboardPopup->image_url }}"
        data-button-text="{{ e($dashboardPopup->button_text ?? '') }}"
        data-button-url="{{ e($dashboardPopup->button_url ?? '') }}"
    ></div>
@endif

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (document.querySelector('.dashboard-swiper')) {
                new Swiper('.dashboard-swiper', {
                    loop: true,
                    speed: 650,
                    spaceBetween: 16,
                    autoplay: {
                        delay: 4200,
                        disableOnInteraction: false,
                    },
                    pagination: {
                        el: '.dashboard-slide-pagination',
                        clickable: true,
                    },
                    navigation: {
                        nextEl: '.dashboard-slide-next',
                        prevEl: '.dashboard-slide-prev',
                    },
                });
            }

            function showDashboardModal(data) {
                const title = data.title || '';
                const description = data.description || '';
                const image = data.image || '';
                const buttonText = data.buttonText || '';
                const buttonUrl = data.buttonUrl || '';

                let html = '';

                if (image) {
                    html += `
                        <div style="margin:-1rem -1rem 1rem; overflow:hidden; border-radius:1.25rem 1.25rem 0 0;">
                            <img src="${image}" alt="" style="width:100%; max-height:360px; object-fit:cover; display:block;">
                        </div>
                    `;
                }

                if (description) {
                    html += `<div style="text-align:left; font-weight:700; color:#003b7a; line-height:1.6;">${description}</div>`;
                }

                Swal.fire({
                    title: title,
                    html: html,
                    showCancelButton: Boolean(buttonUrl),
                    confirmButtonText: buttonUrl ? buttonText || '{{ __('dashboard.open') }}' : '{{ __('app.close') }}',
                    cancelButtonText: '{{ __('app.close') }}',
                    confirmButtonColor: '#e4003a',
                    cancelButtonColor: '#003b7a',
                    width: 720,
                    customClass: {
                        popup: 'dashboard-swal-popup',
                        title: 'dashboard-swal-title',
                    },
                }).then(function (result) {
                    if (result.isConfirmed && buttonUrl) {
                        window.open(buttonUrl, '_blank', 'noopener,noreferrer');
                    }
                });
            }

            document.querySelectorAll('.dashboard-slide-card').forEach(function (card) {
                card.addEventListener('click', function () {
                    showDashboardModal({
                        title: card.dataset.title,
                        description: card.dataset.description,
                        image: card.dataset.image,
                        buttonText: card.dataset.buttonText,
                        buttonUrl: card.dataset.buttonUrl,
                    });
                });
            });

            const popup = document.getElementById('dashboard-popup-data');

            if (popup) {
                setTimeout(function () {
                    showDashboardModal({
                        title: popup.dataset.title,
                        description: popup.dataset.description,
                        image: popup.dataset.image,
                        buttonText: popup.dataset.buttonText,
                        buttonUrl: popup.dataset.buttonUrl,
                    });
                }, 450);
            }
        });
    </script>
@endpush