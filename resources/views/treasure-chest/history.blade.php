@extends('layouts.app')
@section('title', __('treasure_chest.key_history'))
@section('content')
<section class="tc-page">
    <nav class="tc-page-nav"><a href="{{ route('treasure-chest.index') }}"><i class="ph-thin ph-arrow-left" aria-hidden="true"></i>{{ __('treasure_chest.title') }}</a></nav>
    <section class="tc-guide tc-history" aria-labelledby="tc-history-title">
        <header>
            <h1 id="tc-history-title">{{ __('treasure_chest.key_history') }}</h1>
            <p>{{ __('treasure_chest.history_hint') }}</p>
            <small>{{ config('app.timezone') }}</small>
        </header>
        <ul class="tc-history-list">
            @forelse($history as $entry)
                <li>
                    <span class="tc-guide-icon"><i class="ph-thin {{ $entry['source'] === 'purchase' ? 'ph-shopping-bag' : ($entry['source'] === 'chest' ? 'ph-gift' : 'ph-key') }}" aria-hidden="true"></i></span>
                    <div class="tc-history-detail">
                        <h2>{{ __('treasure_chest.history_'.$entry['source']) }}</h2>
                        @if($entry['source'] === 'purchase')
                            <p>{{ __('treasure_chest.member') }}: <strong>{{ $entry['username'] ?? __('treasure_chest.history_unavailable') }}</strong></p>
                            <p>{{ __('treasure_chest.product') }}: {{ $entry['product_code'] ?? $entry['product_name'] ?? __('treasure_chest.history_unavailable') }}</p>
                        @elseif($entry['source'] === 'chest')
                            <p>{{ __('treasure_chest.chest_number', ['number' => $entry['chest_number']]) }}</p>
                        @endif
                        <time datetime="{{ $entry['created_at'] }}">{{ \Illuminate\Support\Carbon::parse($entry['created_at'])->format('Y-m-d H:i:s') }}</time>
                    </div>
                    <strong class="tc-history-keys">+{{ $entry['keys'] }}<small>{{ __('treasure_chest.keys') }}</small></strong>
                </li>
            @empty
                <li class="tc-history-empty">{{ __('treasure_chest.history_empty') }}</li>
            @endforelse
        </ul>
        {{ $history->links() }}
    </section>
</section>
@endsection
