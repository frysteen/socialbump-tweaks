# SocialBUMP Tweaks

Notes for whoever picks this up next, most likely a new chat with no memory of
how any of it came about. Read this first.

**Keep it current.** Change how something works, add a feature, or learn
something painful, and write it here in the same session. A note that is wrong
is worse than no note, so fix anything you find that has gone stale.

## What it is

One plugin holding what used to be separate SocialBUMP plugins, each now a
module that can be switched on and off. Bricks Tweaks moved in first and is
done (October 2026); Site Kit is next, and an Elementor Tweaks module is
planned. SEO for AI is not part of this: it is standalone and shares nothing with it.

Naming, because the old plugins used these words differently and it matters:

- A **module** is what used to be a plugin. It has its own top level menu, its
  own pages, and a switch on the Modules page here.
- A **group** is a page inside a module.
- A **feature** is one thing inside a group. What each old plugin called its
  Modules page is a Features page now.

## Where it is up to

As of 16 October 2026, version 0.1.11:

- One module, Bricks Tweaks (modules/bricks), with its own notes in
  modules/bricks/docs/context.md. This plugin runs on bricks.socialbump.com.au,
  hintercoasttransport.com.au, thecosmeticstudionoosa.com.au and
  drivingevents.com.au, plus the hub. The last two moved over from standalone
  Bricks Tweaks on 16 October, after which no site reported the standalone
  plugin and it was deleted from the hub (see Bricks Tweaks retired, below).
- Site Kit is still standalone on the sites: 1.2.6, active on 11 sites
  including the hub. Its modules are built on the hub, not yet released: Site
  Kit (modules/site-kit) and Image Management (modules/images), each with its
  own notes. Both wait while standalone Site Kit is active. Standalone Site Kit
  was switched off on the hub on 16 October and both modules were tested there:
  every page, the settings copy, the registered sizes against the backup, a
  cleaner scan, the sizes panel, robots.txt, FAQ schema and the old addresses.
  See Site Kit and Image Management, below.
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
| assets/js/framework.js | swatches, hide-when fields, switches, select all and none |
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
- Building PHP through a JSON tool call mangles double quotes. Build markup by
  concatenation with chr( 34 ), which is why the code here reads that way.
- A shared file that drifted took two client sites down. Nothing here is shared
  with anything, and that is worth keeping.
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
(NOTICE_PREFIX + user id), read and cleared. Prefixes: sbsk, sbaike,
sb_tweaks (sbbt went with standalone Bricks Tweaks). Tested by asking Site Kit's handler for its current version, which it
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
Converted in October 2026: Publish release and Remove token on Publishing,
Import settings on Updates, and the remove site cross on the Installs page.
SEO for AI has its own copy, SBAIKEConfirm (data-sbaike-confirm), as it does
of the progress popup.

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
