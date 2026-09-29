=== Beltoft Gift Cards for WooCommerce ===
Contributors: christian198521, beltoftnet
Tags: woocommerce, gift cards, gift certificate, store credit, voucher
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.6.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sell digital gift cards, deliver them by email, and let customers redeem them at checkout.

== Description ==

Beltoft Gift Cards for WooCommerce lets you sell gift cards in your store. A customer picks an amount, writes who the card is for and adds a personal message. When the order is paid, the recipient gets an email with a unique gift card code. They type the code in the normal coupon field at checkout, and the amount is taken off their order. Anything left on the card can be used next time.

= Features =

**Selling gift cards**

* A "Gift card" product type with fixed amounts (for example $25, $50, $100), a custom amount within limits you set, or both.
* The customer adds the recipient's name, email and a personal message.
* Shop pages show the price range, such as "From $25".
* Works with page builders like Bricks and Elementor through the `[bgcw_product_form]` shortcode.

**Delivery**

* The gift card is emailed to the recipient in your store's normal email design.
* A "Shop Now" button in the email opens your store with the gift card already applied.
* You decide whether cards are sent when an order is "Processing", "Completed", or both.

**Using a gift card**

* Codes work in the standard WooCommerce coupon field. No setup needed.
* Optionally show a separate "Apply Gift Card" field on the cart and checkout (or place it with the `[bgcw_apply_field]` shortcode).
* Works with both the classic checkout and the newer block checkout.
* Part of a card can be used now and the rest later.
* A card can be locked to one product, so it can only be used for that product.
* Regular coupons can't be used to buy gift cards at a discount (you can turn this off).

**For your customers**

* A "Gift Cards" page in My Account with each card, its balance and its history.

**Managing gift cards**

* A dashboard showing how much has been issued, how much is still unused, and how much has been redeemed or expired.
* A searchable list of all gift cards where you can disable, delete or unlock cards.
* Create gift cards by hand, for example for a customer paying in the store, a promotion or a compensation.
* Every card records where it came from, so your accounting can tell paid cards from free ones.
* Cards expire after a number of days you choose (365 by default), or never.
* See on each order which gift cards it created and which it used.
* If an order is cancelled or refunded, the money goes back onto the gift card, including for partial refunds.

**And also**

* A REST API so other systems (accounting, POS, CRM) can read and manage gift cards.
* Optional integration with Loyalty Rewards for WooCommerce.
* Compatible with WooCommerce High-Performance Order Storage (HPOS).
* Translation-ready, with Portuguese (pt_PT) included.

