@extends('layouts.app')

@section('title', __('bank_account.bind_bank_account'))

@section('content')
    <section class="mx-auto max-w-3xl px-3 py-4 sm:px-6 sm:py-8 lg:px-8">
        <div
             class="rounded-[1.35rem] border-4 border-[#003b7a] bg-white p-4 shadow-[6px_6px_0_#003b7a] sm:rounded-[1.75rem] sm:p-7 sm:shadow-[8px_8px_0_#003b7a]">
            <div class="mb-4 sm:mb-6">
                <div
                     class="mb-2 inline-flex rounded-full bg-[#ffd900] px-3 py-1.5 text-[10px] font-black uppercase text-[#003b7a] sm:mb-3 sm:px-4 sm:py-2 sm:text-xs">
                    {{ __('bank_account.security_binding') }}
                </div>

                <h1 class="text-2xl font-black uppercase leading-none text-[#003b7a] sm:text-4xl">
                    {{ __('bank_account.bind_bank_account') }}
                </h1>

                <p class="mt-2 text-xs font-bold leading-relaxed text-[#003b7a]/70 sm:text-sm">
                    {{ __('bank_account.bind_desc') }}
                </p>
            </div>

            <div
                 class="mb-4 rounded-xl bg-[#ffd900] p-3 text-xs font-black leading-relaxed text-[#003b7a] sm:mb-5 sm:rounded-2xl sm:p-4 sm:text-sm">
                ⚠️ {{ __('bank_account.security_warning') }}
            </div>

            @if ($reviewRequest?->isPending())
                <div class="rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] p-5 text-center sm:p-7">
                    <div class="text-4xl">⏳</div>
                    <h2 class="mt-3 text-xl font-black uppercase text-[#003b7a]">
                        {{ __('bank_account.review_pending_title') }}
                    </h2>
                    <p class="mt-2 text-sm font-bold leading-relaxed text-[#003b7a]/70">
                        {{ __('bank_account.review_pending_desc') }}
                    </p>
                </div>
            @else
                @if ($reviewRequest?->isRejected())
                    <div class="mb-4 rounded-2xl border-2 border-[#e4003a] bg-red-50 p-4 sm:mb-5">
                        <h2 class="font-black text-[#e4003a]">{{ __('bank_account.review_rejected_title') }}</h2>
                        <p class="mt-1 text-sm font-bold text-red-700">
                            {{ $reviewRequest->admin_remark ?: __('bank_account.review_rejected_desc') }}
                        </p>
                    </div>
                @endif

                <form method="POST" action="{{ route('bank-account.store') }}" enctype="multipart/form-data"
                      class="space-y-3 sm:space-y-4">
                    @csrf

                    <div>
                        <label class="mb-1.5 block text-xs font-black uppercase text-[#003b7a] sm:mb-2 sm:text-sm">
                            {{ __('bank_account.real_name') }}
                        </label>

                        <input
                               name="bank_account_name"
                               value="{{ old('bank_account_name', $reviewRequest?->bank_account_name ?: $user->name) }}"
                               type="text"
                               class="w-full rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3 text-sm font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:rounded-2xl sm:px-5 sm:py-4 sm:text-base"
                               placeholder="{{ __('bank_account.real_name_placeholder') }}"
                               required>

                        @error('bank_account_name')
                            <div class="mt-1.5 text-xs font-bold text-[#e4003a] sm:text-sm">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-black uppercase text-[#003b7a] sm:mb-2 sm:text-sm">
                            {{ __('bank_account.bank_name') }}
                        </label>

                        <select
                                name="bank_name"
                                class="w-full rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3 text-sm font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:rounded-2xl sm:px-5 sm:py-4 sm:text-base"
                                required>
                            <option value="" disabled
                                    {{ old('bank_name', $reviewRequest?->bank_name) ? '' : 'selected' }}>
                                {{ __('bank_account.bank_name_placeholder') }}
                            </option>

                            <option value="Vietcombank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'Vietcombank' ? 'selected' : '' }}>
                                Vietcombank</option>
                            <option value="VietinBank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'VietinBank' ? 'selected' : '' }}>
                                VietinBank</option>
                            <option value="BIDV"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'BIDV' ? 'selected' : '' }}>BIDV
                            </option>
                            <option value="Agribank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'Agribank' ? 'selected' : '' }}>
                                Agribank</option>
                            <option value="MBBank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'MBBank' ? 'selected' : '' }}>
                                MBBank</option>
                            <option value="Techcombank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'Techcombank' ? 'selected' : '' }}>
                                Techcombank</option>
                            <option value="VPBank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'VPBank' ? 'selected' : '' }}>
                                VPBank</option>
                            <option value="ACB"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'ACB' ? 'selected' : '' }}>ACB
                            </option>
                            <option value="Sacombank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'Sacombank' ? 'selected' : '' }}>
                                Sacombank</option>
                            <option value="TPBank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'TPBank' ? 'selected' : '' }}>
                                TPBank</option>
                            <option value="VIB"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'VIB' ? 'selected' : '' }}>VIB
                            </option>
                            <option value="MSB"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'MSB' ? 'selected' : '' }}>MSB
                            </option>
                            <option value="HDBank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'HDBank' ? 'selected' : '' }}>
                                HDBank</option>
                            <option value="SHB"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'SHB' ? 'selected' : '' }}>SHB
                            </option>
                            <option value="OCB"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'OCB' ? 'selected' : '' }}>OCB
                            </option>
                            <option value="SeABank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'SeABank' ? 'selected' : '' }}>
                                SeABank</option>
                            <option value="LPBank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'LPBank' ? 'selected' : '' }}>
                                LPBank</option>
                            <option value="Nam A Bank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'Nam A Bank' ? 'selected' : '' }}>
                                Nam A Bank</option>
                            <option value="Eximbank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'Eximbank' ? 'selected' : '' }}>
                                Eximbank</option>
                            <option value="ABBank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'ABBank' ? 'selected' : '' }}>
                                ABBank</option>
                            <option value="Bac A Bank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'Bac A Bank' ? 'selected' : '' }}>
                                Bac A Bank</option>
                            <option value="BaoViet Bank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'BaoViet Bank' ? 'selected' : '' }}>
                                BaoViet Bank</option>
                            <option value="VietABank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'VietABank' ? 'selected' : '' }}>
                                VietABank</option>
                            <option value="VietBank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'VietBank' ? 'selected' : '' }}>
                                VietBank</option>
                            <option value="BVBank / Viet Capital Bank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'BVBank / Viet Capital Bank' ? 'selected' : '' }}>
                                BVBank / Viet Capital Bank</option>
                            <option value="KienlongBank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'KienlongBank' ? 'selected' : '' }}>
                                KienlongBank</option>
                            <option value="NCB"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'NCB' ? 'selected' : '' }}>NCB
                            </option>
                            <option value="PVcomBank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'PVcomBank' ? 'selected' : '' }}>
                                PVcomBank</option>
                            <option value="PGBank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'PGBank' ? 'selected' : '' }}>
                                PGBank</option>
                            <option value="SaigonBank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'SaigonBank' ? 'selected' : '' }}>
                                SaigonBank</option>
                            <option value="GPBank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'GPBank' ? 'selected' : '' }}>
                                GPBank</option>
                            <option value="MBV / OceanBank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'MBV / OceanBank' ? 'selected' : '' }}>
                                MBV / OceanBank</option>
                            <option value="CBBank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'CBBank' ? 'selected' : '' }}>
                                CBBank</option>
                            <option value="SCB"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'SCB' ? 'selected' : '' }}>SCB
                            </option>
                            <option value="Co-opBank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'Co-opBank' ? 'selected' : '' }}>
                                Co-opBank</option>
                            <option value="VRB – Vietnam-Russia Joint Venture Bank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'VRB – Vietnam-Russia Joint Venture Bank' ? 'selected' : '' }}>
                                VRB – Vietnam-Russia Joint Venture Bank</option>
                            <option value="Indovina Bank (IVB)"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'Indovina Bank (IVB)' ? 'selected' : '' }}>
                                Indovina Bank (IVB)</option>

                            <option value="Shinhan Bank Vietnam"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'Shinhan Bank Vietnam' ? 'selected' : '' }}>
                                Shinhan Bank Vietnam</option>
                            <option value="Woori Bank Vietnam"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'Woori Bank Vietnam' ? 'selected' : '' }}>
                                Woori Bank Vietnam</option>
                            <option value="CIMB Vietnam"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'CIMB Vietnam' ? 'selected' : '' }}>
                                CIMB Vietnam</option>
                            <option value="Public Bank Vietnam"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'Public Bank Vietnam' ? 'selected' : '' }}>
                                Public Bank Vietnam</option>
                            <option value="Hong Leong Bank Vietnam"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'Hong Leong Bank Vietnam' ? 'selected' : '' }}>
                                Hong Leong Bank Vietnam</option>
                            <option value="Standard Chartered Vietnam"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'Standard Chartered Vietnam' ? 'selected' : '' }}>
                                Standard Chartered Vietnam</option>
                            <option value="HSBC Vietnam"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'HSBC Vietnam' ? 'selected' : '' }}>
                                HSBC Vietnam</option>
                            <option value="UOB Vietnam"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'UOB Vietnam' ? 'selected' : '' }}>
                                UOB Vietnam</option>
                            <option value="Kasikornbank / KBank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'Kasikornbank / KBank' ? 'selected' : '' }}>
                                Kasikornbank / KBank</option>
                            <option value="Kookmin Bank Hanoi"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'Kookmin Bank Hanoi' ? 'selected' : '' }}>
                                Kookmin Bank Hanoi</option>
                            <option value="Kookmin Bank HCMC"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'Kookmin Bank HCMC' ? 'selected' : '' }}>
                                Kookmin Bank HCMC</option>
                            <option value="KEB Hana Bank Hanoi"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'KEB Hana Bank Hanoi' ? 'selected' : '' }}>
                                KEB Hana Bank Hanoi</option>
                            <option value="KEB Hana Bank HCMC"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'KEB Hana Bank HCMC' ? 'selected' : '' }}>
                                KEB Hana Bank HCMC</option>
                            <option value="NongHyup Bank Hanoi"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'NongHyup Bank Hanoi' ? 'selected' : '' }}>
                                NongHyup Bank Hanoi</option>
                            <option value="IBK Hanoi / HCMC"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'IBK Hanoi / HCMC' ? 'selected' : '' }}>
                                IBK Hanoi / HCMC</option>
                            <option value="DBS Bank HCMC"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'DBS Bank HCMC' ? 'selected' : '' }}>
                                DBS Bank HCMC</option>

                            <option value="CAKE by VPBank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'CAKE by VPBank' ? 'selected' : '' }}>
                                CAKE by VPBank</option>
                            <option value="Timo Digital Bank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'Timo Digital Bank' ? 'selected' : '' }}>
                                Timo Digital Bank</option>
                            <option value="Ubank by VPBank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'Ubank by VPBank' ? 'selected' : '' }}>
                                Ubank by VPBank</option>
                            <option value="LioBank by OCB"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'LioBank by OCB' ? 'selected' : '' }}>
                                LioBank by OCB</option>
                            <option value="Vikki Digital Bank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'Vikki Digital Bank' ? 'selected' : '' }}>
                                Vikki Digital Bank</option>
                            <option value="Umee / KienlongBank"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'Umee / KienlongBank' ? 'selected' : '' }}>
                                Umee / KienlongBank</option>

                            <option value="MoMo"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'MoMo' ? 'selected' : '' }}>MoMo
                            </option>
                            <option value="ZaloPay"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'ZaloPay' ? 'selected' : '' }}>
                                ZaloPay</option>
                            <option value="VNPAY"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'VNPAY' ? 'selected' : '' }}>VNPAY
                            </option>
                            <option value="ShopeePay"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'ShopeePay' ? 'selected' : '' }}>
                                ShopeePay</option>
                            <option value="Viettel Money"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'Viettel Money' ? 'selected' : '' }}>
                                Viettel Money</option>
                            <option value="VNPT Money"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'VNPT Money' ? 'selected' : '' }}>
                                VNPT Money</option>
                            <option value="MobiFone Money"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'MobiFone Money' ? 'selected' : '' }}>
                                MobiFone Money</option>
                            <option value="VETC"
                                    {{ old('bank_name', $reviewRequest?->bank_name) === 'VETC' ? 'selected' : '' }}>VETC
                            </option>
                        </select>

                        @error('bank_name')
                            <div class="mt-1.5 text-xs font-bold text-[#e4003a] sm:text-sm">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-black uppercase text-[#003b7a] sm:mb-2 sm:text-sm">
                            {{ __('bank_account.bank_account_no') }}
                        </label>

                        <input
                               name="bank_account_no"
                               value="{{ old('bank_account_no', $reviewRequest?->bank_account_no) }}"
                               type="text"
                               inputmode="numeric"
                               class="w-full rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3 text-sm font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:rounded-2xl sm:px-5 sm:py-4 sm:text-base"
                               placeholder="{{ __('bank_account.bank_account_no_placeholder') }}"
                               required>

                        @error('bank_account_no')
                            <div class="mt-1.5 text-xs font-bold text-[#e4003a] sm:text-sm">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-black uppercase text-[#003b7a] sm:mb-2 sm:text-sm">
                            {{ __('bank_account.phone') }}
                        </label>

                        <div class="grid gap-2 sm:grid-cols-[1fr_auto]">
                            <div
                                 class="flex overflow-hidden rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] focus-within:bg-white focus-within:ring-4 focus-within:ring-[#ffd900] sm:rounded-2xl">
                                <div
                                     class="flex items-center border-r-2 border-[#003b7a] bg-[#003b7a] px-4 text-xs font-black text-white sm:px-5 sm:text-sm">
                                    +84
                                </div>

                                <input
                                       id="bank_phone"
                                       name="phone"
                                       value="{{ old('phone', $reviewRequest?->phone ?: $user->phone) }}"
                                       type="text"
                                       class="min-w-0 flex-1 bg-transparent px-4 py-3 text-sm font-bold text-[#003b7a] outline-none sm:px-5 sm:py-4 sm:text-base"
                                       placeholder="{{ __('bank_account.phone_placeholder') }}"
                                       required>
                            </div>

                            <button
                                    type="button"
                                    data-otp-send
                                    data-otp-url="{{ route('bank-account.otp.send') }}"
                                    data-phone-input="bank_phone"
                                    class="rounded-xl bg-[#003b7a] px-4 py-3 text-xs font-black uppercase text-white shadow-[0_4px_0_#001f40] sm:rounded-2xl sm:px-5 sm:py-4 sm:text-sm">
                                {{ __('otp.send_code') }}
                            </button>
                        </div>

                        <div data-otp-message class="mt-1.5 hidden text-xs font-bold sm:text-sm"></div>

                        {{-- <p class="mt-2 text-xs font-bold text-[#003b7a]/60">
                        {{ __('otp.phone_prefix_hint') }}
                    </p> --}}

                        @error('phone')
                            <div class="mt-1.5 text-xs font-bold text-[#e4003a] sm:text-sm">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-black uppercase text-[#003b7a] sm:mb-2 sm:text-sm">
                            {{ __('otp.verification_code') }}
                        </label>

                        <input
                               name="otp_code"
                               value="{{ old('otp_code') }}"
                               type="text"
                               inputmode="numeric"
                               maxlength="6"
                               class="w-full rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3 text-sm font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:rounded-2xl sm:px-5 sm:py-4 sm:text-base"
                               placeholder="{{ __('otp.code_placeholder') }}"
                               required>

                        @error('otp_code')
                            <div class="mt-1.5 text-xs font-bold text-[#e4003a] sm:text-sm">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-black uppercase text-[#003b7a] sm:mb-2 sm:text-sm">
                            {{ __('bank_account.id_photos') }}
                        </label>

                        <input
                               name="id_photos[]"
                               type="file"
                               accept="image/jpeg,image/png,image/webp"
                               multiple
                               @required(!$reviewRequest?->isRejected())
                               class="w-full rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3 text-sm font-bold text-[#003b7a] file:mr-3 file:rounded-lg file:border-0 file:bg-[#003b7a] file:px-3 file:py-2 file:font-black file:text-white sm:rounded-2xl sm:px-5 sm:py-4">

                        <p class="mt-2 text-xs font-bold text-[#003b7a]/60">
                            {{ __('bank_account.id_photos_hint') }}
                        </p>

                        @if ($reviewRequest?->isRejected())
                            <p class="mt-1 text-xs font-bold text-[#003b7a]/60">
                                {{ __('bank_account.existing_id_photos', ['count' => count($reviewRequest->id_photo_paths ?? [])]) }}
                            </p>
                        @endif

                        @error('id_photos')
                            <div class="mt-1.5 text-xs font-bold text-[#e4003a] sm:text-sm">{{ $message }}</div>
                        @enderror
                        @error('id_photos.*')
                            <div class="mt-1.5 text-xs font-bold text-[#e4003a] sm:text-sm">{{ $message }}</div>
                        @enderror
                    </div>
                    <div
                         class="rounded-xl bg-[#fff8c7] p-3 text-xs font-bold leading-relaxed text-[#003b7a]/75 ring-1 ring-[#003b7a]/10 sm:rounded-2xl sm:p-4 sm:text-sm">
                        {{ __('bank_account.change_notice') }}
                    </div>

                    <div class="grid grid-cols-2 gap-2 sm:gap-3">
                        <a
                           href="{{ route('withdraw.index') }}"
                           class="rounded-xl bg-gray-100 px-4 py-3 text-center text-xs font-black uppercase text-gray-700 sm:rounded-2xl sm:px-5 sm:py-4 sm:text-sm">
                            {{ __('app.back') }}
                        </a>

                        <button
                                type="submit"
                                class="rounded-xl bg-[#e4003a] px-4 py-3 text-xs font-black uppercase text-white shadow-[0_4px_0_#9e0028] sm:rounded-2xl sm:px-5 sm:py-4 sm:text-sm sm:shadow-[0_6px_0_#9e0028]">
                            {{ __('bank_account.submit_for_review') }}
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </section>
@endsection
