<div class="otp-records-panel">
    <style>
        .otp-records-panel {
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #0f172a;
        }

        .otp-notice {
            margin-bottom: 14px;
            border: 1px solid #fde68a;
            border-radius: 14px;
            background: #fffbeb;
            padding: 12px 14px;
            color: #92400e;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.5;
        }

        .otp-table-wrap {
            overflow-x: auto;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06);
        }

        .otp-table {
            width: 100%;
            min-width: 820px;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }

        .otp-table thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            padding: 11px 12px;
            color: #475569;
            font-size: 11px;
            font-weight: 900;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: .04em;
            white-space: nowrap;
        }

        .otp-table tbody td {
            border-bottom: 1px solid #f1f5f9;
            padding: 11px 12px;
            vertical-align: middle;
            color: #334155;
            white-space: nowrap;
        }

        .otp-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .otp-table tbody tr:hover {
            background: #f8fafc;
        }

        .otp-phone {
            font-weight: 900;
            color: #003b7a;
        }

        .otp-purpose {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            background: #e0f2fe;
            padding: 4px 9px;
            color: #0369a1;
            font-size: 11px;
            font-weight: 900;
        }

        .otp-code {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 68px;
            border-radius: 10px;
            background: #0f172a;
            padding: 6px 10px;
            color: #ffffff;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 15px;
            font-weight: 950;
            letter-spacing: .08em;
        }

        .otp-code-empty {
            color: #94a3b8;
            font-size: 12px;
            font-weight: 700;
        }

        .otp-status {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 9px;
            font-size: 11px;
            font-weight: 900;
        }

        .otp-status-valid {
            background: #dcfce7;
            color: #15803d;
        }

        .otp-status-used {
            background: #e5e7eb;
            color: #374151;
        }

        .otp-status-expired {
            background: #fee2e2;
            color: #b91c1c;
        }

        .otp-date {
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
        }

        .otp-empty {
            padding: 26px 12px !important;
            text-align: center;
            color: #94a3b8 !important;
            font-weight: 800;
        }

        @media (max-width: 640px) {
            .otp-notice {
                font-size: 12px;
            }

            .otp-table {
                min-width: 760px;
                font-size: 12px;
            }

            .otp-table thead th,
            .otp-table tbody td {
                padding: 9px 10px;
            }
        }
    </style>

    <div class="otp-notice">
        {{ __('filament.phone_otp_records_notice') }}
    </div>

    <div class="otp-table-wrap">
        <table class="otp-table">
            <thead>
                <tr>
                    <th>{{ __('filament.phone') }}</th>
                    <th>{{ __('filament.purpose') }}</th>
                    <th>{{ __('filament.otp_code') }}</th>
                    <th>{{ __('filament.status') }}</th>
                    <th>{{ __('filament.expires_at') }}</th>
                    <th>{{ __('filament.created_at') }}</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($records as $record)
                    @php
                        $status = $record->status_label;
                        $statusKey = strtolower($status);
                    @endphp

                    <tr>
                        <td>
                            <span class="otp-phone">
                                {{ $record->phone }}
                            </span>
                        </td>

                        <td>
                            <span class="otp-purpose">
                                {{ $record->purpose }}
                            </span>
                        </td>

                        <td>
                            @if ($record->decrypted_code)
                                <span class="otp-code">
                                    {{ $record->decrypted_code }}
                                </span>
                            @else
                                <span class="otp-code-empty">
                                    {{ __('filament.not_available') }}
                                </span>
                            @endif
                        </td>

                        <td>
                            <span class="otp-status otp-status-{{ $statusKey }}">
                                {{ __('filament.otp_status_' . $statusKey) }}
                            </span>
                        </td>

                        <td>
                            <span class="otp-date">
                                {{ $record->expires_at?->format('Y-m-d H:i:s') ?? '-' }}
                            </span>
                        </td>

                        <td>
                            <span class="otp-date">
                                {{ $record->created_at?->format('Y-m-d H:i:s') ?? '-' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="otp-empty">
                            {{ __('filament.no_records_found') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>