=== SocialBUMP Tweaks ===
Contributors: socialbump
Tags: admin, tweaks, modules
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.3.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SocialBUMP site tweaks in switchable modules. Turn on only the parts a site needs.

== Description ==

One plugin holding the tweaks a SocialBUMP site expects, as modules that can be
switched on and off. Each module adds its own menu with its own pages, and a
module that cannot run on a site hides itself.

Modules are being moved across one at a time from the plugins they used to be.

== Changelog ==

= 0.3.2 =
* Fixed the notice WordPress 6.7 and later logged on every request with WP_DEBUG on, "Translation loading for the sb-tweaks domain was triggered too early". Module and feature names and descriptions are now translated on init instead of while plugins load. Nothing else changes.
* SocialBUMP Tweaks now needs PHP 8.0 or later. WordPress will not install, update or activate it on older PHP, and a site moved onto older PHP gets a notice instead of the plugin loading. Also stops the PHP 8.4 deprecation notices from the Image Management module.

= 0.3.1 =
* Each image size on SB Image Settings now has a padlock. A locked size cannot be changed, removed or reset, and SB Image Cleaner never deletes its files, so emails that link straight to those files keep working. Everything else can still be reset and cleaned.

= 0.3.0 =
* New Woo Tweaks module: the seven WooCommerce features that were in Site Kit now have their own SB Woo Tweaks menu, with Shop and Checkout and Store Admin pages. It only appears where WooCommerce is active, and switches and settings carry across from Site Kit on update.
* New Elementor Tweaks module: set alt text per widget (the image's own, your own words, a dynamic tag like the post title, or none) on every Elementor widget that shows images, and an aspect ratio, fit and position for widget images. It replaces the Custom Alt Text for Elementor Images plugin, and that plugin's settings carry over.

= 0.2.1 =
* Image Cleaner: Leftover sizes from other sources now has Select all and Select none links, so a long list can be ticked in one go.

= 0.2.0 =
* Import settings on the Updates page now asks in the SocialBUMP style popup instead of the browser's own box.
* New Site Kit module: everything from the standalone SocialBUMP Site Kit plugin apart from its image pages, with the same settings and page addresses. On a site with standalone Site Kit, the plugin is switched off and the module takes over on the first load, with nothing to set up again.
* New Image Management module: Site Kit's Images and Image Cleaner pages, now SB Image Settings and SB Image Cleaner in the Media menu, with an Image Management row in the SocialBUMP admin bar. Its settings are copied across from Site Kit automatically, and the old page addresses open the new pages.
* Every question and run summary on the Site Kit and Image Management pages now uses the SocialBUMP style popup. Delete FAQ content no longer falls back to the browser's own confirm box.
* SB Image Cleaner: a figure that needs attention, such as Sizes to build or Missing images, now keeps its yellow edge when it also has Show images. It used to turn grey.
* SB Image Cleaner: a size added on SB Image Settings, or a WordPress size switched back on, now starts ticked in the Sizes list. It used to start unticked, so the scan left it out until someone noticed.
* SB Image Cleaner: Build Thumbnails now has a green outline while there is something to build, to match the red outline on the delete buttons.
* Every button on the SocialBUMP Tweaks pages now has a 2px border, filled or outlined, so buttons of the same size match exactly.

= 0.1.11 =
* Bricks Tweaks: new Hide Unpublished ACF Posts switch under Extras. Drafts, scheduled, private and binned posts are left out of ACF relationship and post object fields on the front end, so related posts can be picked ahead of time and only appear once published.

= 0.1.10 =
* Reports to the hub are more reliable: a check-in now waits for the hub to confirm it (a lost one is tried again later, rather than a day later), and after an update pushed from the hub the site sends its new state back in its answer, so the Installs page is right straight away. The hub records that answer, and keeps recording a check-in even if the site stops waiting.

= 0.1.9 =
* Module pages no longer show a version badge: a module has no version of its own, it ships inside SocialBUMP Tweaks, whose pages keep showing the plugin's version.

= 0.1.8 =
* New Bricks Tweaks module: everything from the standalone SocialBUMP Bricks Tweaks plugin (the Image Carousel element, five conditions, ACF loop sorting, the ACF gallery loop, Default To WP Editor and Gutenberg Block Styles), with the same settings, menu and page names, so nothing on any site needs changing. Once it is here, standalone Bricks Tweaks is switched off and cannot be switched back on, and the two never run together.
* Modules that need Bricks now start properly: Bricks is a theme and loads after plugins, so it was being reported as missing.
* Installs: sites running a standalone plugin that a SocialBUMP Tweaks module now replaces get an Install button in the Tweaks column. One click installs SocialBUMP Tweaks on that site, which switches the old plugin off and carries on with its settings.
* Admin bar: SB Tweaks sits on the bar by itself while nothing else is in it. Once a module is on, a SocialBUMP item holds each module (with its pages on a flyout) and SB Tweaks as the last row, and both top items open the Modules page. A standalone plugin that a module replaces, still running on the hub for publishing, sits in the same place its module would. Site Kit and SEO for AI stay on their own. The modules in the SocialBUMP item follow your card order on the Modules page.
* Framework: a progress popup (SBTweaksProgress) for anything that takes more than a moment, in the same style as SEO for AI's: elapsed time, progress bar, what is being worked on, then a report with the time taken, failures in red, and Close. Publishing and site updates on the Installs page use it. The changes popup has Cancel on the left, Publish on the right and an X to close, and buttons there are compact.
* Installs: sites with updates waiting get a tick box and an Update all button. Under the sites, Update all updates everything on the ticked sites and an Update under each plugin column updates just that plugin on them, all in the same progress popup as publishing. The Publish column in the hub table is only as wide as its button.

= 0.1.7 =
* Installs: the Update buttons work again with the hub table above them, and the hub table shows a newly published version straight away.

= 0.1.6 =
* Installs: the hub has its own table above the sites, showing its copy of each plugin, the version live on GitHub, and any changes waiting to be published, with a link to each plugin's Publishing page. It asks GitHub with each plugin's publishing token, so it is not held up by the 60 checks an hour limit the hub's server shares with other sites.

= 0.1.5 =
* Updates page: laid out the same as the Site Kit and Bricks Tweaks Updates pages, with Updates and Settings as full-width sections and Export and Import in side-by-side cards.

= 0.1.4 =
* Can now be updated straight from the SocialBUMP hub's Installs page. The hub signs each update instruction and the site checks it before doing anything: it only ever updates SocialBUMP plugins, only to a newer version, and only from that plugin's own GitHub release.
* Installs: an Update button under any out of date plugin, and Update all per site, pushes the latest release to that site in seconds, without waiting for the site to find the update itself. Sites show the buttons once they run a version that can receive pushed updates.
* New Updates page on every site: the version running, a Check for updates button that asks GitHub straight away, Update now when one is waiting, and export and import of the Modules switches and every module's settings, to set up another site the same way. The version badge in the header now links to it.

= 0.1.3 =
* Installs: a site updating several plugins back to back now has every report recorded. The check-in limit is 20 reports per site in ten minutes, where one every thirty seconds used to turn away the later reports without the site knowing.
* Installs: the Active under each plugin version now links straight to that plugin's main page on the site.
* Deactivating a SocialBUMP plugin now reports it to the hub as deactivated. WordPress announces a deactivation before saving it, so the report used to still say active.
* Installs: each site now links to its Updates screen as well as its Plugins screen.
* The SocialBUMP plugin hub has moved to plugins.socialbump.com.au: sites now report which SocialBUMP plugins they have to the new hub, and releases are published from there. bricks.socialbump.com.au goes back to being a clean blueprint for new sites.

= 0.1.2 =
* Built the module framework: the Modules page now has real switches, and each module switched on gets its own menu with its own pages.
* New Installs page on the hub: every site running a SocialBUMP plugin checks in, and the page shows which plugins each site has, their versions (highlighted when behind), whether they are active, and when the site last reported, flagging any quiet for over three days. SocialBUMP Tweaks also reports itself like the other plugins.

= 0.1.1 =
* The Start

= 0.1.0 =
* First release. The front door only: the Modules page and, on the hub, Publishing.
