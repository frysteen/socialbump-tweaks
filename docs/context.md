# SocialBUMP Tweaks

Notes for whoever picks this up next, most likely a new chat with no memory of
how any of it came about. Read this first.

**Keep it current.** Change how something works, add a feature, or learn
something painful, and write it here in the same session. A note that is wrong
is worse than no note, so fix anything you find that has gone stale.

## What it is

One plugin holding what used to be separate SocialBUMP plugins, each now a
module that can be switched on and off. Site Kit and Bricks Tweaks are being
moved across one at a time, and an Elementor Tweaks module is planned. SEO for
AI is not part of this: it is standalone and shares nothing with it.

Naming, because the old plugins used these words differently and it matters:

- A **module** is what used to be a plugin. It has its own top level menu, its
  own pages, and a switch on the Modules page here.
- A **group** is a page inside a module.
- A **feature** is one thing inside a group. What each old plugin called its
  Modules page is a Features page now.

## Where it is up to

The front door, the admin footer credit and the whole module framework. The
Modules page draws real cards now, and falls back to its empty note until the
first module is migrated in, because the modules folder does not exist yet.

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
- The admin bar counting rule: with no modules on, Tweaks stands alone; with
  exactly one on, that module's menu stands alone and Tweaks stays out of the
  way; with two or more, everything shares the combined SocialBUMP menu.

## The framework

A module is a folder in modules/ holding a module.php that returns its
declaration: id, title, description, requires, replaces, menu (slug, label,
page_title, after), bar (id, label), heading, prompt and boot. Folder names
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
- Dependencies: acf (satisfied by Bricks Advanced Themer's bundle), bricks
  (BRICKS_VERSION) and woocommerce, filterable through sb_tweaks/dependencies.
- Lockout: a module's replaces basename is deactivated on every admin_init
  while the module is on, with an admin notice saying so, so the old plugin
  cannot run beside it.
- Bricks element registration belongs to the Bricks module, not the
  framework. The Bar port dropped Site Kit's unused accent helpers.
- assets/js/framework.js holds the shared page behaviours: colour swatch
  sync, hide-when fields, the card switch is-on class, and the select all and
  none links (data-sb-tweaks-check).

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

So this plugin deactivates the old one and stops it being switched on again.
Run the check on every admin load, not only on activation: an activation hook
does not fire when a plugin is updated.

### Names that cannot change

The standalone plugins were cleaned up before the merge, so most identifiers
are free. These are not:

- Bricks condition keys sb_bricks_*, the element name
  sb-bricks-image-carousel, the loop settings sbBricks*, and the gallery query
  type sb_bricks_gallery_. All written into pages.
- faq_questions, faq_question, faq_answer. Sites hold content under them.
- The registered image size names, image-240 through image-1920. Written into
  every attachment's metadata and into Bricks element settings.
- The shortcode tag social_bump_credit, which is a snippet rather than ours,
  but is placed by hand in templates.

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
| includes/framework/ | the seven classes the modules run on |
| assets/js/framework.js | swatches, hide-when fields, switches, select all and none |
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

The version badge in the banner is plain text rather than a link, because there
is no Updates page yet. That page arrives with the first module, because the
settings export and import live on it.

## Where to be careful

- Everything is developed on the hub, plugins.socialbump.com.au, through its
  Novamira MCP connector. Lint every PHP file before writing it, and verify a
  change in a fresh request rather than the one that wrote the file.
- Building PHP through a JSON tool call mangles double quotes. Build markup by
  concatenation with chr( 34 ), which is why the code here reads that way.
- A shared file that drifted took two client sites down. Nothing here is shared
  with anything, and that is worth keeping.

## Hub reporter

includes/class-socialbump-reporter.php is shared by every SocialBUMP plugin (Site
Kit, Bricks Tweaks, SEO for AI, SocialBUMP Tweaks): the same file in each, kept
identical like the shared admin bar, and guarded by class_exists so whichever
loads first runs. It is required from the plugin's main file at the top level,
not on plugins_loaded, so it is already listening when a plugin is activated.

It reports every tracked plugin on the site at once, active or not, since a
deactivated plugin cannot speak for itself: site URL and name, each plugin's
version and active state, WordPress and PHP versions. Nothing else. It posts to
https://plugins.socialbump.com.au/wp-json/sb-tweaks/v1/checkin with the shared
X-SB-Key header, non-blocking with a 3 second timeout, so it never slows a page.
It sends on activated_plugin and deactivated_plugin for one of ours, on an admin
page load when the plugin list changed or a day has passed (option
socialbump_reporter_last holds the time and a hash), and from the daily cron
event socialbump_reporter_daily for sites nobody logs into. upgrader_process_
complete clears the last report so the next admin load sends the new version.
On the hub it calls sb_tweaks_installs_record() directly instead of over HTTP.

To track another plugin, add its folder to SocialBUMP_Reporter::PLUGINS in every
copy and a label to SB_Tweaks_Installs::LABELS, and bump the reporter VERSION.

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

The Installs tab (sb-tweaks-installs, between Modules and Publishing) shows a row
per site and a column per plugin. The hub's own copy of each plugin is taken as
the latest release, so a lower version shows amber, a deactivated one grey, and
a site silent for over three days is tinted red (a deleted plugin, or a site that
is down, cannot report). Active under a version links to that plugin's main page on the site (SB_Tweaks_Installs::PAGES, built from the reported home address plus /wp-admin/).
The cross on each row removes it; it returns if the site
checks in again. Later the check-in reply is the natural place to send a site
instructions, such as switching features off on a site that has moved away.

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
