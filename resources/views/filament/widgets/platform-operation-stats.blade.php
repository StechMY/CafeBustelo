@php
    $stats = $this->getStats();

    $selected = $stats['selected'];
    $overview = $stats['overview'];

    $money = fn($amount) => \App\Support\Filament\Dashboard\PlatformDashboardQuery::money($amount);

    $selectedRows = [
        [
            [
                'label' => __('filament.operation_today_registered'),
                'value' => number_format((int) $selected['registered']),
                'desc' => __('filament.operation_selected_range'),
                'icon' => 'users',
                'tone' => 'blue',
            ],
            [
                'label' => __('filament.operation_first_recharge_users'),
                'value' => number_format((int) $selected['first_recharge_users']),
                'desc' => __('filament.operation_first_recharge_users_desc'),
                'icon' => 'users',
                'tone' => 'green',
            ],
            [
                'label' => __('filament.operation_transfer_referral_users'),
                'value' => number_format((int) $selected['transfer_referral_users']),
                'desc' => __('filament.operation_transfer_referral_users_desc'),
                'icon' => 'users',
                'tone' => 'purple',
            ],
            [
                'label' => __('filament.operation_valid_members'),
                'value' => number_format((int) $selected['valid_members']),
                'desc' => __('filament.operation_valid_members_desc'),
                'icon' => 'users',
                'tone' => 'green',
            ],
        ],
        [
            [
                'label' => __('filament.operation_transfer_referral_rate'),
                'value' => number_format((float) $selected['transfer_referral_rate'], 2) . '%',
                'desc' => __('filament.operation_transfer_referral_rate_desc'),
                'icon' => 'chart',
                'tone' => 'red',
            ],
            [
                'label' => __('filament.operation_today_upgrade_amount'),
                'value' => $money($selected['upgrade_amount']),
                'desc' => __('filament.operation_today_upgrade_amount_desc'),
                'icon' => 'money',
                'tone' => 'blue',
            ],
            [
                'label' => __('filament.operation_today_recharge'),
                'value' => $money($selected['recharge']),
                'desc' => __('filament.operation_reviewed_at_selected_range'),
                'icon' => 'money',
                'tone' => 'green',
            ],
            [
                'label' => __('filament.operation_today_withdraw'),
                'value' => $money($selected['withdraw']),
                'desc' => __('filament.operation_reviewed_at_selected_range'),
                'icon' => 'money',
                'tone' => 'red',
            ],
        ],
    ];

    $overviewRows = [
        [
            [
                'label' => __('filament.operation_total_recharge'),
                'value' => $money($overview['total_recharge']),
                'desc' => __('filament.operation_cumulative_not_date_filtered'),
                'icon' => 'money',
                'tone' => 'green',
                'highlight' => true,
            ],
            [
                'label' => __('filament.operation_total_balance_alt'),
                'value' => $money($overview['total_balance']),
                'desc' => __('filament.operation_total_wallet_balance'),
                'icon' => 'money',
                'tone' => 'blue',
                'highlight' => true,
            ],
            [
                'label' => __('filament.operation_total_invest'),
                'value' => $money($overview['total_invest']),
                'desc' => __('filament.operation_cumulative_not_date_filtered'),
                'icon' => 'money',
                'tone' => 'purple',
                'highlight' => true,
            ],
            [
                'label' => __('filament.operation_total_withdraw'),
                'value' => $money($overview['total_withdraw']),
                'desc' => __('filament.operation_cumulative_not_date_filtered'),
                'icon' => 'money',
                'tone' => 'red',
                'highlight' => true,
            ],
        ],
        [
            [
                'label' => __('filament.operation_total_members'),
                'value' => number_format((int) $overview['total_members']),
                'desc' => __('filament.operation_cumulative_not_date_filtered'),
                'icon' => 'users',
                'tone' => 'blue',
                'highlight' => true,
            ],
            [
                'label' => __('filament.operation_total_orders'),
                'value' => number_format((int) $overview['total_orders']),
                'desc' => __('filament.operation_cumulative_not_date_filtered'),
                'icon' => 'orders',
                'tone' => 'gray',
                'highlight' => true,
            ],
            [
                'label' => __('filament.operation_total_order_commission'),
                'value' => $money($overview['total_order_commission']),
                'desc' => __('filament.operation_cumulative_not_date_filtered'),
                'icon' => 'money',
                'tone' => 'green',
            ],
            [
                'label' => __('filament.operation_today_orders'),
                'value' => number_format((int) $overview['today_orders']),
                'desc' => __('filament.operation_selected_range'),
                'icon' => 'orders',
                'tone' => 'gray',
            ],
        ],
        [
            [
                'label' => __('filament.operation_today_recharge'),
                'value' => $money($overview['today_recharge']),
                'desc' => __('filament.operation_reviewed_at_selected_range'),
                'icon' => 'money',
                'tone' => 'green',
            ],
            [
                'label' => __('filament.operation_today_first_recharge_users'),
                'value' => number_format((int) $overview['today_first_recharge_users']),
                'desc' => __('filament.operation_first_recharge_users_desc'),
                'icon' => 'users',
                'tone' => 'green',
            ],
            [
                'label' => __('filament.operation_today_withdraw'),
                'value' => $money($overview['today_withdraw']),
                'desc' => __('filament.operation_reviewed_at_selected_range'),
                'icon' => 'money',
                'tone' => 'red',
            ],
            [
                'label' => __('filament.operation_today_bonus'),
                'value' => $money($overview['today_bonus']),
                'desc' => __('filament.operation_bonus_desc'),
                'icon' => 'gift',
                'tone' => 'yellow',
            ],
        ],
        [
            [
                'label' => __('filament.operation_yesterday_same_time_recharge'),
                'value' => $money($overview['yesterday_recharge']),
                'desc' => __('filament.operation_yesterday_same_time_desc'),
                'icon' => 'money',
                'tone' => 'green',
            ],
            [
                'label' => __('filament.operation_yesterday_same_time_first_recharge_users'),
                'value' => number_format((int) $overview['yesterday_first_recharge_users']),
                'desc' => __('filament.operation_yesterday_same_time_desc'),
                'icon' => 'users',
                'tone' => 'green',
            ],
            [
                'label' => __('filament.operation_yesterday_withdraw'),
                'value' => $money($overview['yesterday_withdraw']),
                'desc' => __('filament.operation_yesterday_same_time_desc'),
                'icon' => 'money',
                'tone' => 'red',
            ],
            [
                'label' => __('filament.operation_yesterday_bonus'),
                'value' => $money($overview['yesterday_bonus']),
                'desc' => __('filament.operation_bonus_desc'),
                'icon' => 'gift',
                'tone' => 'yellow',
            ],
        ],
        [
            [
                'label' => __('filament.operation_before_yesterday_same_time_recharge'),
                'value' => $money($overview['before_yesterday_recharge']),
                'desc' => __('filament.operation_before_yesterday_same_time_desc'),
                'icon' => 'money',
                'tone' => 'green',
            ],
            [
                'empty' => true,
            ],
            [
                'label' => __('filament.operation_before_yesterday_withdraw'),
                'value' => $money($overview['before_yesterday_withdraw']),
                'desc' => __('filament.operation_before_yesterday_same_time_desc'),
                'icon' => 'money',
                'tone' => 'red',
            ],
            [
                'empty' => true,
            ],
        ],
        [
            [
                'label' => __('filament.operation_pending_withdraw_amount'),
                'value' => $money($overview['pending_withdraw_amount']),
                'desc' => __('filament.operation_pending_withdraw_amount_desc'),
                'icon' => 'money',
                'tone' => 'yellow',
                'highlight' => true,
            ],
            [
                'label' => __('filament.operation_today_registered'),
                'value' => number_format((int) $overview['today_registered']),
                'desc' => __('filament.operation_selected_range'),
                'icon' => 'users',
                'tone' => 'blue',
            ],
            [
                'label' => __('filament.operation_valid_members'),
                'value' => number_format((int) $overview['valid_members']),
                'desc' => __('filament.operation_valid_members_desc'),
                'icon' => 'users',
                'tone' => 'green',
                'highlight' => true,
            ],
            [
                'label' => __('filament.operation_invalid_members'),
                'value' => number_format((int) $overview['invalid_members']),
                'desc' => __('filament.operation_total_members_minus_valid'),
                'icon' => 'users',
                'tone' => 'red',
                'highlight' => true,
            ],
        ],
        [
            [
                'label' => __('filament.operation_expired_product_members'),
                'value' => number_format((int) $overview['expired_product_member_count']),
                'desc' => __('filament.operation_expired_product_members_desc'),
                'icon' => 'users',
                'tone' => 'yellow',
                'highlight' => true,
            ],
            [
                'label' => __('filament.operation_yesterday_registered'),
                'value' => number_format((int) $overview['yesterday_registered']),
                'desc' => __('filament.operation_yesterday_same_time_desc'),
                'icon' => 'users',
                'tone' => 'blue',
            ],
            [
                'empty' => true,
            ],
            [
                'empty' => true,
            ],
        ],
        [
            [
                'label' => __('filament.operation_total_balance'),
                'value' => $money($overview['summary_balance']),
                'desc' => __('filament.operation_total_wallet_balance'),
                'icon' => 'money',
                'tone' => 'blue',
                'highlight' => true,
            ],
            [
                'label' => __('filament.operation_invalid_members_total_recharge'),
                'value' => $money($overview['invalid_members_total_recharge']),
                'desc' => __('filament.operation_invalid_members_desc'),
                'icon' => 'money',
                'tone' => 'green',
            ],
            [
                'label' => __('filament.operation_invalid_members_total_withdraw'),
                'value' => $money($overview['invalid_members_total_withdraw']),
                'desc' => __('filament.operation_invalid_members_desc'),
                'icon' => 'money',
                'tone' => 'red',
            ],
            [
                'empty' => true,
            ],
        ],
    ];
