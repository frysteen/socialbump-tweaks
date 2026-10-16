# Image notes from standalone SocialBUMP Site Kit (reference)

The image sections of Site Kit's notes as they stood at 1.2.6, the last version
before its Images and Image Cleaner pages became this module. Kept for the detail
on how the size engine, the cleaner and the media library extras work, and why.
Read them with these names: SBSK_Images as SB_Images, sbsk/ hooks as
sb_images/, sbsk_images_ AJAX actions as sb_images_, sb_tweaks_site_kit_
options and user meta as sb_tweaks_images_, the Images page as SB Image
Settings, the Image Cleaner page as SB Image Cleaner, and Site Kit's own sizes
as this module's. Anything about Site Kit's groups, switches, publishing or the
release process no longer applies. The current notes are context.md.

---

### Images

**Images and Image Cleaner.** The largest part of the kit, and the one to be most
careful with. Two modules since the split. Images (modules/images) holds
SBSK_Images for the sizes and the settings page, and _Rebuild, the size engine:
building, clearing, present(), owned sizes. Image Cleaner (modules/image-cleaner)
holds _Tools for the page, the AJAX handlers and the attachment panels, _Orphans
for what is on disk but not in the database, and _Cleaner for its page. The
engine stays with Images because registering a size is what records it as ours,
and that has to keep happening whether or not the cleaner is on. The cleaner does
not need Images switched on: its boot loads the two Images classes itself, which
registers nothing, and it covers whatever sizes anyone has registered.

With the cleaner off nothing of it loads, and that is the point: the sizes panel
used to be built for every attachment the media library listed, about 600 file
checks a page, and the edit screens carried a stylesheet, a script and jQuery
for it. styles() only enqueues on upload.php, post.php and post-new.php while
the cleaner is on. The old rebuild_on switch became the cleaner's group switch:
sbsk_split_image_cleaner() in the main file carries it across once, records
scheme 2 in sbsk_image_split, and drops rebuild_on from the settings.


**Tidy titles on upload.** clean_title() keeps a name that already has spaces
exactly as typed, minus the extension, so "Epoxy Flooring Warranties - UV
Stability" survives with its dash and capitals. A name with no spaces is slug or
camera style: dashes, underscores and dots become spaces, other punctuation goes,
and each word gets a capital first letter without the rest being lowered, so
sdi-UV-test becomes Sdi UV Test. It used to strip every dash and title-case with
mb_convert_case, which also lowered the rest of each word (UV became Uv). Alt
text copies the cleaned title, only when the image has none.

on_upload() reads the stored title with get_post_field( 'post_title', $id, 'raw' ),
not get_the_title(): that runs wptexturize, which turns " - " into &#8211;, and the
entity ended up in the alt text (shown as 8211 by the old cleaner, as &#8211; by
1.1.15). clean_title() also decodes entities first, as a second guard.

**Gutenberg image zoom** (class-sbsk-images-zoom.php, gallery-zoom-editor.js,
gallery-zoom.js, gallery-zoom.css): switched on under Image extras on the Images
page, setting gallery_zoom (the key kept its first name). The core gallery and
image blocks gain two attributes via the blocks.registerBlockType filter
(sbskZoom, sbskZoomSize) and a Zoom sidebar panel: a toggle and the popup size,
fed by size_choices(), which lists every registered size with its width. The
size on the page is deliberately left to each block's own Resolution setting;
an earlier build had its own page-size dropdown and it duplicated that. An image
block inside a gallery hides the panel (getBlockParentsByBlockName), since the
gallery's setting governs its images. On the front end render_block_core/gallery
and render_block_core/image mark each wp-image-<id> img with data-sbsk-zoom,
the popup size URL, through the WP HTML Tag Processor, and the lightbox assets
enqueue once from whichever block rendered first. The popup is our own: a
gallery's images page through with arrows and arrow keys, a single image opens
alone, Esc or a click on the dark area closes, captions come from the
figcaption. The native WordPress lightbox was no use here because it always
opens the full-size original. The popup size is validated against
get_intermediate_image_sizes() at render, falling back to full. Everything
steps aside when the switch is off; the block attributes simply sit unused in
the saved markup.

On a Bricks site the gallery's columns, cropping and gaps depend on Bricks
Tweaks' Gutenberg Block Styles, because Bricks strips core's block CSS on the
pages it renders. Zoom itself works either way.

#### The rule that everything else depends on

A size counts as present only when its file is really there. Metadata is not
proof, and treating it as proof caused the worst bug in this module.

SBSK_Images_Rebuild::present( $id, $meta ) answers it, by checking each entry in
the metadata against the disk. missing(), missing_all() and the per attachment
panel all go through it. Anything new that asks what an attachment has must go
through it too.

What happened without it: a size was removed, its files were deleted, and the
metadata entries stayed behind. Adding the size back left an entry pointing at a
file that no longer existed, so the scan saw the entry, called it present, and
reported nothing to build. 638 images sat with no 480 and nothing said a word.
The same gap was showing in the details panel, which printed dimensions for a
file that was not there. Three places, one wrong assumption.

#### Which sizes the cleaner works on

The Image Cleaner page carries the list: every registered size, grouped as Image
Sizes (ours), WordPress, WooCommerce and Other Image Sizes, with all and none per
group and Select all and none at the bottom. Whatever is ticked is what the scan
counts, what Build fills in, what a forced rebuild remakes, and what Remove old
sizes clears. It replaced the picker that only appeared when forcing.

The choice is per user, in the sb_tweaks_site_kit_cleaner_sizes user meta, and everything is
ticked until a choice is saved. A name that is no longer registered is dropped on
the way out of chosen(), so removing a width cannot leave a stale tick behind.

It is a plain form posting to admin-post, not AJAX, so save-state.js does the
rest: the button stays quiet until a tick changes, the reminder follows you down
the page, and leaving with changes pending warns first. Reloading without saving
throws the ticks away, which is the point. The all and none links are marked
data-sb-always-on so the reminder never mistakes one for the save button, and
they dispatch a real DOM change event: save-state listens with addEventListener,
and a jQuery trigger never reaches it, which left the button disabled while the
ticks plainly changed.

The engine takes it as an $only argument on missing_all() and build(). Null
means every size, which is what the attachment panel and a single image rebuild
still pass.

It deliberately does not reach stale(), stale_files() or clean(). A size that has
been removed is no longer registered, so it can never appear in the tick list,
and filtering by that list meant removing a width left its files behind with the
scan reporting nothing to clear. Clearing old sizes always covers every removed
size, whatever is ticked.

wanted() returns nothing at all while the Image sizes switch is off, because
nothing is registered then, so every image-* file an attachment carries becomes
a leftover to clear. Reading the saved widths without checking the switch meant
turning the feature off left every file in place with the scan reporting nothing
to remove.

Each row is a flex line that wraps: a long size name such as
woocommerce_gallery_thumbnail pushes its pixels down to their own line, right
aligned, rather than out of the box. Rows are striped, every other one on a faint
grey, purely to be easier to read down a long list.

The progress box carries a clock on the right: how long the run has been going,
and the average per image, which is the half worth reading because it says
whether a large library means two minutes or twenty. Both are repeated in the
finished line. Cancel sits where the close cross goes, and they swap: Cancel
while it runs, the cross once it has stopped. Cancelling lets the batch in
flight finish and asks for nothing after it, so nothing is half written; the
line then reads Stopped rather than Finished. There is no thumbnail column any
more: each row in the log carries its own image, and the column only left a gap
down the left of everything.

The log keeps every image of the run rather than the last twenty, and the box
scrolls. Each row carries the file name, the original dimensions under it, the
sizes made, and any size that was asked for and not made, which says why. All of it used to
show as an image with nothing under it, reading like a failure.

