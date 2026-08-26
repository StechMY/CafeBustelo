# Flutter API Handoff

## 1. Overview

Base API URL format:

```text
https://your-domain.com/api/v1
```

Authentication uses Laravel Sanctum personal access tokens. After login/register, store `access_token` securely and send it as:

```http
Authorization: Bearer <token>
Accept: application/json
```

All JSON responses follow:

```json
{
  "success": true,
  "message": "Success",
  "data": {}
}
```

Errors:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "username": ["The username field is required."]
  }
}
```

Paginated blocks:

```json
{
  "success": true,
  "data": {
    "items": [],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 100,
      "last_page": 5
    }
  }
}
```

Date/time format: ISO-8601 string, for example `2026-06-16T12:00:00.000000Z`.

Currency format: numeric values are returned as numbers. Some objects also include `formatted_*` strings using the backend currency.

## 2. Authentication Flow

### Register

Method: `POST`

Step 1 URL: `/api/v1/register/send-otp`

Auth required: no

Body:

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

Flutter should start the resend countdown from `data.resend_seconds`. SMS provider credentials stay only in Laravel.

Step 2 URL: `/api/v1/register`

Auth required: no

Body:

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| name | string | yes | max 255 |
| username | string | yes | alpha dash, min 4, max 32, unique |
| phone | string | yes | max 40, same phone used for OTP |
| otp_code | string | yes | 6 digits |
| referral_code | string | yes | must exist |
| password | string | yes | min 8 |
| password_confirmation | string | yes | must match password |
| device_name | string | no | token name |

Example:

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

Success:

```json
{
  "success": true,
  "message": "Account created successfully.",
  "data": {
    "token_type": "Bearer",
    "access_token": "1|plainTextToken",
    "user": {
      "id": 1,
      "username": "jane123",
      "name": "Jane",
      "phone": "60123456789",
      "phone_verified_at": "2026-06-25T10:00:00.000000Z",
      "status": "inactive",
      "referral_code": "ZXCV1234",
      "points_balance": 0
    }
  }
}
```

Invite-day points may be credited immediately during registration or later when the invited user's first recharge is approved, depending on the backend admin setting. Flutter should not assume the referrer receives invite-day points at registration time.

### Login

Method: `POST`

URL: `/api/v1/login`

Auth required: no

Body:

```json
{
  "username": "jane123",
  "password": "password123",
  "device_name": "flutter"
}
```

Success returns the same token shape as register. Store `access_token` in secure storage.

### Logout

Method: `POST`

URL: `/api/v1/logout`

Auth: Bearer token required

Deletes the current token. Flutter should clear local token and cached user state.

### Current User

Method: `GET`

URL: `/api/v1/me`

Auth: Bearer token required

Returns:

```json
{
  "success": true,
  "data": {
    "user": {}
  }
}
```

## 3. Global Response Format

Use `success` as the first branch. For HTTP 401, clear token and route to login. For 422, display field errors from `errors`.

## 4. Data Types

### User

| Field | Type | Nullable | Meaning | Example |
| --- | --- | --- | --- | --- |
| id | integer | no | User ID | 1 |
| username | string | no | Login username | `jane123` |
| name | string | yes | Display name | `Jane` |
| email | string | yes | Email if present | `a@b.com` |
| phone | string | yes | Phone | `60123456789` |
| status | string | no | `active`/`inactive` | `active` |
| is_virtual | boolean | no | Virtual user flag | false |
| order_status | boolean | no | Can purchase products | true |
| withdraw_status | boolean | no | Can submit withdraw | true |
| check_in_status | boolean | no | Can check in | true |
| referral_code | string | no | Invite code | `ABCD1234` |
| referrer_id | integer | yes | Parent user | 2 |
| points_balance | number | no | Points balance | 10 |
| profile_photo_url | string | yes | Avatar URL | `https://...` |
| current_vip_level_number | integer | no | Current VIP level | 1 |
| current_vip_level_name | string | no | VIP label | `LV1` |
| accumulated_recharge | number | no | Approved recharge total | 1000 |
| activated_at | string datetime | yes | Activation time | `2026-06-16T...Z` |
| created_at | string datetime | yes | Created time | `2026-06-16T...Z` |

### Wallet

