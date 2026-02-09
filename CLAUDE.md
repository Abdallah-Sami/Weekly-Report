# CLAUDE.md - Weekly Reports Management System

## Project Overview

A weekly reports management system (نظام إدارة التقارير الأسبوعية) for the Research and Industrial Development Agency (وكالة البحوث والتنمية الصناعية). Built with vanilla PHP + MySQL, no framework. All UI text is in Arabic (RTL layout).

## Tech Stack

- **Backend**: PHP 7.4+ (vanilla, no framework)
- **Database**: MySQL 5.7+ with PDO (prepared statements)
- **Frontend**: Bootstrap 5 RTL + Font Awesome 6 + SweetAlert2
- **Server**: Apache with mod_rewrite
- **Auth**: Session-based with bcrypt password hashing
- **Language/Direction**: Arabic (RTL), UTF-8/utf8mb4

## Project Structure

All application code lives inside `weekly-reports/`:

```
weekly-reports/
├── index.php                    # Entry point - redirects to dashboard or login
├── config/database.php          # DB config, constants, Database singleton class
├── includes/
│   ├── functions.php            # ALL shared functions (auth, CSRF, DB helpers, uploads, dates, etc.)
│   ├── header.php               # HTML head + navbar + sidebar include + starts content area
│   ├── sidebar.php              # Left sidebar navigation (role-aware)
│   └── footer.php               # Closes HTML, loads JS, renders $extraScripts
├── pages/
│   ├── login.php                # Standalone login page (own HTML structure)
│   ├── dashboard.php            # Main dashboard with stats and quick actions
│   ├── create_report.php        # Create weekly report form (admin/manager only)
│   ├── edit_report.php          # Edit existing report
│   ├── view_report.php          # View report details + attachments
│   ├── reports_archive.php      # Paginated report list with filters
│   ├── users_management.php     # CRUD for users (admin only)
│   ├── upload_annual_plan.php   # Upload/list annual plans
│   └── notifications.php        # Notification list with pagination
├── ajax/
│   ├── mark_notification_read.php  # Mark notification(s) as read (JSON response)
│   ├── upload_attachment.php       # Upload file attachment (JSON response)
│   └── delete_report.php          # Delete report with CSRF check (JSON response)
├── auth/
│   ├── login_process.php        # Handles login POST, session creation, redirect
│   └── logout.php               # Destroys session, clears cookie
├── cron/
│   └── send_weekly_reminder.php # Cron job: sends reminders to managers every Monday
├── assets/
│   ├── css/style.css            # Custom CSS with CSS variables, responsive design, print styles
│   ├── js/script.js             # Sidebar toggle, notification AJAX functions
│   └── img/.gitkeep
├── uploads/
│   ├── .htaccess                # Blocks PHP execution in uploads dir
│   ├── attachments/.gitkeep     # Report attachments storage
│   └── annual_plans/.gitkeep    # Annual plan files storage
├── sql/database.sql             # Full schema + seed data
├── .htaccess                    # URL rewriting, blocks config access
└── README.md                    # Setup instructions (Arabic)
```

## Database Schema

Database: `weekly_reports_db` (utf8mb4)

| Table | Purpose |
|-------|---------|
| `departments` | 4 departments (Industrial Dev, Learning Resources, Innovation, Research) |
| `users` | Users with roles: admin, manager, employee. FK to departments |
| `reports` | Weekly reports with week_start/week_end dates, status (draft/published) |
| `report_details` | Per-department achievements and notes within a report |
| `attachments` | Files attached to report_details (evidence/شواهد) |
| `annual_plans` | Yearly plans uploaded per department |
| `notifications` | User notifications (reminder, report_created, report_updated) |
| `activity_logs` | Audit log of user actions with IP addresses |

Key relationships:
- `reports` -> `report_details` (one-to-many, CASCADE delete)
- `report_details` -> `attachments` (one-to-many, CASCADE delete)
- `users.department_id` -> `departments.id` (SET NULL on delete)

## Role-Based Access Control

Three roles with hierarchical permissions:

| Role | Capabilities |
|------|-------------|
| **admin** | Full access: CRUD all reports, manage users, view all plans, delete reports |
| **manager** | Create/edit reports for own department only (current week), upload annual plans |
| **employee** | Read-only access to reports and plans |

Role checks use functions: `isAdmin()`, `isManager()`, `isEmployee()`, `requireRole([...])`.

## Architecture Patterns

### Page Pattern
Every page in `pages/` (except `login.php`) follows this pattern:
```php
$pageTitle = 'Page Title';
require_once __DIR__ . '/../includes/header.php';  // starts session, requires login, outputs HTML head
requireRole(['admin', 'manager']);                   // optional role check

// PHP logic (queries, form handling)

// HTML output (Bootstrap 5 RTL)

// Optional: set $extraScripts for page-specific JS
$extraScripts = "<script>...</script>";
require_once __DIR__ . '/../includes/footer.php';
```

