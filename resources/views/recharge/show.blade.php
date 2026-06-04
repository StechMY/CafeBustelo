@extends('layouts.app')

@section('title', __('recharge.recharge_detail'))

@section('content')
    <section class="mx-auto max-w-3xl px-3 py-6 sm:px-6 lg:px-8">
        <div class="rounded-[1.75rem] bg-white p-5 shadow-xl ring-1 ring-black/5 sm:p-7">
            <div class="mb-6 flex items-start justify-between gap-4">
                <div>
                    <div class="text-xs font-black uppercase text-[#e4003a]">
                        {{ __('recharge.recharge_detail') }}
                    </div>

                    <h1 class="mt-2 text-4xl font-black text-[#003b7a]">
                        {{ config('app.currency') }} {{ number_format((float) $rechargeRequest->amount, 2) }}
                    </h1>

                    <p class="mt-1 text-sm font-bold text-[#003b7a]/60">
                        {{ $rechargeRequest->submitted_at?->format('d M Y, h:i A') ?? $rechargeRequest->created_at->format('d M Y, h:i A') }}
                    </p>
                </div>

                <span class="rounded-full px-3 py-1 text-xs font-black uppercase
                    @class([
                        'bg-yellow-100 text-yellow-700' => $rechargeRequest->status === 'pending',
                        'bg-green-100 text-green-700' => $rechargeRequest->status === 'approved',
                        'bg-red-100 text-red-700' => $rechargeRequest->status === 'rejected',
                    ])">
                    {{ __('status.' . $rechargeRequest->status) }}
                </span>
            </div>

            <div class="grid gap-3">
                <div class="rounded-2xl bg-[#fff8c7] p-4">
                    <div class="text-xs font-black uppercase text-[#003b7a]/60">
                        {{ __('recharge.bank_name') }}
                    </div>
                    <div class="mt-1 font-black text-[#003b7a]">
                        {{ $rechargeRequest->bank_name ?: '-' }}
                    </div>
                </div>

                <div class="rounded-2xl bg-[#fff8c7] p-4">
                    <div class="text-xs font-black uppercase text-[#003b7a]/60">
                        {{ __('recharge.reference_no') }}
                    </div>
                    <div class="mt-1 font-black text-[#003b7a]">
                        {{ $rechargeRequest->reference_no ?: '-' }}
                    </div>
                </div>

                @if ($rechargeRequest->admin_remark)
                    <div class="rounded-2xl bg-red-50 p-4">
                        <div class="text-xs font-black uppercase text-red-500">
                            {{ __('recharge.admin_remark') }}
                        </div>
                        <div class="mt-1 font-bold text-red-700">
                            {{ $rechargeRequest->admin_remark }}
                        </div>
                    </div>
                @endif

                @if ($rechargeRequest->receipt_url)
                    <a href="{{ $rechargeRequest->receipt_url }}"
                       target="_blank"
                       class="rounded-2xl bg-[#003b7a] px-5 py-4 text-center text-sm font-black uppercase text-white">
                        {{ __('recharge.view_receipt') }}
                    </a>
                @endif

                <a href="{{ route('recharge.index') }}"
                   class="rounded-2xl bg-[#ffd900] px-5 py-4 text-center text-sm font-black uppercase text-[#003b7a]">
                    {{ __('recharge.back_to_recharge') }}
                </a>
            </div>
        </div>
    </section>
@endsection