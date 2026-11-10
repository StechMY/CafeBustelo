@extends('layouts.app')
@section('title', __('red_packet.title'))
@section('content')
<section class="rp-page" data-red-packet>
    <div class="rp-hero">
        <nav class="rp-nav" aria-label="{{ __('red_packet.title') }}">
            <a href="{{ route('dashboard') }}"><span aria-hidden="true">‹</span>{{ __('red_packet.back') }}</a>
            <a href="{{ route('red-packet.history') }}">{{ __('red_packet.history') }}<span aria-hidden="true">↗</span></a>
        </nav>
        <header class="rp-intro">
            <h1>{{ __('red_packet.title') }}</h1>
            <p>{{ __('red_packet.intro') }}</p>
        </header>
        <div class="rp-play">
            <div class="rp-stage">
                <div class="rp-halo" aria-hidden="true"></div>
                <span class="rp-satellite rp-satellite--left rp-envelope-frame rp-envelope-closed" aria-hidden="true"></span>
                <span class="rp-satellite rp-satellite--right rp-envelope-frame rp-envelope-closed" aria-hidden="true"></span>
                <div class="rp-opening-glow" data-rp-glow aria-hidden="true"></div>
                <button class="rp-packet" type="button" data-rp-open aria-label="{{ __('red_packet.open') }}" @disabled(!$activity['enabled'] || (!$activity['is_virtual'] && !$activity['remaining_chances']))>
                    <span class="rp-envelope-frame rp-envelope-closed" data-rp-closed-frame aria-hidden="true"></span>
                    <span class="rp-envelope-frame rp-envelope-open" data-rp-open-frame aria-hidden="true"></span>
                </button>
                <div class="rp-rain" aria-hidden="true">@for($i = 0; $i < 14; $i++)<span class="rp-envelope-frame rp-envelope-closed" style="--i:{{ $i }}"></span>@endfor</div>
            </div>
            <div class="rp-chance-ticket"><span class="rp-ticket-mark" aria-hidden="true"></span><span>{{ __('red_packet.chances') }}</span><strong data-rp-chances>{{ $activity['is_virtual'] ? random_int(1, 10) : $activity['remaining_chances'] }}</strong></div>
            <button class="rp-open-button" type="button" data-rp-open @disabled(!$activity['enabled'] || (!$activity['is_virtual'] && !$activity['remaining_chances']))>{{ __('red_packet.open') }}</button>
            <p class="rp-small">{{ __('red_packet.one_chance') }}</p>
            <p class="rp-status" data-rp-status role="status" aria-live="polite">{{ !$activity['enabled'] ? __('red_packet.closed') : (!$activity['is_virtual'] && !$activity['remaining_chances'] ? __('red_packet.no_chances') : '') }}</p>
            <button type="button" class="rp-text-button" data-rp-retry hidden>{{ __('red_packet.retry_button') }}</button>
        </div>
        <div class="rp-schedule">{{ $activity['starts_at'] }} — {{ $activity['ends_at'] ?: __('red_packet.no_end') }}<span>{{ $activity['timezone'] }}</span></div>
    </div>
    <div class="rp-content">
        <section class="rp-progress" id="rp-progress">
            <div class="rp-section-heading"><div><h2>{{ __('red_packet.progress_title') }}</h2><p>{{ __('red_packet.progress_count', ['count' => $activity['today_invites']]) }}</p></div><span class="rp-progress-count">{{ min(5, $activity['today_invites']) }}<small>/ 5</small></span></div>
            <div class="rp-invite-track" aria-hidden="true">@for($i = 1; $i <= 5; $i++)<span class="{{ $activity['today_invites'] >= $i ? 'is-complete' : '' }}">{{ $i }}</span>@endfor</div>
            <div class="rp-milestones">@foreach([3, 5] as $target)<div class="rp-milestone {{ $activity['today_invites'] >= $target ? 'is-complete' : '' }}"><span class="rp-art rp-art--gift" aria-hidden="true"></span><strong>{{ __('red_packet.milestone_'.$target) }}</strong><span class="rp-milestone-state">{{ $activity['today_invites'] >= $target ? '✓' : min($target, $activity['today_invites']).' / '.$target }}</span></div>@endforeach</div>
            <p class="rp-daily-note">{{ __('red_packet.daily_note') }} ({{ $activity['timezone'] }})</p>
        </section>
        <section class="rp-prizes" aria-labelledby="rp-prizes-title">
            <div class="rp-section-heading"><h2 id="rp-prizes-title">{{ __('red_packet.prizes_title') }}</h2><span class="rp-heading-rule" aria-hidden="true"></span></div>
            <ul>@foreach($activity['prizes'] as $prize)
                @php($art = $prize['type'] === 'cash' ? 'cash' : ($prize['type'] === 'coupon' ? 'coupon' : match((int) $prize['slot']) { 1 => 'phone', 2 => 'washer', 3 => 'earbuds', default => 'gift' }))
                <li class="rp-prize-card"><span class="rp-art rp-art--{{ $art }}" aria-hidden="true"></span><div class="rp-prize-caption"><span class="rp-prize-kind">{{ __('red_packet.'.$prize['type']) }}</span><h3>{{ $prize['name'] }}</h3>@if($prize['type'] === 'physical' && $prize['amount'] > 0)<p>{{ number_format($prize['amount'], 0) }} VND</p>@endif</div></li>
            @endforeach</ul>
        </section>
        <section class="rp-guide">
            <div class="rp-rules"><h2>{{ __('red_packet.rules_title') }}</h2><div><section><h3>{{ __('red_packet.invite') }}</h3><p>{{ __('red_packet.invite_rule') }}</p></section></div><div><section><h3>{{ __('red_packet.upgrade') }}</h3><p>{{ __('red_packet.upgrade_rule') }}</p></section></div></div>
            <div class="rp-invite"><span class="rp-invite-art rp-envelope-frame rp-envelope-closed" aria-hidden="true"></span><h3>{{ __('red_packet.invite_title') }}</h3><label for="rp-invite-link">{{ __('red_packet.your_link') }}</label><input id="rp-invite-link" readonly value="{{ $activity['invite_url'] }}"><button type="button" data-rp-copy>{{ __('red_packet.copy_link') }}</button><p data-rp-copy-status role="status"></p></div>
        </section>
    </div>
    <dialog class="rp-result" data-rp-result aria-labelledby="rp-result-title">
        <div class="rp-result-inner"><div class="rp-result-art"><span class="rp-art rp-art--gift" data-rp-result-art aria-hidden="true"></span></div><h2 id="rp-result-title">{{ __('red_packet.reward_title') }}</h2><strong data-rp-prize></strong><p data-rp-reward-note></p><button type="button" class="rp-open-button" data-rp-close>{{ __('red_packet.close') }}</button><a href="{{ route('red-packet.history') }}">{{ __('red_packet.history') }}</a></div>
    </dialog>
    <script type="application/json" id="red-packet-config">{!! json_encode([
        'url' => route('red-packet.draw'), 'user_id' => auth()->id(), 'enabled' => $activity['enabled'], 'is_virtual' => $activity['is_virtual'], 'remaining_chances' => $activity['remaining_chances'],
        'ends_at' => $activity['ends_at'] ? \Illuminate\Support\Carbon::parse($activity['ends_at'], $activity['timezone'])->toIso8601String() : null,
        'prize_artwork' => collect($activity['prizes'])->mapWithKeys(fn ($prize) => [$prize['name'] => $prize['type'] === 'cash' ? 'cash' : ($prize['type'] === 'coupon' ? 'coupon' : match((int) $prize['slot']) { 1 => 'phone', 2 => 'washer', 3 => 'earbuds', default => 'gift' })]),
        'labels' => collect(['opening', 'open', 'closed', 'no_chances', 'retry', 'cash_received', 'coupon_received', 'physical_received'])->mapWithKeys(fn ($key) => [$key => __('red_packet.'.$key)])
            ->put('copied', __('red_packet.link_copied'))->put('copy_manual', __('red_packet.copy_manual')),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
</section>
@endsection
