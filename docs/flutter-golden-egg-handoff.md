# Flutter / App 敲金蛋接入交接

更新日期：2026-10-17。面向负责 App 的同事及 AI agent。Laravel 后端已有接口、购买送次数、邀请首购奖励、奖品库存、钱包入账和优惠券结账；App 需要实现入口及原生页面。本次不是 Android 浏览器兼容性修复。

## 先确认这几项

- 新现金奖励发往 **`withdrawable` 可提现钱包**，真实账号与虚拟账号相同。历史充值钱包奖励不迁移；历史请求重试不会重新发奖。
- 顶部不显示 `Agent` 或“无限”文字。虚拟账号仍按后端原有规则抽奖，次数仅随机展示 1–10。
- 活动开启期间，任意产品每笔成功购买给购买者 1 次机会，包括重复购买和使用优惠券购买。新邀请用户符合首购条件时，邀请人另得 1 次。失败订单、旧订单不补发。
- 奖品和中奖金额完全由服务器决定，App 不计算概率、不修改库存、不自行给钱包加款。
- 优惠券是百分比减免：10 表示减免 10%、实付 90%。每笔最多 1 张，每张仅用 1 次，支持 100% 减免。

## 连接与入口

沿用 App 现有环境的 API base URL，例如 `https://your-domain.com/api/v1`；不要把开发机的 `127.0.0.1` 写进手机配置。复用现有登录后的 Sanctum token：

```http
Authorization: Bearer <existing_access_token>
Accept: application/json
Content-Type: application/json
```

`GET /api/v1/app-config` 无需登录，新增模块标记：

```json
{"success":true,"data":{"modules":{"golden_egg":true}}}
```

上例仅摘录相关字段，完整响应还包含其他模块、`currency`、`wallet_types` 等。`modules.golden_egg` 表示后端支持此功能，**不是活动开启状态**。当前活动是否开启，以登录后 `GET /golden-egg` 的 `data.enabled` 为准。

在首页活动区、导航或个人页添加金蛋图标入口，打开全屏金蛋页。入口不要依赖 iOS/Android 分支，也不要因没有次数而隐藏。活动关闭时真实账号可查看奖品、历史与规则，抽奖按钮禁用。虚拟账号依然可以抽奖。

## 接口清单

下表路径相对 `/api/v1`；除了 App 配置，均需登录。

| 方法与路径 | 用途 |
| --- | --- |
| `GET /app-config` | 模块能力、货币及钱包类型名称 |
| `GET /golden-egg` | 活动状态、次数、九种奖品、本人历史、可用券、邀请链接 |
| `POST /golden-egg/draw` | 确认后抽奖，幂等重试；每分钟最多 30 次请求 |
| `GET /products` | 产品及 `data.coupons` |
| `POST /products/{product_id}/purchase` | 使用单张可选优惠券购买；成功时后端自动发次数 |
| `GET /wallet` | 刷新真实余额和流水 |
| `GET /wallet/transactions?wallet_type=withdrawable&type=golden_egg` | 查询可提现钱包中的金蛋流水 |

### 读取活动

`GET /api/v1/golden-egg` 返回示例。奖品 ID、金额与可用状态都是示意值，必须使用实际返回内容。

```json
{
  "success": true,
  "data": {
    "enabled": true,
    "is_virtual": false,
    "remaining_chances": 3,
    "invite_url": "https://your-domain.com/register?ref=EXAMPLE",
    "prizes": [
      {"id":101,"name":"Wallet reward 99999","type":"cash","amount":"99999.00","discount_percent":"0.00","available":true},
      {"id":102,"name":"Wallet reward 399999","type":"cash","amount":"399999.00","discount_percent":"0.00","available":true},
      {"id":103,"name":"Wallet reward 799999","type":"cash","amount":"799999.00","discount_percent":"0.00","available":true},
      {"id":104,"name":"Wallet reward 9999999","type":"cash","amount":"9999999.00","discount_percent":"0.00","available":true},
      {"id":105,"name":"Wallet reward 39999999","type":"cash","amount":"39999999.00","discount_percent":"0.00","available":false},
      {"id":106,"name":"Wallet reward 99999999","type":"cash","amount":"99999999.00","discount_percent":"0.00","available":false},
      {"id":107,"name":"5% purchase discount","type":"coupon","amount":"0.00","discount_percent":"5.00","available":true},
      {"id":108,"name":"10% purchase discount","type":"coupon","amount":"0.00","discount_percent":"10.00","available":true},
      {"id":109,"name":"15% purchase discount","type":"coupon","amount":"0.00","discount_percent":"15.00","available":true}
    ],
    "history": [
      {"id":501,"prize_id":101,"egg_number":5,"name":"Wallet reward 99999","type":"cash","amount":"99999.00","discount_percent":"0.00","created_at":"2026-10-15T16:30:00+08:00"}
    ],
    "coupons": [{"id":601,"discount_percent":"10.00"}]
  }
}
```

