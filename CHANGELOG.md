# Changelog

## Unreleased

## 1.2.0 (2026-11-20)

- Settings → Photos.sh → Remove background → "Model": the model that finds
  the subject, a select with a description of each. Atlas (the default;
  everyday product photos: a product alone or with its box, on any
  backdrop), Lumen (pale packaging on a light backdrop, such as a cream or
  wooden box beside a bottle, and close-ups where the product fills the
  photo) or Muse (people: portraits, and models wearing the product). Each
  costs the same. It applies to every Remove background (the row action, the
  edit screen, the bulk actions, the WooCommerce bulk action and WP-CLI). The
  API is sent `model=lumen` or `model=muse` only when one of those is chosen;
  with Atlas the parameter is left out and the API's default applies. The
  setting is saved in `photos_sh_settings` as `model`; sites that never chose
  one use Atlas.
- WP-CLI: `wp photos-sh remove-background --model=atlas|lumen|muse` overrides
  the setting for that run.
- A result made with Lumen or Muse has `['model' => 'lumen']` (or `'muse'`)
  in `_photos_sh_params`, next to `'studio' => true` on a studio shot.
- Needs photos-sh/photos 1.3 (`removeBackground(model: …)`).

## 1.1.0 (2026-11-16)

- Settings → Photos.sh → Remove background → "Studio shots" (off by default):
  your product photos are taken on a plain light backdrop (a light tent), so
  background removal also keeps the box, case or lid shown with the product.
  When it is on, Remove background sends `studio=true` (the row action, the
  edit screen, the bulk actions, the WooCommerce bulk action and WP-CLI). The
  setting is saved in `photos_sh_settings` as `studio`.
- WP-CLI: `wp photos-sh remove-background --studio` (or `--no-studio`)
  overrides the setting for that run.
- A result made on a studio shot has `['studio' => true]` in
  `_photos_sh_params`.
- Needs photos-sh/photos 1.1 (`removeBackground(studio: …)`); the zip comes
  with 1.2.1.

## 1.0.0 (2026-11-13)

First version.

- Settings → Photos.sh: API key (or the `PHOTOS_API_KEY` constant or environment
  variable), the connected account and credits left, base URL under "Advanced".
- Media library: Remove background, Upscale 2× / 4×, Remove text and Fix for a
  marketplace, as row actions in the list view and in a box on the attachment
  edit screen. The result is saved as a new attachment.
- Media library bulk actions, one image per background task (Action Scheduler
  when available, WP-Cron otherwise), with a progress notice.
- WooCommerce: "Remove background from product images" bulk action on the
  products list.
- WP-CLI: `wp photos-sh remove-background|upscale|remove-text|fix <attachment-id>…`
  and `wp photos-sh account`.
- Media library (row actions, the box on the attachment edit screen, bulk
  actions) and WP-CLI: Fix light and colour (`enhance`), Remove noise
  (`denoise`), Colourise (`colorize`), Restore faces (`restore-faces`) and
  Hide faces (`blur-faces`, blurred). Each result is a new attachment, as
  before.
- Restore faces and Hide faces say how many faces they worked on (in the
  notice, on the new image's edit screen, in WP-CLI). When no face was found,
  no new image is saved and the editor is told so; in a bulk action the image
  is listed as skipped.
- A caution for each tool that needs one, in the box on the edit screen and as
  the tip of the row action: Colourise guesses its colours; Restore faces
  invents detail and is not for identity photos or evidence; Remove noise is
  slow and takes photos up to 12 megapixels; Hide faces leaves a face it does
  not find showing.
- Write alt text (`wp photos-sh alt-text`): sets the attachment's alternative
  text to the API's one-sentence description. Existing alternative text is
  kept unless the editor chooses "Replace alt text", the bulk action "Write
  alt text (replace existing text)", or `--replace`; an image that is skipped
  is not sent to the API. The UI says the text is written by AI, in English,
  and should be read before publishing.
- New action `photos_sh_alt_text_written` (`$attachment_id`, `$text`,
  `$previous`).
- `_photos_sh_result` holds `faces` (and `skipped` for Restore faces) for the
  two face tools.
