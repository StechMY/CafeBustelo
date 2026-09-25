# 給 Flutter AI 的 Turnstile OTP 接入任務

## 範圍與現況

請在 Flutter App 的「發送 SMS OTP」流程加入 Cloudflare Turnstile。Laravel
後端已在本機完成強制驗證及專用驗證頁，尚未部署。本文件取代先前「App 不需要
CAPTCHA」的說明。請只修改 Flutter 專案，不要移除後端驗證或增加免驗證入口。

參考專案：`C:\Users\jiash\OneDrive\Desktop\WorkProject\Cafebustelo_frontend`。
本次後端工作只讀取這個專案，沒有修改 Flutter 檔案。

2026-09-25 檢查到的接入位置（行號可能隨版本改動）：

| 檔案／方法 | 需要修改的內容 |
| --- | --- |
| `lib/features/auth/register_screen.dart`，`_sendOtp()` | 先完成驗證，再呼叫註冊 OTP；驗證視窗與送出期間防止重複點擊 |
| `lib/core/auth/auth_controller.dart`，`sendRegistrationOtp()` | 傳遞必填的 `turnstileToken` |
| `lib/data/services/auth_service.dart`，`sendRegistrationOtp()` | JSON 加入 `turnstile_token`；仍使用 `authenticated: false` |
| `lib/features/mobile/feature_screens.dart`，銀行綁定 `_sendOtp()`（約 7252 行） | 先取得 `bank_account` token，再呼叫現有 API |
| `lib/core/api/api_client.dart` | 已會帶 JSON 與 Bearer token，保留既有錯誤處理，不要自動重試 OTP POST |
| `lib/core/config/base_url_resolver.dart`、`lib/base.dart` | 驗證頁使用已解析的 `CFBase.baseUrl`，不要另硬編碼正式網域 |

目前 `pubspec.yaml` 沒有 WebView dependency；依專案 Flutter SDK 選相容的
WebView 套件（例如 `webview_flutter`），不要無關升級。本次沒有在 `lib` 找到
`/account/password/send-otp` 呼叫；若同事的新版已有該功能，按下表接入，
不需要為這個任務新增整個修改密碼畫面。

## 後端合約

| 用途 purpose | WebView GET 路徑 | OTP POST 路徑 | Turnstile action | 登入 |
| --- | --- | --- | --- | --- |
| `register` | `/otp/challenge/register` | `/api/v1/register/send-otp` | `otp_register` | 不需要 |
| `bank_account` | `/otp/challenge/bank_account` | `/api/v1/bank-account/send-otp` | `otp_bank_account` | Bearer token |
| `change_password` | `/otp/challenge/change_password` | `/api/v1/account/password/send-otp` | `otp_change_password` | Bearer token |

驗證頁位於網站根路徑，不在 `/api/v1` 下面。正式環境例子：
`https://cafebustl.online/otp/challenge/register`。它無需登入，不需要 Bearer
token 或手機號碼，未知 purpose 回傳 404。此頁只取得 Turnstile token，不發 SMS。
Site key 已由後端注入頁面；App 不需要 Secret key，也不能自行呼叫 Siteverify。

在載入頁面前建立名稱為 **`OtpChallenge`** 的 JavaScript channel。
頁面呼叫 `window.OtpChallenge.postMessage(JSON.stringify(message))`，訊息如下：

```json
{"type":"success","purpose":"register","token":"opaque-single-use-token"}
```

```json
{"type":"expired","purpose":"register"}
```

```json
{"type":"error","purpose":"register"}
```

`success` 僅表示取得待後端檢查的 token，**不表示短信已發送**。
驗證 message 是合法 JSON、purpose 等於當次用途，token 為非空字串且不超過
2048 字元。同一視窗只處理一次 success；關閉／disposed 後的訊息必須忽略。
error、expired、使用者取消或頁面載入失敗都不得呼叫 SMS API；提供重新開啟驗證的操作。

