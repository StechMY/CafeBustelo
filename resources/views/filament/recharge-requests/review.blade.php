<div class="space-y-3">
    <p><strong>{{ __('filament.recharge_request') }}:</strong> #{{ $record->id }}</p>
    <p><strong>{{ __('filament.username') }}:</strong> {{ $record->user?->username }} · {{ $record->user?->name }} · {{ $record->user?->phone }}</p>
    <p><strong>{{ __('filament.amount') }}:</strong> {{ config('app.currency') }} {{ number_format((float) $record->amount, 2) }}</p>
    <p><strong>{{ __('recharge.payment_information') }}:</strong> {{ $record->payment_bank_name }} · {{ $record->payment_account_name }} · {{ $record->payment_account_no }}</p>
    <p><strong>{{ __('filament.reference_no') }}:</strong> {{ $record->reference_no ?: '-' }}</p>
    @if ($record->receipt_path)
        <a href="{{ $record->receipt_url }}" target="_blank" rel="noopener noreferrer" class="underline">{{ __('recharge.view_receipt') }}</a>
    @else
        <p>{{ __('recharge.no_receipt') }}</p>
    @endif
</div>
