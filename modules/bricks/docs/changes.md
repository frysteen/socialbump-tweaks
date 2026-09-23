# SocialBUMP Bricks Tweaks: changes before the merge

The release notes of the standalone SocialBUMP Bricks Tweaks plugin
(frysteen/socialbump-bricks-tweaks), newest first, exactly as each release
shipped. From here on, changes are listed in the SocialBUMP Tweaks changelog.

## 1.1.6 (2026-09-23)

- Can now be updated straight from the SocialBUMP hub's Installs page. The hub signs each update instruction and the site checks it before doing anything: it only ever updates SocialBUMP plugins, only to a newer version, and only from that plugin's own GitHub release.

## 1.1.5 (2026-09-23)

- The SocialBUMP plugin hub has moved to plugins.socialbump.com.au: sites now report which SocialBUMP plugins they have to the new hub, and releases are published from there. bricks.socialbump.com.au goes back to being a clean blueprint for new sites.

## 1.1.4 (2026-09-23)

- Deactivating a SocialBUMP plugin now reports it to the hub as deactivated. WordPress announces a deactivation before saving it, so the report used to still say active.

## 1.1.3 (2026-09-23)

- Reports to the SocialBUMP hub which SocialBUMP plugins this site has, their versions and whether each is active, whenever one changes and once a day, so the hub can show what is installed where. Only the site address and name, those plugin versions, and the WordPress and PHP versions are sent.

## 1.1.2 (2026-09-22)

- Conditions: on the Blog page, the SocialBUMP conditions now check the Blog page itself rather than the first post in the list, so Bricks Content no longer shows the not-built-with-Bricks section on a Blog page built in Bricks. Inside query loops each post is still checked.

## 1.1.1 (2026-09-22)

- Made the Save changes button more robust, so a save button that is not a standard form submit button still saves.
- New Extras module, Gutenberg Block Styles: Bricks strips the block editor styles from pages it renders, so Gutenberg content shown through a Bricks template lost its gallery columns, cropping and gaps. It now loads only the styles the content uses. The gallery block also gets a working Block spacing setting with link sides and a full unit list, and every gallery without its own gap uses the Image Gallery spacing from your Bricks theme style, so Gutenberg and Bricks galleries match site wide.

## 1.1.0 (2026-09-20)

- The Modules page is now called Features, to leave the word Modules free for what these plugins are about to become inside SocialBUMP Tweaks. Nothing on it has changed.
- Settings are now saved under sb_tweaks_bricks_ names, ready for the move into the combined plugin. Your existing settings are converted automatically the first time the site loads after updating, and the old ones are left in place and backed up.
- The names this plugin saves into your pages have been tidied up so they all start with sb_bricks_: the element conditions, the loop ordering settings, the gallery loop and the Image Carousel element. Pages built before this keep working, because the old names are still understood.

## 1.0.7 (2026-09-19)

- Reorder Cards has moved up beside the Modules heading, on the right. It used to sit just above Save changes, which made it easy to hit by mistake.
- The Save changes button now fills with your admin colour scheme once there is something to save, instead of the pale yellow. The unsaved changes reminder stays yellow, since it is a notice rather than a button.
- The Save changes button no longer flashes as a live button for a moment when a settings page loads. It now starts in its resting state.
- Settings forms no longer hold on to unsaved changes when you reload the page past the warning. The page now comes back showing what is actually saved, rather than your unsaved edits sitting there looking saved.
- Select all and Select none, Collapse all and the other text links now all look the same and sit in the same place, with a hover colour you can actually see.

## 1.0.6 (2026-09-19)

- Fixed settings not saving properly since the features were split into their own pages. Saving one page wiped what was set on the others, so saving Extras would switch the Conditional Logic features back off and the other way round. Each page now saves only what is on it.
- Fix ACF CPT SVG Icons has moved to SocialBUMP Site Kit, under Admin Settings, because it has nothing to do with Bricks. If you were using it, switch it on there and it behaves exactly as before.

