# NTI Backend

## Requirements

- PHP 8.2+
- MySQL 8.0+
- Redis
- Docker (optional)

## Setup

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
```

## Production environment

Set in `.env`:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

FRONTEND_URL=https://your-frontend.com
SANCTUM_STATEFUL_DOMAINS=your-frontend.com
CORS_ALLOWED_ORIGINS=https://your-frontend.com
```

Multiple CORS origins (comma-separated):
```
CORS_ALLOWED_ORIGINS=https://app.nti.sk,https://admin.nti.sk
```

Multiple stateful domains (comma-separated):
```
SANCTUM_STATEFUL_DOMAINS=app.nti.sk,admin.nti.sk
```

HSTS header is sent automatically when request comes over HTTPS.

## Mail

App uses SMTP. For Gmail create an app password at https://myaccount.google.com/apppasswords

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=you@gmail.com
MAIL_PASSWORD=16_digit_appcode
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=you@gmail.com
MAIL_FROM_NAME=NTI
```

## Seeded accounts

After `migrate --seed`:

| Role | Email | Password |
|------|-------|----------|
| Super admin | value of SUPER_ADMIN_EMAIL | value of SUPER_ADMIN_PASSWORD |
| NTI admin | set via createAdmin | value of NTI_ADMIN_DEFAULT_PASSWORD |

## Running with Docker

```bash
docker compose up -d
```

Services: app, mysql, redis.

## Queue worker

```bash
php artisan queue:work
```

Required for email delivery and background jobs.