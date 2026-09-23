# Standalone SocialBUMP Bricks Tweaks notes (reference)

These are the notes of the standalone plugin as they stood at 1.1.6, the last
version before it moved into SocialBUMP Tweaks. They are kept for the detail on
how each feature works and why. Where they name code, read SBBT_ as SB_Bricks_,
sbbt/ hooks as sb_bricks/, class-sbbt- files as class-sb-bricks-, and a
modules/<slug>/module.php as features/<slug>/feature.php. Anything about
publishing, Updates, Transfer, the shared files or the release process no longer
applies: SocialBUMP Tweaks does those. The current notes are context.md.

---

# SocialBUMP Bricks Tweaks

Notes for whoever picks this up next, most likely a new chat with no memory of
how any of it came about. Read this first.

**Keep it current.** Change how something works, add a feature, or learn
something painful, and write it here in the same session. A note that is wrong is
worse than no note, so fix anything you find that has gone stale.

That means all of it, not just the overview. Add a module and it gets its own
entry under the detailed list, describing what it does, how it does it, and what
it can be set to. Change how a setting behaves and the entry for that setting
changes with it. Add a setting to an existing module and add it to that entry.
The detail is the point: an overview that says a module exists helps nobody who
has to change it.

## What it does

Bricks specific work: custom elements, element conditions, and tweaks to how
Bricks behaves. It refuses to run without the Bricks theme and says so. Anything
useful on a site without Bricks belongs in Site Kit instead.

## How it is organised

Everything is a module and every module can be switched off. A module that is off
is never loaded.

| Group | What is in it |
| --- | --- |
| Conditions | ACF Relationship, ACF Repeater, Bricks Content, Post Type, WooCommerce Archive Display |
| Extras | ACF Gallery Loop, ACF Loop Sorting, Default To WP Editor, Gutenberg Block Styles |
| Elements | Image Carousel |

The shared block below still calls this the Modules page, because Site Kit's
is still called that. Bricks Tweaks renamed its own in September 2026, ready for
the merge, where Modules means what these plugins become.

Each of those groups is a card on the Features page, the same arrangement Site
Kit uses, and each has a page of its own. A group can be switched off as a
whole, which takes its page out of the menu, the tabs and the admin bar, and
stops every module inside it from loading. The order the cards are dragged into
is the order those three follow.

Where a group holds a single module with a page of its own, the group page is
that page: there is no second menu entry for it.

The pieces are group_states(), group_enabled(), in_group() and group_needs() on
SBBT_Modules, and ordered_groups(), render_groups(), render_group(),
render_card(), save_groups() and bar_items() on SBBT_Settings. All of it came
across from Site Kit and should stay identical to it, prefix aside. When one
changes, change the other: bar_items() was missed in the port and the tab bar
listed no groups at all until someone noticed, and save() was ported without
the two lines that keep one group's page from wiping the others.

Saving on a group page posts sbbt_group and comes back to that page. Saving on
the Features page stays there.

## Conditions

Bricks lets an element be shown or hidden by a condition. SBBT_Conditions is the
shared engine behind all of ours: a module supplies a condition.php describing the
condition and how to answer it, and registers it with SBBT_Conditions::register().

- A condition about one post is answered against that post.
- A condition about the page rather than a post sets needs_post to false. That is
  how WooCommerce Archive Display works on a shop archive, where there is no post
  to ask about.

SBBT_Conditions stands down, with a notice, if the old WP CodeBox Bricks Toolkit
snippet is still defining the same helper. One or the other, never both.

## What each module does

Default says whether a fresh install has it on.

| Module | Needs | Default | What it does |
| --- | --- | --- | --- |
| ACF Relationship | ACF | off | Show or hide an element depending on whether a relationship or post object field has anything in it |
| ACF Repeater | ACF | off | Show or hide depending on whether a repeater has rows. The repeater is picked from a list |
| Bricks Content | | off | Show or hide depending on whether the post was built with Bricks. Useful for falling back to normal WordPress content |
| Post Type | | off | Show or hide depending on the post type, so one template can serve Pages, Posts and custom types |
| WooCommerce Archive Display | WooCommerce | off | Show or hide depending on whether the shop or category archive lists products, categories, or both |
| ACF Gallery Loop | ACF | off | Adds each ACF gallery field to the query loop Type list, so you can loop its images and build the markup yourself. Any return format, with ordering options |
| ACF Loop Sorting | ACF | off | An Order setting on ACF query loops: reversed, sorted or random. What ACF has saved never changes |
| Default To WP Editor | | off | Opens the WordPress editor rather than the Bricks tab on posts with no Bricks content. The Bricks tab is still one click away |
| Gutenberg Block Styles | | on | Puts back the block editor styles Bricks strips, on pages whose content has blocks, and only for the blocks used. Also gives the gallery block its Block spacing setting |
| Image Carousel | | on | An SEO friendly carousel: real img tags with alt text, drag ordering, ACF gallery support, breakpoint controls, lightbox and continuous scroll |

The WooCommerce Archive Display condition is the one to read first if you are
writing a new condition: it is the only one that answers a question about the page
rather than a post, so it shows what needs_post false is for.

## Every module in detail

What it does, how it does it, and what it can be set to. The hook named is the
one to look at first when something misbehaves.

