@extends('layouts.app')
@section('title', __('red_packet.history'))
@section('content')
<section class="rp-history-page" data-rp-history>
    <header class="rp-history-hero">
        <a class="rp-back" href="{{ route('red-packet.index') }}"><span aria-hidden="true">‹</span>{{ __('red_packet.back_to_event') }}</a>
        <span class="rp-history-decoration rp-envelope-frame rp-envelope-open" aria-hidden="true"></span>
        <h1>{{ __('red_packet.history') }}</h1><p>{{ __('red_packet.history_intro') }}</p>
        <div class="rp-history-totals"><span>{{ __('red_packet.draw_records') }}<strong>{{ $draws->total() }}</strong></span><span>{{ __('red_packet.entries_title') }}<strong>{{ $entries->total() }}</strong></span></div>
    </header>
    <div class="rp-ledger">
        <nav class="rp-history-tabs" aria-label="{{ __('red_packet.history') }}"><a href="#rp-wins-panel" id="rp-wins-tab" data-rp-tab>{{ __('red_packet.draw_records') }}<span>{{ $draws->total() }}</span></a><a href="#rp-entries-panel" id="rp-entries-tab" data-rp-tab>{{ __('red_packet.entries_title') }}<span>{{ $entries->total() }}</span></a></nav>
        <section class="rp-ledger-panel" id="rp-wins-panel" aria-labelledby="rp-wins-tab" data-rp-panel>
            <h2 class="rp-ledger-heading">{{ __('red_packet.draw_records') }}<small>{{ config('app.timezone') }}</small></h2>
            <ul class="rp-records">@forelse($draws as $draw)
                <li class="rp-record"><span class="rp-record-art rp-art rp-art--{{ $draw->prize_type === 'physical' ? 'gift' : $draw->prize_type }}" aria-hidden="true"></span><div class="rp-record-body"><span class="rp-record-kind">{{ __('red_packet.'.$draw->prize_type) }}</span><h3>{{ $draw->prize_name }}</h3><p>{{ __('red_packet.'.$draw->prize_type.'_received') }}</p><time datetime="{{ $draw->created_at->toIso8601String() }}">{{ $draw->created_at->format('d M Y · H:i') }}</time></div><span class="rp-record-status">{{ __('red_packet.recorded') }}</span></li>
            @empty
                <li class="rp-empty"><span class="rp-art rp-art--gift" aria-hidden="true"></span><h3>{{ __('red_packet.empty_wins') }}</h3><p>{{ __('red_packet.empty') }}</p><a href="{{ route('red-packet.index') }}">{{ __('red_packet.back_to_event') }}</a></li>
            @endforelse</ul>
            <div class="rp-pagination">{{ $draws->appends(['entries_page' => $entries->currentPage()])->fragment('rp-wins-panel')->links() }}</div>
        </section>
        <section class="rp-ledger-panel" id="rp-entries-panel" aria-labelledby="rp-entries-tab" data-rp-panel>
            <h2 class="rp-ledger-heading">{{ __('red_packet.entries_title') }}<small>{{ config('app.timezone') }}</small></h2>
            <ul class="rp-records">@forelse($entries as $entry)
                <li class="rp-record rp-record--entry"><span class="rp-earned" aria-label="+1"><span class="rp-envelope-frame rp-envelope-closed" aria-hidden="true"></span><b>+1</b></span><div class="rp-record-body"><h3>{{ __('red_packet.'.$entry->source.(str_starts_with($entry->source, 'milestone') ? '_source' : '')) }}</h3><div class="rp-record-details">@if($entry->invitee)<span><small>{{ __('red_packet.member') }}</small>{{ $entry->invitee->username }}</span>@endif @if($entry->purchase)<span><small>{{ __('red_packet.product') }}</small>{{ $entry->purchase->meta['product_code_snapshot'] ?? $entry->purchase->meta['product_name_snapshot'] ?? '—' }}</span>@endif</div><time datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->format('d M Y · H:i') }}</time></div><span class="rp-record-status {{ $entry->used_at ? 'is-used' : 'is-ready' }}">{{ __('red_packet.'.($entry->used_at ? 'used' : 'ready')) }}</span></li>
            @empty
                <li class="rp-empty"><span class="rp-envelope-frame rp-envelope-closed" aria-hidden="true"></span><h3>{{ __('red_packet.empty_entries') }}</h3><p>{{ __('red_packet.no_chances') }}</p><a href="{{ route('red-packet.index') }}">{{ __('red_packet.back_to_event') }}</a></li>
            @endforelse</ul>
            <div class="rp-pagination">{{ $entries->appends(['draws_page' => $draws->currentPage()])->fragment('rp-entries-panel')->links() }}</div>
        </section>
    </div>
</section>
@endsection
