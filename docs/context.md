# SocialBUMP Tweaks

Notes for whoever picks this up next, most likely a new chat with no memory of
how any of it came about. Read this first.

**Keep it current.** Change how something works, add a feature, or learn
something painful, and write it here in the same session. A note that is wrong
is worse than no note, so fix anything you find that has gone stale.

## What it is

One plugin holding what used to be separate SocialBUMP plugins, each now a
module that can be switched on and off. Bricks Tweaks and Site Kit moved in
(Site Kit split into Site Kit and Image Management on the way), and Woo
Tweaks and Elementor Tweaks were added after. SEO for AI is not part of this:
it is standalone and shares nothing with it.

Naming, because the old plugins used these words differently and it matters:

- A **module** is what used to be a plugin. It has its own top level menu, its
  own pages, and a switch on the Modules page here.
- A **group** is a page inside a module.
- A **feature** is one thing inside a group. What each old plugin called its
  Modules page is a Features page now.

## Where it is up to

As of 31 October 2026, version 0.4.3 is the published version (28 October).
0.5.0 is next, with Email Signatures, the SocialBUMP UI look, betas and module
controls from the hub. Betas of it (0.5.0-beta.N, see Betas below) run on
gimage.com.au and bricks.socialbump.com.au. 0.3.0 was the first with Woo
Tweaks and Elementor Tweaks.