### Conditions

All five are built the same way: a condition.php returning key, label, compare,
value and check, registered through SBBT_Conditions. Bricks shows them in the
element Conditions panel alongside its own. check is the callback that answers
true or false for the element being drawn.

The post a condition asks about comes from SBBT_Conditions::post_id():
get_the_ID(), which follows a Bricks query loop, except on the posts page outside
a loop, where it is the Blog page (page_for_posts). WordPress sets the current post
there to the first post in the list, so a Single Page template on a Bricks-built
Blog page used to answer Bricks Content for that post and show the wrong section
(found on Cosmetic Studio). Conditions are never evaluated in the builder canvas:
Bricks skips them there so hidden elements stay editable, so test on the front end.

**ACF Relationship.** Answers whether a relationship or post object field has
anything in it. The usual use is hiding a Related section when nothing has been
linked, rather than showing an empty heading. Needs ACF.

**ACF Repeater.** Answers whether a repeater has rows, with the repeater picked
from a list rather than typed. Same idea: no rows, no section. Needs ACF.

**Bricks Content.** Answers whether the post was built with Bricks. Useful in a
shared template that has to cope with both: show the Bricks content when there is
some, fall back to the WordPress editor content when there is not.

**Post Type.** Answers on the post type of what is being shown, so one template
can serve Pages, Posts and custom types and vary a header or a meta block.

**WooCommerce Archive Display.** Answers whether the shop or category archive is
set to show products, categories, or both. This is the one that proves the
needs_post false path: on a shop archive there is no post to ask about, so the
condition is about the page. Read this one first if you are writing a condition
that is not about a single post. Needs WooCommerce.

### Extras

**ACF Gallery Loop.** Adds every ACF gallery field to the Bricks query loop Type
list, so a gallery can be looped and the markup built by hand instead of using a
gallery element. It hooks bricks/setup/control_options to offer the fields, then
bricks/query/run to return the images, and bricks/query/loop_object_id and
loop_object_type so dynamic data inside the loop resolves against the image. It
also filters post_thumbnail_id, so a featured image element inside the loop shows
the looped image. Works with any ACF return format, ID, array or URL. Needs ACF.

**ACF Loop Sorting.** Adds an Order setting to ACF query loops through
bricks/query/result: as saved, reversed, sorted or random. It reorders what comes
back rather than what ACF stores, so the order saved in ACF never changes.
Settings: repeater and relationship, each a switch, so you can have it on one
kind of loop and not the other. Needs ACF.

**Default To WP Editor.** On a post with no Bricks content of its own, opens the
WordPress editor rather than the Bricks tab, through admin_enqueue_scripts. The
Bricks tab is still one click away. Handy on a site where most posts are written
normally and only a few are built.

**Gutenberg Block Styles.** On by default. Bricks dequeues wp-block-library and
global-styles on every page it renders (Setup::deregister_styles(), on
wp_print_styles at priority 100, no setting consulted). Right for pages built in
Bricks, wrong for a post whose content is Gutenberg but displays through a Bricks
single template: galleries lose their columns and wrap, images their cropping.
restore() runs on wp_print_styles at 101, straight after, on singular pages whose
content has blocks, and only when Bricks actually removed the library. It walks
the blocks (inner blocks and reusable blocks included) and enqueues each core
block's own stylesheet, the per-block wp-block-<name> handles WordPress already
registers, plus block-library/common.min.css as sbbt-block-common. The layout
rules come from wp_get_global_stylesheet( [ 'styles' ] ) filtered down to rules
whose selector names is-layout-, printed as sbbt-block-layout: on a theme with no
theme.json that part also carries body margin and padding resets, the default
grey button and pullquote sizing, none of which should reach a Bricks site. Asking
for base-layout-styles returns nothing on WordPress 7.1, which is why it is done
this way. The colour and size presets (over 100 KB on the hub, mostly the palette)
are added only when the content uses a has-*-color style class or var:preset.
Pages with no block content are untouched, and it stays out of the builder.

It also switches on Block spacing for the gallery block alone, through the
wp_theme_json_data_theme filter: Bricks ships no theme.json, so the editor
otherwise offers the gallery padding only. Switching the control on is only half
of it: the gallery's own render writes --wp--style--unstable-gallery-gap into its
wp-block-gallery-N rule, which only sizes the images, while the gap property
itself comes from the layout support and is only written when the theme declares
block spacing at the top level of a theme.json. Bricks does not, so the images
shrank for a gap that never appeared. gallery_gap_css() on render_block_core/gallery
adds the gap to that same rule through the style engine (block-supports context),
turning presets like var:preset|spacing|60 into their variable. Declaring block
spacing globally instead would have switched on WordPress's flow spacing rules
too, which would fight the Typography snippet, so it is kept to the gallery.

The gallery's control is the plain one, with link sides and a unit list (px, %,
em, rem, vw, vh), because the filter also sets defaultSpacingSizes false and the
units for core/gallery. With WordPress's generic presets on, the editor draws a
preset slider per axis instead and loses the link toggle, which is how the hub
first looked. The site-wide root settings are untouched. WordPress's custom box
only takes a number and a unit, so a variable cannot be typed there; variables
could only be offered as presets, which would bring the preset control back.

