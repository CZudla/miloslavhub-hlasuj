# Hlasuj! by MiloslavHub — administrator guide

Updated 8 October 2026. Qualified baseline: application 0.8.9, voting schema 0.8.5. Organisation management, expanded English UI and related changes in the development branch are unreleased. This guide does not qualify their installation. Use [the release record](../../RELEASE-0.8.9.md) and an installation-specific deployment record together.

## Components and prerequisites

The PHP/JavaScript frontend runs at the root of its own HTTPS host. The WordPress plugin stores teaching content in WordPress posts and metadata; a separate MySQL/MariaDB database stores voting runs, sessions, participants and responses. Demo storage is separate and temporary.

The plugin declares PHP 8.1+ and WordPress 6.4+. Recorded local qualification used PHP 8.4, WordPress 7.1.2 and MariaDB 11.4.9; that does not certify every minimum-version combination. PHP needs MySQLi. Apache uses the supplied frontend rewrite rules; another web server needs equivalent routing. Subdirectory installation is not qualified.

## Install and configure

1. Prepare an isolated WordPress installation and an empty voting database. Import the plugin's `database-schema.sql` only into that empty external database.
2. Add this installation's `MHL_LIVE_DB_HOST`, `MHL_LIVE_DB_NAME`, `MHL_LIVE_DB_USER` and `MHL_LIVE_DB_PASSWORD` to its protected WordPress configuration before WordPress loads. Keep values outside Git and packages.
3. Install the matching `miloslavhub-live` plugin and frontend release. Preserve the plugin directory name.
4. Create the frontend's private `config.php` from `config.example.php`. Set the WordPress REST base, frontend host, demo URL, branding and contact for this installation.
5. Configure the voting frontend address and allowed CORS origin in the plugin settings. Verify database connectivity and schema compatibility.
6. Supply operator-specific privacy information, retention settings and competition choices. Test the teacher, two student browsers, projection, closed results, exports and demo before using real class data.

An unconfigured frontend returns HTTP 503. It must not silently use the original project's production API.

## Upgrade and recover

Back up both databases, WordPress and media, frontend, plugin and private configurations together. Test recovery in an isolated environment. Preserve permanent slugs and QR addresses. Install frontend and plugin from the same qualified release.

The known 0.8.5 schema needs no voting DDL migration for baseline 0.8.9. Historical SQL files are not a checklist to run indiscriminately. Check an unknown schema before making changes. The new development roles modify WordPress options even without a voting schema migration.

Rollback normally restores coordinated application files while retaining new votes. Restoring an old database can destroy responses collected since the backup. Rolling back the new organisation model can also change access to shared content; plan this explicitly.

## Operate and diagnose

Monitor frontend and API availability, database connectivity, disk capacity, backups and retention. Demo expires after 15 minutes, but physical cleanup occurs on later requests. Application retention does not delete existing CSV files, hosting logs or backups.

Protect projection links and their tokens. CORS and noindex do not make public results private. Local burst tests do not establish production hosting capacity. Keep AI disabled unless the operator has explicitly arranged its provider, data policy and budget.

Use the [homepage and routing runbook](../../HOMEPAGE-AND-ROUTING.md) for the documentation website. An isolated static website patch must not deploy an unqualified plugin or frontend application.

[Technical security](../../SECURITY.md) · [Privacy](../../PRIVACY.md) · [Licence notices](../../licenses/NOTICE.md) · [Documentation index](../../INDEX.md).
