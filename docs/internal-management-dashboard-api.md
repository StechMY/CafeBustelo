# Internal Management Dashboard API

> This endpoint exposes aggregated management data. It must be reachable only by trusted
> server-side systems and must never be embedded in a public client, browser bundle, or mobile app.

## Endpoint

```http
GET /api/internal/v1/management-dashboard
```

The endpoint is read-only. Its successful response uses schema version `1.0` and is returned with
`Cache-Control: private, no-store`.

## Authentication

Send a dedicated Laravel Sanctum service-client token:

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

The token must:

- belong to an active `DashboardApiClient`;
- have the single required `dashboard:read` ability.

Ordinary customer and admin tokens cannot use this endpoint, even if they otherwise authenticate
through Sanctum. The endpoint does not use the current Filament admin or apply a support-admin
downline restriction.

Create a service client and one-time token with:

```bash
php artisan dashboard-api:create-client "Central Dashboard Production"
```

The plaintext token is printed once and is not stored in recoverable form. Copy it immediately to
the Central Dashboard's secret store. Do not put it in source control, application logs, browser
storage, screenshots, or support tickets.

## Query parameters

| Parameter | Format | Default | Notes |
| --- | --- | --- | --- |
| `date_from` | `Y-m-d` | Current application date | Inclusive, normalized to `00:00:00` |
| `date_to` | `Y-m-d` | Current application date | Inclusive, normalized to `23:59:59.999999` |
| `virtual_mode` | Enum | `exclude_virtual` | See definitions below |

The maximum inclusive range is 366 days. Invalid values, a reversed range, or a longer range return
HTTP `422`.

### Virtual modes

- `exclude_virtual`: include only users where `users.is_virtual = false`.
- `only_virtual`: include only users where `users.is_virtual = true`.
- `include_all`: do not filter on `users.is_virtual`.

The filter is applied to users and user-owned aggregates such as requests, wallets, products,
claims, and commissions. For dashboard compatibility, `sections.user_overview.virtual_users`
always reports the cumulative count of virtual users and ignores `virtual_mode`.

## Date semantics

All input boundaries and `generated_at` use `config('app.timezone')`.

- Approved recharge and withdrawal totals use `reviewed_at`.
- New users use `users.created_at`.
- Orders and product purchase volume use `user_products.purchased_at`.
- Pending requests use `created_at`; rejected requests use `reviewed_at`.
- Daily income, bonuses, and salaries use `claimed_at`.
- Paid commissions use `commission_records.created_at`.
- Expired user products use `expires_at`.
- Cumulative totals, balances, active products, and valid/invalid member counts are not limited by
  the selected dates unless the field description explicitly says otherwise.

For compatibility with the existing Filament dashboard,
`sections.operation_overview.today_registered` is the selected-range registration count despite its
legacy label.

## Response

Money is returned as JSON numbers rounded to two decimals. IDs and raw records are never returned.

```json
{
  "schema_version": "1.0",
  "platform": {
    "code": "cafebustelo",
    "name": "CafeBustelo"
  },
  "generated_at": "2026-07-30T18:00:00+08:00",
  "timezone": "Asia/Kuala_Lumpur",
  "currency": {
    "code": "MYR",
    "symbol": "RM"
  },
  "filters": {
    "date_from": "2026-07-28",
    "date_to": "2026-07-30",
    "virtual_mode": "exclude_virtual"
  },
  "common": {
    "cash_in": 235.0,
    "cash_out": 56.0,
    "net_cash_flow": 179.0,
    "new_users": 3,
    "total_users": 3,
    "order_count": 4
  },
  "sections": {
    "operation_selected": {
      "registered": 3,
      "first_recharge_users": 2,
      "transfer_referral_users": 2,
      "valid_members": 2,
      "transfer_referral_rate": 100.0,
      "upgrade_amount": 300.0,
      "recharge": 235.0,
      "withdraw": 56.0
    },
    "operation_overview": {
      "total_recharge": 240.0,
      "total_balance": 65.0,
      "total_invest": 500.0,
      "total_withdraw": 56.0,
      "total_members": 3,
      "total_orders": 4,
      "total_order_commission": 3.0,
      "today_orders": 2,
      "today_recharge": 125.0,
      "today_first_recharge_users": 1,
      "today_withdraw": 6.0,
      "today_bonus": 7.0,
      "yesterday_recharge": 110.0,
      "yesterday_first_recharge_users": 1,
      "yesterday_withdraw": 50.0,
      "yesterday_bonus": 0.0,
      "before_yesterday_recharge": 0.0,
      "before_yesterday_withdraw": 0.0,
      "pending_withdraw_amount": 20.0,
      "expired_product_member_count": 1,
      "today_registered": 3,
      "yesterday_registered": 1,
      "valid_members": 2,
      "invalid_members": 1,
      "summary_balance": 65.0,
      "invalid_members_total_recharge": 10.0,
      "invalid_members_total_withdraw": 6.0
    },
    "finance_overview": {
      "approved_recharge": 235.0,
      "approved_withdraw": 56.0,
      "revenue_cash_gap": 179.0,
      "product_purchase_volume": 500.0,
      "daily_income_paid": 12.0,
      "commissions_paid": 3.0,
      "bonus_paid": 7.0,
      "salary_subsidy_paid": 11.0
    },
    "request_overview": {
      "pending_recharge_count": 1,
      "pending_recharge_amount": 30.0,
      "pending_withdraw_count": 1,
      "pending_withdraw_amount": 20.0,
      "rejected_recharge_count": 1,
      "rejected_withdraw_count": 1
    },
    "user_overview": {
      "new_users": 3,
      "active_users": 3,
      "inactive_users": 0,
      "virtual_users": 1,
      "active_user_products": 3,
      "expired_user_products": 1,
      "recharge_wallet_balance": 40.0,
      "withdrawable_wallet_balance": 25.0
    },
    "finance_chart": {
      "labels": [
        "2026-07-28",
        "2026-07-29",
        "2026-07-30"
      ],
      "approved_recharge": [
        0.0,
        110.0,
        125.0
      ],
      "approved_withdraw": [
        0.0,
        50.0,
        6.0
      ],
      "net": [
        0.0,
        60.0,
        119.0
      ]
    }
  }
}
```

## Errors

| Status | Meaning |
| --- | --- |
| `401` | Token is missing, invalid, or expired |
| `403` | Token lacks `dashboard:read`, belongs to another tokenable type, or its client is inactive |
| `422` | One or more filters are invalid |
| `429` | The service client exceeded the rate limit |

Error responses use the existing API error structure:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "date_to": [
      "The date to field must be a date after or equal to date from."
    ]
  }
}
```

Responses never include stack traces, SQL, credentials, bank details, receipts, or raw model data.

## Rate limit

Each service client is limited to 60 requests per minute. A successful call updates the client's
`last_used_at` timestamp at most once every five minutes.

## Token rotation and revocation

Use a replacement-first rotation:

1. Create a new service client with `dashboard-api:create-client`.
2. Store the new token in the Central Dashboard secret store and deploy it.
3. Confirm successful calls with the replacement token.
4. Disable the old client by setting its `is_active` value to `false`.
5. Delete the old client's Sanctum tokens after the rollback window.

For an urgent revocation, immediately set the affected client's `is_active` value to `false`.
Disabling the client blocks all its tokens without requiring a cache flush. Use an audited
administrative database workflow or a controlled `php artisan tinker` session; never paste the
plaintext token into that session.

## Curl example

```bash
curl \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  "https://example.com/api/internal/v1/management-dashboard?date_from=2026-07-01&date_to=2026-07-30&virtual_mode=exclude_virtual"
```