The default gap is the Bricks theme style's Image Gallery spacing
(settings['image-gallery']['gutter'], var(--card-gap) on the hub). default_gap()
reads it through Theme_Styles::get_setting_by_key() on the front end, where Bricks
has already picked the active style, and from the site-wide style (condition
any) in the editor. It is written as --wp--style--gallery-gap-default, the first
thing WordPress's gallery gap chain reads when a gallery has no gap of its own,
so gap and image widths both follow it. A gap set on one gallery still wins.
Only the base value is used, not per-breakpoint ones. editor_styles() on
enqueue_block_assets puts the same rule, and Bricks' own
uploads/bricks/css/global-variables.min.css (pure :root variables), into the
editor, so the editor gap matches the page. The filter
sbbt/gutenberg_styles/default_gallery_gap can change it. This replaces the
gallery gap line that was going to go into the old Gutenberg Blocks snippet.

If a site's galleries still sit in one row with this on, look for custom CSS
forcing flex-wrap: nowrap on figure.wp-block-gallery. Epoxy Flooring Co had a
WPCodeBox snippet doing exactly that, and the old SocialBUMP Gutenberg Blocks
snippet on the hub replaced core's gallery layout with its own, ignoring the
Columns and Aspect ratio settings. Both have been retired in favour of core.

### Elements

**Image Carousel.** A real Bricks element, not a wrapper around a library
shortcode, and the only module here that ships its own CSS and JavaScript. It was
written because most carousels output background images or lazy placeholders that
search engines and AI crawlers cannot read. This one outputs real img tags with
alt text. It takes images from a manual selection or an ACF gallery, has drag
ordering, per breakpoint controls for how many show at once, an optional lightbox
and continuous scroll. On by default, since it is the reason the plugin exists for
most sites.

Bricks caches element registration. After changing an element, regenerate the
Bricks CSS files and reload the builder before deciding something is broken.

## The files, and what each one is for

| File | What it is |
| --- | --- |
| socialbump-bricks-tweaks.php | constants, updater, hub check, sbbt_log_change(), refuses to run without Bricks |
| includes/class-sbbt-settings.php | the Features page, banner, menu, admin bar |
| includes/class-sbbt-modules.php | finds every module and works out what can run |
| includes/class-sbbt-conditions.php | the shared engine behind every element condition |
| includes/class-sbbt-acf-source.php | decides whether ACF is really present, or only the Advanced Themer copy |
| includes/class-sbbt-release.php | publishing, hub only |
| includes/class-sbbt-updates.php | the Updates page |
| includes/class-sbbt-transfer.php | settings export and import |
| includes/class-sbbt-docs.php | these notes and the Publishing panel |
| includes/class-sbbt-convert.php | moves the old sbbt_ options onto the sb_tweaks_bricks_ names, once |

The modules:

| Module | Files |
| --- | --- |
| condition-acf-relationship, -acf-repeater, -bricks-content, -post-type, -woo-archive-display | condition.php and module.php each |
| acf-sorting | class-sbbt-repeater-ordering.php and class-sbbt-relationship-ordering.php |
| gallery-loop | class-sbbt-gallery-loop.php |
| default-wp-editor | class-sbbt-default-wp-editor.php |
| image-carousel | class-element-image-carousel.php plus assets, a real Bricks element |

A condition module is only a condition.php and a module.php. An element module
registers a Bricks element class and ships its own CSS and JavaScript.

## Writing a module

A folder under includes/modules/<slug>/ with a module.php returning an array:
id, title, description, section, default, requires, optional settings and
features, and a boot callback.

- requires names what it needs: acf, bricks, woocommerce.
- ACF supplied only by the copy bundled with Advanced Themer does not count as
  ACF. The check is deliberately stricter than class_exists.
- Write the class file before the module.php that loads it.
- A fatal inside one module is caught, so it cannot take the site down.

## What it stores

| Name | Holds |
| --- | --- |
| sbbt_modules | which modules are on |
| sbbt_module_settings | each module settings |
| sbbt_github_token | encrypted, hub only |
| sbbt_pending_changes | notes for the next release |

## Names, and which ones can never change

Two kinds of name live in this plugin and they are treated in opposite ways.

**Saved in the site's options.** Which features are on, the group switches,
feature settings, the per user card order. These are sb_tweaks_bricks_ names
now: sb_tweaks_bricks_features, _settings and _groups. They were renamed in
September 2026 so the merge into SocialBUMP Tweaks inherits clean data and
needs no converter of its own. SBBT_Convert copies the old sbbt_ options and
the card meta across on first load, backs everything up into
sb_tweaks_bricks_backup, deletes nothing, and records SCHEME so it runs once.
Bump SCHEME if the names ever move again.

**Saved inside the pages themselves.** These are the dangerous ones: Bricks
writes them into every page and template that uses the feature, so renaming
one in code breaks live pages silently, with no error anywhere. They are all
sb_bricks_ now:

| What | Now | Was |
| --- | --- | --- |
| Condition keys | sb_bricks_acf_relationship, _acf_repeater, _bricks_content, _post_type, _woo_archive_display | socialbump_* |
| Carousel element name | sb-bricks-image-carousel | sb-image-carousel |
| Relationship loop order | sbBricksRelationshipOrder | sbbtRelationshipOrder |
| Repeater loop order | sbBricksRepeaterOrder, sbBricksRepeaterOrderField | socialbumpRepeaterOrder, socialbumpRepeaterOrderField |
| Gallery loop | sbBricksGalleryOrder, Offset, Limit | sbbtGallery* |
| Gallery query type | sb_bricks_gallery_<field> | sbbt_gallery_<field> |

