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
- Confirm PHP >= 8.2 and pdo_mysql, curl, openssl, session and json extensions.
- Verify HTTPS, cookie flags, session storage writability, file permissions and .htaccess behavior.
- Visit /admin/system_readiness.php while signed in as Admin. It performs a minimal Gemini GenerateContent request using the server-side key and returns only connection status/model, never the key or raw response.
- Confirm outbound HTTPS to Google and run role-based smoke tests. This endpoint is a diagnostic, not a substitute for hosted end-to-end testing.

## Deployment and rollback
- Deploy only approved changed files; inspect FTP action logs to verify delta transfer and preserve remote files.
- Smoke-test login/logout, queue, patient assistant, doctor draft review, admin centre and database writes.
- If application files fail, restore the pre-deployment files via FTP.
- If a schema change fails, stop writes and restore the full database backup through phpMyAdmin; DDL cannot be rolled back with a transaction.
- Verify restored application/database behavior before reopening access and record the restored commit.

## Current limitations
No staging website exists. Account-specific PHP configuration, extensions, sessions, permissions, .htaccess, actual Gemini connectivity and rollback remain unverified until tested on the InfinityFree account. This runbook performs no backup, SQL execution or deployment.