WebView 必須開啟 JavaScript、DOM storage，使用正常且穩定的 User-Agent，
保留必要 cookie/storage；允許 Cloudflare challenge 資源及其內部
`about:blank`／`about:srcdoc` frame。不要用只允許本站所有資源的攔截器破壞驗證。
限制頂層頁面只停留在設定的可信 HTTPS origin 及預期驗證路徑，阻擋外站頂層導覽，
避免把 bridge 暴露給任意網站；子 frame 與資源請求須區別處理。
不使用 `file://`、自行拼接本機 HTML 或忽略 TLS 錯誤。

## 發送請求

取得 token 後立即送一次，成功／失敗／timeout 後一律丟棄 token；重發必須重新
驗證。token 最長 5 分鐘有效、只能使用一次；不得寫進日誌、持久儲存、URL 或分析事件。

註冊及銀行綁定：

```json
{
  "phone": "0912345678",
  "turnstile_token": "token-from-the-matching-challenge-page"
}
```

修改密碼只送：

```json
{
  "turnstile_token": "token-from-change_password-page"
}
```

修改密碼由後端使用帳戶既有手機號碼。所有請求使用 `Accept: application/json`
及 `Content-Type: application/json`，需登入的入口保留既有 Bearer token。
不要將 Sanctum token 傳給 WebView。後續提交註冊、綁定或新密碼時的 `otp_code`
合約不變，Turnstile token 與六位 SMS OTP 是兩種不同資料。

成功沿用 `success/message/data` 格式，`data.resend_seconds` 和
`data.expires_in_minutes` 控制倒數與文案。先完成驗證，再收到 OTP POST 成功，
才顯示「驗證碼已發送」。驗證視窗與請求期間鎖定本次用途及手機號碼，防止使用者
修改輸入造成畫面與實際收件號碼不一致。

驗證失敗例子（文案會隨語系改變，不可依字串判斷）：

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "turnstile_token": ["Please complete the security verification first."]
  }
}
```

- HTTP 422、`errors.turnstile_token`：可能缺少、過期、重複、用途／網域不符，
  或驗證服務暫時不可用。顯示錯誤並讓使用者手動重試，不要迴圈自動發送。
- 其他 422：沿用 `errors.phone` 等欄位錯誤及冷卻／SMS 服務錯誤處理。
- HTTP 429：依 `Retry-After` 等待，再讓使用者重新驗證。現有 IP／帳戶 3 次/分鐘限制不变。
- HTTP 401：沿用登入失效流程。
- 網路 timeout：不得當作短信未發送而自動重送，保留手動重試及手機冷卻處理。

## 驗收與上線

1. Android、iOS 實機各測註冊／銀行 OTP；若有密碼頁，也測該流程。
2. 通過才呼叫一次 OTP API；取消、載入失敗、error／expired 不呼叫。
3. 快速連點、重複 bridge callback、離開畫面後 callback 不產生額外 API 請求。
4. 缺少 token、錯 purpose、錯 hostname、重複 token 均遭後端拒絕，不發 SMS。
5. 測 422、429、401、斷網、timeout、重發倒數與重新驗證，避免自動重試付費發送。
6. 開發測試使用 Cloudflare 測試設定及假的 SMS 供應商，不要大量發送付費短信。

先在 staging 配好 Turnstile 並部署後端，完成 App 接入與真機測試，再發布新版 App，
安排更新提示與正式後端切換。**本版一部署就強制驗證，沒有環境開關或舊版豁免；
舊版 App 的 OTP 會被拒絕。** App 審核／發布期間不要提前部署這版到正式環境。
如需要分階段先上驗證頁，由後端負責人安排分開發布，不能在 Flutter AI 的工作中
移除後端驗證。部署前也須完成可信代理、共享 cache、hostname allowlist 設定，
詳見 [OTP deployment](otp-protection.md)。

請交付 Flutter 修改檔案清單、測試結果、Android／iOS 支援狀態與仍需人工驗證的項目。
不要宣稱 App 已發布，除非已實際完成發布。

官方參考：
- https://developers.cloudflare.com/turnstile/get-started/mobile-implementation/
- https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
