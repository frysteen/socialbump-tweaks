=== SocialBUMP Tweaks ===
Contributors: socialbump
Tags: admin, tweaks, modules
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SocialBUMP site tweaks in switchable modules. Turn on only the parts a site needs.

== Description ==

One plugin holding the tweaks a SocialBUMP site expects, as modules that can be
switched on and off. Each module adds its own menu with its own pages, and a
module that cannot run on a site hides itself.

Modules are being moved across one at a time from the plugins they used to be.

== Changelog ==

= 0.1.2 =
* Built the module framework: the Modules page now has real switches, and each module switched on gets its own menu with its own pages.
* New Installs page on the hub: every site running a SocialBUMP plugin checks in, and the page shows which plugins each site has, their versions (highlighted when behind), whether they are active, and when the site last reported, flagging any quiet for over three days. SocialBUMP Tweaks also reports itself like the other plugins.

= 0.1.1 =
* The Start

= 0.1.0 =
* First release. The front door only: the Modules page and, on the hub, Publishing.