@endphp

<x-filament-widgets::widget>
    <style>
        .ops-modern {
            width: 100%;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .06);
        }

        .ops-modern summary {
            list-style: none;
            cursor: pointer;
        }

        .ops-modern summary::-webkit-details-marker {
            display: none;
        }

        .ops-modern-head {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px 22px;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        }

        .ops-modern-head:hover {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        .ops-main-icon {
            width: 48px;
            height: 48px;
            flex: 0 0 48px;
            display: grid;
            place-items: center;
            border-radius: 16px;
            background: #003b7a;
            color: #ffffff;
            font-size: 24px;
            font-weight: 900;
            box-shadow: 0 8px 18px rgba(0, 59, 122, .22);
        }

        .ops-head-text {
            min-width: 0;
            flex: 1;
        }

        .ops-head-title {
            margin: 0;
            font-size: 17px;
            line-height: 1.3;
            font-weight: 900;
            color: #0f172a;
        }

        .ops-head-desc {
            margin-top: 5px;
            max-width: 820px;
            font-size: 13px;
            line-height: 1.55;
            color: #64748b;
        }

        .ops-arrow {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            display: grid;
            place-items: center;
            border-radius: 999px;
            background: #f1f5f9;
            color: #475569;
            transition: .18s ease;
        }

        .ops-modern[open] .ops-arrow {
            transform: rotate(180deg);
            background: #003b7a;
            color: #ffffff;
        }

        .ops-body {
            border-top: 1px solid #e5e7eb;
            padding: 20px;
            background: #f8fafc;
        }

        .ops-section+.ops-section {
            margin-top: 24px;
        }

        .ops-section-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }

        .ops-section-title {
            font-size: 14px;
            font-weight: 900;
            color: #0f172a;
        }

        .ops-section-badge {
            border-radius: 999px;
            background: #e0f2fe;
            color: #0369a1;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 800;
        }

        .ops-arrangement {
            overflow: visible;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            background: #ffffff;
            position: relative;
            z-index: 1;
        }

        .ops-row {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            position: relative;
            z-index: 1;
        }

        .ops-cell {
            position: relative;
            min-height: 104px;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 18px 20px;
            border-right: 1px solid #e5e7eb;
            background: #ffffff;
            transition: .16s ease;
            z-index: 1;
        }

        .ops-cell:hover {
            z-index: 999;
            transform: translateY(-2px);
            background: #f8fafc;
            box-shadow: 0 18px 36px rgba(15, 23, 42, .14);
        }

        .ops-row+.ops-row {
            border-top: 1px solid #e5e7eb;
        }


        .ops-cell:nth-child(4n) {
            border-right: 0;
        }



        .ops-cell-empty {
            background: #fbfdff;
        }

        .ops-cell-empty:hover {
            transform: none;
            box-shadow: none;
            background: #fbfdff;
        }

        .ops-icon {
            width: 52px;
            height: 52px;
            flex: 0 0 52px;
            display: grid;
            place-items: center;
            border-radius: 16px;
            color: #ffffff;
            font-size: 25px;
            font-weight: 900;
            box-shadow: 0 8px 18px rgba(15, 23, 42, .12);
        }

        .ops-icon svg {
            width: 27px;
            height: 27px;
            stroke-width: 1.9;
        }

        .ops-tone-blue .ops-icon {
            background: linear-gradient(135deg, #2563eb, #003b7a);
        }

        .ops-tone-green .ops-icon {
            background: linear-gradient(135deg, #16a34a, #047857);
        }

        .ops-tone-red .ops-icon {
            background: linear-gradient(135deg, #e4003a, #991b1b);
        }

        .ops-tone-yellow .ops-icon {
            background: linear-gradient(135deg, #f59e0b, #b45309);
        }

        .ops-tone-purple .ops-icon {
            background: linear-gradient(135deg, #8b5cf6, #5b21b6);
        }

        .ops-tone-gray .ops-icon {
            background: linear-gradient(135deg, #64748b, #334155);
        }

        .ops-content {
            min-width: 0;
            flex: 1;
        }

        .ops-value {
            max-width: 100%;
            font-size: 20px;
            line-height: 1.1;
            font-weight: 950;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ops-label {
            margin-top: 5px;
            font-size: 13px;
            line-height: 1.28;
            font-weight: 800;
            color: #334155;
        }

        .ops-highlight .ops-value {
            color: #b91c1c;
        }

        .ops-desc-tooltip {
            pointer-events: none;
            position: absolute;
            left: 14px;
            right: 14px;
            bottom: calc(100% + 10px);
            z-index: 9999;
            opacity: 0;
            transform: translateY(8px);
            border-radius: 14px;
            background: #0f172a;
            color: #ffffff;
            padding: 11px 13px;
            box-shadow: 0 18px 35px rgba(15, 23, 42, .24);
            transition: .16s ease;
        }

        .ops-desc-tooltip::after {
            content: "";
            position: absolute;
            left: 24px;
            bottom: -6px;
            width: 12px;
            height: 12px;
            background: #0f172a;
            transform: rotate(45deg);
        }

        .ops-tooltip-value {
            font-size: 14px;
            line-height: 1.25;
            font-weight: 950;
            color: #ffffff;
            word-break: break-word;
        }

        .ops-tooltip-desc {
            margin-top: 6px;
            border-top: 1px solid rgba(255, 255, 255, .14);
            padding-top: 6px;
            font-size: 12px;
            line-height: 1.45;
            font-weight: 700;
            color: rgba(255, 255, 255, .82);
        }

        .ops-cell:hover .ops-desc-tooltip {
            opacity: 1;
            transform: translateY(0);
        }


        .ops-mobile-desc {
            display: none;
        }

        @media (max-width: 1280px) {
            .ops-row {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .ops-cell:nth-child(2n) {
                border-right: 0;
            }

            .ops-cell:nth-child(4n) {
                border-right: 0;
            }

            .ops-row+.ops-row {
                border-top: 0;
            }

            .ops-cell {
                border-bottom: 1px solid #e5e7eb;
            }
        }

        @media (max-width: 640px) {
            .ops-modern-head {
                align-items: flex-start;
                padding: 16px;
            }

            .ops-main-icon {
                width: 42px;
                height: 42px;
                flex-basis: 42px;
                border-radius: 14px;
                font-size: 21px;
            }

            .ops-value-tooltip {
                display: none;
            }

            .ops-head-title {
                font-size: 15px;
            }

            .ops-head-desc {
                font-size: 12px;
            }

            .ops-body {
                padding: 12px;
            }

            .ops-section-top {
                align-items: flex-start;
                flex-direction: column;
            }

            .ops-row {
                grid-template-columns: 1fr;
            }

            .ops-cell {
                min-height: 86px;
                padding: 14px;
                border-right: 0;
                border-bottom: 1px solid #e5e7eb;
            }

            .ops-icon {
                width: 44px;
                height: 44px;
                flex-basis: 44px;
                border-radius: 14px;
                font-size: 21px;
            }

            .ops-value {
                font-size: 18px;
            }

            .ops-label {
                font-size: 12px;
            }

            .ops-desc-tooltip {
                display: none;
            }

            .ops-mobile-desc {
                display: block;
                margin-top: 4px;
                font-size: 11px;
                line-height: 1.35;
                color: #64748b;
            }

            .ops-cell-empty {
                display: none;
            }
        }
    </style>

    <details class="ops-modern">
        <summary>
            <div class="ops-modern-head">
                <div class="ops-main-icon">
                    📊
                </div>

                <div class="ops-head-text">
                    <div class="ops-head-title">
                        {{ __('filament.operation_stats') }}
                    </div>

                    <div class="ops-head-desc">
                        {{ __('filament.operation_stats_desc') }}
                    </div>
                </div>

                <div class="ops-arrow">
                    ▼
                </div>
            </div>
        </summary>

        <div class="ops-body">
            <div class="ops-section">
                <div class="ops-section-top">
                    <div class="ops-section-title">
                        {{ __('filament.operation_selected_range_stats') }}
                    </div>

                    <div class="ops-section-badge">
                        {{ __('filament.date_filter_applied') }}
                    </div>
                </div>

                <div class="ops-arrangement">
                    @foreach ($selectedRows as $row)
                        <div class="ops-row">
                            @foreach ($row as $item)
                                @include('filament.widgets.partials.operation-stat-cell', [
                                    'item' => $item,
                                ])
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="ops-section">
                <div class="ops-section-top">
                    <div class="ops-section-title">
                        {{ __('filament.operation_customer_dashboard_style') }}
                    </div>

                    <div class="ops-section-badge" style="background:#fffbeb;color:#b45309;">
                        {{ __('filament.not_affected_by_date_filter') }}
                    </div>
                </div>

                <div class="ops-arrangement">
                    @foreach ($overviewRows as $row)
                        <div class="ops-row">
                            @foreach ($row as $item)
                                @include('filament.widgets.partials.operation-stat-cell', [
                                    'item' => $item,
                                ])
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </details>
</x-filament-widgets::widget>
