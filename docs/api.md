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
| GET | `/api/v1/wallet` | Yes | Wallet balances and summary |
| GET | `/api/v1/wallet/transactions` | Yes | Paginated wallet transactions |
| GET | `/api/v1/recharge` | Yes | Recharge wallet, methods, requests |
| POST | `/api/v1/recharge` | Yes | Submit recharge request |
| GET | `/api/v1/withdraw` | Yes | Withdraw wallet, bank account, requests |
| POST | `/api/v1/withdraw` | Yes | Submit withdraw request |
| GET | `/api/v1/team` | Yes | B/C/D team data and stats |
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

```http
POST /api/v1/register
```

```json
{
  "name": "Jane",
  "username": "jane123",
  "phone": "60123456789",
  "referral_code": "ABCD1234",
  "password": "password123",
  "password_confirmation": "password123",
  "device_name": "flutter"
}
```

Creates wallets, welcome bonus, invite-day points, login log, and returns a Sanctum token.

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

### Withdraw

```json
{
  "amount": 100
}
```

Uses `WithdrawService`. Backend validates withdraw status, active account, active bank account, VIP minimum, fee, and balance lock.

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

- `GET /wallet/transactions`: `wallet_type`, `direction`, `type`, `page`, `per_page`
- `GET /bonus-codes`: `status`, `type`, `page`, `per_page`
- Most history endpoints: `page`, `per_page`

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
- Product commission estimates are based on claim income, not product price.
- Downline product commission formula:
  `commission_per_claim = income_per_claim * rate / 100`
  `estimated_full_commission = income_per_claim * total_claims * rate / 100`
  `claimed_commission_estimate = income_per_claim * claimed_count * rate / 100`
- Points redemption formula is from `PointService`:
  `cash_amount = points * points_to_cash_rate`.
- Backend remains source of truth for all availability, balance, claim, salary, withdraw, and SVIP rules.

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
| `display_total_team_commission` | My Team total commission | `/team`: `total_team_commission` |
| `display_total_team_recharge` | My Team accumulated recharge | `/team`: `team_accumulated_recharge` |
| `display_total_users_count` | My Team total users | `/team`: `total_users` |
| `display_valid_users_count` | My Team valid users | `/team`: `total_valid_users` |
| `display_b_count` | My Team B count | `/team`: `b_count` |
| `display_b_active_count` | My Team active B count | `/team`: `b_active_count` |
| `display_c_count` | My Team C count | `/team`: `c_count` |
| `display_c_active_count` | My Team active C count | `/team`: `c_active_count` |
| `display_d_count` | My Team D count | `/team`: `d_count` |
| `display_d_active_count` | My Team active D count | `/team`: `d_active_count` |
| `display_income_today` | Profile income today | `/profile`: `income_today`; `/dashboard`: `today_daily_income` |
| `display_yesterday_earnings` | Profile yesterday earnings | `/profile`: `yesterday_earnings` |
| `display_team_income` | Profile team income | `/profile`: `team_income`; `/dashboard`: `total_commission` |
| `display_daily_income_total` | Profile daily-income total | `/profile`: `daily_income_total`; `/dashboard`: `total_daily_income` |
| `display_salary_income_total` | Profile salary-income total | `/profile`: `salary_income_total` |
| `display_today_joined_count` | My Team today joined | `/team`: `today_joined_count` |
| `display_yesterday_joined_count` | My Team yesterday joined | `/team`: `yesterday_joined_count` |
| `display_today_recharge` | My Team today recharge | `/team`: `today_recharge` |
| `display_yesterday_recharge` | My Team yesterday recharge | `/team`: `yesterday_recharge` |
| `display_this_month_recharge` | My Team current-month recharge | `/team`: `this_month_recharge` |
| `display_last_month_recharge` | My Team previous-month recharge | `/team`: `last_month_recharge` |
| `display_wallet_total_credit` | Wallet summary total credit | `/wallet`: `total_credit` |
| `display_wallet_total_debit` | Wallet summary total debit | `/wallet`: `total_debit` |

No override fields were found for individual products, user products, recharge requests, withdraw requests, points, bonus codes, SVIP purchases, or downline-product rows. Those endpoints continue returning real values.

The current `/api/v1` surface does not expose the Filament admin dashboard. Existing Filament dashboard widgets use their own admin/account scopes and virtual-user filters, but they do not read the user `display_*` fields listed above. No new admin override behavior was introduced.

## Open Questions

- The current web profile page only supports avatar updates. The API `POST /profile` safely supports `name` and `phone`; no web password/security-password profile update flow exists.
- No existing web security password rule was found for withdraw. The API follows current `WithdrawService`.
