@extends('layouts.app')

@section('title', __('recharge.submit_recharge'))

@section('content')
    <section class="mx-auto max-w-3xl px-3 py-4 sm:px-6 sm:py-8 lg:px-8">
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

            <form method="POST" action="{{ route('recharge.store') }}" enctype="multipart/form-data" class="space-y-3 sm:space-y-4">
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

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-black uppercase text-[#003b7a] sm:mb-2 sm:text-sm">
                            {{ __('recharge.bank_name') }}
                        </label>

                        <input
                            name="bank_name"
                            value="{{ old('bank_name') }}"
                            type="text"
                            class="w-full rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3 text-sm font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:rounded-2xl sm:px-5 sm:py-4 sm:text-base"
                            placeholder="{{ __('recharge.bank_name_placeholder') }}"
                        >

                        @error('bank_name')
                            <div class="mt-1.5 text-xs font-bold text-[#e4003a] sm:text-sm">{{ $message }}</div>
                        @enderror
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
        </div>
    </section>
@endsection