## 1.0.5 (2026-09-19)

- HOT FIX: on a site running an older SocialBUMP Site Kit, this plugin could take the site down with a fatal error, because both carry the same shared file and this one loaded it first. It now loads last, so the order no longer matters and an older Site Kit is left alone.

## 1.0.4 (2026-09-19)

- New Modules page, the same one Site Kit has. Each group of features sits on a card you can switch on or off, collapse to its title, and drag into the order you want.
- Each group now has a page of its own: Bricks Elements, Conditional Logic and Extras. The menu, the tabs across the top and the admin bar all follow the order you set, and a group that is switched off drops out of all three.
- Saving on a group page now leaves you on that page instead of returning you to the list.

## 1.0.3 (2026-09-15)

- The Update now button on the Updates page now runs the update the same way the WordPress dashboard does, under maintenance mode, instead of deactivating and reactivating the plugin. The old way could leave the plugin switched off after an update.

## 1.0.2 (2026-09-14)

- The banner now lists every page in the plugin, so you can move between them without going back to the admin menu. Updates shows a waiting version and Publishing shows how many changes are queued.

## 1.0.1 (2026-09-14)

- A SocialBUMP overview page collects every plugin on the site, and lets all of them be published from one screen.
- Updating no longer leaves the plugin missing from the menus until you navigate away.
- A site that is not the publishing hub now clears the GitHub token and release notes it has no use for.
- A SocialBUMP Hub page gathers every plugin on the site, with one place to publish them all from. It only appears on the publishing hub.
- Menus now carry the SocialBUMP mark, and publishing lays out in two columns instead of three stretched cards.

## 1.0.0 (2026-09-14)

- Admin bar item is now shared: with more than one SocialBUMP plugin active they sit together under a single SocialBUMP menu, each with its own pages.
- Save buttons stay greyed out until something is actually changed, with a reminder that follows you down the page while changes are unsaved.
- Unsaved changes now also warn before you leave the page with something unsaved.
- Save buttons look the same in every SocialBUMP plugin: a plain grey outline when there is nothing to save, amber when there is.
- Page headings now read the plugin name followed by the page you are on.
- The plugin now carries its own notes at docs/context.md, and they can be read and edited on the Publishing page.
- The plugin notes now describe every module and condition in detail, including how it works and what it can be set to.

## 0.1.7 (2026-09-13)

- Exported settings file name reads properly for the site it came from.

## 0.1.6 (2026-09-13)

- Updates page can now export the settings to a JSON file and import them on another site.

## 0.1.5 (2026-09-13)

- Publishing now retries GitHub when it fails, checks the zip attached, and no longer undoes a release that actually went out.
- Publish page fills in the notes box from changes logged since the last release.
- Added an SB Bricks Tweaks shortcut to the admin bar, with a dropdown to each of its pages.
- Admin bar shortcut highlights the plugin and the page you are on.
- New WooCommerce Archive Display condition, so a shop template can switch between a product loop and a category loop without a snippet.
- Modules can now require WooCommerce, and a condition can be checked on an archive where there is no single post.

## 0.1.4 (2026-09-13)

Version 0.1.4

## 0.1.3 (2026-09-13)

Version 0.1.3

## 0.1.2 (2026-09-13)

Fix: the header logo markup was broken by the last release, so the logo did not show.

## 0.1.1 (2026-09-13)

The logo in the header now takes you back to the Features page.

## 0.1.0 (2026-09-13)

First numbered build while the plugin is still being put together.
Bricks elements: SEO friendly image carousel.
Conditions: ACF Relationship, ACF Repeater, Bricks Content, Post Type.
Extras: ACF Gallery Loop, ACF Loop Sorting, Default To WP Editor, Fix ACF CPT SVG Icons.
ACF features switch themselves off when ACF is only present as the copy bundled with Advanced Themer.
