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
- The admin bar rule: with no module on, SB Tweaks stands alone; with any
  module on, one SocialBUMP item (id sb-tweaks-group) holds a row per module
  and SB Tweaks as the last row, and both top items open the Modules page. The
  id differs from the standalone plugins' old socialbump item so the two never
  merge. The standalone plugins' shared bar offers each entry to the
  socialbump/admin_bar/claim filter first; SB_Tweaks_Modules::claim_bar_item()
  takes any whose id (the folder minus socialbump-) a module replaces and
  registers it with SB_Tweaks_Bar, which renders at 210, after the shared bar
  at 200. So on the hub, where standalone Bricks Tweaks runs for publishing, it
  sits in the SocialBUMP item like the module. Unclaimed standalone plugins
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
| assets/js/progress.js | the progress popup, SBTweaksProgress (see Popups) |
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
(NOTICE_PREFIX + user id), read and cleared. Prefixes: sbsk, sbbt, sbaike,
sb_tweaks. Tested by asking Site Kit's handler for its current version, which it
refused before touching GitHub, with the redirect caught and the message read
back.

Sites table: a tick box (and select all) on each site with updates waiting,
an Update all button in a narrow column before the remove column, and a
tfoot row with Update all (everything on the ticked sites) and an Update
under each plugin column (that plugin only on the ticked sites, enabled only
when a ticked site is behind on it). Both run the site's waiting updates one at a time in the
framework's progress popup (SBTweaksProgress, see Popups). A single Update button, stacked under its version, stays inline.
Keep explanatory copy on these pages to a minimum; Sam knows what the buttons do.

## Popups

Two kinds, both in the framework, both looking like SEO for AI's.

### Progress popup (assets/js/progress.js, window.SBTweaksProgress)

For anything that takes more than a moment: publishing, pushing updates to
sites, rebuilds, bulk changes. Loaded on every SocialBUMP Tweaks and module page
alongside framework.js, so a module never ships its own.

    var job = SBTweaksProgress.open( 'Publishing' );
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

### Confirm popup (the waiting changes on the Installs page)

For looking at something before acting on it. The same overlay and box (10px
radius, 24px padding), the title top left, an X to close in the top right,
Cancel on the left and the action on the right along the bottom, Escape and a
click outside close it. Its action hands straight over to a progress popup.
Markup and styles: .sb-hub__modal and .sb-hub__dialog in the Installs page.
