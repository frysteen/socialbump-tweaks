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
- A **feature** is one thing inside a module. What each old plugin called its
  Modules page is a Features page now.

## Where it is up to

The front door only. The Modules page is deliberately empty and Publishing
works, so the plugin can reach GitHub before anything is migrated into it.

Not built yet, in the order they are expected:

- The module framework: registering, switching, per module menus.
- The shared admin bar item. One module on shows that module by name; two or
  more show a combined SocialBUMP item with a row each, including this plugin.
- The Updates page, which is also where settings export and import live, with
  tick boxes for what to export and an import that touches only what is in the
  file.
- The converter that reads the old sbsk_ and sbbt_ settings and writes them
  under sbtweaks_. Written so it can be lifted out once every site is across.
- The release notes archive, pulled once from the two old repos.

## How it is built

| File | What it is |
| --- | --- |
| socialbump-tweaks.php | constants, updater, hub check, sbtweaks_log_change(), loads everything |
| includes/class-sbtweaks-settings.php | menu, banner, the Modules page, Publishing |
| includes/class-sbtweaks-release.php | publishing to GitHub, hub only |
| includes/class-sbtweaks-docs.php | these notes, and the panel on Publishing |
| assets/css/admin.css | everything the admin pages look like |
| assets/js/save-state.js | the unsaved changes reminder |
| vendor/plugin-update-checker | the updater library, left alone |

The prefix is SBTWEAKS_ and sbtweaks_ throughout, the text domain is sb-tweaks,
the page slug is sb-tweaks, and the repo is frysteen/socialbump-tweaks. The
stylesheet and the release and notes code came across from Site Kit and SEO for
AI with the prefix changed, so they behave exactly as they do there.

The version badge in the banner is plain text rather than a link, because there
is no Updates page yet. Give it its link back when that page arrives.

## Where to be careful

- Everything is developed on the hub, bricks.socialbump.com.au, through its
  Novamira MCP connector. Lint every PHP file before writing it, and verify a
  change in a fresh request rather than the one that wrote the file.
- Building PHP through a JSON tool call mangles double quotes. Build markup by
  concatenation with chr( 34 ), which is why the code here reads that way.
- This plugin will eventually carry copies of files the old plugins share. Until
  it does, nothing here is shared with anything, and that is worth keeping for
  as long as possible: a shared file that drifted took two client sites down.