skip_reason() works out the wording, and the cropped and uncropped cases are not
the same. A cropped size needs the original to be at least that big in both
directions, so a wide but short original fails on the height. An uncropped size
is a box to fit inside, so its height is a maximum, not a requirement: a
1920 x 1280 original needs no 1920 x 1920 large because it already fits, and
calling that image is smaller was simply wrong. The wordings are: already this
size when the original sits on an edge of the box, image is smaller when it fits
inside with room to spare, and not needed when it is bigger than the box, which
means something other than the dimensions stopped it and guessing would lie.

The sizes panel on an attachment says the same three things. A row that is not
there now gives the size it would have been and why it was not made, rather than
a bare not needed, which covered all three reasons at once and told you nothing. A clean
logs its rows too. log_item() in the tools class builds all of it.

A plain build only logs images something was actually done to, since it passes
over most of the library and a row per untouched image says nothing. A forced
run logs every image, because there the skipped ones are the interesting part.

#### How the engine and the tools keep their cost down

- make_sizes() decodes an image once and makes every wanted size from it with
  multi_resize(), which on both GD and Imagick works from the original pixels
  for each size. build() and rebuild() used to call wp_get_image_editor() per
  size: thirteen decodes of one photo for one rebuild. multi_resize() names
  files after the file it loaded, and the attached file can be the -scaled one
  while the thumbnails carry the original name, so each made file is moved to
  the base_name() the set uses, replacing what was there. Two sizes with the
  same dimensions share one file, thumbnail and woocommerce_thumbnail both
  being 300 x 300 cropped, so the first one moves it and the second finds its
  source gone: it has to point at where the file went instead. Missing that
  wrote metadata naming a file that was not there, and the size showed as
  missing straight after being built. It only shows on a -scaled original,
  where the names differ at all, and only with two sizes of equal dimensions,
  so a site without WooCommerce never sees it. Test rebuilds on a site that
  has both.
- can_make() is the one rule for whether a size is worth making, used by both
  missing_all() and rebuild() so forcing is never a smaller job than a plain
  build. It asks image_resize_dimensions(), which is what WordPress itself
  uses, rather than working the rule out again. Two hand rolled versions got
  it wrong: judging on width alone made a forced rebuild skip a 1536 x 1536 on
  a 1281 x 1920 portrait; requiring a crop to fit both ways reported 600 x 400
  images as missing a 600 x 400 crop for ever, since WordPress makes no file
  when the result would be the original, and hid the cropped sizes WordPress
  does make from a short source, such as 600 x 466 for a 600 x 600 crop of a
  1200 x 466 image. Ask WordPress; do not restate its rules.
- dimensions() reads the original's size from the file, not the metadata, and
  everything that decides what a size should be uses it: present(),
  missing_all(), rebuild() and the leftover scan's expected_dimensions(). An
  optimiser that caps uploads, WPvivid here, rewrites the file and leaves the
  metadata saying what it used to be. Eleven images on feelsoma.com did, one
  claiming 2560 x 1922 for a file that is 1920 x 1442. Everything between those
  numbers was reported missing and could never be built, because the resize the
  metadata implies is impossible, and 271 files were offered for deletion as
  leftovers because they were judged against sizes that original cannot make.
  Reading the header is about a tenth of a millisecond, 0.05s across 401
  images, and it is the only number that can be acted on. The metadata itself
  is left alone: correcting it is a repair, not a scan's business.
- A run works through the images that have something to do, not the whole
  library: ids() takes the mode and filters to the ones missing a size, or
  carrying a stale one, before the run starts. A plain build on a library that
  is nearly current walked all 416 images on feelsoma.com to find the 7 that
  needed anything, and the bar crawled through hundreds doing nothing visible.
  The filter costs a fifth of a second once. A forced rebuild remakes
  everything, so it keeps the whole list. The batch reports the run length so
  the bar counts to what it will really do.
- A file still in use names what is using it, with a link to edit that post
  where the person can edit it, through references_html(). references() carries
  an edit URL beside each label, and references_text() is the plain version for
  anywhere a link cannot go.
- Deleting leftovers keeps the same clock the rebuild does: elapsed and per file
  while it runs, and both again in the finished line.
- stale() measures against every size registered right now, not just our own
  list. A site can register image-480 from a snippet while this module is
  switched off, and those files are live: comparing with our list alone called
  1,234 working thumbnails old sizes to clear on feelsoma.com, one button press
  from deleting them. A size anyone registers is never stale, whoever made the
  files. Note the collision this implies: add_image_size is last-one-wins and
  silent, and a snippet on init beats our after_setup_theme, so on a site with
  both the settings page can show one spec while the site makes another.
- present() counts a size as there only when the file exists AND its recorded
  dimensions still match what the size would produce now. Checking existence
  alone meant changing a size's dimensions was invisible: every image kept a
  real file at the old size, so nothing ever showed as needing a rebuild, while
  the old files turned up under leftovers. Changing staff_entry from 270 x 400
  to 400 x 600 on doogood.com.au left 370 entries at the old size and a scan
  reporting no work.
- log_item() returns one list of rows, made and skipped together, smallest
  size first, so the log reads as the set of sizes rather than two lists stuck
  end to end.
- The Sizes to build figure is a button when it is not zero, and opens the list
  of images behind it: a row each, with the sizes that image is missing, a
  Rebuild by the name for the whole image and one by each size for that size
  alone. summary() collects the rows on the pass it already makes, capped at
  LIST_CAP images, because adding a width makes the whole library missing it
  and nobody reads six hundred rows. ajax_fix() does the work and returns what
  is left, so a row updates itself or disappears.
- clean() drops the metadata entry for a stale size, but the file only goes if
  file_has_other_owner() says nothing else uses it: another size of the same
  attachment with the same dimensions (medium at 480 and image-480 share one
  file), the original itself, or any other attachment whose metadata names
  the file. That last check reads file_owners(), built once per request
  (see Clearing old thumbnails faster below); it used to be one LIKE on
  postmeta per stale file.
- A run holds its id list in a transient for ten minutes (sbsk_images_run_ per
  user and per mode, pushed on another ten minutes by every batch that reads
  it; sbsk_orphans_run_ for the orphan list) instead of every
  batch fetching the whole library again. One list per tool: a build, a clean
  and a forced rebuild each keep their own, so switching tools mid run cannot
  hand one the other's ids. ids() also warms the attachment meta cache in
  chunks of 500, so a scan reads metadata in a handful of queries rather than
  one per image. Scan starts a run fresh; the last batch clears it.
  The orphan run steps a whole batch on each time because the list is fixed;
  remove() checks every file again before touching it, so a list gone a
  little stale costs nothing.
- summary() is one pass over the library and gathers the per size totals the
  report needs on that pass; the report used to walk it all again. The orphan
  reference lookups, three unindexed queries per file, run once per file and
  the rows reuse the answer.
- The media modal panel is a placeholder until its pane is on screen, then
  sbsk_images_panel fetches the list. attachment_fields_to_edit runs for every
  attachment the library sends to the browser, forty a page, and the list is a
  file check per size, so building it there cost hundreds of stats a page for
  panes nobody opened. admin.js watches the page for new panes with a
  MutationObserver and fills each once. The full edit screen still builds its
  meta box in place, one image, no cost worth saving.

#### Find leftover thumbnails (the deep scan)

Three things can be wrong with a size file, and each needs its own check.

- Missing: a registered size with no file. missing_all(), fixed by Build.
- Removed: a file the metadata still names, for a size the kit no longer
  registers. stale(), fixed by Remove old sizes, and only ever our own names.
- Unaccounted: a file on disk that nothing in the database names, whose
  dimensions no registered size would produce. unaccounted(), fixed by the
  deep scan.

The third one exists because the orphan scan deliberately gives a free pass to
any file whose name matches a real attachment, through belongs_to_attachment(),
so that a WebP sibling or a thumbnail missing from its metadata is never
deleted. The cost is that an old theme's photo-1300x200 is invisible: not an
orphan, because it looks like it belongs, and not a removed size, because it
was never in the metadata at all. On an old site that is most of the junk.

