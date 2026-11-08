# Careplate AI MVP: handoff

Backend only (Laravel 13, SQLite, Pest). No UI was built or changed. This file is for the frontend team and the competition judges. Everything below was checked against the code or a command run on 2026-11-08; the limits section is part of the contract, read it before writing product copy.

Tested with PHP 8.5.12, Laravel 13.39.0, Pest, SQLite. Full suite at the time of writing: 806 tests, 797 passed, 9 failed. The 9 failures are old and not AI related (8 Vite manifest tests that need `public/build/manifest.json`, and 1 `RegistrationTest` registration test). Pint reports the same 3 old files, PHPStan the same 1 old error (`VerifyEmailController`). The AI tests alone (`tests/Feature/Ai`, `tests/Unit`) are 782 of 782 green.

## What exists

| Feature | Summary |
|---|---|
| Scan | `POST /ai/v1/scan`: one label photo to structured nutrition facts plus a provisional assessment. Stateless: photo and result are never stored. |
| Chat | Sessions and turns. Numbers always come from the verified catalog (database mode) or the answer says it is general knowledge. Stored for the owner for 7 days. |
| Foods | `GET /ai/v1/foods`: search the verified catalog (no nutrient values, those arrive through chat claims). |
| Submissions | `POST /ai/v1/food-submissions`: a user proposes a food; it stays `pending` and is not searchable. |
| Admin review | Approve a submission or revoke a food, with optimistic revision checks and an audit trail. |
| Purge | `ai:purge-chats` deletes chats past retention (daily schedule entry exists). |

## Routes

Base: same origin as the Laravel app (local: `http://127.0.0.1:8001`). All routes sit in the `web`, `auth` group: session cookie, CSRF, JSON errors. Every response has `Cache-Control: no-store, private` and `X-Request-Id`.

| Method and path | Needs | Success | Notes |
|---|---|---|---|
| POST `/ai/v1/scan` | `scan-nutrition`, throttle `ai-scan` | 200 | multipart field `image`, JPEG or PNG, up to 5 MiB, up to 16 MP |
| GET `/ai/v1/foods` | `nutrition-chat` | 200 | `q` (<=200 chars, optional), `limit` 1 to 5 |
| POST `/ai/v1/chat/sessions` | `nutrition-chat` | 201 | empty JSON object body |
| GET `/ai/v1/chat/sessions` | `nutrition-chat` | 200 | `cursor`, `limit` 1 to 20; own sessions only |
| DELETE `/ai/v1/chat/sessions/{session}` | owner | 204 | content erased at once; late results are refused |
| GET `/ai/v1/chat/sessions/{session}/turns` | owner | 200 | `cursor`, `limit` 1 to 20; pending and failed turns are visible |
| POST `/ai/v1/chat/sessions/{session}/turns` | owner, throttle `ai-chat` | 200 | one turn; see idempotency below |
| POST `/ai/v1/food-submissions` | `submit-food`, throttle `ai-submit` | 201 (200 on identical resend) | body is closed: unknown keys are 422 |
| POST `/ai/v1/admin/food-submissions/{submission}/approve` | `verify-food` and role `admin` | 200 | `{expected_revision, reviewer_note, acknowledge_review_flags?}` |
| POST `/ai/v1/admin/foods/{food}/revoke` | `verify-food` and role `admin` | 200 | `{expected_revision, reason}` |

Roles from `AiPermissionSeeder`: `family_head` has scan, chat, submit. `nutritionist` has scan and chat only. `admin` has everything. Registration assigns `family_head`. Another user's session is always 404 (never 403), for read, send, delete and replay. A non-UUID id in an admin path is 404.

## Auth and CSRF flow

Same origin only, cookies on every call, `Accept: application/json` always.

1. `GET /sanctum/csrf-cookie` (204). This sets the `XSRF-TOKEN` and session cookies.
2. Read the `XSRF-TOKEN` cookie and URL-decode it.
3. `POST /login` with header `X-XSRF-TOKEN: <decoded>` and JSON `{"email","password"}`. The reply is a 302 to the dashboard. Do not follow it (the dashboard needs the Vite build).
4. Re-read `XSRF-TOKEN` after login (it rotates) and send it as `X-XSRF-TOKEN` on every POST and DELETE.

No bearer tokens. CSRF is not disabled for `/ai/v1/*`. `tools/api-smoke.php` is a working reference client (plain curl).

## Examples (synthetic values)

Scan (`curl -b jar -H "X-XSRF-TOKEN: $T" -H "Accept: application/json" -F image=@label.jpg $ORIGIN/ai/v1/scan`). Abridged 200 body:

