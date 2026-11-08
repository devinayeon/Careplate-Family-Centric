# Scan evaluation samples

`ocr/manifest.csv` lists 8 nutrition-label images (3 dev, 5 holdout, product-disjoint) fetched from Wikimedia Commons on 2026-11-08 under free licences (public domain, CC0, CC BY, CC BY-SA). Source URL, licence, author and SHA-256 are recorded per file. Attribution for the CC BY and CC BY-SA files is in the manifest `author_attribution` column; the files were re-encoded (see `processing`), so they are adapted copies.

Licences, attribution and what was changed per file are in `ocr/NOTICE.md`. These images show third-party packaging; SMP-01 in particular is a scan of store-brand packaging whose uploader-declared public-domain status does not cover the artwork (residual copyright risk). Remove or replace the images with team-owned photos before any public redistribution beyond the competition evaluation.

Limits you must keep in mind:

- The samples are mostly US/Canadian/European web samples, not Indonesian packaging. Results are NOT accuracy on Indonesian packaging.
- The ground truth was transcribed by an AI assistant reading the images, `truth_status=unverified_by_human`. No second reviewer has checked it.
- 8 images and 1 run each is a smoke test of the pipeline, not a statistic. n/N per field is tiny.
- The team's own licensed photos (see the spec dataset plan) replace these later.

Run (live, needs `AI_ENABLED=true` and a key in the git-ignored `.env`; sends one request per image):

    php tools/ai-dev.php ai ai:eval scan

Results go to `results/scan-<UTC timestamp>.json` (no image bytes, no base64). `--split=dev` limits the run to one split.

## Chat evaluation

`chat-golden.jsonl` is the unchanged copy of the 12 spec cases. Their inputs are fixture descriptions, so `chat-catalog-map.json` gives each one a concrete Indonesian message and an expectation that is resolved against the live demo catalog (value = per-100 g value x amount / 100, two decimals). A case whose premise the demo catalog lacks (CHAT-04 several rice variants, CHAT-08 revised food, CHAT-09 revoke during a turn) is `applicable=false` with a reason and is not scored; synthetic coverage of those lives in the Pest tests only.

Run (live; only general and mixed cases call the model, the rest are server templates):

    php tools/ai-dev.php ai ai:eval chat

It creates temporary users and sessions in the configured database, sends each case through `ChatService`, deletes everything it created (foods are only read), and writes `results/chat-<UTC timestamp>.json`. Checks are deterministic (mode, retrieval status, claims at 2 dp, citations, model-call count, unsafe numbers in model text); relevance and honesty of the model text still need human review. One run per case is a smoke test, not a statistic.

Reading the chat result honestly:

- **9/9 is on 9 of 12 cases, 4 of them mapped/substituted onto the demo catalog** (3 `mapped_fixture`, 1 `substituted_nutrient`; see `summary.fidelity`). It is a demo-catalog smoke test and not a pass of the 12 golden cases. Not-applicable and skipped items carry `pass`/`routing_ok` = null, not false.
- **`unsafe_claims = 0` is close to guaranteed by construction** and is not an independent safety measurement: NarrationGuard blocks every number-plus-unit before a narration can reach the answer, so a narration that was used cannot contain one. The informative numbers are `narrations_rejected n/N` (cases where the model answered but its text was dropped for the server template or an error) and `rejected_unsafe_numbers` (how many numbers sat in those dropped texts; the texts are not stored).
- **Expected claim values reuse the production `AnswerRenderer::derive`**, so the eval checks wiring, routing, citations and 2 dp rounding, not the arithmetic. The BCMath arithmetic is covered independently by `tests/Feature/Ai/AnswerRendererTest.php`.
- For the two-turn CHAT-07, `latency_ms` is the last turn only and `model_calls` spans both turns. `golden_sha256` is computed over LF-normalised content.

## Indonesian label sets (BPOM "Informasi Nilai Gizi")

