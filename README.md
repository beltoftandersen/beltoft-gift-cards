# Beltoft Gift Cards for WooCommerce

Sell digital gift cards, deliver them by email, and let customers redeem them at checkout.

- Stable version: 1.6.3
- Requires: WordPress 5.8+, PHP 7.4+, WooCommerce 6.0+ (tested up to WordPress 7.1)
- Author: beltoft.net
- Text domain: beltoft-gift-cards

## Overview

This plugin adds a gift card product type to your WooCommerce store. Customers purchase a gift card, choose an amount, and enter the recipient's email. When the order is processed, the recipient gets a branded email with their unique gift card code. Codes are redeemed at checkout through the standard WooCommerce coupon field — no extra steps for the customer.

## Features

- Gift card product type with predefined amounts (e.g., $25, $50, $100) or custom amounts
- Email delivery using WooCommerce email templates — same look as your order emails
- Coupon field redemption — codes work in the standard WooCommerce coupon field, no setup required
- Optional dedicated "Apply Gift Card" field on cart/checkout via settings or shortcode
- Block regular coupons from discounting gift card products (on by default) so a discounted card can't be redeemed at full value
- Auto-apply from email — the "Shop Now" button in the delivery email automatically applies the gift card to the recipient's cart
- Virtual coupon integration — gift card discounts display natively between subtotal and total with WooCommerce [Remove] link
- Balance tracking with partial redemption — remaining balance carries over
- Personal message displayed in cart and order details
- Price range display in shop catalog (e.g., "$25 – $100")
- My Account tab for customers to view gift cards, balances, and transaction history
- Admin dashboard with stats: total issued, outstanding balance, redeemed, expired
- Gift card management list with search, status filters, pagination, and bulk actions
- Manual gift card creation from the admin panel — no order required
- Order meta box showing gift cards created by and used on each order
- Automatic balance restore on cancel/refund, including proportional partial refunds
- Loyalty Rewards integration — optionally block or allow customers from using loyalty points to purchase gift cards
- Shortcode `[bgcw_product_form]` for page builders (Bricks, Elementor, etc.)
- Email settings (subject, heading, on/off) under WooCommerce > Settings > Emails
- Atomic balance deduction to prevent race conditions on concurrent redemptions
- Rate limiting on gift card code lookups
- HPOS compatible — works with WooCommerce High-Performance Order Storage
- Clean uninstall with opt-in data removal
- Portuguese (pt_PT) translation included
- PSR-4 codebase, no Composer dependency
- Gift card source tracking (shop order, paid offline, promotion, compensation) — the redeeming order records whether the card was paid or free, for accounting
- REST API (`wc-bgcw/v1`) to list, create, update, adjust, and delete gift cards from external systems; works with WooCommerce REST API keys

## How It Works

1. Create a "Gift Card" product in WooCommerce and set the predefined amounts.
2. Customer purchases the gift card, picks an amount, and enters recipient details and an optional message.
3. When the order is processed, a unique code is generated and emailed to the recipient.
4. Recipient enters the code at checkout in the coupon field — the gift card balance is applied as a discount.
5. Partial use is tracked. The remaining balance stays on the gift card for future orders.

## Installation

1. Upload the plugin to `wp-content/plugins/` or install from a ZIP.
2. Activate the plugin.
3. Go to **WooCommerce > Gift Cards > Settings** to configure.
4. Create a new product and select **"Gift card"** as the product type.
5. Optionally adjust the delivery email under **WooCommerce > Settings > Emails > Gift Card Delivery**.

## Configuration

### Gift Card Product

Create a product, select "Gift card" as the product type. Set predefined amounts in the Gift Card data panel (e.g., 25,50,75,100). Custom amounts and their min/max are controlled from the global settings page.

### Redemption

Gift card codes always work in the standard WooCommerce coupon field — this is automatic. To also show a dedicated "Apply Gift Card" field, enable it in settings. You can choose automatic placement or shortcode-only:

```
[bgcw_apply_field]
```

### Email Template

Uses WooCommerce's email system — same header, footer, and colours as your other store emails. Customise the subject and heading under WooCommerce > Settings > Emails > Gift Card Delivery.

Override the template by copying `templates/emails/gift-card-delivery.php` to your theme's `woocommerce/emails/` folder.

### Page Builders

For Bricks, Elementor, or other page builders that replace WooCommerce templates, use the WooCommerce Add to Cart element or the shortcode:

```
[bgcw_product_form]
```

## REST API

Base: `https://your-store.example/wp-json/wc-bgcw/v1/`. Authenticate with WooCommerce REST API keys (Basic auth over HTTPS) or a WordPress application password. Requires the `manage_woocommerce` capability.

