# Vehicle Requisition & Scheduling (VRS)

A working native PHP 8 application with PDO, MySQL/MariaDB, HTML, CSS, and vanilla JavaScript. No frameworks, build tools, CDNs, or external fonts are required. The print slip includes a locally bundled MIT-licensed QR generator.

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
- Administrator-only approval and vehicle/driver assignment; approval decisions require password confirmation and are recorded in history.
- Vehicle **and driver** overlap detection, configurable turnaround time, maintenance blocks, expired registration/license checks, capacity validation, and overdue-dispatch availability checks. Final approval serializes assignments with database locks and repeats validation. Conflicting vehicles and drivers are disabled in the assignment selectors. Administrators can explicitly enable schedule overrides and use a separate Override & approve button with password confirmation and an auditable justification of at least 15 characters. Merely entering a reason does not override a normal approval. Unavailable resources, expired registration/licenses, and capacity failures cannot be overridden.
- Native calendar: month, week, day, and vehicle timeline; navigation, date selection, vehicle/type/office/driver/status/destination filters, event detail dialog, and daily schedule printing. Administrators see all approved fleet schedules, including trips that are dispatched, returned, or completed. All other roles see only their own approved trips and those same subsequent statuses. Unapproved requests are hidden for everyone, including their requester; rejected and cancelled requests are excluded as well. This scope applies to the calendar, its filters and timeline, the dashboard daily schedule, and printed daily schedules. Global maintenance blocks are excluded from these personal calendars; maintenance and conflict checks remain available in their existing management workflows.
- Fleet records including validated, re-encoded JPEG/PNG vehicle photographs; drivers, offices, users, roles, and account activation. Deactivate records instead of deleting operational history.
- Dispatch, return, actual times, odometers, distance, condition, incident remarks, and final completion.
- Printable approved requisitions with saved approvers and trip records; browser Print / Save as PDF; print-view audit. The template follows the supplied Requisition_Slip_for_Vehicle_Use.pdf, including its accountability acknowledgement, approval signatures, and office record. A locally generated QR code encodes only the saved slip reference (for example, VR-2026-0001).
- Reporting date/office filters, office totals, scheduled vehicle hours, mileage, requested fuel, driver/requester trip history, late returns, outcomes, CSV export, and printing. Fuel figures represent **requested allocation**, not measured fuel consumption. Audit and approval histories have dedicated views. Maintenance appears on the maintenance management page.
- In-app notifications for submission, review, and status changes; unread indicators and mark-as-read.

User records include employee name, designation, and office; passengers are stored as text on each requisition instead of separate employee/passenger tables. Availability is derived from schedules, active dispatches, resource statuses, and blocks; a future reservation does not block a vehicle for a whole day. The availability API is advisory; final approval repeats checks inside a transaction.

## Roles

Requesters can create and manage their own eligible requests. Supervisors can view requests from their own office but cannot approve them. Administrative officers manage vehicles/drivers and maintenance, and access reports, but cannot approve requests. New submissions go directly to the Administrator, who assigns the vehicle and driver and approves, rejects, or returns the request. Existing requests awaiting supervisor review can be processed directly by the Administrator. Dispatchers record release, return, and completion. Administrators can manage all modules and explicitly override scheduling conflicts. Office record supervisor names are descriptive; approval authority belongs only to users with the Administrator role.

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

## Vehicle slip template and QR

The vehicle slip uses `includes/print-slip.php` and `assets/css/vehicle-slip.css`, separate from the daily schedule print styles. It recreates the supplied one-page Letter form with populated personnel, passengers, vehicle and journey details, fuel choices, accountability acknowledgement, approval names/dates, and return records. Handwritten signature lines remain blank. Long passenger lists or text can continue onto additional pages rather than being clipped.