The convention, so a new one never has to be guessed at: options, functions
and classes in snake_case; CSS classes hyphenated; Bricks element setting keys
in camelCase, because that is what Bricks itself uses; element names
hyphenated, because the name becomes the brxe- class; condition keys in
snake_case.

### The fallbacks, and when to remove them

Nothing converts page data by itself. Each site is converted by hand, so the
code has to understand both names until that is done everywhere:

- A condition declares 'was' in its condition.php. SBBT_Conditions keeps an
  alias map, so an old key still evaluates, while only the new key is offered
  in the builder. Nobody can pick an old one again.
- The carousel keeps its old name through a second class marked
  deprecated = true, which is Bricks' own way of keeping an element working
  while hiding it from the panel. An unregistered element name renders
  nothing at all, so this one matters most.
- The loop settings and the gallery query type read the new key and fall back
  to the old one.

Do not rely on re-saving a page to convert it. Bricks saves what the builder
has in front of it, and the builder binds controls by key: an old key shows as
an empty setting, so a save can quietly drop the value. Convert the data, then
edit.

Take the fallbacks out once every site has been converted, and only then.

## Where to be careful

- A group page submits only its own modules, so the save must start from what
  is already stored and touch only the modules on that page. It did neither
  when the group pages were first ported: save() walked every module and
  started from an empty array, so saving Extras switched off everything under
  Conditional Logic and the other way round, and the same happened to the
  settings. The fix is the two lines Site Kit already had: pick the modules
  with in_group( $group ) when a group was posted, and seed $states from
  $saved. This is the same partial save bug the shared notes describe, and it
  is now the third plugin to have had it. When porting a page, port its save.

- Bricks caches element registration. After adding an element, regenerate the
  Bricks CSS files and reload the builder before deciding it does not work.
- A condition that returns the wrong shape silently hides everything it touches.
  Test both states in the builder, not just the one you expect.
- This plugin is the natural home for anything that reads the Bricks element
  tree. SEO for AI has its own Bricks module for reading content into the export;
  that one belongs there, not here, because it is about the export.

<!-- shared:start -->

## House rules, shared by all three SocialBUMP plugins

This block is identical in the docs of Site Kit and Bricks Tweaks. Change it in
one and copy it to the other in the same session. They all live on the hub, so that
is a two minute job, and the Publishing page warns you when they have drifted.

### The two plugins

| Plugin | Folder | Prefix | Menu |
| --- | --- | --- | --- |
| SocialBUMP Bricks Tweaks | socialbump-bricks-tweaks | SBBT_ / sbbt_ | SB Bricks Tweaks |
| SocialBUMP Site Kit | socialbump-site-kit | SBSK_ / sbsk_ | SB Site Kit |

SocialBUMP SEO for AI, in socialbump-ai-knowledge-exporter, used to be the third
and is not any more. It was split off in September 2026: its own admin bar item,
its own menu, no card on the Hub page, no part in the Hub's update checking or
its master prompt, and its own copies of everything it used to share. It may go
on copying from these two. Nothing is shared with it, so nothing done here can
reach it, and its notes are its own to change.

Which plugin does a job belong in? Needs the Bricks theme, Bricks Tweaks.
Useful on any site, Site Kit. About what AI crawlers read, SEO for AI.

### Files that are identical in each plugin

- includes/class-socialbump-admin-bar.php
- assets/js/save-state.js

Change one, change both, then check the md5s match. Both are written so that
whichever plugin loads first wins and the other stands aside, so a site running
mixed versions still works.

### The Modules page cards

SocialBUMP_Cards and module-cards.js give a page of cards a chevron to collapse
each to its title (the title toggles too), Collapse all, Expand all and Collapse
disabled links above the grid, and a Reorder Cards button beside the heading, on
the right, that opens a list to drag. It used to sit below the grid, directly
above Save changes, where it was hit by mistake on the way to saving. Order and collapsed state are per user, in user meta, alphabetical
until changed, and saved over AJAX as they change, never through the form. The
saved order is meant to drive the menu, the tab bar and the admin bar as well,
and saving one drops that menu's entry from ASE's submenu order. Both files are
identical wherever they exist.

Putting the page into a plugin takes five things, and it is broken in a quiet
way if any one is missed. Porting it to Bricks Tweaks missed three of them and
the page looked wrong rather than dead, which cost an hour:

1. Copy class-socialbump-cards.php and module-cards.js in. The class guards
   itself with class_exists, but require it behind class_exists as well: an
   opcache entry compiled before that guard existed took a site down.
2. Call register( prefix, menu slug ) at boot. Without it the AJAX endpoint
   never exists, so the arrangement cannot save.
3. Enqueue module-cards.js on the settings pages, handle sb-module-cards.
   Without it nothing collapses or drags at all.
4. Mark up the grid: container_attributes() on it, card_attribute() on each
   card, toolbar() twice, links above and reorder below. A card's first child
   must be its head, and the toolbar key must match the grid's.
