@extends('layouts.app')

@section('title', __('recharge.submit_recharge'))

@section('content')
    @php
        $currency = config('app.currency', 'RM');
    @endphp

    <section class="mx-auto max-w-4xl px-3 py-4 sm:px-6 sm:py-8 lg:px-8">
        <div class="rounded-[1.35rem] border-4 border-[#003b7a] bg-white p-4 shadow-[6px_6px_0_#003b7a] sm:rounded-[1.75rem] sm:p-7 sm:shadow-[8px_8px_0_#003b7a]">
            <div class="mb-4 sm:mb-6">
                <div class="mb-2 inline-flex rounded-full bg-[#ffd900] px-3 py-1.5 text-[10px] font-black uppercase text-[#003b7a] sm:mb-3 sm:px-4 sm:py-2 sm:text-xs">
                    {{ __('recharge.recharge_center') }}
                </div>

                <h1 class="text-2xl font-black uppercase leading-none text-[#003b7a] sm:text-4xl">
                    {{ __('recharge.submit_recharge') }}
                </h1>

                <p class="mt-2 line-clamp-2 text-xs font-bold text-[#003b7a]/70 sm:text-sm sm:line-clamp-none">
                    {{ __('recharge.submit_desc') }}
                </p>
            </div>

            @if ($rechargeMethods->isEmpty())
                <div class="rounded-xl bg-red-50 p-4 text-sm font-black text-red-700">
                    {{ __('recharge.no_recharge_method_available') }}
                </div>

                <a
                    href="{{ route('recharge.index') }}"
                    class="mt-4 inline-flex rounded-xl bg-[#003b7a] px-5 py-3 text-xs font-black uppercase text-white"
                >
                    {{ __('recharge.back') }}
                </a>
            @else
                <form
                    method="POST"
                    action="{{ route('recharge.store') }}"
                    enctype="multipart/form-data"
                    class="space-y-3 sm:space-y-4"
                    x-data="{
                        selectedMethodId: @js((string) old('recharge_method_id', $rechargeMethods->first()?->id)),
                        methods: @js($rechargeMethods->mapWithKeys(fn ($method) => [
                            (string) $method->id => [
                                'id' => $method->id,
                                'name' => $method->name,
                                'bank_name' => $method->bank_name,
                                'account_name' => $method->account_name,
                                'account_no' => $method->account_no,
                                'qr_image_url' => $method->qr_image_url,
                                'instructions' => $method->instructions,
                            ],
                        ])),
                        copied: null,
                        get selectedMethod() {
                            return this.methods[this.selectedMethodId] || null;
                        },
                        copy(text, key) {
                            if (! text) return;

                            const done = () => {
                                this.copied = key;
                                setTimeout(() => this.copied = null, 1500);
                            };

                            if (navigator.clipboard && window.isSecureContext) {
                                navigator.clipboard.writeText(text).then(done);
                                return;
                            }

                            const input = document.createElement('textarea');
                            input.value = text;
                            input.style.position = 'fixed';
                            input.style.opacity = '0';
                            document.body.appendChild(input);
                            input.select();
                            document.execCommand('copy');
                            document.body.removeChild(input);
                            done();
                        }
                    }"
                >
                    @csrf

                    <div>
                        <label class="mb-1.5 block text-xs font-black uppercase text-[#003b7a] sm:mb-2 sm:text-sm">
                            {{ __('recharge.amount') }}
                        </label>

                        <input
                            name="amount"
                            value="{{ old('amount') }}"
                            type="number"
                            step="0.01"
                            min="1"
                            class="w-full rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3 text-sm font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:rounded-2xl sm:px-5 sm:py-4 sm:text-base"
                            placeholder="100.00"
                            required
                        >

                        @error('amount')
                            <div class="mt-1.5 text-xs font-bold text-[#e4003a] sm:text-sm">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-black uppercase text-[#003b7a] sm:mb-2 sm:text-sm">
                            {{ __('recharge.select_recharge_method') }}
                        </label>

                        <select
                            name="recharge_method_id"
                            x-model="selectedMethodId"
                            class="w-full rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3 text-sm font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:rounded-2xl sm:px-5 sm:py-4 sm:text-base"
                            required
                        >
                            @foreach ($rechargeMethods as $method)
                                <option value="{{ $method->id }}" @selected((string) old('recharge_method_id') === (string) $method->id)>
                                    {{ $method->name }}
                                    @if ($method->bank_name)
                                        - {{ $method->bank_name }}
                                    @endif
                                </option>
                            @endforeach
                        </select>

                        @error('recharge_method_id')
                            <div class="mt-1.5 text-xs font-bold text-[#e4003a] sm:text-sm">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="grid gap-3 lg:grid-cols-[280px_1fr]">
                        <div class="rounded-[1.25rem] bg-[#003b7a] p-3 text-white sm:rounded-[1.5rem] sm:p-4">
                            <div class="text-[10px] font-black uppercase tracking-wide text-[#ffd900]">
                                {{ __('recharge.scan_qr_to_pay') }}
                            </div>

                            <template x-if="selectedMethod && selectedMethod.qr_image_url">
                                <a
                                    :href="selectedMethod.qr_image_url"
                                    target="_blank"
                                    class="mt-3 block overflow-hidden rounded-2xl bg-white p-2"
                                >
                                    <img
                                        :src="selectedMethod.qr_image_url"
                                        :alt="selectedMethod.name"
                                        class="mx-auto max-h-[260px] w-full object-contain"
                                    >
                                </a>
                            </template>

                            <template x-if="! selectedMethod || ! selectedMethod.qr_image_url">
                                <div class="mt-3 rounded-2xl bg-white/10 p-6 text-center text-xs font-bold text-white/70">
                                    {{ __('recharge.no_qr_image') }}
                                </div>
                            </template>
                        </div>

                        <div class="rounded-[1.25rem] bg-[#fff8c7] p-3 ring-1 ring-[#003b7a]/10 sm:rounded-[1.5rem] sm:p-4">
                            <div class="text-[10px] font-black uppercase tracking-wide text-[#003b7a]/60">
                                {{ __('recharge.payment_information') }}
                            </div>

                            <div class="mt-3 grid gap-2">
                                <div class="rounded-xl bg-white p-3">
                                    <div class="text-[9px] font-black uppercase text-[#003b7a]/55">
                                        {{ __('recharge.bank_name') }}
                                    </div>

                                    <div class="mt-1 flex items-center justify-between gap-3">
                                        <div class="font-black text-[#003b7a]" x-text="selectedMethod?.bank_name || '-'"></div>

                                        <button
                                            type="button"
                                            class="rounded-full bg-[#003b7a] px-3 py-1 text-[10px] font-black uppercase text-white"
                                            @click="copy(selectedMethod?.bank_name, 'bank')"
                                            x-text="copied === 'bank' ? @js(__('app.copied')) : @js(__('app.copy'))"
                                        ></button>
                                    </div>
                                </div>

                                <div class="rounded-xl bg-white p-3">
                                    <div class="text-[9px] font-black uppercase text-[#003b7a]/55">
                                        {{ __('recharge.account_name') }}
                                    </div>

                                    <div class="mt-1 flex items-center justify-between gap-3">
                                        <div class="font-black text-[#003b7a]" x-text="selectedMethod?.account_name || '-'"></div>

                                        <button
                                            type="button"
                                            class="rounded-full bg-[#003b7a] px-3 py-1 text-[10px] font-black uppercase text-white"
                                            @click="copy(selectedMethod?.account_name, 'name')"
                                            x-text="copied === 'name' ? @js(__('app.copied')) : @js(__('app.copy'))"
                                        ></button>
                                    </div>
                                </div>

                                <div class="rounded-xl bg-white p-3">
                                    <div class="text-[9px] font-black uppercase text-[#003b7a]/55">
                                        {{ __('recharge.account_no') }}
                                    </div>

                                    <div class="mt-1 flex items-center justify-between gap-3">
                                        <div class="break-all font-black text-[#003b7a]" x-text="selectedMethod?.account_no || '-'"></div>

                                        <button
                                            type="button"
                                            class="shrink-0 rounded-full bg-[#e4003a] px-3 py-1 text-[10px] font-black uppercase text-white"
                                            @click="copy(selectedMethod?.account_no, 'no')"
                                            x-text="copied === 'no' ? @js(__('app.copied')) : @js(__('app.copy'))"
                                        ></button>
                                    </div>
                                </div>

                                <template x-if="selectedMethod && selectedMethod.instructions">
                                    <div class="rounded-xl bg-[#ffd900] p-3 text-xs font-bold leading-relaxed text-[#003b7a]" x-text="selectedMethod.instructions"></div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-black uppercase text-[#003b7a] sm:mb-2 sm:text-sm">
                            {{ __('recharge.reference_no') }}
                        </label>

                        <input
                            name="reference_no"
                            value="{{ old('reference_no') }}"
                            type="text"
                            class="w-full rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3 text-sm font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:rounded-2xl sm:px-5 sm:py-4 sm:text-base"
                            placeholder="{{ __('recharge.reference_no_placeholder') }}"
                        >

                        @error('reference_no')
                            <div class="mt-1.5 text-xs font-bold text-[#e4003a] sm:text-sm">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-black uppercase text-[#003b7a] sm:mb-2 sm:text-sm">
                            {{ __('recharge.receipt') }}
                        </label>

                        <input
                            name="receipt"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="w-full rounded-xl border-2 border-dashed border-[#003b7a] bg-[#fff8c7] px-4 py-3 text-xs font-bold text-[#003b7a] file:mr-3 file:rounded-full file:border-0 file:bg-[#003b7a] file:px-3 file:py-1.5 file:text-xs file:font-black file:text-white sm:rounded-2xl sm:px-5 sm:py-4 sm:text-sm sm:file:mr-4 sm:file:px-4 sm:file:py-2 sm:file:text-sm"
                            required
                        >

                        <p class="mt-1.5 text-[10px] font-bold text-[#003b7a]/60 sm:mt-2 sm:text-xs">
                            {{ __('recharge.receipt_help') }}
                        </p>

                        @error('receipt')
                            <div class="mt-1.5 text-xs font-bold text-[#e4003a] sm:text-sm">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="rounded-xl bg-[#ffd900] p-3 text-xs font-bold leading-relaxed text-[#003b7a] sm:rounded-2xl sm:p-4 sm:text-sm">
                        {{ __('recharge.warning_desc') }}
                    </div>

                    <div class="grid grid-cols-2 gap-2 sm:gap-3">
                        <a
                            href="{{ route('recharge.index') }}"
                            class="rounded-xl bg-gray-100 px-4 py-3 text-center text-xs font-black uppercase text-gray-700 sm:rounded-2xl sm:px-5 sm:py-4 sm:text-sm"
                        >
                            {{ __('recharge.back') }}
                        </a>

                        <button
                            type="submit"
                            class="rounded-xl bg-[#e4003a] px-4 py-3 text-xs font-black uppercase text-white shadow-[0_4px_0_#9e0028] sm:rounded-2xl sm:px-5 sm:py-4 sm:text-sm sm:shadow-[0_6px_0_#9e0028]"
                        >
                            {{ __('recharge.submit') }}
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </section>
@endsection