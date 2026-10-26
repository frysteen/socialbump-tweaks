# Image Management module

Site Kit's Images and Image Cleaner pages, as a module of their own. Read the
framework notes first, at the plugin root in docs/context.md. This file covers
only what this module does.

standalone-notes.md in this folder holds the image sections of Site Kit's notes
as they stood at 1.2.6: how the size engine, the cleaner, the orphan scan, the
deep scan, the media grid and media search work, and why. Read them as this
module's, with the names below.

## What it does

Two pages in the Media menu, with no menu or Features page of their own:

- SB Image Settings (upload.php?page=sb-image-settings): the image-<width>
  sizes, WordPress's own sizes and which of its larger ones are made, tidy
  titles and alt text on upload, Gutenberg image zoom, Media Browser
  Enhancements and Better media search.
- SB Image Cleaner (upload.php?page=sb-image-cleaner): the scan, Build, Remove
  old sizes, the deep scan for leftover thumbnails, missing images, orphaned
  files, and the sizes to work on. Each image in the media library gets a sizes
  panel with Regenerate sizes, in the attachment details and on its edit
  screen.

The module's switch on the Modules page turns both on together. There are no
switches per page, by design: neither is any use without the other. In the
SocialBUMP admin bar the module is one row, Image Management, opening SB Image
Settings, with both pages on its flyout.

## How it is built

| Path | What it is |
| --- | --- |
| module.php | the declaration: groups settings and cleaner, menu parent upload.php, waits_for, migrate, prompt, boot |
| includes/class-sb-images-module.php | SB_Images_Module: settings, page addresses, assets, old addresses and the migrate |
| features/images/ | SB Image Settings: SB_Images and the rebuild engine, zoom, media grid and media search |
| features/image-cleaner/ | SB Image Cleaner: SB_Images_Cleaner, SB_Images_Tools, SB_Images_Orphans |
| assets/js/images.js | both pages and the sizes panel: what was Site Kit's admin.js image code |
| assets/css/images.css | their styles; media-grid and gallery-zoom have their own files |
| docs/ | these notes and the standalone notes |

Each group has one feature marked always, with an admin_page, so its group page
is that feature's page. The menu is 'parent' => 'upload.php' with slug
sb-image, so the pages are sb-image-settings and sb-image-cleaner. Under a
parent menu the framework keeps the groups in the order module.php declares
them, which is why Settings comes first everywhere and the bar row opens it.
On the hub the Documentation page is sb-image-documentation, reached through
admin.php and not listed in the Media menu.

SB_Images_Module::assets() adds images.css and images.js on the two pages and,
for the sizes panel, on upload.php, post.php and post-new.php, with the
framework's confirm.js, which images.js uses for every question and summary.

Site Kit's old addresses, admin.php?page=sb-site-kit-images and
sb-site-kit-image-cleaner, redirect to the new pages. WordPress refuses an
unknown page before admin_init, so this hangs off admin_page_access_denied.

## Settings, and the copy from Site Kit

| Option | What it holds |
| --- | --- |
| sb_tweaks_images_settings | ['images'] the settings page (sizes_on, sizes, clean_titles, auto_alt, gallery_zoom, media_grid, media_search, wp_off), ['image-cleaner'] if anything is ever saved for it |
| sb_tweaks_images_features | empty; it existing is what switches the module on ('auto') |
| sb_tweaks_images_owned_image_sizes | every size name this module has registered, so cleanup knows what is its own |
| sb_tweaks_images_adopted_image_sizes | leftover sizes from other sources chosen for clearing |
| sb_tweaks_images_kept_orphans | orphaned files kept on purpose |
| sb_tweaks_images_migrated | set once the copy below has run |
| user meta sb_tweaks_images_cleaner_sizes | [ 'off' => [...] ], the sizes each person left unticked on the cleaner, so a size added later starts ticked. Site Kit saved the ticked ones; chosen() turns that list round the first time it reads it |
| user meta sb_tweaks_images_media_grid | each person's grid size and fit |

SB_Images_Module::migrate() runs on every request until it has run, before
the framework asks whether the module is on. It copies Site Kit's
sb_tweaks_site_kit_settings ['images'] and ['image-cleaner'],
sb_tweaks_site_kit_owned_image_sizes, _adopted_image_sizes and _kept_orphans,
and both user meta keys, never over anything already here, and leaves Site
Kit's copies exactly as they were. It carries over Site Kit's two run once
fixes on the way: a site that never saved its Images page keeps the old
standard widths (240 to 1920), and the old rebuild_on setting decides the
cleaner where the split never ran. It switches the module on where Site Kit
had either page on. A site that never had Site Kit is marked done and starts
with the module off.

