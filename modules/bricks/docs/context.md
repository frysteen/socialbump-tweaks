# Bricks Tweaks module

What was the standalone SocialBUMP Bricks Tweaks plugin (last standalone release
1.1.6), now a module of SocialBUMP Tweaks. Read the framework notes first, at
the plugin root in docs/context.md: menus, switches, saving, cards, the Updates
page, the reporter and pushed updates all live there. This file covers only what
the Bricks module does.

standalone-notes.md in this folder holds the standalone plugin's full notes, for
the detail on each feature. changes.md holds its 23 release notes, 0.1.0 to
1.1.6, shown read only on the Documentation page.

## What it does

Eleven features in three groups, each with its own switch:

- Bricks Elements: Image Carousel (sb-bricks-image-carousel), an SEO friendly
  Splide carousel with real img tags, ACF gallery support, breakpoints,
  lightbox and continuous scroll.
- Conditional Logic, under the SocialBUMP group in the Bricks Conditions panel:
  ACF Relationship, ACF Repeater, Bricks Content, Post Type, WooCommerce
  Archive Display.
- Extras: ACF Loop Sorting (repeater and relationship loops, each with its own
  setting), ACF Gallery Loop, Default To WP Editor, Gutenberg Block Styles,
  Hide Unpublished ACF Posts.

The module requires Bricks (the framework checks the active theme, because
themes load after plugins and BRICKS_VERSION is not defined while modules
boot). Features needing ACF or WooCommerce show as unavailable without them.

## How it is built

| Path | What it is |
| --- | --- |
| module.php | the declaration: groups, menu, replaces, prompt, boot |
| includes/class-sb-bricks-conditions.php | registers conditions with Bricks |
| includes/class-sb-bricks-acf-source.php | the ACF bundled with Advanced Themer notice |
| features/<slug>/feature.php | one per feature, the framework's feature shape |
| docs/ | these notes, the standalone notes and the changes archive |

module.php defines SB_BRICKS_PATH and SB_BRICKS_URL. Its boot registers the
enabled element features with \Bricks\Elements on init at 11, as the standalone
plugin did, and shows the ACF source notice on this module's pages. A feature
reads its settings through sb_tweaks_module( 'bricks' )->setting( $id, $key ),
which works during boot because the framework registers a module's loader
before booting its features.

The menu slug stays sb-bricks-tweaks and sits just below Bricks, because Admin
Site Enhancements' menu setup (admin_site_enhancements_extra) and bookmarks
hold that slug. Settings stay in sb_tweaks_bricks_features, _settings and
_groups, so no site needs converting and the Updates page export carries them.

## Names that moved, and names that cannot

Moved in the merge, code only: SBBT_* classes to SB_Bricks_*, class-sbbt-*.php
to class-sb-bricks-*.php, sbbt/ filters to sb_bricks/ (element_conditions,
gutenberg_styles/default_gallery_gap, relationship_ordering/elements,
repeater_ordering/elements, repeater_ordering/day_first), asset handles
sbbt-carousel, sbbt-splide-auto-scroll, sbbt-block-common, sbbt-block-layout,
sbbt-bricks-variables and sbbt-gallery-gap to sb-bricks-*, and the text domain
to sb-tweaks. Any snippet hooking an old sbbt/ filter needs the new name.

Cannot change, because pages hold them: condition keys sb_bricks_*, the element
name sb-bricks-image-carousel, the loop settings sbBricks*, and the gallery
query types sb_bricks_gallery_<field>. The conditions group is still labelled
SocialBUMP.

## Old page names still read (to remove later)

The standalone plugin kept read-only fallbacks for pages saved before its
rename, and the module keeps them for now so nothing can break during the move:
the old condition keys (socialbump_*) as aliases, the old carousel element name
sb-image-carousel registered hidden, sbbtGallery* and sbbt_gallery_ in the
gallery loop, sbbtRelationshipOrder, and socialbumpRepeaterOrder. September
2026: none were found in the saved Bricks data on bricks.socialbump.com.au or
thecosmeticstudionoosa.com.au. Once every Bricks site has been checked the same
way, remove the fallbacks and the legacy element.

## Moving a site over

The framework retires the standalone plugin (see Turning the old plugins off in
the framework notes): once this module is on disk, standalone Bricks Tweaks is
switched off the first time SocialBUMP Tweaks loads, cannot be activated again,
and the module waits out the request in which the standalone plugin had already
loaded, so the two never run together. The hub is the exception: there the
standalone plugin stays on for publishing and the module waits.

To move a client site: first search its saved Bricks data (_bricks_ post meta
and bricks_ options) for the old names above and convert anything found by
hand. Then press Install in its Tweaks column on the hub's Installs page (the
site needs reporter 1.2.0, which any of the standalone plugins' current
releases carry). The site installs SocialBUMP Tweaks, switches it on, and this
module takes over on the next request with the settings the standalone plugin
saved. The standalone plugin's files stay, switched off, until removed by hand.
## Tested (September 2026)