| Method | Route | Purpose |
|---|---|---|
| GET | `/gift-cards` | List. Params: `page`, `per_page` (≤100), `search`, `status`, `source`, `orderby`, `order`. |
| POST | `/gift-cards` | Create. Body: `amount`*, `source`* (`paid_offline`, `promotion`, `compensation`), `recipient_name`, `recipient_email`, `sender_name`, `sender_email`, `message`, `expires_at` (ISO 8601 or `null`), `send_email` (default true). |
| GET | `/gift-cards/{id}` | Single card. |
| GET | `/gift-cards/code/{code}` | Single card by code. |
| PATCH | `/gift-cards/{id}` | Update `status` (`active`/`disabled`), `source`, recipient/sender fields, `message`, `expires_at`. |
| POST | `/gift-cards/{id}/adjust` | Change balance. Body: `amount` (positive credit, negative debit), `note`. |
| GET | `/gift-cards/{id}/transactions` | Ledger. |
| DELETE | `/gift-cards/{id}?force=true` | Permanently delete card and ledger. |

All datetimes in responses (`created_at`, `expires_at`, transaction `created_at`) are ISO 8601 in UTC with a trailing `Z` (e.g. `2032-01-31T00:00:00Z`). On input, `expires_at` accepts an ISO 8601 datetime (interpreted as UTC when no offset is given), a MySQL datetime string, or `null` to clear it. `recipient_email` and `sender_email` accept a valid email address or an empty string to clear the field.

Every card includes `source` and `is_paid`. On redeemed orders, each gift card coupon line carries `bgcw_gift_card_id`, `bgcw_source`, `bgcw_is_paid`, `bgcw_source_order_id`, and the order carries `_bgcw_paid_redeemed_total` / `_bgcw_free_redeemed_total`. Those two order totals reflect amounts actually deducted and are not reduced by later refunds; refunds appear as separate `refund` transactions in the ledger.

Validation failures return HTTP 400; failures while creating, updating, deleting a card, or recording a ledger entry return HTTP 500 (`bgcw_rest_create_failed`, `bgcw_rest_update_failed`, `bgcw_rest_delete_failed`, `bgcw_rest_ledger_failed`). The `/adjust` `amount` is bounded to ±1,000,000.

```bash
curl -u ck_xxx:cs_xxx "https://your-store.example/wp-json/wc-bgcw/v1/gift-cards?source=promotion"
```

## Hooks & Filters

Developers can extend the plugin:

- `bgcw_gift_card_created` — fires after a gift card is created (used by the email system)
- `bgcw_gift_card_deleted` — fires after a gift card is deleted from the admin list
- `bgcw_my_account_card_actions` — fires inside each card row on the My Account → Gift Cards page, for adding buttons
- `bgcw_validate_amount_limits` — return false to skip the predefined/min/max amount checks for a programmatic add-to-cart
- `bgcw_show_recipient_name_field` — return false to hide the Recipient Name field on the product page
- `bgcw_show_recipient_email_field` — return false to hide the Recipient Email field on the product page. The buyer's billing email is used as the recipient and the email validation is skipped
- `bgcw_show_personal_message_field` — return false to hide the Personal Message field on the product page
- `bgcw_coupon_valid_for_gift_card` — return true to let a specific coupon discount gift card products when coupon blocking is enabled
- `bgcw_rest_permission` — filter REST access (default: `manage_woocommerce`)

Example — hide the Recipient Email field on every gift card product:

```php
add_filter( 'bgcw_show_recipient_email_field', '__return_false' );
```

## Translations

- Text domain: `beltoft-gift-cards`
- Translation template: `languages/beltoft-gift-cards.pot`

## Changelog

### 1.6.3

- Improved: The My Account gift cards list now stacks each card with labelled rows on small screens instead of a cramped table.

### 1.6.2

- Improved: Complete European Portuguese (pt_PT) translation.

### 1.6.1

- Fixed: A product-locked code from the email link now waits until the product is in the cart (variable products) and is applied automatically, instead of reporting success on a failed apply.
- Fixed: The dedicated gift card field reports when a code could not be applied, and explains when a card is locked to a product not yet in the cart.
- Fixed: Bulk "Remove product restriction" counts only cards that were locked; admin list loads product names in one query.

### 1.6.0

- Added: Gift cards can be locked to a specific product. A locked card only discounts that product, the email link adds the product to the cart with the code applied, and admins can remove the restriction from the gift card list.
- Added: `product_id` on the REST API (read, create, update) and `bgcw_validate_amount_limits` filter.
- Added: "For: product" shown in cart, My Account, admin list and emails for locked cards.