On the hub it waits while standalone Site Kit is active, since its pages could
still change what is copied. Anywhere else the Site Kit module retires the
plugin on the same request.

The module waits_for socialbump-site-kit/socialbump-site-kit.php: it sits out
any request the standalone plugin loaded in, because both would register the
same sizes and handle the same uploads.

## Names that moved, and names that cannot

Moved: SBSK_Images* classes to SB_Images*, class-sbsk-images*.php to
class-sb-images*.php, the sbsk/ filters to sb_images/ (editable_sizes,
mime_types, media_search/applies, media_search/file_variants,
orphans/extensions, orphans/keep, orphans/mention_sources,
orphans/bookkeeping_keys), the JS filters to sb-images/zoom-attributes and
sb-images/zoom-controls, every AJAX action from sbsk_images_ to sb_images_ (16
of them) and the nonce to sb_images, the admin-post actions (sb_images_save,
sb_images_save_cleaner_sizes, sb_images_adopt_sizes, sb_images_unadopt_sizes,
sb_images_media_grid_save), transients (sb_images_job, sb_images_run_,
sb_images_stopped_, sb_images_taken_, sb_images_orphans_run_,
sb_images_mentioned_names), form fields and query arguments, script globals
(sbImagesGalleryZoom, sbImagesFolderFilter, sbImagesGridPicker,
SBImagesMediaGrid), CSS classes, ids and handles from sbsk- to sb-images-, the
front end zoom markup (data-sb-images-zoom, sb-images-zoom), and the text
domain to sb-tweaks. The page wrapper class sbsk-images is sb-images-settings.

Kept: the image-<width> size names, which every image's metadata holds, and
the gallery and image blocks' zoom attributes, sbskZoom and sbskZoomSize,
which posts have saved in their block markup.

A page cached before the move still carries data-sbsk-zoom and Site Kit's
gallery-zoom files, which no longer load, so clear the page cache on a site
that uses Gutenberg image zoom once it moves over.

The orphan scan's search for mentions skips this module's own options and Site
Kit's (sb_tweaks_images_ and sb_tweaks_site_kit_). Site Kit's own exclusion
still read sbsk_, the prefix from before its options were renamed, so it no
longer covered them.

## Popups

Every question and every summary is the framework's popup (SBTweaksConfirm):
ask() and confirmed() before a delete or a rebuild, tell() for a refusal, and
open() for the summary at the end of a run, with Scan again. images.js wraps
the four as local functions, so its code reads as it did in Site Kit.

## Leftover sizes from other sources

The list Image Cleaner shows after a scan (SB_Images_Tools::foreign_html()) has
Select all and Select none above it (Sam, October 2026: Doogood had 23 to
tick). They are data-sb-images-foreign="all" or "none" buttons, handled in
images.js on the report panel, and tick only that form's sb_images_adopt[]
boxes, firing one real change event like the size groups do.

Ticking a size here only adds it to Old thumbnails to clear. Remove old sizes
still keeps any file another size of the image, ticked or not, still names
(SB_Images_Rebuild::file_has_other_owner()), so a leftover size that shares
its file with a kept one only loses its entry.

## Switching a site to the standard sizes

How bricks.socialbump.com.au, doogood.com.au and epoxyflooringco.com.au were
moved onto 450, 665, 890, 1330 and 1920 (October 2026), written as the order to
do it in. Villa Real Estate was sorted the same way earlier, under standalone
Site Kit. It is mostly finding where the old sizes are used and changing those
first, because Remove old sizes deletes the files and anything still asking
for them shows a broken image.

Sites that send image links out in email need those sizes kept: lock them
with the padlock on SB Image Settings before Remove old sizes (see Locked
sizes). Driving Events was done that way in October 2026, with image-480 and
image-720 locked because its sent newsletters link to them.

### 1. Look before touching anything