| 字段 | 类型与含义 |
| --- | --- |
| `enabled` | bool；后台活动总开关 |
| `is_virtual` | bool；服务器识别的账号身份，不由 App 上传或切换 |
| `remaining_chances` | 真实账号为整数；虚拟账号为 `null`，不代表没有次数 |
| `prizes` | 全部九种配置，按后台奖品位排序；没有 `slot` 字段，数组位置不是中奖结果 |
| `prizes[].id` | 奖品配置 ID，用于匹配中奖项；不能假设 ID 为 1–9 |
| `available` | 是否可被当前账号抽中；库存为 0 或权重为 0 的真实奖品为 false。结果展示仍保留它 |
| `amount` / `discount_percent` | 金蛋与优惠券接口返回两位小数的十进制字符串；不要从 `name` 解析金额 |
| `history` | 最近 30 条本人记录；`id` 为中奖记录 ID，`prize_id` 为奖品配置 ID；没有历史分页接口 |
| `coupons` | 本人所有未用券；`id` 是结账需要的 `coupon_id` |
| `invite_url` | 直接复制或系统分享，沿用现有邀请流程 |

接口不返回库存数量、权重或中奖概率。货币取 App 配置的 `currency`；不要写死 RM、đ 或使用手机地区猜测。

### 确认后抽奖

`POST /api/v1/golden-egg/draw`：

```json
{"request_id":"8bfa6d71-cc46-41d2-85c4-0aeec1b3b096","egg_number":5}
```

- `request_id`：新一轮确认后生成 UUID，并在发请求前按当前用户持久化。
- `egg_number`：用户点选位置，整数 1–9；不是 `prize_id`。
- 不传中奖金额、中奖类型、账号虚拟身份、库存或指定奖品。

成功为 HTTP 200：

```json
{
  "success": true,
  "data": {
    "prize": {
      "id":502,
      "prize_id":101,
      "egg_number":5,
      "name":"Wallet reward 99999",
      "type":"cash",
      "amount":"99999.00",
      "discount_percent":"0.00",
      "created_at":"2026-10-15T16:35:00+08:00"
    },
    "remaining_chances":2
  }
}
```

券奖品的 `type` 为 `coupon`，`amount` 为 `"0.00"`，使用 `discount_percent` 展示减免。**中奖响应没有 `coupon_id`**：刷新 `GET /golden-egg` 或 `GET /products` 后，从 `coupons` 取实际券 ID。不能把中奖记录 `id` 或 `prize_id` 当成券 ID。

### 抽奖重试与错误

超时、断网、5xx 等结果不确定时，保留原 `request_id` 和 `egg_number`；恢复网络后用同一请求重试。禁止自动换 UUID，否则可能变成第二轮。服务器对同一用户、同一 UUID 返回原中奖结果，不重复扣次数、扣库存或发奖；即使活动已关闭，也可恢复已完成的请求。

未解决的请求期间禁止选另一颗、发第二个请求或假装抽完。成功后清除待恢复请求；按中奖记录 `id` 去重更新历史。已保存的旧请求始终属于原登录用户，不得发给另一个账号。取消确认时不生成请求、不扣次数。

| HTTP | 处理 |
| --- | --- |
| 400 | `success:false`，`message` 为活动关闭、没有次数、奖品售罄或券不可用等业务原因；显示消息并刷新活动状态。售罄不消耗机会 |
| 401 | token 无效，走现有重新登录流程 |
| 422 | `errors` 按字段返回，例如 UUID 格式错误或 `egg_number` 超出范围；不要继续播放成功动画 |
| 429 | 遵守 `Retry-After`；重试抽奖仍用原 UUID，不要连续循环请求 |
| 5xx / 网络超时 | 结果不确定，保留请求并提供恢复抽奖操作 |

