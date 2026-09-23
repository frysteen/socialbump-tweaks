=== SocialBUMP Tweaks ===
Contributors: socialbump
Tags: admin, tweaks, modules
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SocialBUMP site tweaks in switchable modules. Turn on only the parts a site needs.

== Description ==

One plugin holding the tweaks a SocialBUMP site expects, as modules that can be
switched on and off. Each module adds its own menu with its own pages, and a
module that cannot run on a site hides itself.

Modules are being moved across one at a time from the plugins they used to be.

== Changelog ==

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
