# Photos.sh for WordPress

The [Photos.sh](https://photos.sh) image tools in the WordPress media library:
remove backgrounds, upscale, remove text, fix light and colour, remove noise,
colourise, restore faces, hide faces, fix product photos for marketplaces, and
write alt text. Every new image is saved as a new attachment; originals are
never overwritten.

- WordPress 6.5 or later, PHP 8.3 or later.
- Built on the PHP library [`photos-sh/photos`](https://github.com/photos-sh/photos-php),
  bundled in the plugin zip. HTTP goes through `wp_remote_request()`.
- Plugin slug and text domain: `photos-sh`. License: GPL-2.0-or-later.

## Install

1. Download `photos-sh.zip` from the
   [latest release](https://github.com/photos-sh/photos-wordpress/releases/latest).
2. In WordPress, go to Plugins → Add New → Upload Plugin, choose the zip and
   activate the plugin.
3. Create an API key on your organisation's API keys page in the Photos.sh
   console, and enter it under Settings → Photos.sh.

To update, upload the zip of a newer release the same way; WordPress offers to
replace the version that is installed. The plugin is not in the wordpress.org
plugin directory, so WordPress does not offer its updates by itself.

## What it does

**Settings → Photos.sh.** The API key, a line with the connected account and
the credits left, "Studio shots" and "Model" under "Remove background", and the
base URL under "Advanced" (default `https://api.photos.sh`). The key can instead come
from the constant or environment variable `PHOTOS_API_KEY`; that wins over the
saved key and makes the field read-only. The saved key is never printed back:
the field shows its last four characters as a placeholder.

"Studio shots" (off by default) is for product photos taken on a plain light
backdrop (a light tent): background removal then also keeps what clearly
stands out from the backdrop and touches the product, such as the box, case or
lid shown with a bottle. Shadows and the floor below the product are left out.
A photo without a plain light backdrop is cut out as without the setting. It
applies to every Remove background: the row action, the box on the edit
screen, the bulk actions (read when each task runs), the WooCommerce bulk
action and WP-CLI, where `--studio` and `--no-studio` override it for one run.
The API is sent `studio=true` only when it is on.

"Model" is the model that finds the subject, a select with a description of
each. Each costs the same; try another when one drops part of the product:

| Model | Recommended for |
| --- | --- |
| Atlas (default) | Everyday product photos: a product alone or with its box, on any backdrop. |
| Lumen | Pale packaging on a light backdrop, such as a cream or wooden box beside a bottle, and close-ups where the product fills the photo. On those, Atlas can keep the bottle and drop the box. It may keep a dark backdrop at the photo's edge, and can cut away part of an open frame or wire stand. |
| Muse | People: portraits, and models wearing the product. |

It applies to every Remove background, like "Studio shots"; in WP-CLI,
`--model=atlas|lumen|muse` overrides it for one run. The API is sent
`model=lumen` or `model=muse` only when one of those is chosen: with Atlas the
parameter is left out and the API uses its default, Atlas. The setting is saved
in `photos_sh_settings` as `model`.

**Media library, one image.** Remove background, Upscale 2× / 4×, Remove text,
Fix light and colour, Remove noise, Colourise, Restore faces, Hide faces,
Fix for a marketplace (Amazon, eBay, Etsy, Shopify, Google Merchant Center,
Allegro, Zalando) and Write alt text, as row actions in the list view and in a
"Photos.sh" box on the attachment edit screen. (In the row actions, "Fix for a
marketplace…" opens the edit screen, where the marketplaces are listed.) The
action runs while the editor waits and comes back with a notice linking to the
new image.

What the tools added last do, and where they can be wrong. The box on the edit
screen shows the caution, a row action shows it when the pointer rests on it,
and the notice after the action repeats it (not for Remove noise, whose
caution is about the wait):

| Tool | What it does | Caution shown to the editor |
| --- | --- | --- |
| Fix light and colour | Automatic levels and white balance. | |
| Remove noise | Takes out sensor noise and JPEG artefacts. | "Remove noise is slow, and takes photos up to 12 megapixels." |
| Colourise | Colours a black-and-white photo. | "Colourise guesses the colours, so some of them can be wrong." |
| Restore faces | Redraws each face sharp; the rest of the photo is not touched. | "Restore faces invents the detail it adds: do not use it for identity photos or evidence." |
| Hide faces | Blurs every face it finds. | "Hide faces blurs the faces it finds; a face it does not find still shows." |

Restore faces and Hide faces say how many faces they worked on: in the notice,
on the new image's edit screen, and in WP-CLI. When no face was found (or, for
Restore faces, every face is large enough already), no new image is saved and
the editor is told so: the image the API returns is then the source again, and
a copy titled "faces hidden" with every face showing would mislead.

**Alt text.** "Write alt text" makes no image: it sets the attachment's
alternative text (`_wp_attachment_image_alt`) to a one-sentence description of
the image. The text is written by AI, in English, and can be wrong; the box,
the notice and the bulk progress notice all say "The text is written by AI, in
English. Read it before publishing."

Alternative text that is already there is kept unless the editor asks for it
to be replaced, and an image that is skipped is not sent to the API:

- Row action: "Write alt text" on an image without alternative text. An image
  that has it gets "Replace alt text…" instead, which opens the edit screen.
- Edit screen: the box has "Write alt text", or, when the image has
  alternative text, "Replace alt text" with the line "This image already has
  alternative text. Replacing it cannot be undone."
- Bulk actions: "Write alt text (keep existing text)" skips the images that
  have alternative text and lists them as skipped; "Write alt text (replace
  existing text)" writes all of them.
- WP-CLI: `wp photos-sh alt-text` skips them; `--replace` writes all of them.

Alternative text made of spaces only counts as none.

**Media library, bulk actions** (list view). The same operations, under a
"Photos.sh" group. Each selected image becomes one background task. A notice on
the media screens shows how many are done and lists the failures and the
skipped images with the reason; it refreshes itself while images are waiting.

**WooCommerce** (only when active). The bulk action "Remove background from
product images" on the products list: each product's main image is replaced by
the cutout, and the old image stays in the media library. A product whose main
image already is a cutout made by this plugin is skipped.

**WP-CLI.**

```bash
wp photos-sh remove-background 12 13
wp photos-sh remove-background 12 --studio # keep the box; --no-studio: don't
wp photos-sh remove-background 12 --model=lumen # atlas, lumen or muse
wp photos-sh upscale 12 --scale=4          # 2 (default) or 4
wp photos-sh remove-text 12
wp photos-sh enhance 12                    # Fix light and colour
wp photos-sh denoise 12                    # Remove noise
wp photos-sh colorize 12                   # Colourise
wp photos-sh restore-faces 12
wp photos-sh blur-faces 12                 # Hide faces
wp photos-sh fix 12 --marketplace=amazon
wp photos-sh alt-text 12 13                # skips images that have alt text
wp photos-sh alt-text 12 --replace         # writes over existing alt text
wp photos-sh account
```

Image commands take one or more attachment IDs, process them one after the
other, and print the new attachment's ID for each (`--porcelain`: only the
IDs). `restore-faces` and `blur-faces` also print how many faces they worked
on. `alt-text` prints the text it wrote (`--porcelain`: the IDs of the
attachments it wrote to). An image that is skipped (it has alt text, or no
face was found) gets a warning and is not a failure. The exit status is 1
when any image failed.

## The new attachment

The file is named after the source: `shoe.jpg` gives `shoe-no-bg.png`,
`shoe-upscaled-2x.png`, `shoe-no-text.png`, `shoe-enhanced.jpg`,
`shoe-no-noise.jpg`, `shoe-colourised.jpg`, `shoe-faces-restored.jpg`,
`shoe-faces-hidden.jpg` or `shoe-amazon.jpg`. The title is the source's with
what was done, such as "Shoe (background removed)". The alternative text,
caption and parent post are copied. Post meta:

| Key | Value |
| --- | --- |
| `_photos_sh_source` | The source attachment's ID. |
| `_photos_sh_operation` | `remove-background`, `upscale`, `remove-text`, `enhance`, `denoise`, `colorize`, `restore-faces`, `blur-faces` or `fix`. |
| `_photos_sh_params` | `['scale' => 2]`, `['marketplace' => 'amazon']`, `['studio' => true]` (remove background on a studio shot), `['model' => 'lumen']` (remove background with Lumen or Muse; both keys when both apply), or `[]`. |
| `_photos_sh_result` | For a fix: `['passed' => bool, 'findings' => [...]]`. For remove text: `['removed' => int, 'kept' => int]`. For hide faces: `['faces' => int]`. For restore faces: `['faces' => int, 'skipped' => int]` (`skipped`: faces left alone because they are large enough already). |

"Write alt text" adds no meta of its own: it only sets
`_wp_attachment_image_alt`.

The image sent to the API is the attachment's main file (for a very large
upload, the copy WordPress scaled down).

