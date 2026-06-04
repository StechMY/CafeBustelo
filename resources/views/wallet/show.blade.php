@extends('layouts.app')

@section('title', __('wallet.transaction_detail'))

@section('content')
    <section class="mx-auto max-w-3xl px-3 py-6 sm:px-6 lg:px-8">
        <div class="rounded-[1.75rem] bg-white p-5 shadow-xl ring-1 ring-black/5 sm:p-7">
            <div class="mb-6 flex items-start justify-between gap-4">
                <div>
                    <div class="text-xs font-black uppercase text-[#e4003a]">
                        {{ __('wallet.transaction_detail') }}
                    </div>

                    <h1 class="mt-2 text-3xl font-black text-[#003b7a] sm:text-4xl">
                        {{ $walletTransaction->direction === 'credit' ? '+' : '-' }}
                        {{ config('app.currency') }} {{ number_format((float) $walletTransaction->amount, 2) }}
                    </h1>

                    <p class="mt-1 text-sm font-bold text-[#003b7a]/60">
                        {{ $walletTransaction->created_at->format('d M Y, h:i A') }}
                    </p>
                </div>

                <span class="rounded-full px-3 py-1 text-xs font-black uppercase
                    @class([
                        'bg-green-100 text-green-700' => $walletTransaction->direction === 'credit',
                        'bg-red-100 text-red-700' => $walletTransaction->direction === 'debit',
                    ])">
                    {{ __('wallet.' . $walletTransaction->direction) }}
                </span>
            </div>

            <div class="grid gap-3">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl bg-[#fff8c7] p-4">
                        <div class="text-xs font-black uppercase text-[#003b7a]/60">
                            {{ __('wallet.wallet') }}
                        </div>

                        <div class="mt-1 font-black text-[#003b7a]">
                            {{ __('wallet.' . $walletTransaction->wallet_type . '_wallet') }}
                        </div>
                    </div>

                    <div class="rounded-2xl bg-[#fff8c7] p-4">
                        <div class="text-xs font-black uppercase text-[#003b7a]/60">
                            {{ __('wallet.type') }}
                        </div>

                        <div class="mt-1 font-black text-[#003b7a]">
                            {{ __('wallet.types.' . $walletTransaction->type) !== 'wallet.types.' . $walletTransaction->type
                                ? __('wallet.types.' . $walletTransaction->type)
                                : str($walletTransaction->type)->headline() }}
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl bg-[#fff8c7] p-4">
                        <div class="text-xs font-black uppercase text-[#003b7a]/60">
                            {{ __('wallet.before') }}
                        </div>

                        <div class="mt-1 font-black text-[#003b7a]">
                            {{ config('app.currency') }} {{ number_format((float) $walletTransaction->balance_before, 2) }}
                        </div>
                    </div>

                    <div class="rounded-2xl bg-[#ffd900] p-4">
                        <div class="text-xs font-black uppercase text-[#003b7a]/60">
                            {{ __('wallet.after') }}
                        </div>

                        <div class="mt-1 font-black text-[#003b7a]">
                            {{ config('app.currency') }} {{ number_format((float) $walletTransaction->balance_after, 2) }}
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-[#fff8c7] p-4">
                    <div class="text-xs font-black uppercase text-[#003b7a]/60">
                        {{ __('wallet.remarks') }}
                    </div>

                    <div class="mt-1 font-bold text-[#003b7a]">
                        {{ $walletTransaction->remarks ?: '-' }}
                    </div>
                </div>

                @if ($walletTransaction->meta)
                    <div class="rounded-2xl bg-[#fff8c7] p-4">
                        <div class="text-xs font-black uppercase text-[#003b7a]/60">
                            {{ __('wallet.extra_detail') }}
                        </div>

                        <pre class="mt-2 overflow-x-auto rounded-xl bg-white p-3 text-xs font-bold text-[#003b7a]">{{ json_encode($walletTransaction->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                @endif

                <a href="{{ route('wallet.index') }}"
                   class="rounded-2xl bg-[#003b7a] px-5 py-4 text-center text-sm font-black uppercase text-white">
                    {{ __('wallet.back_to_wallet') }}
                </a>
            </div>
        </div>
    </section>
@endsection