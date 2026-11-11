# Careplate AI MVP: handoff

Backend only (Laravel 13, SQLite or MySQL 8, Pest). No UI was built or changed. This file is for the frontend team and the competition judges. Everything below was checked against the code or a command run on 2026-11-08/09; the limits section is part of the contract, read it before writing product copy.

Tested with PHP 8.5.12, Laravel 13.39.0, Pest, SQLite and MySQL 8.0.43 (see Databases). Full suite on 2026-11-09 at commit `ede68c5`: SQLite 959 tests, 933 passed, 9 failed, 17 skipped (the 17 are MySQL-only tests); MySQL 959 tests, 938 passed, 9 failed, 12 skipped (the 12 are SQLite-only tests). The 9 failures are old and not AI related (8 Vite manifest tests that need `public/build/manifest.json`, and 1 `RegistrationTest` registration test). Pint reports the same 3 old files, PHPStan the same 1 old error (`VerifyEmailController`). Every other test passes. Read Provider quota before demoing: the free-tier key allowed about 20 requests per day on `gemini-3.6-flash` (observed; other models may differ).

## Providers: Gemini (default) and Qwen (OpenAI-compatible gateway)

DEFAULT (since 2026-11-09): `AI_PROVIDER=gemini`, model `gemini-3.5-flash-lite` (free-tier key: preflight 1.0 s; 3 dev images all read correctly, p50 4.3 s, p95 5.2 s). The OpenAI-compatible option `AI_PROVIDER=openai` uses `qwen/qwen3.8-max` through an OpenAI-compatible `POST {OPENAI_BASE_URL}/chat/completions` (env `OPENAI_BASE_URL`, `OPENAI_API_KEY`, `OPENAI_MODEL`; the key lives only in the git-ignored `.env`). Gemini uses `AI_GEMINI_KEY`, `AI_GEMINI_MODEL`. Same adapter (`GeminiClient`, class name kept), same contract: structured output via `response_format: json_schema`, images as `data:` URLs, `finish_reason` must be `stop`, reasoning text is ignored, one retry inside the shared deadline.

Checked live on 2026-11-09: `ai:preflight` ok (model `qwen3.8-max`, about 6 s); a text+schema call and a label-image call (FDA label: 230 kcal, 160 mg read correctly) work. Offline tests: `tests/Feature/Ai/OpenAiClientTest.php` (13).

Limits, stated plainly:

- The gateway is a FREE Qwen tier that returns HTTP 429 "at capacity" with `Retry-After` of 6 to 26 s. Our scan deadline is 25 s, so a longer `Retry-After` is not retried and the request fails with `AI_PROVIDER_ERROR`. In a live eval of 8 samples only a minority was read; a 3-image run read 1 of 3. Latency of a successful label read was about 10 to 22 s. Plan for a paid or reserved capacity before the demo.
- There is no `store:false` equivalent: retention and training use are the gateway operator's policy. Do not send real user photos or chats until that policy is known.
- The earlier eval numbers in this README were produced with Gemini, not Qwen. Accuracy of `qwen/qwen3.8-max` on the label sets is NOT measured yet (capacity-blocked).
- `php tools/ai-dev.php doctor` still reports the Gemini key flag only (the baseline helper was not changed).

## Food catalog: the team's `makanan` feeds the AI

The chat reads only `foods` (verified, per 100 g, with citations). Foods the admin verifies in the team's `makanan` table (`status = terverifikasi`) are published into `foods` automatically, through `ReviewFood` only (provider `CAREPLATE_MAKANAN`, audit id `sync:makanan`). One AI food per content version: a changed row revokes the old food and approves a new one; a rejected, deleted or no longer eligible row is revoked.