unaccounted() walks the uploads folders, takes every file whose name ends in a
WxH, and keeps the ones where the base is a real attachment, nothing in the
database names the file, and those dimensions are not among the ones
expected_dimensions() says the currently registered sizes would produce for
that attachment. That last test is what protects a valid thumbnail whose
metadata went missing: it would be rebuilt at those exact dimensions, so it is
left alone. expected_dimensions() is pure arithmetic through
image_resize_dimensions(), no files touched.

The size list groups by ours, WordPress, WooCommerce and Other. Grouping the
rest by the theme or plugin that registered them is not possible and was tried
twice: WordPress records nothing about who registered a size; reading the code
for add_image_size misses anything registered through a filter or with a built
up name, which is how Total and Gravity Forms both do it; and registration
timing puts the theme and the plugins in the same bucket, since both land on
init. A wrong label is worse than none on a page whose next button deletes
files, so Other it is.

Results are grouped by dimensions, not by image, because that is the shape a
person can judge: 1024 x 750, 412 files, 180 MB is recognisably the old
WordPress large, where 412 file names would tell you nothing. You tick groups
and confirm; nothing is deleted by scanning. The list carries a red heading, the
instruction, and one line saying a plugin switched off right now will appear
here as well. That last line is the only way this feature can bite, so keep it.

The panel has its own cross, and a normal Scan images clears it, because by then
it is the answer to the previous question. The quiet re-scan after a deletion
leaves it alone, since that run redraws it with what is left.

Deleting runs in batches of DEEP_BATCH files, the browser calling until done,
behind the same bar the rebuild uses, with the count under it. Each batch is checked again server side: still
unaccounted, not kept, and not referenced, through references_many(), which
does the three LIKE scans once per fifty names with the names OR'd together
rather than three per file. That is the slow part of a deletion, and on a big
database it is still a few seconds per fifty, so a thousand files is a couple
of minutes; per file it was closer to ten. Files left alone come back with a
reason and are listed above the refreshed panel rather than quietly dropping
out of the count.

The reference check reads the content once rather than once per file.
mentioned_names() pulls every image file name out of post content, postmeta and
options, paged through by id so no row is ever left unread on a big site, and
holds the set for ten minutes; after that a check is
an array lookup, and only a name that is actually mentioned costs a query, to say
what is using it. It was three LIKE scans per file before, each reading a whole
table, so a site with 685 orphans spent 64 seconds on a scan that is otherwise
a fifth of a second. Batching the names into one OR'd query does not help,
because the LIKEs still scan; reading the content once is the only fix. Measured
on doogood.com.au: 64s to 0.24s, plus 0.28s to build the index, same answers.
The index used to stop at a fixed row cap per table, and rows past the cap were
invisible: on a big Bricks site a used file could be called an orphan because
the row naming it was never read. It pages through everything now. Do not put a
cap back.

The index is rebuilt at the start of every scan, not reused from the transient,
because a scan answers for the site as it is now: content deleted since the last
scan otherwise still looked like a use. The ten minute hold only covers the
deletion that follows a scan. And when the targeted queries find nothing, the
answer is nothing: an earlier version said site content instead, on the grounds
that the index had seen the name somewhere, which held files back over a mention
that had already been deleted. Term and user meta are not indexed, so they are
checked directly when nothing else matches.

Image optimisers keep a record of every file they have compressed, which
mentions the file without using it. WPvivid's stopped a plainly stale thumbnail
being deleted. Those meta keys are in bookkeeping_keys(), filterable, and both
reference checks ignore them. Each group opens to the files behind
it, with a thumbnail, the file name and a link that opens it in a new tab, capped
at LIST_CAP per group, because dimensions alone are not enough to judge a
deletion by.

The honest limit, and it is on the screen: a size is only known to be
registered if something registers it now. Deactivate WooCommerce and its three
sizes look unaccounted. That is why this is ticked and confirmed rather than a
single button, and why remove_unaccounted() recomputes the list and rechecks
is_kept() and references() per file rather than trusting the paths the browser
sends.

#### Sizes

- Registered on after_setup_theme, and added to the editor size chooser through
  image_size_names_choose.
- Widths live in the module settings. Saving them changes what the site wants;
  it does not touch a single file. The scan, then Build or Clear, brings the
  disk into line. That is deliberate: on a few hundred images it is a long job
  and belongs behind a progress bar, not a form submit.
- Never calls wp_create_image_subsizes(). That regenerates everything from the
  original and loses manual crops. It builds size by size instead.
- Sizes the kit did not create are left alone. sbsk_owned_image_sizes records
  what it made, so a theme size is never cleared as though it were ours.
- Removing a size deletes the file, the WebP and AVIF copies beside it, and the
  metadata entry, in one pass. The metadata matters as much as the file: an entry pointing at a
  deleted file makes WordPress hand out a URL for an image that is not there.

#### Rebuilding

- Batches of five over AJAX: sbsk_images_count, then sbsk_images_batch, then a
  final sbsk_images_report. Each request is told its offset, so nothing depends
  on order and nothing is redone.
- A request that comes back empty is retried once, one image at a time, and only
  a second failure is reported. Each request starts where the last finished, so
  a failure costs nothing and what is built is kept.
- All three handlers raise the time limit. Measured on a 672 image library the
  scan takes well under a second, so slowness was not the cause of the one
  failure seen in the wild: the rebuild had finished and a later request simply
  never answered. The raised limits are insurance for a slower host.
- The progress log gives each image its own row: its own thumbnail, its name,
  and the sizes built for it underneath. It used to show one thumbnail beside a
  batch of names, which never matched what you were reading.

#### The orphan scan

- Compares the uploads folder against every place the database records a file:
  _wp_attached_file, _wp_attachment_metadata, and _wp_attachment_backup_sizes.
- That last one is easy to forget and was missed at first. Editing an image in
  WordPress writes a new set of files and keeps the old ones so Restore original
  image still works, and those are recorded only in the backup meta. Without
  reading it, every edited image contributed a pile of false orphans, and
  deleting them would have quietly taken the undo away. If a site ever shows
  orphans that are plainly in use, look for another meta key like that one.
- Every known file is recorded along with its WebP and AVIF twins, since an
  optimiser writes photo.jpg.webp or photo.jpg.avif, keeping the original
  extension in front. The scan strips the .webp or .avif before checking the
  extension, so those count as images.
- A leftover thumbnail from an attachment that no longer exists is found, and
  so are its WebP and AVIF copies. Tested by planting such files and scanning.
- It walks the uploads root and its year and month folders only. A folder
  belonging to another plugin is left alone on purpose.
- Anything referenced anywhere, or marked Keep, is listed but never bulk
  deleted, and the button counts only what it will actually remove. A list of
  fourteen with a button offering two is correct, though it reads like a fault.
  sbsk_kept_orphans holds what you told it to leave.
- Each row shows a thumbnail and, where the file can be traced, the attachment
  id as a grey pill linking to the media library. Tracing tries the filename,
  then the same name with a size suffix stripped, then the backup data.

#### On an attachment

The list of sizes appears in two places, and they are not the same mechanism.

- The full edit screen gets a real metabox, SocialBUMP Site Kit Sizes, through
  add_meta_boxes_attachment. It can be dragged and collapsed like any other.
- The media modal cannot have metaboxes at all, so the field built through
  attachment_fields_to_edit carries its own postbox markup: an empty label, and
  html holding a div.postbox with a postbox-header and an inside. That is how
  the Replace Media panel does it, and it is the only way to get a title bar in
  the modal.
- The field bows out when the screen base is post, or the edit screen would
  show the same thing twice.
- WordPress lays an extra field out as a table row: a label column, and a field
  cell floated right at 65 per cent to sit beside it. With an empty label that
  leaves a gap, so the CSS takes the label column to zero and drops the float,
  the width and the 1px margin from the field. The margin is the part people
  miss: it is why a full width float overflows, and why other plugins use 99.8
  per cent instead of removing it.
- Each size that exists links to that file. A missing one stays plain text,
  since there would be nothing to open.

#### Uploads

