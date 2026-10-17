# Elementor Tweaks module

Extras for sites built with Elementor (Sam, October 2026). Read the framework
notes first, at the plugin root in docs/context.md. This file covers only what
this module does. Sam is moving away from Elementor for new builds, so this
module serves the Elementor sites he still looks after.

## What it does

Its own SB Elementor Tweaks menu, after SB Site Kit, with one page, Images
(sb-elementor-tweaks-images), holding two features. Neither has settings of its
own on that page: both add settings to Elementor's widgets, in a section called
SB Image.

| Feature | id | What it adds to a widget |
| --- | --- | --- |
| Image Alt Text | elementor-image-alt | Alt text: the image's own (default), Custom (typed, or a dynamic tag such as the post title) or None (decorative), and Only where empty |
| Image Aspect Ratio | elementor-image-ratio | Under Image shape: Aspect ratio (Original by default), Custom ratio, Object fit (cover by default) and Position (centre centre by default) or Custom position, each per device |

Both are on by default once the module is switched on. The module needs
Elementor (requires elementor, a dependency the framework checks with
ELEMENTOR_VERSION). Without it the card says so and nothing loads, which is how
the hub shows it.

It replaces Custom Alt Text for Elementor Images (MIGHTYminnow, 1.0.3), see
Moving a site over.

## How it is built

| Path | What it is |
| --- | --- |
| module.php | the declaration: id elementor, group images, menu sb-elementor-tweaks after sb-site-kit, bar row elementor-tweaks, replaces, migrate |
| includes/class-sb-elementor-tweaks.php | SB_Elementor_Tweaks: options, the SB Image section, which widgets get it, any_device(), migrate() and the alt text conversion |
| features/elementor-image-alt/ | SB_Elementor_Image_Alt: its controls and the render filter |
| features/elementor-image-ratio/ | SB_Elementor_Image_Ratio: its controls, all CSS through Elementor selectors |

Options: sb_tweaks_elementor_features, _settings (unused so far), _groups, and
sb_tweaks_elementor_migrated once the move over has run.

### The SB Image section, and which widgets get it

A feature registers what it adds with SB_Elementor_Tweaks::add_part( $id,
callable( $widget, $info ), $ratio_only, $order ). The module then adds one SB
Image section (id sb_elementor_image, Content tab) per widget and calls each
part inside it, alt text first.

Elementor has no hook for "a widget's own settings are all registered", so the
section goes in on elementor/element/after_section_end, straight after the
first section that holds an image setting. That also places it right under the
image in the panel. The test, SB_Elementor_Tweaks::is_image_control():

- counts a Media control that takes images, a Gallery control, and
  image_dimensions, the custom size box Elementor puts beside every image size
  dropdown (Group_Control_Image_Size). That last one catches widgets that take
  their image from a field, such as Dynamic Content's ACF Gallery and ACF
  Fields widgets, and the featured image widgets. Media inside a repeater
  counts too (Testimonial Carousel, Reviews, Price List).
- ignores names starting with an underscore (the shared Advanced tab), and,
  case insensitive, names containing background, overlay, fallback, mask,
  slideshow, marker, button, poster, hosted_url, icon or video. Those are not
  the widget's own picture. imageMarker on the ACF Google Map widget is why the
  match is case insensitive.

Elementor strips labels and tabs from controls on the front end
(Performance::should_optimize_controls), so the test reads type and name only,
which are the same in the editor and on the front end. The controls must
register on the front end too: settings with no control are dropped before
rendering, and a dynamic tag in them is never filled in. The stack is read with
controls_manager->get_element_stack(), not $widget->get_controls(), which would
pull in the shared Advanced tab while it is being built.

The ratio part only goes on widgets with no ratio of their own:
is_ratio_control() spots aspect_ratio, item_ratio, thumbs_ratio and per device
copies already registered, and own_ratio_widgets() lists the ones whose ratio
comes after their image (gallery, media-carousel, video, posts, archive-posts,
loop-grid, loop-carousel; filter sb_tweaks/elementor/own_ratio). The filter
sb_tweaks/elementor/image_widget gets [ widget, ratio, selector, background ]
and the widget, and can change the selector or return false to skip a widget.

