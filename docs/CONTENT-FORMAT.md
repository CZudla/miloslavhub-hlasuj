# Portable content format v2 / v1 compatibility

Application 0.8.9 implements v1. Unreleased development dated 2026-10-07 exports v2 and imports both v1 and v2. The authoritative validator is `MHL_Content_Transfer::validate_json`; no SQL migration is required. This contract transfers teaching content; results and a full installation backup have separate lifecycles.

## Envelope and references

UTF-8 JSON, at most 2,097,152 bytes, depth at most 32. Root keys are exactly `format`, `format_version`, `subject`, `lectures`, `questions`. The format is `hlasuj-content`; accepted versions are integer `1` and `2`. New exports use `2`. Unknown versions and fields are rejected. No filesystem paths, executable markup, URL fetching or ZIP extraction are accepted. A 0.8.9 installation cannot import v2; upgrade the destination to a qualified version supporting v2 before sharing new files.

The subject has exactly `id` (`s1`), `title`, `settings`. Each question has exactly `id`, `title`, `options`, `correct_index`, `settings`. Question IDs match `q[1-9][0-9]{0,3}`, are unique, and have no relation to WordPress IDs. `correct_index` is null for a poll or an integer zero-based index of an option. `options` is an ordered list of 2–26 nonempty strings. Titles and options allow at most 1,000 UTF-8 bytes; control characters are rejected and markup is sanitized. Empty text after sanitization is rejected.

Each lecture has exactly `id`, `title`, `question_ids`, `settings`. IDs match `l[1-9][0-9]{0,3}` and are unique. `question_ids` is an ordered list of existing question IDs with no repetitions within one lecture. One question may appear in several lectures. Every exported question must be referenced. At most 100 lectures and 500 different questions are accepted, with at most 500 references per lecture. Empty subjects and lectures are supported.

## Complete settings allowlist

All listed keys are required; unknown keys are rejected. Limits for strings below are UTF-8 bytes. Text settings may be empty. Booleans must be JSON booleans, integer fields must be JSON integers, and enum values must have the indicated type.

### Subject

| Key | Allowed value / fallback for missing source metadata |
|---|---|
| brand_template | miloslavhub, fes_upce, neutral, custom; default miloslavhub |
| subject_template | standard, competition, minimal; default standard |
| subject_short_title | text, 200; default empty |
| subject_code | text, 100; default empty |
| subject_period | text, 200; default empty |
| competition_title | text, 300; default empty |
| subject_extra_info | text, 4,000; default empty |
| custom_brand_name | text, 200; default empty |
| custom_brand_subtitle | text, 300; default empty |
| custom_primary_color | six-digit hex color; default #172033 |
| custom_accent_color | six-digit hex color; default #1f5fae |
| hof_enabled | boolean; default false |
| hof_visibility | public, participants; default public |
| hof_limit | integer 3–100; default 10 |
| hof_period_days | integer 1–3650; default 365 |
| hof_title | text, 300; default empty |
| hof_nonopt_mode | hidden, anonymous; default hidden |

### Lecture

| Key | Allowed value / default |
|---|---|
| gamification | boolean; false |
| score_scope | subject, lecture, none; subject |
| show_live_results | boolean; false |

### Question

| Key | Allowed value / default |
|---|---|
| multiplier | number 1, 1.5 or 2; default 1 |
| speed_window | integer 5–120; default 20 |
| poll_points | integer 0–1000; default 0 |
| time_limit | null (destination default), 0 (no question limit), integer 5–600; default null |
| rag_policy | exclude, private, public_after_lecture; exclude |
| async_show_results | boolean; true |
| correct_answer_explanation (v2 only) | plain text, 4,000; default empty |
| explanation_mode (v2 only) | teacher_only, show_after_close, hidden; teacher_only |
| teacher_note (v2 only) | private teaching text, 4,000; default empty |

The three v2 fields are required in v2 and forbidden in v1. A v1 import leaves their metadata absent, so the application uses empty text and `teacher_only`. Re-exporting a v1 import produces a v2 file. Both versions preserve explicitly disabled booleans, including `async_show_results = false` stored as an empty WordPress meta value. No value is interpreted as a central approval or a permission grant.

`show_after_close` publishes the current explanation through the public results endpoint only for a closed quiz. Anyone who can access those results can read it, including the projection. `teacher_only` and `hidden` both omit it from public results; they differ in the teacher's declared intent. Neither sends `teacher_note` to students. The plain text renderer escapes markup. Historical sessions currently refer to current question metadata; they do not preserve a separate explanation snapshot.

## Safety and scope

The exporter checks `manage_options` and edit permission for every subject, lecture and question. Both form operations require WordPress session authentication and an action-specific nonce. Confirmation uses a 15-minute preview bound to the current user. A per-user DB-backed option lock serializes preview and confirmation. The preview is consumed before writes, preventing duplicate copies on repeat submission. A crash may leave the lock; remove it only after verifying the prior request ended. A newly uploaded preview replaces the previous one.

Import maps portable IDs to fresh WordPress IDs, fresh random permanent slugs, and the importing user. All posts remain drafts. Async enablement is explicitly false, no deadline is copied, and no run/session/vote is created. Existing content is never updated. Normal WordPress hooks still run. Handled insertion/metadata failures remove newly created posts in reverse order; failure to remove is reported. This is compensating cleanup, not a cross-plugin transaction. Fatal termination can leave partial drafts. External plugins with save hooks need their own review.

The file omits account and participant records, results, categories, external URL fields, binary assets, source QR/IDs, projection secrets and unrecognized metadata. User-authored text can contain private information, so the exporter must review it before sharing. V2 includes private teacher notes as material shared between authorized teachers. Correct answers, explanations and notes must not be shared with students as a public download. Global scoring settings and automatic time defaults come from the destination. Language-specific text is retained; the UI uses WordPress gettext but a complete English UI is a later project stage.

Tests cover real WordPress/MariaDB round trips, relationships, draft visibility, permissions, validation, duplicate/expired/cross-user confirmation, injected persistence failures and an actual authenticated browser upload/preview/confirmation. They use synthetic local data and block external requests.
