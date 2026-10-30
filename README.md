<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Database backups

Deploy `composer.json`, `composer.lock`, and the configuration files, then run
these commands in the production project directory:

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan backup:run --only-db
```

The server needs PHP's ZIP extension and the database dump executable on its
`PATH`: `mysqldump` for MySQL, `mariadb-dump` for MariaDB, `pg_dump` for
PostgreSQL, or `sqlite3` for SQLite. The PHP user must be able to write to
`storage`. Spatie Laravel Backup is pinned to 9.3.6 to retain PHP 8.2 support;
newer 9.x releases require PHP 8.3. Use a Linux
production server, as Windows is not officially supported by the package.

Backups use the existing `DB_CONNECTION` and database credentials, and upload
to DigitalOcean Spaces using `DO_SPACES_KEY`, `DO_SPACES_SECRET`,
`DO_SPACES_REGION`, `DO_SPACES_BUCKET`, and `DO_SPACES_ENDPOINT` (the origin
endpoint, not the CDN URL). The Spaces key needs write and list access.
Each run uploads a timestamped ZIP containing the SQL dump to
`<DO_SPACES_ROOT>/backups/database/<timestamp>.zip` in the configured bucket;
`DO_SPACES_ROOT` defaults to `cafebustelo`. The separate `backups` disk writes
private objects, independently of the public image disk. Local files under
`storage/app/backup-temp` are temporary and removed after a successful backup.
Only the database is included; uploads and application files are not backed up.

Check for `Backup completed!` and a zero exit status, then list the remote backups:

```bash
php artisan backup:list
```

Download the ZIP through the Spaces console and verify restoration into a
separate test database before relying on a backup. Keep this prefix private in
any bucket access policies. No schedule, automatic cleanup, or email notifications
are enabled; run the command whenever a backup is needed and manage storage usage.

## Treasure chest activity

Deploy the code, run `php artisan migrate --force`, build assets with `npm run build`,
and refresh cached configuration with `php artisan config:cache`. The activity is
disabled by default. In **Platform Management → Treasure chests → Activity settings**,
set the start time, optional end time, and enable the activity. Times use the existing
`TIMEZONE` environment setting (`Asia/Ho_Chi_Minh` for Vietnam). Non-super-admin
operators need the `Manage:TreasureChest` permission.

Each qualifying purchase by a directly invited user awards keys to the inviter,
regardless of registration date or earlier purchases. Only purchases completed while
the activity is enabled and within its time window count; previous purchases are not
backfilled. The start is inclusive and the end is exclusive. Product codes, original
prices, key counts and the nine fixed rewards are in `config/treasure_chest.php`.
Weekend products with custom codes are also matched by their weekend product type
and original price. Coupons do not reduce the number of keys earned.

Keys are never spent. Chest reward keys also unlock later chests. Each chest can be
claimed once per user; disabling and reopening does not reset progress. Cash rewards
go to the withdrawable wallet and purchase coupons use the existing checkout rules.
The member page is `/treasure-chest`; API clients use `GET /api/v1/treasure-chest`
and `POST /api/v1/treasure-chest/claim` with `chest_number` (1–9). Repeating a claim
returns the original reward without paying it again. Virtual accounts do not earn
keys or claim these rewards.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
