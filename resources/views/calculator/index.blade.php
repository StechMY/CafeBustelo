@extends('layouts.app')

@section('title', __('calculator.calculator'))

@section('content')
@section('content')
    @php
        $currency = config('app.currency', 'RM');
    @endphp

    <div
         x-data="teamIncomeCalculator({
             products: @js($calculatorProducts),
             rates: @js($rates),
             currency: @js($currency),
         })">
        <section class="bg-[#ffd900]">
            <div class="mx-auto max-w-7xl px-3 py-6 sm:px-6 sm:py-10 lg:px-8">
                <div class="grid gap-6 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
                    <div>
                        <div
                             class="inline-flex rounded-full bg-white/75 px-5 py-2 text-xs font-black uppercase tracking-[0.22em] text-[#003b7a] shadow">
                            {{ __('calculator.calculator_center') }}
                        </div>

                        <h1
                            class="mt-4 max-w-3xl text-4xl font-black uppercase leading-[0.9] tracking-tight text-[#003b7a] sm:text-6xl lg:text-7xl">
                            {{ __('calculator.hero_title') }}
                        </h1>

                        <p class="mt-5 max-w-2xl text-sm font-bold leading-relaxed text-[#003b7a]/75 sm:text-base">
                            {{ __('calculator.hero_description') }}
                        </p>
                    </div>

                    <div class="rounded-[2rem] border-4 border-[#003b7a] bg-white p-5 shadow-[10px_10px_0_#003b7a] sm:p-7">
                        <div class="text-xs font-black uppercase tracking-[0.18em] text-[#e4003a]">
                            {{ __('calculator.selected_product') }}
                        </div>

                        <h2
                            class="mt-3 text-3xl font-black leading-tight text-[#003b7a] sm:text-4xl"
                            x-text="selectedProduct ? selectedProduct.name : '-'"></h2>

                        <div class="mt-5 grid grid-cols-2 gap-3">
                            <div class="rounded-2xl bg-[#fff8c7] p-4">
                                <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                    {{ __('calculator.daily_income') }}
                                </div>

                                <div
                                     class="mt-1 text-xl font-black text-[#e4003a]"
                                     x-text="formatMoney(selectedProduct?.daily_income || 0)"></div>
                            </div>

                            <div class="rounded-2xl bg-[#fff8c7] p-4">
                                <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                    {{ __('calculator.duration') }}
                                </div>

                                <div class="mt-1 text-xl font-black text-[#003b7a]">
                                    <span x-text="selectedProduct?.duration_days || 0"></span>
                                    {{ __('calculator.days') }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 rounded-2xl bg-[#003b7a] p-4 text-white">
                            <div class="text-[10px] font-black uppercase text-white/60">
                                {{ __('calculator.daily_total') }}
                            </div>

                            <div
                                 class="mt-1 break-words text-3xl font-black text-[#ffd900]"
                                 x-text="formatMoney(totalDaily())"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-[#f5f5f5]">
            <div class="mx-auto max-w-7xl px-3 py-6 sm:px-6 sm:py-8 lg:px-8">
                @if ($products->isEmpty())
                    <div class="rounded-[2rem] bg-white p-10 text-center shadow-xl ring-1 ring-black/5">
                        <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-[#003b7a] text-white">
                            @include('partials.icon', ['name' => 'calculator', 'class' => 'h-8 w-8'])
                        </div>

                        <h2 class="mt-5 text-xl font-black text-[#003b7a] sm:text-2xl">
                            {{ __('calculator.no_products') }}
                        </h2>

                        <p class="mt-2 text-sm font-bold text-[#003b7a]/60">
                            {{ __('calculator.no_products_desc') }}
                        </p>
                    </div>
                @else
                    <div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
                        <div class="rounded-[2rem] bg-white p-5 shadow-xl ring-1 ring-black/5 sm:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h2 class="text-xl font-black uppercase text-[#003b7a] sm:text-2xl">
                                        {{ __('calculator.enter_team_size') }}
                                    </h2>

                                    <p class="mt-1 text-sm font-bold text-[#003b7a]/55">
                                        {{ __('calculator.enter_team_size_desc') }}
                                    </p>
                                </div>

                                <div
                                     class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-[#003b7a] text-[#ffd900]">
                                    @include('partials.icon', [
                                        'name' => 'calculator',
                                        'class' => 'h-6 w-6',
                                    ])
                                </div>
                            </div>

                            <div class="mt-5 space-y-4">
                                <div>
                                    <label class="mb-2 block text-xs font-black uppercase text-[#003b7a]/65">
                                        {{ __('calculator.product') }}
                                    </label>

                                    <select
                                            x-model.number="productId"
                                            class="w-full rounded-2xl border-2 border-[#003b7a]/15 bg-[#fff8c7] px-4 py-3 text-sm font-black text-[#003b7a] outline-none focus:border-[#003b7a]">
                                        <template x-for="product in products" :key="product.id">
                                            <option
                                                    :value="product.id"
                                                    x-text="`${product.code} - ${product.name}`">
                                            </option>
                                        </template>
                                    </select>
                                </div>

                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <label class="mb-2 block text-xs font-black uppercase text-[#003b7a]/65">
                                            B
                                            <span class="text-[#e4003a]" x-text="`${money(rates.b)}%`"></span>
                                        </label>

                                        <input
                                               type="number"
                                               min="0"
                                               step="1"
                                               x-model.number="bCount"
                                               class="w-full rounded-2xl border-2 border-[#003b7a]/15 bg-white px-3 py-3 text-center text-lg font-black text-[#003b7a] outline-none focus:border-[#003b7a]">
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-black uppercase text-[#003b7a]/65">
                                            C
                                            <span class="text-[#e4003a]" x-text="`${money(rates.c)}%`"></span>
                                        </label>

                                        <input
                                               type="number"
                                               min="0"
                                               step="1"
                                               x-model.number="cCount"
                                               class="w-full rounded-2xl border-2 border-[#003b7a]/15 bg-white px-3 py-3 text-center text-lg font-black text-[#003b7a] outline-none focus:border-[#003b7a]">
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-black uppercase text-[#003b7a]/65">
                                            D
                                            <span class="text-[#e4003a]" x-text="`${money(rates.d)}%`"></span>
                                        </label>

                                        <input
                                               type="number"
                                               min="0"
                                               step="1"
                                               x-model.number="dCount"
                                               class="w-full rounded-2xl border-2 border-[#003b7a]/15 bg-white px-3 py-3 text-center text-lg font-black text-[#003b7a] outline-none focus:border-[#003b7a]">
                                    </div>
                                </div>

                                <button
                                        type="button"
                                        class="w-full rounded-2xl bg-[#e4003a] px-5 py-3 text-sm font-black uppercase text-white shadow-lg transition hover:-translate-y-0.5 hover:bg-[#c90032]"
                                        @click="reset()">
                                    {{ __('app.reset') }}
                                </button>
                            </div>
                        </div>

                        <div class="rounded-[2rem] bg-white p-5 shadow-xl ring-1 ring-black/5 sm:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h2 class="text-xl font-black uppercase text-[#003b7a] sm:text-2xl">
                                        {{ __('calculator.result') }}
                                    </h2>

                                    <p class="mt-1 text-sm font-bold text-[#003b7a]/55">
                                        {{ __('calculator.result_desc') }}
                                    </p>
                                </div>

                                <div
                                     class="shrink-0 rounded-full bg-[#ffd900] px-4 py-2 text-xs font-black uppercase text-[#003b7a]"
                                     x-text="selectedProduct?.code || '-'"></div>
                            </div>

                            <div class="mt-5 grid grid-cols-2 gap-3">
                                <div class="rounded-2xl bg-[#fff8c7] p-4">
                                    <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                        {{ __('calculator.daily_income') }}
                                    </div>

                                    <div
                                         class="mt-1 text-xl font-black text-[#e4003a]"
                                         x-text="formatMoney(selectedProduct?.daily_income || 0)"></div>
                                </div>

                                <div class="rounded-2xl bg-[#fff8c7] p-4">
                                    <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                        {{ __('calculator.duration') }}
                                    </div>

                                    <div class="mt-1 text-xl font-black text-[#003b7a]">
                                        <span x-text="selectedProduct?.duration_days || 0"></span>
                                        {{ __('calculator.days') }}
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 space-y-3">
                                <template x-for="row in rows()" :key="row.key">
                                    <div class="rounded-[1.5rem] bg-[#fff8c7] p-4 ring-1 ring-[#003b7a]/10">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <div class="text-xs font-black uppercase text-[#003b7a]/60"
                                                     x-text="row.label">
                                                </div>

                                                <div class="mt-1 text-sm font-bold text-[#003b7a]/70">
                                                    <span x-text="formatMoney(selectedProduct?.daily_income || 0)"></span>
                                                    ×
                                                    <span x-text="money(row.rate)"></span>%
                                                    ×
                                                    <span x-text="row.count"></span>
                                                </div>
                                            </div>

                                            <div
                                                 class="text-2xl font-black text-[#e4003a]"
                                                 x-text="formatMoney(row.amount)"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-[1.5rem] bg-[#003b7a] p-4 text-white shadow-lg">
                                    <div class="text-[10px] font-black uppercase text-white/60">
                                        {{ __('calculator.daily_total') }}
                                    </div>

                                    <div
                                         class="mt-2 break-words text-2xl font-black"
                                         x-text="formatMoney(totalDaily())"></div>
                                </div>

                                <div class="rounded-[1.5rem] bg-[#e4003a] p-4 text-white shadow-lg">
                                    <div class="text-[10px] font-black uppercase text-white/60">
                                        {{ __('calculator.monthly_estimate') }}
                                    </div>

                                    <div
                                         class="mt-2 break-words text-2xl font-black"
                                         x-text="formatMoney(totalMonthly())"></div>
                                </div>

                                <div class="rounded-[1.5rem] bg-[#ffd900] p-4 text-[#003b7a] shadow-lg">
                                    <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                        {{ __('calculator.cycle_estimate') }}
                                    </div>

                                    <div
                                         class="mt-2 break-words text-2xl font-black"
                                         x-text="formatMoney(totalCycle())"></div>
                                </div>
                            </div>

                            <div
                                 class="mt-5 rounded-[1.5rem] bg-[#003b7a]/5 p-4 text-sm font-bold leading-relaxed text-[#003b7a]/70">
                                {{ __('calculator.disclaimer') }}
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        function teamIncomeCalculator(config) {
            return {
                products: config.products || [],
                rates: config.rates || {
                    b: 0,
                    c: 0,
                    d: 0,
                },
                currency: config.currency || 'RM',

                productId: config.products?.[0]?.id || null,
                bCount: 0,
                cCount: 0,
                dCount: 0,

                get selectedProduct() {
                    return this.products.find((product) => Number(product.id) === Number(this.productId)) ||
                        this.products[0] ||
                        null;
                },

                reset() {
                    this.bCount = 0;
                    this.cCount = 0;
                    this.dCount = 0;
                },

                sanitizeCount(value) {
                    const number = Number(value || 0);

                    if (Number.isNaN(number) || number < 0) {
                        return 0;
                    }

                    return Math.floor(number);
                },

                amount(rate, count) {
                    const dailyIncome = Number(this.selectedProduct?.daily_income || 0);
                    const cleanRate = Number(rate || 0);
                    const cleanCount = this.sanitizeCount(count);

                    return dailyIncome * (cleanRate / 100) * cleanCount;
                },

                rows() {
                    return [{
                            key: 'b',
                            label: @json(__('calculator.b_level_income')),
                            rate: Number(this.rates.b || 0),
                            count: this.sanitizeCount(this.bCount),
                            amount: this.amount(this.rates.b, this.bCount),
                        },
                        {
                            key: 'c',
                            label: @json(__('calculator.c_level_income')),
                            rate: Number(this.rates.c || 0),
                            count: this.sanitizeCount(this.cCount),
                            amount: this.amount(this.rates.c, this.cCount),
                        },
                        {
                            key: 'd',
                            label: @json(__('calculator.d_level_income')),
                            rate: Number(this.rates.d || 0),
                            count: this.sanitizeCount(this.dCount),
                            amount: this.amount(this.rates.d, this.dCount),
                        },
                    ];
                },

                totalDaily() {
                    return this.rows().reduce((total, row) => total + row.amount, 0);
                },

                totalMonthly() {
                    return this.totalDaily() * 30;
                },

                totalCycle() {
                    return this.totalDaily() * Number(this.selectedProduct?.duration_days || 0);
                },

                money(value) {
                    return Number(value || 0).toLocaleString(undefined, {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });
                },

                formatMoney(value) {
                    return `${this.currency} ${this.money(value)}`;
                },
            };
        }
    </script>
@endpush