On Epoxy (Elementor 4.3.5, Pro 4.3.2, Dynamic Content for Elementor) that gives
both parts on Image, Image Box, Image Carousel, Basic Gallery, Testimonial,
Testimonial Carousel, Reviews, Call to Action, Flip Box, Hotspot, Price List,
Author Box, Site Logo, Featured Image, Video Playlist, the Link in Bio and
Contact Buttons widgets, and Dynamic Content's ACF, ACF Gallery, ACF
Relationship, ACF Repeater, Dynamic Posts, Featured Image, Terms and User
Fields widgets; alt text only on Gallery and Media Carousel. Atomic (v4)
widgets are not covered: they use props, not controls.

### Alt text

SB_Elementor_Image_Alt::render() filters elementor/widget/render_content. When
the widget's sb_elementor_alt is custom or none it reads the finished HTML with
WP_HTML_Tag_Processor and sets alt on every img, or aria-label on an element
with role="img" (Media Carousel draws its slides as backgrounds that way). It
never searches the HTML for the old alt text, which is where the old plugin
fell over: an empty or missing alt, or one escaped differently, and its swap
silently did nothing, and it only ever covered the Image widget.

- Custom text comes from get_settings_for_display(), so dynamic tags are filled
  in for the post being shown, loop items included. HTML is stripped and
  entities decoded first; the tag processor escapes the value itself.
- Custom with nothing in it (a tag that came back empty) leaves the images
  alone rather than blanking them.
- Only where empty (sb_elementor_alt_fill) skips images that already have alt
  text, so a gallery keeps its own and only the gaps get the custom text.
- None sets alt="" on images, and on role="img" elements removes aria-label and
  sets role="presentation".

Rank Math's Image SEO "Add missing ALT attributes" fills any empty alt in
the_content at priority 11, after Elementor has rendered, so on a site with it
on, None ends up as Rank Math's alt format instead. Epoxy has it on
("%title% %count(varname)% - %sitename%"). The old plugin's none had the same
problem. Templates outside the_content (headers, footers, loop items) are not
touched by it.

### Aspect ratio, fit and position

Plain Elementor selectors on '{{WRAPPER}} img', so the CSS goes into the page's
CSS file, shows live in the editor and is responsive like any other setting.

- Aspect ratio (sb_elementor_ratio, responsive) writes aspect-ratio plus
  object-fit: var(--sb-elementor-fit, cover) and object-position:
  var(--sb-elementor-position, 50% 50%). Original writes nothing, so an image
  is left exactly as Elementor draws it until a ratio is picked.
- Object fit and Position set those two custom properties on the same img.
  Their defaults are empty, so cover and centre centre come from the fallbacks.
- Custom in the ratio or position dropdown writes nothing itself
  (selectors_dictionary custom => '', which Elementor skips), and the Custom
  box below supplies the value with the same rule. That way a custom value
  typed on desktop still carries down to tablet and mobile.
- The Custom boxes, fit and position show when any device's dropdown asks for
  them: SB_Elementor_Tweaks::any_device() builds an or condition over the base
  name and every active breakpoint with the in operator. Elementor's plain
  condition reads only the desktop value, and != '' would be true for a device
  with no value at all.
- The ratio and position dropdowns are frontend_available. That makes
  Elementor register a real copy per device instead of one responsive
  control, so every per device name those conditions read exists. Without it,
  Elementor's server side check (includes/conditions.php, which reads the key
  without isset) logged an undefined array key warning per device for every
  image widget left at Original, on every render: seen in Epoxy's log during
  the test, gone after. The cost is that a widget with a ratio or position
  picked carries those values in its data-settings.

Widgets that draw their picture as a CSS background rather than an img
(SB_Elementor_Tweaks::background_widgets(), filter
sb_tweaks/elementor/background_widgets) get the shape on the picture's box
instead. So far that is only the Call to Action, whose picture is a background
on .elementor-cta__bg inside .elementor-cta__bg-wrapper. Sam found it on Epoxy's
home page: the img rule had nothing to hit.

- Aspect ratio goes on the box with height: 100%. The card is a wrapping flex
  row, so without it the box is stretched as a flex item and the ratio is
  ignored. The card has no set height, so the 100% itself works out as auto.
- min-height: 0 on the box, through a selector one class stronger
  ({{WRAPPER}}.elementor-widget), so the ratio wins over Elementor's 200px
  default and the widget's own Height setting, which Elementor merges into the
  same CSS block after ours.
- Object fit becomes background-size (Cover or Contain only, with
  background-repeat: no-repeat) and Position becomes background-position.
