=== Photos.sh ===
Contributors: photossh
Tags: background removal, upscale, product photos, woocommerce, images
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Remove backgrounds, upscale, remove text and fix product photos for marketplaces, from the media library.

== Description ==

Photos.sh brings the image tools of the [Photos.sh](https://photos.sh) API to the media library:

* **Remove background**: a cutout of the product on a transparent background. With "Studio shots" on, the box, case or lid shown with the product is kept too. Choose the model that finds the subject: Atlas for everyday product photos, Lumen for pale packaging on a light backdrop, Muse for people.
* **Upscale 2× / 4×**: a larger, sharper copy made with AI.
* **Remove text**: watermarks, captions and other text off the photo.
* **Fix light and colour**: automatic levels and white balance.
* **Remove noise**: sensor noise and JPEG artefacts off the photo. It is slow, and takes photos up to 12 megapixels.
* **Colourise**: colour for a black-and-white photo. The colours are guesses, so some of them can be wrong.
* **Restore faces**: the faces of an old, blurred or noisy photo redrawn sharp. The detail is invented: do not use it for identity photos or evidence.
* **Hide faces**: every face that is found is blurred. A face that is not found still shows, so check the result.
* **Fix for a marketplace**: a copy that follows the image rules of Amazon, eBay, Etsy, Shopify, Google Merchant Center, Allegro or Zalando.
* **Write alt text**: a one-sentence description of the image as its alternative text. The text is written by AI, in English: read it before publishing. Alternative text that is already there is kept unless you choose to replace it.

Every new image is saved as a new image in the media library. The original is never changed. "Write alt text" makes no image: it only sets the image's alternative text.

Where the tools are:

* On one image: in the row actions of the media library's list view, and in the "Photos.sh" box on the image's edit screen.
* On many images: in the bulk actions of the media library's list view. The images are processed in the background, one at a time, and a notice shows the progress and what failed.
* With WooCommerce: the bulk action "Remove background from product images" on the products list replaces each product's main image with the cutout. The old image stays in the media library.
* On the command line: `wp photos-sh remove-background|upscale|remove-text|enhance|denoise|colorize|restore-faces|blur-faces|fix|alt-text <attachment-id>…` and `wp photos-sh account`.

You need a Photos.sh account and an API key. Processing an image uses credits of that account.

== External services ==

This plugin sends images to the Photos.sh API (https://api.photos.sh by default) to process them. It is the only service the plugin talks to.

* When you run one of the tools on an image, the image file and the chosen options (the tool, the upscale factor, the marketplace, whether the photo is a studio shot, the background removal model) are sent, with your API key. The processed image comes back and is saved in your media library. For "Write alt text", a description of the image comes back and is saved as its alternative text.
* When you open Settings → Photos.sh (or run `wp photos-sh account`), your API key is sent to read the account's name and the credits left.

Nothing is sent until you add an API key and run a tool. The service is provided by Photos.sh: [terms of service](https://photos.sh/legal/terms), [privacy policy](https://photos.sh/legal/privacy).

== Installation ==

1. Upload the plugin zip under Plugins → Add New → Upload Plugin, and activate it.
2. Create an API key on your organisation's API keys page in the Photos.sh console.
3. Enter the key under Settings → Photos.sh. The page shows the connected account and the credits left.

Instead of saving the key in the database, you can define it in `wp-config.php`:

`define( 'PHOTOS_API_KEY', 'your-api-key' );`

or set the `PHOTOS_API_KEY` environment variable. The field on the settings page is then read-only.

== Frequently Asked Questions ==

= Is the original image changed? =

No. Every result is a new image in the media library. It keeps a link to the image it was made from, shown on its edit screen. "Write alt text" changes no image file: it sets the image's alternative text.

= Does "Write alt text" replace the alternative text I wrote? =

Not unless you ask. On an image that has alternative text, the row action and the box on the edit screen offer "Replace alt text" instead. In the bulk actions, "Write alt text (keep existing text)" skips those images and "Write alt text (replace existing text)" writes all of them. On the command line, `wp photos-sh alt-text` skips them unless you add `--replace`.

The text is written by AI, in English, and can be wrong. Read it before publishing.

= Can Remove background keep the box shown with my product? =

Yes, when your product photos are taken on a plain light backdrop (a light tent). Turn on "Studio shots" under Settings → Photos.sh: background removal then also keeps what clearly stands out from the backdrop and touches the product, such as the gift box, case or lid shown with a bottle. Shadows and the floor are left out. A photo without a plain light backdrop is cut out as without the setting. On the command line, `--studio` or `--no-studio` overrides the setting for one run.

= Which background removal model should I choose? =

Under Settings → Photos.sh → Remove background → "Model". Each costs the same; try another when one drops part of the product.

* **Atlas** (default): everyday product photos: a product alone or with its box, on any backdrop.
* **Lumen**: pale packaging on a light backdrop, such as a cream or wooden box beside a bottle, and close-ups where the product fills the photo. On those, Atlas can keep the bottle and drop the box. It may keep a dark backdrop at the photo's edge, and can cut away part of an open frame or wire stand.
* **Muse**: people: portraits, and models wearing the product.

On the command line, `--model=atlas`, `--model=lumen` or `--model=muse` overrides the setting for one run.

= Why did "Hide faces" or "Restore faces" save no new image? =

No face was found in the image (or, for "Restore faces", every face is large enough already), so there was nothing to change. The notice says so. When faces were found, the notice and the new image's edit screen say how many. A face that was not found is not hidden: check the result before you publish it.

= How are bulk actions processed? =

One image per background task. The plugin uses Action Scheduler when another plugin (such as WooCommerce) provides it, and WP-Cron otherwise. When Photos.sh is busy or cannot be reached, a task is tried again up to three times, a few minutes apart.

= Why did an image fail? =

The progress notice says why: the account is out of credits, the API key was refused, Photos.sh was busy, or the image could not be read. Details are written to the PHP error log, never with the API key. Images listed as skipped did not fail: they already have alternative text, or no face was found in them.

= Where are the bulk actions in the grid view? =

WordPress has bulk actions only in the list view of the media library. Switch to the list view with the icon above the grid.

== Changelog ==

= 1.2.0 =
* Settings → Photos.sh → Remove background → "Model": the model that finds the subject. Atlas (the default) for everyday product photos, Lumen for pale packaging on a light backdrop and close-ups, Muse for people. Each costs the same. WP-CLI: `--model` overrides it for one run.

= 1.1.0 =
* Settings → Photos.sh → "Studio shots": for product photos on a plain light backdrop, Remove background also keeps the box, case or lid shown with the product. WP-CLI: `--studio` and `--no-studio` override it for one run.

= 1.0.0 =
* First version: settings page, media library actions and bulk actions, WooCommerce product bulk action, WP-CLI commands.
* New tools in the media library, its bulk actions and WP-CLI: Fix light and colour, Remove noise, Colourise, Restore faces and Hide faces.
* Write alt text: sets an image's alternative text to a description written by AI. Existing alternative text is kept unless you choose to replace it.