```json
{"schema_version":"1.0","request_id":"req_x","extraction_status":"complete",
 "extraction":{"label_basis":"per_serving","serving_size":{"value":30,"unit":"g","household_text":"3 keping"},
   "servings_per_package":2,"nutrients":{"energy_kcal":{"value":150,"qualifier":"exact","unit":"kcal","evidence":"label 150 kcal","label_percent_akg":null}},
   "needs_review":false},
 "normalized_per_serving":{"energy_kcal":150,"sodium_mg":450,"sugars_total_g":12},
 "assessment":{"policy":{"id":"careplate-ggl-screening-draft-v1","status":"provisional"},
   "daily_reference_contribution_percent":{"sugars_total":24,"sodium":22.5,"fat_total":11.94},
   "flags":[],"verdict":"policy_pending_validation","summary":"..."},
 "warnings":["Pembanding edukasi dewasa umum; bukan %AKG label atau kebutuhan personal."]}
```

`extraction_status` is `complete` or `partial`. Unknown values are `null`, never 0. An unreadable label is 422 `OCR_UNREADABLE`. The verdict is always `policy_pending_validation` and `flags` is empty: the screening policy is provisional.

Chat turn: `POST /ai/v1/chat/sessions/{id}/turns`

```json
{"client_turn_id":"40000000-0000-4000-8000-000000000001","message":"Berapa protein 50 gram tahu contoh?",
 "food_id":"10000000-0000-4000-8000-000000000001","amount_g":50}
```

`message` is 1 to 2000 characters after trim. `food_id` and `amount_g` are optional (`amount_g` > 0, <= 2000, 2 decimals max; with no amount in the body or text the server uses 100 g and says so). A conflict between the body amount and the text amount is 422 `AMOUNT_CONFLICT`. Response (200), abridged:

```json
{"schema_version":"1.0","request_id":"req_x","session_id":"...","turn_id":"...","client_turn_id":"...","sequence":1,
 "mode":"database","retrieval_status":"found","answer":"...",
 "claims":[{"food_id":"...","revision":3,"nutrient":"protein_g","canonical_value":10,"unit":"g","canonical_basis":"100g",
            "requested_amount":50,"derived_value":5,"source_type":"database","citation_id":"food-1"}],
 "citations":[{"id":"food-1","food_id":"...","revision":3,"food_name":"...","basis":"100g","source_reference":"...","verified_at":"2026-11-06T02:00:00Z"}],
 "general_knowledge_notice":null,"warnings":[]}
```

`mode` is `database`, `general`, `clarification` or `referral`; `retrieval_status` is `found`, `no_match`, `ambiguous` or `not_needed`. A `general` answer carries `general_knowledge_notice` and no citations. Show the notice and the citation source on every answer. Nutrient values appear only in `claims`, computed by the server; model text is checked and replaced by a server template if it contains numbers.

Idempotency is per `(session, client_turn_id)`. Same key and same body: completed returns the stored response (no new model call); still running returns 409 `TURN_IN_PROGRESS`; failed returns the stored error; a lease that ran out returns `AI_TIMEOUT`. Same key with a different body is 409 `IDEMPOTENCY_CONFLICT`. A deliberate retry needs a new `client_turn_id`. A second turn while one is running is 409 `SESSION_BUSY`.

History item (`GET .../turns`, wrapped as `{"data":[...],"next_cursor":null}`): `{id, client_turn_id, sequence, state: pending|completed|failed, user_content, response, failure_code, created_at}`. Sessions list items: `{id, state:"active", title, created_at, updated_at}`.

Foods: `GET /ai/v1/foods?q=ayam&limit=3` gives `{"data":[{"food_id","revision","name","basis":"100g","verification_level":"source_reviewed","source_reference"}]}`.

Submission body (synthetic):

```json
{"name":"Tahu Contoh (data sintetis)","aliases":["tahu contoh"],"basis":"100g",
 "nutrients":{"energy_kcal":100,"fat_g":5,"sugars_total_g":null,"protein_g":10,"carbohydrate_g":4,"sodium_mg":20},
 "source":{"provider":"MANUAL","source_id":"synthetic-fixture-only","source_url":"https://example.invalid/x","snapshot_version":"synthetic-v1","source_sha256":null}}
```

Reply: `{"submission_id","status":"pending","revision_counter":1}`. Admin approve/revoke reply: `{"food_id","revision","status":"verified|revoked","sync_status":"ready"}`. An identical repeated decision returns the stored result; a stale `expected_revision` is 409 `STALE_REVISION`. Approve uses the submission `revision_counter`, revoke uses `foods.current_revision`.

## Errors

Envelope: `{"error":{"code","message","retryable"},"request_id":"req_..."}`. Messages are Indonesian. `Retry-After` is set on 429. Raw provider output, prompts, SQL and stack traces never appear.

