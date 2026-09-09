# Vehicle Requisition & Scheduling (VRS)

A working native PHP 8 application with PDO, MySQL/MariaDB, HTML, CSS, and vanilla JavaScript. No frameworks, build tools, CDNs, JavaScript libraries, or external fonts are required.

## Try the local demo

With XAMPP Apache running, open **http://localhost/VRS/** and select **Enter as administrator**. A clearly labeled demo workspace is created automatically using PDO SQLite in `storage/demo.sqlite`. Changes persist locally. MySQL is not required for this preview.

Alternatively, from this directory:

```sh
/Applications/XAMPP/xamppfiles/bin/php -S 127.0.0.1:8086 router.php
```

Open **http://127.0.0.1:8086/**. Always include `router.php` when using PHP's development server so private files are blocked.

All demo accounts use password **Demo@12345**:

| Role | Email |
| --- | --- |
| Administrator | daniel@vrs.local |
| Requester | requester@vrs.local |
| Supervisor | supervisor@vrs.local |
| Administrative officer | admin@vrs.local |
| Dispatcher | dispatch@vrs.local |

Sample schedules are relative to the date the demo database is first initialized. To reset only the disposable demo data, stop the application and remove `storage/demo.sqlite`; it is regenerated on the next request. Do not remove an operational database.

## Browser installation (XAMPP)

1. Start Apache and MySQL in XAMPP.
2. Open **http://localhost/VRS/setup.php** on the server, or **http://127.0.0.1:8086/setup.php** when using the development server. Opening install.php in a browser also takes you to the wizard.
3. Review the system checks. Enter your MySQL host, port, database name, username, and password. Use an empty database, or check **Create the database if it does not exist** (requires database creation permissions).
4. Optionally test an existing database connection. Tests do not create databases. With JavaScript enabled, connection testing keeps all form entries; passwords are never inserted into the HTML response.
5. Enter your organization and initial administrator details, then select **Install workspace**.
6. Sign in with your new administrator. The wizard saves config/local.php, imports the schema, creates the account and office, and switches off demo mode. Existing SQLite demo records remain on disk and are not migrated.

The browser installer accepts localhost requests only, uses CSRF protection, serializes installations, refuses nonempty databases, and locks whenever config/local.php exists. Existing configuration files are never overwritten. For remote hosting, use the CLI steps below. MySQL DDL cannot be rolled back: after an interrupted schema import, inspect the database before retrying. The wizard will refuse a partially populated database.

## MySQL installation via command line

