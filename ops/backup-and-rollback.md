# Backup, hosting readiness and rollback

## Mandatory pre-deployment backup
1. Export the complete application database in InfinityFree phpMyAdmin (structure and data); download it and verify it opens and is non-empty.
2. Download the current website files over FTP, including hidden files such as .htaccess and config/production.php. Store backups outside public_html.
3. Record the deployed commit and current database table list. The previously uploaded 2026-11-26 SQL export is not a fresh pre-deployment backup.
4. Do not proceed until both backups are verified and retained unchanged.

## Production migration
- Review migrations/deployment_readiness.sql against the live schema.
- Confirm the five feature tables are absent before applying it.
- Do not rerun config/schema_additions.sql; the inspected export already contains its seven tables.
- The final ALTER converts medical_records to utf8mb4. MySQL/MariaDB DDL auto-commits; it is not transactionally reversible.
- Execute SQL only after a separate explicit production-SQL approval and a fresh backup.

## Host checks
- Verify the PHP version actually selected in the InfinityFree control panel; do not infer the account's runtime version from platform announcements. The application CI has been exercised on PHP 8.2 and 8.4.
- Confirm pdo_mysql, curl, openssl, session and json extensions using the hosted readiness page.
- Verify HTTPS, cookie flags, session storage persistence, database connectivity and relevant file permissions.
- Test the deployed .htaccess on the account: confirm normal routes work and direct requests to /config, /tests, production.php, .env files, SQL, log and INI files are denied. Confirm there is no HTTP 500 caused by unsupported directives. The mod_headers cache-control block is conditional, but still requires live verification.
- Open /admin/system_readiness.php while signed in as Admin. A GET request runs local checks only and returns a session-bound CSRF token; it does not contact Gemini.
- To test Gemini deliberately, send an authenticated POST to the same endpoint with the returned CSRF token. The endpoint enforces a 30-second per-session rate limit, uses the server-side GEMINI_API_KEY in the request header, and returns only connection status/model. Never place the key in browser code, URLs, chat, screenshots or logs.
- Confirm outbound HTTPS to generativelanguage.googleapis.com from the hosted PHP runtime. General outbound HTTPS support does not prove this specific API is reachable.
- Run role-based smoke tests only in a controlled, approved environment. This diagnostic is not a substitute for hosted end-to-end testing.

## Deployment and rollback
- Deploy only approved changed files; inspect FTPS action logs to verify delta transfer and preserve remote files.
- Smoke-test login/logout, queue, patient assistant, doctor draft review, admin centre and database writes.
- If application files fail, restore the pre-deployment files via FTP.
- If a schema change fails, stop writes and restore the full database backup through phpMyAdmin; DDL cannot be rolled back with a transaction.
- Verify restored application/database behavior before reopening access and record the restored commit.

## Current limitations
No staging website exists. Account-specific PHP version, extensions, database connectivity, sessions, permissions, .htaccess behavior, FTPS connection, actual Gemini connectivity and rollback remain unverified until tested on the InfinityFree account. The CI test matrix is not evidence of the hosting account's runtime configuration. This runbook performs no backup, SQL execution or deployment.
