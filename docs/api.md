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

## Open Questions

- The current web profile page only supports avatar updates. The API `POST /profile` safely supports `name` and `phone`; no web password/security-password profile update flow exists.
- No existing web security password rule was found for withdraw. The API follows current `WithdrawService`.
