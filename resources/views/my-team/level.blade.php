@extends('layouts.app')

@section('title', __('my_team.level_detail', ['level' => $levelLabel]))

@section('content')
    @php
        $currency = config('app.currency', 'RM');
        $commissionPercent = number_format((float) ($commissionRate?->rate_percent ?? 0), 2);
    @endphp

    <section class="bg-[#ffd900]">
        <div class="mx-auto max-w-7xl px-3 py-3 sm:px-6 sm:py-10 lg:px-8">
            <div class="grid gap-4 lg:grid-cols-[1fr_260px] lg:items-end">
                <div>
                    <a
                        href="{{ route('my-team.index') }}"
                        class="inline-flex rounded-full bg-white/75 px-4 py-1.5 text-[10px] font-black uppercase tracking-[0.14em] text-[#003b7a] shadow sm:px-5 sm:py-2 sm:text-xs sm:tracking-[0.18em]"
                    >
                        ← {{ __('app.back') }}
                    </a>

                    <h1 class="mt-3 text-3xl font-black uppercase leading-[0.9] tracking-tight text-[#003b7a] sm:mt-4 sm:text-6xl lg:text-7xl">
                        {{ $levelLabel }} {{ __('my_team.team') }}
                    </h1>

                    <p class="mt-2 line-clamp-2 max-w-2xl text-xs font-bold leading-relaxed text-[#003b7a]/75 sm:mt-4 sm:line-clamp-none sm:text-base">
                        {{ __('my_team.level_detail_desc') }}
                    </p>
                </div>

                <div class="rounded-[1.35rem] border-3 border-[#003b7a] bg-white p-3 shadow-[6px_6px_0_#003b7a] sm:rounded-[2rem] sm:border-4 sm:p-5 sm:shadow-[8px_8px_0_#003b7a]">
                    <div class="flex items-center justify-between gap-3 lg:block">
                        <div>
                            <div class="text-[10px] font-black uppercase tracking-[0.14em] text-[#e4003a] sm:text-xs sm:tracking-[0.18em]">
                                {{ __('my_team.commission_rate') }}
                            </div>

                            <div class="mt-1 text-3xl font-black leading-none text-[#003b7a] sm:mt-2 sm:text-4xl">
                                {{ $commissionPercent }}%
                            </div>
                        </div>

                        <div class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-[#ffd900] text-lg font-black text-[#003b7a] sm:hidden">
                            {{ $levelLabel }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-[#f5f5f5]">
        <div class="mx-auto max-w-7xl px-3 py-4 sm:px-6 sm:py-8 lg:px-8">
            <div class="rounded-[1.35rem] bg-white p-4 shadow-xl ring-1 ring-black/5 sm:rounded-[2rem] sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <h2 class="text-lg font-black uppercase text-[#003b7a] sm:text-2xl">
                            {{ __('my_team.downline_list') }}
                        </h2>

                        <p class="mt-1 line-clamp-1 text-xs font-bold text-[#003b7a]/55 sm:line-clamp-none sm:text-sm">
                            {{ __('my_team.downline_list_desc') }}
                        </p>
                    </div>

                    <form method="GET" action="{{ route('my-team.level', $level) }}" class="grid grid-cols-[1fr_auto] gap-2 sm:flex">
                        <select
                            name="status"
                            class="rounded-2xl border-2 border-[#003b7a]/15 bg-[#fff8c7] px-3 py-2 text-xs font-black text-[#003b7a] outline-none sm:px-4 sm:py-3 sm:text-sm"
                        >
                            <option value="all" @selected($status === 'all')>{{ __('my_team.all_status') }}</option>
                            <option value="active" @selected($status === 'active')>{{ __('status.active') }}</option>
                            <option value="inactive" @selected($status === 'inactive')>{{ __('status.inactive') }}</option>
                        </select>

                        <button
                            type="submit"
                            class="rounded-2xl bg-[#003b7a] px-4 py-2 text-xs font-black uppercase text-white sm:px-5 sm:py-3 sm:text-sm"
                        >
                            {{ __('app.filter') }}
                        </button>
                    </form>
                </div>

                <div class="mt-4 overflow-hidden rounded-[1.25rem] border border-[#003b7a]/10 sm:mt-5 sm:rounded-[1.5rem]">
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

                                $productLabel = '-';

                                if ($highestProduct) {
                                    $productLabel = trim(($highestProduct->product_code ?: '-') . ' ' . ($highestProduct->product_name ? '- ' . $highestProduct->product_name : ''));
                                }
                            @endphp

                            {{-- Mobile compact row --}}
                            <div class="p-3 md:hidden">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-black text-[#003b7a]">
                                            #{{ $downline->username }}
                                        </div>

                                        <div class="mt-0.5 text-[11px] font-bold text-[#003b7a]/55">
                                            {{ $lastFivePhone === '-' ? '-' : '*****' . $lastFivePhone }}
                                        </div>
                                    </div>

                                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-black uppercase {{ $downline->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ __('status.' . $downline->status) }}
                                    </span>
                                </div>

                                <div class="mt-2 grid grid-cols-[1.2fr_0.8fr] gap-2">
                                    <div class="min-w-0 rounded-2xl bg-[#fff8c7] p-2.5">
                                        <div class="text-[9px] font-black uppercase text-[#003b7a]/45">
                                            {{ __('my_team.highest_product') }}
                                        </div>

                                        <div class="mt-0.5 truncate text-xs font-black text-[#003b7a]">
                                            {{ $productLabel }}
                                        </div>

                                        @if ($highestProduct)
                                            <div class="mt-0.5 text-[10px] font-bold text-[#003b7a]/55">
                                                {{ $currency }} {{ number_format((float) $highestProduct->price_snapshot, 2) }}
                                            </div>
                                        @endif
                                    </div>

                                    <div class="rounded-2xl bg-[#fff8c7] p-2.5">
                                        <div class="text-[9px] font-black uppercase text-[#003b7a]/45">
                                            {{ __('my_team.joined_at') }}
                                        </div>

                                        <div class="mt-0.5 text-xs font-black text-[#003b7a]">
                                            {{ $downline->created_at?->format('d M Y') ?? '-' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Desktop/tablet table row --}}
                            <div class="hidden gap-3 px-4 py-4 md:grid md:grid-cols-[1fr_1fr_1fr_1.5fr_1fr] md:items-center md:gap-4">
                                <div>
                                    <div class="font-black text-[#003b7a]">
                                        #{{ $downline->username }}
                                    </div>
                                </div>

                                <div>
                                    <div class="font-black text-[#003b7a]">
                                        {{ $lastFivePhone === '-' ? '-' : '*****' . $lastFivePhone }}
                                    </div>
                                </div>

                                <div>
                                    <span class="inline-flex rounded-full px-3 py-1.5 text-xs font-black uppercase {{ $downline->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ __('status.' . $downline->status) }}
                                    </span>
                                </div>

                                <div class="min-w-0">
                                    @if ($highestProduct)
                                        <div class="truncate font-black text-[#003b7a]">
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
                                    <div class="text-sm font-bold text-[#003b7a]/70">
                                        {{ $downline->created_at?->format('d M Y') ?? '-' }}
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-8 text-center sm:p-10">
                                <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-[#003b7a] text-white sm:h-16 sm:w-16">
                                    @include('partials.icon', ['name' => 'team', 'class' => 'h-7 w-7 sm:h-8 sm:w-8'])
                                </div>

                                <h3 class="mt-4 text-lg font-black text-[#003b7a] sm:mt-5 sm:text-xl">
                                    {{ __('my_team.no_downline') }}
                                </h3>

                                <p class="mt-2 text-sm font-bold text-[#003b7a]/55">
                                    {{ __('my_team.no_downline_desc') }}
                                </p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="mt-4 sm:mt-5">
                    {{ $downlineUsers->links() }}
                </div>
            </div>
        </div>
    </section>
@endsection