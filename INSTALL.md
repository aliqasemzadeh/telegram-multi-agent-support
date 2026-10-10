# راهنمای نصب — Telegram Multi-Agent Support

## پیش‌نیازها

- PHP 8.3+ (ترجیحاً 8.4) با افزونه‌های رایج Laravel (`pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`)
- Composer
- Node.js 20+ و npm
- MySQL / MariaDB در حال اجرا
- (اختیاری) EnvKit / Laragon برای سرو کردن سایت روی دامنه `.test`

---

## ۱) دریافت پروژه

اگر از قبل کلون کرده‌اید، همین پوشه را باز کنید:

```bash
cd C:\ProgramData\envkit\projects\telegram-multi-agent-support
```

---

## ۲) نصب وابستگی‌های PHP و JS

```bash
composer install
npm install
```

---

## ۳) تنظیم `.env`

اگر `.env` ندارید:

```bash
copy .env.example .env
php artisan key:generate
```

برای محیط فعلی شما، مقادیر مهم این‌ها هستند:

```env
APP_NAME="Telegram Multi Agent Support"
APP_URL=http://localhost:8000

# فارسی کردن رابط کاربری
APP_LOCALE=fa
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=fa_IR

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tmas
DB_USERNAME=root
DB_PASSWORD=
```

قبل از migrate، دیتابیس `tmas` را در MySQL بسازید (اگر وجود ندارد):

```sql
CREATE DATABASE tmas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

یا از EnvKit / phpMyAdmin همان دیتابیس را ایجاد کنید.

---

## ۴) مایگریشن و Seed

```bash
php artisan migrate --seed
```

این کار جداول برنامه، نقش‌ها/دسترسی‌ها (Spatie) و دو کاربر نمونه را می‌سازد.

### کاربران پیش‌فرض بعد از seed

| نقش | ایمیل | رمز عبور |
|-----|--------|----------|
| مدیر | `admin@example.com` | `password` |
| پشتیبان | `test@example.com` | `password` |

---

## ۵) بیلد فرانت‌اند

برای استفاده عادی:

```bash
npm run build
```

برای توسعه با hot-reload:

```bash
composer run dev
```

یا جداگانه:

```bash
php artisan serve
npm run dev
```

---

## ۶) اجرا و ورود

1. مرورگر را باز کنید: `http://localhost:8000` (یا دامنه EnvKit پروژه)
2. با یکی از کاربران بالا وارد شوید
3. از منوی سایدبار:
   - **پشتیبان:** حضور (`/user`) و برنامه هفتگی (`/user/week`)
   - **مدیر:** پوشش هفته، مدیریت هفته، پشتیبان‌ها

---

## مسیرهای مهم

| مسیر | توضیح |
|------|--------|
| `/user/week` | اعلام ساعات هفته بعد (تلفن/چت) |
| `/user` | ثبت حضور روی ساعات تعریف‌شده |
| `/admin/coverage` | دیدن ساعات خالی و انتساب پشتیبان |
| `/admin/weeks` | بستن/باز کردن هفته |
| `/admin/agents` | مدیریت پشتیبان‌ها |

وقتی مدیر هفته را می‌بندد، پشتیبان دیگر نمی‌تواند برنامه `week` را تغییر دهد؛ ثبت حضور در `index` ادامه دارد.

---

## نصب مجدد / ریست دیتابیس (محیط لوکال)

```bash
php artisan migrate:fresh --seed
npm run build
```

هشدار: همه داده‌های جداول پاک می‌شود.

---

## تست‌ها (اختیاری)

```bash
php artisan test
```

---

## عیب‌یابی سریع

| مشکل | کار پیشنهادی |
|------|----------------|
| خطای اتصال به MySQL | سرویس MySQL روشن باشد؛ نام دیتابیس `tmas` و یوزر/پسورد `.env` درست باشد |
| صفحه سفید / استایل خراب | `npm run build` را دوباره اجرا کنید |
| منو فارسی نیست | در `.env` مقدار `APP_LOCALE=fa` بگذارید و `php artisan config:clear` بزنید |
| Forbidden روی صفحات برنامه | با کاربر seed وارد شوید یا نقش `admin` / `agent` به کاربر بدهید |
| Queue / Session خطا | `php artisan migrate` را کامل اجرا کرده باشید (`sessions`, `jobs`, `cache` جداول دیتابیسی دارند) |

---

## دستور خلاصه (از صفر)

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
# سپس .env را مثل بخش ۳ تنظیم کنید و دیتابیس tmas را بسازید
php artisan migrate --seed
npm run build
php artisan serve
```

بعد وارد شوید با `admin@example.com` / `password` یا `test@example.com` / `password`.
