@extends('layouts.app')

@section('title', __('points.points_claim'))

@section('content')
    @php
        $currency = config('app.currency', 'RM');
        $pointsBalance = (float) $user->points_balance;
        $exchangeRate = max(0.01, (float) $rate);
        $cashPreview = $exchangeRate > 0 ? $pointsBalance * $exchangeRate : 0;
    @endphp

    <section class="bg-[#ffd900]">
        <div class="mx-auto max-w-7xl px-3 py-6 sm:px-6 sm:py-10 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr] lg:items-center">
                <div>
                    <div
                         class="inline-flex rounded-full bg-white/75 px-5 py-2 text-xs font-black uppercase tracking-[0.22em] text-[#003b7a] shadow">
                        {{ __('points.points') }}
                    </div>

                    <h1
                        class="mt-4 max-w-3xl text-4xl font-black uppercase leading-[0.9] tracking-tight text-[#003b7a] sm:text-6xl lg:text-7xl">
                        {{ __('points.points_claim') }}
                    </h1>

                    <p class="mt-5 max-w-2xl text-sm font-bold leading-relaxed text-[#003b7a]/75 sm:text-base">
                        {{ __('points.points_claim_desc') }}
                    </p>
                </div>

                <div class="rounded-[2rem] border-4 border-[#003b7a] bg-white p-5 shadow-[10px_10px_0_#003b7a] sm:p-7">
                    <div class="text-xs font-black uppercase tracking-[0.18em] text-[#e4003a]">
                        {{ __('points.points_balance') }}
                    </div>

                    <div class="mt-2 break-words text-5xl font-black leading-none text-[#003b7a] sm:text-6xl">
                        {{ number_format($pointsBalance, 2) }}
                    </div>

                    <div class="mt-4 rounded-2xl bg-[#fff8c7] p-4">
                        <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                            {{ __('points.estimated_cash_value') }}
                        </div>

                        <div class="mt-1 text-2xl font-black text-[#e4003a]">
                            {{ $currency }} {{ number_format($cashPreview, 2) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-3 py-6 sm:px-6 sm:py-8 lg:px-8">
        <div class="grid gap-5 lg:grid-cols-[380px_1fr]">
            <div class="space-y-5">
                <div class="rounded-[2rem] bg-white p-5 shadow-xl ring-1 ring-black/5 sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h2 class="text-xl font-black uppercase leading-tight text-[#003b7a] sm:text-2xl">
                                {{ __('points.redeem_points') }}
                            </h2>

                            <p class="mt-1 text-sm font-bold leading-relaxed text-[#003b7a]/55">
                                {{ __('points.redeem_points_desc') }}
                            </p>
                        </div>

                        <div class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-[#003b7a] text-[#ffd900]">
                            @include('partials.icon', ['name' => 'bonus', 'class' => 'h-6 w-6'])
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <div class="rounded-2xl bg-[#fff8c7] p-4">
                            <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                {{ __('points.exchange_rate') }}
                            </div>

                            <div class="mt-1 text-base font-black leading-tight text-[#003b7a]">
                                1.00
                                {{ __('points.points') }}
                                <br>
                                =
                                {{ $currency }} {{ number_format($exchangeRate, 2) }}
                            </div>
                        </div>

                        <div class="rounded-2xl bg-[#fff8c7] p-4">
                            <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                {{ __('points.minimum_redeem') }}
                            </div>

                            <div class="mt-1 text-base font-black leading-tight text-[#003b7a]">
                                {{ number_format((float) $minimumPoints, 2) }}
                            </div>
                        </div>
                    </div>

                    @if (!$pointsEnabled)
                        <div class="mt-5 rounded-2xl bg-red-100 p-4 text-sm font-black text-red-700">
                            {{ __('points.points_disabled') }}
                        </div>
                    @elseif (!$redeemEnabled)
                        <div class="mt-5 rounded-2xl bg-red-100 p-4 text-sm font-black text-red-700">
                            {{ __('points.redeem_disabled') }}
                        </div>
                    @elseif (!$hasApprovedDeposit)
                        <div class="mt-5 rounded-2xl bg-red-100 p-4 text-sm font-black text-red-700">
                            {{ __('points.approved_deposit_required') }}
                        </div>
                    @else
                        <form
                              method="POST"
                              action="{{ route('points.redeem') }}"
                              class="mt-5 space-y-4"
                              x-data="pointsRedeemCalculator({
                                  balance: @js($pointsBalance),
                                  rate: @js($exchangeRate),
                                  minimumPoints: @js((float) $minimumPoints),
                                  hasApprovedDeposit: @js($hasApprovedDeposit),
                                  currency: @js($currency),
                              })">
                            @csrf

                            <div>
                                <label class="mb-2 block text-xs font-black uppercase text-[#003b7a]/65">
                                    {{ __('points.points') }}
                                </label>

                                <input
                                       type="number"
                                       name="points"
                                       min="0.01"
                                       max="{{ $pointsBalance }}"
                                       step="0.01"
                                       x-model.number="points"
                                       class="w-full rounded-2xl border-2 border-[#003b7a]/15 bg-[#fff8c7] px-4 py-3 text-lg font-black text-[#003b7a] outline-none focus:border-[#003b7a]"
                                       placeholder="0.00"
                                       required>
                                <button
                                        type="button"
                                        class="mt-2 w-full rounded-2xl bg-[#fff8c7] px-4 py-2.5 text-xs font-black uppercase text-[#003b7a] ring-1 ring-[#003b7a]/15 transition hover:bg-[#ffd900]"
                                        @click="exchangeAll()">
                                    {{ __('points.exchange_all_points') }}
                                </button>
                            </div>

                            <div class="rounded-[1.5rem] bg-[#003b7a] p-4 text-white">
                                <div class="text-[10px] font-black uppercase text-white/60">
                                    {{ __('points.cash_receive') }}
                                </div>

                                <div class="mt-1 break-words text-3xl font-black text-[#ffd900]"
                                     x-text="formatMoney(cashAmount())"></div>
                            </div>

                            <button
                                    type="submit"
                                    :disabled="!canRedeem()"
                                    :class="canRedeem() ?
                                        'bg-[#e4003a] text-white hover:-translate-y-0.5 hover:bg-[#c90032]' :
                                        'cursor-not-allowed bg-gray-200 text-gray-500'"
                                    class="w-full rounded-2xl px-5 py-3 text-sm font-black uppercase shadow-lg transition">
                                {{ __('points.redeem_points') }}
                            </button>
                        </form>
                    @endif
                </div>

                <div class="rounded-[2rem] bg-[#003b7a] p-5 text-white shadow-xl sm:p-6">
                    <h3 class="text-lg font-black uppercase leading-tight sm:text-xl">
                        {{ __('points.how_to_get_points') }}
                    </h3>

                    <div class="mt-4 space-y-3 text-sm font-bold leading-relaxed text-white/80">
                        <div class="rounded-2xl bg-white/10 p-4">
                            {{ __('points.upgrade_day_rule') }}
                        </div>

                        <div class="rounded-2xl bg-white/10 p-4">
                            {{ __('points.invite_day_rule') }}
                        </div>

                        <div class="rounded-2xl bg-white/10 p-4">
                            {{ __('points.invite_day_first_purchase_rule') }}
                        </div>

                        <div class="rounded-2xl bg-white/10 p-4">
                            {{ __('points.redeem_rule') }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="min-w-0 rounded-[2rem] bg-white p-5 shadow-xl ring-1 ring-black/5 sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-xl font-black uppercase text-[#003b7a] sm:text-2xl">
                            {{ __('points.points_history') }}
                        </h2>

                        <p class="mt-1 text-sm font-bold text-[#003b7a]/55">
                            {{ __('points.points_history_desc') }}
                        </p>
                    </div>

                    <div class="rounded-full bg-[#fff8c7] px-4 py-2 text-xs font-black uppercase text-[#003b7a]">
                        {{ number_format($pointsBalance, 2) }} {{ __('points.points') }}
                    </div>
                </div>

                @if ($transactions->isNotEmpty())
                    <div class="mt-5 space-y-3 xl:hidden">
                        @foreach ($transactions as $transaction)
                            <div class="rounded-[1.25rem] bg-[#fff8c7] p-4 ring-1 ring-[#003b7a]/10">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap gap-1.5">
                                            <span
                                                  @class([
                                                      'rounded-full px-2 py-0.5 text-[9px] font-black uppercase',
                                                      'bg-green-100 text-green-700' => $transaction->direction === 'credit',
                                                      'bg-red-100 text-red-700' => $transaction->direction === 'debit',
                                                  ])>
                                                {{ __('wallet.' . $transaction->direction) }}
                                            </span>

                                            <span
                                                  class="rounded-full bg-[#003b7a] px-2 py-0.5 text-[9px] font-black uppercase text-white">
                                                {{ __('points.types.' . $transaction->type) !== 'points.types.' . $transaction->type
                                                    ? __('points.types.' . $transaction->type)
                                                    : str($transaction->type)->headline() }}
                                            </span>
                                        </div>

                                        <div class="mt-2 text-[11px] font-bold text-[#003b7a]/55">
                                            {{ $transaction->created_at->format('d M Y, h:i A') }}
                                        </div>

                                        @if ($transaction->remarks)
                                            <div class="mt-1 line-clamp-2 text-xs font-bold text-[#003b7a]/60">
                                                {{ $transaction->remarks }}
                                            </div>
                                        @endif
                                    </div>

                                    <div class="shrink-0 text-right">
                                        <div
                                             class="text-lg font-black leading-tight {{ $transaction->direction === 'credit' ? 'text-green-600' : 'text-[#e4003a]' }}">
                                            {{ $transaction->direction === 'credit' ? '+' : '-' }}
                                            {{ number_format((float) $transaction->points, 2) }}
                                        </div>

                                        <div class="mt-1 text-[10px] font-bold text-[#003b7a]/50">
                                            {{ __('wallet.after') }}:
                                            {{ number_format((float) $transaction->balance_after, 2) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-5 hidden overflow-hidden rounded-[1.25rem] border border-[#003b7a]/10 xl:block">
                        <table class="w-full table-auto divide-y divide-[#003b7a]/10 text-sm">
                            <thead class="bg-[#fff8c7]">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                        {{ __('wallet.date') }}
                                    </th>

                                    <th class="px-4 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                        {{ __('wallet.type') }}
                                    </th>

                                    <th class="px-4 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                        {{ __('wallet.direction') }}
                                    </th>

                                    <th class="px-4 py-3 text-right text-xs font-black uppercase text-[#003b7a]/70">
                                        {{ __('points.points') }}
                                    </th>

                                    <th class="px-4 py-3 text-right text-xs font-black uppercase text-[#003b7a]/70">
                                        {{ __('wallet.before') }}
                                    </th>

                                    <th class="px-4 py-3 text-right text-xs font-black uppercase text-[#003b7a]/70">
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
                                        <td class="whitespace-nowrap px-4 py-3 align-top font-bold text-[#003b7a]/70">
                                            {{ $transaction->created_at->format('d M Y') }}<br>
                                            <span class="text-xs">{{ $transaction->created_at->format('h:i A') }}</span>
                                        </td>

                                        <td class="px-4 py-3 align-top font-black leading-tight text-[#003b7a]">
                                            <div class="max-w-[150px]">
                                                {{ __('points.types.' . $transaction->type) !== 'points.types.' . $transaction->type
                                                    ? __('points.types.' . $transaction->type)
                                                    : str($transaction->type)->headline() }}
                                            </div>
                                        </td>

                                        <td class="px-4 py-3 align-top">
                                            <span
                                                  @class([
                                                      'rounded-full px-3 py-1 text-xs font-black uppercase',
                                                      'bg-green-100 text-green-700' => $transaction->direction === 'credit',
                                                      'bg-red-100 text-red-700' => $transaction->direction === 'debit',
                                                  ])>
                                                {{ __('wallet.' . $transaction->direction) }}
                                            </span>
                                        </td>

                                        <td
                                            class="whitespace-nowrap px-4 py-3 text-right align-top font-black {{ $transaction->direction === 'credit' ? 'text-green-600' : 'text-[#e4003a]' }}">
                                            {{ $transaction->direction === 'credit' ? '+' : '-' }}
                                            {{ number_format((float) $transaction->points, 2) }}
                                        </td>

                                        <td
                                            class="whitespace-nowrap px-4 py-3 text-right align-top font-bold text-[#003b7a]/70">
                                            {{ number_format((float) $transaction->balance_before, 2) }}
                                        </td>

                                        <td
                                            class="whitespace-nowrap px-4 py-3 text-right align-top font-bold text-[#003b7a]">
                                            {{ number_format((float) $transaction->balance_after, 2) }}
                                        </td>

                                        <td class="px-4 py-3 align-top font-bold leading-tight text-[#003b7a]/60">
                                            <div class="max-w-[190px] truncate" title="{{ $transaction->remarks ?: '-' }}">
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
                        <div class="text-xl font-black text-[#003b7a]">
                            {{ __('points.no_transactions') }}
                        </div>

                        <p class="mt-2 text-sm font-bold text-[#003b7a]/70">
                            {{ __('points.no_transactions_desc') }}
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        function pointsRedeemCalculator(config) {
            return {
                balance: Number(config.balance || 0),
                rate: Number(config.rate || 100),
                minimumPoints: Number(config.minimumPoints || 0),
                hasApprovedDeposit: Boolean(config.hasApprovedDeposit),
                currency: config.currency || 'RM',
                points: 0,

                exchangeAll() {
                    this.points = this.balance;
                },

                canRedeem() {
                    const cleanPoints = Number(this.points || 0);

                    if (cleanPoints <= 0) {
                        return false;
                    }

                    if (!this.hasApprovedDeposit) {
                        return false;
                    }

                    if (cleanPoints > this.balance) {
                        return false;
                    }

                    if (this.minimumPoints > 0 && cleanPoints < this.minimumPoints) {
                        return false;
                    }

                    return true;
                },

                cashAmount() {
                    if (this.rate <= 0) {
                        return 0;
                    }

                    const cleanPoints = Number(this.points || 0);

                    if (cleanPoints <= 0) {
                        return 0;
                    }

                    return cleanPoints * this.rate;
                },

                money(value) {
                    return Number(value || 0).toLocaleString(undefined, {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });
                },

                formatMoney(value) {
                    return `${this.currency} ${this.money(value)}`;
                },
            };
        }
    </script>
@endpush