- **Only gram units.** `makanan` stores nutrients per portion (`kuantitas` + `satuan`). Rows in `g`/`gram` are converted to per 100 g with BCMath (rounded half up to 4 decimals). `ml`, `porsi`, `buah`, `mangkok` and similar are NOT published: no density or weight is known, and guessing would invent numbers.
- **Unknown stays null.** `gula` and `natrium` were added with a default of 0, so a stored 0 cannot be told from unknown and is published as null.
- **How it runs.** `MakananObserver` syncs after the team's transaction commits and never breaks the admin screen (a failure is logged with metadata only). Seeders run without model events: run `php artisan ai:sync-makanan` after `db:seed` and to repair or backfill (output: `published`, `unchanged`, `revoked`, `skipped:<reason>`, `failed`). Turn the observer off with `AI_SYNC_MAKANAN=false`.
- **Verification.** The approval is accepted only if the makanan row is still verified and the version was built from its current content (hash check in `ReviewCandidate`); users cannot submit this provider.
- **Limit.** The AI sees this food as "source-reviewed by an admin", not as nutritionist-verified.

## What exists

| Feature      | Summary                                                                                                                                                        |
| ------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Scan         | `POST /ai/v1/scan`: one label photo to structured nutrition facts plus a provisional assessment. Stateless: photo and result are never stored.                 |
| Chat         | Sessions and turns. Numbers always come from the verified catalog (database mode) or the answer says it is general knowledge. Stored for the owner for 7 days. |
| Foods        | `GET /ai/v1/foods`: search the verified catalog (no nutrient values, those arrive through chat claims).                                                        |
| Admin review | Approve a submission or revoke a food, with optimistic revision checks and an audit trail.                                                                     |
| Screens      | `/pindai-kemasan` and `/chat-gizi` (Inertia pages `User/Pindai`, `User/ChatGizi`, same permissions as the API) call `/ai/v1/*` from the browser with the session cookie and the `XSRF-TOKEN` header (`resources/js/lib/aiApi.ts`). A chat is titled by its first question (60 characters). |
| Purge        | `ai:purge-chats` deletes chats past retention (daily schedule entry exists).                                                                                   |

## Routes

Base: same origin as the Laravel app (local: `http://127.0.0.1:8001`). All routes sit in the `web`, `auth` group: session cookie, CSRF, JSON errors. Every response has `Cache-Control: no-store, private` and `X-Request-Id`.

| Method and path                                           | Needs                                | Success                       | Notes                                                           |
| --------------------------------------------------------- | ------------------------------------ | ----------------------------- | --------------------------------------------------------------- |
| POST `/ai/v1/scan`                                        | `health_tool:use_scanner`, throttle `ai-scan` | 200                           | multipart field `image`, JPEG or PNG, up to 5 MiB, up to 16 MP  |
| GET `/ai/v1/foods`                                        | `health_tool:use_ai_chat`                     | 200                           | `q` (<=200 chars, optional), `limit` 1 to 5                     |
| POST `/ai/v1/chat/sessions`                               | `health_tool:use_ai_chat`                     | 201                           | empty JSON object body                                          |
| GET `/ai/v1/chat/sessions`                                | `health_tool:use_ai_chat`                     | 200                           | `cursor`, `limit` 1 to 20; own sessions only                    |
| DELETE `/ai/v1/chat/sessions/{session}`                   | owner                                | 204                           | content erased at once; late results are refused                |
| GET `/ai/v1/chat/sessions/{session}/turns`                | owner                                | 200                           | `cursor`, `limit` 1 to 20; pending and failed turns are visible |
| POST `/ai/v1/chat/sessions/{session}/turns`               | owner, throttle `ai-chat`            | 200                           | one turn; see idempotency below                                 |
| POST `/ai/v1/admin/food-submissions/{submission}/approve` | `food:verify` and role `admin`       | 200                           | `{expected_revision, reviewer_note, acknowledge_review_flags?}` |
| POST `/ai/v1/admin/foods/{food}/revoke`                   | `food:verify` and role `admin`       | 200                           | `{expected_revision, reason}`                                   |