- Which sizes the site registers now, and from where: SB Image Settings (or
  Site Kit's Images page), a snippet (Epoxy had WPCodeBox snippet 3, "Image
  Sizes"), the theme (Total's dynamic resizing makes wpex_ sizes on the fly),
  or another plugin. Note anything cropped: the old -square and -4x3 sizes
  were cropped, the new ones are not.
- Which builders hold content: Bricks, Elementor, WPBakery (and Templatera
  templates), Divi, Beaver Builder, Oxygen, Breakdance, Gutenberg, plus theme
  options, widgets and snippets. Old pages can sit in a builder that is no
  longer switched on.
- Back up before any edit: the post_content and builder data
  (_elementor_data, _bricks_page_content_2, _fl_builder_data, ct_builder_json,
  _breakdance_data) of everything you change, to /tmp as sbx-<stamp>-*.json.

### 2. Add the standard sizes and build them

On SB Image Settings, Add default sizes, Save, then Build Thumbnails on SB
Image Cleaner. Old sizes stay registered for now, so nothing breaks yet.

### 3. Find every use of an old size

| Where | What to look for |
| --- | --- |
| Elementor (_elementor_data) | image_size, thumbnail_size, size_size, imgsize_size and any other *_size key holding an old name; "custom" with a *_custom_dimension, which only becomes elementor_custom_WxH once its file is made |
| Bricks (_bricks_page_content_2, _header_, _footer_, templates) | the size setting on images, galleries and the carousel |
| Gutenberg (post_content) | sizeSlug, the size-<name> class, a file URL ending -WxH, and block attributes such as sbskZoomSize |
| WPBakery (post_content, templatera) | img_size in shortcodes, custom width x height values, Total wpex_ sizes |
| Divi (post_content, et_pb_layout, Theme Builder layouts) | src URLs on et_pb_image and other modules, gallery and background images; the Theme Builder layouts are et_template, et_header_layout, et_body_layout and et_footer_layout; Divi 5 stores blocks in post_content |
| Beaver Builder, Oxygen, Breakdance | _fl_builder_data and _fl_builder_draft, ct_builder_json or ct_builder_shortcodes, _breakdance_data |
| Elsewhere | theme options, widgets, snippets, and the CSS in them (background images) |

### 4. Pick the new size

The nearest standard size, going up one when the nearest is more than 10%
narrower than the old one (thumbnail 150 cropped, medium 300 and large 1024
count as standard too):

| Old | New |
| --- | --- |
| image-240 | medium |
| image-480 | image-450 |
| image-640, image-720 | image-665 |
| medium_large (768) | image-890 |
| image-960 | image-890 |
| image-1200 | image-1330 |
| 1536x1536, 2048x2048, image-2000 | image-1920 |
| image-<w>-square, image-<w>-4x3 | the width rule above, plus the shape (step 5) |

### 5. Keep the shape where the old size was cropped

The new sizes keep each image's own shape, so anything that relied on a
cropped size needs the shape set where it is shown:

- Elementor: Image Aspect Ratio in the widget's SB Image section (Elementor
  Tweaks module): 1:1 for -square, 4:3 for -4x3, cover, centre centre.
  Before that module existed, Epoxy got Custom CSS on each widget, selector
  img { aspect-ratio: 1; object-fit: cover; }, which the module's settings
  replace.
- Gutenberg image block: aspectRatio and scale cover in the block attributes
  (the img gets style aspect-ratio and object-fit).
- Bricks: aspect-ratio and object-fit on the element's image.
- Any builder without an aspect ratio setting: CSS on the image,
  aspect-ratio plus object-fit: cover.
- Widgets with a ratio of their own (Elementor Gallery and Posts) and
  background images set to cover need nothing.

### 6. Make the changes

- Prefer the site's purpose built tool (novamira/elementor-edit-element and
  friends, the Bricks abilities). Two Elementor catches from Epoxy:
  elementor-edit-element drops widgets whose plugin is inactive (JetEngine's
  listing grid) and says "not persisted", and it refuses post types without
  Elementor support (jet-engine listings). For those, restore from the backup
  and change only the size string in the stored JSON with PHP.
- Builders with no abilities (WPBakery, Divi, Beaver Builder and the like):
  back up, change only the size name or file URL in the stored data with PHP,
  then clear that builder's cache.
- Clear Elementor's CSS (files_manager->clear_cache()) or Bricks' CSS files
  afterwards, and any page cache.

### 7. Check the front end

Crawl every public page and template output (sitemap plus anything else
found), map each uploads file on the page to its size names through
_wp_attachment_metadata, and confirm no page shows a size that is about to go
and no file is missing. Decode &#039; and friends in style attributes first,
or background image files show as unknown.

### 8. Clean up

1. Switch off whatever registered the old sizes (the snippet, the theme
   setting).
2. Take a database backup.
3. SB Image Cleaner: Scan.
4. Leftover sizes from other sources: Select all, add them to Old thumbnails
   to clear (elementor_custom_*, wpex_* and the like), after checking step 3
   found none in use.
5. Remove old sizes. Any file another kept size still names is kept, so only
   its entry goes (SB_Images_Rebuild::file_has_other_owner()).
6. Orphaned files, then clear the host cache.

Form upload images (WS Form keeps them under uploads/ws-form) get no new sizes
and are on no page; leave them.

## Locked sizes (October 2026)

For files that emails or other sites link to straight, such as Driving Events,
whose newsletters use image-480. Each size on SB Image Settings has a padlock
beside it (our widths, beside the remove cross; WordPress, WooCommerce and
theme sizes under Additional thumbnail sizes). Saved as locked_sizes, a list of
size names, in sb_tweaks_images_settings (images). SB_Images::locked_sizes(),
is_locked(), locked_widths() and locked_dimensions() read it.