| Field | Type | Nullable | Meaning | Example |
| --- | --- | --- | --- | --- |
| type | string | no | `recharge` or `withdrawable` | `recharge` |
| label | string | no | Localized wallet label | `Recharge Wallet` |
| balance | number | no | Available balance | 50 |
| locked_balance | number | no | Locked amount | 20 |
| formatted_balance | string | no | Display money | `RM 50.00` |

### Product

| Field | Type | Nullable | Meaning | Example |
| --- | --- | --- | --- | --- |
| id | integer | no | Product ID | 1 |
| code | string | yes | Product code | `P001` |
| name | string | no | Product name | `Plan A` |
| product_type | string | no | Product type | `hourly` |
| product_type_label | string | no | Display label | `Hourly` |
| price | number | no | Product price | 100 |
| daily_income | number | no | Claim income amount | 10 |
| income_per_claim | number | no | Same as claim income | 10 |
| duration_days | integer | no | Duration days | 30 |
| claim_interval_hours | integer | no | Claim interval | 24 |
| total_claims | integer | no | Backend-calculated total claims | 30 |
| total_income | number | no | income_per_claim * total_claims | 300 |
| profit | number | no | total_income - price | 200 |
| roi | number | no | Percent ROI | 200 |
| available_days | array<int> | no | ISO weekdays | `[1,2]` |
| available_days_label | string | no | Display days | `Monday, Tuesday` |
| available_from | string datetime | yes | Start availability | null |
| available_until | string datetime | yes | End availability | null |
| stock_quantity | integer | yes | Stock cap | 100 |
| sold_quantity | integer | no | Sold count | 2 |
| remaining_stock | integer | yes | Remaining stock | 98 |
| is_purchasable_now | boolean | no | Current availability | true |
| can_afford_recharge | boolean | no | Recharge wallet enough | true |
| can_afford_withdrawable | boolean | no | Withdrawable wallet enough | false |
| can_purchase | boolean | no | Backend purchase-state hint | true |
| extra_points | number | no | Product extra points | 0 |

### UserProduct

Fields: `id`, `product_id`, `product_name`, `product_code`, `status`, `price_snapshot`, `daily_income_snapshot`, `income_per_claim`, `duration_days_snapshot`, `claim_interval_hours`, `total_claims`, `claimed_days`, `claimed_count`, `missed_days`, `remaining_claims`, `next_claim_at`, `purchased_at`, `expires_at`, `is_claimable`, `is_completed`, `meta`.

### WalletTransaction

Fields: `id`, `wallet_type`, `wallet_type_label`, `type`, `direction`, `amount`, `formatted_amount`, `description`, `balance_before`, `balance_after`, `remarks`, `meta`, `created_at`, `created_at_label`.

### RechargeRequest

Fields: `id`, `amount`, `formatted_amount`, `deposit_method`, `status`, `approved`, `gateway_provider`, `merchant_order_id`, `gateway_status`, `payment_url`, `reference_no`, `receipt_url`, `payment_method_name`, `payment_bank_name`, `payment_account_name`, `payment_account_no`, `admin_remark`, `reviewed_at`, `created_at`.

### WithdrawRequest

Fields: `id`, `amount`, `fee_amount`, `net_amount`, `status`, `vip_level_snapshot`, `min_withdraw_amount_snapshot`, `withdraw_fee_percent_snapshot`, `bank_name`, `bank_code`, `bank_account_name`, `bank_account_no`, `payout_status`, `payout_status_label`, `gateway_release_at`, `gateway_submitted_at`, `gateway_completed_at`, `admin_remark`, `reviewed_at`, `created_at`.

### TeamMember

Fields: `id`, `username`, `name`, `phone`, `status`, `accumulated_recharge`, `created_at`.

### PointTransaction

Fields: `id`, `direction`, `type`, `points`, `balance_before`, `balance_after`, `remarks`, `meta`, `created_at`.

### DownlineProductGroup

Fields: `user_id`, `username`, `name`, `phone`, `level`, `level_label`, `rate`, `product_count`, `total_sales`, `total_income`, `total_estimated_commission`, `total_claimed_commission_estimate`, `latest_purchase_at`, `latest_purchase_at_label`, `products`.

### DownlineProductDetail

Fields: `product_name`, `product_code`, `status`, `price`, `income_per_claim`, `total_claims`, `claimed_count`, `claim_interval_hours`, `estimated_total_income`, `rate`, `commission_per_claim`, `claimed_commission_estimate`, `earning`, `estimated_full_cycle_commission`, `purchased_at`, `purchased_at_label`.

