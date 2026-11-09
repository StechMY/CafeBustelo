@extends('layouts.app')

@section('title', __('recharge.payment_processing'))

@section('content')
    @php
        $failedStatuses = ['failed', 'reject', 'overdue', 'create_failed', 'closed'];
        $isSuccessful = $rechargeRequest->isPaymentConfirmed();
        $requiresReview = $rechargeRequest->requiresGatewayReview();
        $isExpired = $rechargeRequest->isGatewayExpired();
        $isFailed = ! $isSuccessful
            && in_array($rechargeRequest->gateway_payment_status, $failedStatuses, true);
    @endphp

    <section class="mx-auto max-w-2xl px-3 py-8 sm:px-6 sm:py-14">
        <div
            class="rounded-[1.75rem] border-4 border-[#003b7a] bg-white p-6 text-center shadow-[8px_8px_0_#003b7a] sm:p-9"
            x-data="{
                successful: @js($isSuccessful),
                failed: @js($isFailed),
                requiresReview: @js($requiresReview),
                expired: @js($isExpired),
                gatewayStatus: @js($rechargeRequest->gatewayStatusLabel()),
                attempts: 0,
                poll() {
                    if (this.successful || this.failed || this.expired || this.requiresReview || this.attempts >= 60) return;
                    this.attempts++;
                    fetch(@js(route('recharge.gateway.status', $rechargeRequest)), { headers: { 'Accept': 'application/json' } })
                        .then(response => response.ok ? response.json() : Promise.reject())
                        .then(data => {
                            this.gatewayStatus = data.gateway_status_label;
                            this.successful = data.payment_confirmed;
                            this.requiresReview = data.requires_review;
                            this.expired = data.gateway_status === 'expired';
                            this.failed = ! data.payment_confirmed && ['failed', 'reject', 'overdue', 'create_failed', 'closed'].includes(data.gateway_status);
                            if (! this.successful && ! this.failed && ! this.expired && ! this.requiresReview) setTimeout(() => this.poll(), 5000);
                        })
                        .catch(() => setTimeout(() => this.poll(), 5000));
                }
            }"
            x-init="setTimeout(() => poll(), 3000)"
        >
            <div x-show="successful" x-cloak>
                <div class="text-3xl font-black uppercase text-green-600">{{ __('recharge.payment_successful') }}</div>
                <p class="mt-3 font-bold text-[#003b7a]/70">{{ __('recharge.payment_successful_desc') }}</p>
            </div>
            <div x-show="failed" x-cloak>
                <div class="text-3xl font-black uppercase text-[#e4003a]">{{ __('recharge.payment_failed') }}</div>
                <p class="mt-3 font-bold text-[#003b7a]/70">{{ __('recharge.payment_failed_desc') }}</p>
            </div>
            <div x-show="requiresReview" x-cloak>
                <div class="text-3xl font-black uppercase text-[#e4003a]">{{ __('ydpay.review_title') }}</div>
                <p class="mt-3 font-bold text-[#003b7a]/70">{{ __('ydpay.review_help') }}</p>
            </div>
            <div x-show="expired" x-cloak>
                <div class="text-3xl font-black uppercase text-[#003b7a]">{{ __('ydpay.deposit_statuses.expired') }}</div>
                <p class="mt-3 font-bold text-[#003b7a]/70">{{ __('ydpay.deposit_expired_help') }}</p>
            </div>
            <div x-show="! successful && ! failed && ! expired && ! requiresReview">
                <div class="text-3xl font-black uppercase text-[#003b7a]">{{ __('recharge.payment_processing') }}</div>
                <p class="mt-3 font-bold text-[#003b7a]/70">{{ __('recharge.waiting_for_payment_confirmation') }}</p>
            </div>

            <div class="mt-6 grid gap-3 rounded-2xl bg-[#fff8c7] p-4 text-left sm:grid-cols-2">
                <div>
                    <div class="text-[10px] font-black uppercase text-[#003b7a]/55">{{ __('recharge.merchant_order_id') }}</div>
                    <div class="mt-1 break-all text-sm font-black text-[#003b7a]">{{ $rechargeRequest->merchant_order_id }}</div>
                </div>
                <div>
                    <div class="text-[10px] font-black uppercase text-[#003b7a]/55">{{ __('recharge.amount') }}</div>
                    <div class="mt-1 text-sm font-black text-[#003b7a]">{{ config('app.currency') }} {{ number_format((float) $rechargeRequest->amount, 2) }}</div>
                </div>
                <div>
                    <div class="text-[10px] font-black uppercase text-[#003b7a]/55">{{ __('recharge.gateway_status') }}</div>
                    <div class="mt-1 text-sm font-black uppercase text-[#003b7a]" x-text="gatewayStatus || 'pending'"></div>
                </div>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                <a href="{{ route('recharge.index') }}" class="rounded-2xl bg-[#ffd900] px-5 py-4 text-sm font-black uppercase text-[#003b7a]">{{ __('recharge.back_to_recharge') }}</a>
                <a href="{{ route('dashboard') }}" class="rounded-2xl bg-[#003b7a] px-5 py-4 text-sm font-black uppercase text-white">{{ __('recharge.back_to_dashboard') }}</a>
            </div>
        </div>
    </section>
@endsection
