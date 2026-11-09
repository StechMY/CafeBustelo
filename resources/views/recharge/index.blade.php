@extends('layouts.app')

@section('title', __('recharge.recharge'))

@section('content')
    @php
        $currency = config('app.currency', 'RM');
    @endphp

    <section class="bg-[#ffd900]">
        <div class="mx-auto max-w-7xl px-3 py-3 sm:px-6 sm:py-8 lg:px-8">
            <div class="grid gap-4 lg:grid-cols-[1.4fr_0.9fr] lg:items-center">
                <div>
                    <div class="mb-2 inline-flex rounded-full bg-white/70 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide shadow sm:mb-3 sm:px-4 sm:py-2 sm:text-xs">
                        {{ __('recharge.recharge_center') }}
                    </div>

                    <h1 class="text-3xl font-black uppercase leading-[0.9] text-[#003b7a] sm:text-6xl">
                        {{ __('recharge.hero_title') }}
                    </h1>

                    <p class="mt-2 line-clamp-2 max-w-2xl text-xs font-bold leading-relaxed text-[#003b7a]/80 sm:mt-4 sm:line-clamp-none sm:text-base">
                        {{ __('recharge.hero_description') }}
                    </p>
                </div>

                <div class="rounded-[1.35rem] border-3 border-[#003b7a] bg-white p-3 shadow-[6px_6px_0_#003b7a] sm:rounded-[1.5rem] sm:border-4 sm:p-5 sm:shadow-[8px_8px_0_#003b7a]">
                    <div class="flex items-center justify-between gap-3 lg:block">
                        <div class="min-w-0">
                            <div class="text-[10px] font-black uppercase text-[#e4003a] sm:text-xs">
                                {{ __('dashboard.recharge_wallet') }}
                            </div>

                            <div class="mt-1 break-words text-2xl font-black leading-none text-[#003b7a] sm:mt-2 sm:text-4xl">
                                {{ $currency }} {{ number_format((float) $rechargeBalance, 2) }}
                            </div>
                        </div>

                        <a
                            href="{{ route('recharge.create') }}"
                            class="shrink-0 rounded-xl bg-[#e4003a] px-4 py-2.5 text-center text-xs font-black uppercase text-white shadow-lg sm:mt-5 sm:inline-flex sm:w-full sm:justify-center sm:rounded-2xl sm:px-5 sm:py-3 sm:text-sm"
                        >
                            {{ __('recharge.submit_recharge') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-3 py-4 sm:px-6 sm:py-8 lg:px-8">
        <div class="rounded-[1.35rem] bg-white p-4 shadow-xl ring-1 ring-black/5 sm:rounded-[1.5rem] sm:p-6">
            <div class="mb-4 flex items-center justify-between gap-4 sm:mb-5">
                <div class="min-w-0">
                    <h2 class="text-lg font-black uppercase text-[#003b7a] sm:text-2xl">
                        {{ __('recharge.my_recharge_requests') }}
                    </h2>

                    <p class="mt-1 line-clamp-1 text-xs font-bold text-[#003b7a]/60 sm:line-clamp-none sm:text-sm">
                        {{ __('recharge.my_recharge_requests_desc') }}
                    </p>
                </div>

                <a
                    href="{{ route('recharge.create') }}"
                    class="hidden shrink-0 rounded-full bg-[#e4003a] px-4 py-2 text-xs font-black uppercase text-white sm:inline-flex"
                >
                    {{ __('recharge.submit_recharge') }}
                </a>
            </div>

            @if ($requests->isNotEmpty())
                <div class="divide-y divide-[#003b7a]/10 overflow-hidden rounded-[1.25rem] border border-[#003b7a]/10">
                    @foreach ($requests as $requestItem)
                        <a
                            href="{{ route('recharge.show', $requestItem) }}"
                            class="block bg-[#fff8c7] p-3 transition hover:bg-[#fff3a0] sm:p-4"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="break-words text-lg font-black leading-tight text-[#003b7a] sm:text-xl">
                                        {{ $currency }} {{ number_format((float) $requestItem->amount, 2) }}
                                    </div>

                                    <div class="mt-1 text-[10px] font-bold text-[#003b7a]/60 sm:text-xs">
                                        {{ $requestItem->submitted_at?->format('d M Y, h:i A') ?? $requestItem->created_at->format('d M Y, h:i A') }}
                                    </div>

                                    <div class="mt-1 text-[10px] font-black uppercase text-[#003b7a]/70 sm:text-xs">
                                        {{ $requestItem->isGatewayDeposit() ? $requestItem->gatewayProviderLabel() : __('recharge.manual_deposit') }}
                                        @if ($requestItem->isGatewayDeposit() && $requestItem->gateway_payment_status)
                                            · {{ __('recharge.gateway_status') }}: {{ $requestItem->gatewayStatusLabel() }}
                                        @endif
                                    </div>

                                    @if ($requestItem->reference_no)
                                        <div class="mt-0.5 truncate text-[10px] font-bold text-[#003b7a]/60 sm:text-xs">
                                            {{ __('recharge.reference_no') }}: {{ $requestItem->reference_no }}
                                        </div>
                                    @endif
                                </div>

                                <span
                                    class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-black uppercase sm:px-3 sm:text-xs
                                    @class([
                                        'bg-yellow-100 text-yellow-700' => $requestItem->status === 'pending' && ! $requestItem->requiresGatewayReview(),
                                        'bg-green-100 text-green-700' => $requestItem->isPaymentConfirmed(),
                                        'bg-red-100 text-red-700' => $requestItem->status === 'rejected' || $requestItem->requiresGatewayReview(),
                                    ])"
                                >
                                    {{ $requestItem->gateway_provider === 'ydpay' ? $requestItem->gatewayStatusLabel() : __('status.' . $requestItem->status) }}
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-4 sm:mt-6">
                    {{ $requests->links() }}
                </div>
            @else
                <div class="rounded-[1.25rem] bg-[#fff8c7] p-6 text-center sm:p-8">
                    <div class="text-xl font-black text-[#003b7a] sm:text-2xl">
                        {{ __('recharge.no_requests') }}
                    </div>

                    <p class="mt-2 text-sm font-bold text-[#003b7a]/70">
                        {{ __('recharge.no_requests_desc') }}
                    </p>

                    <a
                        href="{{ route('recharge.create') }}"
                        class="mt-5 inline-flex rounded-full bg-[#e4003a] px-6 py-3 text-sm font-black uppercase text-white"
                    >
                        {{ __('recharge.submit_recharge') }}
                    </a>
                </div>
            @endif
        </div>
    </section>
@endsection
