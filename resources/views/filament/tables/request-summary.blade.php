<div class="p-4" data-request-summary>
    <h3 class="font-semibold">{{ __('request-summary.heading') }}</h3>
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('request-summary.scope') }}</p>
    <dl style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-top: 0.75rem;">
        <div>
            <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('request-summary.records') }}</dt>
            <dd class="font-semibold">{{ number_format($summary->records) }}</dd>
        </div>
        <div>
            <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('request-summary.amount') }}</dt>
            <dd class="font-semibold">{{ config('app.currency') }} {{ number_format($summary->amount, 2) }}</dd>
        </div>
        @if ($withdrawal)
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('request-summary.fee') }}</dt>
                <dd class="font-semibold">{{ config('app.currency') }} {{ number_format($summary->fee, 2) }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('request-summary.net') }}</dt>
                <dd class="font-semibold">{{ config('app.currency') }} {{ number_format($summary->net, 2) }}</dd>
            </div>
        @endif
    </dl>
    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('request-summary.approval_hint') }}</p>
</div>