`ocr-id/manifest.csv` holds 8 real photos of Indonesian packaging (Open Food Facts contributor photos, CC BY-SA 3.0, `kind=web_sample_id`, 3 dev / 5 holdout, product-disjoint). `ocr-id/synthetic/manifest.csv` holds 6 labels rendered by `ocr-id/synthetic/generate.php` (`kind=synthetic_id`, `truth_status=exact_by_construction`). Licences, attribution and changes are in `ocr-id/NOTICE.md`. This is not the USDA chat catalog and not the FDA/Wikimedia set in `ocr/`.

Limits you must keep in mind:

- The real truth was transcribed by an AI assistant reading the photos, `truth_status=unverified_by_human`. Some digits in ID-05 (Mizone) are partly hidden by glare; the annotation is flagged in the manifest notes. ID-08 is an English/French export-style label (dual column), not BPOM wording.
- The synthetic labels are clean renders. They test Indonesian label semantics (decimal comma, kkal, energy distractor, %AKG, Garam vs Natrium, less-than, kJ, dual columns, household serving) and not photographic robustness. 100% on them says little about photos.
- 8 real photos x 3 runs is 24 requests on 8 images: repeated runs measure stability of the same pictures, not 24 independent samples.

Run (live; sends one request per image and run):

    php tools/ai-dev.php ai ai:eval scan --manifest=ai/evals/ocr-id/manifest.csv --runs=3
    php tools/ai-dev.php ai ai:eval scan --manifest=ai/evals/ocr-id/synthetic/manifest.csv --runs=3

`--manifest` must be a `.csv` under `ai/evals` (default `ocr/manifest.csv`), `--runs` is 1 to 5 (default 1). The result JSON has `summary` (overall fields n/N across all requests, abstentions, invalid outputs, unsafe inferences, latency p50/p95, tokens), `summary.by_kind` (the same per `kind`), `summary.errors_by_code`, `summary.per_image` (per-field pass counts per image across the runs), `summary.unstable` (fields that passed in some runs and failed in others) and one `items` entry per request with `run`.

### Indonesian live runs (2026-11-08/09 UTC), what the result files are

The configured model `gemini-3.6-flash` is on a free-tier key limited to 20 requests per day, which was exhausted during the first 3-run pass. Result files are therefore of three kinds, and each states its model in `model_config`:

- `scan-20261108T175225Z.json`: real Indonesian set, `gemini-3.6-flash`, `--runs=3`, 24 requests of which 15 failed with HTTP 429 (`AI_PROVIDER_ERROR`). Only 9 requests produced a reading; all 9 were fully correct. The error counts are quota, not model, failures.
- `scan-20261108T175815Z.json`: synthetic set, `gemini-3-flash-preview`, `--runs=3`, 18 requests of which 11 failed with HTTP 429 (5 requests per minute on that model); 7 readings.
- `scan-20261108T180056Z.json`, `...180216Z.json`, `...180454Z.json` (prompt sha `c599e583`, before) and `...181603Z.json`, `...181701Z.json`, `...181826Z.json` (prompt sha `4f4184e1`, after): `gemini-3.1-flash-lite`, `--runs=1`, real Indonesian, synthetic, and the original `ocr/` set. This is a different (smaller) model, used for the prompt before/after comparison because it was the only one with quota left. It is not the shipped model.

The 3-run protocol on `gemini-3.6-flash` (real and synthetic sets, plus the original set once for the prompt regression) has to be repeated when the daily quota is back. Use `--pause=13` on keys with a per-minute limit.

### Re-scoring and valid readings

Provider failures (HTTP 429 quota, timeouts, disabled) say nothing about the model, so since scorer version 2 they are excluded from field scores, abstentions, latency and token totals and reported as `provider_errors` (with `provider_errors_by_code`); `valid_readings` is the real n per group and per image. `AI_OUTPUT_INVALID` is the model's own unusable output and stays scored. The result files in this folder were re-scored offline (no request, no key needed) with `php tools/ai-dev.php ai ai:eval scan --rescore=ai/evals/results/<file>.json`; the item rows are untouched, `summary` is recomputed, `scorer_version` and `rescored_at` are recorded. After re-scoring: the `gemini-3.6-flash` real run is 9 valid readings and 15 provider errors (every scored field 9/9, latency p50 7.4 s, p95 12.0 s over the 9) and the `gemini-3-flash-preview` synthetic run is 7 valid readings and 11 provider errors.

