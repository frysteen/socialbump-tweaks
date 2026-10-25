I'm moving a WordPress site onto the standard image sizes from SocialBUMP Tweaks' Image Management module. I need your help finding everywhere the old sizes are used, then updating the pages and templates.

Work on the site this chat is about, through the SocialBUMP MCP hub connector. Read the hub guide, then the site's novamira/agent-context and its skills, and use the site's own abilities before PHP. Before changing anything, read wp-content/plugins/socialbump-tweaks/modules/images/docs/context.md on the site, especially "Switching a site to the standard sizes" and "Locked sizes", and tell me briefly what you've read.

THE SIZES
- The standard sizes are widths, uncropped (the height follows each image): image-450, image-665, image-890, image-1330 and image-1920. WordPress's own thumbnail, medium and large count as standard too, but check this site's widths for them on SB Image Settings, as some sites have changed them.
- Old sizes are anything else the site registers or still has files for. Usually that's image-240, image-480, image-640, image-720, image-960 and image-1200 from Site Kit, the old cropped -square and -4x3 sizes, Elementor custom sizes (elementor_custom_WxH), theme sizes (wpex_ and the like), and WordPress's 768, 1536 and 2048 extras.

WHAT I LOOK AFTER, SO DON'T WORRY ABOUT IT
- Building the new thumbnails (Build Thumbnails on SB Image Cleaner) and, at the end, Remove old sizes, leftover thumbnails and orphaned images. Don't create or delete image files yourself.
- Keeping an old size: the padlock beside it on SB Image Settings. A locked size stays registered and its files are never removed. Anything that sent emails or other websites link to directly has to be locked, not changed.

WHAT I NEED FROM YOU, IN THIS ORDER

1. Find every use of an old size. Change nothing yet. First tell me which theme and page builders the site uses (it may have more than one, and old pages can sit in a builder that's no longer switched on), then check each one:
   - Bricks: _bricks_page_content_2 and the header, footer and template data (image, gallery, carousel and lightbox sizes, background images, and dynamic tags carrying a size such as {featured_image:image-480} or {acf_field:image-480}).
   - Elementor: _elementor_data (image_size, thumbnail_size and any other *_size key, plus custom dimensions).
   - WPBakery: shortcodes in post_content and Templatera templates (img_size on vc_single_image, vc_gallery and the like, custom width x height values, and theme sizes such as Total's wpex_).
   - Divi: shortcodes in post_content (src URLs on et_pb_image and others, gallery and background images), the Divi Library (et_pb_layout) and Theme Builder layouts (et_template, et_header_layout, et_body_layout, et_footer_layout). Divi 5 stores blocks in post_content instead of shortcodes.
   - Beaver Builder (_fl_builder_data, _fl_builder_draft), Oxygen (ct_builder_json, ct_builder_shortcodes), Breakdance (_breakdance_data). For any other builder, find where it stores its layouts and search there.
   - Gutenberg: post_content (sizeSlug, size-<name> classes, file URLs ending -WxH), including reusable blocks (wp_block) and block theme templates and template parts (wp_template, wp_template_part), where block theme headers and footers live.
   - Hard-coded <img> tags and image URLs in HTML and code elements: Bricks Code and HTML elements, Elementor HTML widgets, Custom HTML blocks and text widgets.
   - Direct file links anywhere: post content, post meta, options, widgets, theme settings and SEO images. Match each -WxH file to its size through the attachment's metadata.
   - Code: WPCodeBox snippets, the child theme and mu-plugins (add_image_size, shortcode defaults, wp_get_attachment_image_src and similar calls naming an old size).
   - Email: FluentCRM, MailPoet or similar campaigns, templates and automations. Any size a sent email links to needs locking.
   Leave out srcset (WordPress picks those itself), revisions, attachment metadata, image optimiser records (WPvivid and the like) and backup options.

2. Report back, then wait for my OK:
   - A table of every page, template and snippet: the element, the old size, the size you suggest, and an edit link (https://<site>/wp-admin/post.php?post=ID&action=edit).
   - The sizes that must be locked, and why.
   - Snippets or theme code that register old sizes, for me to switch off later.
   - Anything you couldn't check.
   For the new size, follow the table under "Pick the new size" in the docs. Where the old size was cropped (-square, -4x3), keep the shape with the builder's aspect ratio setting (cover, centre centre), as the docs describe.

3. Once I've confirmed, update the pages and templates:
   - Check the new size files exist first. If they don't, ask me to run Build Thumbnails.
   - Back up everything you're about to change to /tmp as sbx-<stamp>-*.json.
   - Use the builder's own abilities where the site has them (Bricks, Elementor). For a builder without them (WPBakery, Divi and the like), tell me first how you'll make the change, then change only the size name or file URL in its stored data. For a static image, update its stored URL to the new size's file as well.
   - Where a builder has no aspect ratio setting, keep a cropped shape with CSS on the image (aspect-ratio plus object-fit: cover).
   - Regenerate each builder's CSS and clear any caches.
   - Check the live pages: no image uses an old size (srcset aside) and none is broken.

4. Tell me when it's all clear, so I can switch off the old snippets and run Remove old sizes. Then do a final check: the registered sizes, the locked sizes' files still there, the links in sent emails still working, and no broken images on the main pages.

Ask before any change, and only tell me what you've actually checked.