### SvipPackage

Fields: `id`, `name`, `price`, `formatted_price`, `duration_days`, `is_active`, `description`.

### SvipPurchase

Fields: `id`, `svip_package_id`, `package_name`, `amount`, `status`, `started_at`, `expires_at`, `is_active`, `meta`.

### SalaryClaim

Fields: `id`, `type`, `period_key`, `vip_level_snapshot`, `salary_amount`, `team_subsidy_amount`, `total_amount`, `claimed_at`, `meta`.

### BonusCode

Fields: `id`, `code`, `type`, `amount`, `status`, `remark`, `expires_at`, `used_at`, `created_at`.

### CheckInRecord

Fields: `id`, `claim_date`, `vip_level_snapshot`, `amount`, `claimed_at`, `meta`.

## 5. Endpoint Reference

All endpoints listed below use the global response format.

### App Config

`GET /api/v1/app-config`

Auth: no

Use for startup labels, currency, locale, wallet type labels, product type labels, and enabled modules.

### Dashboard

`GET /api/v1/dashboard`

Auth: yes

Use for home screen. Returns `user`, `wallets`, `stats`, `active_products`, `slides`, `marquees`, `popup`, check-in state, and current VIP.

### Profile

`GET /api/v1/profile`

Auth: yes

Use for profile/account tab. Returns user, wallets, stats, VIP level, next VIP, SVIP package/current purchase, referral URL, and bank account.

`POST /api/v1/profile`

Body:

```json
{
  "name": "Jane",
  "phone": "60123456789"
}
```

`POST /api/v1/profile/avatar` uses multipart with `avatar`.

`DELETE /api/v1/profile/avatar` removes avatar.

### Change Password

Step 1 URL: `/api/v1/account/password/send-otp`

Auth required: yes

Laravel sends OTP purpose `change_password` to the authenticated user's saved phone number. Flutter must not send a phone number for this flow.

Step 2 URL: `/api/v1/account/password`

Auth required: yes

