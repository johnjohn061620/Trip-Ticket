# Trip Ticket System

A printable **Driver's Trip Ticket & Vehicle Trip Log** (A4 landscape) with a PHP + MySQL backend:
saved tickets, editable names/signatories, and a replaceable logo.

## Features

- **Trip tickets** - create, open, duplicate, delete, search and print tickets
- **Print-ready A4 landscape** document that matches the original form layout
- **Driver accounts** - register drivers (username + password + full name); drivers log in with their own account
- **Driver login flow** - drivers immediately pick Aerox or Ford Pick up, their name is locked on the ticket, and the vehicle's plate number is filled in automatically
- **Vehicles** - each vehicle has a plate number and average consumption (Aerox 30 km/L, Ford Pick up 8 km/L); add more in Settings. Fuel used = distance / vehicle consumption
- **Per-vehicle tank balance** - a new ticket starts from that same vehicle's previous ending balance (e.g. Aerox ended at 155 -> next Aerox trip begins at 155)
- **Ticket list filters** - search, month/date range, and vehicle radio filter (All / Aerox / Ford Pick up)
- **Export** - "Export PDFs (zip)" renders each filtered ticket's print document (A4 landscape) as its own PDF inside one ZIP; "Export CSV" backs up every field; "Print backup" prints a filtered summary table
- **Trip log inputs** - date picker for dates, AM/PM time cells (auto-formatted to hh:mm AM/PM)
- **Logo** - upload, replace or remove the logo in Settings (PNG, JPG, GIF, WEBP, SVG)
- **Editable names** - agency, office, address, document code, driver defaults and all signatories
- **Auto calculations** - gasoline totals (a+b, balance) and speedometer distance, computed in the browser **and** re-verified on the server when saving
- **Database storage** - all settings and tickets live in MySQL
- **Login protection** - single admin password (change it in Settings)

## Requirements

- PHP 8.0+ (with PDO MySQL extension - enabled by default in XAMPP)
- MySQL 5.7+ / MariaDB 10.2+
- Apache with `mod_rewrite` not required

## Local setup (XAMPP)

1. Put this folder inside `C:\xampp\htdocs\` (e.g. `C:\xampp\htdocs\Trip Ticket`).
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Create the database: open `http://localhost/phpmyadmin` -> *Import* -> choose `database.sql` -> Go.
   (Or from a terminal: `C:\xampp\mysql\bin\mysql.exe -u root < database.sql`)
4. Create the config file: copy `config/env.example.php` to `config/env.php`
   and adjust credentials if your MySQL user is not `root` with an empty password.
5. Open `http://localhost/Trip Ticket/`
6. Log in with the password **`admin123`** and change it immediately under *Settings*.

## Upgrading an existing install

If the database was created from an older copy of `database.sql`, run this once
(phpMyAdmin or `mysql -u root trip_ticket < upgrade.sql`):

```sql
ALTER TABLE trip_tickets
  ADD COLUMN date_iso DATE DEFAULT NULL AFTER ticket_date,
  ADD KEY idx_date_iso (date_iso);

ALTER TABLE trip_tickets
  ADD COLUMN vehicle_name VARCHAR(60) NOT NULL DEFAULT '' AFTER plate_no,
  ADD COLUMN fuel_rate DECIMAL(6,2) NOT NULL DEFAULT 30 AFTER vehicle_name;

ALTER TABLE settings
  ADD COLUMN default_vehicle_id INT UNSIGNED DEFAULT NULL AFTER plate_no;

CREATE TABLE IF NOT EXISTS vehicles (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(60)  NOT NULL,
  plate_no      VARCHAR(60)  NOT NULL DEFAULT '',
  km_per_liter  DECIMAL(6,2) NOT NULL DEFAULT 30,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vehicle_name (name)
) ENGINE=InnoDB;

INSERT INTO vehicles (name, plate_no, km_per_liter) VALUES
('Aerox', '140106', 30.00), ('Ford Pick up', '', 8.00)
ON DUPLICATE KEY UPDATE id = id;

-- older installs without the plate column:
-- ALTER TABLE vehicles ADD COLUMN plate_no VARCHAR(60) NOT NULL DEFAULT '' AFTER name;

UPDATE settings SET default_vehicle_id = (SELECT id FROM vehicles WHERE name='Aerox')
  WHERE default_vehicle_id IS NULL;

CREATE TABLE IF NOT EXISTS drivers (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(60)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL DEFAULT '',
  full_name     VARCHAR(160) NOT NULL DEFAULT '',
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB;
```

