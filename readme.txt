=== SocialBUMP Tweaks ===
Contributors: socialbump
Tags: admin, tweaks, modules
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.6.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SocialBUMP site tweaks in switchable modules. Turn on only the parts a site needs.

== Description ==

One plugin holding the tweaks a SocialBUMP site expects, as modules that can be
switched on and off. Each module adds its own menu with its own pages, and a
module that cannot run on a site hides itself.

Modules are being moved across one at a time from the plugins they used to be.

== Changelog ==

= 0.6.5 =
* SocialBUMP Tweaks: one banner on every page, with a breadcrumb: the logo goes to Modules and the module name to its first page
* SocialBUMP Tweaks: the Modules page lists each module's pages as links and its groups as small switches, and Features pages switch each feature with a small switch
* SocialBUMP Tweaks: group pages of Bricks Tweaks and Site Kit have the Features page bar, with the count, Enable all and Disable all, collapse buttons and Save changes
* SocialBUMP Tweaks: every SocialBUMP admin menu item, SEO for AI included, shows the mark in SocialBUMP green on a dark tile
* SocialBUMP Tweaks: one colour picker everywhere, Email Signatures included, with a Theme colours menu listing the site's Bricks, Core Framework, Elementor and theme colours
* SocialBUMP Tweaks: Cmd+S (Ctrl+S on Windows) saves on every Tweaks page, or says there's nothing to save
* SocialBUMP Tweaks: the Unsaved chip sits on the card's top edge, so the card no longer moves when it shows
* SocialBUMP Tweaks: Select all and Select none on tick lists, as buttons or small text links
* SocialBUMP Tweaks: open admin tabs keep in step, so a change saved in one tab shows in the others without a reload
* SocialBUMP Tweaks: in Safari, the page no longer jumps when you pick a colour or when part of it redraws
* Bricks Tweaks: added AT Stylesheet Live Reload, so saving a WPCodeBox stylesheet in Advanced Themer reloads it in open tabs
* Site Kit: Block Admin Access and Hide Toolbar On The Front End have Select all and Select none under their role boxes
* Email Signatures: no default company. Any company can be deleted while there's another, and deleting one asks whether to move its people to another company or delete them too
* Email Signatures: a new Import/Export page for people and templates. Export People lets you pick who to export, and opens from each company's bar too
* Email Signatures: Add person for a company from its card and its bar on the Signatures page, which now shows with one company too
* Email Signatures: company bars on the Signatures page line up, with the Active, Inactive and Deleted counts on the right
* Email Signatures: company cards show the brand colours under the name (click them to edit) and the website with a globe icon, so a long website no longer pushes the switch out of the card
* Email Signatures: a company card's Default Template opens a popup to set its Default Template and the ones its people can pick
* Email Signatures: the Templates page's Used by menu filters straight away, and template cards line up across a row
* Email Signatures: Duplicate a person from their card. With more than one company it asks which company the copy goes in; the copy starts Inactive with (copy) after the last name. Preview on admin cards is now just the eye icon.
* SocialBUMP UI: new Actions dropdown button in the framework (white, or green for a page's main action), with a menu that can't be cut off by cards or popups and works from the keyboard. Email Signatures: person cards now have Preview, Edit, Copy link, Open and an Actions menu with Duplicate, Move to company, Email signature link, Download CSV and Delete. Move to company is new.
* Installs page: Actions is now the framework's dropdown (still green), so it opens, closes and works from the keyboard like every other Actions menu, and nothing can cut it off.

= 0.6.4 =
* Email Signatures: new icons in a Downloads group (PDF, Download, File, Book and Video), and LinkedIn is now the plain "in"; companies' icons are redrawn on the next admin visit.

= 0.6.3 =
* Email Signatures: LinkedIn added to the Social Media icons and the Info List icons (Bootstrap Icons' LinkedIn logo, as Simple Icons no longer has one).
* Email Signatures: the Icon fields (Social Media, Info Lists, Additional Buttons) now show each icon beside its name, in the field and in its menu, with a search box.
* Email Signatures: Social Media rows can use any of the icons (phone, email and the rest, not just the social ones), and the company's and people's Additional Info List Items can have a Custom SVG, uploaded as on Social Media.
* Email Signatures: Custom SVG fields show just the icon with Replace and Remove buttons, and now show the icon on a person's page too.
* Email Signatures: a person's Additional Info List Items are laid out in two lines per item, and the row numbers on the company's Social Media and Additional Info List Items stay narrow when no Custom SVG is in use.
* Email Signatures: a person's Additional Info List Items are laid out as Icon and Custom SVG on one line (the Custom SVG space kept while it isn't in use), then Label, Text and Link, with room round the fields.
* Email Signatures: Custom SVG sits beside the Icon on Social Media and both Additional Info List Items, its space always kept, and the row number columns stay narrow.
* Email Signatures: Additional Buttons can use a Custom SVG icon, uploaded beside the Icon as on Social Media.
* Email Signatures: every Icon menu (Social Media, Additional Buttons and the Additional Info List Items) now has the same list in the same order, with Custom SVG last.
* Email Signatures: a company's Branding tab has Info List Icons, to change the Phone, Mobile, Email, Website, Address and Save Contact icons or use a Custom SVG, each with a reset to the default.
* Email Signatures: each Additional Button can have its own colour, picked with the colour swatch and menu; blank keeps the template's.

= 0.6.2 =
* Email Signatures: five new icons for the Info Lists and buttons (Quote, Clipboard, Calculator, Dollar and Receipt) in a new Enquiries group with Message. Additional Buttons can have an icon before the label, drawn in the button's text colour and the same height as the text, so icon buttons are the same size as the rest.
* Framework: ACF forms on SocialBUMP Tweaks pages (Email Signatures companies, Settings and people) now notice a change put back, so Save goes back to Nothing to save and there's no leave warning; ACF's own leave warning is off. A save stopped by a required field shows a popup naming each field with its tab and row, Show me goes to it, and tabs holding one get a red dot.
* Framework: ACF forms skip the browser's own checks so ACF's run every time, so an empty required field on another tab now shows the Can't save yet popup instead of nothing; fields with an error get a red outline.
* Framework: on ACF forms a field that stopped a save keeps its red outline (and its tab its dot) until it has something in it, and Show me no longer clicks into the field, which made ACF drop its message.
* Email Signatures: picking an icon on an Additional Button fills an empty Label with the icon's name, as the Info Lists and Social Media rows do.
* Framework: on ACF forms, ACF's Validation failed banner is now a toast that goes by itself, and editor (TinyMCE) text put back counts as no change.

= 0.6.1 =
* Email Signatures: new companies start with a confidentiality notice as their Footer Text and a blank Sign-off; Social Media rows put the icon first; picking an icon fills an empty Name or Label (Social Media and both Info List lists); the Additional Banners moved to the Campaign Banner tab, now called Banners.
* Email Signatures: the default Footer Text for new companies is now a general confidentiality and privacy notice, suitable for any business.
* Email Signatures: Footer Text has Bold and Link buttons (nothing else is kept); its links are underlined, counted in Reports, and take a Link colour set on Edit styles (the brand primary by default). Brand Primary and Brand Accent each get Lighter, Light, Dark and Darker shades in every colour menu, made from the colour automatically.
* Email Signatures: the colour menus now also list Core Framework's colours and Elementor's system and custom colours, under a heading for each source (Brand colours, Bricks, Core Framework, Elementor, Theme). Core Framework shades such as var(--primary-d-3) can be typed in.
* Email Signatures: Footer Text's editor now shows only Bold, Italic, Link and Unlink (it was showing the full toolbar), italic is kept, and pasted headings or lists keep their line breaks.

= 0.6.0 =
* Email Signatures: a person's Email isn't required any more, here or on the team page form. Companies have an Email, used in the signature for anyone without their own.
* Email Signatures: Company Email is an Info List item. A new or reset Info List has Save my contact details ticked, above the company's and person's items, which start unticked.
* Email Signatures: Email link is on every person. Its To can be changed, and starts as their Email, else their company's. It starts on Updated Signature for someone emailed before.
* Email Signatures: Default Sender Name in Settings, Signature Emails, for {sender}. Blank uses whoever sends it.
* Email Signatures: the copied or not copied status is gone (Changed since copied, Not copied yet, Up to date). Every Copy signature and Copy HTML code click is logged, with the template, and who was logged in.
* Email Signatures: the people file has a Separator column, and imports without an Email, matching by name.
* Email Signatures: in an Info List, Email is now Person Email, with a note that it uses the Company Email when the person has none. Company Email links to that company's Company tab.
* Email Signatures: people cards show the company's email with a Company tag for someone without their own, or No email set.
* Email Signatures: company cards show the company's Phone, Email and Address.
* Email Signatures: people use their company's templates only. Their own main and extra templates are gone, along with the Templates box on the person page and the template columns in the people file.
* Email Signatures: Main Template is now called Default Template everywhere.
* Email Signatures: company cards show Phone, Email, Address and Default Template, each with a Not set link to its field when empty. The cards line up row by row, and their people and templates counts link to those pages, filtered to the company.
* Email Signatures: the Templates page has a Used by filter, and each card lists the companies it's the default for and available for. Edit styles and Layout show which companies use the template.
* Email Signatures: the Info List icon menu is grouped (Contact, Social Media, Bookings and Hours, Reviews and Awards, Other) and adds Facebook, Instagram, YouTube, TikTok, X, Threads, Pinterest and a thumbs up.
* Email Signatures: a Google icon in the company Social Media list and the Info List icon menu.
* Email Signatures: icons added in an update are drawn in each template's colours the first time an administrator opens an Email Signatures page, instead of showing in the default teal.
* Email Signatures: WhatsApp and Messenger icons in the company Social Media list and the Info List icon menu.
* Email Signatures: Copy and Paste in the Layout popups, to put one widget's, row's, column's or gap's look on another of the same kind, on any template.
* Email Signatures: the Submit New Team Member form only works from that company's own team page link, and anything typed into it can no longer run shortcodes.
* Email Signatures: shortcodes in a person's details no longer run a second time inside Text widgets.
* Image Management: the site wide image tools (rebuild, clear, scan and delete) are for administrators only; single image tools stay with anyone who can edit that image.
* Settings import and export no longer carry the hub's module allowances and limits.
* Email Signatures: with more than one company, the people file has a Company menu: Export writes that company's people and Import adds everyone to it, matching only people already in it.
* Email Signatures: a photo in an import that can't be used (a GIF, say) is no longer left in the Media Library.
* Email Signatures: a person added to, or moved into, a switched off company is made Inactive.
* Email Signatures: the Layout card's own Reset only resets rows, columns and widgets, not Container, Footer or Layout Settings.
* Email Signatures: the Active, Inactive, Deleted and total counts are readable in dark mode.
* Email Signatures: UPPERCASE and lowercase text no longer break symbols such as &nbsp;.
* Email Signatures: deleting a company moves its deleted people to the default company too, and a company can't be added past the limit from a page opened earlier.
* Email Signatures: brand colours say so when the icons couldn't be redrawn, and copy buttons only say Copied once it really copied.
* Bricks: Bricks Content conditions saved in the older wording work again, and gallery loops saved under the old name show their images.
* Site Kit: adding a FAQ post type after removing one can no longer wipe another, and Reset to default rules on robots.txt asks first.
* Image Management: Regenerate keeps a file another image still uses, the delete confirm shows the real file count, a long orphan scan keeps its list to the end, and a size settings field has its width back.
* Woo Tweaks: the regular and sale prices in the cart follow the cart's tax display setting.
* The Installs page says how many updated and failed after a bulk update, and a failed publish no longer leaves readme.txt with the new version.
* Email Signatures: tidied unused code and fixed old wording on screen. The Default Template help now reads "Used for everyone in this company", the Settings page description lists what it really holds, the Reports notice points to Enable Reporting, and the person screens say Person instead of Signature.
* Email Signatures: merged repeated code into shared helpers (people in a company, the company Email, link buttons, the Active switch, the Company filter, the tag lists, private page headers and more). Icon-only link buttons now have screen reader labels.
* Email Signatures: removed unused styles and merged rules that were written twice. Nothing looks different.
* Email Signatures: the Save Contact card splits a name the same way as the rest of the plugin when no first and last name are saved.
* Tidy: removed about 24KB of unused styles from the framework stylesheet, plus unused code in the framework, Images and Site Kit (an old Installs publish handler nothing called, and a few functions nothing used).
* Site Kit: shortcodes in excerpts run once instead of twice, and the FAQ clean-up says "1 post type has" instead of "1 post type have".
* UI rules: hover tips use the framework tooltip instead of the browser one, Save buttons keep one width whether they say Nothing to save or Save changes, copy confirmations show as toasts, and copied tags say Copied in their tip instead of changing size.
* Site Kit: deleting leftover FAQ content asks in a red Delete popup and says how it went in a toast.
* Saved and done messages on the Updates page, the plugin notes and the Image Cleaner show as toasts instead of banners, so the page does not jump.
* Email Signatures: Houzz added to the social media icons and to the Social Media group in the Info List icon menu.
* Email Signatures: the template toolbar shows "In use by 2 companies" as a pill, with the company names in its tip, in place of the list.
* Email Signatures: hovering the In use by pill lists the companies one a line, each a link to edit that company.
* Stop button for long jobs: the progress popup can show Stop while it runs, which ends the job after the step in hand and keeps what is done. Updating several sites and sending modules to several sites on the Installs page now have it.
* Modules and Features pages: cards drag by a handle beside their switch and the new order saves as soon as one is dropped (the Reorder Cards popup is gone). The toolbar shows how many are on as a pill, with Enable all and Disable all.
* Features and Modules pages: Disable all now sits left of Enable all, and turns red on hover where Enable all turns green.

= 0.5.3 =
* Email Signatures: the person photo is a fixed width again, set by Width in Edit styles. The Max width version left the photo out in Apple Mail.

= 0.5.2 =
* Email Signatures: shortcodes, [like_this] and Dynamic Shortcodes {like:this}, work in every company and person field.

= 0.5.1 =
* Email Signatures: divider lines between columns and rows have their own spacing on each side (left and right, or top and bottom), with a lock to keep them the same.
* Email Signatures: Save my contact details can also go in an Info List, so it can stack or sit on one line with other items.
* Email Signatures: Company Info List Items and Person Info List Items are blocks of their own in the layout, as well as being in the Info List.
* Email Signatures: Footer Settings sets the spacing above and below the footer divider, buttons, footer text, campaign banner and banners.
* Email Signatures: spacing is based on 8px, with One and a half, Triple and Quadruple added, and Footer Settings has a top and bottom lock for each part.
* Email Signatures: the footer text can have its own padding, background and border, and buttons and banners have a Space between setting.
* Email Signatures: Reset Template asks what to reset, including each row, column and widget's settings without changing the layout.

= 0.5.0 =
* Added the Email Signatures module.
* Module controls from the hub: the hub chooses which modules each site can use and sets their limits, and a module a site cannot use is hidden there. Email Signatures is off on client sites until the hub allows it.
* New look for every SocialBUMP Tweaks page: SocialBUMP colours and the Outfit font whatever the admin colour scheme, an Auto, Light and Dark switch saved per user, and cards that show on, off and unsaved. Tables, ACF fields and popups follow light or dark mode too.
* Saved notices show as toasts at the top right and fade away by themselves, and a reload no longer shows them again.
* Toggle switches: the Yes on a switched-on toggle is dark on green, like a button (it was white).
* Betas: test builds of SocialBUMP Tweaks can be sent from the hub to chosen sites, which go back on to releases at the next one.
* Reporter 1.3.0: takes a pushed beta of SocialBUMP Tweaks, still signed by the hub and only from its own GitHub release.
* Installs page on the hub: sites shown as cards with search and filters, Update selected for all or one plugin, and modules and Email Signatures limits set on several sites at once.

= 0.4.3 =
* Elementor Tweaks: an Image widget with an SB aspect ratio and a link no longer collapses to nothing when the image loads lazily (the 4th image on a page onwards).

= 0.4.2 =
* SB Image Cleaner: Select all and Select none also sit beside the Add ticked to Old thumbnails to clear button, under the leftover sizes list.

= 0.4.1 =
* SB Image Cleaner: the Rebuild Thumbnails panel now lines up with Sizes, and the two stack on narrow screens and phones.
* SB Image Cleaner: a size group from a WPCodeBox or Code Snippets snippet links its title to that snippet.

= 0.4.0 =
* SB Image Cleaner now scans every image, including ones another plugin hides from the media library (customer avatars from YITH My Account), so their missing thumbnails show and get built.
* Sizes another plugin registers only in the admin (LearnDash Visual Customizer's course_icon) are remembered when SB Image Settings or SB Image Cleaner is opened, so they are never listed as leftovers to clear.
* SB Image Cleaner groups other image sizes by the plugin, theme or code snippet that registers them (for example a WPCodeBox snippet, Modern Events Calendar or WooCommerce), with anything it cannot trace under Unknown source. Leftover sizes show where their name still turns up, usually a plugin or snippet that is switched off.
* Leftover elementor_custom_ sizes are labelled as made by an Elementor widget's Custom image size, with a warning when a widget on a page is still set to that size. Scan on SB Image Cleaner also refreshes where each size comes from.

= 0.3.5 =
* SB Image Cleaner: the site icon's sizes no longer appear under leftover sizes from other sources, and Build Thumbnails puts them back whichever sizes are ticked.

= 0.3.4 =
* SB Image Cleaner: Remove old sizes and Find leftover thumbnails now handle ShortPixel's WebP copies (photo-480x320.webp), which were left behind before.
* SB Image Cleaner: the site icon's own sizes (the 32, 180, 192 and 270 pixel favicons) are never cleared any more, and Build Thumbnails makes them again where they were removed.

= 0.3.3 =
* SB Image Cleaner: the Rebuild Thumbnails buttons now stay at the top, under the instructions, so scan results and lists open below them.
* SB Image Cleaner: a second row of the Rebuild Thumbnails buttons now shows under long lists (orphaned images, leftover thumbnails, images to build, and the log a run leaves).
* Modules page: modules a site can't use (Bricks Tweaks without Bricks, Woo Tweaks without WooCommerce and the like) are no longer shown.

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
