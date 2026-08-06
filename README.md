# AIHub

AIHub is a full-stack web platform that aggregates multiple AI tools behind a single account system, credit-based usage, and an admin panel. Built with PHP, MySQL, HTML/CSS, and JavaScript.

## Features

- **Authentication** — signup/login with OTP email verification, forgot/reset password flow, rate-limited login and OTP attempts to resist brute force
- **Credits system** — each AI tool has a configurable credit cost; usage is deducted and logged per user
- **Dashboard** — browse available AI tools, launch them, see remaining credits
- **Favorites** — users can bookmark tools for quick access
- **History** — per-user log of tool usage
- **Pricing / top-up page** — plan and credit purchase flow
- **Profile page** — account management
- **Admin panel** — separate authenticated area to manage tools, users, and view audit logs
- **Audit logging** — admin actions are recorded for accountability
- **Email delivery** — PHPMailer over Gmail SMTP for OTPs and password resets

## Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP (PDO + MySQL) |
| Database | MySQL |
| Frontend | HTML, CSS,JavaScript |
| Mail | PHPMailer (Gmail SMTP) |
| Local dev | WAMP |

## Project structure

```
aihub2/
├── admin/                  # Admin panel (separate auth, tool/user management)
├── database/
│   ├── schema.sql          # Full database schema
│   └── migrations/         # Incremental schema changes
├── lib/PHPMailer/          # Vendored PHPMailer library
├── logs/                   # Runtime logs (SMTP log is git-ignored)
├── db.php                  # PDO database connection
├── email_config.php        # SMTP config (reads from environment variables)
├── audit_logger.php        # Admin action logging helpers
├── rate_limiter.php        # Login/OTP brute-force protection
├── otp_functions.php       # OTP generation + email sending
├── index.php, dashboard.php, login.php, signup.php, ...
├── .env.example            # Reference list of required environment variables
└── .gitignore
```

## Setup (local development with WAMP/XAMPP)

1. **Clone the repo** into your local server's web root, e.g. `C:\wamp64\www\aihub`.

2. **Create the database.**
   - Create a MySQL database named `aihub` (or your own name).
   - Import the schema: `database/schema.sql`.
   - Apply migrations in order: files under `database/migrations/`.

3. **Set environment variables.** This project reads configuration from real environment variables (not a `.env` loader), so set them in your web server / OS environment. See `.env.example` for the full list:
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
   - `SMTP_USERNAME`, `SMTP_PASSWORD` (a Gmail **App Password**, not your normal password — create one under Google Account → Security → 2-Step Verification → App passwords)

   On WAMP, you can set these in Apache's `httpd.conf` (`SetEnv VAR value`) or in a `php.ini` override, then restart Apache.

4. **Start Apache + MySQL** via your WAMP/XAMPP control panel.

5. **Visit** `http://localhost/aihub/index.php` and sign up for an account.

6. **Admin access:** create an admin row directly in the `admins` table (see schema) and log in at `admin/admin_login.php`.

## Security notes

- CSRF tokens protect all state-changing admin and user forms.
- Passwords are hashed with `password_verify()`/`password_hash()`.
- Login and OTP requests are rate-limited (default: 5 attempts / 15 minutes).
- All SQL access goes through PDO prepared statements.
- No credentials are hardcoded — `db.php` and `email_config.php` read from environment variables only.

## License

MIT — see [LICENSE](LICENSE).
