@extends('layouts.app')

@section('title', __('my_team.level_detail', ['level' => $levelLabel]))

@section('content')
    @php
        $currency = config('app.currency', 'RM');
    @endphp

    <section class="bg-[#ffd900]">
        <div class="mx-auto max-w-7xl px-3 py-6 sm:px-6 sm:py-10 lg:px-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <a
                        href="{{ route('my-team.index') }}"
                        class="inline-flex rounded-full bg-white/75 px-5 py-2 text-xs font-black uppercase tracking-[0.18em] text-[#003b7a] shadow"
                    >
                        ← {{ __('app.back') }}
                    </a>

                    <h1 class="mt-4 text-4xl font-black uppercase leading-[0.9] tracking-tight text-[#003b7a] sm:text-6xl lg:text-7xl">
                        {{ $levelLabel }} {{ __('my_team.team') }}
                    </h1>

                    <p class="mt-4 max-w-2xl text-sm font-bold leading-relaxed text-[#003b7a]/75 sm:text-base">
                        {{ __('my_team.level_detail_desc') }}
                    </p>
                </div>

                <div class="rounded-[2rem] border-4 border-[#003b7a] bg-white p-5 shadow-[8px_8px_0_#003b7a]">
                    <div class="text-xs font-black uppercase tracking-[0.18em] text-[#e4003a]">
                        {{ __('my_team.commission_rate') }}
                    </div>

                    <div class="mt-2 text-4xl font-black text-[#003b7a]">
                        {{ number_format((float) ($commissionRate?->rate_percent ?? 0), 2) }}%
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-[#f5f5f5]">
        <div class="mx-auto max-w-7xl px-3 py-6 sm:px-6 sm:py-8 lg:px-8">
            <div class="rounded-[2rem] bg-white p-4 shadow-xl ring-1 ring-black/5 sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-black uppercase text-[#003b7a] sm:text-2xl">
                            {{ __('my_team.downline_list') }}
                        </h2>

                        <p class="mt-1 text-sm font-bold text-[#003b7a]/55">
                            {{ __('my_team.downline_list_desc') }}
                        </p>
                    </div>

                    <form method="GET" action="{{ route('my-team.level', $level) }}" class="flex gap-2">
                        <select
                            name="status"
                            class="rounded-2xl border-2 border-[#003b7a]/15 bg-[#fff8c7] px-4 py-3 text-sm font-black text-[#003b7a] outline-none"
                        >
                            <option value="all" @selected($status === 'all')>{{ __('my_team.all_status') }}</option>
                            <option value="active" @selected($status === 'active')>{{ __('status.active') }}</option>
                            <option value="inactive" @selected($status === 'inactive')>{{ __('status.inactive') }}</option>
                        </select>

                        <button
                            type="submit"
                            class="rounded-2xl bg-[#003b7a] px-5 py-3 text-sm font-black uppercase text-white"
                        >
                            {{ __('app.filter') }}
                        </button>
                    </form>
                </div>

                <div class="mt-5 overflow-hidden rounded-[1.5rem] border border-[#003b7a]/10">
                    <div class="hidden bg-[#fff8c7] px-4 py-3 text-xs font-black uppercase text-[#003b7a]/70 md:grid md:grid-cols-[1fr_1fr_1fr_1.5fr_1fr] md:gap-4">
                        <div>{{ __('my_team.user_id') }}</div>
                        <div>{{ __('my_team.phone_last_5') }}</div>
                        <div>{{ __('my_team.status') }}</div>
                        <div>{{ __('my_team.highest_product') }}</div>
                        <div>{{ __('my_team.joined_at') }}</div>
                    </div>

                    <div class="divide-y divide-[#003b7a]/10 bg-white">
                        @forelse ($downlineUsers as $downline)
                            @php
                                $phone = preg_replace('/\D+/', '', (string) $downline->phone);
                                $lastFivePhone = $phone ? substr($phone, -5) : '-';
                                $highestProduct = $downline->highest_product_snapshot;
                            @endphp

                            <div class="grid gap-3 px-4 py-4 md:grid-cols-[1fr_1fr_1fr_1.5fr_1fr] md:items-center md:gap-4">
                                <div>
                                    <div class="text-[10px] font-black uppercase text-[#003b7a]/45 md:hidden">
                                        {{ __('my_team.user_id') }}
                                    </div>

                                    <div class="font-black text-[#003b7a]">
                                        #{{ $downline->username }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-[10px] font-black uppercase text-[#003b7a]/45 md:hidden">
                                        {{ __('my_team.phone_last_5') }}
                                    </div>

                                    <div class="font-black text-[#003b7a]">
                                        {{ $lastFivePhone === '-' ? '-' : '*****' . $lastFivePhone }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-[10px] font-black uppercase text-[#003b7a]/45 md:hidden">
                                        {{ __('my_team.status') }}
                                    </div>

                                    <span class="inline-flex rounded-full px-3 py-1.5 text-xs font-black uppercase {{ $downline->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ __('status.' . $downline->status) }}
                                    </span>
                                </div>

                                <div>
                                    <div class="text-[10px] font-black uppercase text-[#003b7a]/45 md:hidden">
                                        {{ __('my_team.highest_product') }}
                                    </div>

                                    @if ($highestProduct)
                                        <div class="font-black text-[#003b7a]">
                                            {{ $highestProduct->product_code ?: '-' }}
                                            {{ $highestProduct->product_name ? ' - ' . $highestProduct->product_name : '' }}
                                        </div>

                                        <div class="mt-1 text-xs font-bold text-[#003b7a]/55">
                                            {{ $currency }} {{ number_format((float) $highestProduct->price_snapshot, 2) }}
                                        </div>
                                    @else
                                        <div class="font-black text-[#003b7a]/45">
                                            -
                                        </div>
                                    @endif
                                </div>

                                <div>
                                    <div class="text-[10px] font-black uppercase text-[#003b7a]/45 md:hidden">
                                        {{ __('my_team.joined_at') }}
                                    </div>

                                    <div class="text-sm font-bold text-[#003b7a]/70">
                                        {{ $downline->created_at?->format('d M Y') ?? '-' }}
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-10 text-center">
                                <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-[#003b7a] text-white">
                                    @include('partials.icon', ['name' => 'team', 'class' => 'h-8 w-8'])
                                </div>

                                <h3 class="mt-5 text-xl font-black text-[#003b7a]">
                                    {{ __('my_team.no_downline') }}
                                </h3>

                                <p class="mt-2 text-sm font-bold text-[#003b7a]/55">
                                    {{ __('my_team.no_downline_desc') }}
                                </p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="mt-5">
                    {{ $downlineUsers->links() }}
                </div>
            </div>
        </div>
    </section>
@endsection