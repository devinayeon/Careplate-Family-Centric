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
