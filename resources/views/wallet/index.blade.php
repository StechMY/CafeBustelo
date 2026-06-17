@extends('layouts.app')

@section('title', __('wallet.wallet'))

@section('content')
    @php
        $currency = config('app.currency', 'RM');

        $balanceCards = [
            [
                'label' => __('wallet.recharge_balance'),
                'value' => $summary['recharge_balance'],
                'desc' => __('wallet.recharge_balance_desc'),
                'class' => 'bg-[#003b7a] text-white',
                'valueClass' => 'text-white',
            ],
            [
                'label' => __('wallet.recharge_locked'),
                'value' => $summary['recharge_locked'],
                'desc' => __('wallet.locked_balance_desc'),
                'class' => 'bg-white text-[#003b7a]',
                'valueClass' => 'text-[#003b7a]',
            ],
            [
                'label' => __('wallet.withdrawable_balance'),
                'value' => $summary['withdrawable_balance'],
                'desc' => __('wallet.withdrawable_balance_desc'),
                'class' => 'bg-[#e4003a] text-white',
                'valueClass' => 'text-white',
            ],
            [
                'label' => __('wallet.withdrawable_locked'),
                'value' => $summary['withdrawable_locked'],
                'desc' => __('wallet.locked_balance_desc'),
                'class' => 'bg-white text-[#003b7a]',
                'valueClass' => 'text-[#003b7a]',
            ],
        ];

        $filteredSummaryCards = [
            [
                'label' => __('wallet.all_filtered_credit'),
                'value' => $summary['all_filtered_credit'],
                'class' => 'bg-green-50 text-green-700',
            ],
            [
                'label' => __('wallet.all_filtered_debit'),
                'value' => $summary['all_filtered_debit'],
                'class' => 'bg-red-50 text-[#e4003a]',
            ],
            [
                'label' => __('wallet.all_filtered_total'),
                'value' => $summary['all_filtered_total'],
                'class' => 'bg-[#fff8c7] text-[#003b7a]',
            ],
            [
                'label' => __('wallet.all_filtered_net'),
                'value' => $summary['all_filtered_net'],
                'class' => $summary['all_filtered_net'] >= 0 ? 'bg-green-50 text-green-700' : 'bg-red-50 text-[#e4003a]',
            ],
        ];

        $pageSummaryCards = [
            [
                'label' => __('wallet.page_credit'),
                'value' => $summary['page_credit'],
                'class' => 'bg-green-50 text-green-700',
            ],
            [
                'label' => __('wallet.page_debit'),
                'value' => $summary['page_debit'],
                'class' => 'bg-red-50 text-[#e4003a]',
            ],
            [
                'label' => __('wallet.page_total'),
                'value' => $summary['page_total'],
                'class' => 'bg-[#fff8c7] text-[#003b7a]',
            ],
            [
                'label' => __('wallet.page_net'),
                'value' => $summary['page_net'],
                'class' => $summary['page_net'] >= 0 ? 'bg-green-50 text-green-700' : 'bg-red-50 text-[#e4003a]',
            ],
        ];
    @endphp

    <section class="bg-[#ffd900]">
        <div class="mx-auto max-w-7xl px-3 py-3 sm:px-6 sm:py-8 lg:px-8">
            <div class="grid gap-4 lg:grid-cols-[1.35fr_0.9fr] lg:items-center">
                <div class="min-w-0">
                    <div class="mb-2 inline-flex rounded-full bg-white/70 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide shadow sm:mb-3 sm:px-4 sm:py-2 sm:text-xs">
                        {{ __('wallet.wallet_center') }}
                    </div>

                    <h1 class="max-w-3xl text-3xl font-black uppercase leading-[0.9] tracking-tight text-[#003b7a] sm:text-6xl lg:text-7xl">
                        {{ __('wallet.hero_title') }}
                    </h1>

                    <p class="mt-2 line-clamp-2 max-w-2xl text-xs font-bold leading-relaxed text-[#003b7a]/80 sm:mt-5 sm:line-clamp-none sm:text-base">
                        {{ __('wallet.hero_description') }}
                    </p>
                </div>

                <div class="rounded-[1.35rem] border-3 border-[#003b7a] bg-white p-3 shadow-[6px_6px_0_#003b7a] sm:rounded-[2rem] sm:border-4 sm:p-6 sm:shadow-[10px_10px_0_#003b7a]">
                    <div class="text-[10px] font-black uppercase text-[#e4003a] sm:text-sm">
                        {{ __('wallet.total_overview') }}
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 sm:mt-4 sm:gap-3">
                        <div class="min-w-0 rounded-xl bg-[#003b7a] p-3 text-white sm:rounded-2xl sm:p-4">
                            <div class="text-[8px] font-black uppercase leading-tight text-white/70 sm:text-[10px]">
                                {{ __('wallet.total_credit') }}
                            </div>

                            <div class="mt-1 break-words text-lg font-black leading-tight sm:text-2xl">
                                {{ $currency }} {{ number_format((float) $summary['total_credit'], 2) }}
                            </div>
                        </div>

                        <div class="min-w-0 rounded-xl bg-[#e4003a] p-3 text-white sm:rounded-2xl sm:p-4">
                            <div class="text-[8px] font-black uppercase leading-tight text-white/70 sm:text-[10px]">
                                {{ __('wallet.total_debit') }}
                            </div>

                            <div class="mt-1 break-words text-lg font-black leading-tight sm:text-2xl">
                                {{ $currency }} {{ number_format((float) $summary['total_debit'], 2) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-3 py-4 sm:px-6 sm:py-8 lg:px-8">
        <div class="grid grid-cols-2 gap-2 sm:gap-4 xl:grid-cols-4">
            @foreach ($balanceCards as $card)
                <div class="min-w-0 rounded-[1rem] p-3 shadow-lg ring-1 ring-black/5 sm:rounded-[2rem] sm:p-6 {{ $card['class'] }}">
                    <div class="line-clamp-2 text-[9px] font-black uppercase leading-tight opacity-70 sm:text-sm">
                        {{ $card['label'] }}
                    </div>

                    <div class="mt-2 break-words text-xl font-black leading-tight sm:text-4xl {{ $card['valueClass'] }}">
                        {{ $currency }} {{ number_format((float) $card['value'], 2) }}
                    </div>

                    <div class="mt-3 hidden text-xs font-bold opacity-70 sm:block">
                        {{ $card['desc'] }}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4 rounded-[1.35rem] bg-white p-4 shadow-xl ring-1 ring-black/5 sm:mt-5 sm:rounded-[2rem] sm:p-6">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                <div class="min-w-0">
                    <h2 class="text-lg font-black uppercase text-[#003b7a] sm:text-2xl">
                        {{ __('wallet.transaction_history') }}
                    </h2>

                    <p class="mt-1 line-clamp-1 text-xs font-bold text-[#003b7a]/60 sm:line-clamp-none sm:text-sm">
                        {{ __('wallet.transaction_history_desc') }}
                    </p>
                </div>
            </div>

            <form
                method="GET"
                action="{{ route('wallet.index') }}"
                class="mt-4 grid w-full min-w-0 grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-[1fr_1fr_1.15fr_1fr_1fr_auto_auto]"
            >
                <select
                    name="wallet_type"
                    class="min-w-0 w-full rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] px-2.5 py-2 text-[10px] font-black text-[#003b7a] outline-none sm:rounded-2xl sm:px-3 sm:text-sm"
                >
                    <option value="all" @selected($walletType === 'all')>{{ __('wallet.all_wallets') }}</option>
                    <option value="recharge" @selected($walletType === 'recharge')>{{ __('wallet.recharge_wallet') }}</option>
                    <option value="withdrawable" @selected($walletType === 'withdrawable')>{{ __('wallet.withdrawable_wallet') }}</option>
                </select>

                <select
                    name="direction"
                    class="min-w-0 w-full rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] px-2.5 py-2 text-[10px] font-black text-[#003b7a] outline-none sm:rounded-2xl sm:px-3 sm:text-sm"
                >
                    <option value="all" @selected($direction === 'all')>{{ __('wallet.all_directions') }}</option>
                    <option value="credit" @selected($direction === 'credit')>{{ __('wallet.credit') }}</option>
                    <option value="debit" @selected($direction === 'debit')>{{ __('wallet.debit') }}</option>
                </select>

                <select
                    name="type"
                    class="col-span-2 min-w-0 w-full rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] px-2.5 py-2 text-[10px] font-black text-[#003b7a] outline-none md:col-span-1 sm:rounded-2xl sm:px-3 sm:text-sm"
                >
                    <option value="all" @selected($type === 'all')>{{ __('wallet.all_types') }}</option>

                    @foreach ($transactionTypes as $transactionType)
                        <option value="{{ $transactionType }}" @selected($type === $transactionType)>
                            {{ __('wallet.types.' . $transactionType) !== 'wallet.types.' . $transactionType
                                ? __('wallet.types.' . $transactionType)
                                : str($transactionType)->headline() }}
                        </option>
                    @endforeach
                </select>

                <input
                    type="date"
                    name="start_date"
                    value="{{ $startDate }}"
                    class="min-w-0 w-full rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] px-2.5 py-2 text-[10px] font-black text-[#003b7a] outline-none sm:rounded-2xl sm:px-3 sm:text-sm"
                >

                <input
                    type="date"
                    name="end_date"
                    value="{{ $endDate }}"
                    class="min-w-0 w-full rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] px-2.5 py-2 text-[10px] font-black text-[#003b7a] outline-none sm:rounded-2xl sm:px-3 sm:text-sm"
                >

                <button
                    type="submit"
                    class="rounded-xl bg-[#003b7a] px-4 py-2 text-[10px] font-black uppercase text-white sm:rounded-2xl sm:text-sm xl:whitespace-nowrap"
                >
                    {{ __('wallet.filter') }}
                </button>

                <a
                    href="{{ route('wallet.index') }}"
                    class="rounded-xl bg-[#fff8c7] px-4 py-2 text-center text-[10px] font-black uppercase text-[#003b7a] ring-2 ring-[#003b7a] sm:rounded-2xl sm:text-sm xl:whitespace-nowrap"
                >
                    {{ __('wallet.reset') }}
                </a>
            </form>

            @if ($errors->any())
                <div class="mt-4 rounded-2xl bg-red-50 p-4 text-sm font-bold text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="mt-4 grid gap-3 xl:grid-cols-2">
                <div class="rounded-[1.25rem] bg-[#f8fafc] p-3 ring-1 ring-[#003b7a]/10 sm:p-4">
                    <div class="text-xs font-black uppercase text-[#003b7a]/60">
                        {{ __('wallet.all_filtered_summary') }}
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        @foreach ($filteredSummaryCards as $card)
                            <div class="rounded-2xl p-3 {{ $card['class'] }}">
                                <div class="text-[9px] font-black uppercase opacity-60">
                                    {{ $card['label'] }}
                                </div>

                                <div class="mt-1 break-words text-sm font-black sm:text-base">
                                    {{ $currency }} {{ number_format((float) $card['value'], 2) }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-[1.25rem] bg-[#f8fafc] p-3 ring-1 ring-[#003b7a]/10 sm:p-4">
                    <div class="text-xs font-black uppercase text-[#003b7a]/60">
                        {{ __('wallet.current_page_summary') }}
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        @foreach ($pageSummaryCards as $card)
                            <div class="rounded-2xl p-3 {{ $card['class'] }}">
                                <div class="text-[9px] font-black uppercase opacity-60">
                                    {{ $card['label'] }}
                                </div>

                                <div class="mt-1 break-words text-sm font-black sm:text-base">
                                    {{ $currency }} {{ number_format((float) $card['value'], 2) }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            @if ($transactions->isNotEmpty())
                <div class="mt-4 divide-y divide-[#003b7a]/10 overflow-hidden rounded-[1.25rem] border border-[#003b7a]/10 xl:hidden">
                    @foreach ($transactions as $transaction)
                        <a
                            href="{{ route('wallet.transactions.show', $transaction) }}"
                            class="block bg-[#fff8c7] p-3 transition hover:bg-[#fff3a5]"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase
                                            @class([
                                                'bg-green-100 text-green-700' => $transaction->direction === 'credit',
                                                'bg-red-100 text-red-700' => $transaction->direction === 'debit',
                                            ])"
                                        >
                                            {{ __('wallet.' . $transaction->direction) }}
                                        </span>

                                        <span class="rounded-full bg-[#003b7a] px-2 py-0.5 text-[9px] font-black uppercase text-white">
                                            {{ __('wallet.' . $transaction->wallet_type . '_wallet') }}
                                        </span>
                                    </div>

                                    <div class="mt-1 truncate text-xs font-black leading-tight text-[#003b7a]">
                                        {{ __('wallet.types.' . $transaction->type) !== 'wallet.types.' . $transaction->type
                                            ? __('wallet.types.' . $transaction->type)
                                            : str($transaction->type)->headline() }}
                                    </div>

                                    <div class="mt-0.5 text-[10px] font-bold text-[#003b7a]/55">
                                        {{ $transaction->created_at->format('d M Y, h:i A') }}
                                    </div>
                                </div>

                                <div class="shrink-0 text-right">
                                    <div class="text-sm font-black leading-tight sm:text-base {{ $transaction->direction === 'credit' ? 'text-green-600' : 'text-[#e4003a]' }}">
                                        {{ $transaction->direction === 'credit' ? '+' : '-' }}
                                        {{ $currency }} {{ number_format((float) $transaction->amount, 2) }}
                                    </div>

                                    <div class="mt-0.5 text-[9px] font-bold text-[#003b7a]/50">
                                        {{ __('wallet.after') }}:
                                        {{ $currency }} {{ number_format((float) $transaction->balance_after, 2) }}
                                    </div>
                                </div>
                            </div>

                            @if ($transaction->remarks)
                                <div class="mt-2 line-clamp-1 rounded-xl bg-white/70 px-3 py-2 text-[10px] font-bold text-[#003b7a]/65">
                                    {{ $transaction->remarks }}
                                </div>
                            @endif
                        </a>
                    @endforeach
                </div>

                <div class="mt-5 hidden overflow-x-auto rounded-[1.25rem] border border-[#003b7a]/10 xl:block">
                    <table class="w-full min-w-[980px] table-fixed divide-y divide-[#003b7a]/10 text-sm">
                        <thead class="bg-[#fff8c7]">
                            <tr>
                                <th class="w-[120px] px-3 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('wallet.date') }}
                                </th>
                                <th class="w-[145px] px-3 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('wallet.wallet') }}
                                </th>
                                <th class="w-[170px] px-3 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('wallet.type') }}
                                </th>
                                <th class="w-[105px] px-3 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('wallet.direction') }}
                                </th>
                                <th class="w-[125px] px-3 py-3 text-right text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('wallet.amount') }}
                                </th>
                                <th class="w-[125px] px-3 py-3 text-right text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('wallet.before') }}
                                </th>
                                <th class="w-[125px] px-3 py-3 text-right text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('wallet.after') }}
                                </th>
                                <th class="px-3 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('wallet.remarks') }}
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-[#003b7a]/10 bg-white">
                            @foreach ($transactions as $transaction)
                                <tr class="hover:bg-[#fff8c7]/40">
                                    <td class="px-3 py-3 align-top font-bold text-[#003b7a]/70">
                                        {{ $transaction->created_at->format('d M Y') }}<br>
                                        <span class="text-xs">{{ $transaction->created_at->format('h:i A') }}</span>
                                    </td>

                                    <td class="px-3 py-3 align-top">
                                        <span class="inline-flex max-w-full rounded-full bg-[#003b7a] px-3 py-1 text-xs font-black uppercase leading-tight text-white">
                                            {{ __('wallet.' . $transaction->wallet_type . '_wallet') }}
                                        </span>
                                    </td>

                                    <td class="px-3 py-3 align-top font-black leading-tight text-[#003b7a]">
                                        <a href="{{ route('wallet.transactions.show', $transaction) }}" class="hover:underline">
                                            {{ __('wallet.types.' . $transaction->type) !== 'wallet.types.' . $transaction->type
                                                ? __('wallet.types.' . $transaction->type)
                                                : str($transaction->type)->headline() }}
                                        </a>
                                    </td>

                                    <td class="px-3 py-3 align-top">
                                        <span
                                            class="rounded-full px-3 py-1 text-xs font-black uppercase
                                            @class([
                                                'bg-green-100 text-green-700' => $transaction->direction === 'credit',
                                                'bg-red-100 text-red-700' => $transaction->direction === 'debit',
                                            ])"
                                        >
                                            {{ __('wallet.' . $transaction->direction) }}
                                        </span>
                                    </td>

                                    <td class="px-3 py-3 text-right align-top font-black {{ $transaction->direction === 'credit' ? 'text-green-600' : 'text-[#e4003a]' }}">
                                        {{ $transaction->direction === 'credit' ? '+' : '-' }}
                                        {{ $currency }} {{ number_format((float) $transaction->amount, 2) }}
                                    </td>

                                    <td class="px-3 py-3 text-right align-top font-bold text-[#003b7a]/70">
                                        {{ $currency }} {{ number_format((float) $transaction->balance_before, 2) }}
                                    </td>

                                    <td class="px-3 py-3 text-right align-top font-bold text-[#003b7a]">
                                        {{ $currency }} {{ number_format((float) $transaction->balance_after, 2) }}
                                    </td>

                                    <td class="px-3 py-3 align-top font-bold leading-tight text-[#003b7a]/60">
                                        <div class="line-clamp-2">
                                            {{ $transaction->remarks ?: '-' }}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 sm:mt-5">
                    {{ $transactions->links() }}
                </div>
            @else
                <div class="mt-4 rounded-[1.25rem] bg-[#fff8c7] p-6 text-center sm:mt-5 sm:p-8">
                    <div class="text-xl font-black text-[#003b7a] sm:text-2xl">
                        {{ __('wallet.no_transactions') }}
                    </div>

                    <p class="mt-2 text-sm font-bold text-[#003b7a]/70">
                        {{ __('wallet.no_transactions_desc') }}
                    </p>

                    <a
                        href="{{ route('recharge.index') }}"
                        class="mt-5 inline-flex rounded-full bg-[#e4003a] px-6 py-3 text-sm font-black uppercase text-white"
                    >
                        {{ __('dashboard.recharge') }}
                    </a>
                </div>
            @endif
        </div>
    </section>
@endsection