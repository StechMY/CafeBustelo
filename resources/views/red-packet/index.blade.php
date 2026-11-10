@extends('layouts.app')
@section('title', __('red_packet.title'))
@section('content')
<section class="rp-page" data-red-packet>
    <div class="rp-hero">
        <nav class="rp-nav"><a href="{{ route('dashboard') }}"><i class="ph-thin ph-arrow-left" aria-hidden="true"></i>{{ __('red_packet.back') }}</a><a href="{{ route('red-packet.history') }}">{{ __('red_packet.history') }}<i class="ph-thin ph-clock-counter-clockwise" aria-hidden="true"></i></a></nav>
        <div class="rp-hero-grid">
            <header class="rp-intro">
                <span class="rp-flourish" aria-hidden="true">✧</span>
                <h1>{{ __('red_packet.title') }}</h1>
                <p>{{ __('red_packet.intro') }}</p>
                <div class="rp-balance"><span>{{ __('red_packet.chances') }}</span><strong data-rp-chances>{{ $activity['is_virtual'] ? random_int(1, 10) : $activity['remaining_chances'] }}</strong></div>
                <a class="rp-history-inline" href="#rp-progress">{{ __('red_packet.rules_title') }}<i class="ph-thin ph-arrow-down" aria-hidden="true"></i></a>
            </header>
            <div class="rp-play">
                <div class="rp-stage">
                    <div class="rp-aura" aria-hidden="true"></div>
                    <button class="rp-packet" type="button" data-rp-open aria-label="{{ __('red_packet.open') }}" @disabled(!$activity['enabled'] || (!$activity['is_virtual'] && !$activity['remaining_chances']))>
                        <span class="rp-paper" aria-hidden="true"><span class="rp-paper-mark">✧</span></span>
                        <span class="rp-pocket" aria-hidden="true"></span><span class="rp-flap" aria-hidden="true"></span>
                        <span class="rp-seal" aria-hidden="true"><i class="ph-thin ph-gift"></i></span>
                    </button>
                    <div class="rp-rain" aria-hidden="true">@for($i = 0; $i < 16; $i++)<span style="--i:{{ $i }}"><i class="ph-thin ph-envelope-simple"></i></span>@endfor</div>
                </div>
                <button class="rp-open-button" type="button" data-rp-open @disabled(!$activity['enabled'] || (!$activity['is_virtual'] && !$activity['remaining_chances']))>{{ __('red_packet.open') }}<i class="ph-thin ph-sparkle" aria-hidden="true"></i></button>
                <p class="rp-small">{{ __('red_packet.one_chance') }}</p>
                <p class="rp-status" data-rp-status role="status" aria-live="polite">{{ !$activity['enabled'] ? __('red_packet.closed') : (!$activity['is_virtual'] && !$activity['remaining_chances'] ? __('red_packet.no_chances') : '') }}</p>
                <button type="button" class="rp-text-button" data-rp-retry hidden>{{ __('red_packet.retry_button') }}</button>
            </div>
        </div>
        <div class="rp-schedule"><i class="ph-thin ph-clock" aria-hidden="true"></i>{{ $activity['starts_at'] }} — {{ $activity['ends_at'] ?: __('red_packet.no_end') }} <span>{{ $activity['timezone'] }}</span></div>
    </div>
    <div class="rp-content">
        <section class="rp-progress" id="rp-progress">
            <div><h2>{{ __('red_packet.progress_title') }}</h2><p>{{ __('red_packet.progress_count', ['count' => $activity['today_invites']]) }}</p></div>
            <div class="rp-milestones">
                @foreach([3, 5] as $target)
                    <div class="rp-milestone {{ $activity['today_invites'] >= $target ? 'is-complete' : '' }}"><span class="rp-milestone-icon"><i class="ph-thin {{ $activity['today_invites'] >= $target ? 'ph-check' : 'ph-gift' }}" aria-hidden="true"></i></span><strong>{{ __('red_packet.milestone_'.$target) }}</strong><progress value="{{ min($target, $activity['today_invites']) }}" max="{{ $target }}" aria-label="{{ __('red_packet.milestone_'.$target) }}"></progress></div>
                @endforeach
            </div>
            <p class="rp-daily-note">{{ __('red_packet.daily_note') }} ({{ $activity['timezone'] }})</p>
        </section>
        <section class="rp-prizes" aria-labelledby="rp-prizes-title">
            <h2 id="rp-prizes-title">{{ __('red_packet.prizes_title') }}</h2>
            <ul>
                @foreach($activity['prizes'] as $prize)
                    <li><span class="rp-prize-art" aria-hidden="true"><i class="ph-thin {{ match((int) $prize['slot']) { 1 => 'ph-device-mobile-camera', 2 => 'ph-washing-machine', 3 => 'ph-headphones', 6 => 'ph-ticket', default => 'ph-money' } }}"></i></span><h3>{{ $prize['name'] }}</h3>@if($prize['type'] === 'physical' && $prize['amount'] > 0)<p>{{ number_format($prize['amount'], 0) }} VND</p>@endif @if(!$prize['available'])<small>{{ __('red_packet.unavailable') }}</small>@endif</li>
                @endforeach
            </ul>
        </section>
        <section class="rp-guide">
            <div><h2>{{ __('red_packet.rules_title') }}</h2><h3><i class="ph-thin ph-users" aria-hidden="true"></i>{{ __('red_packet.invite') }}</h3><p>{{ __('red_packet.invite_rule') }}</p><h3><i class="ph-thin ph-trend-up" aria-hidden="true"></i>{{ __('red_packet.upgrade') }}</h3><p>{{ __('red_packet.upgrade_rule') }}</p></div>
            <div class="rp-invite"><i class="ph-thin ph-envelope-open" aria-hidden="true"></i><h3>{{ __('red_packet.invite_title') }}</h3><label for="rp-invite-link">{{ __('red_packet.your_link') }}</label><input id="rp-invite-link" readonly value="{{ $activity['invite_url'] }}"><button type="button" data-rp-copy>{{ __('red_packet.copy_link') }}</button><p data-rp-copy-status role="status"></p></div>
        </section>
    </div>
    <dialog class="rp-result" data-rp-result aria-labelledby="rp-result-title">
        <div class="rp-result-inner"><span class="rp-result-symbol" aria-hidden="true">✧</span><h2 id="rp-result-title">{{ __('red_packet.reward_title') }}</h2><strong data-rp-prize></strong><p data-rp-reward-note></p><button type="button" class="rp-open-button" data-rp-close>{{ __('red_packet.close') }}</button><a href="{{ route('red-packet.history') }}">{{ __('red_packet.history') }}</a></div>
    </dialog>
    <script type="application/json" id="red-packet-config">{!! json_encode([
        'url' => route('red-packet.draw'), 'user_id' => auth()->id(), 'enabled' => $activity['enabled'], 'is_virtual' => $activity['is_virtual'], 'remaining_chances' => $activity['remaining_chances'],
        'ends_at' => $activity['ends_at'] ? \Illuminate\Support\Carbon::parse($activity['ends_at'], $activity['timezone'])->toIso8601String() : null,
        'labels' => collect(['opening', 'open', 'closed', 'no_chances', 'retry', 'cash_received', 'coupon_received', 'physical_received'])->mapWithKeys(fn ($key) => [$key => __('red_packet.'.$key)])
            ->put('copied', __('red_packet.link_copied'))->put('copy_manual', __('red_packet.copy_manual')),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
</section>
@endsection