| HTTP | Code | Retryable | Meaning and client action |
|---:|---|:-:|---|
| 401 | UNAUTHENTICATED | no | log in |
| 403 | FORBIDDEN | no | missing permission or role |
| 404 | NOT_FOUND | no | missing, foreign or deleted record |
| 409 | IDEMPOTENCY_CONFLICT | no | same `client_turn_id`, different body |
| 409 | TURN_IN_PROGRESS | yes | same turn still running; poll history or resend later |
| 409 | SESSION_BUSY | yes | another turn running or DB write contention; retry shortly |
| 409 | STALE_REVISION | no | reload the record and decide again |
| 409 | KNOWLEDGE_CHANGED | yes | a cited food was revoked or revised while the answer was prepared; the turn is closed as failed; send the question again as a new message (new `client_turn_id`) |
| 413 | IMAGE_TOO_LARGE | no | smaller photo |
| 415 | UNSUPPORTED_MEDIA_TYPE | no | JPEG or PNG only |
| 419 | CSRF_MISMATCH | no | refresh the CSRF cookie, log in again if needed |
| 422 | INVALID_INPUT | no | fix the input; admin approve uses this with a reason code in the message (`SOURCE_NOT_IN_MANIFEST`, `SOURCE_SHA_MISMATCH`, `REVIEW_ACK_REQUIRED`, `CANDIDATE_INVALID`, `SOURCE_MISSING`, `NOTE_REQUIRED`); every other 422 carries only the generic message "Data yang dikirim tidak valid." The envelope has no per-field errors, so validation detail is never returned |
| 422 | AMOUNT_CONFLICT | no | body amount and text amount disagree |
| 422 | OCR_UNREADABLE | no | retake the photo |
| 429 | RATE_LIMITED | yes | wait `Retry-After`; the model was not called |
| 500 | INTERNAL | no | unexpected server error |
| 502 | AI_PROVIDER_ERROR, AI_OUTPUT_INVALID | yes | provider failed or returned unusable output |
| 503 | DB_BUSY | yes | SQLite lock contention on admin approve/revoke, food submission or session creation; nothing was written; repeat the same call |
| 503 | KNOWLEDGE_UNAVAILABLE | yes | catalog not available |
| 503 | AI_DISABLED, POLICY_CONFIG_INVALID | no | feature off or policy config invalid; the catalog list still works |
| 504 | AI_TIMEOUT | yes | provider too slow (scan deadline 25 s, chat 20 s) |

## Quotas (per user, server side)

Scan 5 per minute and 60 per day. Chat 10 per minute and 100 per day. Submit 5 per minute and 30 per day. A request that fails validation still spends quota. Quota is checked before any provider call.

## Privacy facts

- Scan is stateless: the photo is processed in memory and deleted; no result is stored.
- Chat text and answers are stored for the owner and expire after 7 days (reads stop at the cutoff; physical purge runs daily and can lag up to about 24 h). Deleting a session erases its content at once. Deleting the user removes their chats.
- The Gemini request sends `store: false`. Logs hold only metadata (request id, feature, status or error code, latency, model version, token usage): no images, OCR text, label numbers, prompts, provider responses, chat text, cookies or keys.
- A free-tier Gemini key allows Google to use content to improve its products. Use a paid-tier key for real users. Tell users before the first send that photo or message and session context go to an AI provider; start requests only from a Scan or Send button.
- The provider key lives only in the git-ignored `.env`. It must be rotated by its owner and never put in frontend props or `VITE_` variables.

## Running it

Run from the repository root. `php tools/ai-dev.php` is a guarded wrapper that uses the demo SQLite file `database/careplate-ai-demo.sqlite` and `.env`, and `.env.testing` with an in-memory DB for tests. Start from `.env.ai.example` for a fresh checkout (never overwrite an existing `.env`; set `APP_KEY` and `AI_GEMINI_KEY` yourself).

```
php tools/ai-dev.php doctor            # which env, DB and flags are in effect (no secrets)
php tools/ai-dev.php migrate
php tools/ai-dev.php seed              # roles and permissions
php tools/ai-dev.php test [path|--filter=..]
php tools/ai-dev.php lint | analyse
php tools/ai-dev.php serve             # http://127.0.0.1:8001
php tools/ai-dev.php ai ai:preflight   # one real provider call: key, model, structured output
php tools/ai-dev.php ai ai:import-foods [--download] [--refresh-pending]   # USDA Foundation snapshot to pending submissions
php tools/ai-dev.php ai ai:seed-catalog                                    # approve the matched USDA candidates (script)
php tools/ai-dev.php ai ai:eval scan | chat                                # live evals, results in ai/evals/results
php tools/ai-dev.php ai ai:purge-chats [--dry-run]
php tools/api-smoke.php                # end-to-end API walk with throwaway users (uses the provider: 1 scan, 2 chat turns)
```

