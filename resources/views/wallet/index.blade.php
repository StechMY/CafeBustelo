@extends('layouts.app')

@section('title', __('wallet.wallet'))

@section('content')
    <section class="bg-[#ffd900]">
        <div class="mx-auto max-w-7xl px-3 py-5 sm:px-6 sm:py-8 lg:px-8">
            <div class="grid gap-5 lg:grid-cols-[1.35fr_0.9fr] lg:items-center">
                <div class="min-w-0">
                    <div
                         class="mb-3 inline-flex rounded-full bg-white/70 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide shadow sm:px-4 sm:py-2 sm:text-xs">
                        {{ __('wallet.wallet_center') }}
                    </div>

                    <h1
                        class="max-w-3xl text-[2.6rem] font-black uppercase leading-[0.85] tracking-tight text-[#003b7a] sm:text-6xl lg:text-7xl">
                        {{ __('wallet.hero_title') }}
                    </h1>

                    <p class="mt-3 max-w-2xl text-sm font-bold leading-relaxed text-[#003b7a]/80 sm:mt-5 sm:text-base">
                        {{ __('wallet.hero_description') }}
                    </p>
                </div>

                <div
                     class="rounded-[1.5rem] border-4 border-[#003b7a] bg-white p-4 shadow-[7px_7px_0_#003b7a] sm:rounded-[2rem] sm:p-6 sm:shadow-[10px_10px_0_#003b7a]">
                    <div class="text-xs font-black uppercase text-[#e4003a] sm:text-sm">
                        {{ __('wallet.total_overview') }}
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="min-w-0 rounded-2xl bg-[#003b7a] p-3 text-white sm:p-4">
                            <div class="text-[9px] font-black uppercase leading-tight text-white/70 sm:text-[10px]">
                                {{ __('wallet.total_credit') }}
                            </div>

                            <div class="mt-1 break-words text-xl font-black leading-tight sm:text-2xl">
                                {{ config('app.currency') }} {{ number_format((float) $summary['total_credit'], 2) }}
                            </div>
                        </div>

                        <div class="min-w-0 rounded-2xl bg-[#e4003a] p-3 text-white sm:p-4">
                            <div class="text-[9px] font-black uppercase leading-tight text-white/70 sm:text-[10px]">
                                {{ __('wallet.total_debit') }}
                            </div>

                            <div class="mt-1 break-words text-xl font-black leading-tight sm:text-2xl">
                                {{ config('app.currency') }} {{ number_format((float) $summary['total_debit'], 2) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-3 py-5 sm:px-6 sm:py-8 lg:px-8">
        <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            <div class="min-w-0 rounded-[1.25rem] bg-[#003b7a] p-4 text-white shadow-lg sm:rounded-[2rem] sm:p-6">
                <div class="text-[9px] font-black uppercase leading-tight text-white/70 sm:text-sm">
                    {{ __('wallet.recharge_balance') }}
                </div>

                <div class="mt-2 break-words text-xl font-black leading-tight sm:text-4xl">
                    {{ config('app.currency') }} {{ number_format((float) $summary['recharge_balance'], 2) }}
                </div>

                <div class="mt-3 text-[10px] font-bold text-white/70 sm:text-xs">
                    {{ __('wallet.recharge_balance_desc') }}
                </div>
            </div>

            <div class="min-w-0 rounded-[1.25rem] bg-white p-4 shadow-lg ring-1 ring-black/5 sm:rounded-[2rem] sm:p-6">
                <div class="text-[9px] font-black uppercase leading-tight text-[#003b7a]/70 sm:text-sm">
                    {{ __('wallet.recharge_locked') }}
                </div>

                <div class="mt-2 break-words text-xl font-black leading-tight text-[#003b7a] sm:text-4xl">
                    {{ config('app.currency') }} {{ number_format((float) $summary['recharge_locked'], 2) }}
                </div>

                <div class="mt-3 text-[10px] font-bold text-[#003b7a]/60 sm:text-xs">
                    {{ __('wallet.locked_balance_desc') }}
                </div>
            </div>

            <div class="min-w-0 rounded-[1.25rem] bg-[#e4003a] p-4 text-white shadow-lg sm:rounded-[2rem] sm:p-6">
                <div class="text-[9px] font-black uppercase leading-tight text-white/70 sm:text-sm">
                    {{ __('wallet.withdrawable_balance') }}
                </div>

                <div class="mt-2 break-words text-xl font-black leading-tight sm:text-4xl">
                    {{ config('app.currency') }} {{ number_format((float) $summary['withdrawable_balance'], 2) }}
                </div>

                <div class="mt-3 text-[10px] font-bold text-white/70 sm:text-xs">
                    {{ __('wallet.withdrawable_balance_desc') }}
                </div>
            </div>

            <div class="min-w-0 rounded-[1.25rem] bg-white p-4 shadow-lg ring-1 ring-black/5 sm:rounded-[2rem] sm:p-6">
                <div class="text-[9px] font-black uppercase leading-tight text-[#003b7a]/70 sm:text-sm">
                    {{ __('wallet.withdrawable_locked') }}
                </div>

                <div class="mt-2 break-words text-xl font-black leading-tight text-[#003b7a] sm:text-4xl">
                    {{ config('app.currency') }} {{ number_format((float) $summary['withdrawable_locked'], 2) }}
                </div>

                <div class="mt-3 text-[10px] font-bold text-[#003b7a]/60 sm:text-xs">
                    {{ __('wallet.locked_balance_desc') }}
                </div>
            </div>
        </div>

        <div class="mt-5 rounded-[1.5rem] bg-white p-4 shadow-xl ring-1 ring-black/5 sm:rounded-[2rem] sm:p-6">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="min-w-0">
                    <h2 class="text-xl font-black uppercase text-[#003b7a] sm:text-2xl">
                        {{ __('wallet.transaction_history') }}
                    </h2>

                    <p class="mt-1 text-xs font-bold text-[#003b7a]/60 sm:text-sm">
                        {{ __('wallet.transaction_history_desc') }}
                    </p>
                </div>

                <form
                      method="GET"
                      action="{{ route('wallet.index') }}"
                      class="grid w-full min-w-0 grid-cols-1 gap-2 sm:grid-cols-2 xl:max-w-[720px] xl:grid-cols-[1fr_1fr_1.15fr_auto]">
                    <select
                            name="wallet_type"
                            class="min-w-0 w-full rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-3 py-2 text-xs font-black text-[#003b7a] outline-none sm:text-sm">
                        <option value="all" @selected($walletType === 'all')>{{ __('wallet.all_wallets') }}</option>
                        <option value="recharge" @selected($walletType === 'recharge')>{{ __('wallet.recharge_wallet') }}</option>
                        <option value="withdrawable" @selected($walletType === 'withdrawable')>{{ __('wallet.withdrawable_wallet') }}
                        </option>
                    </select>

                    <select
                            name="direction"
                            class="min-w-0 w-full rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-3 py-2 text-xs font-black text-[#003b7a] outline-none sm:text-sm">
                        <option value="all" @selected($direction === 'all')>{{ __('wallet.all_directions') }}</option>
                        <option value="credit" @selected($direction === 'credit')>{{ __('wallet.credit') }}</option>
                        <option value="debit" @selected($direction === 'debit')>{{ __('wallet.debit') }}</option>
                    </select>

                    <select
                            name="type"
                            class="min-w-0 w-full rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-3 py-2 text-xs font-black text-[#003b7a] outline-none sm:text-sm">
                        <option value="all" @selected($type === 'all')>{{ __('wallet.all_types') }}</option>

                        @foreach ($transactionTypes as $transactionType)
                            <option value="{{ $transactionType }}" @selected($type === $transactionType)>
                                {{ __('wallet.types.' . $transactionType) !== 'wallet.types.' . $transactionType
                                    ? __('wallet.types.' . $transactionType)
                                    : str($transactionType)->headline() }}
                            </option>
                        @endforeach
                    </select>

                    <button
                            type="submit"
                            class="w-full rounded-2xl bg-[#003b7a] px-5 py-2 text-xs font-black uppercase text-white sm:text-sm xl:w-auto xl:whitespace-nowrap">
                        {{ __('wallet.filter') }}
                    </button>
                </form>
            </div>

            @if ($transactions->isNotEmpty())
                <div class="mt-5 space-y-3 xl:hidden">
                    @foreach ($transactions as $transaction)
                        <a href="{{ route('wallet.transactions.show', $transaction) }}"
                           class="block rounded-[1.25rem] bg-[#fff8c7] p-4 shadow-sm ring-1 ring-[#003b7a]/10">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                              class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase
                                @class([
                                    'bg-green-100 text-green-700' => $transaction->direction === 'credit',
                                    'bg-red-100 text-red-700' => $transaction->direction === 'debit',
                                ])">
                                            {{ __('wallet.' . $transaction->direction) }}
                                        </span>

                                        <span
                                              class="rounded-full bg-[#003b7a] px-2.5 py-1 text-[10px] font-black uppercase text-white">
                                            {{ __('wallet.' . $transaction->wallet_type . '_wallet') }}
                                        </span>
                                    </div>

                                    <div class="mt-2 text-sm font-black leading-tight text-[#003b7a]">
                                        {{ __('wallet.types.' . $transaction->type) !== 'wallet.types.' . $transaction->type
                                            ? __('wallet.types.' . $transaction->type)
                                            : str($transaction->type)->headline() }}
                                    </div>

                                    <div class="mt-1 text-[11px] font-bold text-[#003b7a]/60">
                                        {{ $transaction->created_at->format('d M Y, h:i A') }}
                                    </div>
                                </div>

                                <div class="shrink-0 text-right">
                                    <div
                                         class="text-base font-black leading-tight {{ $transaction->direction === 'credit' ? 'text-green-600' : 'text-[#e4003a]' }}">
                                        {{ $transaction->direction === 'credit' ? '+' : '-' }}
                                        {{ config('app.currency') }} {{ number_format((float) $transaction->amount, 2) }}
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <div class="rounded-2xl bg-white p-3">
                                    <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                        {{ __('wallet.before') }}
                                    </div>

                                    <div class="mt-1 text-sm font-black text-[#003b7a]">
                                        {{ config('app.currency') }} {{ number_format((float) $transaction->balance_before, 2) }}
                                    </div>
                                </div>

                                <div class="rounded-2xl bg-white p-3">
                                    <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                        {{ __('wallet.after') }}
                                    </div>

                                    <div class="mt-1 text-sm font-black text-[#003b7a]">
                                        {{ config('app.currency') }} {{ number_format((float) $transaction->balance_after, 2) }}
                                    </div>
                                </div>
                            </div>

                            @if ($transaction->remarks)
                                <div
                                     class="mt-3 rounded-2xl bg-white p-3 text-xs font-bold leading-relaxed text-[#003b7a]/70">
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
                                <th class="w-[130px] px-4 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('wallet.date') }}
                                </th>
                                <th class="w-[160px] px-4 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('wallet.wallet') }}
                                </th>
                                <th class="w-[180px] px-4 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('wallet.type') }}
                                </th>
                                <th class="w-[120px] px-4 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('wallet.direction') }}
                                </th>
                                <th class="w-[130px] px-4 py-3 text-right text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('wallet.amount') }}
                                </th>
                                <th class="w-[130px] px-4 py-3 text-right text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('wallet.before') }}
                                </th>
                                <th class="w-[130px] px-4 py-3 text-right text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('wallet.after') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('wallet.remarks') }}
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-[#003b7a]/10 bg-white">
                            @foreach ($transactions as $transaction)
                                <tr class="hover:bg-[#fff8c7]/40">
                                    <td class="px-4 py-3 align-top font-bold text-[#003b7a]/70">
                                        {{ $transaction->created_at->format('d M Y') }}<br>
                                        <span class="text-xs">{{ $transaction->created_at->format('h:i A') }}</span>
                                    </td>

                                    <td class="px-4 py-3 align-top">
                                        <span
                                              class="inline-flex max-w-full rounded-full bg-[#003b7a] px-3 py-1 text-xs font-black uppercase leading-tight text-white">
                                            {{ __('wallet.' . $transaction->wallet_type . '_wallet') }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3 align-top font-black leading-tight text-[#003b7a]">
                                        <a href="{{ route('wallet.transactions.show', $transaction) }}"
                                           class="hover:underline">
                                            {{ __('wallet.types.' . $transaction->type) !== 'wallet.types.' . $transaction->type
                                                ? __('wallet.types.' . $transaction->type)
                                                : str($transaction->type)->headline() }}
                                        </a>
                                    </td>

                                    <td class="px-4 py-3 align-top">
                                        <span
                                              class="rounded-full px-3 py-1 text-xs font-black uppercase
                                @class([
                                    'bg-green-100 text-green-700' => $transaction->direction === 'credit',
                                    'bg-red-100 text-red-700' => $transaction->direction === 'debit',
                                ])">
                                            {{ __('wallet.' . $transaction->direction) }}
                                        </span>
                                    </td>

                                    <td
                                        class="px-4 py-3 text-right align-top font-black {{ $transaction->direction === 'credit' ? 'text-green-600' : 'text-[#e4003a]' }}">
                                        {{ $transaction->direction === 'credit' ? '+' : '-' }}
                                        {{ config('app.currency') }} {{ number_format((float) $transaction->amount, 2) }}
                                    </td>

                                    <td class="px-4 py-3 text-right align-top font-bold text-[#003b7a]/70">
                                        {{ config('app.currency') }} {{ number_format((float) $transaction->balance_before, 2) }}
                                    </td>

                                    <td class="px-4 py-3 text-right align-top font-bold text-[#003b7a]">
                                        {{ config('app.currency') }} {{ number_format((float) $transaction->balance_after, 2) }}
                                    </td>

                                    <td class="px-4 py-3 align-top font-bold leading-tight text-[#003b7a]/60">
                                        <div class="line-clamp-2">
                                            {{ $transaction->remarks ?: '-' }}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-5">
                    {{ $transactions->links() }}
                </div>
            @else
                <div class="mt-5 rounded-[1.25rem] bg-[#fff8c7] p-8 text-center">
                    <div class="text-2xl font-black text-[#003b7a]">
                        {{ __('wallet.no_transactions') }}
                    </div>

                    <p class="mt-2 text-sm font-bold text-[#003b7a]/70">
                        {{ __('wallet.no_transactions_desc') }}
                    </p>

                    <a href="{{ route('recharge.index') }}"
                       class="mt-5 inline-flex rounded-full bg-[#e4003a] px-6 py-3 text-sm font-black uppercase text-white">
                        {{ __('dashboard.recharge') }}
                    </a>
                </div>
            @endif
        </div>
    </section>
@endsection
