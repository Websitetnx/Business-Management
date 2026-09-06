# PERMIT — Web-Based Business Permit Management System

PERMIT is a PHP 8 and MySQL application for applicant registration, secure sign-in, new business-permit applications, permit renewal, document submission, status tracking, and LGU administrator review.

## Included features

- Applicant sign-up with email OTP verification and sign-in using PHP sessions
- Password hashing with `password_hash()` and `password_verify()`
- Email OTP password recovery without account-discovery responses
- One-time first-administrator setup that locks after use
- Additional administrator creation from the protected admin workspace
- Role-based authorization for applicant, administrator, and payment-only Treasurer pages
- New permit and renewal records stored in MySQL
- Four standard and ten conditional document uploads
- Required Occupancy Permit or Affidavit of Undertaking alternative
- Server-side file type and 5 MB size validation
- Protected document viewing through an authorized PHP endpoint
- Applicant status timeline and administrator review queue
- Approval, release, revision, rejection, notes, notifications, and audit logs
- Post-approval fee assessment and payment submission
- Protected payment-confirmation uploads with administrator or Treasurer verification
- Release guard that requires verified payment, printable payment receipts, and released business certificates
- Configurable Philippine permit-fee formula using declared capital or gross sales
- Itemized LBT, Mayor's Permit, regulatory, inspection, BFP, barangay, and community-tax assessment
- AI-assisted PDF/image requirement scanning with structured findings
- Predictive workload, processing-time, backlog, and revision analytics
- Optional AI management summaries based only on aggregate statistics
- Optional applicant geolocation with accuracy data and administrator map verification
- CSRF protection and prepared PDO statements

## Requirements