## Background tasks

One image per task, on the hook `photos_sh_task`: with Action Scheduler when
it is loaded (WooCommerce ships it), in the group `photos-sh`; with WP-Cron
otherwise. A task that fails because Photos.sh is busy (`503`) or cannot be
reached is scheduled again, at most three times: after the `Retry-After` the
server gives, or after 1, 2 and 4 minutes. A timeout is not retried (the
server may still finish and charge for the image), nor is any other error (no
credits, a refused key, an unreadable image): the image is marked as failed
with the reason. An image that is left as it is (it has alt text that is to be
kept, or no face was found in it) is marked as skipped, with the reason.
Deactivating the plugin removes the tasks that have not run.

## Errors

Editors see plain messages (out of credits, the key was refused, busy: try
again). The details go to the PHP error log, without the API key, and to the
action `photos_sh_error` (`$error`, `$context`). The action
`photos_sh_processed` (`$new_id`, `$source_id`, `$operation`) fires after each
new attachment, and `photos_sh_alt_text_written` (`$attachment_id`, `$text`,
`$previous`) after alternative text was written.

## Install from source

```bash
git clone https://github.com/photos-sh/photos-wordpress.git
cd photos-wordpress
composer install     # brings the PHP library, photos-sh/photos, from Packagist
bin/build            # makes dist/photos-sh.zip
```

