@extends('layouts.app')

@section('title', __('recharge.submit_recharge'))

@section('content')
    @php
        $manualAvailable = $rechargeMethods->isNotEmpty();
        $requestedDepositMethod = old('deposit_method');
        $defaultDepositMethod = match (true) {
            $requestedDepositMethod === 'gateway' && ($gatewayAvailable || $ydpayAvailable) => 'gateway',
            $manualAvailable => 'manual',
            default => 'gateway',
        };
    @endphp

    <section class="mx-auto max-w-4xl px-3 py-4 sm:px-6 sm:py-8 lg:px-8">
        <div class="rounded-[1.35rem] border-4 border-[#003b7a] bg-white p-4 shadow-[6px_6px_0_#003b7a] sm:rounded-[1.75rem] sm:p-7 sm:shadow-[8px_8px_0_#003b7a]">
            <div class="mb-4 sm:mb-6">
                <div class="mb-2 inline-flex rounded-full bg-[#ffd900] px-3 py-1.5 text-[10px] font-black uppercase text-[#003b7a] sm:mb-3 sm:px-4 sm:py-2 sm:text-xs">
                    {{ __('recharge.recharge_center') }}
                </div>
                <h1 class="text-2xl font-black uppercase leading-none text-[#003b7a] sm:text-4xl">{{ __('recharge.submit_recharge') }}</h1>
                <p class="mt-2 text-xs font-bold text-[#003b7a]/70 sm:text-sm">{{ __('recharge.submit_desc') }}</p>
            </div>

            @if (! $manualAvailable && ! $gatewayAvailable && ! $ydpayAvailable)
                <div class="rounded-xl bg-red-50 p-4 text-sm font-black text-red-700">{{ __('recharge.no_recharge_method_available') }}</div>
            @else
                <form
                    method="POST"
                    enctype="multipart/form-data"
                    class="space-y-4"
                    x-data="{
                        depositMethod: @js($defaultDepositMethod),
                        gatewayProvider: @js(old('provider', $gatewayAvailable ? 'qc168' : 'ydpay')),
                        selectedMethodId: @js((string) old('recharge_method_id', $rechargeMethods->first()?->id)),
                        submitting: false,
                        copied: null,
                        methods: @js($rechargeMethods->mapWithKeys(fn ($method) => [
                            (string) $method->id => [
                                'name' => $method->name,
                                'bank_name' => $method->bank_name,
                                'account_name' => $method->account_name,
                                'account_no' => $method->account_no,
                                'qr_image_url' => $method->qr_image_url,
                                'instructions' => $method->instructions,
                            ],
                        ])),
                        get selectedMethod() { return this.methods[this.selectedMethodId] || null },
                        copy(text, key) {
                            if (! text) return;
                            navigator.clipboard?.writeText(text).then(() => {
                                this.copied = key;
                                setTimeout(() => this.copied = null, 1500);
                            });
                        }
                    }"
                    x-bind:action="depositMethod === 'gateway' ? @js(route('recharge.gateway.store')) : @js(route('recharge.store'))"
                    x-on:submit="submitting = true"
                >
                    @csrf
                    <input type="hidden" name="deposit_method" x-bind:value="depositMethod">
                    <input type="hidden" name="provider" x-bind:value="gatewayProvider">

                    <div>
                        <div class="mb-2 text-xs font-black uppercase text-[#003b7a] sm:text-sm">{{ __('recharge.deposit_method') }}</div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @if ($manualAvailable)
                                <button type="button" class="rounded-2xl border-2 p-4 text-left transition" x-bind:class="depositMethod === 'manual' ? 'border-[#003b7a] bg-[#ffd900]' : 'border-[#003b7a]/20 bg-white'" x-on:click="depositMethod = 'manual'">
                                    <span class="block font-black text-[#003b7a]">{{ __('recharge.manual_deposit') }}</span>
                                    <span class="mt-1 block text-xs font-bold text-[#003b7a]/65">{{ __('recharge.manual_deposit_desc') }}</span>
                                </button>
                            @endif
                            @if ($gatewayAvailable || $ydpayAvailable)
                                <button type="button" class="rounded-2xl border-2 p-4 text-left transition" x-bind:class="depositMethod === 'gateway' ? 'border-[#003b7a] bg-[#ffd900]' : 'border-[#003b7a]/20 bg-white'" x-on:click="depositMethod = 'gateway'">
                                    <span class="block font-black text-[#003b7a]">{{ __('recharge.payment_gateway') }}</span>
                                    <span class="mt-1 block text-xs font-bold text-[#003b7a]/65">{{ __('recharge.payment_gateway_desc') }}</span>
                                </button>
                            @endif
                        </div>
                    </div>

                    @error('gateway')
                        <div class="rounded-xl bg-red-50 p-3 text-sm font-bold text-red-700">{{ $message }}</div>
                    @enderror

                    <div>
                        <label class="mb-2 block text-xs font-black uppercase text-[#003b7a] sm:text-sm">{{ __('recharge.amount') }}</label>
                        <input name="amount" value="{{ old('amount') }}" type="number" step="0.01" min="1" max="99999999" class="w-full rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3 text-sm font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:rounded-2xl sm:px-5 sm:py-4 sm:text-base" placeholder="450000.00" required>
                        @error('amount')
                            <div class="mt-1.5 text-xs font-bold text-[#e4003a] sm:text-sm">{{ $message }}</div>
                        @enderror
                    </div>

                    <div x-show="depositMethod === 'manual'" x-cloak class="space-y-4">
                        <div>
                            <label class="mb-2 block text-xs font-black uppercase text-[#003b7a] sm:text-sm">{{ __('recharge.select_recharge_method') }}</label>
                            <select name="recharge_method_id" x-model="selectedMethodId" x-bind:disabled="depositMethod !== 'manual'" x-bind:required="depositMethod === 'manual'" class="w-full rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3 text-sm font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:rounded-2xl sm:px-5 sm:py-4 sm:text-base">
                                @foreach ($rechargeMethods as $method)
                                    <option value="{{ $method->id }}">{{ $method->name }}{{ $method->bank_name ? ' - '.$method->bank_name : '' }}</option>
                                @endforeach
                            </select>
                            @error('recharge_method_id')
                                <div class="mt-1.5 text-xs font-bold text-[#e4003a] sm:text-sm">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="grid gap-3 lg:grid-cols-[280px_1fr]">
                            <div class="rounded-[1.25rem] bg-[#003b7a] p-3 text-white sm:p-4">
                                <div class="text-[10px] font-black uppercase tracking-wide text-[#ffd900]">{{ __('recharge.scan_qr_to_pay') }}</div>
                                <template x-if="selectedMethod?.qr_image_url">
                                    <a x-bind:href="selectedMethod.qr_image_url" target="_blank" class="mt-3 block overflow-hidden rounded-2xl bg-white p-2">
                                        <img x-bind:src="selectedMethod.qr_image_url" x-bind:alt="selectedMethod.name" class="mx-auto max-h-[260px] w-full object-contain">
                                    </a>
                                </template>
                                <template x-if="! selectedMethod?.qr_image_url">
                                    <div class="mt-3 rounded-2xl bg-white/10 p-6 text-center text-xs font-bold text-white/70">{{ __('recharge.no_qr_image') }}</div>
                                </template>
                            </div>

                            <div class="rounded-[1.25rem] bg-[#fff8c7] p-3 sm:p-4">
                                <div class="text-[10px] font-black uppercase tracking-wide text-[#003b7a]/60">{{ __('recharge.payment_information') }}</div>
                                @foreach ([['bank_name', __('recharge.bank_name')], ['account_name', __('recharge.account_name')], ['account_no', __('recharge.account_no')]] as [$key, $label])
                                    <div class="mt-3 rounded-xl bg-white p-3">
                                        <div class="text-[9px] font-black uppercase text-[#003b7a]/55">{{ $label }}</div>
                                        <div class="mt-1 flex items-center justify-between gap-3">
                                            <div class="break-all font-black text-[#003b7a]" x-text="selectedMethod?.{{ $key }} || '-'"></div>
                                            <button type="button" class="rounded-full bg-[#003b7a] px-3 py-1 text-[10px] font-black uppercase text-white" x-on:click="copy(selectedMethod?.{{ $key }}, @js($key))">
                                                <span x-text="copied === @js($key) ? @js(__('app.copied')) : @js(__('app.copy'))"></span>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                                <template x-if="selectedMethod?.instructions">
                                    <div class="mt-3 rounded-xl bg-[#ffd900] p-3 text-xs font-bold leading-relaxed text-[#003b7a]" x-text="selectedMethod.instructions"></div>
                                </template>
                            </div>
                        </div>

                        <div>
                            <label for="receipt" class="mb-2 block text-xs font-black text-[#003b7a] sm:text-sm">{{ __('recharge.receipt') }}</label>
                            <div class="flex items-center gap-3 rounded-xl border-2 border-dashed border-[#003b7a] bg-[#fff8c7] px-4 py-3 sm:rounded-2xl sm:px-5 sm:py-4">
                                <input id="receipt" name="receipt" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" x-bind:disabled="depositMethod !== 'manual'" x-bind:required="depositMethod === 'manual'">
                                <label for="receipt" class="shrink-0 cursor-pointer rounded-full bg-[#003b7a] px-3 py-1.5 text-xs font-black text-white sm:px-4 sm:py-2 sm:text-sm">{{ __('recharge.choose_file') }}</label>
                                <span id="receipt-file-name" class="min-w-0 truncate text-xs font-bold text-[#003b7a]/70 sm:text-sm">{{ __('recharge.no_file_selected') }}</span>
                            </div>
                            <p class="mt-1.5 text-[10px] font-bold text-[#003b7a]/60 sm:text-xs">{{ __('recharge.receipt_help') }}</p>
                            @error('receipt')
                                <div class="mt-1.5 text-xs font-bold text-[#e4003a] sm:text-sm">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="rounded-xl bg-[#ffd900] p-3 text-xs font-bold leading-relaxed text-[#003b7a] sm:p-4 sm:text-sm">{{ __('recharge.warning_desc') }}</div>
                    </div>

                    <div x-show="depositMethod === 'gateway'" x-cloak class="rounded-2xl bg-[#fff8c7] p-4 text-sm font-bold text-[#003b7a]">
                        <select name="gateway_choice" x-model="gatewayProvider" class="rounded border p-2">
                            @if ($gatewayAvailable)<option value="qc168">QC168</option>@endif
                            @if ($ydpayAvailable)<option value="ydpay">YDPAY</option>@endif
                        </select>
                        <p class="mt-1">{{ __('recharge.payment_gateway_desc') }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <a href="{{ route('recharge.index') }}" class="rounded-xl bg-gray-100 px-4 py-3 text-center text-xs font-black uppercase text-gray-700 sm:rounded-2xl sm:px-5 sm:py-4 sm:text-sm">{{ __('recharge.back') }}</a>
                        <button type="submit" x-bind:disabled="submitting" class="rounded-xl bg-[#e4003a] px-4 py-3 text-xs font-black uppercase text-white shadow-[0_4px_0_#9e0028] disabled:cursor-wait disabled:opacity-60 sm:rounded-2xl sm:px-5 sm:py-4 sm:text-sm">
                            <span x-show="! submitting" x-text="depositMethod === 'gateway' ? @js(__('recharge.continue_to_payment')) : @js(__('recharge.submit'))"></span>
                            <span x-show="submitting" x-cloak>{{ __('recharge.payment_processing') }}</span>
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const input = document.getElementById('receipt');
            const fileName = document.getElementById('receipt-file-name');
            if (! input || ! fileName) return;
            input.addEventListener('change', () => {
                fileName.textContent = input.files?.[0]?.name || @json(__('recharge.no_file_selected'));
            });
        });
    </script>
@endsection
