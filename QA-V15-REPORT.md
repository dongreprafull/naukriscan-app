# V15 Integration QA Report

## Static checks
- PHP syntax lint: PASS (all PHP files)
- PWA manifest JSON: PASS
- Service worker basic install/fetch checks: PASS

## Issues found and fixed
- `includes/notifications.php` referenced `users.status`, while the schema defines `users.account_status`. Fixed to use `account_status='active'`.
- `forgot-password.php` exposed the reset token in the HTML response. Removed token disclosure; production reset delivery now requires configured email delivery.

## Environment limitation
A live MySQL integration test requires the Hostinger database credentials/environment and was not executed in this local workspace.

## Production checks still required on Hostinger
- Run Admin > System Check.
- Apply all SQL migrations in order on the target database.
- Configure notification/email/WhatsApp secrets as environment variables.
- Test registration, login, profile, document upload/download, job publish, AI review, matching, notification queue, application assistance, subscription/trial, admin permissions and PWA on the deployed domain.
