@extends('layouts.app')

@section('title', __('wallet.transaction_detail'))

@section('content')
    @php
        $currency = config('app.currency');
        $isCredit = $walletTransaction->direction === 'credit';

        $typeLabel = __('wallet.types.' . $walletTransaction->type) !== 'wallet.types.' . $walletTransaction->type
            ? __('wallet.types.' . $walletTransaction->type)
            : str($walletTransaction->type)->headline();

        $detailItems = [
            [
                'label' => __('wallet.wallet'),
                'value' => __('wallet.' . $walletTransaction->wallet_type . '_wallet'),
                'class' => 'bg-[#fff8c7]',
            ],
            [
                'label' => __('wallet.type'),
                'value' => $typeLabel,
                'class' => 'bg-[#fff8c7]',
            ],
            [
                'label' => __('wallet.before'),
                'value' => $currency . ' ' . number_format((float) $walletTransaction->balance_before, 2),
                'class' => 'bg-[#fff8c7]',
            ],
            [
                'label' => __('wallet.after'),
                'value' => $currency . ' ' . number_format((float) $walletTransaction->balance_after, 2),
                'class' => 'bg-[#ffd900]',
            ],
        ];
    @endphp

    <section class="mx-auto max-w-3xl px-3 py-4 sm:px-6 sm:py-8 lg:px-8">
        <div class="overflow-hidden rounded-[1.35rem] bg-white shadow-xl ring-1 ring-black/5 sm:rounded-[1.75rem]">
            <div class="bg-[#ffd900] p-4 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="text-[10px] font-black uppercase tracking-[0.18em] text-[#e4003a] sm:text-xs">
                            {{ __('wallet.transaction_detail') }}
                        </div>

                        <h1 class="mt-2 break-words text-3xl font-black leading-none sm:text-5xl {{ $isCredit ? 'text-green-700' : 'text-[#e4003a]' }}">
                            {{ $isCredit ? '+' : '-' }}
                            {{ $currency }} {{ number_format((float) $walletTransaction->amount, 2) }}
                        </h1>

                        <p class="mt-2 text-xs font-bold text-[#003b7a]/65 sm:text-sm">
                            {{ $walletTransaction->created_at->format('d M Y, h:i A') }}
                        </p>
                    </div>

                    <span
                        class="shrink-0 rounded-full px-3 py-1.5 text-[10px] font-black uppercase sm:text-xs
                        @class([
                            'bg-green-100 text-green-700' => $isCredit,
                            'bg-red-100 text-red-700' => ! $isCredit,
                        ])"
                    >
                        {{ __('wallet.' . $walletTransaction->direction) }}
                    </span>
                </div>
            </div>

            <div class="p-4 sm:p-6">
                <div class="grid grid-cols-2 gap-2 sm:gap-3">
                    @foreach ($detailItems as $item)
                        <div class="min-w-0 rounded-[1rem] p-3 sm:rounded-2xl sm:p-4 {{ $item['class'] }}">
                            <div class="text-[9px] font-black uppercase text-[#003b7a]/60 sm:text-xs">
                                {{ $item['label'] }}
                            </div>

                            <div class="mt-1 break-words text-sm font-black text-[#003b7a] sm:text-base">
                                {{ $item['value'] }}
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-3 rounded-[1rem] bg-[#fff8c7] p-3 sm:rounded-2xl sm:p-4">
                    <div class="text-[9px] font-black uppercase text-[#003b7a]/60 sm:text-xs">
                        {{ __('wallet.remarks') }}
                    </div>

                    <div class="mt-1 text-sm font-bold leading-relaxed text-[#003b7a] sm:text-base">
                        {{ $walletTransaction->remarks ?: '-' }}
                    </div>
                </div>

                @if ($walletTransaction->meta)
                    <details class="mt-3 rounded-[1rem] bg-[#fff8c7] p-3 sm:rounded-2xl sm:p-4">
                        <summary class="cursor-pointer select-none text-[10px] font-black uppercase text-[#003b7a]/70 sm:text-xs">
                            {{ __('wallet.extra_detail') }}
                        </summary>

                        <pre class="mt-3 max-h-64 overflow-auto rounded-xl bg-white p-3 text-xs font-bold text-[#003b7a]">{{ json_encode($walletTransaction->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </details>
                @endif

                <a
                    href="{{ route('wallet.index') }}"
                    class="mt-4 block rounded-xl bg-[#003b7a] px-5 py-3 text-center text-xs font-black uppercase text-white sm:rounded-2xl sm:py-4 sm:text-sm"
                >
                    {{ __('wallet.back_to_wallet') }}
                </a>
            </div>
        </div>
    </section>
@endsection