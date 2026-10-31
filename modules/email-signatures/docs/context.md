# Email Signatures module

Staff email signatures, built for The Cosmetic Studio Noosa in October 2026 to
replace MySignature, whose templates could not be edited. Read the framework
notes first, at the plugin root in docs/context.md. This file covers only what
this module does.

It started as a standalone plugin (SocialBUMP Email Signatures 1.1.0, never
installed anywhere) and moved in as a module before its first release, so
there is nothing to migrate and nothing to retire.

## What it does

Its own SB Email Signatures menu, with no Features page (the framework's
'menu' => [ 'features' => false ], added for this module: see the framework
notes, Modules of pages):

| Page | What it is |
| --- | --- |
| Signatures (sb-email-signatures-signatures) | a card per person (photo or a grey stand-in, name) with Copy signature, Copy link and Open link; the menu and the module's own address open here (added 28 October 2026) |
| People (edit.php?post_type=sb_emailsig) | one post per person |
| Add Person | the person's fields, the photo crop box and a live signature with Copy |
| Reports (sb-email-signatures-reports) | clicks on signature links by period, person, link and device (added 28 October 2026) |
| Templates (sb-email-signatures-templates) | every template with a preview, Main and Company set badges, Edit styles, Duplicate and Delete (not the main one) |
| Edit styles (...-templates&edit=<id>) | one template's colours, font, text styles and icon style, with a live preview |
| Companies (sb-email-signatures-companies) | a card per company (logo, name, website, people, templates, main template) with Edit and Delete (not the default one); &company=<id> or &company=new is a company's page: Company (with its main template and set), Branding, Social Media, Footer, Campaign Banner (added 30 October 2026) |
| Settings (sb-email-signatures-settings) | what is the site's own, by tab: Team Pages (Team Member Post Type), Tracking Codes, Emails |
| Documentation | hub only |

The nav reads Signatures, Companies, Reports, Templates, Settings (People
was dropped from it later): the others are the module's groups, each with one
always on feature whose admin_page is the page. Signatures is listed in
'nav' too, ahead of People, so it comes first: the framework puts 'nav'
screens before the groups, and page_url() treats a 'nav' slug with no .php
as a page of ours (admin.php?page=). order_menu() puts it first in the
WordPress menu as well.

The Signatures page (SB_EmailSig_Admin::render_list(), feature
signature-list) has the team page's Copy link and Open link, then every
person with a name (published and draft), by name. Each card has an Active
switch (assets/list.js, wp_ajax_sb_emailsig_active: publish or draft, saved
straight away), the accent left border when active and grey when not, and
small buttons. Each card holds that person's signature off screen with its
HTML in a JSON script, the same as the edit screen's box, so copy.js copies
it unchanged. The name links to the person's edit screen.

The module needs ACF Pro (requires acf): the fields are ACF, registered in
PHP, because Sam wanted the values easy to pull into other layouts. Help
text sits under each input (instruction_placement field) on both the person
and settings fields. Off by default ('auto'), so a site gets it only when it
is switched on.

The people screens are WordPress's, so SB_EmailSig_Module gives them the
banner (all_admin_notices) and the framework's admin.css itself. Settings is
acf_form() inside the framework's page (SB_EmailSig_Settings), with
acf_form_head() run on admin_init for that page; the values are still ACF
options (get_field( 'sb_emailsig_*', 'option' )). The settings field group
has no location of its own; acf_form() names it.

## Companies (30 October 2026)

One site can make signatures for several businesses (Sam: an agency doing
signatures for its clients). What Settings held for the one company is now
a company: a post of type sb_emailsig_company (SB_EmailSig_Company), not
public, no WordPress screens, edited on the Companies page
(SB_EmailSig_Companies) with acf_form() on the company's post. The fields
keep their keys and names; Fields::split_groups() makes two groups from
the one definition: SB_EmailSig_Company::GROUP for the COMPANY_TABS, and
group_sb_emailsig_settings (Team Pages, Tracking Codes, Emails) for
Settings.

- Every person has a company: post meta sb_emailsig_company (the person
  field field_sb_emailsig_person_company, shown only with more than one
  company). None, or one deleted, means the default company
  (option sb_emailsig_default_company: the one Settings became).
- The company being drawn is a context, a stack: Company::enter(), leave(),
  with( $id, $fn ), current(). Data::get() runs build() inside the person's
  company and puts company_id in the data; Render::html_from_data() enters
  it again, so brand colours and icons follow. Anything reading a company
  field asks Company::source() for where (the company's post id; 'option'
  only before there are companies). Site settings (staff type, tracking,
  UTM, emails) stay get_field( ..., 'option' ).
- Templates are shared. Each company has a main template (post meta
  sb_emailsig_main_template; Templates::main_for()) and a set
  (COMPANY_SET meta; set_for(), main first). People pick only from their
  company's set; a pick outside it falls back to the company's main
  (for_person()). The person's extra templates are no longer used.
  Templates::delete() refuses any company's main, and takes the template
  out of every set.
- Icons are per company: Icons::slot() is the template id for the default
  company (so nothing moved) and "{tpl}@c{company}" for others; mine()
  falls back to the default slot. They are drawn by settings.js on a
  company's page, which SB_EmailSig_Settings::assets() loads in that
  company's context; Icons::settings_saved() only takes a company post
  saved from the Companies page. settings.js still loads on Settings (the
  Emails tab's merge tags) and finds no Social Media rows there, so draws
  nothing. Edit styles still draws icons with the default company's brand,
  so another company's icons only refresh when that company is saved.
- Campaign banners: the image address keeps ?{QUERY}=1 for the default
  company and uses c{id} for others.
- Migration (Company::migrate(), init 30, once: option
  sb_emailsig_companies_v1, a 60 second transient lock): a company from
  every options_sb_emailsig_* row that is a company field (Company::FIELDS,
  their repeater rows and ACF's _ references), the main template from
  TEMPLATE_OPTION, the set from the old set plus every template a person
  used as main or extra, then every person put in it. The options are left
  as they were, to go back to.
- The person page renders inside the person's company. Its preview takes
  the company picked but not saved (posted with the live fields:
  person_company is in Person::LIVE) through Company::force( $person, $co ),
  which of_person() honours. The template picker's choices are the saved
  company's until the person is saved.
- Deleting a company (not the default) moves its people to the default
  first, then deletes the post for good.
- Everyone's team page (the site's key, Page::list_key(): the link on the
  Signatures and Companies bars) is for administrators and editors when
  there is more than one company: logged out goes to the login and back,
  logged in without the right gets Page not found. With one company it is
  that company's page for anyone, as before companies, so shared links
  keep working. It shows each company under its own heading (with its
  team link), each with Active, Inactive and Deleted (Restore and Delete
  for good come back to the team page: Person::back_to_team()), a Company
  filter and a search. Every team page with more than one person has the
  search (name, job, email, mobile: data-sb-emailsig-text on each card,
  Admin::find_text()); list.js hides what doesn't match (.is-filtered),
  and the Active switch moves a card within its own company.
- Image fields on the module's pages (a person's Photo, a company's
  logos): the pencil is Replace (assets/replace.js, every module page): it
  opens the Media Library to pick another image (the field's
  selectAttachment()), caught before ACF's own click, which opened the
  image's details. ACF's -sync icon.
- Admin::card() draws inside the person's company, so its chips use that
  company's templates.
- With more than one company, managers see each person's company as a
  small label above their name (Admin::company_label()): on the
  Signatures page it opens the company's page, on a team page the
  company's team page. Hidden under a company's heading on everyone's
  team page.
- Filters (Admin::filters(): search, Company, Clear filters) are on the
  Signatures page bar too; cards carry data-sb-emailsig-company for it.
  Section counts follow the filter.
- Reports has a Company filter (with more than one company): clicks by
  the people in it now (Report::company_where(), deleted people too), and
  the Person menu lists only them. A person moved to another company takes
  their past clicks with them.
- Select text ran under the arrow (Every template, a company's name): the
  framework's ui.css select rule (padding-right for the arrow) was
  outranked by its shared input and select rule (input[type] adds
  weight), so the 11px side padding won. Fixed in the framework:
  select:not([multiple]), padding-right 30px.
- Team pages: each company has its own key (post meta sb_emailsig_team_key,
  Company::team_key(), made on first use) whose link shows only its people,
  in its name and main template's colour. Page::list_url( $co ) gives
  either. A company's link can't be edited into another's, as the key is
  the only thing in it. Its card on the Companies page has Copy team page
  link and Open team page.
- The nav reads Signatures, Companies, Reports, Templates, Settings
  (Sam, 30 October 2026: Companies near the top). ASENHA keeps its own
  saved submenu order on the hub (admin_site_enhancements_extra,
  admin_menu, custom_submenus_order, key sb-email-signatures); a page the
  saved list doesn't name lands in the wrong place, so that entry was
  edited to match, with Sam's OK.
- Still to come: team page per company, the agency's own Settings
  (captcha keys), install page branding, limits from the hub. Done 31
  October 2026: Admin Branding logos, Notifications, Request to be added. The Templates page's
  Main and Company set badges are the default company's.

## How a signature is made

Photo border (30 October 2026): drawn into the photo PNG
(Photo::build_ringed()), so every mail app shows it: the image keeps the
template's size, the photo is set in by the border and gap, the gap is
see-through, and the ring follows the shape (circle, rounded, square) with
soft edges (Photo::cover()). Template: Edit styles, Photo: Border (px,
blank none), Border colour (blank the primary), Gap inside the border.
Person: the Photo Crop box's Border: Default (the template's), None, or
Their own (width, colour, gap; the colour field has the brand and theme
colours menu, assets/colour.js, blank the template's colour, and keeps a
hex, var(--sb-brand-...) or a theme variable), kept in _sb_emailsig_border and read by
person_look(); the preview takes it unsaved (Photo::live()). spec only
names the border when there is one, so photos without were not remade.
Needs GD; without it the photo is made without the border.

