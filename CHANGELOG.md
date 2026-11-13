# Changelog

## 1.0.0 (unreleased)

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
