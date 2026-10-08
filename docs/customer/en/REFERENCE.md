# Hlasuj! by MiloslavHub — reference guide

Updated 8 October 2026. The qualified baseline is 0.8.9. References to development features below do not mean that they are enabled in production.

| Term or operation | Meaning |
|---|---|
| Subject | A course or teaching context containing lectures and cumulative settings. |
| Lecture | An ordered selection of questions for a teaching session. |
| Question | Authored prompt and options, with quiz/poll settings. |
| Live voting | The teacher opens and closes voting. A student joining does not start it. |
| Test run | A rehearsal; check the intended mode before collecting real responses. |
| Long-running poll | An independently available opinion-collection activity. It is not a homework implementation. |
| Session | One opening of a question. Repeating it creates another session with its own votes. |
| Permanent QR | The question's joining address remains stable across sessions. |
| Projection | A display for the current question and results; protect private projection tokens. |
| Results export | A file of authorised voting results, distinct from reusable content transfer. |
| Content transfer | Portable subject/lecture/question content. Baseline 0.8.9 uses v1; development writes v2 and reads supported v1. Import preview precedes creation of new drafts. |
| Organisation and sharing | Unreleased local access model with explicit membership and object permissions. |
| Teaching seat | A distinct account with teaching rights, counted once across an organisation. The current local count is not authoritative licence enforcement. |
| Answer explanation | Development feedback feature; student visibility requires the configured closed-voting policy. |
| Private teacher note | Development authoring information excluded from public voting payloads. Review authorised sharing and content export before adding confidential material. |
| UI language | Changes controls and messages, not authored questions or nicknames. Expanded cs/en support is unreleased. |

## Access and integrations

Public student/result routes retain their established public behaviour. Organisation permissions protect management; they do not make an existing public result URL confidential. The `/mhl/v1` REST namespace is retained. It is not yet a fully qualified public integration API with separate scopes and rate limiting.

Central AUTH, licence enforcement and simultaneous seat reservation remain unfinished. Local role membership, browser values and an email address do not constitute a central entitlement.

## Detailed contracts

[API](../../API.md) · [Content format](../../CONTENT-FORMAT.md) · [Organisations and i18n](../../ORGANIZATIONS-I18N-REFERENCE.md) · [Neutral polls](../../NEUTRAL-POLLS-REFERENCE.md) · [Teacher guide](USER-GUIDE.md) · [Documentation index](../../INDEX.md).
