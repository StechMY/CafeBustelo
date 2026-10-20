# 人工充值誤批撤銷

## 上線

部署後執行 `php artisan migrate --force`，並依既有部署流程更新設定與視圖快取。
在後臺角色權限中，將 `ReverseApproval:Recharge` 指派給負責核帳的人員。
Migration 只建立權限，不會自動授予所有審批人員。原有批准操作仍使用 `Audit:Recharge`。

## 使用

- 批准人工充值：維持原本的備註與確認視窗，不增加金額重填或核對勾選。新增的金額確認與原因只用於撤銷誤批。
- 撤銷誤批：在已批准人工充值的列表操作或詳情頁選擇「撤銷誤批並拒絕」，輸入金額、原因並確認。
- 成功後扣回充值錢包與累計充值；訂單改為 `rejected`，因此「已批准充值」加總也會減少。
- 原 `recharge_approved` 流水保留，新增 `recharge_reversed` 扣款流水。其 `meta` 記錄原流水 ID、原審核人／時間／備註、操作人、IP 及原因。
- 不會改動網關充值。撤銷後不會回到待批准狀態，也不會自動重新入帳。

原流水缺失、金額或餘額不一致、餘額不足、批准後已有扣款／購買、鎖定款項，或相關積分／後續薪資領取紀錄時，自動撤銷會被阻止，須完整人工對帳。此功能不自動追討獎勵、取消產品或倒轉已發生的後續交易。

## 已經手改狀態、刪除流水的舊資料

此功能不會自動修補舊資料。不要為了顯示按鈕，把已拒絕的舊訂單再改回 `approved`。
刪除 `wallet_transactions` 不會扣回 `wallets.balance` 或 `users.accumulated_recharge`；
修改訂單狀態只會改變依訂單狀態加總的數值。

先保留資料庫備份，對照修改前備份／binlog、銀行實際到帳、訂單 ID 及玩家所有後續交易，
確認「原入帳是否真實發生、已扣回多少、正確剩餘餘額與累計充值」。
不可只依截圖數字推定要再扣多少，也不可直接重跑撤銷。
恢復可追溯的原始流水與新增補正流水，應作為有明確前後數值及操作者的獨立核帳修復。

以下是唯讀核對範本；將 `:user_id` 替換為已確認的使用者 ID：

```sql
SELECT id, username, accumulated_recharge
FROM users WHERE id = :user_id;

SELECT id, type, balance, locked_balance
FROM wallets WHERE user_id = :user_id;

SELECT id, deposit_method, amount, status, reviewed_by, reviewed_at, admin_remark
FROM recharge_requests WHERE user_id = :user_id ORDER BY id;

SELECT id, wallet_id, type, direction, amount, balance_before, balance_after,
       reference_type, reference_id, remarks, meta, created_at
FROM wallet_transactions WHERE user_id = :user_id ORDER BY id;
```

## 驗證

`ManualRechargeReversalTest` 使用記憶體 SQLite，覆蓋正常沖正、兩筆誤批、重複操作、
缺失／不一致流水、已使用款項、相關獎勵、權限、確認表單和交易回滾。
並應執行 `InviteDayFirstPurchaseTest` 與 `Qc168GatewayTest`，確認既有獎勵和網關入帳路徑。
SQLite 測試無法證明 MySQL 的真實並行鎖行為；正式環境使用訂單、使用者及錢包的交易行鎖。
