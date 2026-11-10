@extends('layouts.app')
@section('title', __('red_packet.history'))
@section('content')
<section class="rp-history-page">
    <nav><a href="{{ route('red-packet.index') }}"><i class="ph-thin ph-arrow-left" aria-hidden="true"></i> {{ __('red_packet.title') }}</a></nav>
    <h1>{{ __('red_packet.history') }}</h1><p>{{ config('app.timezone') }}</p>
    <section><h2>{{ __('red_packet.draw_records') }}</h2><ul>
        @forelse($draws as $draw)
            <li><i class="ph-thin ph-gift" aria-hidden="true"></i><div><strong>{{ $draw->prize_name }}</strong><p>{{ __('red_packet.'.$draw->prize_type.'_received') }}</p><time>{{ $draw->created_at->format('Y-m-d H:i:s') }}</time></div></li>
        @empty<li>{{ __('red_packet.empty') }}</li>@endforelse
    </ul>{{ $draws->appends(['entries_page' => $entries->currentPage()])->links() }}</section>
    <section><h2>{{ __('red_packet.entries_title') }}</h2><ul>
        @forelse($entries as $entry)
            <li><strong class="rp-earned">+1</strong><div><strong>{{ __('red_packet.'.$entry->source.(str_starts_with($entry->source, 'milestone') ? '_source' : '')) }}</strong>@if($entry->invitee)<p>{{ __('red_packet.member') }}: {{ $entry->invitee->username }}</p>@endif @if($entry->purchase)<p>{{ __('red_packet.product') }}: {{ $entry->purchase->meta['product_code_snapshot'] ?? $entry->purchase->meta['product_name_snapshot'] ?? '—' }}</p>@endif<time>{{ $entry->created_at->format('Y-m-d H:i:s') }}</time></div><small>{{ __($entry->used_at ? 'filament.used' : 'filament.unused') }}</small></li>
        @empty<li>{{ __('red_packet.empty') }}</li>@endforelse
    </ul>{{ $entries->appends(['draws_page' => $draws->currentPage()])->links() }}</section>
</section>
@endsection
