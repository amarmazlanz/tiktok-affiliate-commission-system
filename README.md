# Sistem Pengurusan Komisyen Affiliate TikTok

Sistem ini dibangunkan sebagai tugasan teknikal untuk mengurus rangkaian affiliate TikTok, akaun TikTok affiliate, import order daripada CSV TikTok, pengiraan komisyen bulanan, dan paparan laporan komisyen untuk Admin serta Affiliate.

## Tech Stack

- Laravel / PHP
- MySQL
- Blade
- Tailwind CSS
- Laragon / Apache untuk local development
- GitHub untuk version control

## Project Overview

Sistem Pengurusan Komisyen Affiliate TikTok membantu syarikat mengurus operasi affiliate secara lebih tersusun. Admin boleh mendaftarkan affiliate, menetapkan hierarchy upline/downline, mengurus akaun TikTok setiap affiliate, upload CSV order TikTok, menjalankan pengiraan komisyen bulanan, dan melihat laporan komisyen.

Affiliate pula boleh login untuk melihat ringkasan jualan personal, senarai akaun TikTok, direct downline/team, pecahan komisyen, dan recent orders.

## User Roles

### Admin

Admin mengurus keseluruhan sistem, data affiliate, import order, dan pengiraan komisyen.

### Affiliate

Affiliate melihat dashboard sendiri sahaja, termasuk jualan, akaun TikTok, team/downline, recent orders, dan pecahan komisyen.

## Admin Features

- Login admin.
- Manage affiliates.
- Manage upline/downline hierarchy.
- Manage TikTok accounts untuk setiap affiliate.
- Upload CSV order TikTok.
- Run monthly commission calculation.
- View commission report.

## Affiliate Features

- Login affiliate.
- View personal sales.
- View TikTok accounts.
- View direct downline/team.
- View commission breakdown.
- View recent orders.

## Commission Rules

Pengiraan komisyen menggunakan `estimated_commission_base`, iaitu nilai daripada column CSV `Est. Commission Base`.

Hanya order dengan `order_status = Settled` dikira sebagai eligible order.

### Personal Commission

- Seller affiliate menerima 10% daripada `estimated_commission_base`.
- `commission_type = personal`
- `rate = 0.10`

### Manager Special Rule

Jika seller affiliate mempunyai sekurang-kurangnya satu direct downline:

- Seller menerima tambahan 1% daripada jualan personal sendiri.
- `commission_type = manager_bonus`
- `rate = 0.01`

Jika seller affiliate tidak mempunyai direct downline:

- 1% daripada jualan seller diberikan kepada direct upline, jika wujud.
- `commission_type = overriding`
- `level = 1`
- `rate = 0.01`

Nota: Sistem tidak double count Level 1. Jika seller sudah mempunyai direct downline, 1% kekal kepada seller sebagai manager bonus dan direct upline tidak menerima Level 1 daripada jualan seller tersebut.

### Cascading Overriding

- Level 2: second upline menerima 0.3%.
- Level 3: third upline menerima 0.2%.

Kadar:

| Type | Rate |
|---|---:|
| Personal Commission | 10% |
| Manager Bonus | 1% |
| Overriding L1 | 1% |
| Overriding L2 | 0.3% |
| Overriding L3 | 0.2% |

## CSV Import Rules

CSV TikTok order boleh di-upload oleh Admin melalui menu CSV Upload.

Rules import:

- `Creator Username` akan dinormalize tanpa simbol `@`.
- Contoh: `@ali_shop1` akan disimpan sebagai `ali_shop1`.
- `Creator Username` mesti match dengan registered TikTok account dalam sistem.
- Jika username tidak match, order tersebut akan di-skip.
- Unmatched order tidak disimpan.
- Duplicate `Order ID` akan di-skip.
- `Est. Commission Base` digunakan sebagai asas calculation dan disimpan dalam field `estimated_commission_base`.
- `Actual Commission Base` dan `Actual Commission Payment` disimpan sebagai rujukan sahaja.

Column CSV penting:

- `Order ID`
- `Creator Username`
- `Order Status`
- `Est. Commission Base`
- `Actual Commission Base`
- `Actual Commission Payment`
- `Payment Amount`
- `Currency`
- `Quantity`
- `Time Created`
- `Payment time`
- `Time Commission Paid`
- `Platform`

## Installation Guide

Clone repository dan masuk ke folder project:

```bash
git clone <repository-url>
cd tiktok-affiliate-commission-system
```

Install dependency PHP:

```bash
composer install
```

Copy environment file:

```bash
copy .env.example .env
```

Generate application key:

```bash
php artisan key:generate
```

Setup database dalam `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tiktok_affiliate_commission_system
DB_USERNAME=root
DB_PASSWORD=
```

Jalankan migration dan seeder:

```bash
php artisan migrate:fresh --seed
```

Run local development server:

```bash
php artisan serve
```

Kemudian buka:

```text
http://127.0.0.1:8000
```

## Demo Login

### Admin

```text
Email: admin@example.com
Password: password
```

### Affiliate

Affiliate boleh dicipta melalui:

```text
Admin > Affiliate Management
```

Default password untuk affiliate yang dicipta oleh Admin:

```text
password
```

## Testing Flow

Contoh flow untuk menilai fungsi utama sistem:

1. Login sebagai Admin.
2. Pergi ke `Affiliate Management`.
3. Create affiliate `Abu`.
4. Create affiliate `Ali` dan pilih `Abu` sebagai direct upline.
5. Buka detail affiliate `Ali`.
6. Tambah TikTok accounts kepada Ali:
   - `ali_shop1`
   - `ali_shop2`
7. Upload sample CSV yang mempunyai order Settled untuk `ali_shop1` dan/atau `ali_shop2`.
8. Run commission calculation untuk April 2026.
9. Buka commission report.

Expected result untuk contoh jualan Ali RM5,000:

| Affiliate | Type | Amount |
|---|---|---:|
| Ali | Personal Commission 10% | RM500 |
| Abu | Overriding L1 1% | RM50 |
| Total Commission |  | RM550 |

Nota: Contoh ini menganggap Ali tidak mempunyai direct downline. Jika Ali mempunyai direct downline, 1% akan kekal kepada Ali sebagai manager bonus dan tidak diberikan kepada Abu sebagai Level 1.

## Assumptions

- `Settled` dianggap sebagai eligible/completed order.
- `Est. Commission Base` digunakan untuk semua calculation.
- `Actual Commission Base` dan `Actual Commission Payment` hanya disimpan sebagai rujukan.
- `Creator Username` dinormalize tanpa simbol `@`.
- Unmatched username di-skip dan tidak disimpan.
- Duplicate `Order ID` di-skip.
- UI dibuat ringkas kerana fokus utama tugasan ialah core function, data flow, dan commission logic.
- Monthly commission run menggunakan `Time Commission Paid` sebagai tarikh utama, dengan fallback kepada `Time Created` jika `Time Commission Paid` kosong.

## Main Modules

- Authentication dan role-based access.
- Admin Affiliate Management.
- TikTok Account Management.
- CSV Upload dan Order Parsing.
- Commission Calculation Engine.
- Admin Commission Report.
- Affiliate Dashboard.

## Notes for Evaluator

Project ini menekankan ketepatan logic komisyen, data import daripada CSV sebenar TikTok, role-based access, dan struktur Laravel yang mudah dikembangkan. Beberapa fungsi seperti advanced recruitment flow, payout processing, dan full analytics dashboard belum diimplement kerana berada di luar scope tugasan utama.
