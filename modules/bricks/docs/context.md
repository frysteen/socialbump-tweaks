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

Ten features in three groups, each with its own switch:

- Bricks Elements: Image Carousel (sb-bricks-image-carousel), an SEO friendly
  Splide carousel with real img tags, ACF gallery support, breakpoints,
  lightbox and continuous scroll.
- Conditional Logic, under the SocialBUMP group in the Bricks Conditions panel:
  ACF Relationship, ACF Repeater, Bricks Content, Post Type, WooCommerce
  Archive Display.
- Extras: ACF Loop Sorting (repeater and relationship loops, each with its own
  setting), ACF Gallery Loop, Default To WP Editor, Gutenberg Block Styles.

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
