@extends('layouts.app')

@section('title', __('recharge.recharge'))

@section('content')
    <section class="bg-[#ffd900]">
        <div class="mx-auto max-w-7xl px-3 py-6 sm:px-6 lg:px-8">
            <div class="grid gap-5 lg:grid-cols-[1.4fr_0.9fr] lg:items-center">
                <div>
                    <div class="mb-3 inline-flex rounded-full bg-white/70 px-4 py-2 text-xs font-black uppercase tracking-wide shadow">
                        {{ __('recharge.recharge_center') }}
                    </div>

                    <h1 class="text-4xl font-black uppercase leading-[0.9] text-[#003b7a] sm:text-6xl">
                        {{ __('recharge.hero_title') }}
                    </h1>

                    <p class="mt-4 max-w-2xl text-sm font-bold text-[#003b7a]/80 sm:text-base">
                        {{ __('recharge.hero_description') }}
                    </p>
                </div>

                <div class="rounded-[1.5rem] border-4 border-[#003b7a] bg-white p-5 shadow-[8px_8px_0_#003b7a]">
                    <div class="text-xs font-black uppercase text-[#e4003a]">
                        {{ __('dashboard.recharge_wallet') }}
                    </div>

                    <div class="mt-2 text-4xl font-black text-[#003b7a]">
                        {{ config('app.currency') }} {{ number_format((float) $rechargeBalance, 2) }}
                    </div>

                    <a href="{{ route('recharge.create') }}"
                       class="mt-5 inline-flex w-full justify-center rounded-2xl bg-[#e4003a] px-5 py-3 text-sm font-black uppercase text-white shadow-lg">
                        {{ __('recharge.submit_recharge') }}
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-3 py-6 sm:px-6 lg:px-8">
        <div class="rounded-[1.5rem] bg-white p-4 shadow-xl ring-1 ring-black/5 sm:p-6">
            <div class="mb-5 flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-black uppercase text-[#003b7a]">
                        {{ __('recharge.my_recharge_requests') }}
                    </h2>

                    <p class="mt-1 text-sm font-bold text-[#003b7a]/60">
                        {{ __('recharge.my_recharge_requests_desc') }}
                    </p>
                </div>
            </div>

            @if ($requests->isNotEmpty())
                <div class="space-y-3">
                    @foreach ($requests as $requestItem)
                        <a href="{{ route('recharge.show', $requestItem) }}"
                           class="block rounded-[1.25rem] border border-[#003b7a]/10 bg-[#fff8c7] p-4 transition hover:bg-[#fff3a0]">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-xl font-black text-[#003b7a]">
                                        {{ config('app.currency') }} {{ number_format((float) $requestItem->amount, 2) }}
                                    </div>

                                    <div class="mt-1 text-xs font-bold text-[#003b7a]/60">
                                        {{ $requestItem->submitted_at?->format('d M Y, h:i A') ?? $requestItem->created_at->format('d M Y, h:i A') }}
                                    </div>

                                    @if ($requestItem->reference_no)
                                        <div class="mt-1 text-xs font-bold text-[#003b7a]/60">
                                            {{ __('recharge.reference_no') }}: {{ $requestItem->reference_no }}
                                        </div>
                                    @endif
                                </div>

                                <span class="rounded-full px-3 py-1 text-xs font-black uppercase
                                    @class([
                                        'bg-yellow-100 text-yellow-700' => $requestItem->status === 'pending',
                                        'bg-green-100 text-green-700' => $requestItem->status === 'approved',
                                        'bg-red-100 text-red-700' => $requestItem->status === 'rejected',
                                    ])">
                                    {{ __('status.' . $requestItem->status) }}
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $requests->links() }}
                </div>
            @else
                <div class="rounded-[1.25rem] bg-[#fff8c7] p-8 text-center">
                    <div class="text-2xl font-black text-[#003b7a]">
                        {{ __('recharge.no_requests') }}
                    </div>

                    <p class="mt-2 text-sm font-bold text-[#003b7a]/70">
                        {{ __('recharge.no_requests_desc') }}
                    </p>

                    <a href="{{ route('recharge.create') }}"
                       class="mt-5 inline-flex rounded-full bg-[#e4003a] px-6 py-3 text-sm font-black uppercase text-white">
                        {{ __('recharge.submit_recharge') }}
                    </a>
                </div>
            @endif
        </div>
    </section>
@endsection