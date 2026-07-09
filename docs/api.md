# API Documentation

Base path: `/api/v1`

All responses use:

```json
{
  "success": true,
  "message": "Optional message",
  "data": {}
}
```

Errors use:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field": ["Error message"]
  }
}
```

Authenticated endpoints require:

```http
Authorization: Bearer <token>
Accept: application/json
```

Dates are ISO-8601 strings. Money fields are numeric floats and often include a formatted field using `config('app.currency')`.

## Routes

| Method | Endpoint | Auth | Purpose |
| --- | --- | --- | --- |
| POST | `/api/v1/register` | No | Register user and return Sanctum token |
| POST | `/api/v1/register/send-otp` | No | Send registration phone OTP |
| POST | `/api/v1/login` | No | Login by username/password and return Sanctum token |
| POST | `/api/v1/logout` | Yes | Delete current Sanctum token |
| GET | `/api/v1/me` | Yes | Current user |
| GET | `/api/v1/app-config` | No | App config, labels, module flags |
| GET | `/api/v1/dashboard` | Yes | Dashboard data |
| GET | `/api/v1/profile` | Yes | Profile, wallets, VIP, stats, SVIP |
| POST | `/api/v1/profile` | Yes | Update `name` and/or `phone` |
| POST | `/api/v1/profile/avatar` | Yes | Upload avatar file |
| DELETE | `/api/v1/profile/avatar` | Yes | Delete avatar |
| GET | `/api/v1/products` | Yes | Product list with purchase state |
| POST | `/api/v1/products/{product}/purchase` | Yes | Purchase product |
| GET | `/api/v1/user-products` | Yes | Purchased products |
| POST | `/api/v1/user-products/{userProduct}/claim` | Yes | Claim product income |
| GET | `/api/v1/wallet` | Yes | Screen-ready wallet balances, summaries, filters, and paginated transactions |
| GET | `/api/v1/wallet/transactions` | Yes | Legacy paginated wallet transactions |
| GET | `/api/v1/recharge` | Yes | Recharge wallet, methods, requests |
| POST | `/api/v1/recharge` | Yes | Submit recharge request |
| GET | `/api/v1/withdraw` | Yes | Withdraw wallet, bank account, requests |
| POST | `/api/v1/withdraw` | Yes | Submit withdraw request |
| POST | `/api/v1/bank-account/send-otp` | Yes | Send bank account binding phone OTP |
| POST | `/api/v1/bank-account` | Yes | Bind user bank account after OTP verification |
| GET | `/api/v1/my-team` | Yes | Web-aligned My Team B/C/D overview |
| GET | `/api/v1/my-team/{level}` | Yes | Web-aligned My Team level members for `b`, `c`, or `d` |
| GET | `/api/v1/check-in` | Yes | Check-in state and records |
| POST | `/api/v1/check-in` | Yes | Claim daily check-in |
| GET | `/api/v1/salary` | Yes | Salary claim state and records |
| POST | `/api/v1/salary/claim` | Yes | Claim weekly/monthly/annual salary |
| GET | `/api/v1/bonus-codes` | Yes | Bonus codes |
| POST | `/api/v1/bonus-codes/redeem` | Yes | Redeem bonus code by code |
| GET | `/api/v1/contact` | No | Active support contacts |
| GET | `/api/v1/calculator` | Yes | Product and commission calculator data |
| GET | `/api/v1/points` | Yes | Points state and transactions |
| POST | `/api/v1/points/redeem` | Yes | Redeem points to withdrawable wallet |
| GET | `/api/v1/downline-products` | Yes | Downline product groups |
| GET | `/api/v1/svip` | Yes | SVIP packages, active purchase, history |
| POST | `/api/v1/svip/{svipPackage}/purchase` | Yes | Purchase SVIP |

## Request Examples

### Register

Registration requires SMS OTP. Flutter must first call:

```http
POST /api/v1/register/send-otp
```

```json
{
  "phone": "60123456789"
}
```

Success:

```json
{
  "success": true,
  "message": "OTP has been sent.",
  "data": {
    "resend_seconds": 60,
    "expires_in_minutes": 5
  }
}
```

Then submit registration with `otp_code`:

```http
POST /api/v1/register
```

```json
{
  "name": "Jane",
  "username": "jane123",
  "phone": "60123456789",
  "otp_code": "123456",
  "referral_code": "ABCD1234",
  "password": "password123",
  "password_confirmation": "password123",
  "device_name": "flutter"
}
```

Verifies OTP purpose `register`, creates wallets, welcome bonus, login log, and returns a Sanctum token. Invite-day points are given on registration unless the admin setting requires a qualifying first approved recharge.

### Login

```json
{
  "username": "jane123",
  "password": "password123",
  "device_name": "flutter"
}
```

### Product Purchase

```http
POST /api/v1/products/1/purchase
```

```json
{
  "wallet_type": "recharge"
}
```

Uses `ProductPurchaseService`. Backend validates product status, order status, purchase window, stock, purchase limit, and wallet balance.

### Claim Product Income

```http
POST /api/v1/user-products/1/claim
```

Uses `DailyIncomeClaimService`. Hourly products are supported through `claim_interval_hours`.

### Recharge

`POST /api/v1/recharge` uses multipart form data:

| Field | Type | Required |
| --- | --- | --- |
| amount | number | yes |
| recharge_method_id | integer | yes |
| reference_no | string | no |
| receipt | image file | yes |

When the admin setting `invite_day_require_first_deposit` is enabled, approving the invited user's first recharge can give invite-day points to the referrer. The first approved recharge must be at least `invite_day_first_deposit_min_amount`; if the first approved recharge is below the minimum, later recharges do not trigger the invite-day bonus.

### Withdraw

```json
{
  "amount": 100
}
```

Uses `WithdrawService`. Backend validates withdraw status, active account, active bank account, VIP minimum, fee, and balance lock.

### Bank Account Binding

Bank account binding requires an authenticated user and SMS OTP.

```http
POST /api/v1/bank-account/send-otp
```

```json
{
  "phone": "60123456789"
}
```

Success includes `resend_seconds` for the Flutter countdown and `expires_in_minutes` for UI copy.

Then bind the bank account:

```http
POST /api/v1/bank-account
```

```json
{
  "bank_account_name": "Customer Name",
  "bank_name": "Maybank",
  "bank_account_no": "1234567890",
  "phone": "60123456789",
  "otp_code": "123456"
}
```

The API normalizes phone and bank account number, verifies OTP purpose `bank_account`, stores `user_bank_accounts.phone`, sets `phone_verified_at`, locks the account, and returns the bound bank account. Duplicate bank account name or number returns 422 validation JSON instead of 500.

### Salary Claim

```json
{
  "type": "weekly"
}
```

Valid types: `weekly`, `monthly`, `annual`.

### Bonus Redeem

```json
{
  "code": "INV-ABCD-EFGH"
}
```

### Points Redeem

```json
{
  "points": 10
}
```

Uses `PointService`. Formula: `cash_amount = points * points_to_cash_rate`.

### SVIP Purchase

```json
{
  "wallet_type": "withdrawable"
}
```

Uses `SvipService`.

## Pagination

Paginated sections use:

```json
{
  "items": [],
  "pagination": {
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "last_page": 7
  }
}
```

Supported query filters:

- `GET /wallet`: `wallet_type`, `direction`, `type`, `start_date`, `end_date`, `page`, `per_page`
- `GET /wallet/transactions`: `wallet_type`, `direction`, `type`, `start_date`, `end_date`, `page`, `per_page`
- `GET /bonus-codes`: `status`, `type`, `page`, `per_page`
- Most history endpoints: `page`, `per_page`

## Wallet Response

`GET /api/v1/wallet` is the screen-ready wallet endpoint. Flutter should not recalculate filtered totals or current-page totals.

Supported filters:

- `wallet_type`: `all`, `recharge`, `withdrawable`
- `direction`: `all`, `credit`, `debit`
- `type`: transaction type or `all`
- `start_date`: `YYYY-MM-DD`, applied from start of day
- `end_date`: `YYYY-MM-DD`, applied through end of day and must be after or equal to `start_date`
- `page`, `per_page`

Response data:

```json
{
  "success": true,
  "data": {
    "wallets": {
      "recharge": {
        "balance": 100,
        "formatted_balance": "RM 100.00"
      },
      "withdrawable": {
        "balance": 50,
        "formatted_balance": "RM 50.00"
      }
    },
    "summary": {
      "lifetime": {
        "total_credit": 1000,
        "display_total_credit": 5000,
        "is_total_credit_overridden": true,
        "formatted_total_credit": "RM 5,000.00",
        "total_debit": 200,
        "display_total_debit": 900,
        "is_total_debit_overridden": true,
        "formatted_total_debit": "RM 900.00"
      },
      "all_filtered": {
        "total": 800,
        "credit": 1000,
        "debit": 200,
        "net": 800,
        "formatted_total": "RM 800.00",
        "formatted_credit": "RM 1,000.00",
        "formatted_debit": "RM 200.00",
        "formatted_net": "RM 800.00"
      },
      "current_page": {
        "total": 120,
        "credit": 150,
        "debit": 30,
        "net": 120,
        "formatted_total": "RM 120.00",
        "formatted_credit": "RM 150.00",
        "formatted_debit": "RM 30.00",
        "formatted_net": "RM 120.00"
      }
    },
    "filters": {
      "wallet_type": "all",
      "direction": "all",
      "type": "all",
      "start_date": "2026-06-01",
      "end_date": "2026-06-25"
    },
    "transactions": [],
    "meta": {
      "current_page": 1,
      "per_page": 15,
      "total": 100,
      "last_page": 7
    }
  }
}
```

- `summary.lifetime` is not affected by wallet table filters and applies `users.display_wallet_total_credit` / `users.display_wallet_total_debit`.
- `summary.all_filtered` is calculated across every transaction matching the current filters before pagination.
- `summary.current_page` is calculated only from transactions returned in the current page.
- `transactions[].created_at_label` is provided for direct display.

## My Team Response

The My Team API follows the web routes `/my-team` and `/my-team/{level}`. It only exposes B/C/D because the web route only allows `b`, `c`, and `d`.

- B = direct referrals of the current user.
- C = referrals under B users.
- D = referrals under C users.
- Do not use dashboard transfer-referral/deeper-level logic for this screen.

`GET /api/v1/my-team` returns the same overview data used by the web My Team index: invite URL, effective display-overridden stats, active commission rates, and B/C/D card counts.

```json
{
  "success": true,
  "data": {
    "invite_url": "https://example.com/register?ref=ABCDEFGH",
    "stats": {
      "total_team_commission": 0,
      "formatted_total_team_commission": "RM 0.00",
      "total_valid_users": 0,
      "total_users": 0,
      "team_accumulated_recharge": 0,
      "formatted_team_accumulated_recharge": "RM 0.00",
      "b_count": 0,
      "c_count": 0,
      "d_count": 0,
      "b_active_count": 0,
      "c_active_count": 0,
      "d_active_count": 0,
      "today_joined_count": 0,
      "yesterday_joined_count": 0,
      "today_recharge": 0,
      "formatted_today_recharge": "RM 0.00",
      "yesterday_recharge": 0,
      "formatted_yesterday_recharge": "RM 0.00",
      "this_month_recharge": 0,
      "formatted_this_month_recharge": "RM 0.00",
      "last_month_recharge": 0,
      "formatted_last_month_recharge": "RM 0.00"
    },
    "levels": {
      "b": {
        "level": "b",
        "level_number": 1,
        "label": "B",
        "rate": 5,
        "formatted_rate": "5.00%",
        "count": 0,
        "active_count": 0,
        "url": "https://example.com/api/v1/my-team/b"
      }
    }
  }
}
```

`GET /api/v1/my-team/{level}` supports `level=b|c|d`, `status=all|active|inactive`, and `page`. Pagination matches web at 30 members per page.

The member payload mirrors the web level page: username, masked phone last five digits, status, highest product, and joined date.

```json
{
  "success": true,
  "data": {
    "level": "b",
    "level_number": 1,
    "level_label": "B",
    "status": "all",
    "commission_rate": {
      "level": 1,
      "level_label": "B",
      "rate": 5,
      "formatted_rate": "5.00%"
    },
    "summary": {
      "count": 1,
      "active_count": 1,
      "inactive_count": 0
    },
    "data": [
      {
        "id": 10,
        "username": "user001",
        "name": "User Name",
        "phone_last_5": "45678",
        "masked_phone": "*****45678",
        "status": "active",
        "highest_product": null,
        "created_at_label": "25 Jun 2026"
      }
    ],
    "meta": {
      "current_page": 1,
      "per_page": 30,
      "total": 1,
      "last_page": 1
    }
  }
}
```

`GET /api/v1/my-team/e` is not a valid route.

## Reused Services

- `ProductPurchaseService`
- `DailyIncomeClaimService`
- `WithdrawService`
- `CheckInService`
- `SalaryService`
- `BonusCodeService`
- `PointService`
- `SvipService`
- `WalletService` indirectly through those services

## Business Rules

- Product purchase and SVIP purchase support `wallet_type: recharge|withdrawable`.
- SMS OTP is server-side only. Flutter never receives `SMS_APP_KEY`, `SMS_APP_CODE`, or `SMS_APP_SECRET`.
- OTP purpose names are `register` and `bank_account`.
- OTP codes are 6 digits, stored hashed, expire by `sms.otp_expire_minutes`, enforce resend cooldown by `sms.otp_resend_seconds`, and enforce failed attempts by `sms.otp_max_attempts`.
- Product commission estimates are based on claim income, not product price.
- Downline product commission formula:
  `commission_per_claim = income_per_claim * rate / 100`
  `estimated_full_commission = income_per_claim * total_claims * rate / 100`
  `claimed_commission_estimate = income_per_claim * claimed_count * rate / 100`
- Points redemption formula is from `PointService`:
  `cash_amount = points * points_to_cash_rate`.
- Backend remains source of truth for all availability, balance, claim, salary, withdraw, and SVIP rules.

## Downline Product Response

`GET /api/v1/downline-products` returns one paginated group per B/C/D downline user. Products are nested inside each group. Pagination applies to groups, not individual products.

```json
{
  "success": true,
  "data": {
    "summary": {
      "total_users": 1,
      "total_products": 2,
      "total_sales": 1000,
      "total_estimated_commission": 15,
      "total_claimed_commission_estimate": 5,
      "b_users": 1,
      "c_users": 0,
      "d_users": 0,
      "b_products": 2,
      "c_products": 0,
      "d_products": 0,
      "b_estimated_commission": 15,
      "c_estimated_commission": 0,
      "d_estimated_commission": 0
    },
    "data": [
      {
        "user_id": 10,
        "username": "user001",
        "name": "User Name",
        "phone": "0123456789",
        "level": 1,
        "level_label": "B",
        "rate": 5,
        "product_count": 2,
        "total_sales": 1000,
        "total_income": 300,
        "total_estimated_commission": 15,
        "total_claimed_commission_estimate": 5,
        "latest_purchase_at": "2026-06-25T02:00:00.000000Z",
        "latest_purchase_at_label": "25 Jun 2026, 10:00 AM",
        "products": [
          {
            "product_name": "Product A",
            "product_code": "P001",
            "status": "active",
            "price": 500,
            "income_per_claim": 10,
            "total_claims": 30,
            "claimed_count": 10,
            "claim_interval_hours": 24,
            "estimated_total_income": 300,
            "rate": 5,
            "commission_per_claim": 0.5,
            "claimed_commission_estimate": 5,
            "earning": 15,
            "estimated_full_cycle_commission": 15,
            "purchased_at": "2026-06-25T02:00:00.000000Z",
            "purchased_at_label": "25 Jun 2026, 10:00 AM"
          }
        ]
      }
    ],
    "meta": {
      "current_page": 1,
      "per_page": 12,
      "total": 1,
      "last_page": 1
    }
  }
}
```

- Parse groups from `data.data`.
- Commission uses claim income (`daily_income_snapshot`), never product price.
- `earning` equals `estimated_full_cycle_commission`.
- `claimed_commission_estimate` covers already claimed income only.
- A positive `total_claims_snapshot` is used directly. Otherwise total claims are derived from duration and effective claim interval.
- `claim_interval_hours` supports hourly products.
- Web and API responses use the same `DownlineProductReportService`.

## Display Overrides

Admin users can set nullable `users.display_*` fields in Filament. When an override is present, API responses retain the real database value and add the value Flutter should display:

```json
{
  "total_credit": 1200,
  "display_total_credit": 5000,
  "is_total_credit_overridden": true,
  "formatted_total_credit": "RM 5,000.00"
}
```

For overridden money fields:

- `<field>` is the real calculated value.
- `display_<field>` is the effective UI value.
- `is_<field>_overridden` indicates whether the user override is populated.
- `formatted_<field>` formats the effective display value.

For integer fields, the same structure is returned without a formatted money field.

| Override Source | Current Web/Admin Use | Updated API Endpoint / Field |
| --- | --- | --- |
| `display_vip_level` | `User::currentVipLevel`, `SalaryService`, Profile VIP | `/me`, `/dashboard`, `/profile`, `/salary`: raw and display VIP objects/numbers |
| `display_active_team_count` | Profile and Salary active team | `/dashboard`, `/profile`, `/salary`: `active_team_count` or `active_team_size` |
| `display_accumulated_recharge` | Profile and Salary accumulated recharge | `/me`, `/dashboard`, `/profile`, `/salary`: `accumulated_recharge` |
| `display_total_earning` | Profile total earning | `/profile`: `total_earning` |
| `display_total_withdrawals` | Profile total withdrawals | `/profile`: `total_withdrawals` |
| `display_total_team_commission` | My Team total commission | `/my-team`: `stats.total_team_commission` |
| `display_total_team_recharge` | My Team accumulated recharge | `/my-team`: `stats.team_accumulated_recharge` |
| `display_total_users_count` | My Team total users | `/my-team`: `stats.total_users` |
| `display_valid_users_count` | My Team valid users | `/my-team`: `stats.total_valid_users` |
| `display_b_count` | My Team B count | `/my-team`: `stats.b_count` |
| `display_b_active_count` | My Team active B count | `/my-team`: `stats.b_active_count` |
| `display_c_count` | My Team C count | `/my-team`: `stats.c_count` |
| `display_c_active_count` | My Team active C count | `/my-team`: `stats.c_active_count` |
| `display_d_count` | My Team D count | `/my-team`: `stats.d_count` |
| `display_d_active_count` | My Team active D count | `/my-team`: `stats.d_active_count` |
| `display_income_today` | Profile income today | `/profile`: `income_today`; `/dashboard`: `today_daily_income` |
| `display_yesterday_earnings` | Profile yesterday earnings | `/profile`: `yesterday_earnings` |
| `display_team_income` | Profile team income | `/profile`: `team_income`; `/dashboard`: `total_commission` |
| `display_daily_income_total` | Profile daily-income total | `/profile`: `daily_income_total`; `/dashboard`: `total_daily_income` |
| `display_salary_income_total` | Profile salary-income total | `/profile`: `salary_income_total` |
| `display_today_joined_count` | My Team today joined | `/my-team`: `stats.today_joined_count` |
| `display_yesterday_joined_count` | My Team yesterday joined | `/my-team`: `stats.yesterday_joined_count` |
| `display_today_recharge` | My Team today recharge | `/my-team`: `stats.today_recharge` |
| `display_yesterday_recharge` | My Team yesterday recharge | `/my-team`: `stats.yesterday_recharge` |
| `display_this_month_recharge` | My Team current-month recharge | `/my-team`: `stats.this_month_recharge` |
| `display_last_month_recharge` | My Team previous-month recharge | `/my-team`: `stats.last_month_recharge` |
| `display_wallet_total_credit` | Wallet lifetime total credit | `/wallet`: `summary.lifetime.total_credit` |
| `display_wallet_total_debit` | Wallet lifetime total debit | `/wallet`: `summary.lifetime.total_debit` |

No override fields were found for individual products, user products, recharge requests, withdraw requests, points, bonus codes, SVIP purchases, or downline-product rows. Those endpoints continue returning real values.

The current `/api/v1` surface does not expose the Filament admin dashboard. Existing Filament dashboard widgets use their own admin/account scopes and virtual-user filters, but they do not read the user `display_*` fields listed above. No new admin override behavior was introduced.

## Open Questions

- The current web profile page only supports avatar updates. The API `POST /profile` safely supports `name` and `phone`; no web password/security-password profile update flow exists.
- No existing web security password rule was found for withdraw. The API follows current `WithdrawService`.
