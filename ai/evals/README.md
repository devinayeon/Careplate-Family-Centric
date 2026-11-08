# Scan evaluation samples

`ocr/manifest.csv` lists 8 nutrition-label images (3 dev, 5 holdout, product-disjoint) fetched from Wikimedia Commons on 2026-11-08 under free licences (public domain, CC0, CC BY, CC BY-SA). Source URL, licence, author and SHA-256 are recorded per file. Attribution for the CC BY and CC BY-SA files is in the manifest `author_attribution` column; the files were re-encoded (see `processing`), so they are adapted copies.

Limits you must keep in mind:

- The samples are mostly US/Canadian/European web samples, not Indonesian packaging. Results are NOT accuracy on Indonesian packaging.
- The ground truth was transcribed by an AI assistant reading the images, `truth_status=unverified_by_human`. No second reviewer has checked it.
- 8 images and 1 run each is a smoke test of the pipeline, not a statistic. n/N per field is tiny.
- The team's own licensed photos (see the spec dataset plan) replace these later.

Run (live, needs `AI_ENABLED=true` and a key in the git-ignored `.env`; sends one request per image):

    php tools/ai-dev.php ai ai:eval scan

Results go to `results/scan-<UTC timestamp>.json` (no image bytes, no base64). `--split=dev` limits the run to one split.