- On add_attachment the file name is tidied and alt text filled in from the
  title, so an upload called DSC_0042 does not end up as alt text. Alt text
  written by hand is left alone, and the file on disk is never renamed.

## Images, the one with teeth

The Images module owns the registered sizes, rebuilds thumbnails in batches, and
decides what in the uploads folder is rubbish. It can delete files, so it gets
its own warning here. The full account is under Every module in detail; these
are the three rules worth reading before you touch it.

- A size counts as present only when its file is really there. Never ask the
  metadata on its own. SBSK_Images_Rebuild::present() is the only answer.
- Never call wp_create_image_subsizes() on a rebuild. It regenerates from the
  original and can lose crops. The rebuild works size by size instead.
- Only touch what we made. sbsk_owned_image_sizes records what the kit created,
  sbsk_kept_orphans what it was told to leave, and files WordPress keeps so an
  image edit can be undone are never treated as strays.

## Orphan usage check (October 2026)

Found on Villa Real Estate (Elementor): SBSK_Images_Orphans::mentioned_names()
picked rows with LIKE '%wp-content/uploads%', but Elementor stores _elementor_data
as JSON with escaped slashes (wp-content\/uploads), so every Elementor page was
skipped and its images looked unused. It also only read posts, postmeta and
options. Now:

- mention_sources() lists where a use can live: post_content and post_excerpt
  (not revisions), postmeta (minus attachment bookkeeping keys), options (minus
  sbsk_ and transients), termmeta, usermeta (minus session_tokens), and every
  JetEngine CCT table ({prefix}jet_cct_*, its text columns). Other tables can be
  added with the sbsk/orphans/mention_sources filter.
- collect_from() picks rows by REGEXP on an image extension, not by the uploads
  path, paged by primary key; names_in() unescapes \/ and records each name
  both as written and URL decoded.
- references() matches the name as stored and rawurlencode()d, in content and
  excerpts, postmeta, options, term and user meta, then any non core source,
  named as table and row.
- Log and history tables (MainWP stream, WP All Import, analytics, 404 logs) are
  deliberately not sources: they mention files once uploaded or requested and
  would hold everything back.

Verified on Villa: the new index found 1,399 names against 732, saw
web_dji_0218.jpg in an Elementor page that the old one missed, named the page,
and agreed with an independent scan of all 146 tables and 243 text files that
none of the 1,319 orphans was in use. Reading the whole site took 0.6s.

## Leftover sizes from other sources (October 2026)

Found on villarealestate.com.au: 12 sizes (image-{480..2000}-square and -4x3)
registered by a WPCodeBox snippet were commented out, but Old thumbnails to clear
showed 0 while 4,894 attachments still listed them (about 13 GB with WebP),
because stale() only counts sizes in the owned list (OWNED_OPTION), which holds
sizes Site Kit registered itself. That filter stays: it stopped 1,234 live
thumbnails on feelsoma.com being called stale.

Now:
- SBSK_Images_Rebuild::foreign() lists sizes an attachment carries that are not
  registered (all_wanted), not owned and not adopted. Tools::summary() collects
  them in the same pass as 'foreign' => name => images, files (with .webp and
  .avif) and bytes, via size_files().
- The report shows them under Leftover sizes from other sources, unticked, with
  size_warnings(): a plugin or theme style prefix, the name in _elementor_data
  or in post content (size-NAME, sizeSlug), or the name in a snippet table
  (any table matching %snippet%) or the active theme's PHP. Warnings, not blocks.
- Ticking and saving posts to admin_post_sbsk_adopt_sizes (manage_options),
  which calls adopt(): names currently registered or owned are refused, the
  rest go in ADOPTED_OPTION (sb_tweaks_site_kit_adopted_image_sizes), separate from owned.
  Other sizes chosen for clearing lists them, each with Stop clearing
  (admin_post_sbsk_unadopt_sizes, unadopt()). The scan also takes a chosen
  size off the list by itself once no image carries it any more
  (forget_adopted_unused(), fed by summary()), so after a clear and a rescan
  it is gone without pressing Stop clearing. If something writes it back
  later, it shows under Leftover sizes again, unticked.
- stale() intersects with owned + adopted, against what is registered at that
  moment, and clean() asks stale() per image during removal, so a size
  registered again is never cleared. file_has_other_owner() still guards each
  file, so a file shared with a kept size stays and only the entry goes.

Tested on the hub with image 489: a foreign size and one sharing the thumbnail
file listed as foreign, not stale; adopt() refused 'thumbnail'; both stale once
adopted; registering one again made it not stale; un-adopting dropped it; clean()
removed the entries, deleted the size's own file and its .webp, kept the shared
thumbnail; Site Kit's own (owned) sizes behaved as before.

## Media Library thumbnail size (October 2026)

The switch is now called Media Browser Enhancements (same media_grid key), since it
also covers the media pop-up and the HappyFiles folder dropdown. See the two
sections on the pop-up further down.

A switch under Image extras on the Images page, media_grid in the images
settings, on until someone turns it off. SBSK_Images_Media_Grid in
modules/images, booted by SBSK_Images::boot() like the zoom, with
assets/js/media-grid.js and assets/css/media-grid.css. Media, Library in grid
view gets two dropdowns after Bulk select: Standard, Large or X-Large
thumbnails, and Cropped squares or Whole image. The choice is per user, in the
sb_tweaks_site_kit_media_grid user meta, saved over AJAX (sbsk_media_grid_save,
needs upload_files) the moment a dropdown changes. List view loads nothing.

WordPress has no setting for any of this, so it works on the grid's own
scripts:

- Size is the column width WordPress aims for. The Attachments view works out
  its columns as the browser width over idealColumnWidth (150, or 135 under 640
  wide), capped at 12, and the CSS sizes tiles from data-columns. Standard keeps
  150, Large asks for 240, X-Large 360. The script wraps
  AttachmentsBrowser.createToolbar, which runs before createAttachments, so the
  width is in place for the first draw, and the dropdowns go in with
  toolbar.set at priority -60, on the left.
- A bigger tile needs a bigger file. Tiles normally show the medium size, which
  goes soft at 360 wide on a high density screen. Attachment.imageSize is
  wrapped for grid tiles only: Large uses medium on a normal screen and large on
  a high density one, X-Large always large, falling back to the original when
  no large copy exists and it is 2560 or less wide. The browser only knows
  thumbnail, medium, large and full, because those are what
  wp_prepare_attachment_for_js sends.
- Changing size sets the new width, calls setColumns() and re-renders every
  tile already drawn (_viewsByCid), so the new files load in place without a
  reload.
- Fit is CSS only. Standard with Cropped squares is left exactly as WordPress
  draws it. Anything else draws the image at full tile size with object-fit,
  cover for cropped and contain for whole, and takes off WordPress's translate
  centring. Only image tiles (.type-image) are touched, so document and video
  icons look as they always have. The body classes sbsk-grid-size-* and
  sbsk-grid-fit-* come from admin_body_class, so a reload never flashes the
  old layout first.
- Masonry was left out on purpose. The grid's arrow keys, selection and
  infinite scroll all assume equal tiles in rows, and HappyFiles hooks into the
  same grid. Whole image in a square tile shows the full picture without
  fighting any of that.

It was its own module for an hour, and that broke the Images page: a second
module in the images section turns the page into a card grid with the Images
settings drawn underneath, header and all. An extra for an existing page
belongs in that page's settings, not in a module of its own.

Worth knowing: X-Large loads the large copy for every tile, several times the
weight of the medium one, so a page of tiles is noticeably heavier on a slow
connection.

## Better media search (October 2026)

A switch under Image extras on the Images page, media_search in the images
settings, on until someone turns it off. SBSK_Images_Media_Search in
modules/images, booted by SBSK_Images::boot().

What WordPress does on its own, read from the 7.1.3 source:

- Searches post_title, post_excerpt and post_content, word by word, every
  word required. Alt text is never searched.