On bricks.socialbump.com.au, with standalone Bricks Tweaks 1.1.6 active: the new
files went in, the first request switched the standalone plugin off while the
module sat it out, and the second ran the module with every switched on feature
active and none failed. The home page, Contact (Single Page template), two
services (Single Service template, with and without repeater rows) and the
gallery post rendered identically to before, as a logged in admin because the
site shows Coming Soon to visitors. The Bricks builder opened, the carousel was
registered (with its hidden legacy name), listed in the panel and rendered a
gallery, the module's pages loaded, and activate_plugin(), a direct write to
active_plugins and the Plugins screen link all failed to bring the standalone
plugin back. A group switched off hides its page, as before.

## Hide Unpublished ACF Posts (October 2026)

The first feature added after the merge, so the standalone plugin never had it.
features/acf-published-only: an Extras tweak, needs ACF, off by default.

ACF hands back every post a relationship or post object field holds, whatever
its status, so a related post picked while still a draft showed in a Bricks
relationship loop straight away. SB_Bricks_Published_Only filters
acf/format_value/type=relationship and type=post_object at 20, after ACF's own
formatting, and keeps only posts whose status is publish. A single post object
that is not published becomes null. Attachments are always kept: an attachment
reports its parent's status, so an image attached to a draft post reads as
draft and would otherwise vanish. Formatted values are what Bricks loops,
dynamic data, conditions and get_field() read, so all of those follow it.

Front end only (it boots nothing when is_admin()), as the original ran: the
edit screens still list drafts so they can be picked ahead of time, and the
saved field keeps them. Bricks' own loop requests (load more, filters) go
through the REST API, which is not is_admin(), so they are filtered too. It
hides unpublished posts from logged in admins on the front end as well.

Taken from WPCodeBox snippet 97 on thecosmeticstudionoosa.com.au ("Hide
unpublished posts in ACF relationship loops"), which was switched off there
when this was added. The only change from the snippet is keeping attachments.
Checked on the hub with real posts: publish kept; draft, future, private,
pending and trash dropped; an image attached to a draft kept; a single draft
post object gave null; empty values passed through untouched.

## Sites moved over

- thecosmeticstudionoosa.com.au, 16 October 2026, standalone 1.1.8 to SocialBUMP
  Tweaks 0.1.11 with the Install button. Checked first: the standalone plugin
  and the module read the same three options, with the same feature, setting
  and group names and defaults (Gutenberg Block Styles had no saved value, on
  by default in both); no old names in saved Bricks data (one socialbump_ hit
  was a theme style id, socialbump_at); no snippet or child theme code hooking
  sbbt/ filters. Afterwards: standalone off and its files gone, the same
  eight features on, the four sb_bricks_ conditions registered, and 14 pages
  (home, blog post, locations, team, offers, contact, concerns, products)
  identical in text and markup to snapshots taken before. Hide Unpublished
  ACF Posts was switched on; it drops the two draft blog posts held in
  service and area of concern related posts fields, checked live on three
  pages. Snippet 97 stays off. Those staff fields also hold a revision id
  (5412) and three deleted ids beside the real posts; ACF drops those itself
  because the fields only accept staff-member, so the switch changes nothing
  there. Leftover, harmless: the sbbt_modules and sbbt_module_settings options
  and a fix-acf-cpt-svg-icons key, which nothing reads.
- drivingevents.com.au, 16 October 2026, standalone 1.1.8 to SocialBUMP Tweaks
  0.1.11, the last site on the standalone plugin. Same standalone code as
  Cosmetic Studio (class-sbbt-modules.php md5 abff5f0f...), same three
  options, every saved name known to the module; groups never saved, so all
  on by default in both; no old names in saved Bricks data (the same
  socialbump_at theme style); no snippet, child theme or mu-plugin code
  hooking sbbt/ filters. Image Carousel is saved in 29 places but only one is
  a live page (Targa Tasmania); the rest are revisions or unpublished.
  Afterwards: standalone gone from disk, the same nine features on (Post Type
  off), conditions sb_bricks_acf_relationship, _acf_repeater, _bricks_content
  and _woo_archive_display registered, sb-bricks-image-carousel and the hidden
  sb-image-carousel registered. Hide Unpublished ACF Posts was switched on.
  13 of 14 snapshot pages identical, Targa Tasmania with all 126 carousel
  markers. The Courses page lost one Book Now entry: the draft product "Open
  Pit Lane: Performance Night Sessions" (3223) in course 598's
  course_related_products, which the new switch now hides, as meant.
