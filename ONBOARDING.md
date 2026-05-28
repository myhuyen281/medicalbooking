# MEDICAILBOOKING Onboarding

## Project Summary

MEDICAILBOOKING is a plain PHP and MySQL medical booking application. It runs under XAMPP/Apache and uses page-level PHP files rather than a framework or router.

The app supports:

- Public browsing and booking flows for hospitals, doctors, specialties, and medical services.
- Patient registration, login, dashboard, and appointment booking.
- Admin management for users, doctors, specialties, schedules, appointments, homepage banners, and hospital profiles.
- Hospital accounts with approval status and a restricted admin-style management area.

## Tech Stack

- Backend: Core PHP with PDO.
- Database: MySQL.
- Frontend: Bootstrap 5, Bootstrap Icons, custom CSS.
- Runtime assumption: XAMPP with Apache and MySQL.

## Key Files And Folders

- `index.php`: Homepage. Loads specialties, homepage banners, partner hospitals, featured hospitals, and searchable hospital services.
- `config/database.php`: Database constants and the `Database` PDO wrapper.
- `includes/header.php` and `includes/footer.php`: Shared public layout and navigation.
- `public/css/style.css`: Shared frontend styles.
- `database/schema.sql`: Base schema.
- `database/migration_*.sql`: Incremental schema changes for hospital accounts, banners, services, maps, approval, and related content.
- `views/auth/`: Login, logout, patient registration, and hospital registration.
- `views/admin/`: Admin and hospital-admin dashboards plus CRUD pages.
- `views/doctor/`: Doctor dashboard, appointment views, and schedule management.
- `views/patient/dashboard.php`: Patient dashboard.
- `uploads/`: Uploaded files and images.

## Main Public Pages

- `index.php`: Home/search entry point.
- `doctors.php`: Doctor listing.
- `doctor_detail.php`: Doctor detail.
- `book.php`: Doctor appointment booking flow.
- `booking_patient.php`: Patient details during booking.
- `facility_booking_options.php`: Facility booking entry.
- `facility_detail.php`: Facility or hospital detail.
- `specialty_booking.php`: Specialty booking flow.
- `lab_booking.php`: Lab booking flow.
- `imaging_booking.php`: Imaging booking flow.
- `health_package_booking.php`: Health package booking flow.
- `home_care_booking.php`: Home care booking flow.

## Data Model Overview

Core tables from `database/schema.sql`:

- `users`: Admin, hospital, and patient accounts.
- `hospitals`: Hospital/facility profile data.
- `specialties`: Medical specialties.
- `hospital_specialties`: Hospital to specialty mapping.
- `hospital_banners`: Hospital detail banners.
- `homepage_banners`: Homepage carousel/banner content.
- `hospital_services`: Services offered by hospitals.
- `doctors`: Doctors linked to hospitals and specialties.
- `schedules`: Doctor availability slots.
- `appointments`: Bookings between patients, doctors, and schedules.
- `reviews`: Doctor reviews.

## Local Setup

1. Put the project at:

   ```text
   C:\xampp\htdocs\MEDICAILBOOKING
   ```

2. Start Apache and MySQL in XAMPP.

3. Create/import the database in phpMyAdmin or MySQL CLI.

4. Important database name note:

   - `config/database.php` currently uses `medical_booking`.
   - Most migration files use `medical_booking`.
   - `database/schema.sql` currently creates and uses `medical_booking_db`.

   For the app to run without config changes, create/import into `medical_booking`, or update `DB_NAME` in `config/database.php` to match the schema database name.

5. Visit:

   ```text
   http://localhost/MEDICAILBOOKING/index.php
   ```

## Authentication And Roles

Sessions are started in shared headers and auth pages.

Roles:

- `admin`: Full admin access.
- `hospital`: Hospital admin access. Restricted in `views/admin/includes/header.php` to selected pages.
- `patient`: Public user/patient access.

Login behavior:

- Login page: `views/auth/login.php`.
- Successful admin or hospital login redirects to `views/admin/dashboard.php`.
- Successful patient login redirects to the homepage.
- Existing plaintext passwords are accepted once and upgraded to password hashes on login.
- Hospital users must have `hospital_approval_status = 'approved'` before they can log in.

## Development Notes

- There is no central router. Links point directly to PHP files.
- The app builds URLs using `$base_url = 'http://' . $_SERVER['HTTP_HOST'] . '/MEDICAILBOOKING'`.
- The public and admin headers contain a lot of inline CSS and navigation markup.
- Database access goes through the simple `Database` class:

  ```php
  $db = new Database();
  $db->query("SELECT * FROM users WHERE email = :email");
  $db->bind(':email', $email);
  $user = $db->single();
  ```

- Many pages use `try/catch` around newer queries to tolerate missing migration tables.
- UI text appears intended to be Vietnamese, but several files currently contain mojibake text. If the browser shows broken characters, the source files likely need encoding repair, not only a `<meta charset>` change.

## Suggested First Checks

- Confirm the active database name and import order.
- Run through registration and login for all roles.
- Verify admin dashboard counts after importing sample data.
- Test one full booking flow from public page to appointment creation.
- Check whether Vietnamese text displays correctly in the browser.
- Review booking conflict handling around `schedules.status` and appointment creation.

## Likely Next Improvements

- Normalize the database name between config, schema, and migrations.
- Add a seed file for demo users, hospitals, doctors, schedules, and specialties.
- Move repeated inline styles into `public/css/style.css`.
- Add shared helpers for redirects, auth checks, URL generation, and escaping.
- Add CSRF protection to mutating forms.
- Enforce schedule booking atomically in the database to avoid double booking.
