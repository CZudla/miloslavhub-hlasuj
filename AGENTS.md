# Working on Hlasuj!

## Source of truth

- Read README.md and the relevant sections of docs/MASTER-SPEC.md before changing behavior. Follow docs/PRODUCT-PRINCIPLES.md.
- docs/RELEASE-0.8.9.md and docs/DEPLOYMENT-2026-10-05-0.8.9.md describe the qualified baseline. Older notes in ARCHITECTURE.md and ITERATION-PLAN.md may describe an earlier state.
- Planned features in MASTER-SPEC.md and ROADMAP.md are not evidence of implemented behavior. Trace behavior to code and tests.

## Architecture and contracts

- Keep the PHP/JavaScript frontend, WordPress plugin, external voting DB and separate demo storage unless the task requires a change.
- Preserve permanent QR paths and the REST namespace mhl/v1. Students join; authorized teachers start live/test voting.
- Enforce permissions on the server. Teacher metadata alone grants no permission; current management uses manage_options and WordPress nonces.
- Preserve privacy choices, delayed disclosure of correct answers, vote uniqueness and server timing. Treat concurrency as a separate test concern.
- AI is optional. Teacher approval precedes use of a suggestion; ordinary voting must work with AI disabled. Never automatically send student results to an external model.
- Changes in this workspace are local until deployed. Report exactly what was tested and whether production was touched.

## Verification

Run from this repository:

```text
python tests/run.py --php C:/php84/php.exe --browser
```

Python 3, PHP 8.1+, Node, Playwright and Edge are required. When Playwright is installed outside the repository, set NODE_PATH to its actual node_modules directory. Do not assume that missing package resolution requires installation.

Use focused tests for small changes, and the regression runner for changes affecting application behavior. Tests use synthetic data and loopback. Real WordPress/MariaDB integration requires a disposable local environment; tests/wordpress-integration.php refuses other targets. Never remove this guard to obtain a passing test.

Do not claim model quality from mocked provider tests. Paid API evaluation requires an agreed budget. The current migration pilot is local only, with no paid API calls.

## Files and releases

- Keep secrets, production config, DB exports and runtime output outside commits and delivery archives.
- After frontend changes refresh its manifest with scripts/refresh_manifest.py.
- Follow docs/DEPLOYMENT.md and docs/UPGRADE.md for releases. Build scripts require matching test evidence and a clean committed tree; preserve these checks.
- Preserve user changes. Do not edit historical output packages to represent new work.
- Use Czech for user-facing text and explanations. Keep technical identifiers stable.
