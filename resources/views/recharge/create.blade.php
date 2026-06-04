@extends('layouts.app')

@section('title', __('recharge.submit_recharge'))

@section('content')
    <section class="mx-auto max-w-3xl px-3 py-6 sm:px-6 lg:px-8">
        <div class="rounded-[1.75rem] border-4 border-[#003b7a] bg-white p-5 shadow-[8px_8px_0_#003b7a] sm:p-7">
            <div class="mb-6">
                <div class="mb-3 inline-flex rounded-full bg-[#ffd900] px-4 py-2 text-xs font-black uppercase text-[#003b7a]">
                    {{ __('recharge.recharge_center') }}
                </div>

                <h1 class="text-3xl font-black uppercase text-[#003b7a] sm:text-4xl">
                    {{ __('recharge.submit_recharge') }}
                </h1>

                <p class="mt-2 text-sm font-bold text-[#003b7a]/70">
                    {{ __('recharge.submit_desc') }}
                </p>
            </div>

            <form method="POST" action="{{ route('recharge.store') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf

                <div>
                    <label class="mb-2 block text-sm font-black uppercase text-[#003b7a]">
                        {{ __('recharge.amount') }}
                    </label>

                    <input
                        name="amount"
                        value="{{ old('amount') }}"
                        type="number"
                        step="0.01"
                        min="1"
                        class="w-full rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-5 py-4 font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900]"
                        placeholder="100.00"
                        required
                    >

                    @error('amount')
                        <div class="mt-2 text-sm font-bold text-[#e4003a]">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-black uppercase text-[#003b7a]">
                        {{ __('recharge.bank_name') }}
                    </label>

                    <input
                        name="bank_name"
                        value="{{ old('bank_name') }}"
                        type="text"
                        class="w-full rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-5 py-4 font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900]"
                        placeholder="{{ __('recharge.bank_name_placeholder') }}"
                    >

                    @error('bank_name')
                        <div class="mt-2 text-sm font-bold text-[#e4003a]">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-black uppercase text-[#003b7a]">
                        {{ __('recharge.reference_no') }}
                    </label>

                    <input
                        name="reference_no"
                        value="{{ old('reference_no') }}"
                        type="text"
                        class="w-full rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-5 py-4 font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900]"
                        placeholder="{{ __('recharge.reference_no_placeholder') }}"
                    >

                    @error('reference_no')
                        <div class="mt-2 text-sm font-bold text-[#e4003a]">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-black uppercase text-[#003b7a]">
                        {{ __('recharge.receipt') }}
                    </label>

                    <input
                        name="receipt"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="w-full rounded-2xl border-2 border-dashed border-[#003b7a] bg-[#fff8c7] px-5 py-4 text-sm font-bold text-[#003b7a] file:mr-4 file:rounded-full file:border-0 file:bg-[#003b7a] file:px-4 file:py-2 file:text-sm file:font-black file:text-white"
                        required
                    >

                    <p class="mt-2 text-xs font-bold text-[#003b7a]/60">
                        {{ __('recharge.receipt_help') }}
                    </p>

                    @error('receipt')
                        <div class="mt-2 text-sm font-bold text-[#e4003a]">{{ $message }}</div>
                    @enderror
                </div>

                <div class="rounded-2xl bg-[#ffd900] p-4 text-sm font-bold text-[#003b7a]">
                    {{ __('recharge.warning_desc') }}
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <a href="{{ route('recharge.index') }}"
                       class="rounded-2xl bg-gray-100 px-5 py-4 text-center text-sm font-black uppercase text-gray-700">
                        {{ __('recharge.back') }}
                    </a>

                    <button
                        type="submit"
                        class="rounded-2xl bg-[#e4003a] px-5 py-4 text-sm font-black uppercase text-white shadow-[0_6px_0_#9e0028]">
                        {{ __('recharge.submit') }}
                    </button>
                </div>
            </form>
        </div>
    </section>
@endsection