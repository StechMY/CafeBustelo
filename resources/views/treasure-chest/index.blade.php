@extends('layouts.app')
@section('title', __('treasure_chest.title'))
@push('styles')
    <link rel="preload" as="image" href="{{ asset('images/treasure-chest/chest-atlas-v2.png') }}">
@endpush
@section('content')
<section class="tc-page" data-treasure-chest style="--tc-atlas: url('{{ asset('images/treasure-chest/chest-atlas-v2.png') }}')">
    <nav class="tc-page-nav"><a href="{{ route('dashboard') }}"><i class="ph-thin ph-arrow-left" aria-hidden="true"></i>{{ __('golden_egg.back') }}</a><a href="#tc-rules">{{ __('treasure_chest.rules') }}</a></nav>
    <div class="tc-vault">
    <header class="tc-heading">
        <div class="tc-heading-ornament" aria-hidden="true"><span></span><i class="ph-thin ph-key"></i><span></span></div>
        <h1 tabindex="-1" data-page-title>{{ __('treasure_chest.title') }}</h1>
        <p>{{ __('treasure_chest.intro') }}</p>
    </header>
    <div class="tc-balance"><i class="ph-thin ph-key" aria-hidden="true"></i><span>{{ __('treasure_chest.total_keys') }}</span><strong data-total-keys>{{ $activity['total_keys'] }}</strong></div>
    <a class="tc-history-link" href="{{ route('treasure-chest.history') }}"><i class="ph-thin ph-clock-counter-clockwise" aria-hidden="true"></i>{{ __('treasure_chest.key_history') }}<i class="ph-thin ph-caret-right" aria-hidden="true"></i></a>
    <p class="tc-notice" data-closed @if($activity['enabled']) hidden @endif>{{ $activity['status_message'] }}</p>
    @if($activity['starts_at'] || $activity['ends_at'])
        <p class="tc-schedule">{{ $activity['starts_at'] }} — {{ $activity['ends_at'] ?: __('treasure_chest.no_end') }} ({{ $activity['timezone'] }})</p>
    @endif
    <div class="tc-grid">
        @foreach($activity['chests'] as $chest)
            <article class="tc-chest {{ $chest['number'] === 9 ? 'tc-chest-final' : '' }} {{ $chest['claimed'] ? 'is-claimed' : '' }} {{ $chest['claimable'] ? 'is-available' : '' }}" data-chest="{{ $chest['number'] }}">
                <h2>{{ $chest['number'] === 9 ? __('treasure_chest.ultimate') : __('treasure_chest.chest_number', ['number' => $chest['number']]) }}</h2>
                <div class="tc-display" aria-hidden="true">
                    <div class="tc-display-light"></div>
                    <div class="tc-sprite {{ $chest['number'] === 9 ? 'tc-sprite-ultimate' : '' }} {{ $chest['claimed'] ? 'tc-sprite-open' : '' }}"></div>
                    <span class="tc-seal"><i class="ph-thin {{ $chest['claimed'] ? 'ph-check' : ($chest['claimable'] ? 'ph-key' : 'ph-lock-key') }}"></i></span>
                </div>
                <p class="tc-threshold"><i class="ph-thin ph-key" aria-hidden="true"></i><span data-required-keys>{{ __('treasure_chest.required_keys', ['count' => $chest['threshold']]) }}</span></p>
                <ul class="tc-rewards" @if(!$activity['show_rewards']) hidden @endif>
                    @if($chest['amount'] > 0)<li>{{ number_format($chest['amount'], fmod((float) $chest['amount'], 1) ? 2 : 0) }} VND</li>@endif
                    @if($chest['keys'] > 0)<li>{{ __('treasure_chest.key_count', ['count' => $chest['keys']]) }}</li>@endif
                    @if($chest['discount_percent'] > 0)<li>{{ __('golden_egg.percent_off', ['percent' => (float) $chest['discount_percent']]) }}</li>@endif
                </ul>
                <button type="button" data-claim="{{ $chest['number'] }}" aria-label="{{ __('treasure_chest.chest_number', ['number' => $chest['number']]).' · '.__($chest['claimed'] ? 'treasure_chest.claimed' : ($chest['claimable'] ? 'treasure_chest.claim' : 'treasure_chest.locked')) }}" @disabled(!$chest['claimable'])>{{ __($chest['claimed'] ? 'treasure_chest.claimed' : ($chest['claimable'] ? 'treasure_chest.claim' : 'treasure_chest.locked')) }}</button>
            </article>
        @endforeach
    </div>
    <p class="tc-feedback" role="status" aria-live="polite" data-feedback></p>
    </div>
    <dialog class="tc-reveal" aria-labelledby="tc-reveal-title" aria-describedby="tc-reveal-status" data-reveal>
        <div class="tc-reveal-inner">
            <button type="button" class="tc-reveal-close" data-close-reveal aria-label="{{ __('golden_egg.close') }}"><i class="ph-thin ph-x" aria-hidden="true"></i></button>
            <p class="tc-reveal-number" data-reveal-number></p>
            <h2 id="tc-reveal-title">{{ __('treasure_chest.reward_title') }}</h2>
            <div class="tc-reveal-stage" aria-hidden="true">
                <div class="tc-reveal-rays"></div><div class="tc-reveal-glow"></div>
                <div class="tc-reveal-chest">
                    <div class="tc-sprite tc-frame-closed"></div>
                    <div class="tc-sprite tc-frame-ajar"></div>
                    <div class="tc-sprite tc-frame-open"></div>
                </div>
                <div class="tc-particles">@for($i = 0; $i < 20; $i++)<span></span>@endfor</div>
            </div>
            <div class="tc-reveal-result">
                <ul class="tc-reveal-rewards" data-reveal-rewards></ul>
                <p id="tc-reveal-status" data-reveal-status aria-live="polite"></p>
                <button type="button" class="tc-reveal-done" data-close-reveal>{{ __('treasure_chest.continue') }}</button>
            </div>
        </div>
    </dialog>
    <section class="tc-rules" id="tc-rules">
        <div class="tc-guide-layout">
            <section class="tc-guide" aria-labelledby="tc-guide-title">
                <h2 id="tc-guide-title">{{ __('treasure_chest.rules') }}</h2>
                <ul class="tc-guide-steps">
                    @foreach(['invite' => 'ph-users-three', 'keep' => 'ph-key', 'collect' => 'ph-gift'] as $step => $icon)
                        <li><span class="tc-guide-icon"><i class="ph-thin {{ $icon }}" aria-hidden="true"></i></span><div><h3>{{ __('treasure_chest.rule_'.$step.'_title') }}</h3><p>{{ __('treasure_chest.rule_'.$step.'_text') }}</p></div></li>
                    @endforeach
                </ul>
            </section>
            <section class="tc-invite-card" aria-labelledby="tc-invite-title">
                <div class="tc-invite-cover">
                    <img src="{{ asset('images/treasure-chest/invitation-banner.png') }}" alt="" width="1536" height="512" loading="lazy">
                    <h2 id="tc-invite-title">{{ __('treasure_chest.invite_title') }}</h2>
                </div>
                <div class="tc-invite-body">
                    <p>{{ __('treasure_chest.invite_hint') }}</p>
                    <label for="tc-invite">{{ __('golden_egg.your_link') }}</label>
                    <div class="tc-invite-field"><input id="tc-invite" readonly value="{{ $activity['invite_url'] }}"><button type="button" data-copy-invite><i class="ph-thin ph-copy" aria-hidden="true"></i><span>{{ __('treasure_chest.copy_link') }}</span></button></div>
                    <p class="tc-copy-feedback" data-copy-feedback role="status" aria-live="polite"></p>
                </div>
            </section>
        </div>
        <section class="tc-earn" aria-labelledby="tc-earn-title">
            <header class="tc-earn-heading"><div><h2 id="tc-earn-title">{{ __('treasure_chest.earn_keys') }}</h2><p>{{ __('treasure_chest.earn_hint') }}</p></div><i class="ph-thin ph-key" aria-hidden="true"></i></header>
            <ul class="tc-product-rewards">
                @foreach($activity['products'] as $product)
                    <li><div class="tc-product-detail"><h3>{{ $product['code'] }}</h3><span>{{ number_format($product['price'], fmod((float) $product['price'], 1) ? 2 : 0) }} <small>VND</small></span></div><div class="tc-product-keys"><i class="ph-thin ph-key" aria-hidden="true"></i><strong>{{ $product['keys'] }}</strong><span>{{ __('treasure_chest.keys') }}</span></div></li>
                @endforeach
            </ul>
            <footer class="tc-earn-footer"><span><i class="ph-thin ph-clock" aria-hidden="true"></i>{{ __('treasure_chest.purchase_time_note') }}</span><a href="{{ route('products.index') }}">{{ __('dashboard.view_products') }}<i class="ph-thin ph-arrow-up-right" aria-hidden="true"></i></a></footer>
        </section>
    </section>
    <script type="application/json" id="treasure-chest-config">{!! json_encode([
        'url' => route('treasure-chest.claim'), 'activity' => $activity,
        'labels' => collect(['claim', 'claimed', 'locked', 'claiming', 'success', 'retry', 'key_count', 'required_keys', 'chest_number', 'ultimate', 'link_copied', 'copy_manual'])->mapWithKeys(fn($key) => [$key => __('treasure_chest.'.$key)])
            ->put('percent_off', __('golden_egg.percent_off')),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
</section>
@endsection
