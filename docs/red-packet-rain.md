# Red packet rain

The activity is disabled on installation. Its default start is **2026-11-15 00:00:00 in `config('app.timezone')`** (production: Asia/Ho_Chi_Minh). An optional end is exclusive. Closed, future and expired activities have no customer navigation/dashboard entry. Existing activity links show the closed state and cannot draw, including virtual accounts.

## Administration

Open **Dashboard → Activities and rewards → Red packet rain** for the switch and schedule; follow **Configure prize pool** to edit prize names, types, amounts, relative weights and remaining stock. The platform navigation also contains the prize pool and winning records. Physical gifts are recorded for offline fulfilment; there is no shipping workflow.

| Prize | Initial weight | Initial remaining stock |
| --- | ---: | ---: |
| iPhone 18 Pro Max 256GB | 0 | 0 |
| Washing machine, value 5,000,000 VND | 1 | 3 |
| Bluetooth earbuds, value 600,000 VND | 4 | 5 |
| Cash 300,000 VND | 12 | 20 |
| Cash 100,000 VND | 75 | Unlimited |
| 10% purchase coupon | 8 | 20 |

Weights are relative to available prizes. Either weight 0 or stock 0 excludes a prize, including for virtual users. Unlimited stock is `NULL`, controlled by a separate toggle. Ordinary draws reduce finite stock in the same transaction as the reward and chance consumption.

Permissions: `Manage:RedPacket`, `ViewAny:RedPacketPrize`, `View:RedPacketPrize`, `Update:RedPacketPrize`, `ViewAny:RedPacketDraw`, `View:RedPacketDraw`. Winning records honour support staff's existing downline restrictions.

In the role editor, **Custom → Manage Red Packet Activity** grants switch/schedule access. Under **Resources**, **RedPacketPrize** grants prize-pool viewing/editing and **RedPacketDraw** grants read-only winning-record access. Existing `super_admin` accounts can access these activity controls without explicitly assigning new permissions. Clear Laravel configuration and Spatie permission caches after deployment (`php artisan optimize:clear` and `php artisan permission:cache-reset`) so the new role-editor options are visible.

## Chances

- A directly invited member must register at or after the activity start and make their first successful purchase while the activity is open. Each qualifying member earns their inviter one chance. Weekend purchases are excluded. A first weekend purchase cannot later become a qualifying first purchase; deleted purchase history is also considered.
- Invitation progress is grouped by the purchase date in the configured timezone. Three qualifying members on the same day earn one extra chance; five earn two further chances. Each milestone is awarded once per day. Five members therefore earn eight chances; a sixth earns only one more.
- Progress resets at 00:00 by querying the new local date. No cron task or deletion is required. Unused chances remain available until the event closes. A member registering yesterday and first purchasing today counts today.
- A personal purchase earns one separate upgrade chance when its original price exceeds the immediately previous non-weekend purchase's original price. T1 and weekend purchases earn no upgrade chance. T1 can serve as the previous purchase. A first purchase above T1 qualifies. Discounts do not change the comparison. Upgrading is compared with the previous purchase, not the historical maximum.
- Virtual accounts draw without consuming chances and display a changing random count from 1–10. They respect activity dates, weights and prize availability, do not consume real stock, and retain their own cash/coupon/winning records. Virtual purchases do not earn invitation rewards for real users.
- Changing dates, toggling or reopening does not reset existing awards, chances, wins or stock. Past purchases are not backfilled.

Every award has a unique source key. Draw requests use UUIDs and are idempotent per user. Lost responses can be retried with the same UUID, including after closure. A failed or empty-pool draw consumes neither a chance nor stock. Cash credits the withdrawable wallet; coupons use existing eligibility and checkout rules. Customers can inspect both chance and winning histories at `/red-packet/history`.

## Routes and deployment

Web: `GET /red-packet`, `GET /red-packet/history`, `POST /red-packet/draw` (`request_id`: UUID). Authenticated API equivalents live under `/api/v1`. The app-config modules list includes `red_packet`.

After updating code, run the normal deployment plus:

```sh
php artisan migrate --force
npm ci
npm run build
php artisan optimize:clear
php artisan optimize
```

The migration creates three activity tables, adds a nullable coupon source, installs the six defaults and permissions, and leaves the activity disabled. Configure/enable it in the admin after deployment. This change does not require a scheduled job.

## Artwork and visual direction

Built-in image generation produced `public/images/red-packet/atmosphere.png`. The interactive envelope is layered CSS so its flap, seal and reward can animate independently. Existing Phosphor icons represent gifts. Opening animations always play, including when the device requests reduced motion, as requested by the activity owner. English, Chinese, Vietnamese and Indonesian copy is supplied.

Final generation prompt:

> Use case: stylized-concept. Create a premium website background asset for a Vietnamese red-envelope rain rewards event. Wide cinematic 3:2 composition. Deep oxblood and lacquer burgundy (#420e20, #781c30), subtle red silk folds, softly illuminated champagne-gold dust, a few elegant red paper lucky envelopes with embossed gold botanical borders drifting near the TOP AND SIDE edges. A very subtle theatrical curved gold halo in the middle background. Large quiet dark central negative space for a large interactive envelope and readable website typography to be overlaid in HTML. Tactile paper and silk, sophisticated editorial lighting, luxurious without clutter, no coins, no casino equipment, no text, no lettering, no logos, no interface or buttons. High-end 3D still-life photography. Both middle left and center must remain subdued and unobstructed. Image is background artwork, not a webpage mockup.

Palette: oxblood `#420e20`, lacquer `#a32239`, champagne `#e5c58c`, ivory `#fff2dc`, pale paper `#fbf6ef`. Georgia display type contrasts with the site's existing interface type. Desktop uses an invitation/editorial panel beside one interactive envelope; mobile centres that same envelope above a compact chance counter and daily progress. The only prominent decorative animation answers the user's opening action.

Validation: `tests/Feature/RedPacketActivityTest.php` covers dates, first purchases, daily milestones, retained chances, original-price upgrades, idempotent draws, stock, cash/coupons/physical prizes, virtual accounts, authentication, private histories and admin permissions. Existing golden-egg, treasure-chest and coupon tests cover purchase integration regressions.

Local verification: the new suite passed 14 tests / 476 assertions, including all four languages, closed/future/expired entry and draw checks for regular and virtual accounts, existing super-admin access, and assigning permissions through the role editor. The existing three-suite regression ran 89 tests / 857 assertions: 87 passed directly; two golden-egg product-page cases failed because the local AWS SDK data manifest was missing. Rerunning that four-case test in a temporary copy with `Storage::fake('do_spaces')` passed all four cases / 98 assertions; the original tests and production storage configuration were not changed. Vite build, Blade compilation, route registration and `git diff --check` passed. Browser screenshots and animation interaction could not be verified because local HTTP access failed and the browser policy disallowed local-file previews. An ignored, self-contained design demo with explicitly labelled sample data is available at `storage/app/private/red-packet-preview/design-preview.html`.
