# Site Kit module

What was the standalone SocialBUMP Site Kit plugin (last standalone release
1.2.6), now a module of SocialBUMP Tweaks, less its image pages, which are the
Image Management module (modules/images). Read the framework notes first, at the
plugin root in docs/context.md: menus, switches, saving, cards, popups, the
Updates page, the reporter and pushed updates all live there. This file covers
only what the Site Kit module does.

standalone-notes.md in this folder holds the standalone plugin's full notes, for
the detail on each feature and why it works the way it does. Its image sections
are copied into modules/images/docs/standalone-notes.md too. changes.md holds
Site Kit's release notes, shown read only on the Documentation page.

## What it does

Eighteen features in five groups. A group switched on gets its own page.

| Group | Features |
| --- | --- |
| Content | Excerpt Character Counter, Excerpts For Pages, Shortcodes In Excerpts, Force Gutenberg Page Refresh on Save |
| FAQ Settings (off until switched on) | FAQ fields on posts, FAQ post types as a library, FAQPage schema |
| WooCommerce | Download Analytics as CSV, Fix Default Category Title Bug, From Price on Variable Products, Hide Empty Decimals on Prices, Require Login to Check Out, Show Both Prices in the Cart, Turn Off Product Image Zoom |
| SEO | Robots.txt |
| Admin Settings | Block Admin Access, Hide Toolbar On The Front End, SocialBUMP Admin Colour Scheme, Fix ACF CPT SVG Icons, Post Types for Add-ons (always on, a wide card) |

FAQ Settings and Robots.txt are the only features in their groups and bring
their own page, so the group page is that page. The Robots.txt page shows
whether or not the feature is on, with its own switch, which is the feature's
switch; saving it on also switches the SEO group on.

Each feature's own entry in standalone-notes.md ("Every module in detail")
still describes what it does and how.

## How it is built

| Path | What it is |
| --- | --- |
| module.php | the declaration: groups, menu, bar, replaces, prompt, boot |
| includes/class-sb-site-kit.php | SB_Site_Kit: settings, is_enabled and page slugs for the features |
| features/<slug>/feature.php | one per feature, the framework's feature shape |
| assets/css/site-kit.css | the FAQ Settings and Robots.txt pages' own styles |
| docs/ | these notes, the standalone notes and the changes archive |

module.php defines SB_SITE_KIT_PATH and SB_SITE_KIT_URL and loads SB_Site_Kit.
Its boot only adds site-kit.css on this module's pages; the framework's
admin.css draws everything shared. A feature reads its card settings through
SB_Site_Kit::setting( $id, $key ), which asks sb_tweaks_module( 'site_kit' ),
and falls back to the saved option while the module is not booted.

The menu slug stays sb-site-kit, below SB Bricks Tweaks, and group pages stay
sb-site-kit-<group>, because bookmarks and Admin Site Enhancements' menu setup
hold them. Settings stay in sb_tweaks_site_kit_features, _settings and _groups,
plus sb_tweaks_site_kit_faq and _faq_fields for the FAQ page, so no site needs
converting.

## Names that moved, and names that cannot

Moved, code only: SBSK_* classes to SB_Site_Kit_* (SBSK_FAQ to
SB_Site_Kit_FAQ and so on), class-sbsk-*.php to class-sb-site-kit-*.php, the
sbsk/ filters to sb_site_kit/ (faq/schema, post_types/never, robots_txt,
robots_txt/sitemap_url), sbsk_ functions to sb_site_kit_ (post_type_choices,
post_types, admin_access_roles, front_toolbar_roles, robots_save), the
admin-post and AJAX actions (sb_site_kit_faq_save, sb_site_kit_save_robots,
sb_site_kit_faq_fields, sb_site_kit_faq_clean, sb_site_kit_analytics_csv and
_all), form field names, script globals (sbSiteKitExcerptCounter,
sbSiteKitRefreshOnSave, sbSiteKitAnalyticsCSV, sbSiteKitRobotsDefaults), CSS
classes and handles from sbsk- to sb-site-kit-, and the text domain to
sb-tweaks. Shared screen parts (cards, sections, switches, fields, popups) use
the framework's sb-tweaks- classes.

Kept, because posts have them saved: the FAQ fields' ACF keys,
field_sbsk_faq_questions, field_sbsk_faq_question, field_sbsk_faq_answer and
group_sbsk_faq_questions, and the related FAQ group and field keys
group_sbsk_faq_related_<n> and field_sbsk_faq_related_<n>. The field names
(faq_questions, faq_question, faq_answer, sb_tweaks_faq_related_<post type>)
never changed. The admin colour scheme's slug stays socialbump, since each
user's choice is saved under it.

A snippet on a client site that used an sbsk/ hook needs the new name before
the site moves over. Check each site's snippets for sbsk/ first.

## Popups

Every question goes through the framework's popup (SBTweaksConfirm, from
confirm.js). Delete FAQ content on the FAQ Settings page used to fall back to
the browser's own confirm when the popup had not loaded; it now does nothing
instead, since confirm.js loads on every module page.

## The standalone plugin

The module replaces socialbump-site-kit/socialbump-site-kit.php. On a client
site the framework switches the plugin off on the first load and keeps it off;
on the hub it stays on, to publish it, and the module waits out any request it
loaded in. Its admin bar entry (id site-kit) joins the SocialBUMP item while
the module is on.

Its image code is the Image Management module, which waits for the plugin the
same way (waits_for) and copies the image settings across once it is gone. See
modules/images/docs/context.md.

Not ported, because the framework or the hub already does them: Publishing,
the Documentation panel, Transfer, Updates, the shared admin bar, Overview and
Cards classes, Site Kit's own progress and popup scripts, and the run once
conversions: SBSK_Convert, which moved the sbsk_ option names to
sb_tweaks_site_kit_ and records it in sb_tweaks_site_kit_scheme, and the two
image ones, which Image Management's migrate covers. Before a site moves over,
check it has sb_tweaks_site_kit_scheme; a site without it still has its
settings under the old sbsk_ names.