5. Copy the CSS. This is the part that hides: the rules are NOT all generic
   .sb- classes in one chunk. Three of them are attribute selectors that a
   search for .sb- will not find, and every one of them matters:

       [data-sb-cards] { align-items: start; }
       [data-sb-card] > :first-child > h3 { flex: 1; }
       [data-sb-card].is-collapsed > :not(:first-child) { display: none; }

   Without the first, cards stretch to the tallest in the row. Without the
   second, the title does not take the space and the chevron and switch sit
   wrong. Without the third, collapsing works and looks like nothing happened.
   The prefixed card rules are needed too: card, card__head, card__desc,
   card__link, switch, features and dot.

To check a port, render the page, pull every class out of the markup and look
each one up in that plugin's stylesheet. That catches the prefixed ones. Then
check the three attribute selectors above by name, because they never appear in
the markup: is-collapsed is added by the JS, and the other two are on elements
whose classes are already there. Eyeballing the page does not catch any of this,
because a missing rule looks like a layout opinion rather than a fault.

### The shared admin bar item

SocialBUMP_Admin_Bar::register() takes id, label, href and items, and optionally
actions, attention, attention_title and current. Everything is drawn once, at
admin_bar_menu priority 200.

- One plugin active: that plugin sits on the bar on its own.
- Two or more: a single SocialBUMP item, each plugin a row inside it, its pages
  on a flyout from that row.
- Each row has a dot: green when there is nothing to do, amber when there is.
  Any amber row makes the SocialBUMP dot amber, so the top of the bar is the
  only thing that needs watching.
- attention means an update is waiting.
- The current page is white and bold, never the admin colour scheme accent:
  some accents are unreadable on the dark bar.
- An action row marked sb-bar-action is-idle looks inactive and ignores hover.

Two signals, and they mean different things. Keep them apart:

- The dot is about this site: content waiting to be rebuilt, an update ready to
  install. It is what someone looking after the site cares about.
- Amber wording, and a small count beside it, is about the hub: changes noted but
  not yet released. Publishing carries it, through attention and count on that
  item. Never fold this into the dot, and never colour the dot for it: on a client
  site there is nothing to publish and the distinction is the whole point.

### Getting between the pages

The banner carries a row of links to every page in the plugin, with the one you
are on marked. The admin menu lists them too, but on a long menu the plugin can
be a scroll away and its pages only show while you are already on one of them.

- Updates shows the new version number when one is waiting.
- Publishing shows how many changes are queued, and only exists on the hub, so a
  client site gets a shorter row and no badges.
- render_nav() builds it from bar_items(), the same list the admin bar uses, so
  a new page appears in the menu, the admin bar and the banner at once.
- It hides itself when a plugin has fewer than two pages.
### The SocialBUMP Hub page

class-socialbump-overview.php, identical in each plugin, same arrangement as the
admin bar: first to load defines the class, the others register with it.

- A top level SocialBUMP Hub menu, but only on the hub and only when more than
  one plugin is active. It therefore disappears by itself on every site built
  from the blueprint, which is the point: there is nothing to publish there.
- A card per plugin: version, whether an update is waiting, how many changes are
  queued for the next release, and links to its pages. The count is an amber pill
  that jumps down to that plugin publishing panel.
- Below that, each plugin publishing panel in turn, with the plugin name slid in
  as the heading inside the panel, so all of them go out from one screen.
- The item in the admin bar opens this page when it exists, and the first
  plugin otherwise.
- Between the cards and the publishing panels sits a master prompt for starting a
  chat that could touch more than one plugin. It builds itself from whatever is
  registered, so a fourth plugin would appear in it without being told, and it
  covers what the per plugin prompts cannot: that shared code lands everywhere,
  and that the shared block of the notes must stay identical in every copy.

register() takes id, name, version, file and pages, and optionally notes, css,
css_time, logo, accent_var, hub, and release, a callback that draws that plugin
publishing panel.

Two things about the page are easy to get wrong, and both have been:

- It belongs to no plugin in particular, so it loads every registered stylesheet,
  and each one is versioned by when the file changed rather than by the plugin
  version. Version it by the plugin and a browser serves yesterday CSS after every
  edit, which is exactly what happened.
- Each plugin styles itself from its own CSS variable, and nothing sets those on a
  page that belongs to none of them, so the page works out the accent itself and
  sets every registered variable. Without that the panels fall back to the
  WordPress blue and look nothing like the rest.

### After an update

Updating a plugin swaps its files out mid request. If you were on one of its own
pages, the page you land on afterwards can still be running the old code, so its
menus never register and the plugin appears to have vanished until you navigate
somewhere else. Each plugin now clears the compiled copies of its own files on
upgrader_process_complete, which settles it.

The Update now button on each Updates page goes through update-core.php, the
bulk path the dashboard uses: maintenance mode on, files swapped, maintenance
mode off, plugin never deactivated. It used to go through update.php, the
single plugin path, which deactivates the plugin first and does not reactivate
it in PHP at all: the results page carries a hidden iframe that loads
update.php?action=activate-plugin, and that iframe is the reactivation. Leave
the page before it loads, or have anything block it, and the plugin stays off
with nothing in any log. That happened twice on a client site. Keep the bulk
path.