Need more? The [Pro add-on](https://beltoft.net/beltoft-gift-cards-pro) adds PDF gift cards, scheduled delivery, gifting any product, bulk generation, CSV import/export, BOGO promotions and reports.

= How It Works =

1. Create a product and choose "Gift card" as the product type.
2. A customer buys it, picks an amount and enters who it is for.
3. When the order is paid, the recipient gets an email with their code.
4. The recipient enters the code at checkout and the amount is deducted.
5. Any remaining balance stays on the card for next time.

== Installation ==

1. Install the plugin from the Plugins screen, or upload the `beltoft-gift-cards` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Go to **WooCommerce > Gift Cards > Settings** to set amounts, expiry and other options.
4. Create a new product and choose **Gift card** as the product type.
5. Optionally change the email under **WooCommerce > Settings > Emails > Gift Card Delivery**.

== Frequently Asked Questions ==

= How do customers use a gift card? =
They type the code in the normal coupon field at checkout, or click "Shop Now" in the gift card email to have it applied automatically.

= Can I show a separate gift card field? =
Yes. Turn on "Dedicated Gift Card Field" under WooCommerce > Gift Cards > Settings. It can be placed automatically on the cart and checkout, or wherever you put the `[bgcw_apply_field]` shortcode.

= Can a gift card be used more than once? =
Yes, until the balance runs out. If the card is worth more than the order, only what's needed is used and the rest stays on the card.

= What happens when an order is refunded? =
If the order was paid with a gift card, the money goes back onto the card. Partial refunds put back a matching share.

= Can I create gift cards by hand? =
Yes. Go to WooCommerce > Gift Cards and click "Add Gift Card". Choose the amount, the recipient and why the card is issued (paid offline, promotion or compensation).

= Do gift cards expire? =
By default after 365 days. You can change the number of days, or set it to 0 so cards never expire.

= Are gift cards taxed? =
No, the gift card product itself is not taxed. When a gift card is used, its value is deducted from the order the same way a coupon is.

= Does it work with page builders like Bricks or Elementor? =
Yes. Use the `[bgcw_product_form]` shortcode in your product template.

= Does it work with the block checkout? =
Yes. Gift cards work on both the classic and the block cart and checkout.

= Can customers see their balance? =
Yes. The "Gift Cards" page in My Account shows every card they bought or received, the balance and the history.

= Can I connect it to my accounting system? =
Yes, through the REST API. See the developer notes below.

== For Developers ==

= REST API =

Base: `https://your-store.example/wp-json/wc-bgcw/v1/`. Authenticate with WooCommerce REST API keys or a WordPress application password. Requires the `manage_woocommerce` capability.

* GET /gift-cards — List. Params: page, per_page (up to 100), search, status, source, orderby, order.
* POST /gift-cards — Create. Body: amount*, source* (paid_offline, promotion, compensation), recipient_name, recipient_email, sender_name, sender_email, message, expires_at, product_id, send_email (default true).
* GET /gift-cards/{id} — Single card.
* GET /gift-cards/code/{code} — Single card by code.
* PATCH /gift-cards/{id} — Update status (active/disabled), source, recipient/sender fields, message, expires_at, product_id.
* POST /gift-cards/{id}/adjust — Change balance. Body: amount (positive adds, negative deducts), note.
* GET /gift-cards/{id}/transactions — Transaction history.
* DELETE /gift-cards/{id}?force=true — Permanently delete a card.

Dates are ISO 8601 in UTC (for example `2032-01-31T00:00:00Z`). Every card includes `source` and `is_paid`, and orders that used a gift card record the paid and free amounts redeemed.

Example: `curl -u ck_xxx:cs_xxx "https://your-store.example/wp-json/wc-bgcw/v1/gift-cards?source=promotion"`

= Hooks =

Actions: `bgcw_gift_card_created`, `bgcw_gift_card_deleted`, `bgcw_after_deduct_balance`, `bgcw_after_restore_balance`, `bgcw_my_account_card_actions`, `bgcw_product_form_after_recipient_fields`.

Filters: `bgcw_gift_card_creation_args`, `bgcw_add_to_cart_validation`, `bgcw_add_to_cart_data`, `bgcw_validate_amount_limits`, `bgcw_should_send_email_now`, `bgcw_email_template_html`, `bgcw_coupon_valid_for_gift_card`, `bgcw_refund_as_store_credit`, `bgcw_show_recipient_name_field`, `bgcw_show_recipient_email_field`, `bgcw_show_personal_message_field`, `bgcw_rest_permission`.

Example — hide the Recipient Email field (the buyer's email is used instead):

`add_filter( 'bgcw_show_recipient_email_field', '__return_false' );`

The email template can be overridden by copying `templates/emails/gift-card-delivery.php` to your theme's `woocommerce/emails/` folder.

== Screenshots ==
1. Gift card product page with amount selector and recipient fields.
2. Gift card delivery email sent to the recipient.
3. Gift card applied at checkout via the coupon field.
4. Dedicated "Apply Gift Card" field on the cart page.
5. My Account — Gift Cards tab showing balances and transactions.
6. Admin dashboard with stats.
7. Admin gift card list with search, filters, and bulk actions.
8. Settings page.

== Changelog ==

= 1.6.4 =
* Improved: Readme rewritten in plain language. It now covers product-locked gift cards, expiry, block checkout support and all developer hooks, and the tax answer in the FAQ is corrected.

= 1.6.3 =
* Improved: The My Account gift cards list now stacks each card with labelled rows on small screens instead of a cramped table.

= 1.6.2 =
* Improved: Complete European Portuguese (pt_PT) translation.

= 1.6.1 =
* Fixed: A product-locked code from the email link now waits until the product is in the cart (variable products) and is applied automatically, instead of reporting success on a failed apply.
* Fixed: The dedicated gift card field reports when a code could not be applied, and explains when a card is locked to a product not yet in the cart.
* Fixed: Bulk "Remove product restriction" counts only cards that were locked; admin list loads product names in one query.

= 1.6.0 =
* Added: Gift cards can be locked to a specific product. A locked card only discounts that product, the email link adds the product to the cart with the code applied, and admins can remove the restriction from the gift card list.
* Added: `product_id` on the REST API (read, create, update) and `bgcw_validate_amount_limits` filter.
* Added: "For: product" shown in cart, My Account, admin list and emails for locked cards.

= 1.5.1 =
* Added: `bgcw_gift_card_deleted` and `bgcw_my_account_card_actions` hooks for extensions.

= 1.5.0 =
* Added: Gift card `source` (shop order, paid offline, promotion, compensation). Manual creation now asks for a source.
* Added: Redeeming orders record each gift card's source and paid/free status on the coupon line, plus paid/free redeemed totals on the order.
* Added: REST API `wc-bgcw/v1` for listing, creating, updating, adjusting, and deleting gift cards. Works with WooCommerce REST API keys.
* Added: `bgcw_rest_permission` filter.
* Changed: Existing gift cards are classified by source on upgrade: cards with an order ID become "order" (paid), all others "promotion" (free). If you use the Pro add-on's store credit or BOGO features, review those cards' source via the REST API and adjust with PATCH.
* Fixed: Gift card balances were not deducted, and no gift card data was recorded, on orders placed through the block (Store API) checkout.

= 1.4.8 =
* Fixed: Search in the admin Gift Cards list did nothing.

= 1.4.7 =
* Added: "Block Coupons on Gift Card Products" setting (enabled by default). WooCommerce coupons no longer discount gift card line items, which closes a loophole where a discounted gift card was redeemed at full face value. Other items in the cart are still discounted.
* Added: `bgcw_coupon_valid_for_gift_card` filter to allow specific coupons on gift card products.
* Tested with WordPress 7.1 and WooCommerce 10.7.

= 1.4.6 =
- Tested with WordPress 7.0.

= 1.4.5 =
* Fixed: Initial "Show" button label on the My Account → Gift Cards page now reads "Show code", matching the toggled "Hide code" / "Show code" labels for consistency.

= 1.4.4 =
* Added: Show/Hide toggle for gift card codes on the My Account → Gift Cards page (codes are masked by default).
* Added: Filters `bgcw_show_recipient_name_field`, `bgcw_show_recipient_email_field`, and `bgcw_show_personal_message_field` to hide individual recipient fields on the product page.
* Fixed: Transaction notes now translate at display time instead of being stored in the locale that was active when the row was written.

= 1.4.3 =
* Fixed General settings tab missing on non-gift-card products.

= 1.4.2 =
* Added GitHub Actions workflow for automated WordPress.org deployment.

= 1.4.1 =
* Added `width: 100%` to gift card product fields container for better theme compatibility.
* Added placeholder text to Predefined Amounts field on the product edit page.

= 1.4.0 =
* Renamed plugin slug and folder to `beltoft-gift-cards`.
* Renamed text domain to `beltoft-gift-cards`.
* Replaced inline scripts with `wp_add_inline_script()`.
* Fixed double-escaping on gift card price display.
* Improved input sanitization on all add-to-cart POST data.
* Moved all inline styles to external CSS files.
* Added `wp_cache_delete()` calls after custom table writes.
* Updated author to beltoft.net.

= 1.3.0 =
* Improved: MySQL advisory lock for concurrent balance deductions.
* Improved: SQL-level pagination for My Account gift cards.
* Improved: Bulk gift card code lookups in cart and Store API.
* Improved: Expiry sync moved to WP-Cron (hourly) with composite DB index.
* Fixed: Tax-inclusive discount amount in balance deductions.
* Fixed: Refund safety guard requires prior deduction before restoring balance.
* Added: Block checkout support — gift card codes identified via Store API extension.

= 1.0.0 =
* Initial release.
* Gift card product type with predefined and custom amounts.
* Email delivery to recipients using WooCommerce email templates.
* Auto-apply gift card from email "Shop Now" link.
* Virtual coupon integration — gift card discounts display natively between subtotal and total with WooCommerce [Remove] link.
* Optional dedicated "Apply Gift Card" field with automatic or shortcode-only placement.
* Personal message displayed in cart and order details.
* Price range display in shop catalog (e.g., "$25 – $100").
* Balance tracking with partial redemption.
* My Account tab for viewing gift cards and transactions.
* Admin dashboard, gift card list with bulk actions, and manual creation.
* Order meta box showing created and used gift cards.
* Automatic balance restore on cancel/refund with partial refund support.
* Loyalty Rewards for WooCommerce integration — block or allow loyalty points for gift card purchases.
* Atomic balance deduction to prevent race conditions.
* Rate limiting on gift card code lookups.
* HPOS compatibility.
* Block checkout incompatibility declared (classic checkout required).
* Portuguese (pt_PT) translation included.

== Upgrade Notice ==

= 1.3.0 =
Performance and concurrency improvements.

= 1.0.0 =
Initial release.