- Adds the file name only when the caller adds the wp_allow_query_attachment_
  by_filename filter first: query-attachments (the grid and every media
  modal), list view and the REST media route all do. WP_Query reads that
  filter and then calls remove_all_filters() on it, so whichever WP_Query runs
  next uses it up. If a plugin runs a query of its own in between, the real
  search loses its file names. HappyFiles has a comment about exactly this.
- The file it checks is _wp_attached_file. A photo over 2560 is stored as
  name-scaled.jpg, and an upload is renamed by sanitize_file_name(), so
  "Test Photo (1).JPG" is saved as Test-Photo-1-scaled.jpg. Searching the name
  it was uploaded with, or the unscaled name, found nothing.

Measured on the hub with a 3000 x 2000 test upload named Test Photo (1).JPG
(deleted afterwards): before, the upload name, Test-Photo-1.jpg, the alt text,
a size file name and a pasted URL all found nothing. After, all of them find it,
and every search that worked before still gives the same results.

How it works: posts_search at priority 20, only when the query is for
attachments alone, has a search, and is in wp-admin, admin AJAX or REST. The
front end is never touched. It replaces the WHERE clause with:

- each word (as WordPress parsed it, so stopwords, quotes and -exclusions
  behave as before) in the title, caption, description, _wp_attached_file or
  _wp_attachment_image_alt;
- OR the whole search read as a file name: file_variants() takes the last part
  of a URL or path, the sanitize_file_name() version, and the name without its
  extension and without -WxH, -scaled, -rotated or -e<timestamp>, and matches
  any of them against _wp_attached_file or _wp_attachment_metadata, which holds
  original_image and every size file. Skipped when a -word is present, since
  the OR would bring back what was excluded.

Meta is matched through EXISTS subqueries rather than joins, so there are no
duplicate rows and nothing depends on core's sq1 join being there. Filters:
sbsk/media_search/applies and sbsk/media_search/file_variants.

Not changed: HappyFiles limits a search to the folder that is open. That is
HappyFiles' own behaviour, and a search from inside a folder only looks in
that folder.

## Clearing old thumbnails faster (October 2026)

Found on villarealestate.com.au clearing twelve removed sizes from 3,547
images: 1.39 seconds an image, nearly an hour and a half for the run.

- The cost was file_has_other_owner(): one LIKE over every attachment's
  metadata (4,997 rows, 14.5 MB) for each file about to be deleted, 150 ms a
  time on Villa. SBSK_Images_Rebuild::file_owners() now reads all the metadata
  once per request, paged by meta_id, and records which attachments name each
  file: first (name to the first attachment seen) and shared (names more than
  one attachment uses, with every owner). The check is then an array lookup.
- It matches what the query matched: a serialised string value that is
  exactly the file name, folder included where the entry has one (see
  Size files in other folders below). Only values ending in a file extension
  are kept, which leaves out mime types. Built once per request from what is
  stored then, so a name cleared later in the same request can only keep a
  file, never delete one in use.
- Checked against the old query: every size file on the hub (59, 12 of them
  shared) and 379 cases on Villa (all 150 shared image names tried, plus every
  old size on 40 random images), no differences. Villa: the old query took
  35.8 s for those, the new check 4.4 ms after a 0.2 s build.
- The same run showed a second fault. ids() held the run's list for a fixed
  ten minutes. Once that ran out, the next batch worked the list out again,
  shorter by the images already done, while the browser kept its offset, so
  as many images were skipped as had been done (Villa's list went 3,547, then
  3,112, then 2,652 while the offset carried on). A rescan picked them up, but
  the finished line was wrong. Every batch that reads the held list now pushes
  its expiry on another ten minutes.
- The adopt and un-adopt notices were printed inside .sbsk-cleaner, which is a
  two column grid, so a notice took the left cell and pushed Sizes and Rebuild
  Thumbnails out of place. render_page() prints them before opening the grid.
  Anything printed on that page goes above the grid or inside a panel.

## Size files in other folders (October 2026)

Found on villarealestate.com.au: elementor_custom_1280x500 showed under
Leftover sizes as 3 images, 0 files. Elementor makes a custom size on demand
when an image widget is set to Custom, writes it to uploads/elementor/thumbs/,
and records it in the image's metadata as elementor/thumbs/<name>, a path
from the uploads folder, not a bare name beside the original. Every path
here was built as the original's folder plus the entry, so the files were
never found: the scan counted none, and a clear would have dropped the entries
and left the files with nothing naming them.

- SBSK_Images_Rebuild::size_path( $folder, $file ) is now the one way to turn
  an entry into a path: a bare name sits beside the original, a name with a
  folder is read from the uploads folder, and anything with a .. segment or a
  null byte gives an empty path, which every caller skips. size_files(),
  present(), process(), clean() and stale_files() all use it.
- file_owners() keeps names with a folder in them, so a shared Elementor file
  is still protected.
- Not changed: the orphan and deep scans only walk the uploads root and the
  dated folders, so uploads/elementor/ is never scanned by them.
- Tested on the hub with planted files: an elementor/thumbs size and its .webp
  were counted (2 files) and deleted, one named by a second image was kept,
  both entries went, the image's other sizes were untouched, and everything
  planted was removed. Villa's 3 files are now found (about 494 KB).
- On Villa the size came from the Single - Property Layout template (5899):
  the Post Featured Image and Image Carousel widgets still hold a 1280 x 500
  custom dimension but are set to image-1200, so nothing makes it any more.

## One Image Cleaner job at a time (October 2026)

Found on Villa: a build was running in one window, and Image Cleaner opened in
a second window looked idle. Worse than it looked: every window shares the
held run lists (sbsk_images_run_<user>_<mode>), and ajax_count() clears them
at the start of every run, so a Scan or a run started in the second window
would have reset the list under the first and it would have skipped images.

- A site wide lock, the transient sbsk_images_job (JOB in the tools class):
  token, kind (build, force, clean, orphans, deep), who started it, when, done,
  total and the current file. It lapses after JOB_LAPSE (150 seconds) with no
  batch, so a closed window cannot leave it stuck.
- The page makes a token per run (newJob() in admin.js) and sends it as job
  with every request. job_gate() refuses count, batch, report, deep,
  deep_remove, orphans, orphan_one and fix from anything without that token
  while a job holds the lock, answering with busy: the job's public details,
  never the token. ajax_count() claims the lock; each batch handler refreshes
  it at the start (job_beat() with no counts keeps the last ones) and records
  the counts after; the last batch ends it (job_end(), only for the holder).
  When a run is cancelled or fails, the page lets go straight away through
  sbsk_images_release. The page calls scan( true ) before releaseJob(), so
  the quiet rescan still carries the token if the lock is still there.
- Other windows, and other people: on load the page asks sbsk_images_status.
  While a job runs, watch() shows the progress box with the job's label, who
  started it, done of total, the current file and the time (per image or per
  file), polls every 3 seconds, keeps the run buttons off, and shows Finished
  in the other window when the lock is gone. A refused request does the same.
  The per image log is not repeated there; it only exists in the running
  window.
- Tested on the hub through the real AJAX handlers with a temporary login
  session (destroyed afterwards): a second window's scan, count and batch were
  refused with the job's details; status showed the job to other windows but
  not to the running one; the token never appeared in a reply; a release with
  the wrong token kept the lock and the right one freed it; a whole build from
  count to last batch left the lock free. The build was real and made the
  hub's few missing thumbnails. Reading the transient in the same request as
  those HTTP calls returned stale values from that request's cache, so check
  through the status call or a fresh request.


Stopping a run, and leaving the page (October 2026). A run left behind by a
page closed part way through held the lock until it lapsed, 150 seconds after
its last batch, and no window could stop it.

- pagehide sends sbsk_images_release by navigator.sendBeacon with leaving=1.
  The token is marked stopped (transient sbsk_images_stopped_<token>, ten
  minutes) as well as the lock let go, because the batch still running on the
  server would otherwise take the lock back through job_beat when it
  finished. A normal release does not mark the token: the quiet scan at the
  end of a run is sent with it and may arrive after the release.