Lines (30 October 2026): a solid line between columns is a cell of its
own, as wide as the line and filled with the lines colour
(Layout::vline()), and a solid full width line (a Divider block, Line
Below) is a div that tall, filled (Layout::rule()): as an empty table
cell the Gmail phone app shrank it to a single pixel. Both were 1px
borders, which the Gmail phone app lost when it shrank a signature to
fit the screen (Sam's screenshot: the 2px button borders stayed).
Double lines are two of these with an empty one between (see below).
The container border is still a CSS border.
The cells are empty (font-size 0): with an &nbsp; in them, Apple Mail's
compose window gave the space its 12pt font and drew a thick bar and a
wide column line (what arrived was fine).

- Person (ACF, on the post): sb_emailsig_staff_page (when Settings names a
  Team Member Post Type), sb_emailsig_name (also written to the post
  title), _qualifications (added 28 October 2026: optional, shown under the
  name with its own text style, also in Add person), _job_title, _email (a
  text field: an address or a token), _mobile, _photo (attachment id). Name,
  Qualifications, Job Title, Email and Mobile can hold staff page tokens
  (see Staff pages). Since 28 October 2026 also _person_info (their own
  Additional Info List Items, same sub fields as Settings' _info) and
  _person_info_hide (Hide the company's Additional Info List Items for this
  person). Named Additional Information until 29 October 2026, when Sam
  unified the names around the Info List (field names unchanged).
- Settings (ACF options): sb_emailsig_company_name, _phone, _website,
  _logo (image id, on the Branding tab since 28 October 2026, same field
  name; it links to the website, or nowhere without one: the separate Logo
  Link field went on 28 October 2026), _logo_small (Small Logo beside it,
  29 October 2026: an icon or mark with its own layout block and sizes;
  blank shows nothing, a stand-in in previews, never the full logo), the brand colours _brand_primary,
  _brand_accent, _brand_text and _brand_text_light (Branding tab: see Brand
  colours), _address
  (one textarea, shown as typed with its line breaks; it replaced four
  separate fields on 28 October 2026), _maps_link, the _info repeater
  (Additional Info List Items, Company tab, 28 October 2026: info_icon, a name
  from SB_EmailSig_Fields::info_icons() or '' for none; info_label, which
  names the line in Reports and is for the coming Label Style; info_text;
  info_link, optional, https://, mailto: or tel:, tracked like the others;
  info_id), the _social_media
  repeater (social_media_name, _link, _icon, _icon_file), _social_heading
  (28 October 2026: optional text above the social icons, such as Follow
  us:, styled by the social_heading item on the Social icons card),
  _signoff,
  _vcard_show, _vcard_label, the _buttons repeater (button_label,
  button_link, button_outline: Additional Buttons such as Book Online, each
  solid or outline), the _banners repeater (banner_image, banner_link),
  _fine_print (labelled Footer Text since 28 October 2026; the field name
  stayed so saved text was kept). The tab with sign-off, vCard, buttons,
  banners and footer text is labelled Footer since 28 October 2026 (Sam
  found Signature confusing; its key, field_sb_emailsig_tab_signature,
  stayed). Additional Buttons and Banners show their help under the field
  name (wrapper class sb-emailsig-help-top, admin.css), and Banners and the
  Campaign Banner Width give the best image size for the width they show
  at: twice it, at 4:1 or 6:1, e.g. 1200 x 300 or 1200 x 200 for 600
  (SB_EmailSig_Fields::banner_help(), campaign_help()). On the Tracking Codes tab _utm_on,
  _utm_all, _utm_source, _utm_medium, _utm_campaign, _utm_content,
  _track_clicks, _keep_clicks, and on the Campaign Banner tab
  _campaign_show, _campaign_width, _campaign_default_image,
  _campaign_default_link, the _campaigns repeater (campaign_name, _image,
  _link, _start, _end), and on the Emails tab _mail_from_name,
  _mail_from_email (the part before the @ only), _mail_new_subject, _mail_new_message,
  _mail_updated_subject, _mail_updated_message. The social, button and
  banner rows each have
  a hidden fixed id (see Tracking).
- Images (logo, banners) go out at a size the template works out with
  SB_EmailSig_Data::fit( $img, $max_w, $max_h ): inside the maximums and
  never bigger than the image itself, with width and height attributes
  (height left off if the image's size isn't known), from the smallest
  registered size at least twice that wide so they are sharp on retina
  screens (::src()). PNG, JPG or GIF only: email apps don't show SVG, and
  Outlook doesn't show WebP.
- Buttons are table cells with a 2px border and a padded link, the usual
  email button: solid fills the cell (bgcolor too, for Outlook), outline
  leaves it clear; both keep the border in the button colour so they are the
  same size side by side. Outlook ignores border-radius, so they are square
  there.
- Every link in a signature opens in a new window (target _blank,
  rel noopener), for people reading in webmail. Asked for on 28 October 2026.
- Tracking (SB_EmailSig_Tracking, added 28 October 2026), on the Settings
  Tracking Codes tab. The class's header comment is the full description.
  - Track Clicks (on unless switched off): every web link in a signature is
    a short link on this site, /?sbgo={person}-{key}-t{template}-{check}
    (the template it was copied from, added 29 October 2026 at Sam's
    request: every click records its template rather than guessing from
    the person's main one, which can change; links made before have no t
    part, still work, and record template 0, Not recorded; the check
    covers the template, so it can't be edited), which counts
    the click and sends them on (302) to wherever that link goes now. The
    address is looked up at the click (SB_EmailSig_Data::targets()), so
    changing a link in Settings changes it in signatures already pasted into
    Mail. Keys: w website, l logo, o company name, m map, s social, b button, n banner (s, b
    and n plus the row's fixed id). check is 8 characters of an HMAC with the
    module's own secret (option sb_emailsig_link_secret, not wp_salt(), which
    security plugins can change). A link whose row is gone goes to the
    website. tel: and mailto: are left as they are.
  - Row ids (SB_EmailSig_Fields::ROW_IDS): social_media_id, button_id and
    banner_id, hidden in the repeaters by admin.css, made on save by
    acf/update_value (a copied row gets a new one) and, once, for rows saved
    before ids (add_row_ids(), straight into ACF's options_* storage, flag
    sb_emailsig_row_ids). A row with no id falls back to i and its place.
  - Clicks go in {prefix}sb_emailsig_clicks (clicked_at UTC, person_id, link
    key, label then, template_id (DB version 2), device mobile/tablet/desktop,
    bot), made by dbDelta
    (option sb_emailsig_clicks_db). No IP addresses. Not counted: HEAD
    requests, prefetches, people logged in who can edit posts (so testing
    doesn't count). Marked bot and left out of the report: known scanner and
    link preview user agents, an empty one, and three or more different links
    of one signature within 10 seconds (an email scanner opening every link).
    Cleared after Keep Clicks For (6 to 36 months, 24 by default), on 1 in 50
    clicks and when the report opens. vCard downloads count too, as key v
    (the link carries t={template} for the same reason).
  - Short links are sent with no-cache headers (DONOTCACHEPAGE,
    X-LiteSpeed-Cache-Control) so a page cache never keeps the redirect.
  - UTM codes: Add Tracking Codes (on), Other Websites Too (off), and the four
    codes, defaulting to email-signature, email, {person} and {link}; a code
    left blank is left off. {person} is the person's name as a slug
    (jane-smith), {link} the link's name: logo, website, map, the social's
    name, button-{label}, banner-{n}. Only links to the site's own website
    (the Website setting's host or this site's, www. ignored, subdomains
    included) unless Other Websites Too is on. Links already carrying utm_
    codes are left alone. With Track Clicks on they are added at the click;
    off, they are in the signature's links.
  - $d['website'] stays as typed (the vCard uses it); the template links
    website_link.
- Staff pages (SB_EmailSig_Staff and assets/staff.js, added 28 October 2026;
  the class header has it all). Settings, Company tab, Team Member Post
  Type (sb_emailsig_staff_type, choices from the site's public post types,
  None by default). With one set, a person has a Staff Page field
  (sb_emailsig_staff_page, post_object, hidden by acf/prepare_field
  otherwise). Their photo and name link to it while it's published, through
  the short link with key p (looked up per person at the click, UTM
  content profile; $d['profile_link'], '' without). Under the field, a panel
  shows the page's featured image (used straight away when they have no
  photo, else Use this photo; copied once, so the crop stays) and its
  fields as tokens: {title} and {field_name} for ACF fields with one value
  (SB_EmailSig_Staff::TYPES). Tokens work in Name, Job Title, Email and
  Mobile and are filled in by acf/format_value, so every get_field() shows
  the page's current value, the post title included (set from the
  formatted name since then); unknown or empty tokens come out as nothing,
  and the edit screen's box says which are empty. Email became a text
  field on 28 October 2026 so it can hold a token; validate_email() checks
  it is an address or holds a token. Data ajax: wp_ajax_sb_emailsig_staff.
- Reports page (SB_EmailSig_Report, group reports, feature
  signature-reports, added 28 October 2026, reworked 29 October 2026 at
  Sam's request; the class header has the details): filters for the period
  (7, 30 or 90 days, 12 months, or custom dates, in the site's time zone),
  person, link (the links clicked in the period), template and device, kept in the
  address; the selects show the page on change; Clear filters shows when
  any differs from the default. Tiles: clicks (and the change on the period
  before), contact saves (key v), people clicked, top link. Bars (plain
  divs, no library, in the site's accent colour, axis at a round number)
  by hour for one day, by day up to 45 days, by week from Monday up to 200,
  else by month; each bar's tooltip lists its links; clicking a day, week
  or month bar shows it. Tables by person, link, and template and device
  stacked in a third column (Sam, 29 October 2026), each name a
  filter, each count with a share bar. A filtered table keeps showing
  (Sam, 29 October 2026): its other rows (found without its own filter)
  follow greyed with 0, each a link to filter by it instead, and Reset in
  the table's corner clears just that filter (Report::table()). Scanner clicks left out and counted.
  Opening it clears clicks past Keep Clicks For. Sample clicks were added on
  the hub on 29 October 2026 to see it with data (ids in the option
  sb_emailsig_sample_clicks; delete those rows to clear them; their
  templates were filled in at random, mostly the main one).
  Planned with more than one company: a company_id column on clicks,
  recorded at the click like the template, so moving a person doesn't
  rewrite their history, and a Company filter.
- Campaign banner (SB_EmailSig_Campaign, added 28 October 2026), Settings,
  Campaign Banner tab: on or off (off by default), Width (600), a Default
  Banner and Default Link, and Campaigns (name, banner, link, start and end
  dates, either blank for open ended). The signature always has the same
  image, /?sbbanner=1, with a width and no height, alt="", and the same
  link, the short link with key c, so a banner can be changed in signatures
  already in use. The site serves the first campaign running today (site
  time zone), else the default, else a see-through PNG the banner's width
  and 1px tall, made by hand (no GD), so nothing shows. The image is sent
  from the upload at a size about twice the width (Cache-Control max-age
  300, never page cached). A note on the tab says what shows today
  (acf/load_field on field_sb_emailsig_campaign_now). In Classic it comes
  above the other banners. Not yet checked in Outlook for Windows (width
  without height) or how long Gmail's image proxy keeps an old banner.
- SB_EmailSig_Data::get( $post, $template ) gathers both into one array, the
  social icons being the ones built for that template; ::sample() is the
  same with a made up person; ::placeholders() is {Name}, {Email} and so on
  with nothing else set, for the Templates page.
- SB_EmailSig_Render runs the template with $d, $tpl and $s (its resolved
  styles) and strips the whitespace between tags.

Email clients decide most of this:

- Templates are tables and inline styles only, in fonts every mail client
  has (SB_EmailSig_Styles::fonts()).
- The photo (SB_EmailSig_Photo, reworked 28 October 2026). Each template's
  Edit styles page has a Photo card: Show, Shape (circle, rounded corners
  with a radius, square corners), Aspect ratio (1:1, 4:3, 3:4 or Custom with
  two whole numbers) and Width (the height follows). A person can have their
  own aspect ratio (_sb_emailsig_ratio: '', '1:1', '4:3', '3:4' or 'w:h'),
  chosen in their Photo Crop box, which defaults to the template's. With
  their own ratio, Shape and Corner radius show beside it
  (_sb_emailsig_shape, _sb_emailsig_radius in px at the template's width;
  SB_EmailSig_Photo::person_look() lays them over the template's look, and
  going back to Default clears all three). The width is always the
  template's. The crop
  is set in that box with Cropper.js (assets/vendor/cropper, 1.6.2) at the
  ratio the photo will be made at, the preview beside it in the shape, and
  kept as fractions of the original image in _sb_emailsig_crop; the original
  is never changed.
- assets/crop-resize.js, shared by crop.js and Add person (addperson.js):
  the photo itself stays put (no mouse wheel or pinch zoom, no dragging it;
  Sam found the wheel zoomed when he meant to scroll the page), only the
  crop box moves and resizes. Holding Alt (Option on a Mac) while dragging a
  handle resizes from the middle, as in Photoshop. Once Alt is pressed in a
  drag the script owns the resize until the drag ends (cancelling Cropper's
  cropmove stops it tracking the pointer, so handing back would jump),
  starting again from the current box each time Alt changes.
- Images are made per template, on demand, by SB_EmailSig_Photo::image()
  (called from SB_EmailSig_Render, so the template gets $d['photo']): a PNG
  at twice the display size with the shape baked in by GD (Outlook for
  Windows ignores border-radius; a circle in a non-square ratio is an oval).
  Without GD it is only cropped and the template rounds it with CSS ('baked'
  false). Kept in _sb_emailsig_photos, [ template id => file, hash, size ],
  the hash covering the photo, crop, shape and size, so a change makes a new
  file (new address, no cached old photo) and the old one is deleted unless
  another template uses the same file. Unsaved settings on Edit styles make
  one '_preview' image per person, replaced each time. A 240px square JPG
  for the vCard is made on save from the middle of the crop.
  In signatures the photo goes by a fixed address, /?sbphoto={id}-{check}
  (SB_EmailSig_Photo::fixed_url(), check from SB_EmailSig_Tracking::sign()),
  set by SB_EmailSig_Render outside previews: that address in the person's
  main template, /?sbphoto={id}-{template id}-{check} in their others
  (29 October 2026). Photo::maybe_serve() streams that template's image
  (the person's main one when the address names none, or the named one has
  been deleted; made if need
  be) with Cache-Control no-cache and an ETag (304 when unchanged), or a
  see-through PNG one pixel high when there's no photo. So a new photo,
  crop, ratio or shape shows in signatures already pasted, like the
  campaign banner, and the template draws the photo with width only and
  height auto (never a fixed height). Mail apps and Gmail's image proxy
  keep their own copies for a while. The hashed file addresses stay for
  admin previews and thumbnails.
  The People list (80px wide) and the team page cards (96px wide) show the
  person's main template's image (SB_EmailSig_Photo::png_url()) at height auto,
  with no CSS shape, so each person's own ratio and baked shape show as in
  the signature. Only the no-photo stand-in is round.
  SB_EmailSig_Photo::IMAGE_REV forces a rebuild of every photo when bumped
  (2 since the shapes; the old single circle PNG, _sb_emailsig_photo_png, is
  deleted the first time a person's photo is made again).
- A crop saved at another ratio (the template's ratio changed, or the
  person's) is fitted, in PHP and in crop.js alike: the same area at the new
  ratio around the crop's middle, which is where people put the face. No face
  detection: Cropper.js has none and the browsers' isn't dependable.
  SB_EmailSig_Photo::needs_check() is true while the crop's ratio is more
  than 2% off the ratio the site's template makes it at; the Signatures page
  then shows Check the photo crop on that card, and the crop box says so.
  Updating the person saves the fitted crop and clears it.
- Icons are worked on as SVG and sent as PNG: Gmail and Outlook for Windows
  show no SVG. assets/icon-builder.js draws an SVG to a 3x PNG in a colour,
  with an optional rounded background. SB_EmailSig_Icons checks each is a
  real PNG up to 400px and saves it in uploads/sb-email-signatures/icons/,
  listed per template in the option sb_emailsig_icons ([ template =>
  [ contact => [ name => file ], social => [ row => file ] ] ]), and removes
  files nothing lists. Contact icons are drawn on the Edit styles page from
  the template's icons/*.svg (Lucide, ISC); until then the PNGs shipped in
  icons/ are used. Social icons are drawn on the Edit styles page for that
  template and on the Settings page for every template, since the rows
  changed there. Built in social icons are the Simple Icons SVGs in
  assets/social (CC0); a custom one is an SVG picked from the media library
  (social_media_icon_file, an attachment id; before 28 October 2026 it was
  pasted code, which nobody had saved). SB_EmailSig_Icons::file_svg() reads
  it, cleaned by clean_svg() (no scripts, foreignObject, on* attributes or
  javascript: links); the Settings preview gets a newly picked one through
  wp_ajax_sb_emailsig_svg, and settings.js swaps ACF's file icon in the field
  for the SVG itself. Uploads through that field (ACF sends
  _acfuploader with the field key) may be SVG for administrators even where
  the site doesn't allow SVG (Bricks does), and are cleaned the same way on
  the way in. Recolouring sets
  every fill and stroke to one colour, so a multi colour SVG comes out in one
  colour.

## Templates

### Templates are records (29 October 2026)

A template is a hidden post of type sb_emailsig_tpl (SB_EmailSig_Templates):
its post ID is its id (a string everywhere: PHP turns a key like '12' into a
number, so use each entry's id, not its array key), its title the name, and
it keeps the design it is drawn with (_sb_emailsig_design, a folder below)
and its saved styles (_sb_emailsig_styles). Duplicate (Templates page)
copies the design, styles and drawn icons into a new record and opens its
Edit styles page; Delete removes any but the main one (and its icons).
Before records a template was its folder, keyed by folder name, with its
styles in sb_tweaks_email_signatures_styles and names in
sb_tweaks_email_signatures_template_names. Templates::migrate() (init, once,
with a one minute lock) makes a record per folder from those, moves the
site's choice to the record's id, copies the drawn icons to the new id and
sets the company set to the main one; the old options and the icons under
folder names stay for going back. sb_emailsig_template_ids maps folder to
id, so an old id (such as edit=classic) still finds its record. With no
records, the folders are the templates as before. Proved on 29 October 2026
with the harness: the same HTML from the old code, the new code before the
move and after it.

Who uses which:
- The company's main template, sb_tweaks_email_signatures_template (set on
  Settings, Company tab, Main Template, kept in that option by
  Fields::main_template_save()), is everyone's unless their own says
  otherwise.
- The company set, Templates (ACF option sb_emailsig_templates): the
  templates everyone can pick. The main one always is.
- On a person (only with more than one template): Main Template
  (sb_emailsig_template, blank for the company's) and Extra Templates
  (sb_emailsig_templates_extra, listing only templates outside the company
  set). Templates::for_person() is their main (theirs if it still exists,
  else the company's); ::available() is their main first, then the company
  set and their extras, in the order made. Their own main counts as
  available even outside the set.
- Their signature page shows a Signature style dropdown when they have more
  than one: every available template is drawn when the page loads
  ({preview id}-styles JSON), and assets/copy.js swaps the signature, the
  copy buttons' HTML and copy record, and the HTML code box.
- A copy of a template other than their main is recorded in
  _sb_emailsig_copied['t'][ template id ]; Changed since copied counts
  those too while the template is still theirs (Notify::state()).
- The team page, the Signatures page and the People list show each
  person's main template.

People export and import (29 October 2026, SB_EmailSig_Transfer, the class
header has the details): a People file row on the Signatures page, under
Team page, for administrators. Export is a CSV of everyone (UTF-8 with a
BOM for Excel; Info N columns for as many lines as anyone has; a cell
starting = + - or @ gets a leading apostrophe, taken off on import).
Import reads the file into a preview (a transient per user, an hour; the
Signatures page with people=preview) before anything is saved. Matching is
by email; Sam asked (29 October 2026) for a warning and a choice per row,
as staff can share an email: Update (one match starts here), Skip (more
than one match starts here) or Add as a new person. An update writes only
non blank cells that differ from what shows (so staff page tokens stay);
Info lines only when the row has some, a line already there keeping its
row id and click history. Photos: an address in this site's media library
is used as it is, else downloaded (media_sideload_image); Photo Crop
applied when given (Photo::set_crop()). Never deletes anyone. Left out: the
Staff Page, the page key, a person's own photo ratio and shape, and copy
and click history.

Template export and import (29 October 2026). Each card has Export, with a Lock
colours tick: a .json download (Templates::export(): type
sb-email-signature-template, format 1, name, design, locked, exported,
from, styles; format 2 since the layout editor, the layout being in the
styles). Styles are SB_EmailSig_Styles::sanitize() of values(), so the
file holds every setting, not only the ones saved. Without Lock, brand and
theme colours stay links (var(--sb-brand-primary) and so on) and take the
other site's colours; with it, Styles::locked() writes each as the hex it
shows now and fills the blanks that follow a brand colour (primary, text,
and items with a fallback). Import, above the cards: the file (200 KB at
most) is checked for its type, a format no newer than FILE_FORMAT, and a
design this site has; its styles go through sanitize() against the design's
defaults, as a save would; it always makes a new template, its name getting
(imported) when the site already has one by that name, then opens its Edit
styles page, where saving draws its icons. Proved with the harness: the
file reads back through sanitize() unchanged, and a template made from it
draws the same HTML as the original, locked or not. The layout joined the
file on 29 October 2026 (format 2).

### The layout editor (29 October 2026)

Sam's design (28 October 2026), built the day after templates became
records, while he was out, to tweak with him. SB_EmailSig_Layout (the
class header has the shape) and assets/layout.js (SortableJS 1.15.6 in
assets/vendor/sortable, MIT).

- The layout is part of the template's styles, styles['layout'], so it is
  duplicated, exported and imported with them. It has its own page since
  the afternoon of 29 October 2026 (Sam: the Layout card on Edit styles
  became a page): Edit Layout, beside the template's name on Edit styles,
  opens ...-templates&edit=<id>&view=layout in the same window (Sam: not
  a new one) (Styles::render_layout()), the editor beside a preview. It saves on its
  own (admin_post sb_emailsig_save_layout, Styles::save_layout(): the
  layout over the template's saved styles), and Edit styles, which no
  longer posts a layout, keeps the saved one (Styles::with_layout()), so
  the two pages never overwrite each other. Its preview asks
  wp_ajax_sb_emailsig_preview with scope layout (the saved styles with
  this layout). The layout is one hidden field, s[layout], as JSON, so the
  unsaved changes check and the card's Reset (back to the design's) treat
  it like any other. The design's styles.php gives the default.
- Default Spacing and Default Line Width (Sam, 29 October 2026): block_gap
  (labelled Default Spacing) and line_width. Every space (a block's Space
  above, name and Info List spacing, a column's top and bottom, a gap's
  space, padding, the footer divider's spacing) is picked as Default,
  Double, Half, Quarter or Eighth of the Default Spacing (each showing its
  px now), None, or Custom px, and saved as that step or a number, so a
  change to the Default Spacing carries through. Every line (a gap's line,
  a divider, the footer divider) is Default (the Default Line Width), None
  (not a divider) or Custom px. Layout::px() and lpx() work them out; old
  saved numbers stay as Custom, and a block's -1 reads as Default.
  Classic's default uses steps where they match (company Half, name
  Quarter, column 3's top and the footer spacing Double, its column line
  and footer line Default), so it draws the same.
- A divider block has its own colour (Sam, 29 October 2026), picked in its
  popup with the same swatch and theme colours as the cards; blank uses the
  Lines colour.
- Shape: 1 to 3 columns (width, 0 for auto; vertical align, '' for the
  layout's default; top and bottom space inside the column, so a line
  beside it runs past its blocks; blocks), two gaps (space, and line: its
  thickness, 0 for none), the default vertical align, space between
  blocks, padding, border per side and its colour ('' the lines colour),
  a background ('' none, bgcolor too for Outlook), the line above the
  footer (line_below: its width, meaning thickness, 0 for none;
  line_colour, '' the lines colour; line_space, px above it, 24), and the
  holding area. Line thicknesses came in that afternoon at Sam's request; the 0
  and 1 saved before read as none and 1px. A block: type, id (Info List and Divider only), align
  (left, center, right), space above (-1 for the default), its own options.
  Layout::sanitize() writes it in one exact key order and layout.js's
  canon() the same, so an unchanged layout compares as unchanged (the card
  stays unmarked, nothing to save).
- Blocks: Photo, Name and job title (name, qualifications and job title,
  stacked or on one row with a separator, and the spacing between them:
  3px by default), Company name, Logo, Small logo,
  Info List (as many as six), Save my contact details, Social icons (row
  or column, heading on or off, line up with the Info List's text past its
  icons), Divider (as many as six, each with its thickness). Every single block is in the layout
  exactly once: in a column or the holding area ("Not shown"), which
  replaced show and hide ticks. Settings decides what exists, the layout
  where it shows.
- An Info List holds Phone, Mobile, Email, Website, Address, each of the
  company's Additional Info List Items on its own (c:<row id>, so they can
  be split across Info Lists; Sam asked that afternoon, as the first build
  had them as one entry) and the person's own as one entry (they differ
  person to person), in any order, each ticked or not, stacked or on one
  row with a separator (Line, Dot, Small dot, Slash or a space) and its
  spacing, and its own Icon display (Default, the Info List Styles card's;
  Icon, First letter, Full label or None). Select all and Select none
  above the items. A company item added in Settings later turns up ticked in the
  Info List holding the company's items (the first in a column with one
  ticked), after them, unticked in the others; a deleted one drops out
  (Layout::sanitize(), each time it's read). Its settings popup shows each
  company item's text and notes an item also ticked in another Info List
  in a column. The icon style (Info List Styles) and tracking are shared
  by every Info List.
- Always outside the columns: the sign-off above; the line (if on),
  buttons, footer text, campaign banner and banners below.
- Drawing (SB_EmailSig_Layout::html()): one row of column cells; a column
  with nothing to show (no photo outside previews, say) is left out with
  its gap. A gap with a line gives the space either side of a 1px line in
  the lines colour. Each block is a row in its column with its align on the
  cell; images inline and top aligned when centred or right, so Outlook
  centres them too. Columns never stack on phones (Outlook ignores the CSS
  that would).
- Editor: the Layout page. Columns, default vertical align and space
  between blocks on top; the columns side by side, each block a card you
  drag (between columns and the holding area), with an align button that
  cycles left, centre, right, a cog for its settings (also a double click)
  and, for Info List and Divider, a remove cross. A column's cog: width,
  vertical align, space at top and bottom. The strip between two columns
  shows the gap (dashed, solid with a line) and its px; click it for space
  and line. Add Info List and Add Divider put a new one at the end of the
  last column. On the block an Info List lists what it holds, one per line.
  Cards, top to bottom (Sam, 29 October 2026): the layout card, Layout
  Settings, Container Settings, Footer Settings, each with its own Reset
  (Container and Footer Settings reset only their keys, layout.js PARTS).
  Every card's Reset, here and on Edit styles, is always shown, greyed out
  (disabled) until the card differs from the default, so the buttons
  beside it never move (Sam, 29 October 2026).
  The layout card's head has Undo and Redo (also Cmd or Ctrl Z, Shift Z or
  Ctrl Y, outside text boxes): snapshots of s[layout] and the Layout
  Settings fields, up to 100, one per change once it settles (400ms); a
  popup counts once, as it closes with Save. A block's X asks first
  (SBTweaksConfirm), and Undo brings it back. Reset Template (red, left of
  Undo) asks, then puts the layout and Layout Settings back to the design
  (one Undo step, saved only with Save changes; Edit styles' own values
  are untouched). It is greyed out until something on the page differs
  from the design. Its popup has a tick to reset Edit styles too, and
  Edit styles' Reset to defaults has one to reset the Layout page too
  (Sam, 29 October 2026): ticked, the form posts sb_emailsig_reset_other
  and saves straight away, the other page's styles taken from the design
  (layout_page_values() and with_layout() with $reset), and the icons
  are drawn as the design has them (icon_setup() defaults on the Layout
  page; cfg.saved set to the design's Primary and Text on Edit styles).
  Layout Settings holds
  Primary, Text, Lines and Font (Colours and font on Edit styles until
  then; a design without a layout still has that card there) and the
  Default Spacing, Default Line Width and Default Line Style; the layout
  card's bar keeps only Columns and Vertical align. The colours and font
  are ordinary named fields PHP draws (colours_and_font()); layout.js gives
  them swatches and theme colours, and the card's Reset puts them and the
  three defaults back. Styles::LAYOUT_PAGE lists what the Layout page
  owns: its save and preview put those over the saved styles
  (layout_page_values()), and Edit styles keeps them as saved
  (with_layout()); styles.js reads Primary and Text as saved (cfg.saved)
  when its form hasn't got them. A new Primary redraws the contact and
  social icons on the Layout page too (icon-builder.js, Styles::icon_setup()
  for the SVGs and saved icon styles), shown in the preview and sent with
  Save as sb_emailsig_icons, as Edit styles does. Container Settings:
  padding and background colour, then a Border row (top, right, bottom,
  left, style, colour). Footer Settings: Footer Divider (width, style,
  colour, spacing above) and Footer Controls, ticks to leave parts out of
  this template's signatures: Hide Buttons, Hide Banners, Hide Campaign
  Banner, Hide Footer Text (hide_buttons, hide_banners, hide_campaign,
  hide_text; their preview placeholders go too). Line styles: Solid,
  Double (Layout::STYLES; dashed and dotted went 31 October 2026, and
  saved ones read as solid); line_style is the default, and
  a gap's line, the border, a divider and the footer line
  (line_below_style) are Default or their own. Lines and the border are at
  most 8px (Layout::MAX_LINE): Outlook for Windows draws no thicker
  border (caniemail.com, CSS border). Their
  colours are the Edit styles colour field (swatch, value, theme colours
  menu; a blank one shows what it falls back to, dashed). Labels sit in a
  line across the top and every box is 36px high (Sam found them uneven).
  A block's Space above is never used for the first block in a column: the
  column's own space at the top sets that. Settings popups are the framework's (SBTweaksConfirm): the
  preview follows every change in them, Save keeps it, and Cancel, the X,
  Escape or a click outside put it back (Sam, 29 October 2026). With the
  preview beside the editor, a popup sits over the editor's column and
  only that side is dimmed, so the preview stays in view (layout.js
  dock()); stacked on a narrow screen it stays centred. Fewer
  columns hands the last column's blocks to the one before it.
- Classic's default layout is the signature as it was drawn before:
  checked side by side in the harness, the same but for 2px (the gap
  after the line was 32 then, 30 now). Its signatures' HTML changed with
  the switch, so everyone's shows Changed since copied once.
- Templates saved before with the photo off (the Photo card's Show tick)
  start with the photo in the holding area (Styles::layout_for(), also for
  format 1 export files). photo show is kept in step with the layout, as
  the photo code reads it.
- Export files are format 2 since then (the layout is in the styles); a
  site on the older code refuses them as newer.
- Not done yet, ideas: the sign-off as a block, a text block, more than one
  social list, per side padding.

### Making a design

A design is a folder in templates/: the folder name is its id, and adding
one is adding a folder (sb_emailsig/designs filters the list). Templates
are made from designs. It holds:

- template.php: the signature's HTML. Its name and description are a header
  comment (Template Name:, Description:), read with get_file_data(); a
  template made from it starts with that name, changed on Edit styles.
- styles.php: returns the template's defaults, and so what its Edit styles
  page offers. Every group is optional and a card only shows for a group the
  template has:
  - primary, text (colours, '' for the brand's primary and text: leave
    them blank in styles.php), font (a key of SB_EmailSig_Styles::fonts()),
    lines (colour, '' for primary).
  - items: each text part the template draws: label, size, weight, style,
    case, colour ('' for text, or for the brand colour named in fallback,
    e.g. 'fallback' => 'text_light'). The Text card.
  - icons: colour ('' for primary), size, names (the contact icons it uses,
    each icons/<name>.svg, with icons/<name>.png to fall back on).
  - social: background (1 or 0), bg, colour, radius, size. The Social icons
    card.
  - signature: width (the max width). logo: max_width, max_height (0 for
    none). The Sizes card.
  - photo: show, shape, radius, ratio, ratio_w, ratio_h, width. The Photo
    card. With a layout, show follows the layout (the photo in a column or
    not) and the card has no Show tick.
  - layout: the starting layout (see The layout editor). A design with one
    is drawn by SB_EmailSig_Layout::html(): Classic's template.php is just
    that call now. logo also takes small_max_width and small_max_height
    for the Small logo.
  - buttons: bg, colour, outline_colour, radius, text (the text size, 10 to
    24 px; padding and line height follow it), weight (default Normal,
    400, since 28 October 2026 at Sam's request; was Bold), case. The
    Buttons card. The Sign-off text also defaults to Normal (400) since the same
    evening (was Bold). Before 28 October 2026 it was size (the height, 22 to 50), which
    made small buttons sit badly; a saved size becomes text at size x 0.42
    when read (SB_EmailSig_Styles::merge()).
- Anything else it needs (Classic: icons/).

template.php is included by SB_EmailSig_Render::html_from_data() with:

- $d (SB_EmailSig_Data::get(), or ::placeholders() on the Templates page):
  name, qualifications, job_title, email, mobile, mobile_link, company,
  phone, phone_link,
  profile_link (their staff page, or ''), website (as typed: show
  website_label, link website_link, which carries
  the UTM codes), website_label, website_link, address, address_lines,
  maps, social_heading ('' for none), info (the Additional Info List Items: the
  company's unless the person hides them, then the person's; each icon ('' for
  none), label, text, link), info_company and info_person (the same two
  apart, for the layout's Info Lists), socials (row, name,
  link, icon), logo and logo_small (id, url, width, height, alt, or null),
  logo_link (both logos), company_link
  (the website, when both are set), buttons (label,
  link, outline), campaign (image, width, link, or null), banners (image,
  link), signoff, vcard_show, vcard_label,
  vcard_url, fine_print, photo (url, width, height, baked, shape, radius, or
  null; outside previews url is the photo's fixed address, so draw it
  with a width only and height auto),
  preview (true in previews only), id and template.
- $s (SB_EmailSig_Styles::resolve()): every colour as hex, font, lines,
  css[ item ] (a ready style string), colour[ item ], case[ item ], icons
  (show: icon, letter, label or none; colour, bg ('' for none), radius,
  size, names, label: a ready style string for letters and labels, whose
  case is case[ 'icon_label' ]),
  social (bg, colour, radius, size, gap), logo, width, photo (show, shape,
  radius, width, ratio as width over height), buttons (solid and outline
  looks, radius, font (the text size), line (1.2 times it), pad_y (0.4
  times), pad_x (1.15 times), weight, gap: like em, but in px, as Outlook
  ignores em padding).
- $tpl: id, name, path, url.
- $s['layout']: the template's layout (see The layout editor), for designs
  with one.

Rules every template keeps, all in Classic as the example to copy:

- Tables and inline styles only, fonts every mail client has, images with
  width and height attributes, PNG or JPG only (no SVG, no WebP), no
  border-radius that matters (Outlook ignores it: the photo has its shape
  baked in).
- Text goes through $case( item, text ) so the item's case setting applies.
- Links come from $d as they are: they already carry the UTM codes. Link the
  web address with website_link, never website.
- Every link opens in a new window: target="_blank" rel="noopener" ($new in
  Classic), for people reading in webmail.
- Contact icons come from SB_EmailSig_Icons::contact_url( $tpl, name ) and
  carry $mark( 'data-sbes-icon', name ) (social ones data-sbes-social, row),
  so the Edit styles preview can show unsaved colours. $mark is empty
  outside previews.
- Anything not set shows as $ph( 'name' ) (a grey dashed {name here}) in
  previews only; the photo's stand-in is the photo's size and shape.
- Images: SB_EmailSig_Data::fit( image, max width, max height ) for the size
  (never bigger than the image) and ::src( image, width ) for a sharp file.
- Whitespace between tags is stripped when rendered, so indent freely but
  never rely on a space between inline tags.

The site's choices are kept on each template's record (_sb_emailsig_styles;
SB_EmailSig_Templates::stored_styles() and save()). A blank colour falls back: lines, icons and the
social background to the primary colour, an item to the text colour.
SB_EmailSig_Styles::sanitize() and merge() know each group, so a new group
needs adding there, to resolve() and to render_editor() as well.

Each item also has a case (As typed, UPPERCASE, lowercase, Capitalise
Words), applied to the text itself in PHP (SB_EmailSig_Styles::cased(),
called through $case() in the template) rather than CSS text-transform, so
it holds in every mail client and in the plain text copy.

A colour is a hex or a theme colour, var(--name). SB_EmailSig_Styles::
palette() lists the theme colours from Bricks' colour palettes (Core
Framework fills these; rawValue.light used) and theme.json colours that have
a real value, for the menu. The generated shades (var(--primary-d-4) and so
on, isShade in Bricks) are left out of the menu but can be typed: colours()
maps every var, shades too, to its hex, and hex() and styles.js use that.
to_hex() turns hsl()/rgb()/hex into #rrggbb, see-through ones over white.
The var is what is saved, and resolve() turns it into the hex each time the
signature is drawn, so text and lines follow the theme. Icons are PNGs, so
they only take a new theme colour when the Edit styles page is saved again.
A var no longer in the theme falls back to the template's default.

Brand colours (28 October 2026, groundwork for more than one company on a
site, which isn't built: see Later). Settings has a Branding tab: the
logo, then Brand Primary, Brand Accent, Brand Text and Brand Text Light,
each a hex or a theme colour (never another brand colour), picked with the
same swatch and theme colour menu as Edit styles (assets/settings.js on
fields with the wrapper class sb-emailsig-brand-colour). Never blank: a
blank box saves the default (#000000, #333333, #777777; Accent blank means
the primary), SB_EmailSig_Fields::brand_value() and brand_valid().
SB_EmailSig_Styles::brand() reads them each time, as [ key => name, value,
hex ]; their value is a token, var(--sb-brand-primary), var(--sb-brand-
accent), var(--sb-brand-text), var(--sb-brand-text-light), which templates
save like a theme colour and hex() looks up. The Edit styles colour menu
lists them first (editor_palette(), a line before the theme's). A template's
Primary and Text left blank are the brand's (placeholder Brand Primary and
Brand Text), and every other colour defaults from those as before, so a
template follows the brand until it sets its own; Reset to defaults goes
back to the brand. Footer text defaults to Brand Text Light. Icons are PNGs,
so the Settings page now draws every template's contact icons as well as
its social icons, in the brand colours as the form has them
(social_styles() says which brand colour each comes from), and saves both
on save. Later, with several companies, the person's company would supply
its own brand set, and Edit styles would get a company picker to set a
template's look per company.

The Edit styles page (Styles::render_editor) is two columns: the settings,
and a sticky preview scaled to fit its column (CSS zoom, never above 100%),
stacking at 1200px. Cards, in the order Sam set: Template (its name: blank
uses the design's name; the record's title; Edit Layout beside it opens the
Layout page, see The layout editor), Sizes (signature, logo and small logo), Photo (no Show
tick with a layout), Colours and font (only for a design without a
layout: otherwise Layout Settings on the Layout page), Text (each part's name, small and in
capitals, above its fields; card titles are larger so they stand out),
Info List Styles, Social icons, Buttons. Info List Styles (28 October 2026,
named Info icons until Sam renamed it on 29 October 2026) sets what
starts each Info List line: Label style Icon
(icon colour, background with its colour and corner radius, size), First
letter or Full label (label colour, weight, style, case; the contact
text's size), or None (the first column goes). Vertical align (top,
middle, bottom; top by default, hidden for None) sets both cells of each
line, so an icon or label sits level with its text. Built-in lines are labelled
Phone, Mobile, Email, Website, Address; Save my contact details has none;
Additional Info List Items use their own label. A blank icon colour is
the primary, or white once there's a background. The Contact icons colour
moved here from Colours and font (same field). when() takes several values
split by |. Colour fields are the framework's sb-tweaks-colour
markup plus a theme colours button; a blank one shows a dashed swatch of
the colour it falls back to. A field that only counts for one value of
another (Corner radius for Rounded corners, the custom ratio numbers) is
wrapped by Styles::when() and shown by styles.js only while it counts.

It previews live: assets/styles.js posts the unsaved values to
wp_ajax_sb_emailsig_preview every time they change and swaps the icons in
the preview (marked data-sbes-icon and data-sbes-social, only in previews)
for ones drawn in the unsaved colours. Saving waits for the icons, then
sends them with the form. The person picked in the preview is remembered
per user (user meta sb_emailsig_preview_person, written by the preview
request whenever it changes), saved or not, and the page opens on them;
if they've gone, the first person (Sam, 28 October 2026). Reset to defaults asks first in the framework's
popup (Cancel, the X, Escape or a click outside leave it), then fills the
form with the template's defaults and saves, which reloads the page (since
28 October 2026, at Sam's request; before, nothing changed until Save).
Each card also gets an
accent left border and a small red Reset at its top right once anything in
it differs from the template's default (data-default on each field), and
that Reset puts back just that card.

The framework's save-state.js counts every named field in a data-sb-dirty
form as a change, hidden ones too. So the icons field (here and on
Settings) gets its name only when the form is sent, and once the page has
drawn its icons the script dispatches sb:saved on the form, unless the user
has already changed something. Without that both pages showed Unsaved
changes on load and warned on leaving. The Save button has no name: a field
named submit hides form.submit(), which made the first click do nothing.

Module assets are versioned with the plugin version plus the file's time
(SB_EmailSig_Module::asset_version()), because the plugin version doesn't
change between hub edits and browsers kept the old admin.css.

The company's main template is sb_tweaks_email_signatures_template (a
template id). The first template is used if it disappears. The Templates
page no longer has Use this template: the choice is on Settings.

Classic: the client's old signature (Kind Regards, photo, name, job title and
company, a divider, phone, mobile, email, website, address, Save my contact
details, social icons, a line, fine print), plus the logo above the contact
details. Under the line: the Additional Buttons in one row, the footer text,
then the banners (order set by Sam on 28 October 2026). Gaps above the
lines under the name (Sam, 28 October 2026): qualifications and job title
3px, company 6px. On the person's edit screen Name, Qualifications and Job
Title share a row, Email and Mobile the next. Its Edit styles page
adds a Buttons card (colour, solid text colour, outline text colour, radius,
size, weight, case) and a Sizes card: signature max width (default 600),
logo max width (default 180) and logo max height (default blank, saved as
0: no height limit, the width alone decides). All are maximums: the
signature can be narrower, long footer text and banners fill up to the max
width, banners shrink with the window (width 100%, max-width their own
size) and are never shown bigger than the image. The main block can't wrap
(names, email addresses), so if its content is wider than the max width the
signature is that wide. Saved values from before (logo width, banners
width) are ignored. A blank colour field reads Default.

The Templates page shows each template as a card in two columns (name,
badge (Main, or Company set), the design's description and buttons, then
the preview; one column under 1100px), the main one first, with
placeholders only
(SB_EmailSig_Data::placeholders()), not anyone's details, so it looks the
same with or without People. Edit styles shows the full live preview with
the person picker. In previews, anything not set (photo, logo, social icons,
buttons, banners, footer text) shows as a grey dashed stand-in ($ph() in
the template, keyed off $d['preview']). The preview's person picker
(no name, so not an unsaved change) sends person with the preview request;
Styles::requested_person() only accepts a published person.

Classic's default primary colour, #407d82, was sampled from the client's old
signature.

## Getting it into Mail

Each active (published) person has a page at /?sb_emailsig=<id>&key=<key>
(SB_EmailSig_Page): noindex, never cached (DONOTCACHEPAGE, nocache headers,
X-LiteSpeed-Cache-Control), with the signature, Copy signature and install
steps under a mail app picker (since 28 October 2026, Sam's order; tabs at
first, then a dropdown with each app's icon and name, as people won't know
the icons alone; icons Sam supplied, 40px PNGs in assets/guides/): Apple Mail, iPhone or
iPad, Gmail, Gmail phone app, Outlook.com or Office 365, Outlook desktop app
(new and classic Windows, Mac), Outlook phone app, Thunderbird, Yahoo Mail, HTML code (Copy HTML code plus the
code in a read-only box, for other apps and tools). The steps were written from each
maker's own help pages that day (Google, Microsoft, Mozilla, Yahoo), in our
words. Thunderbird takes HTML source, so its tab has Copy HTML code
(data-sb-emailsig-copy-code in copy.js: the raw HTML as plain text; it also
records the copy; the HTML code tab has the same button). The picker is in copy.js too
([data-sb-emailsig-picker]: a button and a listbox; click, arrow keys,
Enter, Escape, a click outside closes it).
Copy signature writes our exact HTML to the clipboard (ClipboardItem), not
a copy of the page selection, so the usual "use Chrome or Firefox, not
Safari" advice from signature sites shouldn't apply; only old browsers fall
back to the selection. Pastes into Gmail and Outlook haven't been tried yet.
Phone apps (added later that evening): Google says the Gmail app uses the
computer signature when the app's Mobile signature is empty, so that guide
sends people to set it up on a computer and leave the app's empty (menus
differ a little on iPhone and Android, noted inline). Microsoft gives
Settings > Mail > Signature (Per Account Signature) for Outlook on iOS and
Android; whether a pasted designed signature keeps its formatting there is
unconfirmed (a Microsoft Q&A answer says plain text), hence the note.
The key is random per person
(_sb_emailsig_key), so the pages cannot be guessed. A draft person's page
says not found. The edit screen and the Signatures page link to it and copy
the link.

The team page, /?sb_emailsigs=<key> (views/signatures-page.php, added 28
October 2026 at Sam's request), shows every active person as a plain white
card: photo, name and small Copy signature, Copy link and Open link buttons
in the template's primary colour. One random key for the site
(sb_tweaks_email_signatures_list_key), the same headers as a person's page.
Linked from the top of the Signatures page. Logged in administrators and
editors (SB_EmailSig_Admin::can_manage(): edit_others_posts) also see the
people switched off, faded, and the Active switch on each card (list.js,
the same wp_ajax_sb_emailsig_active, which checks can_manage() too); active
cards get a left border in the primary colour.

Request to be added (SB_EmailSig_Request and assets/request.js, 31 October
2026; it replaced the quick Add person popup of 28 October 2026, which was
for administrators and editors only and published straight away): on a
company's team page, visitors who can't manage it (not can_manage()) get
Submit New Team Member in the bar (Sam renamed it from Request to be added;
the popup is Submit New Team Member for Review, its words about "them", as
someone may submit a colleague). The popup has Name, Qualifications, Job
Title, Email, Mobile and Choose a photo (straight into the Media Library, at
Sam's choice; JPG, PNG or WebP, up to 10MB), cropped with Cropper.js at the
company's main template's ratio and shape, sent with the details in one go,
so nothing is uploaded unless it's sent. The person lands as a draft
(Inactive) in that company with sb_emailsig_requested (the time) and a
Requested chip on their card until they are made Active, which clears it.
Spam, with no captcha keys yet (Sam: honeypot and rate limit): a field
people leave empty (sb_hp; a bot filling it is told it worked and nothing is
kept), a token signed for the company fetched when the popup opens
(wp_ajax(_nopriv)_sb_emailsig_request_token, so a cached page can't serve a
stale one) and good from 3 seconds to 2 hours old, and at most 5 requests an
hour per visitor (IP, hashed) and 30 an hour for the site. The email about
it is Settings, Admin Notifications' New Team Member Submitted.

Telling people (SB_EmailSig_Notify and assets/notify.js, added 28 October
2026; the class header has it all):

- Changed since copied: the finished signature's md5 is its fingerprint.
  Copy signature on the person's page and the team page posts the
  fingerprint copied (wp_ajax(_nopriv)_sb_emailsig_copied, checked with the
  person's page key) into _sb_emailsig_copied. Copies on admin pages don't
  count, nor an administrator's or editor's on the front end unless it's
  their own signature (same email). The cards (Signatures page, team page
  for administrators and editors) and the edit screen's box say Changed
  since copied (orange) or Not copied yet (grey). Short links, the campaign
  banner, the photo and the logo keep their addresses, so a link, banner or
  photo change doesn't count; text, layout and styles do.
- Email link (one button; the popup picks New or Updated Signature): wp_ajax_sb_emailsig_send,
  can_manage() and a nonce. Emails the person their page link with
  wp_mail, plain text, from Settings, Emails tab: From Name (blank: the
  site's name, shown as the box's placeholder; a company name isn't always
  set, a site name is; merge tags work in it too, e.g. {company}), From Email (the part before the @, with @ and the site's domain
  shown after the box and added when sending; blank: noreply; no Reply-To:
  Sam doesn't want mail going out from or pointing at whoever sends it, as
  that's often a personal address and other domains get blocked), and the New and Updated Signature subject and message
  with merge tags (SB_EmailSig_Notify::tags(): {first_name}, {name},
  {job_title}, {email}, {link}, {sender} from the person and whoever sends
  it; {company}, {website}, {site_name} from Settings and WordPress). The
  tab lists them all with where each comes from (render_tags(), on the
  Merge Tags message field), click to copy (assets/settings.js); a
  site-wide one that's blank is greyed out and can't be copied. Only for active
  people with an email address. The last send goes in _sb_emailsig_sent.
  Nothing is ever sent by itself: Sam didn't want three emails for three
  typo fixes. The button opens the framework's popup (SBTweaksConfirm.open
  from SocialBUMP Tweaks' assets/js/confirm.js, never a popup of the
  module's own): wp_ajax_sb_emailsig_draft fills in To, Subject and Message
  as Settings words them; both can be changed, then Send posts
  them to sb_emailsig_send (blank falls back to Settings' words). Cancel,
  the X, Escape and a click outside close it. On the front-end team page
  (managers only) the page loads WordPress's buttons.css, the framework's
  admin.css, jQuery and confirm.js for it; the framework's popup carries
  wp-core-ui itself (added to the framework for this, with
  .sb-tweaks-dialog__field for labelled fields), so its scoped styles apply
  outside wp-admin. In wp-admin SB_EmailSig_Notify::register_confirm()
  registers confirm.js where the framework doesn't.
- The edit screen's buttons are small (Copy signature, Copy link, Open
  link, Email link) and the line of help under them went, at Sam's request.

Copy (assets/copy.js) puts the exact HTML on the clipboard with ClipboardItem
(text/html and text/plain), and falls back to selecting the rendered
signature with execCommand('copy'). iOS strips the formatting when it is
pasted into Settings, Mail, Signature; shaking the phone and tapping Undo
brings it back, which the page explains.

Save my contact details links to /?sb_vcard=<id> (SB_EmailSig_VCard): a
vCard 3.0 with the photo embedded, lines folded at 75 bytes. The free text
address goes in the street part, one line each. A query string, so no
rewrite rules or server config.

## Working on this module

The hub's copy (plugins.socialbump.com.au,
wp-content/plugins/socialbump-tweaks/modules/email-signatures) is the only
up to date copy: there is no separate repo for the module, it ships inside
SocialBUMP Tweaks from the hub's Publishing (the Download plugin zip and
Publish release build from the hub's files). Read this file and the
framework's docs/context.md before changing anything.

How changes went up in October 2026, one guarded execute-php call each:

1. Copy the module folder to socialbump-tweaks/sbx-next-email-signatures.
2. Change the copy only: exact find and replace pairs, each required to
   match once, or whole files as nowdoc strings (a nowdoc drops the newline
   before its closing marker, so add one).
3. Check every file's md5 in the copy against the expected list (the md5 of
   the sorted "path:md5" lines) and php -l every PHP file. If anything is
   off, stop: nothing live has changed.
4. Swap: rename the live folder to
   wp-content/uploads/sbx-backups/email-signatures-<UTC stamp> (outside the
   plugin, so it never ships), the copy in, then opcache_invalidate every
   PHP file.
5. Check in fresh requests: the pages answer 200 with no PHP notices
   (an authenticated wp_remote_get with a WP_Session_Tokens token and
   wp_generate_auth_cookie, the token destroyed after), and the changed
   behaviour itself.
6. Note the change for the next release with sb_tweaks_log_change() (one
   plain sentence, starting "Email Signatures:"), and update this file in the
   same swap.

Until the evening of 28 October 2026 each swap kept the previous version
as sbx-prev-email-signatures, and the one before that as
sbx-prev-email-signatures-v1 ... -v28, in the plugin's own folder. The
release zip skips only .git, .github, node_modules and .DS_Store, so those
folders would have shipped in it: Sam had them deleted the same evening,
with three older sbx-prev-*.bak framework files. Backups now only ever go
in wp-content/uploads/sbx-backups, never inside the plugin folder.

Testing tips:

- Clicks by anyone logged in who can edit posts aren't counted, so test
  Reports from a private window or a phone.
- To test sending without sending, catch it with pre_wp_mail (return true)
  and look at the to, subject, message and headers; put _sb_emailsig_sent
  back afterwards.
- AJAX handlers can be run in process: wp_set_current_user(), $_POST with a
  fresh nonce, a wp_die_ajax_handler that throws, output captured with
  ob_start().
- A person's photo address (/?sbphoto=) answers 404 to a bad check and 304
  to its own ETag.
- Never call get_field() for a field from inside that same field's
  acf/load_field filter: get_field() loads the field again, so it loops
  until PHP runs out of stack. Read the value with
  get_option( 'options_<name>' ) instead. Campaign::width() did this
  (29 October 2026): harmless until a Settings save wrote ACF's
  _options_sb_emailsig_campaign_width reference, after which get_field()
  loaded the field by key. The Bricks module's gallery loop runs
  acf_get_fields() on every field group on every request (Bricks'
  control options on init), so the whole site answered 500 until fixed.

## Checked so far

Before the hub: the template, the photo crop and circle, the vCard and the
icon builder (three style settings) against stubbed WordPress and headless
Chrome.

On the hub, 28 October 2026, in fresh requests: every module page loads for
an administrator with the banner and no notices (then: Features, People, Add
Person, a person's edit screen, Settings with its three tabs, Templates with
Classic in use, Documentation); a Test Person (id 533) with a media library
photo made a 260px circle PNG with clear corners; their signature page
answered 200 with noindex and no-store and the right links, a wrong key 404;
the vCard answered 200 as text/vcard with the photo. Settings holds The
Cosmetic Studio's company details for testing.

Version 2 (pages, Edit styles, one address field) went onto the hub the same
day, checked in fresh requests: the module's own address redirects to
People; the nav reads People, Templates, Settings, Documentation with the
right one marked on each page, including the people screens; Settings,
Templates, Edit styles (eight cards, the colour pickers and styles.js) and
Documentation answer 200 with the banner and no notices; help text sits
under the input on the person and settings fields; the preview endpoint
renders posted styles without saving them; the signature page shows the
address on two lines with no preview markers; the vCard carries it as
1/17 Thomas Street\nNoosaville QLD 4566. The hub's old four address options
were moved into sb_emailsig_address and deleted.

On the hub the side menu shows Settings before Templates: Admin and Site
Enhancements has a saved submenu order for this menu from before, which it
applies after order_menu(). Saving its Admin Menu Organiser again, or
dragging the two there, puts them right. Sites without that plugin get the
module's order.

Not yet seen: the crop box choosing a photo, the icon builders on Settings
and Edit styles in a browser, and a real paste into Apple Mail and an
iPhone.

Later on 28 October 2026 Sam tried things himself on the hub in Safari on a
Mac: the Signatures and team pages, Add person, the photo crop (Option to
resize from the middle, the page scrolling over it), the People list
thumbnails and Email link (the email arrived). Reports had no clicks yet, as
his were made logged in. Still not seen by anyone: a paste into Outlook or
Gmail, and an old pasted signature picking up a new photo by itself.

The Add Person menu item is added by SB_EmailSig_Module::add_person_item():
WordPress only lists Add New for a post type with a top level menu.

## Later

- The template editable in the admin, discussed and put off: Sam knows HTML,
  so a template edited as table markup with merge tags and a CSS box inlined
  on save would do.
- A short reply signature: Sam liked it, for later. Icons on the install
  tabs: Sam offered to find some.
- Photo crop extras offered, not asked for yet: rotate and flip (the GD
  build would have to do the same), a warning when the crop is too small to
  stay sharp, and arrow keys to nudge the box.
- The Campaigns list's help still sits at the bottom; offered to move it up
  like Additional Buttons and Banners.
- Agreed next, in this order (28 October 2026): Qualifications and the
  Additional Information repeaters and the Info icons card (all done the
  same day); then the layout editor below, which takes in where the company
  name sits. Sam's order for it (29 October 2026): templates as records with
  the company and person choices and the Signature style dropdown (done),
  then export and import (done), then the layout editor (built 29 October
  2026: see The layout editor).
- Sam's idea for much later, not to build yet: more than one company on a
  site, each with its own details, branding and colours, and a person
  picks their company when added. Edit styles would then get a company
  picker, to set a template's look per company (brand colours are the
  groundwork: see Brand colours).
- Sam's bigger idea (28 October 2026; built 29 October 2026, see The
  layout editor; kept here as the brief): a drag and drop
  layout editor. Pick 1, 2 or 3 columns (3 is rare), then drag blocks into
  them and up and down: photo, name group (name, qualifications, job
  title), company name, logo, info list, Save my contact details, social
  icons, and the two Additional Information lists. Blocks never nest.
  Buttons, footer text and banners always sit below. Per column width and
  vertical align (a default plus per column), a separator per gap
  (Classic is really three columns with a line only before the last),
  outer border thickness per side, padding and gaps, left, centre or right
  per block. SortableJS suggested for the dragging. Columns can't stack on
  phones (Outlook). Templates as copies with their own styles, the company
  main and set, and a person's main and extras: done 29 October 2026 (see
  Templates are records); the layout will be kept on the record too.
  Export and import done the same day. Agreed for the editor (29 October
  2026): a holding area replaces show and hide ticks (drag a block there
  and it doesn't show); the Photo card's Show tick stays until the editor
  ships, then a template with it off starts with the photo in the holding
  area. Settings decides what exists, the template where it shows. A
  second logo on Settings, Branding ("Small logo", an icon or mark), its
  own block with its own max width and height; blank shows nothing (a
  stand-in in previews), never the full logo. The contact list is a block
  that can be added more than once, each copy with a popup (drag to order,
  tick to hide; Sam has used this pattern before) listing Phone, Mobile,
  Email, Website, Address and the company's and the person's Additional
  Information as two entries, noting a line that is also in another list;
  each copy stacked, or on one row with a separator. The icon style (Info
  icons card) and tracking stay shared. Sam sent screenshots
  of his old signature site's layouts as the range to cover: photo left
  or right or on top, name and title side by side or stacked, socials as a
  vertical strip beside everything, contact lines on one row with
  separators, centred layouts, photo with logo beside it. This replaces
  the "company name position" item.


## SocialBUMP UI (29 October 2026)

module.php sets 'ui_ready' => true, so every page here follows the user's
Auto, Light or Dark mode (framework docs, SocialBUMP UI). assets/admin.css
uses var(--sbui-x, fallback) for its colours; the fallbacks are the old
WordPress greys, for the person edit screen and the People list, which are
WordPress screens without body.sb-ui. The signature itself always shows on
white (.sb-emailsig-preview, .sb-emailsig-editor__preview, the social icon
preview, the Preview popup), as an email shows it.

Signatures page (SB_EmailSig_Admin::render_list): first the framework's
toolbar (.sb-ui-bar): Team page (Copy link and Open as icon buttons), People
file (Export, and Import, which opens the file picker and goes straight to
the import preview: list.js submits the form on choosing a file; nothing is
saved until Import there) and Add person (primary, for can_manage() users who
can publish). Add person opens SB_EmailSig_Add::dialog( 'admin' ): the team
page's popup with admin buttons and the .add--admin styles in admin.css.
Then a People heading with a count and how many are active, and the cards
straight on the page, no panel round them. A card per person has the
photo (56px), name, job title and Active switch along the top, a badge for
where the signature stands (Up to date, Changed since copied, Not copied yet),
then Preview (primary) and icon buttons for Edit, Copy link, Open link and
Email link (send_button(), an icon always). Its popup has an Email dropdown, New Signature or Updated Signature (Notify::KINDS), starting on Updated when the signature has changed since it was copied; each keeps its own edits while the popup is open, and the kind picked is posted and logged (Sam, 31 October 2026).
Every icon button has a framework tooltip (data-sb-tip), not a title; Copy
link's tooltip says Copied for a moment (copy.js).
There is no Copy signature on this page any more (Sam): the person copies
from their own page. Preview (list.js) opens the framework popup
(SBTweaksConfirm.open, class sb-emailsig-preview-modal) with the signature
from the card's hidden {id}-html, Close and Edit. copy.js and notify.js use
SBTweaksUI.toast() for their messages where the page has it, and icon
buttons keep their icon (a spinner while sending).

WordPress's People list and Add New are out of the menu and the nav (Sam,
29 October 2026): the people are on the Signatures page.
SB_EmailSig_Module::order_menu() drops them; list_to_signatures() sends
edit.php?post_type=sb_emailsig to the Signatures page, except the Trash view
(post_status=trash) and bulk actions, and carries trashed=N across, which
the page shows as a toast. Edit still opens WordPress's own edit screen.

Templates page (SB_EmailSig_Templates::render_page, Sam, 29 October
2026): the bar (Settings link for the main template, Import, Add template)
and a small card per template in the people grid: name, design, its layout
in numbers (stats(): columns, blocks, hidden) and how many people have it as
their main (use_counts()), then Preview (list.js, the popup with Edit
styles), Edit styles, Layout, Duplicate, Export (templates.js asks about
Lock colours first) and Delete (framework confirm, not on the main one). No
preview on the card itself. Add template (templates.js) asks for a name and
posts admin_post_sb_emailsig_add_template: add() makes a record on the
default design with no saved styles, so it has the design's default layout,
and opens its Edit styles page with added=1.
A template's own description (Sam, 29 October 2026) is its record's
post_excerpt, set in the Template card on Edit styles (a textarea, 160
characters at most, with the framework's character count) and shown on its
card. clean_note() makes it one line of plain text. Duplicate, Export and
Import carry it ('note' in the file). The Template card has no Reset: the
name and description aren't styles.

Edit styles and Layout (Sam, 29 October 2026) open with the framework's bar
(SB_EmailSig_Styles::editor_bar()): All templates, the template's name
(and Main), .sb-ui-seg tabs between Edit styles and Layout, and Save
changes for that page's form (form= attribute, kept in step by the unsaved
changes script). The old back links and the Edit Layout button are gone.

Layout page, Info List popup: the whole row drags (Sortable with
filter: 'input', no handle); a click that ends a drag is swallowed so it
doesn't tick the box. The layout popups have no blur behind them, so the
preview beside them stays readable.

Add and Edit person (SB_EmailSig_Person, Sam, 30 October 2026): our own
page in place of WordPress's post screen, the Signatures page with
?person=<id> or ?person=new. The bar: All people, the name and Active
switch, Copy link and Open for their page, Delete (Trash, after asking) and
Save changes. Cards (Details, Photo with the crop box, Info List,
Templates) draw the person's ACF fields with acf_render_fields() inside one
form with acf_form_data(); save() runs acf_validate_save_post( true ),
makes the post when adding (published), then acf_save_post(), so
acf/save_post does the title, crop and photos as before. The preview
beside it redraws as the simple fields change (preview(), the posted values
through acf/pre_load_value, nothing stored) and zooms to fit. post.php and
post-new.php for a person redirect here (?sb_classic=1 skips that), and
get_edit_post_link() points here. Add person on the Signatures page opens
this page; the team page keeps its quick popup (SB_EmailSig_Add). Meant to
open in a popup on the team page later.

The person page's preview (Sam, 30 October 2026) is drawn as the template
editor draws it ($d['preview'], so a part with nothing in it shows its
stand-in) by view(). Tabs above it (switcher()) show each template they
can use, main first, from SB_EmailSig_Templates::available() with the
form's unsaved main and extra picks; a tab redraws straight away. Under it,
"Disabled on this template" lists what that template leaves out
(SB_EmailSig_Layout::disabled(): held back blocks, Info List items no
showing Info List ticks, and parts Footer Controls hide). The photo picked
and the crop box's crop, ratio, shape and radius come from the form too:
SB_EmailSig_Photo::live() reads them in place of the saved meta (a
get_post_metadata filter) until unlive(), and the photo is made in the
'_preview' slot. crop.js fires a native change on its hidden input when the
crop is moved, reset or reshaped, or the photo changes, so the preview and
Unsaved changes follow it. Their own Info List rows go too, as ACF posts
them (rows of sub field key => value, given to acf/pre_load_value for the
repeater); person.js listens for ACF's append, remove and sortstop actions
and fires a change from the repeater, as ACF fires none. The crop's hidden
input has data-sb-watch, so the Photo card's Unsaved chip counts it (the
framework skips hidden inputs without it), and the saved crop opening keeps
its saved value.

The front-end pages (Sam, 30 October 2026): the team page and a person's
page are drawn in the SocialBUMP UI look, body.sb-ui with .sb-tweaks-wrap
round the page, loading the framework's admin.css, ui.css and ui.js, its
popup (confirm.js) and the module's admin.css (SB_EmailSig_Page::head_assets(),
body_class(), foot_assets()). Logged in, the mode is the person's saved one;
otherwise it follows their device. The team page uses the Signatures page's
own cards (SB_EmailSig_Admin::section() and card() with $front): everyone
gets Preview (its action opens their page), Copy link and Open their page;
administrators and editors also get Inactive, the switch, where the
signature stands, the chips, Edit, Email link and Delete (back to the team
page, ?trashed={id}), and a bar with the page's link, Signatures in
WordPress and Add person (the Add person page). Visitors get Request to be
added instead. A person's page has their signature on white
with template tabs (copy.js, data-sb-emailsig-style-tabs) and Copy
signature, then the mail app picker and steps.

The Signatures page's sections (Sam, 30 October 2026): Active (published),
Inactive (draft) and Deleted (the Trash), each with its heading and count
(SB_EmailSig_Admin::section(), card()). Active always shows (with a note when
no one is); the others hide when empty. Flipping a card's Active switch moves
it to the other section in name order (data-sb-emailsig-sort), recounts and
toasts (list.js move(), recount()). Deleted cards are faded on a light red. Deleted is a details element, closed
unless the page has just deleted someone (?trashed or ?purged); its cards
show when they were deleted and when WordPress empties the Trash
(_wp_trash_meta_time plus EMPTY_TRASH_DAYS), with Restore
(sb_emailsig_person_restore: back to Active or Inactive, as they were when
deleted, from _wp_trash_meta_status; the page then scrolls to the card and
it glows green, ?restored=id) and Delete
for good (sb_emailsig_person_purge: wp_delete_post, asks first). Both are
SB_EmailSig_Person handlers, checked by trashed_person().

Person cards (Sam, 30 October 2026) also show the email and mobile under the
job title (SB_EmailSig_Admin::contact()) and, beside where the signature
stands, chips only when they apply (chips()): their own main template, extra
templates just for them, their own Info List lines, company lines hidden,
and the staff page. No clicks there (Sam).

## Edit styles and Layout page by company (30 October 2026)

- Both pages get `companies` (each company's brand hex, colours menu, social svgs and Primary) and `company` (the previewed person's). The person picker options carry `data-company`.
- styles.js and layout.js `useCompany()` swap `cfg.brand`, the social svgs and the palette, on load and when the person changes, so the preview's redrawn icons and the swatches are in that company's colours.
- On save the icons are drawn for every company and posted as `{ companies: { id: set } }`; `Styles::save_icons()` saves each into its company slot. A plain set still saves to the default slot.
- Photo card: Border, Gap inside and Border colour sit on their own row (`.sb-emailsig-fields--row`).

## Layout builder: up to 5 columns, Duplicate, drag-on blocks (30 October 2026)

- `SB_EmailSig_Layout::MAX_COLUMNS = 5` (Sam: "just in case"); sanitize keeps up to 5 columns and always 4 gaps. layout.js gets `maxCols`, pads `gaps` on load, and the Columns menu offers 1 to 5.
- Info Lists and Dividers have a Duplicate tool: a copy with the same settings and the next free number, straight after the original. Disabled at `MAX_MULTI`.
- The Add buttons are now "New Info List" and "New Divider" blocks under Not in Use, dragged on to the layout (a SortableJS clone list, `data-list="new"`); `sync()` turns a dropped one into a fresh block with its defaults.
- The holding area is headed "Not in Use" (was "Not shown").
- Every block in a column has an X (Sam, 30 October 2026): an Info List or Divider is removed (asks first); a one-of-a-kind block moves to Not in Use (`data-act="unuse"`, no question, Undo brings it back). Blocks in Not in Use have no X.

## Photo map keys (30 October 2026)

- A new person's photo went missing after the first save, until a re-crop. `Photo::image()` compared the map key (PHP turns a template id like '554' into the number 554) with `!==` against the string key, so it "moved" the entry on to itself and `forget()` deleted the file. Keys are now compared as strings, and `forget()` takes a `$keep` file it never deletes. People who already had a '_preview' entry sharing the file were safe, which is why a re-crop "fixed" it.

## Company card logo (30 October 2026)

- Companies page cards show the small logo, else the logo, else the company's first letter (a leading "The" left out) in white on its Brand Primary (`.sb-emailsig-company__logo--letter`, `Companies::initial()`).

## Gap line style, iPhone text size (30 October 2026)

- The gap popup's Line style only shows while Line is Default or Custom: Sam set Solid with Line on None and saw no line.
- `-webkit-text-size-adjust: 100%` on the signature previews (`.sb-emailsig-preview-pop`, `.sb-emailsig-front`, `.sb-emailsig-editor__fit`): iPhone Safari was enlarging the 10px footer text on the person's page. The signature HTML itself is unchanged.
- Footer text carries `-webkit-text-size-adjust:100%` inline (Sam, 30 October 2026), so iPhone Mail keeps it at its own size (it enlarges text under about 13px). Changed every signature's HTML, so everyone shows Changed since copied.
- Then on the whole signature (Sam: any size for any text, none of it enlarged): the outer table carries it too, and it passes down to every part. The footer's own copy stays.

## Preview popup template tabs (30 October 2026)

- A person's Preview popup (Signatures page and team pages) shows their templates as tabs when they have more than one (`Admin::template_tabs()` puts them on the button as `data-sb-emailsig-tpls`). The first is the card's own signature; the others come from `wp_ajax(_nopriv)_sb_emailsig_card_sig`, checked with the person's page key as their page is, and are kept once loaded.
- The Preview popup sits at the top of the screen (Close and Edit stay put as templates change its height), its tabs are styled like the framework's (whose tab styles only reach inside its pages), and switching shows a spinner over the dimmed signature.
- Header bar (31 October 2026, Sam): the Preview popup's top is a bar like its footer, the name on the left and the tabs at the far right. A template's Preview has a Show as dropdown there instead: Layout View (its placeholders) first, then everyone under their companies (more than one) by name, Inactive ones marked, any template, as nobody may use a new one yet (`Admin::people_pick()` puts them on the button as `data-sb-emailsig-people`, for those who manage; `wp_ajax_sb_emailsig_tpl_sig` draws one with a nonce and edit_post).
- A template's Preview footer (31 October 2026, Sam): Edit styles and Edit Layout as plain buttons with the card's icons on the left (`data-sb-emailsig-foot`, Edit Layout only for layout templates), and only Close, with an X icon, on the right.

## Company tabs and Keep Report Logs For (30 October 2026)

- New company tab, Additional Content, between Branding and Social Media: Sign-off and Save to Contacts Link (from Footer) and Additional Info List Items (from Company). Same field keys, so nothing saved moved. Added to `Fields::COMPANY_TABS`.
- Settings, Tracking Codes: Keep Clicks For is now Keep Report Logs For (clicks older than it are deleted from Reports by `Tracking::prune()`).
- Settings tab Tracking Codes is now Tracking, in two parts under small headings (`Fields::sub_heading()`, an ACF message field with only a label): Reports (Track Clicks, now Enable Reporting, and Keep Report Logs For) and Google Analytics (UTM). The headings start new rows, so hiding Keep Report Logs For no longer shuffles the fields below. Field keys unchanged.

## UTM codes list and reset (30 October 2026)

- Tracking tab, Google Analytics (UTM): a Codes list under the four boxes, click to copy (`Tracking::codes()`, `render_codes()`, the Emails tab's merge-tag markup and copy script). New codes beside {person} and {link}: {first_name}, {company} (the company being drawn) and {template} (the template's name; '' or 0 means the person's main). All come out as slugs. `Tracking::tag()` now takes `$person_id` and `$tpl_id`; company and template are only looked up when a box uses them.
- Reset Google Analytics Settings (`render_reset()`, settings.js): puts Add Tracking Codes (on), Other Websites Too (off) and the four boxes back to `DEFAULTS` in the form, saved with Save. Its field is hidden while everything is already the default.

## Company tab headings (30 October 2026)

- A company's Company tab is in two parts under `sub_heading()`s: Company Info List Items (named for the layout's Info List, Sam, 31 October 2026; Company Name, Phone and Address in a row of three, then Website and Google Maps Link) and Template Settings (Main Template, Templates). Field keys unchanged.
- Templates: the Main Template's box is ticked and locked (settings.js, `is-locked`), not disabled so it's still sent. It stays ticked when another becomes main.
- Sub-headings are a band: `--sbui-surface-2` background, `--sbui-border` border, `--sbui-r` corners (admin.css), 28px above (8px straight after the tabs).
- Phone and Address have no help text (Sam, 30 October 2026).
- A company's page: with two or more companies, the bar's name is a menu (`Companies::switcher()`, settings.js) that opens the company picked. One company: just the name.
- Reset Google Analytics Settings is a warning-coloured button (soft yellow, solid on hover) with the restore icon.
- Counts beside headings (`sb-emailsig-count--active|inactive|deleted|total`): a soft fill with its `-line` border and navy text (green, warning, danger); totals navy with white text.

## Team Member Post Type per company (30 October 2026)

- Team Member Post Type moved from Settings (its Team Pages tab is gone) to a company's Company tab, under a Team Pages heading. `Staff::type( $co )` reads the company's own (null: the company being drawn; 0 with no companies: the old option); `person_type()` is the person's company's; `types()` every company with one.
- `move_type()` (admin_init, once after `Company::MIGRATED`, flag `sb_emailsig_staff_type_moved`) copies Settings' value to every company without its own.
- `page_id()` only counts a staff page of the person's company's type, so moving someone to a company without staff pages drops the link (the meta stays).
- Person page: Staff Page shows while any company has staff pages; staff.js hides it for a company without, clears it when the company changes, and sends `sb_company` with the search so `page_query()` lists that company's pages.
- Request to be added: the person is the team page's company's, and the crop uses its main template.

## Staff page box and Merge Tag Fields (30 October 2026)

- Person page: while a staff page is picked, the Staff Page field, its help and the photo and tokens panel are one box (staff.js toggles `has-staff`; admin.css).
- Company tab, Team Pages: Merge Tag Fields (`field_sb_emailsig_staff_fields`, checkboxes, shown once a post type is picked). Choices are every offered type's single-value ACF fields (`Staff::field_map()`, `field_choices()`); settings.js shows only the chosen type's and unticks the others' when the type changes.
- `Staff::allowed( $co )`: the ticked names, or null (all) for a company that has never saved it. Never saved, the boxes load all ticked (`fields_value()`), so a save keeps everything working. {title} always works.
- `tokens( $staff, $co )` filters to the company's allowed fields; `fill()` and `missing()` pass the person's company, the panel's ajax the company picked on the page (`company`, from staff.js).
- Use this photo (staff.js) sets the Photo field with `acf.val()` before `render()`, as ACF's picker does: since ACF 6.8 render() sets it silently, so the crop box and preview never heard of the new photo.

## Card chips for staff pages (30 October 2026)

- A person's card: chips in a row (`.sb-emailsig-person__chips`), then the copied state on its own line (`.sb-emailsig-person__copied`).
- Staff page chip is now "Linked to: <page title>", to the page's WordPress edit screen (some staff pages are only listed, not public). "Image mismatch" (warning chip, to their Edit page) when their Photo isn't the staff page's featured image.
- Staff page tokens on the person page: every allowed one, empty ones dashed with "empty"; a click copies the tag (staff.js), as Settings' merge tags do, instead of putting it in the field last typed in.
- Card chips: "<Field> missing" for each of Name, Qualifications, Job title, Email or Mobile that uses a staff page merge tag now empty or no longer allowed (`Staff::missing()`), to their Edit page; Email's red (`--danger`), the rest warning. The tags are kept, not cleared, as the staff page may only be empty for now.
- Person page: under Name, Qualifications, Job Title, Email and Mobile, a note for each merge tag that comes out empty ("{email_address} is empty on the staff page.", or "needs a staff page" with none picked), live as they type (staff.js `notes()`); Email's red.
- Container Settings: Hide Sign-off (`hide_signoff` in the layout, layout.js), so a template can leave the sign-off out; listed under Disabled on this template like the Footer Controls, and part of the card's Reset.

## Signatures page grouped by company (30 October 2026)

- With more than one company, the Signatures page puts each company's Active, Inactive and Deleted under its own bar, as everyone's team page does (`Admin::of_company()`); one company stays as it was. The company label on the cards hides inside a group (CSS).
- `Admin::company_head( $co, $active, $inactive, $deleted )`, on both pages: the company name (a click folds its people away, list.js; remembered in localStorage `sbEmailsigFolded`; searching shows them all), its brand colours as dots, Active, Inactive and Deleted counts (just "N people" for those who don't manage; list.js keeps them current), the team page's Copy and Open, and Edit company (administrators).
- Moving people between companies by dragging was talked about and left out: it changes their template, branding and staff page link, better done on their page.
- Brand colour swatches (`Companies::swatches( $co )`): the four brand colours as 22px circles, crossed where the company has none of its own (tooltip says what it falls back to). On each company's bar (Signatures page, team page) and its card on the Companies list. In WordPress, for administrators, the row is a button that opens Brand colours (assets/brand.js, the framework's popup): all four, each with a picker, a box for a hex or theme colour and the theme colour menu, then Save. `brand_save` checks and keeps them (blank: the default, as the Branding tab), brand.js redraws the company's icons for every template and `brand_icons` keeps them (`Icons::save()`), as Save on the company's page does; the popup stays open until that's done (its Save click is caught before the framework's closes it). A pick-and-save-straight-away swatch was tried first and dropped: too easy to change by accident, and macOS's Colours window fought it.
- A company's people sit 24px in from its bar (`.sb-emailsig-company-group__body`), and the cards' company label is hidden in a group (the admin rule needed the higher specificity).

## Logos in the Media Library (SB_EmailSig_Logo, 30 October 2026)

- The Small Logo is always square. A picked image that isn't opens a crop popup (assets/logo.js, Cropper.js, 1:1). Save crop makes a new Media Library image (512 px a side at most, titled "Email signature logo: Company") and puts it in the field. Under 128 px a side is refused; under 512 is warned about. An image already square is used as it is.
- A crop remembers the image it was cut from (`_sb_emailsig_crop_of`) and the square (`_sb_emailsig_crop_rect`), so Re-crop opens there. With that image deleted, Re-crop works from the crop and the new crop becomes the one to re-crop from (crop_of 0), with a note in the popup.
- Images in use are tagged `_sb_emailsig_logo` = `small:<company>` / `main:<company>` on company save (acf/save_post 30). Only tagged images get our sizes: `sb_emailsig_logo_square-64/128/192/256` (small, square images only) and `sb_emailsig_logo-200/400/600/800` (main, widths). Not registered with WordPress, so no other upload gets them.
- Untagged on save when cleared or replaced: our sizes are deleted (not a file another size shares), and a crop we made is deleted once nothing uses it. Crops made and never saved (`_sb_emailsig_crop_pending`) are deleted on the next crop or save.
- Images module (core, 30 October 2026): `sb_images/wanted_for` and `sb_images/attachment_size_groups`. Our sizes are wanted on tagged images only, built by Build Thumbnails whatever is ticked, listed read-only as "Email Signatures" on SB Image Cleaner's Sizes and in each image's own size list. With this module off they're unclaimed, so they show as leftovers to clear. The crops themselves stay as ordinary images.
- SB_EmailSig_Data::src() picks the smallest of any of an image's sizes at least twice the shown width in the same shape (2% ratio tolerance), so logos, banners and campaign images use our sizes or the site's image- widths.
- backfill() tags existing companies' logos once (option `sb_emailsig_logo_tags_v1`).
- Reset crop, in the popup and under the field (for a crop whose image is still there), sets the biggest centred square. Under the field it acts at once: a square image goes back in as it is, any other gets a new centred crop. On a square image that's the whole image, and saving then puts the image itself back in the field (no crop made; a selection within 1 px of the whole counts, as Cropper can round to 511); the crop it replaces is deleted on company save.
- A crop's file name ends in the time in base 36 (email-signature-logo-socialbump-t3abc1.png): reusing a just-deleted crop's name served the old picture from the browser cache.
- With HappyFiles (taxonomy `HAPPYFILES_TAXONOMY`, happyfiles_category), every crop goes in an "Email Signature Images" folder, made if missing (file(), on crop and on company save). Crops keep the site's own sizes too, so they can be used elsewhere (Sam, 31 October 2026).

## Team page logo and Admin Branding (31 October 2026)

- The team page and a person's page (views/signatures-page.php, signature-page.php) show a 72 px circle (56 on phones) left of the heading, SB_EmailSig_Page::head_mark(): on a person's page their photo first, as their main template makes it (Photo::png()): a circle when it's square, else as it is at 72 px high (Sam: no forced circle crop), then the company's Small Logo, else its first letter in white on its Brand Primary (as the Companies cards, Companies::initial()). The every-company page: Admin Branding's Small Logo, else nothing.
- Settings has an Admin Branding tab (last): Logo (`sb_emailsig_admin_logo`, not used yet) and Small Logo (`sb_emailsig_admin_logo_small`). The small one is square like a company's: the same crop popup (logo.js KEYS), tags `small:site` / `main:site`, crops' CROP meta `site`, synced on Settings save (acf/save_post 'options').
- The front pages print the site icon themselves (head_assets(): wp_site_icon()). They skip wp_head(), so the tab icon was left to the browser's /favicon.ico guess, and a person's page showed none.
- A person's photo box has Reset to Default (Photo::render_box(), crop.js), shown while their ratio or border is their own: sets both back to Default (the template's), which drops their shape and radius too. Saved with the rest.
- Photo::styles_for( $tpl, $person ) resolves a template's styles in the person's company (brand colours fill Default photo border colours and the like). Without it, the photo served at its fixed address (maybe_serve), made on save (generate()) and shown on their page came out in the default company's colours; only the admin preview, already in their company, was right.

## Default company bleed-through check (31 October 2026)

Anything reading company fields uses SB_EmailSig_Company::current(), which is the default company when nothing has entered one. Checked every caller:
- Fixed: the person's photo (Photo::styles_for( $tpl, $person )); the Templates page's Main and Company set badges and its order, and the Main badge on Edit styles (Templates::companies_of(), every company's); the Emails tab's merge tag list, which showed the default company's Company Name and Website (with more than one company those are per person, no value shown).
- Already in the right company: signatures (Render via Data::get's company_id), cards and chips (Admin::card), the person page and its live preview, a company's page (Companies::render_edit), short link redirects (Tracking), campaign images, Email link (Notify::compose), icons (saved per company), team page marks, logos.
- Left as the default company's, as they are not one company's: Settings (site-wide), the Templates page's placeholder previews.
- A gap between columns has its own line Colour too (gaps[i].colour, '' for the lines colour), set in its popup like a Divider's (Sam, 31 October 2026).
- Solid and Double only (Sam, 31 October 2026): dashed and dotted can only be borders, and the Gmail phone app shrank a full width dotted line to a single dot. Saved dashed or dotted reads as solid (Layout::OLD_STYLES).
- Double is drawn like solid, with filled blocks, not a border: a full width one (Divider, Footer Divider) is two filled divs with an empty div between, and one between columns two vline() cells with an empty vgap() cell between. Layout::dsplit() splits the width as a browser does a double border (3 is 1, 1, 1; 5 is 2, 1, 2; 8 is 3, 2, 3). The container border stays a CSS border (solid or double): drawing it with filled cells needs a known colour behind the content.
- The signature's outer table is border-collapse:separate (border-spacing 0): collapsed, half the Container border sat outside it, so Gmail webmail cut off its left side and the Footer Divider stopped short of its outer edge (Sam's 6px test, 31 October 2026).
- Container border lock (Sam, 31 October 2026): locked, one Width (px) box sets all four sides; the lock button (dashicons lock/unlock) shows Top, Right, Bottom and Left instead. Locked to start with when all four are the same; locking again sets all four to Top's. Only the editor knows it's locked (layout.js borderLocked); the four sides are saved as before.
- Every line width is labelled Line Width (Divider, gap, Footer Divider, the Container border's locked width; Sam, 31 October 2026).
- Line widths are dropdowns, not number boxes (Sam, 31 October 2026), so nothing over 8px can be typed in: a line (Divider, gap between columns, Footer Divider) is Default (its px), 1px to 8px, then None (not the Divider); a Container border side (and its locked Width) 1px to 8px, None; the Default Line Width 1px to 8px (layout.js pxSelect()).
- Double is at least 3px (Layout::DOUBLE_MIN), forced: while a line comes out Double, 1px and 2px are disabled in its dropdown and a thinner width goes to 3px (layout.js forceDouble(), pxDouble()); picking Default when the Default Line Width is under 3 puts the line's style back to Default instead (Sam). A Container border side of 1 or 2 goes to 3; Default Line Style Double takes the Default Line Width to 3 (sanitize too). Layout::lwidth() still draws anything thinner at 3.
- Line height (Sam, 31 October 2026): Edit styles' Text card has Default line height (styles 'line_height', 1.4 for every design that doesn't set one) and each text part a Line height (items[x]['line'], blank for the default), 1 to 3 (Styles::lh()). Written unitless (line-height:1.4), so it grows when the Gmail phone app enlarges small text: the px one (1.3 x size) didn't, and footer text crowded. The Info List's letters and labels follow Contact details. Outlook on Windows is the one that's buggy with line-height (caniemail: em and px); unitless there is untested.
- iPhone and iPad install guide (views/signature-page.php): the shake to Undo step is only when the pasted signature doesn't look like the copied one (often huge); Sam found iOS 27 kept the styling on paste, and shaking then undid the paste itself (31 October 2026; nothing found online confirming the iOS change).
- Team page headings (Sam, 31 October 2026): a company's page is "Email signatures for {company}" with "Find your name, preview your signature, then open your page to copy it into your mail app." under it; the every-company page stays "Email signatures". Its circle is Admin Branding's small logo, else the site icon (get_site_icon_url), else nothing.
- Settings tabs (Sam, 31 October 2026), in this order (Fields::SITE_TABS, sorted in split_groups()): Admin Branding, Admin Notifications, Signature Emails (was Emails), Tracking.
- Admin Notifications (SB_EmailSig_AdminMail, Sam, 31 October 2026): Default Send To (blank: the site admin email; commas for more than one), then a section per notice in NOTICES (an ACF group, sb_emailsig_notice_{key}, drawn as a card: its name and an Enabled/Disabled switch, on by default, in the header row; disabled, only that row shows), and when enabled its own Send To (blank: the Default), Subject and Message with merge tags. A line of the message whose merge tags all come out empty is left out. Read by the group's key, so a never-saved one gets its defaults. So far one notice: New Team Member Submitted (request). A new one: an entry in NOTICES and an AdminMail::send() call.
- Signature Emails tab (Sam, 31 October 2026): New Signature and Updated Signature are cards side by side, each an ACF group (sb_emailsig_mail_new, _updated) with Subject and Message. Named so the fields keep their option names (sb_emailsig_mail_new_subject and so on) and keys, so saved texts carry over; SB_EmailSig_Notify::settings() reads the groups by key. Cards share the Admin Notifications look (admin.css sb-emailsig-card-field).

## Activity log and live updating (31 October 2026)

Sam's order: the log first, live updating on top of it, both before the hub's module controls.

- SB_EmailSig_Log: its own table ({prefix}sb_emailsig_log: at, user_id, who, action, type, object_id, name, company_id, detail), made by dbDelta on init when sb_emailsig_log_db differs from DB_VERSION; rows over 12 months old go in a daily cron (sb_emailsig_log_prune). Caught from WordPress so every way in counts: transition_post_status (a person, company or template added, deleted, restored; a person made Active or Inactive), before_delete_post (a person deleted for good; a company or template deleted without the Trash), acf/save_post at 99 (a person or company edited on its page; Settings saved, only on the Settings page). Logged where they happen: template styles and layout (SB_EmailSig_Styles), brand colours (SB_EmailSig_Companies::brand_save), Email link and Resend (Notify::send, detail new or updated), a signature copied (Notify::copied, by the person's name when not logged in, detail the template when it's not their main), a team page submission (Request sets Log::$via = 'request', so it's "submitted"). A person added and saved in one request is logged once (Log::$added). Who: the logged in user's display name, else Visitor.
- The Activity page (features/signature-activity; nav Signatures, Companies, Templates, Reports, Activity, Settings, Documentation, Sam 31 October 2026; Module::order_menu and module.php's sections): search a name, type (People, Companies, Templates, Settings), company (with more than one), 50 a page, newest first, each row in words (Log::sentence()). On the hub, ASENHA's saved submenu order may need Activity added (not touched: ask Sam first).
- Each row's id is the site's change number, SB_EmailSig_Log::REV (sb_emailsig_rev, autoloaded).
- SB_EmailSig_Live (wp_ajax(_nopriv)_sb_emailsig_live) and assets/live.js on the Signatures page (where admin, can_manage()), the team pages (where team, the page's key: SB_EmailSig_Page::team_company() decides as render_list() does) and a person's page (where person, their key). A page sends the number it has; the same number is one option read. Otherwise the rows since: a person's card is drawn again as this page draws it (Admin::card(), or Admin::deleted_card() in Deleted) and put in its section in name order, or taken away ('gone': not shown here, another company's, or not Active on a public page); a company, template or Settings change draws every card on the page again (ids sent); over 300 rows, the page reloads. A person's page reloads when they or a company, template or Settings changed, not for a copy or an email (their own copy would reload it). Cards carry data-sb-emailsig-id; list.js gives live.js recount() and filter() (window.sbEmailsigListApi), so counts and search follow.
- Timing (Sam, 31 October 2026, "15 seconds seems like forever"): every 5 seconds while the page is used, every 30 once it has sat 3 minutes, none while the tab is hidden, at once when shown again or touched after sitting. This page's own requests to admin-ajax (fetch, wrapped, and jQuery) make it ask at once and tell the browser's other tabs (BroadcastChannel sb-emailsig-live), and a page loading tells the others too (after a save that reloads it).
- Not live: company bars' names and colours, the Templates and Companies pages, Settings. Those need a reload.

## Company on/off switch (31 October 2026)

Sam: switch a whole company off when it no longer pays to have its signatures managed, without switching each person off or deleting anything. "Everything" dies; Delete Company (for good, with its files) is for later.

- SB_EmailSig_Switch: META sb_emailsig_off on the company (when). Switched by administrators (manage_options) with the switch far right of the company's bar (Admin::company_head(), on the Signatures page and everyone's team page) and in a company's own team page's bar; off asks first (list.js, SBTweaksConfirm), then the page reloads. Logged (company disabled, enabled); open pages reload on those rows (Live::poll()).
- Off: its team page and people's pages are not found for everyone but administrators and editors (Page::listening_404(), which keeps live.js so they come back by themselves); the addresses in pasted signatures that go through the site are not found: photo (Photo::maybe_serve, /?sbphoto), vCard, campaign banner (c{id}) and tracked links (Tracking::maybe_go). Icons: the files in the company's own slots (Icons::slot(); the default company's are the slots without @c) are moved to uploads/sb-email-signatures/off-c{id} (index.php and a deny-all .htaccess) and moved back when it's on; Icons::save() does nothing for a company that's off. Logos: Logo::park() strips the module's sizes unless a company still on (or Admin Branding) uses the image that way; Logo::unpark() makes them again (same names). Logo::own() only counts uses by companies switched on, so nothing remakes them meanwhile.
- Stays: a logo used at its full size and footer banners (the Media Library's own files), untracked links.
- Its people: dimmed (is-off on the group), their switch locked (and set_active() refuses), no Email link; Notify refuses; Request isn't offered; stay_off() (wp_insert_post_data) keeps anyone from being made Active by any other route.
- With one company the Signatures page has no company bars, so no switch there (the hub's module controls cover that case).
- Also on each Companies card, top right after Default (Sam, 31 October 2026; list.js loads on the Companies list for it), the card's details dimmed while off. A company's own team page, for those who manage, dims its people too (sb-emailsig-off-body). No lightening on hover.
- Templates cards (Sam, 31 October 2026): "Main for:" under the description names the companies it's the main template for (two by name, then "and N more" with the rest in a tip), with more than one company. The Company set chip's tip already names the companies whose set it's in.
- A company's edit page has its switch beside the company picker (as a person's Active switch on theirs); a person's own page locks their Active switch while their company is off.
- Toolbar names as menus (Sam, 31 October 2026), as the company menu: a person's page lists everyone (Active and Inactive, under their companies with more than one) and opens another's page; Edit styles and Layout list every template and open the same page of another (Edit styles for one without a layout). SB_EmailSig_Admin::goto_menu() draws them, with its own small script (data-sb-emailsig-goto).
- Templates cards no longer say "Classic design" under the name (Sam, 31 October 2026): there is only ever the one design; every template is made from it.

## Layout rows (31 October 2026)

Sam: the layout as rows, each with its own columns, so a row can hold, say, an Info List as a row of icons under the rest. Built in three parts: rows and their settings first (this), then removing rows with their contents in more detail, then columns added, removed and dragged between rows with no maximum.

- Shape: styles['layout']['rows'] (each: columns, gaps, valign, padding, border, background) and row_gaps (one between each row and the next, as a column gap; space each side of a line). A layout from before rows (columns, gaps and valign at the top level) reads as Row 1 (Layout::legacy(), and load() in layout.js). The Container keeps its padding, border and background round all the rows. MAX_ROWS 12.
- Drawing (Layout::columns()): one row with no box of its own is drawn exactly as before rows (same HTML), so signatures already copied don't show as changed. Otherwise a full width table, a cell per row with its box, and between rows a cell of the gap's height, or the gap's space each side of a full width line (rule()).
- Editor: each row a box with its grip (more than one row), name, Columns and Vertical align, cog (padding, background, border sides, style and colour) and X (asks; with blocks in it, where they go: the end of another row's last column, or Not in Use). Between rows a gap bar opens the gap's popup (gapPopup(), as between columns). Add row under the rows. Lists are r{row}c{column}.
- Layout::all_columns() gives every column for what reads them all (shows(), disabled(), Templates::stats()).

## Row and block colours (31 October 2026)

Sam: a dark row with white text and icons, and any block's colours of its own, so anything can be built. Three layers, each blank to follow the one above: template (Edit styles), row, block.

- Layout::COLOURS: a row's text, accent (labels, letters and separators) and icons; Name and job title (name, qualifications, job, accent), Company name (text), Info List (text, accent, icons), Save my contact details (text, icons), Social icons (heading, icons, icon background). Rows keep theirs as 'colours'; blocks as their 'colours' option. Theme and brand colours allowed, worked out in the company being drawn.
- Drawing: Layout::draw() puts a block's colours (else its row's) into the styles it draws with (colours_for(): the items' css colour, the lines colour for separators, the label colour), then puts them back. With none set nothing changes, so signatures stay as they were.
- Icons in a colour of their own are PNGs like the rest: the Layout page draws a variant set for each colour a row or block uses (layout.js variantSpecs(), buildVariants()), sent with Save (in each company's set as 'variants') and kept by SB_EmailSig_Icons::save_variants() in the slot's 'variants', named by Layout::variant_key() (c or s, size, colour, background, radius). A key that misses (sizes or colours changed since, or never saved) shows the usual icon; a social row whose icon changed since (its svg's md5) does too. The preview draws them in the browser (swapVariants()). Re-save the Layout page after changing icon styles on Edit styles or a company's social rows to redraw them.

## Reset in layout popups (31 October 2026)

Sam: every block, row, column and gap popup has Reset bottom left, live only while its settings differ, with no asking (nothing keeps until Save). It puts back the design's settings for that one (the field's data-default layout, as Reset Template uses: a block of the same type and number, the row, column or gap at the same place), else a new one's. popup() takes { pick, def, reopen }: Reset copies def onto the object, closes and reopens the popup with its fields redrawn; Cancel after Reset still puts back what it was when first opened (carried).

## Text block (31 October 2026)

Sam: a block with an optional title and text, as many as wanted (MULTI, "New Text"). Options (Layout::block_options('text')): title, text, gap (space between), title_/text_ size (0 or 8 to 48), weight, style, case, line ('' each to follow the template: the title Name's styles, the text the Info List's contact styles), colours (title, text, links; over the row's text colour).

- Saved through wp_kses with TEXT_HTML only (a href, strong, b, em, i, u, br); the title one line.
- Drawing (Layout::text_block(), fill()): the text's line breaks kept (nl2br), merge tags (Layout::TAGS: {first_name} {last_name} {name} {job_title} {qualifications} {company} {phone} {mobile} {email} {website}; first name is the first word of the name), then Dynamic Shortcodes when the plugin is active ({wp-shortcode:site_phone @ format="link"}, via DynamicShortcodes\Plugin::instance()->shortcodes_manager->expand_shortcodes()), then do_shortcode(), then make_clickable() for web and email addresses (not phone numbers), then the case on the words only, then every link rebuilt: its href, a new window, the links colour (blank: the text's) and underlined. Empty: a placeholder in previews, nothing otherwise. Values are filled in when the signature is drawn; a pasted signature keeps what it had.
- Editor (layout.js textPopup()): Link wraps the selection (or adds the address as its words) in <a href>, Remove link unwraps the link the cursor is in, the merge tags insert at the cursor of the title or text last typed in.

## First and last names (31 October 2026)

Sam: a person has First Name (required) and Last Name fields, not one Name guessed apart. SB_EmailSig_Names:

- The full name (sb_emailsig_name) is still kept, built from the two on every ACF save (acf/save_post at 15, after ACF saves and before Photo makes the post title follow it at 20), so everything that reads the name is unchanged. Its field is hidden from the form (acf/prepare_field) and not required. Staff page tokens work in both names (Staff::TOKEN_FIELDS); the full name, joined from the raw values, fills them in when read.
- Once (option sb_emailsig_names_split, on admin_init): everyone with a name and no first name gets it split, the first word and the rest.
- Data::get() has first_name and last_name; the vCard's N line and the Text block's {first_name} and {last_name} use them (else the name split).
- The Name and job title block has Name format (block option 'format', Names::formats()): full, first, initial_last (S. Frysteen), first_initial (Sam F.), last_first (Frysteen, Sam). Without first and last names it shows the full name.
- Submit New Team Member form: First Name (required) and Last Name fields (first, last); a form sending one name has it split.
- People export: First Name and Last Name columns in place of Name. Import reads them, or a Name column from an older file, split.

## Columns added, removed and moved (31 October 2026)

Sam: no Columns menu; columns come and go one at a time, and drag along a row or into another. Layout::MAX_COLUMNS is 12 (a sane top); gaps per row are max(GAP_PAD 4, columns - 1), so layouts saved before keep their shape (the line about 5 columns and always 4 gaps above is out of date).

- layout.js: each column's head has a grip (not when it's the row's only one), its settings and Remove (not when it's the row's only one); + at the end of a row adds a column (gapIn() reuses the padded gap at the end). Remove with blocks in it asks where they go (another column, default the one before it, or Not in Use); empty, it just goes (Undo brings it back).
- Dragging: a Sortable per row's board (group sbes-cols, handle the grip, draggable the columns); moveColumn() moves it in the state and re-renders. A row keeps one column, and takes no more than MAX_COLUMNS. Gap i sits between columns i and i + 1: taking a column out takes the gap after it (before it, for the last); putting one in adds a new gap after it.

Gaps (31 October 2026, Sam): a gap between columns or rows is Double unless set: new ones, the design's (Classic's two column gaps), and what Reset gives where the design has none. Saved gaps keep their own space. Every gap, between columns or rows, also has the Default Line Width unless set (clean_gap()'s $line, canonGap()'s ld), so Reset gives the same line on each.

## Column boxes and padding on each side (31 October 2026)

Sam: a column has what a row has, and padding is set per side.

Defaults: a column's padding is Double top and bottom, none left and right (Classic's columns, the fallback layout and new columns, so Reset gives that); a row's padding is none. Every column's Vertical align defaults to Default (the row's).

- Data: a row's padding and a column's padding are { top, right, bottom, left } (Layout::clean_pad(); one value from before is all four). A column's Space at the top and bottom became its padding's top and bottom; it also has border (clean_border(), Solid or Double), background and colours (Layout::COLOURS['row']). Keys in order: width, valign, padding, border, background, colours, blocks.
- Drawing: pad_css() writes one value when all four sides match, so rows saved before draw the same. A column with no border or background draws as before, its left and right padding added to the gap space either side. With a border or background (boxed) the box goes on the column's own cell, so it fills the row's height, and the gap space either side becomes cells of its own (vgap()) outside it. Blocks follow the column's colours, else the row's (Layout::$colours_now); layout.js variantSpecs() does the same.
- Editor: paddingSection() (Top, Right, Bottom, Left two by two with a lock: locked, Top sets all four and the others are greyed and follow), borderSection() (the row's border, shared) and boxColours() (Background colour first, then Text, Labels and separators, Icons) make the row and column popups.

## Limits set from the hub (31 October 2026)

Sam: each site gets Email Signatures only when the hub allows it (SB Tweaks' SB_Tweaks_Policy, sent from the hub's Installs page, Modules popup), with limits: people, companies switched on and templates, 1, 5 and 2 until the hub sets others, 0 for no limit. The hub itself has none. People counts everyone, deleted ones in the Trash too: a client deletes someone for good or buys more slots.

- SB_EmailSig_Limits: full( key ), message( key ) ("No person slots left. Ask for more to add another."), count( key ) for the bars ("People 1 of 1", just the count with no limit) and button( key, html ), the Add button as it is or greyed out with the message as its tip (a span carries the tip, as a disabled button shows none). No admin notice: Sam didn't want the banner.
- Where: the Signatures page bar (count and Add person), the Companies page bar (count and Add company; no count by the grid any more), the Templates page bar (count and Add template; Import and each card's Duplicate greyed out too; no count by the grid), and the team pages (Add person for those who manage, Submit New Team Member for visitors, greyed out with the tip).
- At a limit nothing more is made: a new person is stopped however they'd come (wp_insert_post_empty_content: the person page, an import, a request, which says why), the person page and a new company's page show the message instead of the form, Add, Duplicate and Import template go back with ?template=limit, and a company can't be switched on (Switch::ajax answers with the message, list.js shows it). Only the oldest people up to the limit can be active (may_be_active(), hold_person() however they're saved, and the Active switch).
- Over a limit (lowered on the hub): apply() runs on init once per change of limits (sb_emailsig_limits_applied) and the newest go off: active people past it Inactive (by date added), companies switched off (never the default one). Templates over it are left out of Templates::all() (templates_over(), newest by id), so nobody can use them until the limit goes up; nothing is deleted.
- Anyone without a company joins the default one (Company::adopt_one() on save, adopt_all() twice a day): with one company its field isn't shown, so someone added before companies (Chris on gimage) couldn't be put in it.