### 1.5.1

- Added: `bgcw_gift_card_deleted` and `bgcw_my_account_card_actions` hooks for extensions.

### 1.5.0

- Added: Gift card `source` (shop order, paid offline, promotion, compensation). Manual creation now asks for a source.
- Added: Redeeming orders record each gift card's source and paid/free status on the coupon line, plus paid/free redeemed totals on the order.
- Added: REST API `wc-bgcw/v1` for listing, creating, updating, adjusting, and deleting gift cards. Works with WooCommerce REST API keys.
- Added: `bgcw_rest_permission` filter.
- Changed: Existing gift cards are classified by source on upgrade: cards with an order ID become "order" (paid), all others "promotion" (free). If you use the Pro add-on's store credit or BOGO features, review those cards' source via the REST API and adjust with PATCH.
- Fixed: Gift card balances were not deducted, and no gift card data was recorded, on orders placed through the block (Store API) checkout.

### 1.4.8

- Fixed: Search in the admin Gift Cards list did nothing.

### 1.4.7

- Added: "Block Coupons on Gift Card Products" setting (enabled by default). WooCommerce coupons no longer discount gift card line items, which closes a loophole where a discounted gift card was redeemed at full face value. Other items in the cart are still discounted.
- Added: `bgcw_coupon_valid_for_gift_card` filter to allow specific coupons on gift card products.
- Tested with WordPress 7.1 and WooCommerce 10.7.

### 1.4.6

- Tested with WordPress 7.0.

### 1.4.5

- Fixed: Initial "Show" button label on the My Account → Gift Cards page now reads "Show code", matching the toggled "Hide code" / "Show code" labels for consistency.

### 1.4.4

- Added: Show/Hide toggle for gift card codes on the My Account → Gift Cards page (codes are masked by default).
- Added: Filters `bgcw_show_recipient_name_field`, `bgcw_show_recipient_email_field`, and `bgcw_show_personal_message_field` to hide individual recipient fields on the product page.
- Fixed: Transaction notes now translate at display time instead of being stored in the locale that was active when the row was written.

### 1.4.3

- Fixed General settings tab missing on non-gift-card products.

### 1.4.2

- Added GitHub Actions workflow for automated WordPress.org deployment.

### 1.4.1

- Added `width: 100%` to gift card product fields container for better theme compatibility.
- Added placeholder text to Predefined Amounts field on the product edit page.

### 1.4.0

- Renamed plugin slug and folder to `beltoft-gift-cards`.
- Renamed text domain to `beltoft-gift-cards`.
- Replaced inline scripts with `wp_add_inline_script()`.
- Fixed double-escaping on gift card price display.
- Improved input sanitization on all add-to-cart POST data.
- Moved all inline styles to external CSS files.
- Added `wp_cache_delete()` calls after custom table writes.
- Updated author to beltoft.net.

### 1.3.0

- Improved: MySQL advisory lock for concurrent balance deductions.
- Improved: SQL-level pagination for My Account gift cards.
- Improved: Bulk gift card code lookups in cart and Store API.
- Improved: Expiry sync moved to WP-Cron (hourly) with composite DB index.
- Fixed: Tax-inclusive discount amount in balance deductions.
- Fixed: Refund safety guard requires prior deduction before restoring balance.
- Added: Block checkout support — gift card codes identified via Store API extension.

### 1.0.0

- Initial release.
- Gift card product type with predefined and custom amounts.
- Email delivery to recipients using WooCommerce email templates.
- Auto-apply gift card from email "Shop Now" link.
- Virtual coupon integration — gift card discounts display natively between subtotal and total with WooCommerce [Remove] link.
- Optional dedicated "Apply Gift Card" field with automatic or shortcode-only placement.
- Personal message displayed in cart and order details.
- Price range display in shop catalog (e.g., "$25 – $100").
- Balance tracking with partial redemption.
- My Account tab for viewing gift cards and transactions.
- Admin dashboard, gift card list with bulk actions, and manual creation.
- Order meta box showing created and used gift cards.
- Automatic balance restore on cancel/refund with partial refund support.
- Loyalty Rewards for WooCommerce integration — block or allow loyalty points for gift card purchases.
- Atomic balance deduction to prevent race conditions.
- Rate limiting on gift card code lookups.
- HPOS compatibility.
- Block checkout incompatibility declared (classic checkout required).
- Portuguese (pt_PT) translation included.

## About

Beltoft Gift Cards for WooCommerce is built and maintained by [beltoft.net](https://beltoft.net).

## License

GPLv2 or later. See https://www.gnu.org/licenses/gpl-2.0.html.
