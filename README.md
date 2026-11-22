# Giftly — Clothes Donation Matching Platform

Giftly connects clothing donors with charities asking for specific sizes, categories and quantities. It is a complete PHP 8 + MySQL web app with secure session authentication, role-based dashboards, pledge management and drop-off booking.

## Run with XAMPP

1. Place this folder inside `C:\xampp\htdocs\` (it is already set up there in this workspace).
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. For a fresh local install only, open [http://localhost/phpmyadmin](http://localhost/phpmyadmin), choose **Import**, and import `database/database.sql`.
4. Import the SQL files in `database/migrations/` in filename order: `20261121_giftly_ecosystem.sql`, `20261122_wardrobe_assistant.sql`, `20261123_charity_country.sql`, `20261124_stories_gallery.sql`, then `20261124_stories_gallery_illustrations.sql`. These migrations add platform features without dropping existing data.
5. For the seeded demo campaigns only, optionally import `database/seeds/stories_gallery_sample_content.sql` after the migrations. It adds two transparent progress updates only when the sample campaigns still have the verified receipt totals shown in the seed, plus clearly labeled illustrative clothing-donation photos supplied for the demo. These photos are not verified Giftly recipients or outcomes. Do not import this sample content into a live charity database.
6. Open [http://localhost/local%20gift%20voucher/](http://localhost/local%20gift%20voucher/) in your browser.

**Important:** `database/database.sql` drops and recreates core tables for demo setup. Do not re-import it into a database containing data you want to keep; apply only the additive migrations to an existing Giftly database.

The default connection is MySQL user `root` with no password and database `giftly`. To use different credentials, set `GIFTLY_DB_HOST`, `GIFTLY_DB_NAME`, `GIFTLY_DB_USER`, and `GIFTLY_DB_PASS` in your PHP/Apache environment, or update `config/database.php`.

## Enable Gemini for the assistant

The assistant uses local, rule-based answers and live request matching when Gemini is not configured. To enable Gemini-generated replies:

1. Create or rotate a Gemini API key in Google AI Studio. Treat any key pasted into a chat or committed to source control as exposed and revoke it.
2. Set `GEMINI_API_KEY` in the Windows environment available to Apache, or in a server-only Apache configuration file outside the web root. Never put the key in PHP/JavaScript source, HTML, or a public project file.
3. Optionally set `GEMINI_MODEL` to a model available to your API key. The default is `gemini-3.8-flash`.
4. Restart Apache so PHP can read the environment variables.

The browser never receives the key; PHP sends it to Gemini in a request header. When Gemini is enabled, the assistant discloses that chat messages are sent to Google. If Gemini is not configured or unavailable, users receive a clear error or the local rule-based behavior, respectively.

## Demo accounts

All seeded accounts use password: `password`

| Role | Email |
| --- | --- |
| Administrator | `admin@giftly.local` |
| Charity | `hello@harbourhouse.local` |
| Charity | `team@newstart.local` |
| Donor | `amelia@giftly.local` |
| Donor | `marcus@giftly.local` |

## Main features

- Public discovery page with keyword and category, size, season and charity filters.
- Community impact dashboard with category/urgency filters, real request totals, and an interactive white political world map. Countries shade darker as charities confirm their country; hover or keyboard focus shows the country and exact count. Charity country values are collected at registration or confirmed on the charity dashboard, and existing records stay unassigned until confirmed. Location markers use only charity-supplied coordinates. Country boundaries are Natural Earth 1:110m public-domain data redistributed by world-atlas (ISC).
- Donor registration, secure login/logout, pledges, cancellation of pending pledges, appointment booking, and giving history.
- Charity request CRUD (including multiple requirements and a secure optional image upload), pledge decisions, appointments, and receipt confirmation.
- Confirmed quantities are counted only for pledges marked `received`; pending, approved and scheduled pledges remain separate from delivered impact.
- Administrator statistics, activity log, account activation, charity verification, system-wide request review, pledge and appointment oversight.
- Donor digital wardrobe with item management, live request matches, quantity reservations, cancellation release, and receipt-confirmed donation history.
- GIFTLY Assistant with database-backed active-request search, contextual help, conversation history, server-side rate limiting, and optional Gemini-generated replies. Database matching and request links remain grounded in Giftly records.
- GIFTLY INSIGHTS dashboards with role-scoped MySQL analytics, date/category/season filters, interactive charts, CSV export, and print reports.
- Stories of Change with charity-owned drafts and publishing, campaign-linked verified delivery counts, featured and additional photos, and responsive public story pages.
- The Happy People Gallery with charity submissions, required informed-consent and guardian confirmations for children, admin approval, searchable category filters, lightbox navigation, and photo-removal requests.
- PDO prepared statements, output escaping, CSRF checks, server-side validation, session ID regeneration, role ownership checks, and transactional state changes.

## Project map

```text
config/      App and PDO configuration
includes/    Authentication, reusable helpers and layout components
assets/      App CSS, JavaScript and locally uploaded request images
auth/        Registration, sign-in and sign-out
donor/       Browsing, pledges, appointments and donor dashboard
charity/     Need requests, pledge inbox and appointment management
admin/       System oversight
database/    MySQL schema, demo data, and additive migrations
```

For production, use a dedicated MySQL user, HTTPS, an environment-managed configuration file, and a scheduled backup strategy.