1. Start Apache and MySQL in XAMPP. PHP requires `pdo_mysql`, `mbstring`, `fileinfo`, and `gd` (vehicle photographs). Demo mode additionally requires `pdo_sqlite`.
2. Create an **empty** MySQL 8 / MariaDB database, using UTF-8, and a database user with access to that database.
3. Copy `config/local.example.php` to `config/local.php`; set the database DSN, username, and password. Keep `demo => false`. The local configuration is gitignored and Apache-protected.
4. Run `php install.php` from this directory (or use XAMPP's full PHP path above). Enter a new administrator name, email, and password. This CLI-only installer refuses databases that already contain tables. It loads `database/vehicle_requisition.sql`, creates an initial office and administrator, and seeds settings. It does **not** install demo accounts in MySQL. If schema creation fails, inspect and recreate the empty database before retrying; MySQL DDL is not transactional.
5. Sign in and configure your organization and signature labels in Settings. Add offices, users, vehicles, and drivers, then create requests.
6. The web-server account needs write access to `assets/uploads/`; demo mode also needs write access to `storage/`. Enable Apache `.htaccess` overrides. For an alternative web server, deny access to `config/`, `database/`, `includes/`, `storage/`, `tests/`, dotfiles, and prevent scripts from executing in uploads. Use HTTPS for an operational deployment; session cookies automatically use Secure under HTTPS.

A setup guide is available at `setup.php`. Demo data does not automatically migrate into MySQL. Disable demo mode before publishing an operational workspace.

## Included workflows

- Responsive dashboard: fleet availability now, active trips, today's schedules, pending approvals, returned trips, overdue vehicles, and maintenance counts.
- Requisitions: drafts, submission, office binding, preferred driver, passenger list, vehicle type, schedule, purpose, destination, fuel requirements, corrections, and eligible cancellation.
- Office-scoped supervisor approval and administrative assignment; approval decisions require password confirmation and are recorded in history.
- Vehicle **and driver** overlap detection, configurable turnaround time, maintenance blocks, expired registration/license checks, capacity validation, and overdue-dispatch availability checks. Final approval serializes assignments with database locks and repeats validation. Administrator conflict overrides require an auditable justification of at least 15 characters.
- Native calendar: month, week, day, and vehicle timeline; navigation, date selection, vehicle/type/office/driver/status/destination filters, event detail dialog, and daily schedule printing. Requesters see their requests; supervisors see their office; administrative and dispatch roles see all requests. All roles can see fleet maintenance blocks.
- Fleet records including validated, re-encoded JPEG/PNG vehicle photographs; drivers, offices, users, roles, and account activation. Deactivate records instead of deleting operational history.
- Dispatch, return, actual times, odometers, distance, condition, incident remarks, and final completion.
- Printable approved requisitions with saved approvers and trip records; browser Print / Save as PDF; print-view audit. The template follows the pasted specification: no original form image was attached, so it is not an exact reproduction of an agency form.
- Reporting date/office filters, office totals, scheduled vehicle hours, mileage, requested fuel, driver/requester trip history, late returns, outcomes, CSV export, and printing. Fuel figures represent **requested allocation**, not measured fuel consumption. Audit and approval histories have dedicated views. Maintenance appears on the maintenance page and calendar.
- In-app notifications for submission, review, and status changes; unread indicators and mark-as-read.

User records include employee name, designation, and office; passengers are stored as text on each requisition instead of separate employee/passenger tables. Availability is derived from schedules, active dispatches, resource statuses, and blocks; a future reservation does not block a vehicle for a whole day. The availability API is advisory; final approval repeats checks inside a transaction.

## Roles

Requesters can create and manage their own eligible requests. Supervisors review their own office. Administrative officers manage vehicles/drivers, maintenance and final assignment, and access reports. Dispatchers record release, return, and completion. Administrators can manage all modules and explicitly override scheduling conflicts. Office record supervisor names are descriptive; actual approval authority comes from the user's role and office assignment.

## Security

PDO prepared statements; output escaping; password hashing; login attempt throttling (five attempts per email/IP per 15 minutes); CSRF protection for all mutations, including logout and print logging; strict sessions and session regeneration; server-side role and record checks; restricted uploads with image decoding/re-encoding; transaction-protected workflow transitions; CSV formula escaping; and critical-action audit records. Filesystem permissions and HTTPS remain deployment responsibilities.

## Verification

```sh
# Isolated domain checks; in-memory SQLite
/Applications/XAMPP/xamppfiles/bin/php tests/domain.php

# Full HTTP workflow; copies the project to a temporary folder,
# runs a local PHP server, and removes its disposable database afterward.
python3 tests/smoke.py /Applications/XAMPP/xamppfiles/bin/php

# Syntax checks
find . -name '*.php' -print0 | xargs -0 -n1 /Applications/XAMPP/xamppfiles/bin/php -l
```

The HTTP suite covers every main page, record forms, private-file protection, office/role access, correction and resubmission, password-confirmed approvals, assignment conflicts, printing, dispatch, odometer validation, returns/completion, CSRF, record/settings persistence, CSV reports, notifications, and audit history. The browser installer was also verified against local MariaDB with a disposable database: schema import, account creation, sign-in, configuration activation, demo-session invalidation, and reinstallation protection passed. The application workflow suite uses SQLite.

### Installation tests

Run the isolated validation and account-seeding checks:

    /Applications/XAMPP/xamppfiles/bin/php tests/installer.php

With local XAMPP MySQL available as root with no password, run the full browser installation test:

    python3 tests/install_mysql.py /Applications/XAMPP/xamppfiles/bin/php

This copies the application into a temporary directory and creates a randomly named vrs_install_test_ database. Only that test database and temporary copy are removed afterward. It does not change the active application configuration or demo records.

### XAMPP macOS permissions

Apache normally runs as daemon, while project files may belong to your macOS account. If the installer shows “Needs attention” for writable folders, grant access to that account from the project directory (without making folders world-writable):

    chmod +a 'user:daemon allow read,write,append,execute,delete,readattr,writeattr,readextattr,writeextattr,readsecurity,file_inherit,directory_inherit' config storage assets/uploads

If a demo database was previously created by the command-line server, grant Apache access to that existing file too:

    chmod +a 'user:daemon allow read,write,append,readattr,writeattr,readextattr,writeextattr,readsecurity' storage/demo.sqlite

Refresh setup.php after changing permissions. New files inherit Apache's ACL. When collaborating from the CLI as well, give your own macOS account an equivalent inheritable ACL so it can read configuration created by Apache.

To verify the actual Apache runtime rather than the PHP development server:

    python3 tests/install_mysql.py /Applications/XAMPP/xamppfiles/bin/php --apache

This mode uses a disposable application copy under the VRS directory and a temporary MySQL database, then removes both. It expects Apache at http://localhost/VRS/ and the XAMPP daemon account.

## Destination search and map

New and edited requisitions use a required address selector: open the place dropdown, type at least three characters, then choose a result to preview its pin on an OpenStreetMap map. The chosen address is saved in the existing destination field. Existing destinations remain selectable; map coordinates are preview-only and a saved address must be searched again to preview its pin.

Search uses the Photon public service over HTTPS from the browser. It requires internet access and JavaScript; search runs after a typing pause, with per-page caching, request cancellation, a timeout, and at least one second between requests. Map previews contact OpenStreetMap. No API key is required. Photon’s public service has no availability guarantee and is intended for reasonable request volumes; use a private Photon endpoint for higher traffic. Set `address_search_url` in `config/local.php` to an HTTPS Photon-compatible endpoint with browser CORS enabled. See https://github.com/komoot/photon for service documentation.

Dropdown regression checks: open `tests/place-dropdown.html` directly from the filesystem in Chrome. This isolated fixture uses mocked search responses and checks dropdown reopening, keyboard/mouse selection, map visibility, validation, and search failure recovery; it does not contact the address provider. The application deliberately blocks serving the tests directory over HTTP.
