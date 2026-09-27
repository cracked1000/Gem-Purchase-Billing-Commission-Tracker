# 💎 GemDesk — Gem Purchase Billing & Commission Tracker

A self-hosted desktop-style web app for a gem-trading office that acts as the
middleman between sellers and a buyer. When a seller walks in and sells gems
(one stone, a lot, or many), you create **one bill per seller visit**, print it
or send it on WhatsApp as their proof of sale, and the app keeps the full
ledger — volumes, pieces, your commission (default 10 %, editable per bill),
and what each seller is owed.

Built with **Laravel 13 + PostgreSQL + Blade/Tailwind** (no JS build step, no
hosting needed — it runs on your laptop).

---

## Features

| Area | What you get |
|---|---|
| **Billing** | One bill per seller visit with unlimited gem rows (description, carats, pieces, price/piece), live totals as you type |
| **Commission** | Default 10 %, **changeable per bill** (any value 0–100). Subtotal − commission = net payable to seller |
| **Receipts** | Two PDFs per transaction with the same receipt number: buyer copy (seller name + full gem details, no phone/commission) and seller copy (phone + commission + net payable) |
| **WhatsApp** | Finalise a bill → the receipt PDF is **saved into the `receipts` folder** (named after the seller, e.g. `Ravi-Perera-0777123456-0608202601.pdf`), **WhatsApp opens with the seller's chat**, the folder opens with the file selected — drag & drop to send. Zero setup, no API keys |
| **Sellers** | Names, WhatsApp numbers, notes (bank details), per-seller totals & full bill history |
| **Dashboard** | Total bought, commission earned, seller payables, **pending payouts**, pieces, 12-month purchase chart, top sellers, recent bills |
| **Transactions** | Searchable / filterable (seller, payout status, free-text on bill # & gem names) |
| **Reports** | Buyer and seller versions of transaction receipts, selected-day reports, and the complete all-bills register, plus filtered CSV export |
| **Payout tracking** | Mark each bill Pending ↔ Paid so you always know who still needs money |

---

## Requirements

- **PHP 8.2+** with extensions: `pgsql, mbstring, xml, curl, zip, gd, bcmath` (on Windows, [Laravel Herd](https://herd.laravel.com) gives you all of this in one installer; on macOS use Herd or Homebrew; on Linux use your package manager)
- **PostgreSQL 13+**
- **Composer 2**

## Installation (≈ 5 minutes)

```bash
# 1. Unzip / clone the project, then:
cd gemdesk
composer install

# 2. Create the database (adjust user/password to your liking):
#    Windows: open pgAdmin or the SQL Shell (psql) and run these two lines:
sudo -u postgres psql -c "CREATE USER gemdesk WITH PASSWORD 'secret' CREATEDB;"
sudo -u postgres psql -c "CREATE DATABASE gemdesk OWNER gemdesk;"

# 3. Create your private local configuration (never share this file):
cp .env.example .env
# Windows Command Prompt: copy .env.example .env
# Then edit DB_USERNAME / DB_PASSWORD and company details in .env.

# 4. Generate a fresh app key, create tables, load demo data:
php artisan key:generate
php artisan migrate --seed

# 5. Run it:
php artisan serve
```

Open **http://127.0.0.1:8000** — you should see the dashboard with demo data.

> Shortcut scripts: `./run.sh` (Linux/macOS) or double-click `run.bat` (Windows).

### Daily auto-start (optional)
- **Windows:** put a shortcut to `run.bat` in `shell:startup`.
- **macOS/Linux:** add `@reboot cd /path/to/gemdesk && php artisan serve` via `crontab -e`.

---

## Daily workflow

1. **Seller walks in** → *Sellers → + Add Seller* (name + WhatsApp number) — once only.
2. **They sell gems** → *New Bill*: pick the seller, add every gem row (describe lots as e.g. "Garnet lot", pieces = 6, price/piece). Watch subtotal, commission and net payable update live.
3. **Adjust commission** for this deal if it's not 10 %.
4. **Save** → the receipt PDF lands in the **`receipts` folder** (inside the
   GemDesk project, named `SellerName-Phone-BillNo.pdf`), the seller's
   **WhatsApp chat opens automatically**, and the folder opens with the PDF
   highlighted — just **drag & drop it into the chat**. The receipt page also
   offers **💬 Open Chat**, **📁 Receipts Folder** and **⬇ PDF** buttons any time.
5. When you transfer the money, hit **✓ Mark payout as paid**.
6. The **Dashboard**, **Transactions**, and **Reports** pages always reflect everything.

> The demo data is there so the dashboard looks alive. Wipe it any time:
> `php artisan migrate:fresh` (empty start) — your real data starts from bill `GD-00001`.

---

## Configuration (`.env`)

| Key | Purpose | Default |
|---|---|---|
| `GEMDESK_COMPANY_NAME` | Printed on every bill/PDF | GemDesk Traders (Pvt) Ltd |
| `GEMDESK_COMPANY_ADDRESS` | 〃 | Beruwala address |
| `GEMDESK_COMPANY_PHONE` | 〃 | +94 77 … |
| `GEMDESK_COMPANY_EMAIL` | 〃 (optional) | — |
| `GEMDESK_DEFAULT_COMMISSION` | Pre-filled % on new bills (still editable per bill) | `10` |
| `GEMDESK_WA_COUNTRY_CODE` | Converts `0xxx…` numbers for wa.me | `94` |
| `GEMDESK_RECEIPTS_FOLDER` | Where receipt PDFs are saved (relative = inside the project) | `receipts` |
| `GEMDESK_WA_AUTO_OPEN` | Auto-open WhatsApp chat + receipts folder when a bill is finalised | `true` |
| `DB_*` | PostgreSQL connection | gemdesk / secret |

If you ever run `php artisan config:cache`, re-run it after changing `.env`.

---

## Backups and accidental-deletion protection

GemDesk now has a **Backups & Restore** screen. Download the verified JSON backup regularly and keep copies on a USB drive or cloud storage. It contains sellers, active and archived bills, every line item, payout status, and notes. GemDesk also keeps timestamped internal snapshots in `storage/app/backups`, automatically snapshots normal activity, and takes a snapshot before edits, payout changes, archives, and restores.

Bills use soft deletion: **Archive** hides a bill from dashboards and reports without destroying its items, and **Archived Bills** can restore it. Receipt PDFs are derived from the database and are regenerated during a restore.

For an additional PostgreSQL-level copy:

```bash
# Database dump (run weekly, keep copies on a USB stick / cloud drive):
pg_dump -U gemdesk -h 127.0.0.1 gemdesk > gemdesk-backup-$(date +%F).sql

# Restore:
psql -U gemdesk -h 127.0.0.1 gemdesk < gemdesk-backup-2026-07-31.sql
```

Also keep copies of the PDFs you download, and the whole `gemdesk/` folder.

## Running the test suite

```bash
php artisan test        # billing math, validation, data safety, backups,
                        # PDFs, reports, sellers, and WhatsApp handling
```
Tests run on an in-memory SQLite database; the app itself runs on PostgreSQL.

## Project map

```
app/Http/Controllers     Dashboard, Sellers, Invoices, Reports
app/Services             InvoiceService (all money math, one place)
app/Support              Lkr (formatting), WaNumber, WhatsAppMessage, AmountInWords
app/Http/Requests        Validation rules
resources/views          Blade templates (invoices/pdf = the PDF bill)
database/migrations      sellers, invoices, invoice_items
tests/                   Feature + unit tests
```

## Suggested upgrades when you need them

1. **WhatsApp Cloud API** — fully automatic sending (no drag & drop). Needs a
   Meta developer app, a dedicated SIM, and a permanent token. Replace
   `ReceiptStorage`/the open-chat button with a `WhatsAppService` that uploads
   the PDF from the receipts folder — the folder is already in place.
2. **Login** — `composer require laravel/breeze` if the laptop is ever shared.
3. **Per-seller default commission** — one column + a form field.
4. **Exchange-rate field / USD view** for foreign-buyer invoices.
5. **Dockerfile** for one-command setup on any machine.

---

Built on the Laravel framework (MIT). dompdf © Barry vd. Heuvel (MIT).