Upload `dist/photos-sh.zip` under Plugins → Add New → Upload Plugin. The zip
holds the plugin and `vendor/` without development packages. (For development
you can also link the checkout into `wp-content/plugins/photos-sh`.)

To work on the library and the plugin together, point Composer at a checkout
of the library with a `path` repository in `composer.json`; `bin/build` copies
such a checkout into the zip instead of linking it.

## Development

```bash
composer test        # PHPUnit: transport, operations, alt text, naming, retry rules, tasks, settings
composer stan        # PHPStan level 8 with szepeviktor/phpstan-wordpress
composer cs          # PHPCS with the WordPress Coding Standards; composer cs:fix applies fixes
composer check       # all three
```

The unit tests stub the few WordPress functions they touch
(`tests/Support/wp-stubs.php`); they need no WordPress install.

The code follows the WordPress Coding Standards, except that classes are
namespaced and autoloaded by PSR-4 from `src/` (so file names follow the class
names) and arrays use the short syntax. See `phpcs.xml.dist`.

**CI** (`.github/workflows/ci.yml`) runs the unit tests on PHP 8.3 to 8.5 and
the linters and the build on ubuntu-latest.

**A release** is a tag `v<version>` on `main`. The version is written in
`photos-sh.php` (the header and `PHOTOS_SH_VERSION`), `src/Plugin.php` and
`readme.txt` (the stable tag); `.github/workflows/release.yml` refuses a tag
that differs from any of them, then builds `photos-sh.zip` and attaches it to
a GitHub release, which is where the plugin is distributed for now.

To try the plugin against another API server, set the base URL under
Settings → Photos.sh → Advanced.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE). The bundled PHP library is MIT.