Permissions are the team's own names (no separate AI list): `health_tool:use_scanner` (scan), `health_tool:use_ai_chat` (chat, food search) and `food:verify` (admin approve/revoke, plus the `admin` role). `user`, `ahli_gizi` and `admin` get scan and chat; only `admin` verifies food, never `ahli_gizi`. Registration assigns `user`. `AiPermissionSeeder` must run after `RolePermissionSeeder` (which uses `syncPermissions`). There is no user food-submission endpoint: foods enter through the team's `makanan` (admin verifies) or the catalog seeder. Another user's session is always 404 (never 403), for read, send, delete and replay. A non-UUID id in an admin path is 404.

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
{
    "schema_version": "1.0",
    "request_id": "req_x",
    "extraction_status": "complete",
    "extraction": {
        "label_basis": "per_serving",
        "serving_size": {
            "value": 30,
            "unit": "g",
            "household_text": "3 keping"
        },
        "servings_per_package": 2,
        "nutrients": {
            "energy_kcal": {
                "value": 150,
                "qualifier": "exact",
                "unit": "kcal",
                "evidence": "label 150 kcal",
                "label_percent_akg": null
            }
        },
        "needs_review": false
    },
    "normalized_per_serving": {
        "energy_kcal": 150,
        "sodium_mg": 450,
        "sugars_total_g": 12
    },
    "assessment": {
        "policy": {
            "id": "careplate-ggl-screening-draft-v1",
            "status": "provisional"
        },
        "daily_reference_contribution_percent": {
            "sugars_total": 24,
            "sodium": 22.5,
            "fat_total": 11.94
        },
        "flags": [],
        "verdict": "policy_pending_validation",
        "summary": "..."
    },
    "warnings": [
        "Pembanding edukasi dewasa umum; bukan %AKG label atau kebutuhan personal."
    ]
}
```

`extraction_status` is `complete` or `partial`. Unknown values are `null`, never 0. An unreadable label is 422 `OCR_UNREADABLE`. The verdict is always `policy_pending_validation` and `flags` is empty: the screening policy is provisional.

Chat turn: `POST /ai/v1/chat/sessions/{id}/turns`

```json
{
    "client_turn_id": "40000000-0000-4000-8000-000000000001",
    "message": "Berapa protein 50 gram tahu contoh?",
    "food_id": "10000000-0000-4000-8000-000000000001",
    "amount_g": 50
}
```

`message` is 1 to 2000 characters after trim. `food_id` and `amount_g` are optional (`amount_g` > 0, <= 2000, 2 decimals max; with no amount in the body or text the server uses 100 g and says so). A conflict between the body amount and the text amount is 422 `AMOUNT_CONFLICT`. Response (200), abridged:

```json
{
    "schema_version": "1.0",
    "request_id": "req_x",
    "session_id": "...",
    "turn_id": "...",
    "client_turn_id": "...",
    "sequence": 1,
    "mode": "database",
    "retrieval_status": "found",
    "answer": "...",
    "claims": [
        {
            "food_id": "...",
            "revision": 3,
            "nutrient": "protein_g",
            "canonical_value": 10,
            "unit": "g",
            "canonical_basis": "100g",
            "requested_amount": 50,
            "derived_value": 5,
            "source_type": "database",
            "citation_id": "food-1"
        }
    ],
    "citations": [
        {
            "id": "food-1",
            "food_id": "...",
            "revision": 3,
            "food_name": "...",
            "basis": "100g",
            "source_reference": "...",
            "verified_at": "2026-11-06T02:00:00Z"
        }
    ],
    "general_knowledge_notice": null,
    "warnings": []
}
```

`mode` is `database`, `general`, `clarification` or `referral`; `retrieval_status` is `found`, `no_match`, `ambiguous` or `not_needed`. A `general` answer carries `general_knowledge_notice` and no citations. Show the notice and the citation source on every answer. Nutrient values appear only in `claims`, computed by the server; model text is checked and replaced by a server template if it contains numbers.

Idempotency is per `(session, client_turn_id)`. Same key and same body: completed returns the stored response (no new model call); still running returns 409 `TURN_IN_PROGRESS`; failed returns the stored error; a lease that ran out returns `AI_TIMEOUT`. Same key with a different body is 409 `IDEMPOTENCY_CONFLICT`. A deliberate retry needs a new `client_turn_id`. A second turn while one is running is 409 `SESSION_BUSY`.

History item (`GET .../turns`, wrapped as `{"data":[...],"next_cursor":null}`): `{id, client_turn_id, sequence, state: pending|completed|failed, user_content, response, failure_code, created_at}`. Sessions list items: `{id, state:"active", title, created_at, updated_at}`.

Foods: `GET /ai/v1/foods?q=ayam&limit=3` gives `{"data":[{"food_id","revision","name","basis":"100g","verification_level":"source_reviewed","source_reference"}]}`.

Submission body (synthetic):

```json
{
    "name": "Tahu Contoh (data sintetis)",
    "aliases": ["tahu contoh"],
    "basis": "100g",
    "nutrients": {
        "energy_kcal": 100,
        "fat_g": 5,
        "sugars_total_g": null,
        "protein_g": 10,
        "carbohydrate_g": 4,
        "sodium_mg": 20
    },
    "source": {
        "provider": "MANUAL",
        "source_id": "synthetic-fixture-only",
        "source_url": "https://example.invalid/x",
        "snapshot_version": "synthetic-v1",
        "source_sha256": null
    }
}
```

Reply: `{"submission_id","status":"pending","revision_counter":1}`. Admin approve/revoke reply: `{"food_id","revision","status":"verified|revoked","sync_status":"ready"}`. An identical repeated decision returns the stored result; a stale `expected_revision` is 409 `STALE_REVISION`. Approve uses the submission `revision_counter`, revoke uses `foods.current_revision`.

## Errors

Envelope: `{"error":{"code","message","retryable"},"request_id":"req_..."}`. Messages are Indonesian. `Retry-After` is set on 429. Raw provider output, prompts, SQL and stack traces never appear.

| HTTP | Code                                 | Retryable | Meaning and client action                                                                                                                                                                                                                                                                                                                                                 |
| ---: | ------------------------------------ | :-------: | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
|  401 | UNAUTHENTICATED                      |    no     | log in                                                                                                                                                                                                                                                                                                                                                                    |
|  403 | FORBIDDEN                            |    no     | missing permission or role                                                                                                                                                                                                                                                                                                                                                |
|  404 | NOT_FOUND                            |    no     | missing, foreign or deleted record                                                                                                                                                                                                                                                                                                                                        |
|  409 | IDEMPOTENCY_CONFLICT                 |    no     | same `client_turn_id`, different body                                                                                                                                                                                                                                                                                                                                     |
|  409 | TURN_IN_PROGRESS                     |    yes    | same turn still running; poll history or resend later                                                                                                                                                                                                                                                                                                                     |
|  409 | SESSION_BUSY                         |    yes    | another turn running, SQLite busy or MySQL lock wait/deadlock; retry shortly                                                                                                                                                                                                                                                                                              |
|  409 | STALE_REVISION                       |    no     | reload the record and decide again                                                                                                                                                                                                                                                                                                                                        |
|  409 | KNOWLEDGE_CHANGED                    |    yes    | a cited food was revoked or revised while the answer was prepared; the turn is closed as failed; send the question again as a new message (new `client_turn_id`)                                                                                                                                                                                                          |
|  413 | IMAGE_TOO_LARGE                      |    no     | smaller photo                                                                                                                                                                                                                                                                                                                                                             |
|  415 | UNSUPPORTED_MEDIA_TYPE               |    no     | JPEG or PNG only                                                                                                                                                                                                                                                                                                                                                          |
|  419 | CSRF_MISMATCH                        |    no     | refresh the CSRF cookie, log in again if needed                                                                                                                                                                                                                                                                                                                           |
|  422 | INVALID_INPUT                        |    no     | fix the input; admin approve uses this with a reason code in the message (`SOURCE_NOT_IN_MANIFEST`, `SOURCE_SHA_MISMATCH`, `REVIEW_ACK_REQUIRED`, `CANDIDATE_INVALID`, `SOURCE_MISSING`, `NOTE_REQUIRED`); every other 422 carries only the generic message "Data yang dikirim tidak valid." The envelope has no per-field errors, so validation detail is never returned |
|  422 | AMOUNT_CONFLICT                      |    no     | body amount and text amount disagree                                                                                                                                                                                                                                                                                                                                      |
|  422 | OCR_UNREADABLE                       |    no     | retake the photo                                                                                                                                                                                                                                                                                                                                                          |
|  429 | RATE_LIMITED                         |    yes    | wait `Retry-After`; the model was not called                                                                                                                                                                                                                                                                                                                              |
|  500 | INTERNAL                             |    no     | unexpected server error                                                                                                                                                                                                                                                                                                                                                   |
|  502 | AI_PROVIDER_ERROR, AI_OUTPUT_INVALID |    yes    | provider failed (also free-tier quota, HTTP 429: see Provider quota) or returned unusable output                                                                                                                                                                                                                                                                          |
|  503 | DB_BUSY                              |    yes    | SQLite busy or MySQL lock wait/deadlock on admin approve/revoke, food submission or session creation; nothing was written; repeat the same call                                                                                                                                                                                                                           |
|  503 | KNOWLEDGE_UNAVAILABLE                |    yes    | catalog not available                                                                                                                                                                                                                                                                                                                                                     |
|  503 | AI_DISABLED, POLICY_CONFIG_INVALID   |    no     | feature off or policy config invalid; the catalog list still works                                                                                                                                                                                                                                                                                                        |
|  504 | AI_TIMEOUT                           |    yes    | provider too slow (scan deadline 25 s, chat 20 s)                                                                                                                                                                                                                                                                                                                         |

## Quotas (per user, server side)

Scan 5 per minute and 10 per day per user (free tier). Chat 10 per minute and 100 per day. Edit the hard-coded numbers in `config/ai.php` (`quotas`) when you move to a paid tier. A request that fails validation still spends quota. Quota is checked before any provider call.

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

Config knobs (`config/ai.php`, `.env.ai.example`): `AI_ENABLED`, `AI_GEMINI_KEY`, `AI_GEMINI_MODEL` (default `gemini-3.5-flash-lite`; earlier evals used `gemini-3.6-flash`), scan and chat deadlines, token budgets, `AI_CHAT_RETENTION_DAYS`. AI is off until `AI_ENABLED=true`; the catalog list (`GET /ai/v1/foods`) never needs the model.

## Databases

Two engines are supported by the same code. The default and the demo are SQLite.

- SQLite: one instance, one persistent volume. Write transactions use `transaction_mode=IMMEDIATE` (PHP 8.4 or newer), `busy_timeout` 5 s. No multi-instance hosting.
- MySQL 8: InnoDB, `utf8mb4_unicode_ci`, strict mode. Tested on a throwaway MySQL 8.0.43 (`127.0.0.1:3407`, sql_mode with STRICT_TRANS_TABLES and ONLY_FULL_GROUP_BY, isolation REPEATABLE-READ). Other versions, other isolation levels and the team's real server were not tested. The `mariadb` connection in `config/database.php` is not tuned (no lock wait setting) and was not tested. Both `mysql` and `mariadb` pin the session time zone to `+00:00` (the app runs in UTC), so TIMESTAMP values do not depend on the server's SYSTEM zone or DST; the live `@@session.time_zone` check was not run (MySQL server unavailable).
- The app's MySQL connection runs `SET SESSION innodb_lock_wait_timeout = 5` on connect, so a blocked write fails after about 5 s instead of the InnoDB default of 50 s. This setting is configured and verified live on MySQL 8.0.43 (`@@session.innodb_lock_wait_timeout` = 5).

Row locks (`lockForUpdate()` compiles to nothing on SQLite, where the IMMEDIATE write lock already serialises). One order everywhere:

- chat: session row, then turn row, then cited food rows (shared lock). A competing turn, delete or finalize waits or fails as a retryable error, and it never acts on a stale snapshot.
- admin: approve locks the submission, then the food row; revoke locks only the food row. The importer locks the submission rows it may refresh.
- Lock errors become retryable errors: SQLite busy/locked (driver codes 5 and 6) and MySQL 1205 (lock wait timeout), 1213 (deadlock) and SQLSTATE 40001 map to `SESSION_BUSY` (chat) or `DB_BUSY` (admin approve/revoke, food submission, session creation). Nothing was written; repeat the same call. Other database errors are not remapped.

Behaviour differences you will see on MySQL:

- Collation: `LIKE`, equality and unique indexes ignore case, accents and trailing spaces. `GET /foods?q=` re-checks the matches in PHP, so `q=táhu` does not match `Tahu`. Chat food resolution was already exact. A food source triple that differs only by case (`abc` vs `ABC`) is one source on MySQL: a second submission is 409, never a second row. On SQLite it is a separate source. Sort by name is case-insensitive on MySQL and byte order on SQLite.
- JSON key order: responses stored as JSON are read back from the database (the first answer is the stored result, so it equals every replay). MySQL reorders object keys (short keys first). Clients must not rely on key order.
- Column limits are validated, never truncated: `source_url` is 500 characters (migration `2026_11_09_000001`), nutrient values have at most 8 integer and 4 decimal digits (a 5th decimal is refused, not rounded), and over-long aliases, audit ids and notes are 422.
- The unique index on `food_submissions` has an explicit short name (the generated name exceeded the 64-character MySQL limit).

Known limits on MySQL:

- Approving two NEW foods with adjacent source triples at the same moment can deadlock on a gap lock. One call gets a retryable `DB_BUSY`; the admin repeats it.
- `ai:purge-chats` filters chat turns and sessions by columns without a dedicated index (it does select by primary key and `chat_sessions.active_turn_id`, which has an index since migration `2026_11_09_000002`). Fine at MVP volume; run it off-peak. A rare deadlock with a chat turn gives that turn a retryable `SESSION_BUSY` or fails one purge chunk (earlier chunks stay committed, the next run continues).
- The two-connection concurrency tests run in one PHP process: a waiting connection times out after 1 s and the test asserts the retryable error. A real wait followed by success is not shown by a test.

Run the suite on MySQL only against a throwaway server that you own, never a shared or team database:

```
php tools/ai-test-mysql.php [--compact] [--filter=x] [tests/Feature/Ai/FooTest.php]
```

The runner force-sets host `127.0.0.1`, port `3407`, database `careplate_ai_test` (and `careplate_ai_cc` for the two-connection tests), user `root` with an empty password, and refuses anything else before connecting. Create those two empty databases on a local mysqld on port 3407 first.

Status of the MySQL runs: the full MySQL suite was run on the final code (commit `ede68c5`, MySQL 8.0.43 throwaway server): 959 tests, 938 passed, 9 failed, 12 skipped. The 9 failures are the same baseline as SQLite (8 Vite manifest tests and the registration test), none is AI-owned; the 12 skips are SQLite-only tests. This includes the two-connection MySQL concurrency tests, the importer lock test, the 5 s lock-wait check and the MySQL timezone/index checks that earlier could not run. Not covered: your own MySQL server (version, `sql_mode`, time zone), MariaDB, and more than one application instance.

## Provider quota

The Gemini key used for development is a free-tier key. Observed: about 20 requests per day on `gemini-3.6-flash` (free tier; other models may differ), rejected by the provider with HTTP 429 and shown to clients as 502 `AI_PROVIDER_ERROR`, and 5 per minute on `gemini-3-flash-preview`. That is far below the app quotas (scan 10 per day per user). A demo or the judging can therefore hit `AI_PROVIDER_ERROR` long before any app quota.

- For the competition demo and for real users use a paid-tier key (this also removes the free-tier content-use clause, see Privacy facts), or temporarily lower the AI quotas in `config/ai.php`.
- Database-mode chat answers (a catalog food asked about by name) never call the model and keep working without the provider; `GET /ai/v1/foods` too. Scan and general-knowledge chat need the provider. Mixed mode (a catalog food plus an education question) calls the model once; only the database part survives a provider failure: the answer then holds the catalog facts and citation without the education text, plus a warning that the explanation is unavailable.
- Provider failures in eval runs (`AI_PROVIDER_ERROR`, which includes quota rejections, `AI_TIMEOUT`, `AI_DISABLED`) are reported as `provider_errors`, not as model failures. Other errors stay scored and are also listed as `harness_errors` (see `ai/evals/README.md`).

## Chat and Indonesian foods

Questions in Indonesian are the primary language. The chat catalog is 10 USDA Foundation foods (snapshot 2026-10-29) with Indonesian display names. Aliases are derived from the name, for example `jeruk` and `susu sapi utuh`:

| Catalog name                 | USDA record                                      |
| ---------------------------- | ------------------------------------------------ |
| Beras putih mentah           | Rice, white, long grain, unenriched, raw         |
| Apel dengan kulit            | Apples, fuji, with skin, raw                     |
| Wortel mentah                | Carrots, mature, raw                             |
| Tomat mentah                 | Tomatoes, grape, raw                             |
| Dada ayam tanpa kulit mentah | Chicken, breast, boneless, skinless, raw         |
| Kentang mentah               | Potatoes, russet, without skin, raw              |
| Susu sapi utuh basis gram    | Milk, whole, 3.25% milkfat, with added vitamin D |
| Ubi jalar mentah             | Sweet potatoes, orange flesh, without skin, raw  |
| Jeruk bagian dapat dimakan   | Oranges, raw, navels                             |
| Brokoli mentah               | Broccoli, raw                                    |

Local foods such as tempe, tahu, nasi matang, telur, bayam and yogurt are NOT in the catalog. A question about them returns `retrieval_status: no_match` with no numbers and a suggestion to scan the label; it does not invent values. The 10 CSV rows that the importer skipped (never invented), with the reason:

| Row                         | Reason                                                                                     |
| --------------------------- | ------------------------------------------------------------------------------------------ |
| FOOD-001 nasi putih matang  | no cooked white rice record (raw rice is not offered as cooked)                            |
| FOOD-003 pisang             | ambiguous: overripe vs ripe records                                                        |
| FOOD-007 telur              | no raw fresh whole egg record                                                              |
| FOOD-011 oat kering         | no Foundation record has every query term in the required state (oats dry)                 |
| FOOD-014 pepaya             | no papaya in Foundation                                                                    |
| FOOD-015 bayam              | spinach descriptions carry no raw state                                                    |
| FOOD-017 daging sapi giling | ambiguous: 90/10 vs 80/20                                                                  |
| FOOD-018 yogurt plain       | no Foundation record has every query term in the required state (yogurt plain unsweetened) |
| FOOD-019 tahu               | not in Foundation                                                                          |
| FOOD-020 tempe              | not in Foundation                                                                          |

Some of these could be added from another source (USDA SR Legacy was not tried), which needs its own snapshot entry and review. A general education question (for example what protein is for) is answered by the model as `mode: general` with the general-knowledge notice, no citations, and the narration guard that replaces any text containing numbers with units. Chat quality was evaluated on the 12 spec cases mapped to the catalog (see Honest limits), not on a set of Indonesian questions.

## Indonesian labels

Scan was first evaluated on 8 US, Canadian and EU web images (`ai/evals/ocr/`: FDA, Wikimedia Commons). The Indonesian sets are separate (`ai/evals/ocr-id/`, details in `ai/evals/README.md`): 8 real photos from Open Food Facts contributors (CC BY-SA 3.0, truth transcribed by an AI assistant, `unverified_by_human`; ID-05 is partly hidden by glare, ID-08 is an export-style English/French label) and 6 synthetic BPOM-format labels (exact truth by construction, clean renders).

| Model, set, runs                                         |             Requests | Scored requests | Result                                                                                                                                  |
| -------------------------------------------------------- | -------------------: | --------------: | --------------------------------------------------------------------------------------------------------------------------------------- |
| `gemini-3.6-flash` (shipped), real, 3 runs               |                   24 |               9 | every scored field correct on those 9; 15 provider errors (`AI_PROVIDER_ERROR`, quota rejections seen in the run log); ID-08 never read |
| `gemini-3-flash-preview`, synthetic, 3 runs              |                   18 |               7 | 1 unsafe inference: serving size 100 g taken from "Per 100 g"; 11 provider errors (`AI_PROVIDER_ERROR`, HTTP 429 seen in the run log)   |
| `gemini-3.1-flash-lite`, 1 run, prompt v1.1 before/after | 8 + 6 + 8 per prompt |             all | unsafe inferences on the original set 3 to 1; table in `ai/evals/README.md`                                                             |

- The shipped model was never read on the synthetic set, and prompt v1.1 was never run on `gemini-3.6-flash`. v1.1 (`ai/prompts/ocr-system.txt`, sha `4f4184e1`) is kept PENDING that regression run; if the shipped model regresses, revert the file to the version at commit `718dc98`. The commands to finish are in `ai/evals/README.md`.
- Salt vs sodium: a label row named garam, zout, sel, sal or salz without natrium, sodium or sodio is salt, not sodium: sodium stays unknown (`null`) and a warning is returned. `Garam (Natrium)` and the sodium words are sodium.
- This is not a validated accuracy figure for Indonesian packaging. 9 scored requests on 8 photos is a smoke test: repeated runs measure stability of the same pictures, not independent samples, and the truth of the real photos was not checked by a human.

## Honest limits

Catalog and wording

- The catalog is 10 USDA Foundation foods. They were approved by a script (`ai:seed-catalog`): the source id and the archive sha256 were matched against `ai/data/snapshot.manifest.json`. The nutrient values were NOT re-verified, review flags were acknowledged by the script, and no human or nutritionist reviewed anything. Product copy must say "source-reviewed (script)" and never "nutritionist-verified" or "lab-verified".
- Several candidates carry review flags: variant picks (3 foods), single-variety only (5 foods), legacy energy nutrient 1008 (4 foods, energy method labelled), exact zeros (2 foods). Sugars are `null` for 9 of 10 foods because USDA Foundation rarely reports them.
- Foods outside the catalog get no numbers: the chat says so and suggests scanning the label.
- User-submitted foods (provider `MANUAL`) stay pending. Admin approve returns 422 `INVALID_INPUT` with `SOURCE_NOT_IN_MANIFEST` in the message until the manifest supports them. Scans never feed submissions.
- The scan assessment policy is provisional: verdict `policy_pending_validation`, no flags. Do not present it as a health rating.

Evaluation

- Scan eval: 8 Wikimedia Commons web sample images (3 dev, 5 holdout), mostly US, Canadian and EU labels, ground truth written by an AI assistant and not checked by a human. 100% on tiny n is a smoke test, not accuracy, and says nothing about Indonesian packaging (see Indonesian labels for those sets). Latency on `gemini-3.6-flash`: p50 about 15.6 s, p95 about 21.8 s (n=8), against a 25 s deadline and the spec target of 15 s. Plan for slow scans and show progress.
- Chat eval: 9 applicable of 12 golden cases, 4 of the 9 mapped or substituted onto the demo catalog; 9 of 9 routing, claims and citations pass. `unsafe_claims = 0` holds by construction (the guard blocks every number-plus-unit), it is not an independent safety result. Only 2 cases reach the model. One run per case.
- NarrationGuard is lexical. Known bypass classes: number words attached to units in unusual forms, units outside its list, other-language units, and citation styles it does not recognise. It fails closed on invalid UTF-8 and regex errors, and the numbers in answers come from server claims anyway, but text quality of general answers is not human graded.
- The eval images carry licence caveats (CC BY, CC BY-SA, third-party packaging artwork). Replace them with team-owned photos before public redistribution.

Model

- `gemini-3.8-flash` was the planned model but did not respond on the project key (no answer in 45 to 50 s, tested twice). `gemini-3.6-flash` answers in about 1 s on preflight and was used for every evaluation, so it is the frozen default. The model id is validated against a strict pattern before it is placed in the request URL.

Operations

- SQLite needs PHP 8.4 or newer for `transaction_mode=IMMEDIATE` (on older PHP Laravel silently falls back to deferred transactions). Engines, locks and the MySQL status are in Databases.
- PHP ini for the 5 MiB scan contract: `upload_max_filesize` >= 5M and `post_max_size` >= 6M (the upload is multipart; the base64 expansion happens server side). With the stock `upload_max_filesize=2M`, a 2 to 5 MiB photo is answered 413 IMAGE_TOO_LARGE. `max_execution_time` must sit comfortably above the 25 s scan deadline (and the 20 s chat deadline). A fatal execution timeout cannot be caught: the request dies without an envelope and a chat turn stays `pending` until its lease expires.
- `memory_limit` must be at least 256M for the scan route (the ImagePreparer guard decodes up to 16 MP).
- Stored chat turns carry an HMAC `request_hash` keyed with `app.key`. Rotating `APP_KEY` breaks replay of stored turns (they will read as a different request).
- Concurrency tests use two PDO handles in one PHP process, not separate OS processes.
- Cost was not computed (no price table); token usage is recorded in the eval results.
- The provider key must be rotated by its owner; nothing in the repository contains it.