### What a client site must not carry

The hub is the blueprint new sites are built from, so whatever is in its database
travels with every copy. On any site that is not the hub, each plugin deletes its
GitHub token, its queued release notes and its release cache when an admin page
loads. A token has no business on a client site.

If you add anything else that only the hub should know, delete it there too.
### Unsaved changes, and the save button

Any form marked data-sb-dirty is watched, and every one of them also carries
autocomplete="off". Without it a browser puts unsaved values back into the
fields when the page is reloaded past the warning, and it does so after the
page has parsed: the button flickers while the script and the form disagree
about the baseline, and worse, the edits sit there on screen under a button
saying there is nothing to save. Reloading should show what is saved. The save button sits disabled reading
Nothing to save until something changes, then wakes up with its own wording and
an amber reminder appears top right and follows you down the page. Put the change
back the way it was and both go quiet. Leaving with something unsaved warns you.

Attributes a button can carry:

- data-sb-save: treat as a save button even though it is not a submit.
- data-sb-label-dirty: the wording to use when there is something to save, for a
  button whose resting label says there is nothing.
- data-sb-always-on: never disable this one. Used for buttons that do work
  rather than save, such as Full Rebuild, and for any submit that is an action
  rather than a save, such as Reset to defaults.
- data-sb-idle=1: nothing to run right now, so sit inactive until there is.

The reminder saves with the button that actually saves: one marked data-sb-save,
then the primary button, and only then the first submit in the form. A form can
hold more than one submit and not all of them save. The image sizes form has
Reset to defaults sitting above Save changes, and the reminder used to submit
whichever came first, so clicking it reset the sizes rather than saving them.
Worth remembering when adding any second submit to a form.