错误封装为 `{"success":false,"message":"...","errors":[]}`；422 的 `errors` 是字段到消息数组的对象。错误文字不是稳定错误码，不要依赖英文字符串判断抽中了什么。

## 原生页面和动画状态

1. 全屏进入，加载状态后显示 3×3 九颗金蛋；顶部使用实际金蛋图标，保留关闭按钮，不显示 Agent 身份字样。
2. 真实账号仅当 `enabled && remaining_chances > 0` 才可开始新抽奖；虚拟账号直接允许。关闭或零次数仍能查看规则、奖品和邀请入口。
3. 点击某颗后先显示确认框：真实账号提示“使用 1 次机会，奖品即时发放”，虚拟账号仅提示即时发奖。取消回到原状态。
4. 确认后锁住连续点击和离开动作，发送请求；同时播放选中金蛋放大、其他蛋淡出的聚焦。请求失败要恢复选择画面，保留需要恢复的请求。
5. 得到成功响应后，播放锤子敲击、第一层裂纹、裂纹加深发光、破壳与闪光，再显示真实奖品。可用原生动画引擎，不必使用 WebView 或 GSAP。
6. 动画结束后揭晓全部九个栏位：中奖项放在服务器 `prize.egg_number`，标记“你抽中”；剩余八项按配置 ID 排除中奖项后随机摆放。零库存、零权重的奖品也展示，只显示奖励，不展示库存或不可抽取状态。这八项不额外发奖。
7. “再敲一颗”返回柜子；真实次数用响应值，不自行减 1；虚拟账号每次成功后换成另一个 1–10 的展示数字，取消或失败不变。不要显示 ∞ 或用展示数字限制 Agent 抽奖。
8. 金额根据栏位缩小字号，特别长时按千分位分组换行；货币和首组数字保持一起，小数部分不拆开，不使用省略号或裁切。

客户端随机仅用于剩余奖品的摆放与虚拟账号次数展示，不能决定真实奖品。若在请求期间后台调整了配置，应刷新目录；不要拿另一个配置覆盖服务器的实际中奖结果。

### 中奖后的状态刷新

- 现金：显示“奖励已存入你的可提现钱包”，刷新 `GET /wallet` 的 `data.wallets.withdrawable.balance`。不要根据奖额在客户端直接加余额，避免请求恢复后重复加款。
- 券：显示“折扣券已保存，购买时可选用”，刷新活动/产品的可用券列表。
- 两者都刷新活动次数和本人历史；金额到账发生在服务器事务成功时，不取决于用户是否看完动画。
- `GET /wallet` 的余额是 JSON number，与金蛋奖额的 decimal string 不同；沿用现有钱包解析器。流水 `type = golden_egg`、`direction = credit`、新现金奖励的 `wallet_type = withdrawable`。历史奖励以实际流水钱包为准，不能从历史中奖记录推断全部都进可提现钱包。

## 背景音乐与敲击音效

音频已随此仓库提供，位于 `public/audio/golden-egg/`。App 可随包分发，或从同环境站点根 URL 加 `/audio/golden-egg/<filename>` 获取。使用已有原生音频库实现，音乐和短音效分开混音；不要等播放完声音才处理 API。

| 文件 | 解码后时长 | 播放方式 / 音量 |
| --- | --- | --- |
| `ambience.mp3` | 26.667 秒 | 72 BPM、八小节原创咖啡馆电钢琴配乐；进页自动尝试、循环，gain 0.22 |
| `hammer-hit.mp3` | 0.480 秒 | 三次锤子实际碰到蛋壳时各播放一次；gain 0.55、0.65、0.75 |
| `shell-crack.mp3` | 0.900 秒 | 每次碰撞与锤声一起叠加，gain 0.25、0.40、0.55；逐次加重，保留混音余量 |
| `prize-reveal.mp3` | 2.200 秒 | 实际奖品揭晓时播放一次，gain 0.60；只在抽奖成功后播放 |