Outside the wrapper use `php artisan ai:...`. `ai:review-food approve|revoke` is a CLI twin of the admin endpoints. Retention needs the Laravel scheduler (`php artisan schedule:run` every minute from the OS). To make a user an admin, assign role `admin` (and run the seeder first). The demo catalog now holds 10 verified foods. Evals are described in `ai/evals/README.md`, sample licences in `ai/evals/ocr/NOTICE.md`.

Config knobs (`config/ai.php`, `.env.ai.example`): `AI_ENABLED`, `AI_GEMINI_KEY`, `AI_GEMINI_MODEL` (frozen default `gemini-3.6-flash`), scan and chat deadlines, token budgets, `AI_CHAT_RETENTION_DAYS`. AI is off until `AI_ENABLED=true`; the catalog list (`GET /ai/v1/foods`) never needs the model.

## Honest limits

Catalog and wording
- The catalog is 10 USDA Foundation foods. They were approved by a script (`ai:seed-catalog`): the source id and the archive sha256 were matched against `ai/data/snapshot.manifest.json`. The nutrient values were NOT re-verified, review flags were acknowledged by the script, and no human or nutritionist reviewed anything. Product copy must say "source-reviewed (script)" and never "nutritionist-verified" or "lab-verified".
- Several candidates carry review flags: variant picks (3 foods), single-variety only (5 foods), legacy energy nutrient 1008 (4 foods, energy method labelled), exact zeros (2 foods). Sugars are `null` for 9 of 10 foods because USDA Foundation rarely reports them.
- Foods outside the catalog get no numbers: the chat says so and suggests scanning the label.
- User-submitted foods (provider `MANUAL`) stay pending. Admin approve returns 422 `INVALID_INPUT` with `SOURCE_NOT_IN_MANIFEST` in the message until the manifest supports them. Scans never feed submissions.
- The scan assessment policy is provisional: verdict `policy_pending_validation`, no flags. Do not present it as a health rating.

Evaluation
- Scan eval: 8 Wikimedia web sample images (3 dev, 5 holdout), mostly US, Canadian and EU labels, ground truth written by an AI assistant and not checked by a human. 100% on tiny n is a smoke test, not accuracy, and says nothing about Indonesian packaging. Latency on `gemini-3.6-flash`: p50 about 15.6 s, p95 about 21.8 s (n=8), against a 25 s deadline and the spec target of 15 s. Plan for slow scans and show progress.
- Chat eval: 9 applicable of 12 golden cases, 4 of the 9 mapped or substituted onto the demo catalog; 9 of 9 routing, claims and citations pass. `unsafe_claims = 0` holds by construction (the guard blocks every number-plus-unit), it is not an independent safety result. Only 2 cases reach the model. One run per case.
- NarrationGuard is lexical. Known bypass classes: number words attached to units in unusual forms, units outside its list, other-language units, and citation styles it does not recognise. It fails closed on invalid UTF-8 and regex errors, and the numbers in answers come from server claims anyway, but text quality of general answers is not human graded.
- The eval images carry licence caveats (CC BY, CC BY-SA, third-party packaging artwork). Replace them with team-owned photos before public redistribution.

Model
- `gemini-3.8-flash` was the planned model but did not respond on the project key (no answer in 45 to 50 s, tested twice). `gemini-3.6-flash` answers in about 1 s on preflight and was used for every evaluation, so it is the frozen default. The model id is validated against a strict pattern before it is placed in the request URL.

Operations
- SQLite, one instance, one persistent volume. No multi-instance or ephemeral-disk hosting. Write transactions use `transaction_mode=IMMEDIATE`, which needs PHP 8.4 or newer (on older PHP Laravel silently falls back to deferred transactions). Chat lock contention returns `SESSION_BUSY`; admin, food-submission and session-creation lock contention returns `DB_BUSY`.
- PHP ini for the 5 MiB scan contract: `upload_max_filesize` >= 5M and `post_max_size` >= 6M (the upload is multipart; the base64 expansion happens server side). With the stock `upload_max_filesize=2M`, a 2 to 5 MiB photo is answered 413 IMAGE_TOO_LARGE. `max_execution_time` must sit comfortably above the 25 s scan deadline (and the 20 s chat deadline). A fatal execution timeout cannot be caught: the request dies without an envelope and a chat turn stays `pending` until its lease expires.
- `memory_limit` must be at least 256M for the scan route (the ImagePreparer guard decodes up to 16 MP).
- Stored chat turns carry an HMAC `request_hash` keyed with `app.key`. Rotating `APP_KEY` breaks replay of stored turns (they will read as a different request).
- Concurrency tests use two PDO handles in one PHP process, not separate OS processes.
- Cost was not computed (no price table); token usage is recorded in the eval results.
- The provider key must be rotated by its owner; nothing in the repository contains it.