### Prompt v1.1 status (pending a gemini-3.6-flash regression run)

`ai/prompts/ocr-system.txt` v1.1 (sha `4f4184e1`) adds three rules: prefer the printed kkal when kJ and kkal are both printed, "Per 100 g" is a basis and not a serving size, and Garam/Salt/Zout/Sel without Natrium/Sodium is not sodium (evidence names the printed row). It is kept PENDING a regression run on `gemini-3.6-flash`:

- The `gemini-3.6-flash` (real, 9 valid readings) and `gemini-3-flash-preview` (synthetic, 7 valid readings) result files were produced with the OLD prompt (sha `c599e583`). No `gemini-3.6-flash` reading exists with prompt v1.1.
- The free-tier quota (20 requests/day/model on `gemini-3.6-flash`; 5/minute on `gemini-3-flash-preview`) blocked the protocol (3 runs of the real and synthetic sets, plus 1 run of the original set).
- The only before/after evidence is on `gemini-3.1-flash-lite`, 1 run per image (a different, smaller model), prompt `c599e583` before and `4f4184e1` after:

| set | prompt | energy | fat | sugar | protein | carb | sodium | serving | basis | unsafe | notes |
|---|---|---|---|---|---|---|---|---|---|---|---|
| real (8) | before | 7/8 | 8/8 | 7/8 | 8/8 | 7/8 | 8/8 | 8/8 | 8/8 | 0 | ID-08 energy 397.7 (kJ converted), ID-05 sugar/carb 0 |
| real (8) | after | 8/8 | 8/8 | 7/8 | 8/8 | 7/8 | 8/8 | 8/8 | 8/8 | 0 | ID-05 sugar/carb 0 |
| synthetic (6) | before | 5/6 | 6/6 | 6/6 | 6/6 | 6/6 | 5/5 (+1/1 null) | 4/4 (+2/2 null) | 6/6 | 0 | SYN-02 energy 85.086 (kJ converted) |
| synthetic (6) | after | 6/6 | 6/6 | 6/6 | 6/6 | 6/6 | 5/5 (+1/1 null) | 4/4 (+2/2 null) | 6/6 | 0 | none |
| original (8, 1 abstention) | before | 6/6 | 7/7 | 6/6 | 7/7 | 7/7 | 5/5 (+2/3 null) | 5/5 (+1/3 null) | 7/7 | 3 | SMP-01 sodium 10 mg less_than (salt read as sodium), SMP-01 serving 100 g, SMP-02 serving 8 ml |
| original (8) | after | 6/6 | 7/7 | 6/6 | 7/7 | 7/7 | 5/5 (+3/3 null) | 5/5 (+2/3 null) | 7/7 | 1 | SMP-02 serving 8 ml (unchanged) |

- Unresolved: ID-05 (Mizone) sugar and carbohydrate. The digits are half hidden by glare; the annotation (9 g) is the annotator's best reading. `gemini-3.6-flash` read 9 g (1 reading), `gemini-3.1-flash-lite` read 0 g in all of its runs. Needs a human look at the photo.

Commands to finish the protocol when the quota resets (50 requests; spread over days on a free key, or use a paid key; `--pause=13` respects a 5/minute limit). Run the original set first with the OLD prompt only if a 3.6 baseline for the original set is wanted (the earlier 3.6 baseline is `scan-20261108T071717Z.json`, prompt `c599e583`):

    php tools/ai-dev.php ai ai:eval scan --manifest=ai/evals/ocr-id/manifest.csv --runs=3 --pause=13
    php tools/ai-dev.php ai ai:eval scan --manifest=ai/evals/ocr-id/synthetic/manifest.csv --runs=3 --pause=13
    php tools/ai-dev.php ai ai:eval scan --runs=1 --pause=13

If `gemini-3.6-flash` regresses on the original set or the Indonesian sets, revert `ai/prompts/ocr-system.txt` to the version at commit 718dc98.
