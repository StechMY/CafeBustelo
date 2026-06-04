@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')

@section('title', __('contact.contact_us'))

@section('content')
    <section class="bg-[#ffd900]">
        <div class="mx-auto max-w-7xl px-3 py-6 sm:px-6 sm:py-10 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
                <div>
                    <div
                         class="inline-flex rounded-full bg-white/75 px-5 py-2 text-xs font-black uppercase tracking-[0.22em] text-[#003b7a] shadow">
                        {{ __('contact.support_center') }}
                    </div>

                    <h1
                        class="mt-4 max-w-3xl text-4xl font-black uppercase leading-[0.9] tracking-tight text-[#003b7a] sm:text-6xl lg:text-7xl">
                        {{ __('contact.hero_title') }}
                    </h1>

                    <p class="mt-5 max-w-2xl text-sm font-bold leading-relaxed text-[#003b7a]/75 sm:text-base">
                        {{ __('contact.hero_description') }}
                    </p>
                </div>

                <div class="rounded-[2rem] border-4 border-[#003b7a] bg-white p-5 shadow-[10px_10px_0_#003b7a] sm:p-7">
                    <div class="text-xs font-black uppercase tracking-[0.18em] text-[#e4003a]">
                        {{ __('contact.need_help') }}
                    </div>

                    <h2 class="mt-3 text-3xl font-black leading-tight text-[#003b7a] sm:text-4xl">
                        {{ __('contact.customer_service') }}
                    </h2>

                    <p class="mt-4 text-sm font-bold leading-relaxed text-[#003b7a]/65 sm:text-base">
                        {{ __('contact.customer_service_desc') }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="min-h-[50vh] bg-[#f5f5f5]">
        <div class="mx-auto max-w-7xl px-3 py-6 sm:px-6 sm:py-8 lg:px-8">
            @if ($contacts->isNotEmpty())
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($contacts as $contact)
                        <a
                           href="{{ $contact->link }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="group overflow-hidden rounded-[2rem] bg-white p-5 shadow-xl ring-1 ring-black/5 transition hover:-translate-y-1 hover:shadow-2xl">
                            <div class="flex items-start gap-4">
                                <div
                                     class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-[#003b7a] text-white shadow-lg transition group-hover:bg-[#e4003a]">
                                    @include('partials.icon', [
                                        'name' => $contact->displayIcon(),
                                        'class' => 'h-8 w-8',
                                    ])
                                </div>

                                <div class="min-w-0 flex-1">
                                    <h3 class="break-words text-xl font-black text-[#003b7a] sm:text-2xl">
                                        {{ $contact->name }}
                                    </h3>

                                    <div class="mt-1 text-xs font-black uppercase tracking-wide text-[#e4003a]">
                                        {{ __('contact.types.' . $contact->type) }}
                                    </div>

                                    @if ($contact->description)
                                        <p class="mt-3 text-sm font-bold leading-relaxed text-[#003b7a]/60">
                                            {{ $contact->description }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <div
                                 class="mt-5 flex items-center justify-between rounded-2xl bg-[#fff8c7] px-4 py-3 text-sm font-black text-[#003b7a] ring-1 ring-[#003b7a]/10">
                                <span>{{ __('contact.tap_to_contact') }}</span>
                                <span class="text-xl transition group-hover:translate-x-1">→</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="rounded-[2rem] bg-white p-10 text-center shadow-xl ring-1 ring-black/5">
                    <div
                         class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-[#003b7a] text-white shadow-lg transition group-hover:bg-[#e4003a] sm:h-16 sm:w-16">
                        @include('partials.icon', [
                            'name' => $contact->displayIcon(),
                            'class' => 'h-7 w-7 sm:h-8 sm:w-8',
                        ])
                    </div>

                    <h2 class="mt-5 text-2xl font-black text-[#003b7a]">
                        {{ __('contact.no_contacts') }}
                    </h2>

                    <p class="mt-2 text-sm font-bold text-[#003b7a]/60">
                        {{ __('contact.no_contacts_desc') }}
                    </p>
                </div>
            @endif
        </div>
    </section>
@endsection
