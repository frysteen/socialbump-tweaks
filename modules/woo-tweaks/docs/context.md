# Woo Tweaks module

Site Kit's WooCommerce group, as a module of its own (Sam, October 2026). Read
the framework notes first, at the plugin root in docs/context.md. This file
covers only what this module does. The features' history is in Site Kit's
docs/standalone-notes.md and docs/changes.md, under their old names.

## What it does

Its own SB Woo Tweaks menu, below SB Site Kit, with two pages:

| Page | Features |
| --- | --- |
| Shop and Checkout (sb-woo-tweaks-shop) | From Price on Variable Products, Hide Empty Decimals on Prices, Show Both Prices in the Cart, Turn Off Product Image Zoom, Require Login to Check Out |
| Store Admin (sb-woo-tweaks-admin) | Fix Default Category Title Bug, Download Analytics as CSV |

The module needs WooCommerce (requires woocommerce). Without it, its card on
the Modules page says so, its switch is disabled and nothing loads, so there
is no menu. The hub has no WooCommerce, so it shows the module that way.

Kept elsewhere on purpose, because they serve another module: Bricks' WooCommerce
Archive Display condition, Site Kit's Block Admin Access (its My Account
choice), and Image Management's handling of WooCommerce's image sizes.

## How it is built

| Path | What it is |
| --- | --- |
| module.php | the definition: id woo, groups shop and admin, menu sb-woo-tweaks after sb-site-kit, bar row woo-tweaks |
| includes/class-sb-woo-tweaks.php | SB_Woo_Tweaks: options, setting(), is_enabled(), page_slug(), migrate(), old_address() |
| features/woo-*/ | one folder per feature, feature.php plus class-sb-woo-*.php |
| features/woo-analytics-csv/assets/ | analytics-csv.js and .css, loaded on WooCommerce's Analytics (page=wc-admin) only |

## Settings, and the copy from Site Kit

Options: sb_tweaks_woo_features, sb_tweaks_woo_settings, sb_tweaks_woo_groups,
and sb_tweaks_woo_migrated once the copy has run.

SB_Woo_Tweaks::migrate() runs once per site, before the framework asks whether
the module is on, and never overwrites what this module already holds. It
copies the seven woo-* switches and their settings (only From Price has any)
from sb_tweaks_site_kit_features and _settings, and sets both groups from
Site Kit's woocommerce group. It creates the features option, which switches
the module on through 'auto', only where Site Kit's WooCommerce group was on
and WooCommerce is active, or a woo-* switch had been saved on. Site Kit's own
options are left as they were. On the hub it waits while standalone Site Kit
is active.

Driving Events was the only site running SocialBUMP Tweaks with WooCommerce
when this was built: all seven switches on, From Price set to loops only with
the label "From:".

Site Kit's old WooCommerce page (admin.php?page=sb-site-kit-woocommerce)
redirects to Shop and Checkout, through admin_page_access_denied.

## Names that moved

The feature ids stayed woo-* (they are the keys in the saved options). The code
names moved from Site Kit's:

| Was | Now |
| --- | --- |
| SB_Site_Kit_Woo_* classes, class-sb-site-kit-woo-*.php | SB_Woo_*, class-sb-woo-*.php |
| SB_Site_Kit::setting() in From Price | SB_Woo_Tweaks::setting() |
| sb_site_kit_analytics_csv and _all (admin actions and nonce) | sb_woo_analytics_csv and _all |
| sbSiteKitAnalyticsCSV (script global) | sbWooAnalyticsCSV |
| sb-site-kit-analytics-csv (style and script handles) | sb-woo-analytics-csv |
| sb-site-kit-csv-* (CSS classes) | sb-woo-csv-* |
| sb_site_kit_csv (WP_Error code) | sb_woo_csv |

## Removing the move-over code later

Once every site with WooCommerce has updated past the release that added this
module, migrate() and old_address() can go. Driving Events is the only one
known.