- The settings only show on the Classic skin with the image above or below.
  Cover fills the whole card, and with the image left or right the box is as
  tall as the text beside it.
- Alt text goes on the background's aria-label only. An img inside, such as
  the Call to Action's graphic image, keeps its own alt.

Not handled yet: Dynamic Content's Dynamic Posts has a background image mode
(dce-bgimage) that the img rule misses too.

## Moving a site over

The old plugin saved two settings on Image widgets: alt_text_type (attachment,
none or custom; attachment is its default) and custom_alt_text, with a dynamic
tag under __dynamic__.custom_alt_text. Sam chose this module's own names
rather than keeping the plugin's, so they are renamed once.

SB_Elementor_Tweaks::migrate() runs on every request until it has run, and
waits until Elementor is active. convert_old_alt() then, on Image widgets only
and in every _elementor_data row, revisions included:

| Old | New |
| --- | --- |
| alt_text_type custom or none | sb_elementor_alt, same value |
| alt_text_type attachment | dropped (this module's default) |
| custom_alt_text | sb_elementor_alt_text |
| __dynamic__ custom_alt_text | __dynamic__ sb_elementor_alt_text, tag settings (before, after) kept |

It decodes to objects, not arrays, so empty settings stay {} and the JSON comes
back exactly as Elementor writes it (wp_json_encode): on Epoxy all 1,031 saved
documents re-encoded byte for byte. It writes the row directly, clears the
post_meta cache and the post's _elementor_element_cache. Where the plugin was
active or anything was converted, the module is switched on with Image Alt Text
on (sb_tweaks_elementor_features created). A site that never had the plugin
starts with the module off. The plugin itself is switched off and kept off by
the framework (replaces), on every site with this module on disk, whether or
not the module is on.

convert_old_alt( true ) is a dry run: it returns the post ids it would change
and writes nothing.

Known sites with the plugin (October 2026):

- epoxyflooringco.com.au: six Image widgets, all custom. Typed: home page
  fc8c0a4, Quote Request Thank You 5400375, Australian Owned Hero 4df894e2.
  Post title tag: Loop Epoxy Colours 7a71d30 (after " Flake Flooring"), Loop
  Sponsorships d167edd, Loop Item Google Reviews 218c6ea (before "Google
  Review by "). The dry run lists 205 posts: those six and 199 revisions.
- Terrace Marina: two Image widgets, both the post title tag: Single Hire
  Product Template (782) 53f8046 and Hire Products Loop Item (863) 74c1364.
- Noosa's Elite Car Spa: not installed, and no snippet, theme file or plugin
  does alt text.

## Tested on Epoxy (October 2026)

Tested through a test copy, not the real plugin, since Epoxy had no SocialBUMP
Tweaks yet: wp-content/plugins/sb-elementor-tweaks-test, "SB Elementor Tweaks
(test copy)", the module folder copied from the hub (md5 checked) plus a loader
that boots both features without the framework and without migrate(). It sits
out if SocialBUMP Tweaks is present. Delete it before SocialBUMP Tweaks is
installed there. A draft page, "SB Elementor Tweaks test (delete me)" (13644),
held the test widgets.

Rendered as a logged in admin over HTTP:

- Typed custom alt with an ampersand: right, and escaped once.
- Post title tag, Only where empty, image with no alt: the title.
- Only where empty on an image with its own alt: kept its own.
- Basic Gallery, Only where empty: filled the one image without alt, kept the
  other.
- No settings: untouched.
- Image Carousel, None: Rank Math refilled the empty alts (see Alt text).
- CSS: 1/1 with top centre and 16/9 under the mobile breakpoint; custom 5/4
  with contain and a custom 50% 30%; 4/3 on the carousel's swiper images.
- Call to Action: 4/3 on the box with height 100% and min-height 0, the
  min-height 0 still winning with Height set to 350px (250px on mobile);
  custom 5/4 with contain and 50% 30%; 16/9 on mobile; nothing written on the
  Cover skin or with the image left; typed alt with an ampersand on the
  background's aria-label, the graphic image keeping its own alt.
- The conversion, on copies of Epoxy's own widgets: typed text, and post title
  tags with before and after text, came through and rendered.

Not yet seen in the Elementor editor in a browser.

## Removing the move-over code later

Once every site with the old plugin has updated past the release that added
this module, migrate() and convert_old_alt() can go. Keep replaces while any
site might still have the plugin on disk.
