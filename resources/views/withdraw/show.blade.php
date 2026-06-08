@extends('layouts.app')

@section('title', __('withdraw.withdraw_detail'))

@section('content')
    <section class="mx-auto max-w-3xl px-3 py-6 sm:px-6 lg:px-8">
        <div class="rounded-[1.75rem] bg-white p-5 shadow-xl ring-1 ring-black/5 sm:p-7">
            <div class="mb-6 flex items-start justify-between gap-4">
                <div>
                    <div class="text-xs font-black uppercase text-[#e4003a]">
                        {{ __('withdraw.withdraw_detail') }}
                    </div>

                    <h1 class="mt-2 text-4xl font-black text-[#003b7a]">
                        {{ config('app.currency') }} {{ number_format((float) $withdrawRequest->amount, 2) }}
                    </h1>

                    <p class="mt-1 text-sm font-bold text-[#003b7a]/60">
                        {{ $withdrawRequest->created_at->format('d M Y, h:i A') }}
                    </p>
                </div>

                <span
                      class="rounded-full px-3 py-1 text-xs font-black uppercase
                    @class([
                        'bg-yellow-100 text-yellow-700' => $withdrawRequest->status === 'pending',
                        'bg-green-100 text-green-700' => $withdrawRequest->status === 'approved',
                        'bg-red-100 text-red-700' => $withdrawRequest->status === 'rejected',
                    ])">
                    {{ __('status.' . $withdrawRequest->status) }}
                </span>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl bg-[#fff8c7] p-4">
                    <div class="text-xs font-black uppercase text-[#003b7a]/60">
                        {{ __('withdraw.amount') }}
                    </div>
                    <div class="mt-1 font-black text-[#003b7a]">
                        {{ config('app.currency') }} {{ number_format((float) $withdrawRequest->amount, 2) }}
                    </div>
                </div>

                <div class="rounded-2xl bg-[#fff8c7] p-4">
                    <div class="text-xs font-black uppercase text-[#003b7a]/60">
                        {{ __('withdraw.fee_amount') }}
                    </div>
                    <div class="mt-1 font-black text-[#e4003a]">
                        {{ config('app.currency') }} {{ number_format((float) $withdrawRequest->fee_amount, 2) }}
                    </div>
                </div>

                <div class="rounded-2xl bg-[#ffd900] p-4">
                    <div class="text-xs font-black uppercase text-[#003b7a]/60">
                        {{ __('withdraw.net_amount') }}
                    </div>
                    <div class="mt-1 font-black text-[#003b7a]">
                        {{ config('app.currency') }} {{ number_format((float) $withdrawRequest->net_amount, 2) }}
                    </div>
                </div>
            </div>

            <div class="grid gap-3 mt-3">
                <div class="rounded-2xl bg-[#fff8c7] p-4">
                    <div class="text-xs font-black uppercase text-[#003b7a]/60">
                        {{ __('withdraw.vip_snapshot') }}
                    </div>
                    <div class="mt-1 font-black text-[#003b7a]">
                        LV{{ $withdrawRequest->vip_level_snapshot }}
                        · {{ __('withdraw.min_withdraw_amount') }}:
                        {{ config('app.currency') }}
                        {{ number_format((float) $withdrawRequest->min_withdraw_amount_snapshot, 2) }}
                    </div>
                </div>
                <div class="rounded-2xl bg-[#fff8c7] p-4">
                    <div class="text-xs font-black uppercase text-[#003b7a]/60">
                        {{ __('withdraw.bank_name') }}
                    </div>
                    <div class="mt-1 font-black text-[#003b7a]">
                        {{ $withdrawRequest->bank_name }}
                    </div>
                </div>

                <div class="rounded-2xl bg-[#fff8c7] p-4">
                    <div class="text-xs font-black uppercase text-[#003b7a]/60">
                        {{ __('withdraw.bank_account_name') }}
                    </div>
                    <div class="mt-1 font-black text-[#003b7a]">
                        {{ $withdrawRequest->bank_account_name }}
                    </div>
                </div>

                <div class="rounded-2xl bg-[#fff8c7] p-4">
                    <div class="text-xs font-black uppercase text-[#003b7a]/60">
                        {{ __('withdraw.bank_account_no') }}
                    </div>
                    <div class="mt-1 font-black text-[#003b7a]">
                        {{ $withdrawRequest->bank_account_no }}
                    </div>
                </div>

                @if ($withdrawRequest->admin_remark)
                    <div class="rounded-2xl bg-red-50 p-4">
                        <div class="text-xs font-black uppercase text-red-500">
                            {{ __('withdraw.admin_remark') }}
                        </div>
                        <div class="mt-1 font-bold text-red-700">
                            {{ $withdrawRequest->admin_remark }}
                        </div>
                    </div>
                @endif

                <a href="{{ route('withdraw.index') }}"
                   class="rounded-2xl bg-[#ffd900] px-5 py-4 text-center text-sm font-black uppercase text-[#003b7a]">
                    {{ __('withdraw.back_to_withdraw') }}
                </a>
            </div>
        </div>
    </section>
@endsection