Styling: .sb-save--clean is a grey outline on transparent, .sb-save--dirty fills
with the admin colour scheme accent, white text, so the thing to press is the
only solid button on the page. The accent is taken down a shade with
color-mix( in srgb, var(--prefix-accent) 76%, #000 ): the SocialBUMP green is
too bright at full strength and every other scheme reads better slightly
darker. The flat var() is declared first as a fallback. The reminder stays pale
yellow: it is a notice, not a button, and the two should not read as the same
thing.

Render the button wearing sb-save--clean already, by passing
'primary sb-save--clean' as submit_button()'s second argument. The script is
enqueued in the footer, so until it runs the button is an ordinary live primary
button and flashed the accent colour on every page load before settling to
Nothing to save. It is not rendered disabled, so a page whose JavaScript fails
can still be saved. Both selectors lead with
.wp-core-ui and .button, because WordPress styles disabled and primary buttons
with important and would otherwise win.

### The look

- One stylesheet per plugin at assets/css/admin.css, every class prefixed.
- Dark banner: SocialBUMP logo, plugin name, page name in a span in the accent
  colour, then a version badge linking to Updates that turns amber when a
  release is waiting. The heading reads plugin name then page name, including on
  a landing page: Site Kit Modules, Bricks Tweaks Features, SEO for AI Content.
- Panels: prefix-section, with __head for the heading and description and __body
  for the content.
- Cards: prefix-card, is-on for a live one, is-unavailable for one waiting on
  something missing. The left edge carries the accent when live.
- Pills: prefix-status__pill, is-good green, is-stale amber. An amber one that
  can be acted on is a link, and clicking it does the thing it describes.
- Text toggle links: sb-toggle on the link, sb-toggles on a pair's wrapper.
  Select all and Select none, Collapse all, Expand all, Collapse disabled, the
  all and none pairs on the Image Cleaner: all the same look, all defined once,
  so a new one never has to be styled again. The colour is the admin scheme
  accent taken down to 72 per cent against black, and hover goes to 42, which is
  a change you can actually see on any scheme. A wrapper sits its pair at the
  right, where the Modules links have always been.
- Menu icon: the SocialBUMP exclamation, shared by all four items through
  SocialBUMP_Overview::brand_icon(). Each plugin positions its menu next to the
  others rather than at a fixed spot.
- WordPress does not recolour an SVG menu icon. It only recolours Dashicons,
  which are a font. An SVG given as a menu icon becomes a background image and
  keeps whatever colour is baked into it, so ours is white and the dimming when
  idle, and the brightening on hover, are done in CSS to match the icons around
  it. Build the SVG by concatenation with chr( 34 ): a quote mangled in the
  middle of it produces markup that silently draws nothing.
- Publishing lays out as two columns: the token and the zip stacked on the left,
  publishing beside them. The cards are placed with CSS grid rather than
  reordered, so the markup and the reading order stay as they are.
- The accent comes from the admin colour scheme, chosen by saturation so a
  washed out swatch is never picked, and exposed as --prefix-accent.

### Releasing

Everything is developed and released on the hub, plugins.socialbump.com.au. Each
plugin decides it is on the hub by host name, and only then loads its release
code and shows a Publishing page.

- Publishing pushes the code to GitHub, builds a zip, creates a release and
  attaches the zip. Sites update through the plugin update checker.
- One fine grained GitHub token per plugin, stored encrypted, scoped to that one
  repo with Contents read and write. A token cannot create repositories, so a new
  repo is made by hand first.
- The notes box fills from prefix_log_change() calls made since the last release,
  and the list empties once a release goes out. Call it after any change worth
  telling someone about, in their words rather than yours.
- Only log what a client site would notice. The Hub page, the Publishing page and
  anything else that exists only on the hub never reach a client site, so a change
  to them earns no note and no release of its own. It rides along with the next
  real one. A release exists to tell other sites something changed for them.
- Publishing retries on a 5xx, checks the zip actually attached, and checks again
  before undoing anything, because GitHub has published a release and then failed
  the response.
- The first release may carry the version already in the files. Every release
  after that has to be higher than the last.
- A version needs all three parts, so 1.1 is padded to 1.1.0 when you leave the
  field, and again on save in case the form never lost focus. Typing 1.1 used
  to get you the browser complaining about a pattern it does not explain.
- Everything in the plugin folder is published except .git, .github, node_modules
  and .DS_Store. These docs ship with the plugin, so they reach every site, and
  the repos are public: nothing private goes in them.

### How work actually gets done here

There is no local checkout and no git client. Everything happens on the live hub
through its Novamira MCP connector, by running PHP on the site. That shapes how
to work:

- Read a file with file_get_contents, write it with file_put_contents.
- Lint before you write. Put the new contents in a temporary file, run php -l on
  it, and only write the real file when it passes. A fatal in a plugin file takes
  the site down, and you are editing the site you would need to fix it.
- JavaScript has no linter here. Walk the brackets, minding strings, comments
  and regular expressions, before writing.
- Call opcache_invalidate() on a file after writing it.
- A class already loaded in the current request is still the old one. Check your
  work in a fresh call, not the one that wrote the file.
- Anchor edits on a unique string and check it matches exactly once. If it
  matches twice, widen it until it does not.
- Keep a copy before a risky edit. copy( $file, sys_get_temp_dir() . ... ) costs
  nothing and has saved a rewrite more than once.
- Verify after every write. A write that silently did nothing, because the anchor
  never matched or the function returned early, has cost more time here than any
  actual bug.

Watch out for quoting when building PHP through a JSON tool call. A backslash in
a regular expression, or a quote in a string, has to survive JSON, then PHP, then
whatever it is written into. Building strings with chr( 34 ) and concatenation is
uglier to read but far less likely to arrive mangled.

### Where things live

The hub is plugins.socialbump.com.au, and the plugins are in the usual place:
wp-content/plugins/<folder>/. Client sites each have their own connector and the
same folder structure.

Every plugin has the same shape:

| File | What it is |
| --- | --- |
| <plugin>.php | constants, updater, hub check, log_change(), loads everything |
| includes/class-<pre>-settings.php or -admin.php | menu, pages, banner, admin bar registration |
| includes/class-<pre>-modules.php | finds and boots the modules |
| includes/class-<pre>-release.php | publishing to GitHub, hub only |
| includes/class-<pre>-updates.php | the Updates page and the update check |
| includes/class-<pre>-transfer.php | settings export and import |
| includes/class-<pre>-docs.php | these notes, and the panel on Publishing |
| includes/class-socialbump-admin-bar.php | shared, identical in both |
| includes/class-socialbump-cards.php | shared: collapsible, reorderable cards. Both have it, with their Modules pages |
| assets/js/module-cards.js | shared, goes with the cards class |
| assets/css/admin.css | everything the admin pages look like |
| assets/js/save-state.js | shared, identical in both |
| vendor/plugin-update-checker | the updater library, left alone |

### Working on a plugin from a client site

Work on the hub by default. Build on a client site only when it has something
the hub has not, which in practice means WooCommerce: the WooCommerce modules in
Site Kit were built on drivingevents.com.au for that reason.

When you have, bring it home carefully. Assume nothing at any step:

1. List both folders and compare every file by md5 and by modified time. Not just
   the files you think you touched: a file you did not expect to differ is
   exactly the one worth knowing about.
2. For each file that differs, work out which side is newer and why before you
   move anything. The hub may have moved on while you were working elsewhere, and
   the client copy may be an older release rather than your new work.
3. Read any file the hub has changed, in full, before overwriting it. Two people
   editing the same file from different directions is how work disappears.
4. Copy back only the files that genuinely differ, one at a time.
5. Compare the md5s again afterwards and confirm each one matches.
6. Update these docs on the hub, never on the client site.
7. Call prefix_log_change() on the hub, so the work appears in the next release.

If the two sides have both changed the same file, stop and say so rather than
picking one. Merging by hand with both versions in front of you takes minutes.
Guessing wrong costs whatever was on the losing side, and nobody finds out until
later.

Nothing may live only on a client site. The next update overwrites the plugin
folder, and anything not carried back to the hub is gone.

### Habits that have paid off

- Lint every PHP file before writing it, and bracket check any JavaScript.
  Write to a temporary file, lint that, and only then put it in place.
- Write a class file before the loader that requires it, so a failure never
  leaves a plugin pointing at a file that is not there.
- Keep each edit small and check it took. A write that silently did nothing has
  cost more time here than any bug.
- Anchor edits on unique strings. If an anchor matches twice, stop and widen it.
- After editing a file, the class already loaded in that same request is still
  the old one. Verify in a fresh request, not the one that wrote the file.

### Things learned the hard way

- **form.requestSubmit() only accepts a real submit button.** Pass it a button
  with type=button, which is what data-sb-save allows, and the browser throws
  and nothing is sent, while the button and the reminder both look exactly
  right. SEO for AI's save button is type=button, so its settings pages
  silently stopped saving. save-state.js now passes the button only when its
  type is submit, and otherwise calls requestSubmit() with nothing. Found on
  21 September 2026.
- PHP declares top level classes and functions while compiling the file, before
  a line of it runs. A class_exists() guard inside the file that declares the
  class always sees its own class and returns, and the file never finishes. This
  broke SEO for AI once. Guard by other means.
- WordPress styles disabled and primary buttons with important. Beat it with
  specificity, not with another important on its own.
- admin_head has already been sent by the time the admin bar is built, so a
  style hooked only there never appears. Hook the footer as well.
- A settings page that submits only part of the settings must merge rather than
  replace, or saving one page wipes the others. All three plugins have had this
  bug. Site Kit and SEO for AI post a marker of which sections were on the page.
  Bricks Tweaks caught it again in September 2026 when the group pages were
  ported without the two lines that seed the save from what is already stored
  and limit it to the group that was posted. Port a page, port its save.
- An element with no link is rendered by the admin bar as an empty item, not an
  anchor, so style both.
- Nested admin bar flyouts need position relative on the row, or they fly off
  to the right of the whole menu.
- The admin menu can be renamed by an admin menu plugin. Admin and Site
  Enhancements holds its own titles and wins over whatever the plugin registers.
- opcache_invalidate() only reaches the PHP process it runs in. On a LiteSpeed
  host with opcache.revalidate_freq set to 60, every other process keeps running
  the old file for up to a minute after a write. A rebuild started in that
  window ran half on old code and half on new, and stamped the cache both ways.
  A fresh request is not proof until a minute has passed, and nothing that
  writes stamps or data formats should be exercised in that minute.
- Three plugins carrying the same shared file meant whichever loads first
  declares the class, and the others must not declare it again. Bricks Tweaks
  sorts before Site Kit, so when it started loading the cards class at the usual
  time it declared it first, and an older published Site Kit whose copy had no
  guard declared it again and killed two live sites. The lesson is not the
  guard, which was already there: it is that the guard only helps in the copy
  that has it, and published sites run old copies for months. A plugin that
  sorts early loads a shared class last, on plugins_loaded at a late priority,
  so the oldest copy present goes first and there is nothing to clash with.
  SEO for AI has since been taken out of the arrangement altogether, and the
  merge below finishes the job for the two that are left.
- The error log is the fastest way to the truth and was checked third rather
  than first during that outage, after two confident wrong explanations. Read
  the log before forming a theory.

### Where this is heading

The three plugins are now two. SEO for AI was split off in September 2026 and
shares nothing with either of these. What is left is to merge these two.

The reason is everything above: the shared files exist in two copies and every
one of them has drifted at least once. The cards CSS was missing three rules in
Bricks Tweaks, bar_items() was never ported so the tabs listed nothing, and the
load order of one shared class took down two client sites. None of these are
hard problems. They are all the same problem, which is that one thing lives in
more than one place and nothing checks that the copies agree.

The shape agreed: one plugin, SocialBUMP Tweaks, in an all new folder with the
prefix sbtweaks_, replacing the Hub as the front door. The shared functions and
styles are combined into it once. Each plugin as it stands today becomes a
folder inside it holding its own modules, so adding an area later is one more
folder and nothing else. A group that cannot run on a site hides itself, so
Bricks features do not appear where Bricks is not installed. group_needs()
already does this, so it is configuration rather than new code.

The work is not the code, which is mostly moving folders and changing a prefix.
It is the three things around it, and each has an answer now:

- Migration. Client sites hold sbsk_ and sbbt_ options and per user card meta.
  About four sites have either plugin, so the first release reads the old keys,
  writes them under the new prefix and erases the originals. Note what that has
  to cover beyond the obvious two: sbsk_owned_image_sizes, sbsk_kept_orphans,
  sbsk_image_split, sbsk_faq_fields, sbsk_groups, and the user meta
  sbsk_cleaner_sizes and socialbump_cards. The card meta is keyed per page slug,
  so it has to be remapped to the new slugs or everyone loses their card order.
  The GitHub tokens and pending changes are hub only and are not carried.
- Deployment. On each site, deactivate both old plugins, then activate the
  merged one, which migrates on its activation hook. Options live in the
  database whether or not a plugin is active, so nothing has to be running to
  be read. Done in that order by hand on four sites there is no window where
  both declare the same classes, which is why the old plugins do not need an
  early bail out guard and do not need a release of their own first.
- Releases. The merged plugin is a new repo with its own update stream, so it
  starts at 0.1.0 and goes back to 1.x.x once every site is converted. Sites
  still on the old plugins stop getting updates and are moved over by hand.

The old release notes are worth keeping. They live in the GitHub releases of the
two old repos, not on any site, since pending_changes empties at every release.
So the merged plugin ships a release notes archive: a one off pull of both
repos' releases into a file, read only, kept split by plugin because a note
about a Bricks element means nothing on a site without Bricks, with the merged
plugin's own releases appended from then on.

Do it deliberately, on one site first, not as a big bang.

<!-- shared:end -->

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