- job_beat never claims the lock for a stopped token, and job_gate turns its
  requests away with { stopped: true }.
- A window watching another run shows Stop that run (#sbsk-progress-takeover)
  in the progress box. sbsk_images_stop (upload_files and the sbsk_images
  nonce, like the rest) marks the holder's token stopped and deletes the lock.
  The window that was running sees stopped on its next request and says it
  was stopped from another window; what it had done stays done.
- Checked through admin-ajax on the hub with a temporary session: stopping a
  held lock, the old token turned away, a late beat after a leaving release
  not taking the lock back, and a normal release leaving no mark. The pagehide
  beacon and the button were not tried in a browser.
## Missing images (October 2026)

Found on Villa: 7 library images had no file on disk at all, not the original
and not one size, all uploaded within 25 minutes on 13 April 2023 (a migration
that lost them). They showed as 52 sizes to build across 7 images, with
Rebuild links that could never work. All 7 were still used on published
portfolio pages (featured image, gallery, property_slider_images). Sam chose
to delete them rather than find the files.

- SBSK_Images_Rebuild::original_missing( $id ): the attached file is not on
  disk. missing() and missing_all() return nothing for such an image, so it
  never counts as sizes to build and Build passes over it.
- summary() collects them as gone (after noting their size names for the
  adopted list tidy) and skips the rest of its checks for them. The report
  shows a Missing images card, only when there are any, opening the
  sbsk-gone list from gone_list(): each image's name, its id linking to the
  attachment, the address WordPress expects the file at, and the page it was
  uploaded to. Capped at LIST_CAP rows; the delete covers them all. Each
  row keeps the thumbnail box, with a grey question mark in it
  (.sbsk-gone__thumb), since there is no image to show.
- Delete missing images (sbsk_images_gone_delete, GONE_BATCH 20 a request)
  runs in the progress box under the job lock as kind gone. The page sends
  the ids, and the server deletes only an image attachment whose file really
  is gone and that the user may delete (wp_delete_attachment, force), so a
  forged or stale id cannot take out a real image. WordPress itself drops
  _thumbnail_id rows pointing at a deleted attachment; ids kept in gallery
  fields (JetEngine and the like) are left as they are and point at nothing.
- Usage is deliberately not shown: finding ids inside gallery meta took about
  a second an image on Villa (a REGEXP over postmeta), too slow for a list.
- Tested on the hub with a planted entry with no file and a real image (489):
  the planted one showed only under Missing images, the card and list carried
  its address, and a delete asked for both removed the planted one and kept
  489 and its file. The planted entry is gone.

## Switching off WordPress sizes (October 2026)

Sam unticked 1536x1536 and 2048x2048 in the Image Cleaner's Sizes list and
expected them gone. That list is per user and only chooses what the tools
work on, so WordPress kept making them and their files stayed (Villa: 4,053
files, 2.15 GB, and 1,057 files, 1.23 GB). Remove old sizes never clears a
registered size, so nothing could touch them.

- The Additional thumbnail sizes list on the Images page has a tick beside
  each of SBSK_Images_Rebuild::OFFABLE: medium_large, large, 1536x1536 and
  2048x2048. Thumbnail and medium have no tick and always stay, because the
  media library and the editor show them. Unticked names are saved as
  wp_off in the images settings; the form carries sbsk_wp_list so a save
  without the list keeps what was there.
- SBSK_Images::drop_off_sizes() filters intermediate_image_sizes, which is
  what get_intermediate_image_sizes(), wp_get_registered_image_subsizes() and
  so uploads and all_wanted() read. A switched off size is no longer made,
  and Site Kit no longer counts it as registered. size_names() also drops it
  from the editor's choices (only large is offered there by WordPress).
- other_sizes() adds switched off sizes back from $_wp_additional_image_sizes
  or the _size_w options, because WordPress no longer lists them, so they
  stay on the page, unticked and dimmed (li.is-off), ready to switch on.
- stale() treats wp_off names like owned and adopted ones, and foreign()
  leaves them out, so their files go straight to Old thumbnails to clear on
  the next scan, without adopting. Turning one back on makes it registered
  again, so it is never cleared and Build makes it where it is missing.
- srcset reads the sizes in each image's metadata, not the registered list,
  so a switched off size keeps appearing in srcset until it is cleared.
- Tested on the hub by submitting the page's own form as an admin with two
  sizes unticked: wp_off saved as exactly those two, every other setting the
  same. Then in a fresh request: neither in WordPress's list or what an
  upload makes, stale for an image carrying 1536x1536 and not foreign, the
  scan listing 5 hub images under Old thumbnails to clear (not cleared), both
  still on the page unticked; with large off it also left the editor's
  choices. The hub's settings were put back exactly afterwards.

## Space on the Old thumbnails to clear figure (October 2026)

The card's note ends with the space its files take, for example "2 removed
sizes across 4,053 images, 3.4 GB". SBSK_Images_Rebuild::stale_usage()
returns files and bytes (stale_files() now wraps it), and summary() adds up
stale_bytes in the pass it already makes. The scan already checks each file
exists, so the size is one more look at the same file. Like the file count,
it includes a file shared with a size that is kept, which the clear leaves in
place, so it is the most the clear can free. Orphaned images already showed
its total. Checked on Villa: 3,620,226,845 bytes for 7,596 files, the same as
an independent count from the disk; the whole scan pass took 2.1 seconds.

## Add an orphan back to the library (October 2026)

On Villa, deleting the 7 missing images turned up orphaned originals and their
thumbnails, most likely because those records named slightly different files
from the ones on disk (not confirmed: they were deleted before anyone looked).

- The orphan list is sorted originals first (is_original(): no -WxH on the
  end of the name, and not an optimiser's copy such as photo.jpg.webp), so an
  original is never hidden under the 25 row cut off.
- An original's row has Add to library (sbsk_images_orphan_add, one at a
  time, under the job lock). The server only takes a file the orphan scan
  lists at that moment (find() is run again), that is_original() and
  looks_like_image() accept, and that WordPress takes as an image type. It
  makes the attachment where the file is, without moving or renaming it,
  titled through SBSK_Images::clean_title(), then wp_generate_attachment_
  metadata() makes its sizes as an upload would, so thumbnails of it on disk
  belong to it again. The page rescans and the row, and its thumbnails, drop
  off the list. A big original goes through WordPress's -scaled step.
- Tested on the hub with a planted original, one of its thumbnails and a
  .webp copy: only the original had the button and came first, the other two
  were refused, the original became a library image with its sizes, and the
  rescan dropped all three. Everything planted was removed.

## Thumbnail size and fit in the media pop-up (October 2026)

The Media Library thumbnail size and fit choices now also apply in the media
pop-up, wherever it opens (post screens, page builders, gallery fields such
as JetEngine's), with the same two dropdowns in its toolbar. The choice is
the same per user meta, so the pop-up follows the Media Library and the
other way round.

- SBSK_Images_Media_Grid::enqueue_modal() on wp_enqueue_media loads the
  script with media-views as its dependency, skipping upload.php, which keeps
  loading through enqueue() with media-grid. assets() holds the shared part
  and loads only once.
- media-grid.js: wanted() is the Media Library grid or any frame with a modal
  (the Media Library's own frame has none). The bigger file is asked for on
  grid tiles but not on the small selection bar thumbnails
  (media.view.Attachment.Selection). Picker ids carry the view's cid, since a
  page can build several pop-ups.
- media-grid.css: every rule also covers .media-modal .attachments. In the
  pop-up WordPress lays its filters on a two column grid in
  .media-toolbar-secondary (labels above, selects below), so the picker takes
  grid-area 2 / 3 / 3 / 4, level with their selects. That placement was set
  from WordPress's CSS, not seen in a browser before release.
- Checked through real page loads on the hub: the script, styles and config
  load on a post edit screen and on Media Library grid, not in list view. The
  dashboard loads them too, because something there calls wp_enqueue_media().
- The filename caption over pictures in some pop-ups comes from neither
  WordPress's templates nor the image records (both checked on Villa) and
  shows on the hub as well; HappyFiles is on both. Left alone at Sam's call.

## HappyFiles folder dropdown in the pop-up from a post list (October 2026)

On a post list screen (edit.php, such as Admin Columns Pro inline edit of a
featured image on Portfolios) HappyFiles leaves its folder panel out of the
media pop-up and stops filtering it. The pop-up there now has a Filter by
folder dropdown: All folders, Uncategorised, then every HappyFiles folder
nested by parent. Anywhere HappyFiles shows its own panel there is no
dropdown, so the two never double up.

- SBSK_Images_Media_Grid::folders() sends the folder list (id, name, depth)
  only when $pagenow is edit.php and happyfiles() is true (HAPPYFILES_VERSION
  defined and the happyfiles_category taxonomy registered, checked when used
  since HappyFiles may load after Site Kit); anywhere else it is false, an
  empty string in SBSKMediaGrid.folders. folder_query() does nothing without
  HappyFiles either.
- The toggle (setting key media_grid, unchanged) is now called Media Browser
  Enhancements, with a description covering the grid, the pop-up and the
  folder dropdown. Same label in module.php's dashboard list.
- media-grid.js: FolderFilter sets the sbsk_folder prop on the library's
  props, added in a modal frame only when there are folders (priority -70,
  before the size picker).
- folder_query() on ajax_query_attachments_args (priority 20) reads
  $_REQUEST['query']['sbsk_folder'], because WordPress drops query keys it
  does not know. -1 is images in no folder (NOT EXISTS); a folder id shows
  that folder's own images, not its children, the way HappyFiles does. 0, an
  unknown id or anything below -1 is ignored.
- media-grid.css: the dropdown takes grid column 3 (label above, select
  below) and the size picker moves to column 4 when it is there. Set from
  WordPress's CSS, not seen in a browser before release.
- Checked through real page loads and query-attachments calls on the hub: the
  list is sent on edit.php for posts and pages, not on a post edit screen or
  the Media Library; each folder returned exactly the images the database
  holds for it. On Villa the Portfolios list sends 7 folders, and
  Uncategorised returns 4,755 of 4,928 images.

## New standard image sizes (October 2026)

The standard widths (SBSK_Images::DEFAULT_WIDTHS) are now 450, 665, 1330 and
1920, from 240, 480, 640, 720, 960, 1200 and 1920. They suit a 1330 wide site
and the srcset WordPress builds: thirds and quarters 450, halves 665, full
width 1330, full width on high density screens 1920 (WordPress leaves anything
over 2048 out of srcset, so 1920 is the top that gets used). With WordPress's
medium 300 and large 1024 the steps are all about 1.3 to 1.5 times. On the same
reasoning SBSK_Images_Rebuild::DEFAULT_OFF switches off medium_large (too
close to 665), 1536x1536 and 2048x2048 (1920 covers both).

- wp_off() uses DEFAULT_OFF when the images setting has no wp_off at all. A
  saved empty list still means none off.
- Reset to default sizes now puts back both the widths and DEFAULT_OFF.
- sbsk_keep_old_image_defaults() in the main file runs once, straight after
  sbsk_split_image_cleaner(), and records sb_tweaks_site_kit_image_defaults.
  On a site already running Site Kit (an images setting saved, or the owned
  sizes option present) it saves the old seven widths if none are saved (an
  empty list counted as none) and an empty wp_off if that key is missing, so
  nothing changes under the site. A new install has neither, since this runs
  on plugins_loaded before the sizes are first registered, and gets the new
  set. Moving an existing site over is done by hand on its Images page.
- The size names image-240 to image-1200 stay on sites that already have
  them; nothing renames or removes them.
- Checked on the hub by running it against saved copies of the options (new
  install, running and never saved, saved before wp_off existed, the hub as
  it is) and restoring them, and through a real Reset to default sizes post,
  also restored. The hub and Villa both had their own saved lists and kept
  them.
- The width list marks each of the standard widths with "default size" under
  its name, from the server (width_row) and live as a width is typed
  (admin.js, reading data-defaults on #sbsk-widths). Add default sizes, beside
  Add a width, adds only the standard widths missing from the list, each in
  order, and nothing is saved until Save changes. It is switched off when none
  are missing. Reset's confirm now says it also switches off the three
  WordPress sizes. Each name box has a left edge: green (#85df07, the is-good
  colour) on a standard width, the row carrying is-default, and orange
  (#dba617) on any other, blank rows included. Add a width now clears the
  default mark, class and notes from the row it copies. Checked against the
  real markup in jsdom: adding a row, adding the defaults, retyping a
  default, removing one and adding again.

## Two thirds size, Reset with WordPress sizes, confirm popups (October 2026)

- DEFAULT_WIDTHS gained 890, for two thirds of a 1330 site: 450, 665, 890,
  1330, 1920. WordPress's large is a 1024 box, so a portrait image comes out
  well under 1024 wide; image-890 is width only. Sites with a saved list are
  untouched; Add default sizes offers 890 there. A site that never saved its
  Images page reads the defaults from the code and picks it up on update.
- Reset to default sizes opens a popup. When thumbnail, medium,
  medium_large or large differ from WordPress's own defaults
  (SBSK_Images::WP_DEFAULTS: 150 x 150 cropped, 300 x 300, 768, 1024 x 1024),
  wp_size_drift() lists them in data-wp-drift on the button and the popup
  shows a tick box over a list of them, one to a line, the size name in
  bold. Ticked, the form carries sbsk_reset_wp
  and save() writes WP_DEFAULTS back. Left unticked, WordPress's sizes stay
  as they are.
- The Save sizes button under Additional thumbnail sizes is gone: it was a
  second submit for the same form and lit up for any change on the page. The
  Settings link stays; Save changes saves everything.
- Every confirm in Site Kit is now the SocialBUMP popup, the same box as SEO
  for AI and the SocialBUMP Tweaks hub's confirm popup (dark overlay, 10px
  radius, 24px padding, title top left, X top right, Cancel left and the
  action right; Escape, the X and a click outside close it). It lives in
  admin.js: ask( element, options ) opens it and returns; the action clicks
  the element again with a one shot pass that confirmed( element ) spends, so
  each handler carries on unchanged after its check. tell() is the one button
  version, for the failed Add to library message. window.SBSKConfirm exposes
  them for scripts printed by module pages (FAQ's Delete content, with the
  browser's confirm as a fallback). Buttons in PHP pages ask with
  data-sbsk-confirm (the question, %s for the value of the field named in
  data-sbsk-confirm-field), data-sbsk-confirm-title and
  data-sbsk-confirm-action: Remove token and Publish release on Publishing,
  Import settings on Updates. Styles: .sbsk-modal and .sbsk-dialog in
  admin.css, copied from .sb-hub__modal and .sb-hub__dialog, without
  !important.
- Checked on the hub: the Images page sends the new defaults and an empty
  drift list, the Save sizes button is gone and Settings stays; with medium
  and large changed the drift list names both; a real Reset post with the
  tick put WordPress's sizes back and one without left them; the Publishing,
  Updates and FAQ pages carry the new attributes or code and load admin.js.
  The popup itself was run against sample markup in jsdom (Cancel, the
  action, Escape, the tick, the %s field and the one button version). Not
  yet seen in a browser. Every option touched was put back.

## Progress and summary popups in the SocialBUMP Tweaks style (October 2026)

The look comes from SocialBUMP Tweaks' popups (its docs, Popups): a white
box over a dark overlay, 10px radius, 24px padding, the elapsed time small
and grey top right, a rounded bar in the accent colour, a bold status line,
Finished over a grey report card (the summary in bold and the time taken).

- Image Cleaner keeps its progress in the box on the page where it always
  was (#sbsk-progress: bar, status, timer, Cancel, the live per image log,
  and Stop that run, which only shows while this window is watching a run
  going in another window and stops that run). Sam tried it as a popup and
  preferred it on the page. Only the end pops up: finished() writes the last
  line in the box as before ("Finished. 4 sizes built, 0 files removed. Took
  6s, 1s per image.") and opens the confirm popup (openModal) with the run's
  title, Finished or Stopped, the report card, and two buttons: Close, and
  Scan again, which hides the box and runs a fresh scan. The X, Escape and a
  click outside close it.
- openJob( title ) shows the box for a run or for watching another window's,
  and keeps the title for the summary: Building thumbnails, Rebuilding all
  thumbnails, Removing old sizes, Deleting orphan images, Deleting missing
  images, Deleting leftover thumbnails, or the other window's job name.
  failJob( text ) turns the bar red (.is-failed) and leaves the reason in the
  box, with no popup. Watching another window ends in the box with Finished
  in the other window, also with no popup.
- Find leftover thumbnails' delete used its own bar in its panel. It now
  runs in the same progress box, honours Cancel, ends with the same summary,
  and the files left alone plus the rescanned list go back into its panel.
- openModal() gained body (extra content, here the summary) and cancel (the
  left button's words, here Close).
- assets/js/progress.js is a copy of SocialBUMP Tweaks' progress.js as
  window.SBSKProgress (classes .sbsk-job, __box, __timer, __title, __track,
  __bar, __count, __current, __report, __summary, __close, the framework's
  sb-tweaks-job names with Site Kit's prefix), plus busy() and __spin: a
  spinner in place of the bar for one step with no telling how long it will
  take. Sam's call: a bar suggests a count that a single post does not have.
  Only Publish release uses it: data-sbsk-progress and
  data-sbsk-progress-status on the button open it once the confirm is
  accepted (and only when the form is valid), and the form then posts in the
  background (see Publish posts in the background). Enqueued with admin.js on
  Site Kit's pages.
- For the merge into SocialBUMP Tweaks: drop progress.js and the .sbsk-job
  styles and point data-sbsk-progress at SBTweaksProgress, which has had
  busy() since October 2026.
  Image Cleaner's box and its summary need nothing from the framework beyond
  a confirm popup that takes extra content and its own Cancel words.
- Checked: admin.js passes node --check (the md5 matched the hub copy); in
  jsdom against the page's markup, with the server's answers stubbed: a
  build (the box, Cancel while running, the log, the last line, the summary
  with Close and Scan again), Scan again (box hidden, a new scan sent),
  closing with Escape and with a click outside, and a failed batch (red bar,
  reason in the box, no popup). progress.js passes node --check and its
  busy(), step() and finish() were run in jsdom. On the hub the Image Cleaner
  page renders the box and loads both scripts, and Publish release carries
  the attributes. Not yet seen in a browser.

## Image Cleaner's size groups say where each size comes from (October 2026)

The Sizes panel lists every registered size in four groups, in this order,
each in width order with its own all | none links, and a group with nothing in
it is not shown:

- Image Sizes: Site Kit's own, the image-<width> names for the widths the
  Images page registers right now (Images switched on, its Image sizes switch
  on, SBSK_Images::widths()). Edit Sizes sits beside this heading, not on the
  panel heading, since these are the only ones it edits, and only shows while
  Images is on.
- Other Image Sizes: everything else, with a grey line under the heading
  ("Added by the theme, a snippet or another plugin."). WordPress does not
  record who registered a size, so nothing finer can be said honestly.
- WooCommerce: names starting woocommerce_.
- WordPress: thumbnail, medium, medium_large, large, 1536x1536, 2048x2048.

It used to put any name starting image- under Image Sizes, so a site with a
WP Codebox snippet registering image-2000, or the old image-240 to
image-1200, showed them all as Site Kit's. Checked on the hub by registering
image-240, image-2000 and two WooCommerce sizes in one request and reading
size_groups(): they landed in Other and WooCommerce, the hub's own five
widths in Image Sizes. The live page shows Edit Sizes beside Image Sizes.

## Sizes panel in groups, left alone list in the summary (October 2026)

Two follow ups to the size groups and the summary popup.

- SBSK_Images_Tools::sizes_list() (the panel on each image: the media pop-up,
  fetched by AJAX, and the box on the attachment edit screen) builds its rows
  keyed by size name, then lays them out in SBSK_Images_Cleaner::size_groups()
  order: Image Sizes, Other Image Sizes, WooCommerce, WordPress, each in width
  order, an empty group left out. Each group is a p.sbsk-sizes__group label
  over a ul.sbsk-sizes__list, no all | none links. Anything no group takes
  (there should be nothing) goes in a final unlabelled list.
- The whole thing is one div.sbsk-sizes. That matters: the pane's script
  swaps .sbsk-sizes (the loading line, and the old list after Regenerate) for
  the answer with replaceWith, so more than one .sbsk-sizes would duplicate
  the list. The inner lists use sbsk-sizes__list for the same reason.
- The panel's heading is still "SocialBUMP Site Kit Sizes". Offered to Sam as
  plain "Image sizes"; not changed.
- Find leftover thumbnails' delete: the files left alone (with why_html, the
  in use links) now go into the summary popup under the bold line, through
  finished( text, extra ). The list is div.sbsk-summary__skipped, capped at
  100 rows plus "and N more", and scrolls past 220px so Close and Scan again
  stay in view. The yellow .sbsk-deep__skipped box is no longer put in the
  panel; the panel gets just the rescanned list, or is hidden when there is
  none. deep_skipped() in the tools class builds the old box server side and
  is not called anywhere.
- Release notes queued for both.
- Checked: admin.js passes node --check and its md5 matched on the hub. In
  jsdom with stubbed answers: a delete with files left alone (list and link in
  the popup, no yellow box behind, rescanned list in the panel), one with
  none, and one with an empty rescan (panel hidden). On the hub: sizes_list()
  for a real image gives one wrapper with Image Sizes and WordPress, a size
  added for that request only showed under Other Image Sizes, and the
  attachment edit page renders the grouped box with no PHP errors. Not yet
  seen in a browser.

## Progress counts that match the bar, and a faster publish (October 2026)

Sam saw a bar finish well before its count on a big delete.

- Find leftover thumbnails' delete: ajax_deep_remove() works out the matching
  files afresh each request, so its total is what is left, and it shrinks as
  files go. The page measured its running count (removed plus left alone)
  against that shrinking number, so the bar was full about halfway. The page
  now keeps the first answer's total (runTotal) for the whole run, and the
  line reads "9 of 12 checked, 6 removed." It also sends seen and run_total
  with each request; the server uses them only for job_beat, so a window
  watching shows the same numbers. In jsdom with a shrinking total the old
  code went 25%, 60%, 100% at 6 of 12 and the new one 25%, 50%, 75%.
- Orphaned images delete: the bar is files checked from a list fixed for the
  run, but the line counted only files removed, so kept or still used files
  left it short. The line is now "400 of 900 checked, 250 removed."
- The build and Remove old sizes already use one held list for both the bar
  and the line; nothing found wrong there.
- Publish (hub only, no release note): push_code() used to send every file
  each time, the text ones in one big tree request and the 37 others (the
  update checker's .mo translations and the two icons) as a blob request each.
  Each GitHub call takes about a third of a second from the hub, and the
  publish took 32 seconds. Now it first lists the repo's tree with
  ?recursive=1 and, for each local file, works out git's blob hash
  (sha1 of "blob <length>\0<contents>"). A match is put in the new tree by
  that sha and nothing is sent; only changed files go inline or as blobs. The
  keep list (README.md, LICENSE, .gitignore, .github) still comes from the
  top level entries of the same listing. If the listing fails or comes back
  truncated, it falls back to the old way and sends everything. A dry run
  against the repo after 1.2.5: listing 0.36 seconds, 194 of 197 files
  matched, the 3 changed files would be sent, no blob uploads. The real
  publish of 1.2.6 took 11.2 seconds (32 before), and afterwards all 197 files
  on GitHub matched the hub, README.md and LICENSE kept. The same change is now
  in SocialBUMP Tweaks, Bricks Tweaks and SEO for AI's release classes.
