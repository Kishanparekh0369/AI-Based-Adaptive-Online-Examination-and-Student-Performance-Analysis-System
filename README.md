# AIProject

AI-Based Adaptive Online Examination and Student Performance Analysis System built with Core PHP, MySQLi, Bootstrap, and JavaScript.

## Structure

- `index.php` is the public landing page.
- `app/config/database.php` is the single database bootstrap.
- `app/helpers/` contains shared helper, auth, and validation functions.
- `app/layouts/` contains reusable public, admin, and student layout files.
- `assets/css/` and `assets/js/` contain frontend assets.
- `admin/`, `student/`, and `public/` contain their respective pages.
- `database/database.sql` contains the database export.

## Run

1. Import `database/database.sql` into MySQL.
2. Confirm the database name and credentials in `app/config/database.php`.
3. Open the project through XAMPP, for example `http://localhost/AIProject/`.