Release notes (Sam, 31 October 2026): keep them short. The changes waiting
for a release (option sb_tweaks_pending_changes, one line each, shown in the
notes box on Publishing and written to readme.txt's changelog) say what a
site owner would notice. A new module is one line, "Added the Email
Signatures module", with its detail in the module's own docs, not the notes.

- Six modules, each with its own notes in its docs/context.md: Bricks Tweaks
  (modules/bricks), Site Kit (modules/site-kit), Image Management
  (modules/images), Woo Tweaks (modules/woo-tweaks), Elementor Tweaks
  (modules/elementor-tweaks) and Email Signatures (modules/email-signatures).
- Elementor Tweaks was tested on epoxyflooringco.com.au through a test copy
  plugin (sb-elementor-tweaks-test) and a test page (13644). Epoxy has run
  SocialBUMP Tweaks since 0.3.0 (17 October), when the test copy was deleted;
  page 13644 was still there that day, for Sam to remove. See the module's
  notes.
- Moving a site onto SocialBUMP Tweaks, below, is the checklist for the sites
  still to come: Terrace Marina and Noosa's Elite Car Spa.
- Site Kit and Image Management went out in 0.2.0 on 16 October. Moved over
  and checked against a snapshot taken before each update: bricks.socialbump.com.au,
  drivingevents.com.au and thecosmeticstudionoosa.com.au. doogood.com.au had
  the plugin installed from the hub on 0.2.0, was updated to 0.2.1, and was
  moved onto the new standard image sizes and cleaned. Driving Events moved
  onto the standard sizes in October 2026 with image-480 and image-720 locked,
  because its sent newsletters link to those files; the locks keep them
  through Remove old sizes and the cleaner. hintercoasttransport.com.au has
  not been checked.
  See Site Kit and Image Management, and Woo Tweaks, below.
- SEO for AI stays standalone for good.

Built so far:

- The menu, banner, nav and Publishing, all hub only for Publishing.
- SB_Tweaks_Credit: the admin footer credit, part of the plugin rather than a
  module, so it follows the plugin being active rather than anything being
  switched on. The front end credit stays a WP CodeBox snippet,
  [social_bump_credit], and both links carry the same UTM parameters.
- The framework, includes/framework/: Cards (drag to reorder and collapse,
  kept per user), Bar (the shared SocialBUMP admin bar menu), Fields (the
  setting field types), Save (one pair of admin-post handlers for every
  module's features and groups), Features (a module's feature loader and
  option reader), Screen (a module's menu, banner and pages), and Modules
  (discovery, defaults, dependencies, retiring the old plugins).
- The Modules page: one switch per module, saved by save_modules() into
  sb_tweaks_modules. A module with a missing dependency keeps its saved state
  rather than having it written off behind someone's back, and with nothing
  saved the default keeps deciding.
- The admin bar rule: with no module on, SB Tweaks stands alone; with any
  module on, one SocialBUMP item (id sb-tweaks-group) holds a row per module
  and SB Tweaks as the last row, and both top items open the Modules page. The
  id differs from the standalone plugins' old socialbump item so the two never
  merge. The standalone plugins' shared bar offers each entry to the
  socialbump/admin_bar/claim filter first; SB_Tweaks_Modules::claim_bar_item()
  takes any whose id (the folder minus socialbump-) a module replaces and
  registers it with SB_Tweaks_Bar, which renders at 210, after the shared bar
  at 200. So a standalone plugin a module replaces, while it still runs on the
  hub for publishing, sits in the SocialBUMP item like its module. Unclaimed standalone plugins
  (Site Kit until its module exists) and SEO for AI sit on the bar alone. A
  claimed standalone plugin follows its module's switch: in the item while
  the module is on, not on the bar at all while it is off. Rows registered
  with a module id (Screen passes it, the claim adds it) are ordered by
  SB_Tweaks_Cards::sort() against the Modules page card order
  (SB_TWEAKS_OPTION), so the bar matches that user's drag order; SB Tweaks'
  own row is always last.

## The framework

A module is a folder in modules/ holding a module.php that returns its
declaration: id, title, description, requires, replaces, menu (slug, label,
page_title, after), bar (id, label), heading, prompt and boot. Its words are
plain strings, never __(), and so are a feature.php's: see Translations wait
for init. Folder names
are hyphenated (modules/site-kit/), ids are the option infix (site_kit), and
the page slugs stay sb-bricks-tweaks and sb-site-kit because ASE menu
configuration and bookmarks hold them.

- Options per module: sb_tweaks_<id>_features, sb_tweaks_<id>_settings and
  sb_tweaks_<id>_groups. Which modules are on lives in sb_tweaks_modules.
  A module with no saved state defaults to on where its
  sb_tweaks_<id>_features option already exists and off otherwise, so an
  update never switches anything on by surprise.
- Features are declared through SB_Tweaks_Features, the merged loader from
  the two old plugins: groups, per-feature settings, always, wide, the
  element type shorthand, an assets callable, and a per-module
  sb_tweaks/<id>/groups filter.
- Saving is SB_Tweaks_Save: admin-post actions sb_tweaks_save and
  sb_tweaks_save_groups, with the module id in a hidden sb_tweaks_module
  field. Module state saves through sb_tweaks_save_modules on the Settings
  class.
- Cards keys: a module's groups grid is keyed by its groups option, the
  Modules page by sb_tweaks_modules.
- SB_Tweaks_Screen draws a module's menu, banner and pages, and on the hub a
  Documentation page that edits the module's own docs/context.md in place,
  through admin_post_sb_tweaks_save_module_docs. Every module ships its own
  notes. Screen also owns brand_icon() and accent_colour(), which the
  Settings class now delegates to.
- Dependencies: acf (a copy bundled inside Bricks Advanced Themer does not
  count, since it hides the ACF menu), bricks (BRICKS_VERSION, or the active
  theme), woocommerce, and elementor (ELEMENTOR_VERSION, added for Elementor
  Tweaks), filterable through sb_tweaks/dependencies.
- Lockout: see Turning the old plugins off below; the rules were tightened
  when the Bricks module arrived.
- Dependencies are decided while plugins load, before the theme: bricks counts
  as present when BRICKS_VERSION is defined or get_template() is bricks, since
  BRICKS_VERSION does not exist yet at that point (a module requiring bricks
  never booted until this was fixed, September 2026).
- A module's Features loader and Screen are registered with SB_Tweaks_Modules
  before they boot, so a feature can read its module's settings through
  sb_tweaks_module( <id> ) while starting up.
- A module may ship docs/changes.md, its standalone plugin's release notes;
  the Documentation page shows it read only under Changes before the merge.
- Bricks element registration belongs to the Bricks module, not the
  framework. The Bar port dropped Site Kit's unused accent helpers.
- assets/js/framework.js holds the shared page behaviours: colour swatch
  sync, hide-when fields, the card switch is-on class, the select all and
  none links (data-sb-tweaks-check), and saved notices going by themselves:
  a dismissible success notice fades after 4 seconds (errors and warnings
  stay) and the address loses its updated flag, so a reload doesn't show it
  again (28 October 2026, Sam's request).

## The shape that was agreed

Written down before the framework was built, so it gets built to this.

### Menus

- SocialBUMP Tweaks has its own top level menu. With no modules on it shows
  alone and opens the Modules page.
- Each enabled module keeps its own top level menu with its own sub pages,
  exactly as it had as a plugin.
- Admin bar: one module on shows that module by name. Two or more show a
  combined SocialBUMP item with a row each, this plugin included.

### Switches

Two levels, both kept. A module has a switch on the Modules page here. Inside a
module, each group keeps its own switch and each feature keeps its own, as they
do today.

### Documentation, and what happened to Publishing

- The main plugin keeps **Publishing**: the release, the zip, and the framework
  documentation, which covers everything that works across modules and how a
  new module is built. It points at each module for what that module does.
- A module loses Publishing entirely and gains **Documentation**: its own notes
  and its own prompt for working on it.
- Both are hub only, hidden on client sites the way Publishing is today. The
  notes still ship inside the zip, so they reach every site as files. They are
  simply not readable through the admin there.
- Notes live with the module: modules/<slug>/docs/context.md. The framework's
  own notes are this file, at the plugin root.
- The shared block is gone. It existed because three plugins each carried a
  copy; inside one plugin there is nothing to keep in step.

### Storage

One set of options per module, which is what makes the export tick boxes fall
out naturally and lets an import touch only what it holds:

- sb_tweaks_<module>_features, _settings, _groups
- Site Kit also has _faq, _faq_fields, _image_split, _kept_orphans,
  _owned_image_sizes, and the user meta _cleaner_sizes
- Per user card order stays in the socialbump_cards user meta, keyed by the
  same option name

**No converter is needed.** Both plugins were renamed onto these keys while
they were still standalone, on every site, so the merged plugin reads what is
already there. That was the whole point of doing it that way.

### Turning the old plugins off

When a module lands here, the old standalone plugin has to stop running. The
danger is not clashing class names, which cannot collide across prefixes. It is
quieter than that:

- Both would read and write the same sb_tweaks_<module>_ options, so two
  screens fight over one value.
- Both would register the same Bricks conditions and the same carousel element
  name.
- Both would register menus with the same slugs.

So, wherever a module for it is on disk (switched on or not), SB_Tweaks_Modules:

- switches the old plugin off as soon as this plugin loads, front end
  included, rather than waiting for an admin page (retire_old_plugins() runs in
  boot(); a front end retirement leaves the sb_tweaks_retired transient so the
  notice appears on the next admin page);
- refuses to let it back into the active_plugins option
  (pre_update_option_active_plugins), which covers the Activate link, bulk
  activation, and a standalone update trying to reactivate itself;
- swaps its Activate link for a Replaced by SocialBUMP Tweaks note;
- does not boot the module in a request where the old plugin was already
  active when the request began (the running list captured at boot), because
  that plugin's code has run for this request. The module starts on the next.

The hub is the exception. It publishes the standalone plugins and needs each
running until its last release is out, so there none of the above happens:
the old plugin stays on, its module waits, and an info notice on this plugin's
pages says so. Test a module against a real site (bricks.socialbump.com.au)
rather than on the hub.

For Bricks Tweaks that is over: the standalone plugin was switched off and
deleted on the hub in October 2026, so the module runs there like anywhere
else. It applies again to Site Kit while its module is being built.

### Names that cannot change

The standalone plugins were cleaned up before the merge, so most identifiers
are free. These are not:

- Bricks condition keys sb_bricks_*, the element name
  sb-bricks-image-carousel, the loop settings sbBricks*, and the gallery query
  type sb_bricks_gallery_. All written into pages.
- faq_questions, faq_question, faq_answer. Sites hold content under them.
- The registered image size names, image-<width>: image-450, image-665,
  image-890, image-1330 and image-1920 by default since Site Kit 1.2, and
  image-240 to image-1920 on sites set up before that. Written into every
  attachment's metadata and into Bricks element settings.
- The shortcode tag social_bump_credit, which is a snippet rather than ours,
  but is placed by hand in templates.
- Elementor Tweaks' widget settings, saved in Elementor data: sb_elementor_alt,
  _alt_text, _alt_fill, _ratio, _ratio_custom, _fit, _position and
  _position_custom (with their per device copies), and its section id
  sb_elementor_image.

One persisted identifier is worth deciding deliberately rather than by
accident: the page slugs sb-site-kit and sb-bricks-tweaks appear in Admin and
Site Enhancements' own menu configuration, in the option
admin_site_enhancements_extra. Change them and that entry goes stale and any
bookmark breaks.

## How it is built

| File | What it is |
| --- | --- |
| socialbump-tweaks.php | constants, updater, hub check, sb_tweaks_log_change(), loads everything |
| includes/class-sb-tweaks-settings.php | menu, banner, the Modules page, Publishing |
| includes/class-sb-tweaks-credit.php | the admin footer credit |
| includes/class-sb-tweaks-release.php | publishing to GitHub, hub only |
| includes/class-sb-tweaks-docs.php | these notes, and the panel on Publishing |
| includes/class-sb-tweaks-updates.php | the Updates page: check, update, export and import, every site |
| includes/class-sb-tweaks-installs.php | the Installs page and the check-in route, hub only |
| includes/class-sb-tweaks-push.php | signed pushed updates and installs to sites, hub only |
| includes/class-sb-tweaks-hub-publish.php | Publish from the Installs page, hub only |
| includes/class-socialbump-reporter.php | the reporter, identical in every SocialBUMP plugin |
| includes/framework/ | the seven classes the modules run on |
| assets/js/framework.js | swatches, hide-when fields, switches, select all and none, saved notices fading |
| assets/js/progress.js | the progress popup, SBTweaksProgress (see Popups) |
| assets/js/confirm.js | the confirm popup, SBTweaksConfirm (see Popups) |
| assets/js/module-cards.js | the cards that drag to reorder and collapse |
| modules/ | one folder per module, each with module.php and its own docs |
| assets/css/admin.css | everything the admin pages look like |
| assets/js/save-state.js | the unsaved changes reminder |
| vendor/plugin-update-checker | the updater library, left alone |

The prefix is SB_TWEAKS_ and sb_tweaks_ throughout, classes are SB_Tweaks_,
the text domain is sb-tweaks, the page slug is sb-tweaks, and the repo is
frysteen/socialbump-tweaks.

The convention, so a new one never has to be guessed at: options, functions and
classes in snake_case; CSS classes hyphenated; Bricks element setting keys in
camelCase, because that is what Bricks itself uses; element names hyphenated,
because the name becomes the brxe- class; condition keys in snake_case.

The version badge in this plugin's own banner links to the Updates page, where
the settings export and import live. Module banners carry no badge.

## Where to be careful

- Everything is developed on the hub, plugins.socialbump.com.au, through its
  Novamira MCP connector. Lint every PHP file before writing it, and verify a
  change in a fresh request rather than the one that wrote the file.
- Upload a new module in an order that never leaves module.php pointing at a
  file that is not there yet. The framework includes every modules/*/module.php
  on every request, so a module.php whose require_once fails is a fatal error
  on every page, the MCP connector included, and the hub can then only be
  fixed by hand. On 17 October 2026 Elementor Tweaks' module.php went up
  before its class file, the next upload failed on the fatal, and Sam had to
  rename module.php in File Manager. Write the includes and features first,
  test load module.php under another name, and rename it into place last.
- Building PHP through a JSON tool call mangles double quotes. Build markup by
  concatenation with chr( 34 ), which is why the code here reads that way.
- A shared file that drifted took two client sites down. Nothing here is shared
  with anything, and that is worth keeping.
- Nothing may be translated before init. module.php and feature.php are read
  while plugins load, so their words are plain strings, and nothing that runs
  then (a module's migrate or boot, a feature's boot, an unavailable callable)
  may call __() or its relatives. WordPress 6.7 and later log a notice on
  every request otherwise. See Translations wait for init.
- Never name a variable $start in code run through Novamira's execute-php. Its
  wrapper times the run with a $start of its own, so a string left in $start
  throws "Unsupported operand types: float - string" after the code has run,
  outside any try, and whatever the code wrote is already on disk.

## Hub reporter

includes/class-socialbump-reporter.php is shared by every SocialBUMP plugin (Site
Kit, SEO for AI and SocialBUMP Tweaks, and standalone Bricks Tweaks before it
was retired): the same file in each, kept
identical like the shared admin bar, and guarded by class_exists so whichever
loads first runs. It is required from the plugin's main file at the top level,
not on plugins_loaded, so it is already listening when a plugin is activated.

It reports every tracked plugin on the site at once, active or not, since a
deactivated plugin cannot speak for itself: site URL and name, each plugin's
version and active state, WordPress and PHP versions. Nothing else. It posts to
https://plugins.socialbump.com.au/wp-json/sb-tweaks/v1/checkin with the shared
X-SB-Key header, and since reporter 1.2.1 waits up to 8 seconds for the hub to
confirm (the Reporter 1.2.1 note further down).
It sends on activated_plugin and deactivated_plugin for one of ours, on an admin
page load when the plugin list changed or a day has passed (option
socialbump_reporter_last holds the time and a hash), and from the daily cron
event socialbump_reporter_daily for sites nobody logs into. upgrader_process_
complete clears the last report so the next admin load sends the new version.
On the hub it calls sb_tweaks_installs_record() directly instead of over HTTP.

To track another plugin, add its folder to SocialBUMP_Reporter::PLUGINS in every
copy and a label to SB_Tweaks_Installs::LABELS, and bump the reporter VERSION.
PLUGINS still lists socialbump-bricks-tweaks. That is harmless, and changing
the file means releasing every plugin, so it waits for the next reporter change.

Reporter 1.0.1: WordPress fires deactivated_plugin before it saves the new
active_plugins list, so reading the list then still showed the plugin as active.
deactivated() sends with that plugin forced inactive (send() takes an override).
activated_plugin fires after the save, so activation needs no such help.

## Installs page (hub only)

includes/class-sb-tweaks-installs.php, booted in sb_tweaks_boot() only on the hub.
The REST route sb-tweaks/v1/checkin (POST) checks X-SB-Key with hash_equals,
allows 20 reports per site host in ten minutes (counter transient sb_installs_<md5>;
one per 30 seconds silently lost reports when plugins were updated back to back,
since the reporter does not wait for the reply),
and record() keeps only the tracked plugin slugs and version strings matching
/^[0-9][0-9A-Za-z.+-]{0,23}$/, strips the site name, and stores the latest report
per host in the option sb_tweaks_installs (not autoloaded, capped at 1000 sites).
The key ships inside the plugins, so it keeps out noise rather than a determined
sender; a forged report can only add a row, which the page can remove.

The Installs tab (sb-tweaks-installs, between Modules and Publishing) shows the sites as
a grid of cards (the hub's own table above them went 31 October 2026, see
Publishing from the Installs page) (Sam, 31 October 2026; before that a table with a column per
plugin). The hub's own copy of each plugin is taken as the latest release, so a
lower version shows amber (its tip says what the hub has), a beta blue, a
deactivated one grey, and a site silent for over three days gets a red border
and a red "Disconnected" pill in the foot where the last seen time was, its
tip the date and time it was last heard from (a deleted plugin, or a site
that is down, cannot report). Active beside a version links to that plugin's
main page on the site (SB_Tweaks_Installs::PAGES, built from the reported home
address plus /wp-admin/). The red cross on each card removes it, after asking;
it returns if the site checks in again.

Modules button (Sam, 31 October 2026): "Modules 5 of 6" on the left of each
card's foot, away from the Update buttons so the two aren't clicked by
mistake. It opens a popup with a tick box per
module and, for a module with limits, a number box for each (blank means no
limit). Save sends the policy to the site (see Module controls from the hub)
in the framework's progress popup: a spinner while the site answers, then
one line per module, "Allowed: Companies 1, Active people 5, Templates 2" or
"Hidden", how long it took, and Close, which closes without reloading the
page. A site on a version without the policy route answers 404 rest_no_route,
shown as "This site needs a newer SocialBUMP Tweaks first".

The cards (render() and card(), styles and script inline in script()):

- Bar (SB_Tweaks_UI::bar, class sb-installs-bar): Sites with its count and a
  search box; a filter (All, Needs update, On beta, each with its count;
  .sb-ui-seg with aria-pressed; Not heard from went 31 October 2026, the red
  pill shows it); on the right the select box and Actions. No sites
  matching shows "No sites match."
- Modules filter (Sam, 31 October 2026): a Modules button beside the
  filter opens a list of every module, all ticked, with Select all and
  Select none. Each row has an Only link on hover, and Option, Cmd or Ctrl
  click on a row does the same (just that module). A site shows when the
  hub allows it any ticked module (its data-policy; modMatch()), so Only on
  Email Signatures leaves the sites that may use it. The button reads
  Modules: All, None, the one module's name, or 2 of 6, with a green outline
  while it filters. Like the other filters, a change clears the selection.
- Selecting (Sam, 31 October 2026): one tick box with "Select all" or "3
  selected" beside it. Nothing ticked: it ticks every site showing.
  Anything ticked: it clears them all. Some ticked: it shows a dash
  (indeterminate). Changing the filter or the search clears the selection,
  so nothing is ever ticked out of sight. The select control is boxed like a
  button (140px wide at least) and Actions is at least 150px, so neither
  moves as the count changes (Sam, 31 October 2026). Actions has the word on the
  left and the arrow on its own slightly darker strip on the right
  (.sb-installs__actions-arrow).
- Actions (n) opens a menu (.sb-installs-menu) in two parts. Selected sites:
  Update everything waiting, Update Tweaks only, Update SEO for AI only,
  Modules. All sites: Signature limits, Update beta sites to the latest beta.
  An item that can't run is aria-disabled (not disabled, so its tip still
  shows) with the reason as its tip: select some sites first, none of the
  selected sites need this update, can take module changes, no site has
  Email Signatures allowed, no site on a beta is behind (blocked()).
- Card head: a tick box (on any site the hub can send to: one with updates
  waiting, or Tweaks active for Modules for selected), the site's icon in a 52px circle, the name, and its address with
  Plugins and Updates on the line under it (Sam, 31 October 2026: side by
  side they wrapped badly). All three are links with a small arrow (they
  open the site in a new tab), underlined only on hover (Sam, 31 October
  2026), as are the plugin names.
- Site icons: read from site_icon_url in each site's public /wp-json/ index
  (WordPress gives it out; no reporter change needed) and kept in the option
  sb_tweaks_install_icons (host to url, ok, at), apart from the reports since
  each check-in replaces those. A card whose icon is due (none stored, over a
  week old, or a day after the site didn't answer) carries data-icon-ask, and
  the page asks for those one at a time after it loads
  (wp_ajax_sb_tweaks_install_icon), so the page never waits on a site. No
  icon, or one that won't load, leaves the first letter of the site name on a
  colour picked from the address (ICON_COLOURS by crc32), so a site always
  gets the same colour. The image loads from the client site with
  referrerpolicy no-referrer.
- Icon ring (Sam, 31 October 2026): 3px, a darker shade (75% mixed with
  black) of the icon's own colour. It is the circle's background with the
  image inset 3px and rounded on top, not a border: a border left a thin
  light line where it met the image. An inner ring (::after, a 2px inset
  shadow in the same colour, 2px in) sits over the image's own edge, which
  still showed a faint line on round icons with see-through corners.
  When the hub fetches an icon, ring() shrinks it to 24 by 24 with GD and
  averages the coloured pixels, leaving out see-through ones and greys,
  whites and blacks; it is stored as ring with the icon. Fewer than 12
  coloured pixels (a black and white logo), a colour too pale to show, or an
  image GD can't read (.ico, .svg) give no colour and a neutral ring
  (--sbui-border-strong). Letter circles get a darker ring of their own
  colour (color-mix with 30% black). An icon stored before rings existed is
  due again at once. First run 31 October 2026: 9 coloured, 4 neutral
  (Doogood, Glenys Searle, Makeup by Marlies, RZ Restorations), 3 letters.
- One line per installed plugin (Sam, 31 October 2026): a light on the left
  (lit green when active, amber when it needs an update, off and grey when
  deactivated, the state as its tip), the plugin's name as the link to its page on the site (plain text
  when deactivated), then the version pill and its buttons on the right. The
  buttons are always there so every card looks the same: Update on each
  plugin, and Push beta (Update beta on a site already on one) on Tweaks,
  greyed out when there's nothing to do. A plugin that
  isn't installed isn't shown, except Tweaks with an Install button when a
  module would move a plugin in ("Moves Site Kit in" as its tip). A failed push shows its
  reason under the line.
- Under Tweaks, when the site may use Email Signatures: "Signatures" on the
  left and its limits on the right as plain text split by thin lines
  (Companies 1 | People 5 | Templates 2), ∞ for no limit with "No limit" as
  its tip, so it stays on one line (Sam, 31 October 2026; it was pills),
  and Signatures is a button that opens that one site's three limits in a
  small popup (esPopup( card ), title "Email Signatures limits on Site"), redrawn after any save (es_line() and the script's esLine()).
  These are what the hub last sent (sb_tweaks_policies) or the defaults; the
  hub doesn't know whether the site has switched the module off on its own
  Modules page, or how many it uses, as sites don't report either yet.
- Several sites at once (Sam, 31 October 2026): Modules for selected opens
  one popup for the ticked sites, each module with Leave as is (showing all
  allowed, all hidden or mixed), Allow or Hide, and limit boxes where blank
  leaves each site's own. Signature limits lists every site with Email
  Signatures allowed, its three limits ready to edit, and sends only the
  rows that changed ("Nothing changed" toast otherwise). Both send each site
  its whole policy through wp_ajax_sb_tweaks_policy, one at a time in the
  progress popup, a line per site in the summary (one site: a line per
  module). The card's data-policy holds what was last sent, for the script.
- The foot: Modules, then when it last checked in ("6 hours ago" with a
  clock, the date and time as its tip; a red Disconnected pill instead on a
  site silent for over three days), and Update all
  (only with updates waiting) and the red cross on the right. It wraps on a
  narrow card. WordPress and PHP versions aren't shown (Sam: he has them
  elsewhere).
- After an update the pill changes in place and its button greys out (a
  beta also greys out Update, since the site is now ahead), the filter
  counts are redone, and a card with nothing left loses its tick box and
  Update all.
- Phones: each part of the bar goes on its own line and the filter wraps.

Checked 31 October 2026 by rendering it on the hub (no PHP warnings, 16
sites) and screenshotting a copy in light, dark and phone widths, with three
made-up cards added to the copy for the behind, silent and not installed
states.

## Hub moved (September 2026)

The hub moved from bricks.socialbump.com.au to plugins.socialbump.com.au, so the
Bricks blueprint can stay clean for starting new sites. The site was copied with
Duplicator, which kept the WordPress security keys, so the encrypted GitHub tokens
were copied across as they were. Every hub address in the plugins (the *_HUB_HOST
constants, SocialBUMP_Reporter::ENDPOINT and HUB_HOST, reporter 1.0.2, the docs and
the AI prompts) now names plugins.socialbump.com.au. Watch for this on any future
move: a copy of the hub on a new address is not the hub until the code says so, and
the first admin page load there runs each plugin's tidy-up, deleting the GitHub
token, the queued release notes and the latest release record. Sites keep
reporting to the old address until they update to a release naming the new one.

## Pushed updates (reporter 1.1.0)

The Installs page can push a release to a site. SB_Tweaks_Push (hub only) signs
an instruction with the hub's Ed25519 private key, sb_tweaks_push_secret, stored
encrypted with wp_salt('auth') like the GitHub tokens (the public half is
sb_tweaks_push_public). The instruction names the host, the plugin, the version,
the download URL, an expiry five minutes out and a random 32 character nonce. It
is posted to the site at ?rest_route=/socialbump/v1/update, which works whatever
the permalink setting.

Every reporter since 1.1.0 carries the public key (PUSH_KEY) and registers that
route. receive() refuses anything that is not signed by the hub, not addressed
to this host, expired or more than ten minutes ahead, already used (nonces kept
as socialbump_push_<nonce> transients for fifteen minutes), not one of PLUGINS,
not exactly https://github.com/frysteen/<slug>/releases/download/v<version>/<slug>.zip,
or not newer than what is installed. It then runs Plugin_Upgrader::run() with
Automatic_Upgrader_Skin and WordPress's temp_backup rollback, only when the
filesystem method is direct, clears opcache for the plugin, reports, and replies
with the new version. The version and URL come from the hub's own copy, so the
site never asks GitHub's API, which is what the 60 an hour limit applies to;
release file downloads are not limited that way.

The hub records each site's reporter version and only shows Update buttons on
sites at 1.1.0 or later (SB_Tweaks_Push::NEEDS). Update all pushes one plugin at
a time. Tested September 2026: every refusal case, then a real push of Site Kit
1.1.20 to bricks.socialbump.com.au in 8.3 seconds with settings and activation
kept. A site whose security plugin blocks outside REST requests answers with an
error the button shows as is.

## Updates page (every site)

includes/class-sb-tweaks-updates.php, booted on every site from sb_tweaks_boot(),
with the page at sb-tweaks-updates (second in the nav, after Modules; the header
version badge links to it). Check for updates calls the plugin-update-checker
instance in $GLOBALS['sb_tweaks_update_checker'] and then wp_update_plugins() so
Update now shows straight away. Update now uses update-core.php's
do-plugin-upgrade bulk path, which never deactivates the plugin (the single
plugin path can leave it switched off; Site Kit hit that).

Export and import cover every option named sb_tweaks_* that travels():
everything except github_token, pending_changes, latest_release,
sb_tweaks_installs, sb_tweaks_push_* and anything ending _backup. Modules added
later are included automatically because they follow the sb_tweaks_<module>_
naming. The Site Kit and Bricks Tweaks standalone settings already use that
naming, so their settings are in an export today. Import only accepts a file
whose plugin is socialbump-tweaks, under 2 MB, and only writes names that pass
travels() and are plain lowercase, digits and underscores. The page renders and
its handlers are registered whether or not any module is on, which is the rule
for every framework page.

Reporter 1.2.0: a signed instruction can also say action install, for
SocialBUMP Tweaks only (INSTALLABLE), from its own GitHub release. install_new()
installs it with Plugin_Upgrader::install() if it is missing, switches it on,
then posts once to admin-ajax.php (never page cached) so SocialBUMP Tweaks runs
and switches off the standalone plugins it has modules for, reports, and
answers with the version and which tracked plugins are now off. Any other
action is refused. The hub offers it (SB_Tweaks_Push::can_install, reporter
1.2.0 or later) as an Install button in the Tweaks column, only on a site
with a plugin some module's replaces names.

## Publishing from the Installs page

Removed 31 October 2026 (Sam): the hub table described next is no longer on
the Installs page. The top navigation shows when a plugin has changes waiting,
and each plugin's Publishing page shows the hub and GitHub versions.
render_hub(), hub_script(), live_version() and RELEASES went with it, and the
hub itself is still left out of the cards. includes/class-sb-tweaks-hub-publish.php
is still there, but nothing calls SB_Tweaks_Hub_Publish now; remove it or put
it to use. The rest of this section is how it worked.

includes/class-sb-tweaks-hub-publish.php, hub only. The hub table has a tick box
per plugin with changes waiting (and select all), the waiting count opens a popup
listing the changes with Cancel and Publish, Review links to the plugin's
Publishing page, and each row has Publish <next version>. Publish selected runs
them one at a time, then the page reloads so the sites table measures against
the new versions.

SB_Tweaks_Hub_Publish::publish() does not reimplement publishing. It fills in
exactly what the plugin's own Publishing form would send (<prefix>_version as
the next patch number, <prefix>_notes from changes_text(), and a fresh
<prefix>_publish nonce), calls that plugin's release class publish(), and
catches its closing redirect with a wp_redirect filter that throws
SB_Tweaks_Hub_Publish_Done. The outcome is the plugin's own notice transient
(NOTICE_PREFIX + user id), read and cleared. Prefixes: sbsk, sbaike,
sb_tweaks (sbbt went with standalone Bricks Tweaks). Tested by asking Site Kit's handler for its current version, which it
refused before touching GitHub, with the redirect caught and the message read
back.

Sites: Update selected runs every waiting update on the ticked sites, and its
menu one plugin only on them (each choice enabled only when a ticked site is
behind on it). Update all on a card does that card's site. All of them run
one at a time in the framework's progress popup (SBTweaksProgress, see
Popups). A single Update button on a plugin row updates in place.
Keep explanatory copy on these pages to a minimum; Sam knows what the buttons do.

## Popups

Two kinds, both in the framework, both looking like SEO for AI's.

### Progress popup (assets/js/progress.js, window.SBTweaksProgress)

For anything that takes more than a moment: publishing, pushing updates to
sites, rebuilds, bulk changes. Loaded on every SocialBUMP Tweaks and module page
alongside framework.js, so a module never ships its own.

    var job = SBTweaksProgress.open( 'Publishing' );
    job.busy();
    job.step( 0, 3, 'Bricks Tweaks: building and publishing 1.1.7' );
    job.status( 'Publishing 1 of 3' );
    job.log( 'Bricks Tweaks 1.1.7', 'published' );
    job.log( 'SEO for AI 1.1.6', 'did not publish: reason', true );
    job.step( 1, 3 );
    job.finish( 'Publishing complete' );
    job.fail( 'GitHub could not be reached' );

How it looks and acts, and how every long action in these plugins should:

- It opens the moment the button is pressed, over a dark overlay, as a
  centred white box: the elapsed time small and grey in the top right corner,
  a centred title saying what is happening (Publishing, Updating sites), a
  rounded progress bar in the admin colour scheme's accent, a bold status line
  ("Publishing 1 of 3"), and a grey line for the item being worked on now,
  written "Label: detail" so the label shows in bold.
- It cannot be closed while it runs: no X, no Close, Escape does nothing. The
  work is usually partway through something that should not be abandoned.
- busy() is for one step with no telling how long it will take, such as
  Publish release on a Publishing page: a spinner turns in place of the bar
  until step(), finish() or fail(). Sam's call: a bar suggests a count that a
  single post does not have.
- step( done, total, current ) moves the bar and sets "done of total"; call
  status() after it to reword that line. Work through items one at a time
  when each one touches something external (GitHub, a client site).
- log() records one line per item for the report: what, then what happened.
  A failure passes true and carries the reason in the item's own words, never
  a generic "something went wrong".
- finish( summary ) ends on Finished: the bar full (red if anything failed), a
  grey report card with the bold summary and "in 2.8 seconds" (or minutes and
  seconds), one line per item with the item bold, failures in red, and a
  centred primary Close button. Close reloads the page so everything on it
  reflects the result; pass a function as the second argument to do something
  else instead.
- fail( message ) stops where it is, turns the bar red, shows the reason and
  offers Close.
- Summaries: "Publishing complete" or "Updates complete" when everything
  worked, otherwise the counts, "2 published, 1 failed".
- Keep the words short. No instructions, no explaining what the buttons do.

### Confirm popup (assets/js/confirm.js, window.SBTweaksConfirm)

Every question asked before acting uses this, never the browser's confirm().
Loaded on every SocialBUMP Tweaks and module page next to progress.js, by
SB_Tweaks_Settings::styles() and SB_Tweaks_Screen::styles(). Moved in from
Site Kit (October 2026), where it was SBSKConfirm.

In markup, a button or link that carries the question asks first:

    <button data-sb-tweaks-confirm="Publish version %s to every site?"
            data-sb-tweaks-confirm-field="sb_tweaks_version"
            data-sb-tweaks-confirm-title="Publish release"
            data-sb-tweaks-confirm-action="Publish">

%s is the value of the form field named in -field; -title defaults to the
button's text and so does -action. On the action the element is clicked
again natively with a one shot pass, so a form submits (and its submit
handlers run, the background publish included) and a link follows its
address. From a script: SBTweaksConfirm.ask( element, options ),
.confirmed( element ) to spend the pass, .tell( options ) for OK only, and
.open( options, onAction ) for anything else, such as a summary. options:
title, message, body, check (a tick box, or { label, list } with each line's
first word bold), cancel, action, alert, before( checked ). Styles
.sb-tweaks-modal and .sb-tweaks-dialog*, the same box as the Installs page's.

Deletes and resets (Sam, 30 October 2026): tone: 'danger' (or
data-sb-tweaks-confirm-tone="danger", and any trigger whose class has --danger
in it) puts the warning icon by the title (.sb-tweaks-dialog__head and
__icon) and makes the action solid red with white words
(.sb-tweaks-button--danger-solid). On SocialBUMP UI pages every popup's
buttons sit together on the right in a grey footer, as in the UI Kit.

Buttons: .sb-tweaks-button--danger is a red outline that turns solid red
with white on hover, for every delete or trash button. An icon with no words
is square at any size: .sb-tweaks-button--icon (the Email Signatures module
uses its own .sb-emailsig-icon, also square).
Converted in October 2026: Publish release and Remove token on Publishing,
Import settings on Updates, and the remove site cross on the Installs page.
SEO for AI has its own copy, SBAIKEConfirm (data-sbaike-confirm), as it does
of the progress popup.

Since 28 October 2026 the popup carries wp-core-ui on its own outer element,
so it looks the same on a front-end page (Email Signatures' team page) that
loads the framework's admin.css, WordPress's wp-includes/css/buttons.min.css,
jQuery and confirm.js; in wp-admin nothing changes. Fields to fill in go in
body, each as <label class="sb-tweaks-dialog__field"><span>Label</span>
<input or textarea></label>: label above, full width box (Email
Signatures' Email link popup is the first to use it).

### Confirm popup (the waiting changes on the Installs page)

For looking at something before acting on it. The same overlay and box (10px
radius, 24px padding), the title top left, an X to close in the top right,
Cancel on the left and the action on the right along the bottom, Escape and a
click outside close it. Its action hands straight over to a progress popup.
Markup and styles: .sb-hub__modal and .sb-hub__dialog in the Installs page.

Module banners carry no version badge (SB_Tweaks_Screen::render_header): a
module has no version of its own. The badge, linked to the Updates page, is
only on SocialBUMP Tweaks' own pages (Modules, Updates, Installs, Publishing).

Reporter 1.2.1 (September 2026): reports sent without waiting (blocking false)
were being lost, most visibly straight after a push, when the hub is busy
waiting on that same site, so the Install button never appeared for sites the
hub still thought were on reporter 1.1.0. Now:
- send() waits up to 8 seconds for the hub's answer and only records
  socialbump_reporter_last once the hub confirms ok. A failure sets the
  socialbump_reporter_retry transient (15 minutes) so maybe_send() retries on
  a later admin page load without hammering an unreachable hub.
- receive() no longer sends a report during a push. The answer carries the
  site's report instead (with_report() / report()), and SB_Tweaks_Push::push()
  records it with SB_Tweaks_Installs::record() when its url matches the host.
- payload()'s reporter is running_version(): the VERSION of the copy that will
  load first next request (first active tracked plugin with the file), read
  from disk, because straight after an update the copy in memory is older.
- The hub's check-in handler calls ignore_user_abort( true ).
The Install button is generic: any site with a plugin that some module's
replaces names, and no SocialBUMP Tweaks, gets it once its reporter is 1.2.0
or later. When the Site Kit module lands with replaces set, Site Kit only sites
get it with no further change.

## Buttons

Every button on a SocialBUMP Tweaks or module page has a 2px border, filled
or outline (Sam, October 2026), so two buttons of the same size are always
the same size whatever their colour. The rule is the first thing in
admin.css, so the page sizes further down still win. admin.css only loads on
our own pages, so WordPress's screens (Media, posts) keep their 1px buttons.

Sizes, measured against WordPress 7.1.3, which draws a 1px border. For each
size the line height drops by 2px and the side padding by 1px, so the height
stays WordPress's own:

| Size | Height | Line height | Side padding |
|---|---|---|---|
| Standard and button-large | 40px | 36px | 15px |
| button-compact | 32px | 28px | 11px |
| button-small | 24px | 20px | 7px |
| button-hero | 48px | 44px | 35px |
| Any of those under 782px | 40px | 36px | 13px |
| Popup buttons, Installs page | 28px | 24px | 11px |
| Installs button-small | 24px | 20px | 8px |
| Keep and Delete in the image report | unchanged | 1.8 | 9px, 1px top and bottom |

- If WordPress changes its button sizes, redo these from
  wp-includes/css/buttons.css. The 2px border is the fixed part.
- sb-save--clean and sb-save--dirty set their border with !important, so
  they say 2px themselves.
- A page that needs its own button size: 2px border, line height = height
  minus 4px, and a selector more specific than .wp-core-ui .button (or later
  in admin.css). The image report's buttons use .button.sb-images-report__keep
  because images.css can load before admin.css.

Outline colours, for a plain .button (never alongside button-primary):

| Class | Colour | For |
|---|---|---|
| sb-tweaks-button--go | green #457a03 | a change that builds or adds, such as Build Thumbnails |
| sb-tweaks-button--danger | red #d63638 | anything that deletes or resets: Remove old sizes, Delete orphan images, Reset to default sizes, the robots.txt reset, Delete ticked content |
| sb-tweaks-button--quiet | grey border #8c8f94, text #3c434a | starts a look, not a change: Find leftover thumbnails |

- Go and danger fill with their colour on hover and focus. Quiet darkens its
  border on hover and keeps WordPress's focus ring.
- Disabled ones take WordPress's grey. Build Thumbnails relies on this: it
  shows green only while there is something to build.
- The filled primary button (Scan images, Save changes) is the page's main
  action. One per row.
- Modules do not keep their own copies. Site Kit's sb-images-button--* and
  sb-site-kit-button--danger were moved onto these in October 2026, and the
  framework's old, unused sb-tweaks-button--deep became --quiet.
## Publish posts in the background (October 2026)

The spinner Site Kit's Publish release shows sat still, because a normal form
post starts leaving the page and browsers stop animating while they wait. The
Publishing pages of all three hub plugins (this one, Site Kit and SEO for AI)
now post Publish release in the background, and this plugin's page now shows
the progress popup too, where before it had none.

- progress.js gained busy() and the __spin element (styles .sb-tweaks-job__spin,
  .is-busy and the sb-tweaks-job-spin keyframes in admin.css). step(), finish()
  and fail() take the spinner away again. See Popups.
- SB_Tweaks_Release::render(): the publish form has id sb-tweaks-publish-form
  and an inline script after it. On submit (after the browser's confirm() in
  the button's onclick, and after the browser's own validation) it opens the
  popup with busy(), sends the same form with fetch plus
  sb_tweaks_background=1, and shows the answer: "SocialBUMP Tweaks x.y.z
  published" and "Publishing complete", or fail() with the handler's message.
  A reply that is not the JSON, or a dropped connection, fails with "No clear
  answer came back", because the publish may still have gone through. Close
  reloads the page, which shows the usual notice and the new version.
- back() still saves the notice, then, with sb_tweaks_background set, answers
  wp_send_json( { ok, message } ) instead of redirecting (message is the
  notice without its tags). Without the field it redirects as before, which
  is what SB_Tweaks_Hub_Publish on the Installs page relies on; it never sends
  the field.
- The confirm on this page is still the browser's own confirm(), not the
  confirm popup. Left for Sam to decide.
- Hub only in effect (client sites carry progress.js and the CSS but never
  see Publishing), so no release note.
- Checked: progress.js passes node --check, md5 matched on the hub. In jsdom:
  the spinner while waiting, then success, a refused publish, an HTML answer,
  a dropped connection, and a cancelled confirm posting nothing. On the hub
  over HTTP with a session: the Publishing page loads with progress.js; a
  background post with the current version comes back as JSON ok false "has
  to be higher than the current version" with the main file untouched; the
  same post without the field still redirects (302).
- Fix, same day: the first real publish failed at once with No clear answer. The
  script fetched form.action, and in a browser that is the hidden input named
  action, not the form's address, so the post went to a junk URL and never
  reached WordPress (nothing was published). It now uses
  form.getAttribute( 'action' ). jsdom does not do that lookup, which is why
  the simulation passed; the test now fakes it, failed the old code, and
  passes the fix.
- Fix, a day later: on this page the popup never opened and Publish posted the
  old way. The inline script checked for the progress popup when the page
  loaded, but the popup script loads in the footer, after it, so the check
  always failed. It now checks when Publish is pressed. The jsdom test had
  loaded the popup script first; it now loads it last, as the page does.
  0.1.11 went out the old way, before this fix. Not yet seen working in a
  browser on this page.

## Faster publish (October 2026)

push_code() now lists the repo's tree once (?recursive=1) and works out git's
blob hash for each local file (sha1 of "blob <length>\0<contents>"). A file
whose hash matches is put in the new tree by that sha and not sent again;
only changed files go inline or as blobs. It used to send everything each
time, including 37 non-text files (the update checker's .mo translations and
the icons) as a request each. If the listing fails or comes back truncated it
sends everything as before. Proven first in Site Kit: its publish went from
32 to 11 seconds, and afterwards every file on GitHub matched the hub. Here 0.1.11
was the first publish with it, and afterwards all 178 files on GitHub matched
the hub, README.md and LICENSE kept. Hub only, no release note.

## Bricks Tweaks retired (October 2026)

On 16 October 2026, once Cosmetic Studio and Driving Events had moved over and
no site on the Installs page reported standalone Bricks Tweaks, it was removed
from the hub.

Removed:
- The socialbump-bricks-tweaks folder (it had no uninstall routine, so nothing
  else went with it; a zip of it was kept in the hub's /tmp at the time).
- Its hub options sbbt_github_token, sbbt_modules, sbbt_module_settings and
  sbbt_groups. The module's own sb_tweaks_bricks_* options stay.
- Its entries in SB_Tweaks_Installs::LABELS, PAGES and RELEASES, so it has no
  column or hub table row, and in SB_Tweaks_Hub_Publish::PLUGINS.
- Site Kit's docs drift check (SBSK_Docs::drifted()) now compares with nothing:
  Bricks Tweaks is gone and SEO for AI no longer carries the shared block,
  which had made it warn falsely since September.

Kept on purpose:
- The module's replaces line, so a site that installs the old plugin again
  still has it switched off and blocked.
- socialbump-bricks-tweaks in the reporter's PLUGINS (see Hub reporter).
- The module's docs/standalone-notes.md and docs/changes.md, the archive.
- The GitHub repo frysteen/socialbump-bricks-tweaks, for Sam to archive.

A side effect: with the label gone, the Installs page no longer records or
shows the standalone plugin, so a site that somehow still ran it would not
show it there. The module's block still protects such a site.

Cosmetic Studio and Driving Events still hold the standalone plugin's old
sbbt_modules and sbbt_module_settings options, which nothing reads.

## Modules under another menu, migrate and waits_for (October 2026)

Added for Image Management, which moves out of Site Kit:

- menu parent (e.g. 'upload.php'): the module's pages sit in that menu, one
  per group, with no top level menu and no Features page. The Modules page
  switch turns the whole module on or off. SB_Tweaks_Screen::parent(),
  page_url( $slug ) (upload.php?page=... under a parent, admin.php?page=...
  otherwise) and front_door() (the Features page, or the first group page).
  Every link the Screen draws (banner home, tabs, admin bar rows, card
  Settings links) and the Modules page Open link go through page_url(). The
  hub's Documentation page of such a module is registered under options.php,
  which is in no menu (with no parent at all WordPress gave it no title): it opens at admin.php?page=<slug>-documentation and shows in the
  tabs and the admin bar flyout, but not in the Media menu.
- migrate: a callable run for every module at the start of
  SB_Tweaks_Modules::boot(), before any is asked whether it is on, so a module
  that takes over settings from elsewhere has its options by the time 'auto'
  looks for them. It checks for itself whether there is anything to do.
  A throw is caught and shown as a failed load on the Modules page.
- waits_for: a plugin basename the module must not run beside without
  replacing it. replaced_running() now checks it as well as replaces, so the
  module sits out a request in which that plugin had loaded.
- Under a parent menu, ordered_groups() returns the groups in the order
  module.php declares them: there is no Features page to drag them on, and
  alphabetical put SB Image Cleaner ahead of SB Image Settings. The Media menu,
  the tabs, the bar flyout and front_door() all follow it.

## Site Kit and Image Management (October 2026)

Standalone Site Kit split in two on the way in. modules/site-kit is everything
but the image pages, on its old options and page addresses (sb-site-kit,
sb-site-kit-<group>), with replaces set to the plugin. modules/images is the
Images and Image Cleaner pages as SB Image Settings and SB Image Cleaner in the
Media menu, on options of its own (sb_tweaks_images_*), copied once from Site
Kit's by its migrate, and waits_for the plugin. Each module's docs/context.md
has the detail, the names that moved and the ones that could not;
standalone-notes.md and changes.md hold what Site Kit had.

Both were ported by script from Site Kit 1.2.6 and checked name by name: every
sbsk name moved (classes, hooks, actions, nonces, options the image module took
over, CSS, script globals, text domain) apart from what posts have saved, the
FAQ fields' ACF keys and the zoom block attributes. Shared screen parts use the
framework's classes and popups; each module has its own stylesheet for its own
pages (site-kit.css, images.css, media-grid.css, gallery-zoom.css). No browser
confirm is left in either.

Before a site moves over: check its snippets for any sbsk/ hook, which needs
the new name; check it has sb_tweaks_site_kit_scheme (SBSK_Convert ran); and
afterwards clear the page cache if it uses Gutenberg image zoom.

Once the Installs page shows no site left on standalone Site Kit, the move-over
code can go: SB_Images_Module::migrate() and its old_addresses() redirect, and
the module's waits_for. The Site Kit module's replaces stays as long as any site
might still have the plugin on disk, as with Bricks Tweaks.

admin.css here still carries older copies of Site Kit's image and FAQ styles
under sb-tweaks- names (Images page, Rebuild panel, sizes, report, orphans,
FAQ sources), from before the framework was split out. Nothing uses them now.

## Woo Tweaks (October 2026)

Sam's call: WooCommerce features get a module of their own, unless a feature
only exists for another module (Bricks keeps its WooCommerce Archive Display
condition, Site Kit's Block Admin Access keeps its My Account choice). Site
Kit's seven woo-* features moved to modules/woo-tweaks: its own SB Woo Tweaks
menu (not under WooCommerce, which has enough in it), after SB Site Kit, with
two pages, Shop and Checkout, and Store Admin. Module id woo, so its options
are sb_tweaks_woo_*. It requires woocommerce, so on a site without it the card
says so and nothing loads, the hub included. Site Kit lost its WooCommerce
group and page; the old address redirects. SB_Woo_Tweaks::migrate() copies the
switches and From Price's settings once. Driving Events is the only site known
to need it. The module's docs/context.md has the names that moved.

Tested on the hub by pretending WooCommerce was active for a few requests (a
temporary mu-plugin filtering sb_tweaks/dependencies and the features option,
removed straight after): the Features page, both group pages with the right
cards, the Documentation page, the Modules card, and the old Site Kit address
redirecting. On the hub, Admin Site Enhancements' menu order puts SB Woo
Tweaks near the bottom of the menu rather than after SB Site Kit; the module's
'after' only applies where nothing else reorders the menu.

## Moving a site onto SocialBUMP Tweaks

The checklist for a client site, whatever it ran before. Each module's notes
have the detail behind each line.

Before installing:

- Snapshot the options and the pages that matter (to /tmp as sbx-<stamp>-*),
  so the result can be checked against them afterwards.
- Standalone Bricks Tweaks: search saved Bricks data for the old names (the
  Bricks module's Moving a site over). None were left on the known sites.
- Standalone Site Kit: the site must have sb_tweaks_site_kit_scheme (its
  options already renamed). Search snippets, the child theme and mu-plugins
  for sbsk/ hooks and rename them (Site Kit and Image Management notes).
- WooCommerce sites: Woo Tweaks copies Site Kit's woo-* switches itself.
- Elementor sites with Custom Alt Text for Elementor Images: nothing to do by
  hand. Elementor Tweaks renames its settings and switches it off. A test copy
  (sb-elementor-tweaks-test) steps aside by itself while SocialBUMP Tweaks is
  active, so delete it after installing; deleting it first leaves anything
  already set with the module's settings unrendered in between.
- Snippets doing what a module now does (hiding unpublished ACF posts, image
  sizes, alt text): note them, to switch off once the module is on.

Installing: the Install button in the site's Tweaks column on the hub's
Installs page (reporter 1.2.0 or later), or upload the zip. The first request
runs every module's migrate and retires the standalone plugins and the old alt
text plugin. The modules take over on the next request.

Afterwards:

- Compare with the snapshot: the same features on, the same pages.
- Switch off the snippets noted above.
- Clear the page cache (the Gutenberg image zoom markup changed names).
- Image sizes, if the site is moving onto the standard sizes: Switching a site
  to the standard sizes in modules/images/docs/context.md. Lock any size that
  emails or other sites link to first, as was done for image-480 and
  image-720 on Driving Events.

## Elementor Tweaks (October 2026)

Sam's first two Elementor wants, as a module: alt text set per widget,
replacing the Custom Alt Text for Elementor Images plugin, and an aspect ratio,
fit and position for widget images with no ratio of their own.
modules/elementor-tweaks, id elementor, its own SB Elementor Tweaks menu after
SB Site Kit with one page, Images. It requires elementor, a dependency added to
the framework for it. Both features add to an SB Image section in the widgets
instead of having settings pages. The old plugin's settings are renamed to
sb_elementor_* once by the module's migrate, and the plugin is retired through
replaces. The module's notes cover how widgets are picked, the Rank Math catch
with decorative images, and the test on Epoxy.

## Translations wait for init (October 2026)

Found on epoxyflooringco.com.au (PHP 8.4, WordPress 7.1.3) with WP_DEBUG on:
every request logged "Function _load_textdomain_just_in_time was called
incorrectly. Translation loading for the sb-tweaks domain was triggered too
early." WordPress 6.7 and later raise it when anything in a domain that has a
translation path is translated before after_setup_theme has finished. It is
logged once per domain per request, so one stray call is as loud as a hundred.

The cause: SB_Tweaks_Modules::discover() includes every module.php, and each
booted module's SB_Tweaks_Features::discover() every feature.php, on
plugins_loaded, and those files called __() at file level: 115 strings in 38
files. Two unavailable callables translated at boot as well: Excerpts For
Pages (only when pages already had excerpts at that point) and Require Login
to Check Out (whenever WooCommerce allows guest checkout, so every request on
Driving Events). Every other translation in the plugin sits in code that runs
on init or later. A temporary mu-plugin on the hub recording gettext calls
before init, with backtraces, found 91 on a request, all in those files.

Why sb_tweaks_boot stays on plugins_loaded: migrate has to run before 'auto'
decides which modules are on; retire_old_plugins() switches the standalone
plugins off, front end included, and the running list has to be read while
they load; Image Management registers its sizes on after_setup_theme; Bricks
Tweaks hooks init 0 (ACF sorting) and init 11 (elements). Booting later would
move all of that.

What changed (0.3.2, on the hub until it is published):

- The words in module.php (description, each group's title and description)
  and feature.php (title, description, each setting's label and description,
  the admin page's title and description) are plain English strings. The
  __( '...', 'sb-tweaks' ) wrappers were taken off by a tokenizer script that
  only touched file level calls of exactly that shape.
- SB_Tweaks_Modules::translate_words(), hooked on init at priority 0 at the
  end of boot(), translates exactly those keys with translate( $text,
  'sb-tweaks' ) in every copy the framework keeps: the module list, each
  Features loader (its declaration, every feature, the switched on copies) and
  each Screen. translate_module() and translate_feature() hold the key lists.
- An unavailable callable may hand back a function that writes the sentence.
  SB_Tweaks_Features::unavailable() calls it from init on; before init it
  answers 'unavailable', since boot only asks whether there is a reason. The
  two callables above do this now.
- Callables that run later (features, an admin page's render) still translate
  as usual.

Why plain strings rather than functions for the words: features are sorted by
title in discover(), and that order is also the order they boot in, so the
titles have to exist before init. As plain strings they sort exactly as
before (the plugin ships no translations, so the translated title always was
the English), and nothing that reads a declaration ever meets anything but a
string. The cost: wp i18n make-pot no longer finds these strings, as they are
not literal __() calls. Nothing builds a .pot for this plugin today; if that
is ever wanted, list them in a file of __() calls that never runs.

The rule from now on: no __(), _e(), esc_html__() or the rest in module.php or
feature.php, or in anything that runs before init. Write the words as plain
strings, or hand back a function.

The acf domain: Epoxy logged the same notice for acf the same number of
times. SB Tweaks does not cause it. Every ACF call in the plugin
(acf_get_field_groups, acf_get_fields, get_field, acf_add_local_field_group
and the rest) is in a callback that runs on init or later: acf/init, wp_head,
admin_head, Bricks' control options (built on init), Bricks condition lists
and render filters. The hub probe recorded no acf translations before init.
The counts match because the notice is logged once per domain per request.
What triggers it on Epoxy was not looked into.

Checked:

- php -l on every changed file, locally (PHP 8.3) and on the hub (8.3.35).
  PHPCompatibility (develop branch, PHPCS 4.0.4) with testVersion 8.2-8.4:
  nothing on the changed files.
- A harness loading all 38 declaration files, the old ones with __() marking
  each string and the new ones through translate_module() and
  translate_feature(): identical arrays, the same strings translated (113, or
  115 with WooCommerce and Rank Math present).
- On the hub, fresh requests before and after: 91 sb-tweaks translations
  before init and one notice before, none after, front end and admin-ajax. A
  snapshot of every hook SB Tweaks registers in order, module and feature
  state and order, unavailable reasons, every module and group page drawn as
  an administrator, the menus and the admin bar: identical, apart from the
  new init hook and the version badge while the file briefly said 0.3.2.
- Require Login's callable through the new unavailable() with guest checkout
  on: nothing translated before init, the same sentence after.
- WP_DEBUG and WP_DEBUG_LOG switched on in the hub's wp-config for a few
  seconds (wp-config put back byte for byte, a copy kept in /tmp). A keyed
  canary request translating in sb-tweaks on plugins_loaded logged the notice
  once, proving the log catches it; the front end, admin-ajax, REST, the
  Modules, Site Kit, Bricks Tweaks, SB Image Settings, SB Image Cleaner,
  Plugins and Dashboard pages then logged none.

The version: the main file stays at 0.3.1 until publishing, which bumps it to
0.3.2 (the Publishing page and the Installs page both do). Bumping it by hand
first makes the Publishing page refuse 0.3.2, the Installs page publish
0.3.3, and every site look behind on a version GitHub does not have.

## PHP 8.0 or later (October 2026)

Sam's call, in the same release as the fix above: SocialBUMP Tweaks is for PHP
8.0 and later only, and must not install or activate on anything older. Every
site reporting to the Installs page was on 8.2 or later at the time (Epoxy on
8.4).

- Requires PHP is 8.0 in the main file's header and in readme.txt. WordPress
  itself then refuses to install, update or activate the plugin on older PHP,
  the update checker's details included.
- A guard at the top of socialbump-tweaks.php, before the reporter and
  everything else: below PHP 8.0 it shows an admin notice and returns, so a
  site already running the plugin that is moved onto older PHP never loads
  the rest. Everything above that return has to stay parseable by old PHP
  (PHPCompatibility 7.0-8.4 is clean on the main file).
- class-sb-images-rebuild.php (10) and class-sb-images-orphans.php (1) had
  parameters like array $meta = null, which PHP 8.4 deprecates as implicitly
  nullable, so a PHP 8.4 site with WP_DEBUG on (Epoxy) logged a notice for
  each once the files loaded. They are ?array $meta = null now, the same
  meaning on every version.
- woo-analytics-csv passes html_entity_decode() its flags (ENT_QUOTES |
  ENT_SUBSTITUTE | ENT_HTML401), the default since PHP 8.1, so PHP 8.0 decodes
  the default date range exactly as every live site does.
- PHPCompatibility on the whole plugin (vendor aside) with testVersion
  8.0-8.4: clean. Checked on the hub: WordPress reads Requires PHP 8.0, the
  Modules, SB Image Settings, SB Image Cleaner and Plugins pages and the front
  end load, and the rebuild calls work with the meta left out.

## Download plugin zip (hub only)

The Publishing page's Download plugin zip card has two buttons.
Download <version> fetches the zip attached to the latest GitHub release
(SB_Tweaks_Release::published_zip()), so it is exactly what the sites update
from. Download test build zips the hub's files as they are now, with the main
file inside the zip renamed to the next patch version plus -beta (build_zip()
with a version, test_version()); the hub's own files are not touched. It shows
only while there are pending change notes. version_compare() puts 0.3.3-beta
after 0.3.2 and before 0.3.3, so a site running a test build still takes the
real release as an update. Never hand out a test build under a plain release
number: a site would then think it is up to date.

## Next version number (hub only)

New version number on the Publishing page saves itself when the box loses
focus (wp_ajax_sb_tweaks_save_next_version, option sb_tweaks_next_version), so
a number such as 0.5.0 or 1.0.0 stays put between visits. It has to be higher
than the hub's version. Reset, inside the box, shows only while the number
differs from the automatic next patch, and clears the saved one. The test build
takes its name from the same number (1.0.0-beta). Publishing clears it, and a
saved number no longer ahead of the hub's version is ignored.

## Modules a site cannot use are hidden

On client sites the Modules page leaves out any module whose required plugin is
missing (Bricks Tweaks without Bricks, Woo Tweaks without WooCommerce), without
a word about it. The hub (sb_tweaks_is_hub()) shows every module, greyed out
with what it needs. save_modules() already keeps a missing module's saved switch
as it was, so hiding the card loses nothing, and the card returns once the
plugin is active. A module that failed to load always shows, with its error.

A module the hub hasn't allowed a site goes further: SB_Tweaks_Modules
discover() skips it (`if ( class_exists( 'SB_Tweaks_Policy' ) && !
SB_Tweaks_Policy::allows( $def['id'] ) ) continue;`), so there is no card,
menu, admin bar row or code running, on the hub too if it were ever not
allowed (it always is). Its front-end pages go with it: Sam turned Email
Signatures off on gimage.com.au and its front-end team pages
redirected.

## Module controls from the hub (October 2026)

includes/class-sb-tweaks-policy.php (SB_Tweaks_Policy), booted in
socialbump-tweaks.php before SB_Tweaks_UI. The hub decides which modules each
client site may use and, for modules that have them, its limits. A module a
site is allowed, the site still switches on and off itself on its Modules
page.

- Defaults until the hub sends anything: every module allowed except those in
  OFF_BY_DEFAULT (email_signatures). LIMITS holds each module's limits:
  Email Signatures companies 1, people 5, templates 2. LIMIT_LABELS names
  them for the popups and cards (People, not Active people: the limit counts
  everyone, trashed people too). 0 means no limit.
- allows( id ), limits( id ), limit( id, key ). The hub (sb_tweaks_is_hub())
  is always allowed everything with no limits.
- The site keeps what the hub sent in the option sb_tweaks_policy: modules
  (id to bool), limits (id to key to int), set (time). clean() keeps only
  whole numbers 0 to 100000 for keys in LIMITS.
- Route POST /wp-json/socialbump/v1/policy (some hosts need
  ?rest_route=/socialbump/v1/policy). receive() checks it the way a pushed
  update is checked: sodium signature against SocialBUMP_Reporter::PUSH_KEY,
  action policy, host matches this site, not expired, nonce used once
  (transient socialbump_push_<nonce>). Then it saves and fires
  do_action( 'sb_tweaks/policy' ).
- On the hub, SB_Tweaks_Push::send_policy( host, policy ) signs
  { action: policy, host, policy, expires, nonce } with SB_Tweaks_Push::secret()
  and posts it. What was sent is kept per host in the option
  sb_tweaks_policies (policy plus sent time); policy_for( host ) reads it
  back for the popup. AJAX: wp_ajax_sb_tweaks_policy (nonce sb_tweaks_push,
  manage_options).
- A module applies its own limits; for Email Signatures see the Limits
  section of its docs (count pills, greyed Add buttons, the newest over a
  limit going off).

Testing on the hub without touching a client site: in one execute-php
request, define( 'SB_TWEAKS_IS_HUB', false ) before anything reads it, then
flush whatever the module caches (SB_EmailSig_Templates::flush()) and read
the counts. Read only; nothing is saved that way.

## Betas (October 2026)

A beta is a test build sent only to chosen sites. Release beta on the
Publishing page (release_beta()) puts this site's files on GitHub as a
pre-release named the next beta, 0.5.0-beta.1, -beta.2 and so on
(next_beta(); version_compare() puts each after the last and before 0.5.0).
Nothing is pushed to the repo's Code tab and the hub's own files and version
don't change; only the zip carries the beta number. The beta before it is
deleted from GitHub (release and tag). The latest is kept in the option
sb_tweaks_beta (version, id, tag, at). Sites checking for updates never see
it: pre-releases aren't offered.

On the Installs page the beta number shows under the SocialBUMP Tweaks
heading, a site behind it gets Push beta (Update beta once it is on one), and
Update beta sites sends the latest to every site already on a beta. The push
is a signed job like any other (SB_Tweaks_Push, job beta), and needs reporter
1.3.0 or later (NEEDS_BETA, can_beta()), which takes a SocialBUMP Tweaks zip
only from its own GitHub release. Beta versions show with is-beta on their
pill.

When a release at or past the beta's version goes out, retire_beta() deletes
the pre-release and clears sb_tweaks_beta, and the beta sites update to the
release like everyone else. Sam publishes betas himself.

## Two-column pages (October 2026)

Rules for a module page laid out in two columns, from SB Image Cleaner, where
Rebuild Thumbnails sat 24px below Sizes and the columns never stacked on a
phone:

- Give every .sb-tweaks-section that is a column, or sits in one, margin 0.
  The framework's .sb-tweaks-section has a 24px top margin for sections stacked
  down a page; inside a grid it pushes that column down.
- Stack the columns at 1100px with grid-template-columns: minmax(0, 1fr), so a
  column is never squeezed narrower than its content. Publishing stacks at
  960px, as its cards are narrower.
- Put notices above the grid, never in it, or they take a grid cell.

Pages that follow this: SB Image Settings (.sb-images-settings), SB Image
Cleaner (.sb-images-cleaner), Site Kit's Robots.txt (.sb-site-kit-robots) and
Publishing. Card and field grids use repeat(auto-fit or auto-fill, minmax(...))
and wrap by themselves.

## Image Management changes, October 2026

Found on feelsoma.com and fixed in the module; the detail is in
modules/images/docs/context.md:

- The scan reads image ids straight from the database, so a plugin hiding
  attachments in the admin (YITH My Account's avatars) cannot shrink it
  (Scans that disagree).
- Sizes registered on the module's pages are remembered and stay wanted in any
  request, so a size a plugin only registers in the admin is never a leftover
  (Leftover sizes from other sources).
- SB Image Cleaner groups sizes by the plugin, theme or snippet that registers
  them, labels Elementor custom sizes and warns when a widget still uses one
  (Size sources).
- The size migration prompt covers all of the above.

## Standalone Site Kit retired (October 2026)

Sam, 27 October 2026: no site was left on the standalone SocialBUMP Site Kit
plugin (the Installs page listed it only on the hub, switched off), so it went
from everywhere on the hub:

- The plugin folder socialbump-site-kit was deleted from the hub (copy in
  /tmp as sbx-<stamp>-hub-socialbump-site-kit.tar.gz). Its GitHub repo was left
  alone. Its sbsk_ options on the hub were left too; nothing reads them.
- The Installs page no longer has a Site Kit column or hub row
  (SB_Tweaks_Installs::LABELS, PAGES, RELEASES), Publish from the Installs page
  no longer knows it (SB_Tweaks_Hub_Publish::PLUGINS), and the reporter in SB
  Tweaks and SEO for AI no longer reports it (SocialBUMP_Reporter::PLUGINS).
  The hub's stored report row lost its Site Kit entry.
- The Site Kit module no longer 'replaces' the plugin, Image Management no
  longer 'waits_for' it, and the hub-only waits in SB_Images_Module::migrate()
  and SB_Woo_Tweaks::migrate() are gone with their SITE_KIT constants.

Kept on purpose: the two migrate() routines, which copy from the Site Kit
module's own sb_tweaks_site_kit_* options rather than the plugin's; the
old_addresses() redirect for bookmarked sb-site-kit-images pages; the FAQ
fields' ACF keys (field_sbsk_faq_, group_sbsk_faq_), which saved posts use; and
the module docs' standalone-notes.md and changes.md, the plugin's history.
socialbump-bricks-tweaks is still in the reporter's list and the framework's
'replaces' handling stays for Bricks and Elementor Tweaks.

## Email Signatures (October 2026)

Staff email signatures, built for The Cosmetic Studio Noosa to replace
MySignature. modules/email-signatures, id email_signatures, its own SB Email
Signatures menu (Signatures, People, Add Person, Reports, Templates,
Settings, Documentation; no Features page since 28 October 2026, see Modules
of pages below). Signatures, the front door, lists everyone as cards and links to a
private team page on the website. It requires acf: the person fields and the Settings page are
ACF, registered in PHP. Templates are folders in the module's templates/,
Classic first; the Templates page previews each and picks the one the site
uses. It was written as a standalone plugin first but never installed
anywhere, so it has no replaces and nothing to migrate. It went onto the hub
28 October 2026 after SocialBUMP Tweaks 0.4.3 was published, switched on
there, with a Test Person and the client's company details on its Settings
page for testing. The module's notes cover how a signature is made, what
email clients force on it, and how to make a template (its Making a template
section: the folder, styles.php groups, what template.php is given and the
rules every template keeps).

Getting a new module's files onto the hub: a zip copied across by hand
through a tool call came out with a different checksum, so the files went up
one by one through execute-php as nowdoc strings, each checked against the
local md5, with Cropper.js and the Simple Icons SVGs downloaded by the hub
itself and checked the same way. module.php went up last, under
module.php.test, and was test loaded before being renamed into place.

Changes since then went up as a staged copy that is checked (every file's
md5 and php -l) and then swapped in: the module's Working on this module
section has the steps. The release zip skips only .git, .github,
node_modules and .DS_Store, so nothing else may be left in the plugin
folder. Backups go only in wp-content/uploads/sbx-backups, the newest two
per prefix kept (checked 31 October 2026: no sbx-prev folders or .bak files
left in the plugin).

From 0.5.0 it is off on client sites until the hub allows it, with limits
set from the hub (see Module controls from the hub). Anyone without a company
is put in the default company (adopt_one() on save, adopt_all() on an admin page load,
at most every 12 hours),
which fixed a person made before companies existed on gimage.com.au.

## Modules of pages (October 2026)

Some modules are a few pages rather than a set of features to switch on and
off. Email Signatures is the first. Two keys in the module declaration, both
read by SB_Tweaks_Screen and ignored by every other module:

- 'menu' => [ 'features' => false ]: no Features page. Every group is on
  (ordered_groups() returns them all, in the order module.php declares them),
  so each group's page is always there. The top menu item has no page of its
  own: its address redirects to the front door (to_front_door() on
  load-toplevel_page_<slug>), which is the first item in the nav.
- 'nav' => [ admin address => title ]: WordPress screens listed in the tab
  bar and admin bar before the module's own pages, such as
  'edit.php?post_type=sb_emailsig' => 'People'. page_url() returns any slug
  holding .php as admin_url( slug ), and current_item() marks one current
  while a screen of that post type is open. A slug without .php is one of
  the module's own pages (admin.php?page=): listing a group's page here puts
  it before the WordPress screens, and so makes it the front door, since a
  group already in the list keeps its place. Email Signatures lists
  sb-email-signatures-signatures first that way.

Only a module with its own menu can use it (pages_only() needs parent() to
be ''). When the change went onto the hub on 28 October 2026, every other
module's slug, front door, page address and tab bar were compared before and
after in fresh requests and came out identical.

The module's side menu order is its own job (SB_EmailSig_Module::order_menu()
at admin_menu 999). On the hub, Admin and Site Enhancements' Admin Menu
Organiser keeps a saved submenu order (custom_submenus_order in
admin_site_enhancements_extra) and applies it after that, so a module's
submenu can show in an older order there until the organiser is saved again.

## SocialBUMP UI (October 2026)

One look for every SocialBUMP page, independent of the user's admin colour
scheme. The reference for how each part should look is the UI kit artifact
(Sam has the link). Files:

- includes/framework/class-sb-tweaks-ui.php (SB_Tweaks_UI). boot() adds the
  body classes and the wp_ajax_sb_tweaks_ui_theme handler. enqueue( $dark_ready )
  loads assets/css/ui.css after admin.css and assets/js/ui.js. mode_switch()
  prints the Auto, Light and Dark switch; both banners (SB_Tweaks_Screen and
  SB_Tweaks_Settings) print it at the end of the brand row.
- assets/css/ui.css. Everything is scoped to body.sb-ui and every colour is a
  --sbui- token (light on body.sb-ui, dark on .sb-ui-mode-dark and on
  .sb-ui-mode-auto inside prefers-color-scheme: dark). Font is Outfit from
  assets/fonts, as "SB Outfit", at 13px. It restyles the framework's own
  markup (banner, sections, cards, switches, .button family, fields, ticks,
  popups, the unsaved pill) by class, so module pages pick it up without
  changes.
- assets/js/ui.js (window.SBTweaksUI). toast( message, kind, more ) with
  kind ok, info, warn or danger: top right under the admin bar, newest on
  top, danger stays until dismissed. Plain dismissible notices inside
  .sb-tweaks-wrap (only the classes notice, notice-*, is-dismissible, updated,
  error, and no id) become toasts on load; .inline notices and anything with
  other classes or an id (usually another plugin's) stay put. Cards inside a
  form[data-sb-dirty] get .is-edited and an Unsaved chip while their fields
  differ from the page load.

The mode is user meta sb_tweaks_ui_theme (auto, light, dark; default auto).
Dark applies on pages drawn from framework parts (a module's Features page,
its group pages, Documentation, the four SocialBUMP Tweaks pages) and on every
page of a module whose module.php sets 'ui_ready' => true (Email Signatures,
Image Management and Site Kit do). Anything else gets sb-ui-light-only and
stays light. A module is ready once its CSS uses the --sbui- tokens: colours
are written var(--sbui-surface, #fff) and the like, so the same file still
looks right on WordPress screens without body.sb-ui (a person's edit screen).
admin.css, images.css and site-kit.css were converted that way on 29 October
2026. ui.css also styles WordPress tables (.widefat), ACF fields, repeaters,
tabs and switches, select2 and the framework popups, and turns plain Remove
crosses ([aria-label=Remove]) grey with a red hover. SEO for AI is a separate
plugin and has none of this yet.

Card states, with no coloured stripe down the side: on is plain, off (a card
with its own switch and no .is-on) is quieter, edited gets an amber ring and
the Unsaved chip.
Edited is worked out from the card's named fields, leaving out hidden
inputs (ACF keeps plenty) unless one has data-sb-watch, as the Email
Signatures crop box's does.

Tooltips (ui.js, ui.css .sb-ui-tip): data-sb-tip="Copy link" on any element
gives it a small dark label that drops down under it (above it near the
bottom of the window) on hover or keyboard focus, after 120ms. Use it on every
icon button instead of title, which shows the browser's own. One tooltip
element is shared and placed with position: fixed, so it isn't clipped by a
card. Changing data-sb-tip while it shows updates it in place (Copied). An
element with no text and no aria-label gets the tip as its aria-label; the
mode switch uses it too. SBTweaksUI.hideTip() hides it.

Toolbar (.sb-ui-bar): a page's links and actions in one panel above its
cards. .sb-ui-bar__group holds a .sb-ui-bar__label (small capitals) and its
buttons, with a divider between groups; .sb-ui-bar__end goes on the right,
for the page's main button (Add person). .sb-ui-count is a count pill beside
a heading; .sb-ui-empty is a dashed box for nothing here yet.

The unsaved pill (.sb-unsaved, the framework's dirty-form script) sits top
right under the admin bar (Sam, 29 October 2026: where he looks to save),
and the toasts move down under it while it shows (body:has(.sb-unsaved:not([hidden]))). The pill is 46px
tall. A card's Unsaved chip (ui.js) is a button too: it saves the form the
same way (save(): its data-sb-save, else primary, else first submit,
including one outside the form with form=).

Tabs (.sb-ui-seg): a pill row of links between a thing's pages, the current
one marked aria-current=page. .sb-ui-bar__title is a name in a bar.

Page layout (Sam, 29 October 2026): a page's links and actions go in the
bar at the top, its cards straight on the page below, never inside a
panel. Build it with SB_Tweaks_UI::bar( [ [ label, html ], ... ], end html ),
SB_Tweaks_UI::heading( title, count, note ) over the .sb-tweaks-grid and
SB_Tweaks_UI::save_button( form id ) for Save changes in the bar (give the
form an id; keep its own Save at the foot). The Features page of every
module and the Modules page work this way: Cards (Reorder, Collapse all,
Expand all, Collapse disabled) on the left, Save changes on the right.

Patterns added with Email Signatures (31 October 2026). Their styles live in
that module's assets/admin.css for now; move them into ui.css when a second
module needs them.

- Count pill in the bar, left of the Add button: "People 1 of 1", or
  "People 2" with no limit. Grey normally, amber (is-low) with one slot left,
  red (is-full) with none. The count replaces any count by the grid heading;
  no heading over the grid. (.sb-emailsig-limit)
- An Add button that can't be used stays where it is, greyed out, with the
  reason as its tip: "No person slots left. Ask for more to add another." A
  disabled button shows no tip, so a focusable span carries data-sb-tip and
  holds a .button.disabled. Front-end pages do the same. No banners for it.
  (.sb-emailsig-limit-lock)
- A bar under the toolbar and over cards needs space: 28px above the grid.
- Popup header bar: a popup that switches what it shows (the Preview popups)
  gets a coloured header bar with the name on the left and the switcher on
  the far right, the switcher on a white background. A long list in the
  switcher is grouped by company with optgroups, inactive people marked.
- Popup footer with links: plain buttons with their icons (Edit styles, Edit
  Layout) on the far left, Close with an X icon on the far right.
- A popup grows to the window height as needed and only scrolls when it
  can't fit (dialog max-height 100% inside the overlay's padding).
- Card footers sit at the bottom of the card (margin-top: auto), so a row of
  cards lines up whatever their content.
- An icon button for a repeat action (Email) stays an icon; choices go in its
  popup (the Email type dropdown) rather than a second button.
- Several actions in a bar go in one Actions menu (Sam, 31 October 2026):
  a primary button with a chevron and the count it acts on, a menu with small
  capital headings for each part, and items greyed with the reason as their
  tip. (.sb-installs-actions, .sb-installs-menu, styles in the Installs page)
- A filter over cards is a .sb-ui-seg of buttons with aria-pressed and a
  count in each, beside a search box in the bar.