- PHP 8.0 or later
- MySQL 8 or MariaDB 10.5+
- PHP extensions: `pdo_mysql`, `fileinfo`, and `curl`
- [Composer](https://getcomposer.org/) for installing PHPMailer
- Apache, Nginx, XAMPP, WAMP, or a similar PHP server

GitHub Pages cannot run PHP. Deploy this repository to a PHP-capable server.

## XAMPP setup

1. Copy the project folder to `C:\xampp\htdocs\Business-permit`.
2. Start Apache and MySQL in XAMPP.
3. Open a terminal in the project folder and install the PHP dependencies:

   ```text
   composer install
   ```

4. Open phpMyAdmin and import `database/schema.sql`.
5. Check the database settings in `config.php`. The defaults use the usual local XAMPP MySQL account:

   ```php
   'host' => '127.0.0.1',
   'name' => 'permitflow',
   'user' => 'root',
   'pass' => '',
   ```

6. Configure SMTP as described below, then restart Apache.
7. Make sure `storage/uploads` is writable by PHP.
8. Open `http://localhost/Business-permit/setup-admin.php` and create the first administrator.
9. Sign in at `http://localhost/Business-permit/login.php`.

The setup page automatically locks after the first administrator account is created. Public sign-up always creates an applicant account.

## Hosted-server configuration

Database values can be supplied using `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` environment variables. Before first-admin setup on a non-local server, also set `ADMIN_SETUP_KEY`; the setup page will require that key.

## Email OTP and password recovery setup

Install the Composer dependencies before using account email features:

```text
composer install
```

For an existing PERMIT database, import:

```text
database/migrations/007_auth_otp.sql
```

Configure the SMTP account used to deliver verification and password-reset codes, then restart Apache/PHP:

```text
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USERNAME=your-smtp-username
SMTP_PASSWORD=your-smtp-password
SMTP_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME=PERMIT
DOCUMENT_ALERT_ADMIN_EMAIL=admin@example.com
DOCUMENT_ALERT_ADMIN_NAME=PERMIT Administrator
APP_URL=https://permits.example.gov
```

On local XAMPP, you can use Apache `SetEnv` directives or create the Git-ignored `config.local.php` file with the equivalent settings:

```php
<?php
return [
    'app_url' => 'https://permits.example.gov',
    'mail' => [
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'your-smtp-username',
        'password' => 'your-smtp-password',
        'encryption' => 'tls',
        'from_address' => 'no-reply@example.com',
        'from_name' => 'PERMIT',
        'admin_address' => 'admin@example.com',
        'admin_name' => 'PERMIT Administrator',
    ],
];
```

Use `tls` with the submission port recommended by your mail provider (commonly `587`), or `ssl` only when the provider requires implicit TLS (commonly port `465`). Keep SMTP credentials out of the repository and browser JavaScript.

For Gmail, enable 2-Step Verification and create a Google **App password** for SMTP. Put that generated app password in `SMTP_PASSWORD`; do not use the normal Google account password. Google Workspace administrators may also need to allow app-password or SMTP access for the sending account.

`DOCUMENT_ALERT_ADMIN_EMAIL` is the independent recipient for invalid-document alerts. Set `APP_URL` to the public root of the permit portal so applicant and administrator emails contain trusted absolute links. Failed mail attempts are written to the standard PHP/Apache error log with a `[PERMIT mail]` prefix; application submissions remain committed even if either recipient cannot be reached.

### Applicant account verification

Public registration creates only applicant accounts. After submitting the registration form, the applicant is sent to `verify-registration.php`, receives a one-time code by email, and must verify it before signing in. Codes expire, are single-use, and a newly issued code replaces the previous code. If delivery is delayed, use the resend action after its cooldown instead of creating another account. Never share an OTP with staff or another user.

### Forgotten passwords

Use **Forgot password?** on the sign-in page to open `forgot-password.php`, submit the account email, enter the emailed code on `verify-reset-otp.php`, and choose a new password on `reset-password.php`. For privacy, the request page gives the same response whether or not an active account exists. A reset code expires, can be used only once, and is invalidated after a successful password change; requesting a newer code invalidates the older one.

Run the standalone OTP regression check with:

```text
php tests/auth_otp_test.php
```

## AI feature setup

PERMIT uses the [OpenAI Responses API](https://developers.openai.com/api/docs/guides/file-inputs) to analyze permit PDFs and images. Structured Outputs return consistent document type, quality, confidence, extracted fields, issues, and human-review indicators.

For an existing PERMIT database, import:

```text
database/migrations/002_ai_features.sql
```

Set these server environment variables and restart Apache/PHP:

```text
OPENAI_API_KEY=your-project-api-key
OPENAI_MODEL=gpt-5.6-luna
AI_DAILY_SCAN_LIMIT=100
```

For local XAMPP, the variables can be added using Apache `SetEnv` directives in the local server configuration. Never commit an API key to GitHub or place it in browser JavaScript.

Optional setting:

```text
ALLOW_SENSITIVE_AI_SCAN=false
```

Medical-result scanning is blocked by default. Enable it only after the LGU completes its privacy and legal review. Every API request uses `store: false`, but administrators must still confirm that sending a document to the configured API project is authorized. OpenAI documents its current retention behavior in its [data controls guide](https://developers.openai.com/api/docs/guides/your-data).

The statistical forecast continues to work without an API key. The API is used for document interpretation and an optional narrative based only on aggregate, non-personal metrics. AI never makes the final permit decision; BPLO reviewers remain responsible for approval or rejection.

### Automatic upload checks

New applications, renewals, and corrected replacement files are checked automatically after the upload is safely stored. A completed scan requests document revision only when the detected type does not match, quality is below 40%, or identification confidence is below 30%. Other AI observations remain advisory.

Missing configuration, quota exhaustion, an unavailable API, rate limiting, and failed scan storage are non-blocking: the application continues to normal BPLO review. Medical results are skipped unless `ALLOW_SENSITIVE_AI_SCAN=true`. Applicants can see per-document results and replace only files marked **Needs revision**; administrators see the pre-scan results and can still run a manual re-scan.

When an automatic scan marks one or more documents invalid, PERMIT sends separate HTML and plain-text alerts to the submitted business contact email and the configured administrator address. The messages include the application reference, document type, validation reasons, AI observations, and direct applicant/admin links. A replacement file that fails its automatic re-scan triggers the same alerts.

Run the rule-boundary regression check with:

```text
php tests/auto_scan_rules_test.php
```

## Geolocation setup

For an existing PERMIT database, import:

```text
database/migrations/003_geolocation.sql
```

Applicants can explicitly select **Use current location** on new applications and renewals. PERMIT saves latitude, longitude, device-reported accuracy, and capture time, then provides administrators with an OpenStreetMap link. The written address remains required and location is optional, so an applicant can continue if permission is denied or GPS is unavailable.

Browser geolocation requires HTTPS in production. `http://localhost` is suitable for local development, but a hosted deployment must use a valid HTTPS certificate. No map or reverse-geocoding API key is required.

## Payment workflow setup

For an existing PERMIT database, import:

```text
database/migrations/004_payment_workflow.sql
```

When BPLO approves an application, the administrator must enter the assessed permit fee. The applicant can then choose an LGU-authorized payment method, enter the transaction or treasury reference, and upload payment confirmation. An administrator or payment-only Treasurer verifies or rejects the submission. Treasurer accounts cannot access application decisions, document scans, analytics, fee settings, or user management. A permit cannot be marked **Released** until the payment is verified as **Paid**. Verified payments receive a printable PERMIT receipt. Once an administrator marks the permit **Released**, the applicant receives an in-app notification and can view, print, or save the protected business permit certificate as a PDF from the application page.

For databases created before the Treasurer role was added, run:

```text
database/migrations/006_treasurer_role.sql
```

This version records and verifies payment evidence; it does not directly transfer money or connect to a third-party payment processor. Configure and publish only payment channels officially authorized by the LGU.

## Automatic fee assessment setup

For an existing PERMIT database, import:

```text
database/migrations/005_fee_assessment_formula.sql
```

Then sign in as an administrator and open **Fee settings**. Enter the rates and fixed charges from the current city or municipal tax ordinance. No tax or fee amount is assumed by the source code.

For new applications, PERMIT uses declared capital as the configurable LBT basis. For renewals, it uses previous-year gross sales. The calculated total is:

```text
Total = Local Business Tax
      + Mayor's Permit / license fee
      + regulatory and applicable inspection fees
      + BFP fire safety inspection fee
      + barangay clearance fee
      + community tax certificate fee
```

The BFP percentage is applied to the Mayor's Permit and regulatory-fee subtotal. If a fee is collected separately or does not apply in the LGU, set it to `0.00`. Each approved application stores an itemized assessment snapshot with its payment record, so later schedule changes do not rewrite past assessments. The result remains subject to BPLO and City Treasurer verification.

## Main database tables

- `users`
- `businesses`
- `applications`
- `application_documents`
- `application_status_history`
- `payments`
- `permit_fee_settings`
- `permit_business_type_rates`
- `notifications`
- `audit_logs`
- `document_ai_scans`
- `ai_analytics_reports`

## Permit requirements

Standard required uploads:

1. DTI / SEC / CDA Registration
2. BFP Application Form
3. BFP Questionnaire
4. Consent Form

Conditional uploads:

1. Lease Contract for Private Building
2. FSIC of Occupancy Valid for 9 Months
3. Occupancy Permit
4. Tax Declaration — Current Year
5. X-Ray Result and Stool Examination
6. NGA Clearance
7. Affidavit of Undertaking in Absence of Occupancy
8. Building Owner's Business Permit
9. Fire Safety Inspection Certificate — Current Year
10. Sanitary Permit — Current Year

Applicants must submit either the Occupancy Permit or the affidavit alternative.