The locally bundled Project Nayuki QR generator (`assets/js/vendor/qrcodegen.js`, MIT license retained in the file, downloaded from https://www.nayuki.io/res/qr-code-generator-library/qrcodegen.js) creates a vector QR with a four-module white border. The payload is exactly the saved requisition reference, not a URL or personal data. JavaScript is required for QR generation; the Print button enables once generation succeeds. No external QR service or network connection is needed.

Run the isolated form rendering checks with `php tests/print-slip.php`. For a populated preview without touching the database, run `php tests/print-slip.php --render > /tmp/vrs-slip-preview.html` and open that file in a browser. The regular HTTP smoke suite also checks the populated print route.

Calendar ownership and the administrator exception for approved fleet schedules are enforced on the server using the signed-in user ID and role. Calendar API query parameters cannot expand access. Approval, dispatch, requisition lists, and reporting keep their existing role permissions.

Assignment control checks: open `tests/assignment.html` directly in a browser. The fixture mocks availability responses and verifies blocked selections, explicit override mode, cancellation, reason validation, and network failure recovery.

### Authenticator settings

Open your profile using your name/avatar in the top bar, then use **Account security** to turn the authenticator on or off. Turning it off requires your current password, removes the enrolled secret and recovery codes, and permits password-only sign-in. Turning it on requires password confirmation followed by a valid code from the displayed QR code or manual setup key. Save the ten new recovery codes before dismissing them. Other password-only sessions are signed out when the authenticator is enabled. First-time accounts still enroll during sign-in by default. The profile shows the actual enrollment status.

### AI booking assistant

Open **AI booking assistant** in the sidebar. Describe the destination, departure/return times, vehicle type, passengers and purpose. The assistant collects missing details and fills an editable review form. **Submit request for approval** creates a requisition for the signed-in user's office, pending Administrator approval. AI cannot approve requests, assign vehicles, or reserve a schedule. Availability and conflict checks still run during administrator assignment.

The booking assistant defaults to **Ollama Cloud**, using `gpt-oss:20b` directly at `https://ollama.com/api/chat`. Copy `config/ollama.local.example.php` to `config/ollama.local.php` and enter your Ollama API key, or set `OLLAMA_API_KEY` in the PHP server environment. The private configuration file is ignored by Git and preserved by the updater; configure it separately on each server. No local Ollama installation is required. The assistant currently accepts text only, so no vision model is used.

Override the server or model using `OLLAMA_URL` and `OLLAMA_MODEL` in the PHP server environment, or `ollama_url` and `ollama_model` entries in `config/local.php`. Settings in `config/ollama.local.php` take precedence over both. The URL is the server root (without `/api/chat`). To use local inference, set `ollama_url` to `http://127.0.0.1:11434` and `ollama_model` to an installed local model such as `llama3.2:latest`. For an Ollama server on another computer, configure that server's reachable address. Preserve all existing database settings. Restart PHP/Apache after changing environment variables.

The integration uses [Ollama native chat](https://docs.ollama.com/api/chat) with non-streaming responses and a 120-second timeout. Local models use [structured outputs](https://docs.ollama.com/capabilities/structured-outputs). Ollama Cloud does not support the `format` schema parameter, so cloud requests include the JSON schema in the instructions and still pass through the same strict application validation. PHP cURL is required. The application validates all AI-generated trip fields before updating the review form. Connection, missing-model, malformed-output and timeout errors leave the request unsubmitted.

OpenAI remains optional: set `BOOKING_AI_PROVIDER=openai` (or `booking_ai_provider` in local configuration), plus `OPENAI_API_KEY`; optional `OPENAI_MODEL` defaults to `gpt-4o-mini`. This mode uses the [Responses API with structured outputs](https://developers.openai.com/api/docs/guides/structured-outputs), `store: false`, and a 40-second timeout. API keys stay server-side and must never be committed or placed in JavaScript. The app does not fall back to OpenAI if Ollama fails.

Both modes allow 20 messages per user per hour. Only the submitted conversation and trip draft are sent to the selected provider; no fleet database, credentials or other users' trips are included. Conversation state stays in the authenticated session and expires after 30 minutes. Start over clears it; successfully submitting the review clears it too.

Checks: `php tests/booking-assistant.php` and `python3 tests/smoke.py`. Live AI checks require the configured provider to be reachable and authenticated; direct Ollama Cloud and OpenAI require their respective API keys. Offline tests do not contact either provider.


The assistant also answers system-help questions and searches vehicle/driver availability. Examples: **Who can approve requests?**, **Which vans are available now?**, **Show unavailable drivers tomorrow from 8 AM to 5 PM**, and **Find vehicle SAB 1234**. The review form stays hidden until a complete booking is prepared; help and search questions do not fabricate or overwrite booking details. Failed submissions reopen the form for correction.

Availability is computed by PHP from current records, scheduled trips, turnaround buffers, maintenance blocks, and registration/license validity. Results show names/plates, available/unavailable status and a general reason, without exposing trip destinations, passengers, references, driver contact details or license numbers. With no dates, the assistant checks right now. Results refresh every 30 seconds while the page is visible and can be refreshed manually. These checks are advisory; the Administrator still performs final assignment and approval. Availability data is rendered by the app and is not sent to the AI provider. Additional checks: `php tests/assistant-tools.php`.

### Developer center and GitHub updates

Administrators can open **Developer center**. Click **Check for updates** to check the configured GitHub branch. Opening the page does not contact GitHub, and there are no automatic checks. Choose **Update now** to download the selected commit as a ZIP, validate its files and PHP syntax, and show the patch preview. Downloads and installations require manual actions. Installation requires an administrator password and confirmation.

Git is not required. The updater never writes `.git`. Configure `update_repository`, `update_branch`, and `update_php_binary` in `config/local.php`; defaults are `MrMilar12/VRS`, `main`, and the server PHP CLI. PHP needs cURL and ZipArchive. Validation prefers proc_open with PHP CLI lint; when disabled, the tokenizer extension with TOKEN_PARSE checks syntax without executing code. The fallback does not perform every CLI compile-time check. Private repositories can use a server environment variable `VRS_GITHUB_TOKEN`. The server needs HTTPS access to api.github.com and codeload.github.com.

The PHP server account needs write access to application code and storage. Grant access only to the application's code directories/files, without changing Git metadata or runtime permissions. Protected paths include dotfiles (except application .htaccess), storage, assets/uploads, and config/local.php. ZIP traversal, symlinks, duplicate paths, oversized archives, and invalid PHP are rejected.

Applying backs up the actual local files before replacing them, including uncommitted local edits. Push intended local changes before patching to retain them in the release. Backups and the installed version manifest live in storage. Rollback restores the previous files, provided they have not changed since installation. Removed files are deleted only when recorded by a previous archive deployment; unmanaged files are preserved. Database migrations do not run automatically. The checkout's Git HEAD remains unchanged after archive deployments; the stored installed-version manifest takes precedence.

Checks run only when requested, with no polling, webhook, or background service. The first installation can read the existing Git HEAD without invoking Git; ZIP-only installations start with an unknown version until their first patch. Keep storage backups private and include them in disk-space monitoring. Do not delete the latest backup while rollback is needed.

Validation: `php tests/updater.php` uses disposable ZIP archives to test patching, rollback, runtime preservation, stale previews, locking, archive validation, and PHP linting. `python3 tests/smoke.py` checks page rendering and access controls.

Restricted-host validation: `php -d disable_functions=proc_open tests/updater.php`. If an older deployed updater still requires proc_open, upload the revised `includes/updater.php` manually once before using web patches.


### InfinityFree updater repair

The updater supports disabled process execution and missing CLI binaries, using PHP tokenizer validation. GitHub downloads use cURL or verified HTTPS streams (allow_url_fopen and OpenSSL required for streams). For private repositories, update_github_token may be set in the protected config/local.php. Host-controlled file limits and permissions still apply. Upload the revised includes/updater.php manually once to repair an older hosted updater; this does not migrate databases or configure a new hosting account.
