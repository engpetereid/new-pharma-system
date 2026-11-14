# Application Security Audit Report
**Application:** Pharmaceutical Company (SAAS)  
**Standard:** OWASP Top 10 (2021/2025) & Laravel Security Best Practices  
**Date:** October 2026  
**Auditor:** Principal ERP Architect & Application Security Specialist  

---

## 1. Executive Summary

An in-depth security audit of the application source code, configuration, dependencies, and public web assets was conducted. Several high-severity vulnerabilities were identified, including an unauthenticated arbitrary file upload / RCE hazard in exposed template asset directories, open public registration in a sensitive enterprise ERP, unescaped CSV formula injection across all export endpoints, state modification via HTTP GET, and the total lack of multi-tenant data isolation.

---

## 2. Vulnerability Register

### VULN-01: Critical — Unauthenticated Arbitrary File Upload in Public Web Root (RCE)
- **Severity**: P0 / Critical (CVSS: 9.8)
- **CWE**: CWE-434 (Unrestricted Upload of File with Dangerous Type)
- **Files Affected**:
  - `public/assets/admin/data/jquery-file-upload/server/php/UploadHandler.php`
  - `public/assets/admin/data/jquery-file-upload/server/php/index.php`
  - `public/assets/admin/vendors/js/editors/ckeditor/samples/old/assets/posteddata.php`
- **Description**: Vendor template sample scripts were published directly into the public directory. `public/assets/admin/data/jquery-file-upload/server/php/index.php` instantiates `UploadHandler` without any authentication, CSRF check, or Laravel routing. An attacker can POST arbitrary files (including `.php` webshells) directly to this endpoint.
- **Remediation**:
  1. Immediately delete all raw `.php` files inside `public/assets/`.
  2. Configure web server (Nginx / Apache) to deny execution of `.php` scripts in asset directories. Only `public/index.php` should ever be executed.

### VULN-02: High — CSV Formula Injection / Spreadsheet Execution (CWE-1236)
- **Severity**: P1 / High (CVSS: 7.5)
- **CWE**: CWE-1236 (Improper Neutralization of Formula Elements in CSV File)
- **Files Affected**:
  - `app/Http/Controllers/Admin/InvoiceController.php` (`export`)
  - `app/Http/Controllers/Admin/InvoiceReturnController.php` (`exportReturns`)
  - `app/Http/Controllers/Admin/DoctorDealController.php` (`exportDeals`, `exportDealInvoices`)
  - `app/Http/Controllers/Admin/TreasuryController.php` (`exportExcel`)
  - `app/Http/Controllers/Admin/ZoneReportController.php` (`export`)
  - `app/Http/Controllers/Admin/DoctorBalanceController.php` (`exportExcel`)
  - `app/Http/Controllers/Admin/AuditLogController.php` (`exportLogs`)
- **Description**: Text values originating from users (pharmacist names, doctor names, invoice notes, descriptions) are written directly into CSV exports with `fputcsv()`. If an input begins with `=`, `+`, `-`, or `@`, spreadsheet software (Microsoft Excel, LibreOffice Calc) treats it as a formula and executes it upon opening.
- **Remediation**: Apply a sanitization helper that prepends a single quote (`'`) to any cell value that starts with formula triggers (`=`, `+`, `-`, `@`, `\t`, `\r`).

### VULN-03: High — Open Public Registration in Enterprise ERP
- **Severity**: P1 / High (CVSS: 7.3)
- **CWE**: CWE-306 (Missing Authentication for Critical Function)
- **Files Affected**:
  - `routes/auth.php:15-18`
  - `app/Http/Controllers/Auth/RegisteredUserController.php`
- **Description**: The `/register` endpoint is accessible without authentication. Anyone can register an account, which defaults to `role = 'accountant'` in `users` table. While redirecting fails due to missing route `dashboard`, accounts are permanently created in the database.
- **Remediation**: Disable the public registration route. Account provisioning must be restricted to authenticated administrators via `UserController`.

### VULN-04: Medium — State Modification via HTTP GET (`/admin/fix-deals`)
- **Severity**: P1 / Medium (CVSS: 6.5)
- **CWE**: CWE-352 / RFC 7231 Violation
- **Files Affected**: `routes/web.php:141-213`
- **Description**: Route `GET /admin/fix-deals` executes mass database transactions, updates deal invoices, and writes files in `storage/app/` using an HTTP GET method. This lacks CSRF protection and can be triggered inadvertently by web crawlers, browser link prefetching, or phishing.
- **Remediation**: Remove the inline route from `web.php` and migrate it to an Artisan console command `php artisan deals:recalculate`.

### VULN-05: Medium — Admin Self-Demotion / Lockout Risk
- **Severity**: P2 / Medium (CVSS: 5.5)
- **Files Affected**: `app/Http/Controllers/Admin/UserController.php:67-93`
- **Description**: In `update()`, an administrator can change their own role to `viewer` or `accountant`. If they are the sole admin in the system, all administrative access is permanently lost.
- **Remediation**: Guard `update()` to disallow an administrator from altering their own role.

### VULN-06: High — Absence of Audit Trail for Mass Data Exports
- **Severity**: P2 / High (CVSS: 6.8)
- **Files Affected**: All controller export endpoints.
- **Description**: Exporting thousands of invoices, patient/customer records, debt lists, or audit logs does not trigger an audit log entry in `audit_logs`. Malicious exfiltration cannot be attributed.
- **Remediation**: Add `AuditService::log('exported', ...)` in all export callback streams.

### VULN-07: Critical Architectural Limitation — Multi-Tenancy Absence
- **Severity**: P0 (Architectural)
- **Files Affected**: Entire schema & ORM layer
- **Description**: The application carries "(SAAS)" in its naming, but contains zero tenant isolation. There is no `tenant_id`, tenant resolver, or global scopes. If multiple companies are placed on the same database, full cross-tenant data leakage will occur.
- **Remediation**: Acknowledge that the current implementation is single-tenant (one database per company). If true SaaS is required, a staged multi-tenant migration with schema partitioning or tenant scoping must be enacted.

---

## 3. Dependency Security Assessment

- **Composer Audit**:
  - Found vulnerabilities in transitive packages: `symfony/routing` (CVE-2026-48784, CVE-2026-45065), `symfony/yaml` (CVE-2026-109413, CVE-2026-45304, CVE-2026-45305).
  - Recommended action: Run non-breaking `composer update symfony/*` during scheduled maintenance after comprehensive testing.
- **NPM Audit**:
  - Found 22 vulnerabilities in dev/build tools (Rollup, Vite, PostCSS, Shell-quote).
  - Recommended action: Dev-only dependencies; update via `npm update` in staging before next production asset build.