文件均为 stereo / 44.1 kHz / 128 kbit/s MP3，总计约 0.49 MB。是项目原创合成素材，没有第三方录音或采样。来源、使用说明在同目录 `README.md`；可复用到 iOS、Android。

网页现有裂壳时间线的参考同步点如下，**不包含请求等待和选中聚焦时间**：

| 相对时间 | 动画 / 音频 |
| --- | --- |
| 0.71 秒 | 第一下接触，锤声 + 轻裂壳 |
| 1.83 秒 | 第二下接触，锤声 + 更深裂壳 |
| 3.10 秒 | 第三下接触，锤声 + 完整破壳 |
| 4.45 秒 | 裂壳动画结束，揭晓奖品，播放中奖和弦 |

原生端若调整动画长度，要把声音挂在实际动画节点上，不要从发 HTTP 的时刻开始固定倒计时。仅播放一次中奖音效，网络恢复返回同一奖品时不要重复加钱。

声音开关和关闭按钮放在同一排顶部工具栏，垂直居中并留出间距；工具栏遵守系统顶部安全区，保持在内容滚动区外。确认框、规则和中奖结果都从工具栏下方开始，给标题留出独立空间，不能用悬浮声音按钮盖住奖品标题。在这些页面中声音仍可操作，统一控制音乐和音效；记住用户上次的静音选择。右上角关闭优先收起当前确认框、规则或结果，回到金蛋柜；在金蛋柜关闭时才退出活动，敲击请求和动画期间禁用关闭。

App 切后台、页面退出或系统音频中断时暂停音乐，并停止正在播放的短音效；恢复后只在用户未静音时继续音乐，不补播错过的敲击声，不创建第二条背景音乐。

网页使用单个 Web Audio context，进页尝试播放；浏览器拦截自动播放时，按钮显示“开启声音”，首次点击活动页或声音按钮后恢复。App 按原生平台音频会话规则实现相同体验。音频加载失败不能阻断抽奖、确认、余额更新。参考实现为 `resources/js/golden-egg-audio.js`，声音时机在 `resources/js/golden-egg.js` 的 GSAP timeline 中。