```json
{
  "otp_code": "123456",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

If the user has no saved phone, the API returns 422 with `errors.phone`.

### Products

`GET /api/v1/products`

Returns currently purchasable products grouped by product type. Use `tabs` for product category tabs and `products_by_type[tab.key]` for the visible product list. Empty product types are omitted. `items` is still included as a flat compatibility list.

Each product includes `type`, `type_label`, `product_type`, `product_type_label`, `available_until`, and `available_until_label`. The stored type remains `weekend_special`, but the display label is `Crazy Weekend`.

`POST /api/v1/products/{product}/purchase`

```json
{
  "wallet_type": "recharge"
}
```

After success, refresh dashboard, wallet, products, and user-products.

`GET /api/v1/user-products`

Paginated purchased products.

`POST /api/v1/user-products/{userProduct}/claim`

After success, refresh dashboard, wallet, and user-products.

### Wallet

`GET /api/v1/wallet?wallet_type=all&direction=all&type=all&start_date=2026-06-01&end_date=2026-06-25&page=1&per_page=15`

Use this endpoint for the wallet screen. It returns balances, lifetime summary, all-filtered summary, current-page summary, filters, transaction types, transactions, and pagination meta in one response.

Parser path:

```text
response.data["data"]["wallets"]
response.data["data"]["summary"]["lifetime"]
response.data["data"]["summary"]["all_filtered"]
response.data["data"]["summary"]["current_page"]
response.data["data"]["transactions"]
response.data["data"]["meta"]
```

Rules:

- `summary.lifetime` is not affected by filters and may be display-overridden.
- Use `summary.lifetime.display_total_credit` and `summary.lifetime.display_total_debit` for customer UI.
- `summary.all_filtered` is across all rows matching filters before pagination.
- `summary.current_page` is only the transactions returned in the current page.
- Do not recalculate `all_filtered` or `current_page` in Flutter.
- Date filters use `YYYY-MM-DD`; backend applies start of day and end of day.
- Transaction rows include `formatted_amount`, `description`, `created_at`, and `created_at_label`.

`GET /api/v1/wallet/transactions?wallet_type=recharge&direction=credit&type=bonus_code&start_date=2026-06-01&end_date=2026-06-25&page=1&per_page=15`

Legacy paginated transaction list. Prefer `/wallet` for the wallet screen.

### Recharge

`GET /api/v1/recharge`

Returns recharge wallet, `deposit_methods`, active manual recharge methods, and request history. Render the gateway option only when its `enabled` value is true.

`POST /api/v1/recharge` remains the manual deposit endpoint and uses multipart form data:

| Field | Type | Required |
| --- | --- | --- |
| amount | number | yes |
| recharge_method_id | integer | yes |
| reference_no | string | no |
| receipt | image | yes |

For QC168 automatic deposit:

1. Send `POST /api/v1/recharge/gateway` with JSON `{"amount":"1000.00"}`.
2. Open the returned `data.payment_url` in the app's approved external-browser/WebView flow.
3. After the provider returns, poll `GET /api/v1/recharge/{id}/status` every 3–5 seconds.
4. Treat local `approved: true` as success. Treat `failed`, `reject`, `refund`, `overdue`, or `create_failed` as unsuccessful. Other gateway states are still processing.

Flutter must not send a user ID and must never receive or store `QC168_OC_KEY`, `QC168_SECRET`, or `QC168_MERCHANT_UID`. The provider may return to the Laravel web status page; no custom mobile deep link is assumed.

If the backend requires first-deposit qualification for invite-day points, the referrer can receive those points only when this user's first approved recharge meets the configured minimum. A first approved recharge below the minimum does not qualify later on a second recharge.

### Withdraw

`GET /api/v1/withdraw`

Returns wallet, bank account, VIP withdrawal settings, request history, and `supported_banks` records containing `name` plus the numeric QC168 `bank_code`. Render the server-provided payout status label and timestamps; do not infer payout completion from the legacy approval status alone.

### Bank Account Binding

Step 1:

`POST /api/v1/bank-account/send-otp`

Auth: Bearer token required

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

If the user already has an active bank account, the API returns 422.

Step 2:

`POST /api/v1/bank-account`

Auth: Bearer token required

```json
{
  "bank_account_name": "Customer Name",
  "bank_name": "Vietcombank",
  "bank_account_no": "1234567890",
  "phone": "60123456789",
  "otp_code": "123456"
}
```

Flutter should not recalculate or bypass OTP state. Laravel verifies purpose `bank_account`, derives the numeric QC168 bank code from the selected `supported_banks` name, stores the normalized phone, sets `phone_verified_at`, locks the bank account, and returns `bank_account`. Do not send abbreviations such as `VCB` as a bank code.

`POST /api/v1/withdraw`

```json
{
  "amount": 100
}
```

The full amount is reserved immediately. After admin approval, the automatic payout waits exactly 24 hours and sends `net_amount`; the existing app withdrawal fee is already reflected in that value. Only `payout_status=completed` is final. Provider `failed`, `rejected`, and `overdue` states remain locked while an authorized admin investigates, retries, or safely cancels. The QC168 merchant fee is never a client-side deduction.

### My Team

`GET /api/v1/my-team`

Use for the My Team overview screen. This follows the web `/my-team` page, not the dashboard transfer-referral statistic.

Parser path:

```text
response.data["data"]["stats"]
response.data["data"]["levels"]["b"]
response.data["data"]["levels"]["c"]
response.data["data"]["levels"]["d"]
response.data["data"]["commission_rates"]
```

Rules:

- B = direct referrals of current user.
- C = referrals under B users.
- D = referrals under C users.
- Only B/C/D are valid for My Team.
- Do not parse or display E/F/G/H here unless the web My Team page is changed.
- `stats` values already apply the same display overrides as the web page.

`GET /api/v1/my-team/b?status=all&page=1`

`GET /api/v1/my-team/c?status=active&page=1`

`GET /api/v1/my-team/d?status=inactive&page=1`

Use these for level detail screens. Valid levels are only `b`, `c`, and `d`. Valid `status` values are `all`, `active`, and `inactive`. Pagination is fixed to the web behavior of 30 members per page.

Level member rows mirror the web level page: user id/username, masked phone last five digits, status, highest product, and joined date.

### Check In

`GET /api/v1/check-in`

Returns current VIP, `has_claimed_today`, today record, VIP levels, totals, and records.

`POST /api/v1/check-in`

Claims daily check-in.

### Salary

`GET /api/v1/salary`

Returns current VIP, salary states, VIP levels, and claim history.

`POST /api/v1/salary/claim`

```json
{
  "type": "weekly"
}
```

### Bonus Codes

`GET /api/v1/bonus-codes?status=unused&type=invite`

Returns stats and paginated bonus codes.

`POST /api/v1/bonus-codes/redeem`

```json
{
  "code": "INV-ABCD-EFGH"
}
```

### Contact

`GET /api/v1/contact`

Auth: no

Returns active support contacts.

### Calculator

`GET /api/v1/calculator`

Returns products and B/C/D rates. Formula is `commission = income_per_claim * rate / 100`.

### Points

`GET /api/v1/points`

Returns points balance, exchange rate, estimated cash value, minimum redeem, enable flags, `has_approved_deposit`, `can_redeem`, formula, and transactions.

`POST /api/v1/points/redeem`

```json
{
  "points": 10
}
```

Requires at least one approved recharge/deposit. If the user has only pending/rejected/no recharge records, Laravel returns 422 with `errors.points`.

### Downline Products

`GET /api/v1/downline-products`

This endpoint is grouped by user. Do not parse it as a flat product list.

Parser path:

```text
response.data["data"]["summary"]
response.data["data"]["data"]       // List<DownlineProductGroup>
group["products"]                   // List<DownlineProductDetail>
response.data["data"]["meta"]
```

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
        "level": 1,
        "level_label": "B",
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
            "price": 500,
            "income_per_claim": 10,
            "total_claims": 30,
            "claimed_count": 10,
            "claim_interval_hours": 24,
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

Flutter implementation requirements:

- Model the page as `summary`, `groups`, and `meta`.
- Parse groups from nested response key `data.data`.
- `DownlineProductGroup.products` is a nested list, defaulting to an empty list.
- Render one user card or expansion section per group.
- Render product rows from that group's `products`.
- Pagination appends groups, not products.
- Use `latest_purchase_at_label` and `purchased_at_label` for display when available.
- `earning` equals `estimated_full_cycle_commission`.
- Commission is based on `income_per_claim`, never `price`.
- Hourly products use returned `claim_interval_hours` and `total_claims`.

### SVIP

`GET /api/v1/svip`

Returns packages, active purchase, and history.

`POST /api/v1/svip/{svipPackage}/purchase`

```json
{
  "wallet_type": "withdrawable"
}
```

## 6. Business Rules for Flutter Developer

## Display Override Rules

Some user-facing totals can be replaced by nullable admin-managed fields on the user record. Flutter must display `display_<field>` when it exists in the response. The API always resolves it, so Flutter can directly use `display_<field>` regardless of whether `is_<field>_overridden` is true.

Raw fields are retained for diagnostics and admin-only comparisons. Do not use raw fields for the normal customer UI when a display field is available.

Money responses use:

```json
{
  "total_credit": 1200,
  "display_total_credit": 5000,
  "is_total_credit_overridden": true,
  "formatted_total_credit": "RM 5,000.00"
}
```

| Module | Raw Field | Display Field | Override Source | Flutter Should Display |
| --- | --- | --- | --- | --- |
| User/VIP | `raw_current_vip_level_number` | `current_vip_level_number` | `users.display_vip_level` | `current_vip_level_number` |
| Dashboard | `current_vip_level` | `display_current_vip_level` | `users.display_vip_level` | `display_current_vip_level` |
| Profile | `current_vip_level` | `display_current_vip_level` | `users.display_vip_level` | `display_current_vip_level` |
| Salary | `current_vip_level` | `display_current_vip_level` | `users.display_vip_level` | `display_current_vip_level` |
| Dashboard | `active_team_count` | `display_active_team_count` | `users.display_active_team_count` | `display_active_team_count` |
| Profile | `active_team_size` | `display_active_team_size` | `users.display_active_team_count` | `display_active_team_size` |
| Salary | `active_team_size` | `display_active_team_size` | `users.display_active_team_count` | `display_active_team_size` |
| User/Profile/Salary | `accumulated_recharge` | `display_accumulated_recharge` | `users.display_accumulated_recharge` | `display_accumulated_recharge` |
| Profile | `total_earning` | `display_total_earning` | `users.display_total_earning` | `display_total_earning` |
| Profile | `total_withdrawals` | `display_total_withdrawals` | `users.display_total_withdrawals` | `display_total_withdrawals` |
| My Team | `stats.total_team_commission` | same field, effective value | `users.display_total_team_commission` | `stats.total_team_commission` |
| My Team | `stats.team_accumulated_recharge` | same field, effective value | `users.display_total_team_recharge` | `stats.team_accumulated_recharge` |
| My Team | `stats.total_users` | same field, effective value | `users.display_total_users_count` | `stats.total_users` |
| My Team | `stats.total_valid_users` | same field, effective value | `users.display_valid_users_count` | `stats.total_valid_users` |
| My Team | `stats.b_count` | same field, effective value | `users.display_b_count` | `stats.b_count` |
| My Team | `stats.b_active_count` | same field, effective value | `users.display_b_active_count` | `stats.b_active_count` |
| My Team | `stats.c_count` | same field, effective value | `users.display_c_count` | `stats.c_count` |
| My Team | `stats.c_active_count` | same field, effective value | `users.display_c_active_count` | `stats.c_active_count` |
| My Team | `stats.d_count` | same field, effective value | `users.display_d_count` | `stats.d_count` |
| My Team | `stats.d_active_count` | same field, effective value | `users.display_d_active_count` | `stats.d_active_count` |
| Profile | `income_today` | `display_income_today` | `users.display_income_today` | `display_income_today` |
| Dashboard | `today_daily_income` | `display_today_daily_income` | `users.display_income_today` | `display_today_daily_income` |
| Profile | `yesterday_earnings` | `display_yesterday_earnings` | `users.display_yesterday_earnings` | `display_yesterday_earnings` |
| Profile | `team_income` | `display_team_income` | `users.display_team_income` | `display_team_income` |
| Dashboard | `total_commission` | `display_total_commission` | `users.display_team_income` | `display_total_commission` |
| Profile | `daily_income_total` | `display_daily_income_total` | `users.display_daily_income_total` | `display_daily_income_total` |
| Dashboard | `total_daily_income` | `display_total_daily_income` | `users.display_daily_income_total` | `display_total_daily_income` |
| Profile | `salary_income_total` | `display_salary_income_total` | `users.display_salary_income_total` | `display_salary_income_total` |
| My Team | `stats.today_joined_count` | same field, effective value | `users.display_today_joined_count` | `stats.today_joined_count` |
| My Team | `stats.yesterday_joined_count` | same field, effective value | `users.display_yesterday_joined_count` | `stats.yesterday_joined_count` |
| My Team | `stats.today_recharge` | same field, effective value | `users.display_today_recharge` | `stats.today_recharge` |
| My Team | `stats.yesterday_recharge` | same field, effective value | `users.display_yesterday_recharge` | `stats.yesterday_recharge` |
| My Team | `stats.this_month_recharge` | same field, effective value | `users.display_this_month_recharge` | `stats.this_month_recharge` |
| My Team | `stats.last_month_recharge` | same field, effective value | `users.display_last_month_recharge` | `stats.last_month_recharge` |
| Wallet lifetime | `summary.lifetime.total_credit` | `summary.lifetime.display_total_credit` | `users.display_wallet_total_credit` | `summary.lifetime.display_total_credit` |
| Wallet lifetime | `summary.lifetime.total_debit` | `summary.lifetime.display_total_debit` | `users.display_wallet_total_debit` | `summary.lifetime.display_total_debit` |

Individual products, purchased products, wallet transactions, recharge/withdraw requests, points, bonus codes, downline-product rows, and SVIP records have no discovered display override fields and should use their returned real values.

The Flutter customer API does not expose the Filament admin dashboard. Admin widget account scoping and virtual-user filters therefore do not change these customer endpoint payloads.

### Product purchase

Flutter can display wallet choices. Backend validates status, stock, active window, max purchase count, wallet balance, and order status. Use `can_purchase` only as UI hint; backend remains source of truth.

### SMS OTP

OTP purpose names are `register`, `bank_account`, and `change_password`. Codes are 6 digits, stored hashed in Laravel, expire by backend config, and respect backend resend cooldown/max-attempt rules. Flutter only calls Laravel OTP endpoints and never receives SMS provider credentials.

### Product claims

Claims may be hourly or daily. Use `claim_interval_hours`, `next_claim_at`, and `is_claimable`. Do not assume one claim per day.

### Commission

Commission is based on claim income, not product price:

```text
commission_per_claim = income_per_claim * rate / 100
estimated_full_commission = income_per_claim * total_claims * rate / 100
claimed_commission_estimate = income_per_claim * claimed_count * rate / 100
```

### Points

The implemented `PointService` uses:

```text
cash_amount = points * points_to_cash_rate
```

The actual rate is returned as `exchange_rate` by `GET /points`.

### SVIP

SVIP can be purchased with recharge or withdrawable wallet. Backend prevents duplicate active SVIP.

### Wallet

Refresh wallet/dashboard after purchase, claim, points redeem, recharge, withdraw, bonus redeem, or SVIP purchase. For wallet filters or pagination, request `/api/v1/wallet` again and render the returned `summary.all_filtered`, `summary.current_page`, `transactions`, and `meta` directly.

## 7. Screen-to-Endpoint Mapping

| Flutter Screen | Endpoint(s) | Notes |
| --- | --- | --- |
| Login | `POST /api/v1/login` | Store token securely |
| Register | `POST /api/v1/register/send-otp`, `POST /api/v1/register` | Requires phone OTP and referral code |
| Dashboard | `GET /api/v1/dashboard` | Pull summary, sliders, popup, check-in state |
| Change Password | `POST /api/v1/account/password/send-otp`, `POST /api/v1/account/password` | Uses authenticated user's saved phone only |
| Products | `GET /api/v1/products` | Show grouped product tabs and purchase buttons |
| Product Detail | `GET /api/v1/products` | Use selected product object |
| My Products | `GET /api/v1/user-products`, `POST /api/v1/user-products/{id}/claim` | Claim buttons |
| Wallet | `GET /api/v1/wallet` | Screen-ready balances, summaries, filters, transactions, and meta |
| Recharge | `GET /api/v1/recharge`, `POST /api/v1/recharge`, `POST /api/v1/recharge/gateway`, `GET /api/v1/recharge/{id}/status` | Manual receipt upload or QC168 browser payment/polling |
| Withdraw | `GET /api/v1/withdraw`, `POST /api/v1/bank-account/send-otp`, `POST /api/v1/bank-account`, `POST /api/v1/withdraw` | Bank account must be active |
| My Team | `GET /api/v1/my-team`, `GET /api/v1/my-team/b`, `GET /api/v1/my-team/c`, `GET /api/v1/my-team/d` | Web-aligned B/C/D only |
| Check In | `GET /api/v1/check-in`, `POST /api/v1/check-in` | Daily status |
| Salary | `GET /api/v1/salary`, `POST /api/v1/salary/claim` | Weekly/monthly/annual |
| Bonus Codes | `GET /api/v1/bonus-codes`, `POST /api/v1/bonus-codes/redeem` | Redeem code |
| Contact | `GET /api/v1/contact` | Public support contacts |
| Calculator | `GET /api/v1/calculator` | Commission formula |
| Points | `GET /api/v1/points`, `POST /api/v1/points/redeem` | Redeem points |
| Downline Products | `GET /api/v1/downline-products` | Grouped by user |
| SVIP | `GET /api/v1/svip`, `POST /api/v1/svip/{id}/purchase` | Wallet type required |

## 8. Example Flutter Integration Notes

Dio header:

```dart
dio.options.headers['Accept'] = 'application/json';
dio.options.headers['Authorization'] = 'Bearer $token';
```

401 handling: delete token, clear cached user, navigate to login.

422 handling: read `errors` map and display field-level messages.

Pagination: keep `page`, append `items`, stop when `current_page >= last_page`.

Money: prefer numeric fields for calculations. Use backend `formatted_*` only for display.

## 9. Testing Checklist

- Register
- Send registration OTP
- Register with phone OTP
- Login
- Logout
- Load dashboard
- Load products
- Purchase product with recharge wallet
- Purchase product with withdrawable wallet
- Claim product income
- Claim hourly product more than once per day
- View wallet transactions
- Submit recharge
- Submit withdraw
- Send bank account OTP
- Bind bank account with phone OTP
- Redeem points
- Buy SVIP with recharge wallet
- Buy SVIP with withdrawable wallet
- Load My Team overview
- Load My Team B/C/D level pages
- Confirm My Team does not show E/F/G/H
- Load downline product groups
- Open downline product details
- Handle insufficient balance
- Handle unauthenticated token expiry
- Handle validation errors

## 10. Open Questions

- Change password is a separate OTP flow. API profile update only supports `name` and `phone`; Flutter must use `/account/password/send-otp` and `/account/password` for password changes.
- Current withdraw flow does not validate a security password. API follows existing `WithdrawService`.