- `date_iso` powers the month and date filters on the ticket list.
- `vehicle_name` / `fuel_rate` snapshot the chosen vehicle and its consumption on each ticket.
- `vehicles` holds the vehicles and their average km per liter (managed in Settings).
- `drivers` holds the registered driver accounts that feed the ticket driver dropdown.

## Configuration

`config/env.php` (never committed - it is in `.gitignore`):

```php
return [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'trip_ticket',
    'db_user' => 'root',
    'db_pass' => '',
];
```

Everything else (logo, names, signatories, password) is edited in the **Settings** page.

## Publishing online

> **Important:** GitHub Pages cannot run PHP or MySQL. Keep the source on GitHub
> and host the live site on any PHP + MySQL host.

### 1. Push to GitHub

```bash
cd "C:\xampp\htdocs\Trip Ticket"
git init
git add .
git commit -m "Trip ticket system"
git remote add origin https://github.com/YOUR_USERNAME/trip-ticket.git
git push -u origin main
```

`config/env.php` and uploaded logos are ignored by git, so no passwords are published.

### 2. Deploy the site (free)

**Recommended: Render (Docker).** The PDF export shells out to headless
**Chromium**, which shared PHP hosts (InfinityFree, AwardSpace) do not provide.
The included `Dockerfile` installs Chromium, so the ZIP export works online.

1. Create a free account at <https://render.com> and connect your GitHub repo.
2. Create a **Web Service** -> choose the repo -> **Runtime: Docker**
   (Render auto-detects `Dockerfile`; `render.yaml` can also set it up as a Blueprint).
3. Pick the **Free** plan and add these **Environment Variables**:
   `DB_HOST`, `DB_PORT` (TiDB uses `4000`), `DB_NAME`, `DB_USER`, `DB_PASS`.
   TLS is enabled automatically via `DB_SSL_CA` (set in `render.yaml`).
4. Provide a MySQL database. Render has no free MySQL, so use a free provider
   such as **TiDB Serverless**, **Railway**, **Aiven**, or **Clever Cloud**
   and paste its connection details into the variables above.
5. Import the schema into that database (TiDB/Railway console or phpMyAdmin):
   run `database.sql`.
6. Deploy. Render injects `$PORT` (the start script rebinds Apache) and the app
   reads credentials from the environment - no `config/env.php` on the server.

> The free Render plan sleeps after ~15 min idle, so the first visit cold-starts
> (~30-50s). Uploaded logos live on the container's ephemeral disk and reset on
> redeploy.

**Simpler alternative (no PDF export): InfinityFree / AwardSpace.** Any cPanel
host with PHP + MySQL works for viewing, editing, CSV and print backup - but not
the PDF ZIP, because they cannot run Chromium.

1. Sign up and create a **MySQL database** + user (note host, name, user, password).
2. Upload the project files via the host's **File Manager** or FTP - into `public_html/`
   (or a subfolder such as `public_html/trip-ticket/`).
3. Open the host's **phpMyAdmin** -> *Import* -> `database.sql` -> Go.
4. Edit `config/env.php` on the server with the host's database credentials.
5. Make sure `assets/uploads/` is writable (755 is enough on most hosts).
6. Visit the site, log in, change the password, upload your logo.

After that, updates are just: edit locally -> `git add . && git commit && git push`
-> redeploy (Render) or upload the changed files to the host.

## Project structure

```
database.sql          Database schema + default settings
Dockerfile            PHP 8.3 + Apache + Chromium image for free hosting (Render)
render.yaml           Render Blueprint (free web service + DB env vars)
docker/start.sh       Rebinds Apache to Render's $PORT
config/env.php        Database credentials (copy from env.example.php; env vars used in Docker)
config/db.php         PDO connection
includes/             Helpers, page header/footer
index.php             Ticket list: search, filters, vehicle radios, duplicate, delete, export buttons
ticket.php            Ticket editor / printer (A4 landscape)
vehicle_pick.php      Vehicle chooser shown right after login (drivers and admins)
drivers.php           Driver accounts: register, edit, activate/deactivate, delete
settings.php          Logo, names, signatories, vehicles, password
export.php            Filtered backup: CSV download or printable summary table
export_pdfs.php       Filtered backup: one A4 PDF per ticket, bundled in a ZIP
actions/              save_ticket, prev_balance, logout
assets/css, assets/js Styles + gasoline/speed calculations
assets/uploads/       Uploaded logos (script execution blocked)
Trip Ticket.html      Original standalone version (reference only)
```

## Security notes

- All SQL uses prepared statements; all output is HTML-escaped.
- Uploaded files are validated by extension **and** MIME type, renamed randomly,
  and `.htaccess` prevents PHP execution inside `assets/uploads/`.
- Change the default password (`admin123`) before going live.
- Keep `config/env.php` out of version control (already in `.gitignore`).
