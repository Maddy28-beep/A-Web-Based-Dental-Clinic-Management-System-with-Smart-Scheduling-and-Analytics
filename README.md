# A Web-Based Dental Clinic Management System with Smart Scheduling and Analytics

Laravel-based dental clinic system with online booking, staff dashboards, clinical charting, billing, inventory, and analytics.

Repository: https://github.com/Maddy28-beep/A-Web-Based-Dental-Clinic-Management-System-with-Smart-Scheduling-and-Analytics

## Features

- Smart appointment scheduling (services, dentists, working hours, closures, buffers)
- Front desk booking flow + booking confirmation
- Check-in and appointment status management
- Patient records + tooth charting with history
- X-ray upload/preview (and compare UI)
- Billing dashboard (bills, payments, locking)
- Inventory management and automatic deductions
- Analytics widgets + drilldowns with role-based scoping
- Light/Dark theme toggle (persisted per browser)

## Tech Stack

- Backend: PHP 8.2+, Laravel 12
- Frontend: Vite, Tailwind CSS
- Database: MySQL/MariaDB (or SQLite for testing)

## Requirements

- PHP 8.2+
- Composer
- Node.js + npm
- A database (MySQL/MariaDB recommended)

## Local Setup (Windows / XAMPP)

1. Install dependencies

   ```bash
   composer install
   npm install
   ```

2. Configure environment

   ```bash
   copy .env.example .env
   php artisan key:generate
   ```

3. Set your DB credentials in `.env`, then migrate + seed

   ```bash
   php artisan migrate --force
   php artisan db:seed
   ```

4. Build assets

   ```bash
   npm run build
   ```

5. Run locally

   ```bash
   php artisan serve
   ```

   For development assets:

   ```bash
   npm run dev
   ```

## Seeded Demo Accounts

After seeding, these accounts are created for local/demo use:

- Admin: `test@example.com` / `password`
- Receptionist: `staff@example.com` / `password`
- Dentist: `dentist@example.com` / `password`

## Tests

```bash
php artisan test
```

## Notes

- Do not commit `.env` or secrets (already ignored).
- If you want fresh demo data, re-run migrations and seeders.
