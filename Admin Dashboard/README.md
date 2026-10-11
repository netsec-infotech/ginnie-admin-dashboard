<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

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

## Server / VPS Requirements

The following guidance covers the minimum and recommended VPS specifications to deploy this Laravel 12 application in production. Adjust upward based on traffic, media volume, background jobs, and API usage.

Hardware (for a single-node deployment):
- Minimum (small projects, staging, or low traffic < 50 concurrent users):
  - 1 vCPU
  - 2 GB RAM (swap enabled)
  - 20–30 GB SSD storage (application + logs + small media)
  - 1 TB monthly bandwidth
- Recommended (production, moderate traffic 50–300 concurrent users):
  - 2–4 vCPU
  - 4–8 GB RAM
  - 60–120 GB NVMe/SSD storage (larger if you store media locally)
  - 2–4 TB monthly bandwidth

Software stack:
- OS: Ubuntu 22.04 LTS or Debian 12 (stable, long-term support)
- Web server: Nginx (recommended) or Apache 2.4+
- PHP: 8.2.x with extensions:
  - BCMath, Ctype, Fileinfo, JSON, Mbstring, OpenSSL, PDO (mysql/pgsql/sqlsrv as used), Tokenizer, XML
  - Redis extension (php-redis) if using Redis cache/queue
  - GD or Imagick (recommended for image processing with spatie/medialibrary)
  - Zip (ext-zip is required by composer.json)
- Composer: 2.x
- Node.js: 20.x LTS with npm 10.x (for building front-end assets via Vite)
- Database (choose one): MySQL 8.0+ / MariaDB 10.6+ / PostgreSQL 14+
- Cache/Queue: Redis 6+ (recommended). Configure REDIS_CLIENT=phpredis

Services and processes:
- Queue worker: run php artisan queue:work (or Supervisor/systemd) with 1–4 workers depending on load
- Scheduler (Cron): * * * * * php artisan schedule:run >> /dev/null 2>&1
- Horizon (optional): if you adopt Laravel Horizon for queues

Storage considerations:
- If you store user-uploaded media locally (spatie/laravel-medialibrary), allocate additional disk and set up regular backups.
- For scalable media, consider S3-compatible storage (e.g., AWS S3, MinIO) and a CDN.

Security and hardening:
- Enable HTTPS (Let’s Encrypt) and HTTP/2/3 on Nginx/Apache
- Keep system packages, PHP, and Composer dependencies updated
- Use UFW/firewalld to allow only 80/443/22 (and 6379/3306 only if needed and restricted)
- Configure appropriate .env values, app key, and database credentials

Scaling guidance:
- Vertical: increase vCPU/RAM first; add more queue workers
- Horizontal: separate DB and Redis onto managed services or dedicated hosts; use object storage for media; add a load balancer with multiple app nodes

Example small production box (balanced start): 2 vCPU, 4 GB RAM, 80 GB NVMe, Ubuntu 22.04, Nginx, PHP 8.2-FPM, Redis, MySQL 8, 2 queue workers.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development/)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).


<!-- Security scan triggered at 2025-09-02 05:39:24 -->

<!-- Security scan triggered at 2025-09-09 05:46:48 -->

<!-- Security scan triggered at 2025-09-28 15:56:19 -->
