<style>
    [data-request-summary] {
        --summary-muted: #4b5563;
        --summary-surface: #f9fafb;
        --summary-border: #e5e7eb;
        padding: 1.25rem;
        color: #111827;
        line-height: 1.5;
    }

    .dark [data-request-summary] {
        --summary-muted: #9ca3af;
        --summary-surface: #1f2937;
        --summary-border: #374151;
        color: #f9fafb;
    }

    [data-request-summary] h3 {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
    }

    [data-request-summary] p {
        margin: 0.375rem 0 0;
        color: var(--summary-muted);
        font-size: 0.8125rem;
        overflow-wrap: anywhere;
    }

    [data-request-summary] dl {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 180px), 1fr));
        gap: 0.75rem;
        margin: 1rem 0;
    }

    [data-request-summary] dl > div {
        min-width: 0;
        padding: 0.875rem 1rem;
        border: 1px solid var(--summary-border);
        border-radius: 0.5rem;
        background: var(--summary-surface);
    }

    [data-request-summary] dt {
        color: var(--summary-muted);
        font-size: 0.8125rem;
    }

    [data-request-summary] dd {
        margin: 0.25rem 0 0;
        font-size: 1.125rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        overflow-wrap: anywhere;
    }
</style>

<div data-request-summary>
    <h3>{{ __('request-summary.heading') }}</h3>
    <p>{{ __('request-summary.scope') }}</p>
    <dl>
        <div>
            <dt>{{ __('request-summary.records') }}</dt>
            <dd>{{ number_format($summary->records) }}</dd>
        </div>
        <div>
            <dt>{{ __('request-summary.amount') }}</dt>
            <dd>{{ config('app.currency') }} {{ number_format($summary->amount, 2) }}</dd>
        </div>
        @if ($withdrawal)
            <div>
                <dt>{{ __('request-summary.fee') }}</dt>
                <dd>{{ config('app.currency') }} {{ number_format($summary->fee, 2) }}</dd>
            </div>
            <div>
                <dt>{{ __('request-summary.net') }}</dt>
                <dd>{{ config('app.currency') }} {{ number_format($summary->net, 2) }}</dd>
            </div>
        @endif
    </dl>
    <p>{{ __('request-summary.approval_hint') }}</p>
</div>
