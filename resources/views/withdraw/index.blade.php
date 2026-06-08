@extends('layouts.app')

@section('title', __('withdraw.withdraw'))

@section('content')
    @php
        $currency = config('app.currency', 'RM');
    @endphp

    <section class="bg-[#ffd900]">
        <div class="mx-auto max-w-7xl px-3 py-3 sm:px-6 sm:py-8 lg:px-8">
            <div class="grid gap-4 lg:grid-cols-[1.4fr_0.9fr] lg:items-center">
                <div>
                    <div class="mb-2 inline-flex rounded-full bg-white/70 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide shadow sm:mb-3 sm:px-4 sm:py-2 sm:text-xs">
                        {{ __('withdraw.withdraw_center') }}
                    </div>

                    <h1 class="text-3xl font-black uppercase leading-[0.9] text-[#003b7a] sm:text-6xl">
                        {{ __('withdraw.hero_title') }}
                    </h1>

                    <p class="mt-2 line-clamp-2 max-w-2xl text-xs font-bold leading-relaxed text-[#003b7a]/80 sm:mt-4 sm:line-clamp-none sm:text-base">
                        {{ __('withdraw.hero_description') }}
                    </p>
                </div>

                <div class="rounded-[1.35rem] border-3 border-[#003b7a] bg-white p-3 shadow-[6px_6px_0_#003b7a] sm:rounded-[1.5rem] sm:border-4 sm:p-5 sm:shadow-[8px_8px_0_#003b7a]">
                    <div class="flex items-center justify-between gap-3 lg:block">
                        <div class="min-w-0">
                            <div class="text-[10px] font-black uppercase text-[#e4003a] sm:text-xs">
                                {{ __('dashboard.withdrawable_wallet') }}
                            </div>

                            <div class="mt-1 break-words text-2xl font-black leading-none text-[#003b7a] sm:mt-2 sm:text-4xl">
                                {{ $currency }} {{ number_format((float) $withdrawableBalance, 2) }}
                            </div>

                            <div class="mt-1 text-[10px] font-bold text-[#003b7a]/60 sm:mt-2 sm:text-xs">
                                {{ __('withdraw.locked_balance') }}:
                                {{ $currency }} {{ number_format((float) $lockedBalance, 2) }}
                            </div>
                        </div>

                        <a
                            href="{{ route('withdraw.create') }}"
                            class="shrink-0 rounded-xl bg-[#e4003a] px-4 py-2.5 text-center text-xs font-black uppercase text-white shadow-lg sm:mt-5 sm:inline-flex sm:w-full sm:justify-center sm:rounded-2xl sm:px-5 sm:py-3 sm:text-sm"
                        >
                            {{ __('withdraw.submit_withdraw') }}
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
                        {{ __('withdraw.my_withdraw_requests') }}
                    </h2>

                    <p class="mt-1 line-clamp-1 text-xs font-bold text-[#003b7a]/60 sm:line-clamp-none sm:text-sm">
                        {{ __('withdraw.my_withdraw_requests_desc') }}
                    </p>
                </div>

                <a
                    href="{{ route('withdraw.create') }}"
                    class="hidden shrink-0 rounded-full bg-[#e4003a] px-4 py-2 text-xs font-black uppercase text-white sm:inline-flex"
                >
                    {{ __('withdraw.submit_withdraw') }}
                </a>
            </div>

            @if ($requests->isNotEmpty())
                <div class="divide-y divide-[#003b7a]/10 overflow-hidden rounded-[1.25rem] border border-[#003b7a]/10">
                    @foreach ($requests as $requestItem)
                        <a
                            href="{{ route('withdraw.show', $requestItem) }}"
                            class="block bg-[#fff8c7] p-3 transition hover:bg-[#fff3a0] sm:p-4"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="break-words text-lg font-black leading-tight text-[#003b7a] sm:text-xl">
                                        {{ $currency }} {{ number_format((float) $requestItem->amount, 2) }}
                                    </div>

                                    <div class="mt-1 text-[10px] font-bold text-[#003b7a]/60 sm:text-xs">
                                        {{ $requestItem->created_at->format('d M Y, h:i A') }}
                                    </div>

                                    <div class="mt-0.5 truncate text-[10px] font-bold text-[#003b7a]/60 sm:text-xs">
                                        {{ $requestItem->bank_name }} · {{ $requestItem->bank_account_no }}
                                    </div>
                                </div>

                                <span
                                    class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-black uppercase sm:px-3 sm:text-xs
                                    @class([
                                        'bg-yellow-100 text-yellow-700' => $requestItem->status === 'pending',
                                        'bg-green-100 text-green-700' => $requestItem->status === 'approved',
                                        'bg-red-100 text-red-700' => $requestItem->status === 'rejected',
                                    ])"
                                >
                                    {{ __('status.' . $requestItem->status) }}
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
                        {{ __('withdraw.no_requests') }}
                    </div>

                    <p class="mt-2 text-sm font-bold text-[#003b7a]/70">
                        {{ __('withdraw.no_requests_desc') }}
                    </p>

                    <a
                        href="{{ route('withdraw.create') }}"
                        class="mt-5 inline-flex rounded-full bg-[#e4003a] px-6 py-3 text-sm font-black uppercase text-white"
                    >
                        {{ __('withdraw.submit_withdraw') }}
                    </a>
                </div>
            @endif
        </div>
    </section>
@endsection