### Database Access
- Singleton `Database` class in `config/database.php`
- Helper functions: `db()`, `dbQuery($sql, $params)`, `dbFetchOne()`, `dbFetchAll()`
- ALL queries use PDO prepared statements (parameterized)

### Security
- CSRF tokens: `generateCsrfToken()`, `verifyCsrfToken()`, `csrfField()` for forms
- XSS prevention: `e($string)` wraps `htmlspecialchars()` - use on ALL output
- Input sanitization: `sanitize()` for user inputs
- Session: httponly cookies, strict mode, 30-min timeout, regenerate ID on login
- File uploads: extension whitelist (jpg, jpeg, png, pdf, docx, xlsx), 5MB max, unique filenames
- `.htaccess` blocks PHP execution in uploads directory

### AJAX Endpoints
All return JSON with `Content-Type: application/json; charset=utf-8`.
Pattern: check auth -> validate input -> perform action -> return `{success: bool, ...}`.

### Flash Messages
- `setSuccess($msg)` / `setError($msg)` -> stored in `$_SESSION`
- `showAlert()` renders Bootstrap alerts (called in header.php)

### Navigation
- `redirect($url)` prepends `SITE_URL` if not absolute
- `isActivePage($page)` for sidebar active state
- `getCurrentPage()` returns current filename without extension

## Key Conventions

### PHP
- All files start with `require_once __DIR__ . '/../includes/functions.php'`
- Output escaping: always use `e()` for user data in HTML
- Date functions: `formatDate()` (Y/m/d), `formatDateTime()` (Y/m/d h:i A)
- Week tracking: `getCurrentWeekRange()` returns Monday-Friday
- Activity logging: `logActivity($action, $description)` for audit trail
- Notifications: `createNotification()` for single user, `createBulkNotification()` for groups

### Frontend
- Bootstrap 5 RTL (loaded from CDN)
- Font Awesome 6 for icons
- SweetAlert2 for confirmation dialogs and alerts
- CSS custom properties defined in `assets/css/style.css` (--primary: #2563eb, --sidebar-width: 260px)
- Card-based layouts with `border-0 shadow-sm` pattern
- Tables use `table-hover align-middle` classes
- Print styles hide sidebar/navbar
- Responsive: sidebar collapses on mobile (<992px) with overlay

### JavaScript
- Vanilla JS (no jQuery or framework)
- `getSiteUrl()` extracts base URL from DOM
- Notification functions: `markNotificationRead(id)`, `markAllRead()`, `updateNotificationBadge(count)`
- Page-specific scripts passed via `$extraScripts` variable before footer include

## Configuration

All config constants in `config/database.php`:
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`
- `SITE_URL` - base URL (default: `http://localhost/weekly-reports`)
- `SESSION_TIMEOUT` - 1800 seconds (30 min)
- `MAX_FILE_SIZE` - 5MB
- `ALLOWED_EXTENSIONS` - ['jpg', 'jpeg', 'png', 'pdf', 'docx', 'xlsx']
- `UPLOAD_PATH` - absolute path to uploads directory
- `MAIL_FROM`, `MAIL_FROM_NAME` - for email notifications

## Development Guidelines

### When Adding a New Page
1. Create PHP file in `pages/`
2. Set `$pageTitle` and include `header.php`
3. Add role check if needed with `requireRole()`
4. Handle POST form submissions at top (check CSRF token)
5. Use `setSuccess()`/`setError()` + `redirect()` after form processing
6. Add navigation link in `includes/sidebar.php`
7. Include `footer.php` at the end

### When Adding an AJAX Endpoint
1. Create PHP file in `ajax/`
2. Include `functions.php`, start session
3. Set `Content-Type: application/json`
4. Check authentication and authorization
5. Verify CSRF token for mutating operations
6. Return JSON with `success` boolean

### When Modifying the Database
1. Update `sql/database.sql` with new tables/columns
2. Use InnoDB engine, utf8mb4 charset
3. Add proper foreign keys with CASCADE/SET NULL

### File Upload Handling
1. Use `uploadFile($file, $destination)` function
2. Files get unique names via `uniqid('file_', true)`
3. Validate with `validateUploadedFile()` before saving
4. Store relative path in DB, full path uses `UPLOAD_PATH` constant

## Git Workflow

- Commit messages: use prefixes `feat:`, `fix:`, `update:`
- Push directly after each significant change
- Branch naming: follows repository conventions

## Default Test Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@example.com | Admin@123 |
| Manager | manager1@example.com | Pass@123 |
| Employee | employee@example.com | Pass@123 |

## Common Pitfalls

- Login page (`pages/login.php`) has its own standalone HTML - it does NOT use header/footer includes
- Manager can only edit reports they created AND only during the current week
- The `e()` function is the primary XSS defense - never output user data without it
- Form submissions must include CSRF token via `csrfField()`
- File uploads go through `.htaccess` protection that blocks PHP execution
- The cron job (`cron/send_weekly_reminder.php`) uses PHP's `mail()` function directly
- Pagination in reports_archive and notifications preserves filter parameters in URLs