浏览器策略依据：[MDN autoplay guide](https://developer.mozilla.org/en-US/docs/Web/Media/Guides/Autoplay)。

## 购买送次数与优惠券结账

`GET /api/v1/products` 已有 `data.coupons`，形如 `[{"id":601,"discount_percent":"10.00"}]`。结账提供“不使用优惠券”及单选券；请求示例：

```http
POST /api/v1/products/123/purchase
```

```json
{"wallet_type":"recharge","coupon_id":601}
```

`wallet_type` 必填，支持 `recharge`、`withdrawable`；`coupon_id` 可省略或传 null，只能是单个整数。所有六类产品默认允许用券，后台可以对单个产品关闭；券无到期时间，活动关闭后已发券仍可用于允许用券的产品。

产品的 `data.items[]` 和 `data.products_by_type.<type>[]` 均新增布尔值 `allow_coupons`，默认 `true`。为 `false` 时，App 隐藏用券选择并清除之前选中的券；原价购买不受影响。服务端会按付款时最新产品设置校验，禁止用券的产品提交 `coupon_id` 返回 HTTP 400 和可展示的 `message`，不扣钱包、不消耗券、不占库存、不发机会。收到该错误后刷新产品目录并保留未用券，不要自动改为原价付款。修改开关不改变已完成订单。

服务器校验归属/未用状态，计算折后价并消费券，成功 HTTP 201 返回 `data.user_product`。显示实际 `price_snapshot`，收益仍用后端快照。100% 券可零元完成购买，也发购买机会。付款失败不消耗券。

每笔成功购买后，刷新钱包、可用券和 `GET /golden-egg`，无需额外“领取次数”接口。购买响应本身不包含金蛋次数；不要在 App 本地永远加 1。网页或另一台手机消费过次数时，以最新读取为准。

邀请奖励以首次成功购买为准：用户通过现有邀请码注册，无论注册时活动是否开放，只要首次成功购买时活动开放，就给直属邀请人 1 次。注册本身不送，同一受邀用户只奖励一次；首购发生在活动关闭期间，后续购买不补邀请奖励。符合条件时，购买者和邀请人分别各得 1 次。Agent 注册/购买不累计真实次数。规则由后端执行，App 不要按注册时间过滤资格；邀请人需刷新金蛋接口读取新次数。部署此规则不会自动重跑已经完成的历史订单。

**购买接口没有抽奖那样的 UUID 幂等协议**。App 要防止重复提交；付款结果未知时先刷新订单与余额，不要因网络超时自动重发购买请求。新的成功订单会再次扣款并获得新机会。

## 复用现有视觉素材

素材已在此后端仓库 `public/images/golden-egg/`。可复制到 App 工程随包发布，或用相同环境的站点根 URL 加 `/images/golden-egg/<filename>` 加载；不是 `/api/v1/images/...`。不要使用此开发机的绝对路径。

| 文件 | 用途 |
| --- | --- |
| `vault-egg-icon.webp` | 首页/菜单真实金蛋图标 |
| `vault-atmosphere.webp` / `vault-atmosphere-desktop.webp` | 手机竖屏 / 宽屏咖啡金蛋场景 |
| `vault-cabinet.webp` | 3×3 金蛋柜，原图 1140×1380 |
| `vault-egg.webp` | 完整金蛋，1086×1448 |
| `vault-egg-crack-1.webp` / `vault-egg-crack-2.webp` | 两级裂纹，保持与完整金蛋同一对齐和比例 |
| `vault-egg-shattered.webp` | 破壳状态 |
| `vault-hammer.webp` / `vault-glow.webp` | 锤子和透光/闪光叠层 |
| `vault-button.webp` | 金色按钮材质 |
| `golden-serif.ttf`、`Noto-Serif-SC-OFL.txt`、`Phosphor-MIT.txt` | 现有标题字体及许可证；图标/字体分发时保留相关许可证 |

版式与敲击序列参考 `resources/views/golden-egg/index.blade.php`、`resources/css/golden-egg.css`、`resources/js/golden-egg.js`。这些是网页参考实现，原生端仍通过 Bearer API 接入。

## 同事 AI agent 的实施与验收

- 在 App 工程实现页面、入口、API client/model 和当前状态管理；后端仍是唯一发奖方，不新增本地概率或伪造奖励。
- 真实账号：关闭活动/零次数不能开始新抽奖；购买成功后机会增加；取消确认不变；成功抽奖减少一次。
- 每个结果显示九种配置，含不可抽中的奖品；实际中奖位置、金额、类型以服务器响应为准。
- 断网重试同一 UUID 只产生一次奖励、一条历史；重启 App 能恢复待确认结果，快速连点不会并发抽奖。
- 现金进入 withdrawable，充值余额不增加；刷新钱包后金额正确。旧充值钱包中奖请求重试不迁移或重发。
- 券只能单选、一次使用；10% 实付 90%，100% 可零元；失败购买不耗券。产品 `allow_coupons=false` 不展示选择券，旧页面带券请求也应拒绝且无扣款；不带券仍能购买，重新开启后未用券可正常使用。不可用券被其他设备消费后，正确刷新列表。
- Agent 不显示身份标签或无限符号，次数在 1–10 之间成功后跳变；仍可抽到全部九种配置，不扣真实库存。
- 进页背景音乐循环，三次锤击与裂壳同步，成功揭晓播放一次和弦；静音记忆、后台暂停、返回恢复及失败素材不影响抽奖都要验证。
- 检查 iOS、Android、窄屏、大字号和极大金额，保证按钮可点、奖额完整、动画资源无穿帮；滚动金蛋柜和中奖页时，声音与关闭按钮保持对齐，标题不会进入工具栏。

后端验收测试在 `tests/Feature/GoldenEggActivityTest.php`；网页声音状态测试为 `node --test tests/js/golden-egg-audio.test.mjs`。现有服务与配置说明见 [金蛋后端说明](golden-egg.md)，通用认证/接口见 [Flutter API 交接](flutter_api_handoff.md)。上线前部署当前后端、执行现有迁移、运行 `npm run build`，并发布 `public/images/golden-egg/` 与 `public/audio/golden-egg/` 素材；本次钱包方向修改无需新增迁移。
