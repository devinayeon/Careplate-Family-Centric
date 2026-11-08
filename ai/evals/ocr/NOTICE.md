# Notice for the sample label images

The 8 images in `images/` were downloaded from Wikimedia Commons on 2026-11-08 and are used only as test inputs for the scan evaluation (`php tools/ai-dev.php ai ai:eval scan`). Machine-readable data (source URL, licence, author, sha256 of the stored and the original file) is in `manifest.csv`.

## Per-image attribution and changes

All stored copies are adapted: JPEGs were re-encoded with GD at quality 85 and all EXIF metadata (including GPS where present) was removed. Further changes are listed per file. No other edits (no crop, no retouching) were made.

| File | Title and source | Author | Licence | Changes |
|---|---|---|---|---|
| smp-01-oranges.jpg | "Nutrition facts label and Nutri-Score on oranges.jpg", https://commons.wikimedia.org/wiki/File:Nutrition_facts_label_and_Nutri-Score_on_oranges.jpg | User:The RedBurn | Public domain as declared by the uploader (see Packaging copyright, residual risk) | Re-encoded JPEG q85, EXIF stripped, no resize |
| smp-02-fruit-drink-box.jpg | "Nutrition facts 2014-03-17 08-47.jpg", https://commons.wikimedia.org/wiki/File:Nutrition_facts_2014-03-17_08-47.jpg | User:Tcoe33 | CC BY-SA 3.0 (https://creativecommons.org/licenses/by-sa/3.0/) | EXIF rotation applied, re-encoded JPEG q85, EXIF stripped, downscaled from 2448x3264 to 2081x2774 |
| smp-03-fruit-salad.jpg | "Fruit salad (1).jpg", https://commons.wikimedia.org/wiki/File:Fruit_salad_(1).jpg | Renee Comet (photographer), US National Cancer Institute | Public domain (US government work) | Re-encoded JPEG q85, EXIF stripped, no resize |
| smp-04-fda-2016.png | "FDA Nutrition Facts Label 2016.png", https://commons.wikimedia.org/wiki/File:FDA_Nutrition_Facts_Label_2016.png | U.S. Food and Drug Administration | Public domain (US government work) | None (byte-identical to the download) |
| smp-05-canada-bilingual.jpg | "NutritionFactsValeurNutritive.jpg", https://commons.wikimedia.org/wiki/File:NutritionFactsValeurNutritive.jpg | User:Mufin777 | CC BY-SA 4.0 (https://creativecommons.org/licenses/by-sa/4.0/) | Re-encoded JPEG q85, EXIF stripped, no resize |
| smp-06-hk-bread.jpg | "Sandwich bread (cropped).JPG", https://commons.wikimedia.org/wiki/File:Sandwich_bread_(cropped).JPG | User:KampanHungs | CC BY 3.0 (https://creativecommons.org/licenses/by/3.0/) | Re-encoded JPEG q85, EXIF stripped, no resize |
| smp-07-snack-cake-cropped.jpg | "Nutrition Facts label with 94% saturated fat per serving.jpg", https://commons.wikimedia.org/wiki/File:Nutrition_Facts_label_with_94%25_saturated_fat_per_serving.jpg | User:B137 | CC0 1.0 | Re-encoded JPEG q85, EXIF stripped, no resize |
| smp-08-ryazhenka.jpg | "Nutrition facts panel on ryazhenka package.jpg", https://commons.wikimedia.org/wiki/File:Nutrition_facts_panel_on_ryazhenka_package.jpg | User:Sokolikmawwer0 | CC BY-SA 3.0 (https://creativecommons.org/licenses/by-sa/3.0/) | Re-encoded JPEG q85, EXIF stripped, no resize |

Authors are Wikimedia Commons user names as shown on the file pages; the licence is as stated there. The files carry no endorsement by the authors.

## Share-alike scope

ShareAlike applies only to the adapted CC BY-SA files (smp-02, smp-05, smp-08): copies or further adaptations of those images must stay under the same licence. It does not extend to the Careplate application code, prompts, schemas or the rest of this repository, which only reads these files as separate test data and does not combine them into a derivative work.

## Packaging copyright

Most of these images show third-party product packaging (brand logos, illustrations, Nutri-Score marks, label layouts). They are used here only as evaluation test inputs.

- A Commons licence only covers what the uploader can license, which is the photograph or scan. It cannot release the copyright in the packaging artwork shown.
- smp-01 is the clearest case: the uploader declared the scan public domain, but it is a scan of a store-brand bag with its logo, orange illustration and Nutri-Score mark. That declaration does not cover the packaging artwork and carries residual risk.
- The FDA label (smp-04) is a US government illustration and the fruit salad (smp-03) is a US government photograph; the other photos include packaging artwork to a smaller extent.

Before any public redistribution of this repository beyond the competition evaluation, remove these images and replace them with team-owned photos (taken with permission as the dataset plan requires), and update `manifest.csv` and the results accordingly.
