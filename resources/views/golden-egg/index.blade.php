@extends('layouts.app')
@section('title', __('golden_egg.title'))
@push('styles')
    <link rel="preload" as="image" href="{{ asset('images/golden-egg/vault-cabinet.webp') }}">
    <link rel="preload" as="image" href="{{ asset('images/golden-egg/vault-egg.webp') }}">
@endpush
@section('content')
<div class="ge-page" data-golden-egg>
    <dialog class="ge-game" data-game aria-labelledby="ge-game-title" tabindex="-1">
        <button type="button" class="ge-close ge-sound" data-toggle-sound aria-label="{{ __('golden_egg.sound_on') }}" aria-pressed="false"><i class="ph-thin ph-speaker-slash" aria-hidden="true"></i></button>
        <div class="ge-scene" data-scene>
            <header class="ge-game-header">
                <a class="ge-brand" href="{{ route('dashboard') }}" aria-label="{{ __('golden_egg.back') }}"><img src="{{ asset('cafe-bustelo-logo.svg') }}" alt="Café Bustelo" width="102" height="40"></a>
                <button type="button" class="ge-close" data-close-game aria-label="{{ __('golden_egg.close') }}"><i class="ph-thin ph-x" aria-hidden="true"></i></button>
            </header>
            <div class="ge-game-layout">
                <div class="ge-game-intro">
                    <h2 id="ge-game-title">@foreach (explode('\n', __('golden_egg.headline')) as $line){{ $line }}@unless($loop->last)<br>@endunless @endforeach</h2>
                    <p class="ge-game-subtitle">{{ __('golden_egg.intro') }}</p>
                </div>
                <div class="ge-stage" data-stage>
                    <img class="ge-cabinet" src="{{ asset('images/golden-egg/vault-cabinet.webp') }}" alt="" width="1140" height="1380" fetchpriority="high">
                    <div class="ge-eggs">
                        @for ($i = 1; $i <= 9; $i++)
                            <button class="ge-egg" type="button" data-egg="{{ $i }}" aria-label="{{ __('golden_egg.choose_egg', ['number' => $i]) }}">
                                <span class="ge-egg-body">
                                    <img class="ge-egg-frame ge-egg-sealed" src="{{ asset('images/golden-egg/vault-egg.webp') }}" alt="" width="1086" height="1448" draggable="false">
                                    <img class="ge-egg-frame ge-egg-crack-one" src="{{ asset('images/golden-egg/vault-egg-crack-1.webp') }}" alt="" width="1086" height="1448" draggable="false">
                                    <img class="ge-egg-frame ge-egg-crack-two" src="{{ asset('images/golden-egg/vault-egg-crack-2.webp') }}" alt="" width="1086" height="1448" draggable="false">
                                    <img class="ge-egg-frame ge-egg-shattered" src="{{ asset('images/golden-egg/vault-egg-shattered.webp') }}" alt="" width="1086" height="1448" draggable="false">
                                </span>
                            </button>
                        @endfor
                    </div>
                    <img class="ge-draw-glow" src="{{ asset('images/golden-egg/vault-glow.webp') }}" alt="" width="1254" height="1254" aria-hidden="true">
                    <img class="ge-hammer" src="{{ asset('images/golden-egg/vault-hammer.webp') }}" alt="" width="743" height="1380" aria-hidden="true">
                </div>
            </div>
            <footer class="ge-game-footer">
                <div class="ge-chance"><span>{{ __('golden_egg.chances') }}</span><strong data-chances>{{ $activity['is_virtual'] ? random_int(1, 10) : $activity['remaining_chances'] }}</strong><span>{{ __('golden_egg.chance_unit') }}</span></div>
                <div class="ge-feedback"><p class="ge-status" data-status role="status" aria-live="polite"></p><button class="ge-retry" type="button" data-retry hidden>{{ __('golden_egg.retry') }}</button></div>
                <button type="button" class="ge-gold-button ge-invite" data-invite>{{ __('golden_egg.invite') }} <i class="ph-thin ph-arrow-right" aria-hidden="true"></i></button>
                <nav class="ge-game-links" aria-label="{{ __('golden_egg.title') }}">
                    <button type="button" data-show-rewards><i class="ph-thin ph-gift" aria-hidden="true"></i><span>{{ __('golden_egg.my_prizes') }}</span></button>
                    <button type="button" data-show-rules><i class="ph-thin ph-file-text" aria-hidden="true"></i><span>{{ __('golden_egg.rules') }}</span></button>
                </nav>
                <div class="ge-invite-link" data-invite-link hidden><label for="ge-invite-url">{{ __('golden_egg.your_link') }}</label><input id="ge-invite-url" readonly value="{{ $activity['invite_url'] }}"></div>
            </footer>
        </div>
        <section class="ge-confirmation" data-confirmation hidden role="dialog" aria-modal="true" aria-labelledby="ge-confirm-title" aria-describedby="ge-confirm-note">
            <div class="ge-confirm-card">
                <img src="{{ asset('images/golden-egg/vault-egg.webp') }}" alt="" width="1086" height="1448">
                <span class="ge-eyebrow" data-confirm-egg-label></span>
                <h2 id="ge-confirm-title">{{ __('golden_egg.confirm_title') }}</h2>
                <p id="ge-confirm-note">{{ __($activity['is_virtual'] ? 'golden_egg.confirm_agent' : 'golden_egg.confirm_note') }}</p>
                <button type="button" class="ge-gold-button" data-confirm-draw>{{ __('golden_egg.confirm_yes') }} <i class="ph-thin ph-arrow-right" aria-hidden="true"></i></button>
                <button type="button" class="ge-text-button" data-cancel-confirmation>{{ __('golden_egg.confirm_cancel') }}</button>
            </div>
        </section>
        <section class="ge-sheet" data-sheet hidden role="dialog" aria-modal="true" aria-labelledby="ge-sheet-title">
            <div class="ge-sheet-card">
                <header class="ge-sheet-header"><h2 id="ge-sheet-title"></h2><button type="button" class="ge-close" data-close-sheet aria-label="{{ __('golden_egg.close') }}"><i class="ph-thin ph-x" aria-hidden="true"></i></button></header>
                <div class="ge-sheet-body">
                    <div data-prizes-panel>
                        <section>
                            <div class="ge-section-heading"><h3>{{ __('golden_egg.my_coupons') }}</h3><a href="{{ route('products.index') }}">{{ __('golden_egg.go_purchase') }} <i class="ph-thin ph-arrow-up-right" aria-hidden="true"></i></a></div>
                            <div class="ge-coupon-list" data-coupon-list>
                                @forelse ($activity['coupons'] as $coupon)
                                    <a class="ge-ticket" href="{{ route('products.index') }}"><i class="ph-thin ph-ticket" aria-hidden="true"></i><strong>{{ __('golden_egg.percent_off', ['percent' => (float) $coupon['discount_percent']]) }}</strong><span>{{ __('golden_egg.coupon_note') }}</span></a>
                                @empty
                                    <p class="ge-empty" data-coupon-empty>{{ __('golden_egg.coupons_empty') }}</p>
                                @endforelse
                            </div>
                        </section>
                        <section><div class="ge-section-heading"><h3>{{ __('golden_egg.history') }}</h3></div><div class="ge-history" data-history>
                            @forelse ($activity['history'] as $draw)
                                <div class="ge-history-item" data-draw-id="{{ $draw['id'] }}"><i class="ph-thin {{ $draw['type'] === 'cash' ? 'ph-wallet' : 'ph-ticket' }}" aria-hidden="true"></i><div><strong>{{ $draw['type'] === 'cash' ? config('app.currency').' '.number_format((float) $draw['amount'], 2) : __('golden_egg.percent_off', ['percent' => (float) $draw['discount_percent']]) }}</strong><span>{{ $draw['name'] }}</span></div><time datetime="{{ $draw['created_at'] }}">{{ \Illuminate\Support\Carbon::parse($draw['created_at'])->timezone(config('app.timezone'))->format('d/m H:i') }}</time></div>
                            @empty
                                <p class="ge-empty" data-history-empty>{{ __('golden_egg.history_empty') }}</p>
                            @endforelse
                        </div></section>
                        <section>
                            <div class="ge-section-heading"><h3>{{ __('golden_egg.rewards') }}</h3><p>{{ __('golden_egg.rewards_intro') }}</p></div>
                            <div class="ge-prize-list">
                                @foreach ($activity['prizes'] as $prize)
                                    <div class="ge-prize"><i class="ph-thin {{ $prize['type'] === 'cash' ? 'ph-wallet' : 'ph-ticket' }}" aria-hidden="true"></i><strong>{{ $prize['type'] === 'cash' ? config('app.currency').' '.number_format((float) $prize['amount'], 2) : __('golden_egg.percent_off', ['percent' => (float) $prize['discount_percent']]) }}</strong><span>{{ $prize['name'] }}</span></div>
                                @endforeach
                            </div>
                        </section>
                    </div>
                    <div data-rules-panel hidden><ol class="ge-rules">@foreach (['rule_purchase', 'rule_invite', 'rule_draw', 'rule_cash', 'rule_coupon'] as $rule)<li>{{ __('golden_egg.'.$rule) }}</li>@endforeach</ol></div>
                </div>
            </div>
        </section>
        <div class="ge-reveal" data-reveal hidden role="dialog" aria-modal="true" aria-labelledby="ge-reveal-title">
            <img class="ge-reveal-glow" src="{{ asset('images/golden-egg/vault-glow.webp') }}" alt="" width="1254" height="1254">
            <div class="ge-reveal-content">
                <p class="ge-eyebrow" id="ge-reveal-title">{{ __('golden_egg.congratulations') }}</p>
                <div class="ge-reveal-award">
                    <img class="ge-reveal-egg" src="{{ asset('images/golden-egg/vault-egg-shattered.webp') }}" alt="" width="1086" height="1448">
                    <div><span class="ge-reveal-type" data-result-type></span><strong class="ge-reveal-value" data-result-value></strong><h3 data-result-name></h3></div>
                </div>
                <p class="ge-reveal-note" data-result-note></p>
                <div class="ge-result-board">
                    <img class="ge-cabinet" src="{{ asset('images/golden-egg/vault-cabinet.webp') }}" alt="" width="1140" height="1380">
                    <div class="ge-result-cells" role="list" aria-label="{{ __('golden_egg.rewards') }}">
                        @for ($i = 1; $i <= 9; $i++)
                            <div class="ge-result-cell" data-result-cell role="listitem">
                                <span class="ge-result-winner" data-result-winner hidden>{{ __('golden_egg.your_prize') }}</span>
                                <i class="ph-thin" data-cell-icon aria-hidden="true"></i>
                                <strong data-cell-value></strong><span class="ge-result-type" data-cell-type></span>
                            </div>
                        @endfor
                    </div>
                </div>
                <button type="button" class="ge-gold-button" data-again>{{ __('golden_egg.again') }} <i class="ph-thin ph-arrow-right" aria-hidden="true"></i></button><button type="button" class="ge-text-button" data-finish>{{ __('golden_egg.done') }}</button>
            </div>
        </div>
    </dialog>
    <script type="application/json" id="golden-egg-config">{!! \Illuminate\Support\Js::encode([
        ...$activity, 'user_id' => auth()->id(), 'draw_url' => route('golden-egg.draw'),
        'currency' => config('app.currency'), 'purchase_url' => route('products.index'), 'dashboard_url' => route('dashboard'),
        'audio' => collect(['ambience' => 'ambience', 'hammer' => 'hammer-hit', 'crack' => 'shell-crack', 'reveal' => 'prize-reveal'])->map(fn ($file) => asset('audio/golden-egg/'.$file.'.mp3'))->all(),
        'labels' => collect(['preparing', 'cracking', 'closed', 'no_chances', 'sold_out', 'request_failed', 'copied', 'copy_failed', 'cash', 'coupon', 'cash_received', 'coupon_received', 'percent_off', 'coupon_note', 'my_prizes', 'rules', 'confirm_egg', 'your_prize', 'sound_on', 'sound_off'])->mapWithKeys(fn ($key) => [$key => __('golden_egg.'.$key)])->all(),
    ]) !!}</script>
</div>
@endsection
