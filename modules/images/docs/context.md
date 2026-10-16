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