A locked size:

- Cannot be changed, removed or switched off. images.js makes the row read
  only, disables its cross or on box and asks before unlocking. The save
  enforces it anyway: SB_Images::posted_sizes() keeps a locked width in the
  list, ignores a posted width for a locked WordPress or WooCommerce size and
  never puts a locked size in wp_off.
- Survives Reset to default sizes: the reset list is the defaults plus the
  locked widths, wp_off leaves it out, and Also put the WordPress sizes back
  skips its options.
- Is still registered when Image sizes is switched off (register_sizes()).
- Is never stale (SB_Images_Rebuild::stale() leaves it out), so Remove old
  sizes and a single image rebuild never delete its files, and it cannot be
  chosen as a leftover size to clear (adopt()).
- Keeps its leftover files. SB_Images_Orphans::is_protected() matches file
  names by dimensions (photo-480x320.jpg for image-480; an uncropped size
  matches on either side, a cropped one on both), so Find leftover thumbnails,
  the deep remove and Delete orphan images all skip them.

Everything else can still be reset and cleaned. SB Image Cleaner lists the
locked sizes in a notice at the top.

Replaced a whole-site lock switch that was never released.

## Size migration prompt (hub only)

SB Image Cleaner on the hub shows a Size migration prompt box under Sizes: the
text to start a new chat with when moving a site onto the standard sizes, with
a Copy prompt button. It finds every use of the old sizes and reports, waits
for Sam's OK, updates the pages and templates, and leaves building, locking
and removing to Sam. The text is docs/size-migration-prompt.md, read as the
page draws, so edit that file to change it.
It covers every builder (Bricks, Elementor, WPBakery, Divi, Beaver Builder,
Oxygen, Breakdance, Gutenberg) and names no site: the chat it is pasted into
already knows which site it is working on.
SB_Images_Cleaner::render_migration_prompt() draws it only where
sb_tweaks_is_hub() is true; render_page() puts it in the first column with
Sizes (sb-images-cleaner__side). The copy uses the clipboard API, falling back
to selecting the text.

## SB Image Cleaner buttons (October 2026)

Rebuild Thumbnails keeps its buttons at the top, under the instructions; the
report, the progress box and the lists open below them. Under a long list
(orphaned images, leftover thumbnails, the images to build, the log a run
leaves) a second row shows,
#sb-images-actions-foot. Each of its buttons carries
data-sb-images-mirror="<id of the top button>": images.js copies that
button's class, wording, hidden and disabled through a MutationObserver on
#sb-images-rebuild-body and passes clicks to it, so the confirmations and runs
are the top button's own. The Force rebuild tickbox is only in the top row.
When a run opens the progress box off screen, the page scrolls to it.

## WebP copies and the site icon (October 2026)

Found on jetskihirenoosa.com.au and noosariverboathire.com.au, both running
ShortPixel:

- ShortPixel writes its WebP beside a file with the extension swapped
  (photo-480x320.webp), where WPvivid, EWWW and Imagify add it
  (photo-480x320.jpg.webp). Only the added kind was handled, so Remove old
  sizes left ShortPixel's copies behind (379 and 356 files), and neither scan
  saw them, because is_image_file() took the .webp off and found no extension
  left. SB_Images_Rebuild::copies() now gives both kinds for a size file (the
  swapped one only when no attachment names it as its own file), and
  delete_file(), size_files() and stale_usage() use it. SB_Images_Orphans::add()
  counts both kinds as belonging, and is_image_file() judges a plain .webp as
  a WebP, so Find leftover thumbnails now lists ShortPixel copies of old sizes.
- WordPress registers site_icon-32, -180, -192 and -270 only while a site icon
  is cropped, so they looked like leftovers from another source and got
  cleared, and the favicon fell back to the 150 and 300 thumbnails.
  SB_Images_Rebuild::wanted_for( $id ) is all_wanted() plus those sizes for the
  attachment in the site_icon option. present(), missing_all(), build(),
  rebuild(), stale() and foreign() use it, as does the leftover scan's
  expected_dimensions(), so the site icon's sizes are never cleared or listed,
  and Build Thumbnails makes them again where they are missing.

Follow-up: the site icon's sizes had been offered under Leftover sizes from
other sources and ticked for clearing. is_site_icon_size() now keeps any
site_icon-* name out of foreign() and stale() on every attachment, so they are
never listed or cleared, even if an older version let them be chosen. Build
also ignores the ticked list for them (missing_all() and build()), because
they are never registered and so never ticked, which had left Sizes to build
at 0 on jetskihirenoosa.com.au with the favicon sizes still missing.
