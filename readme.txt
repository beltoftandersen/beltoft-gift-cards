=== Beltoft Gift Cards for WooCommerce ===
Contributors: christian198521, beltoftnet
Tags: woocommerce, gift cards, gift certificate, store credit, voucher
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.6.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sell digital gift cards, deliver them by email, and let customers redeem them at checkout.

== Description ==

Beltoft Gift Cards for WooCommerce adds a gift card product type to your store. Customers purchase a gift card, choose an amount, and enter the recipient's email. When the order is processed, the recipient gets a branded email with their unique gift card code. Codes are redeemed at checkout through the standard WooCommerce coupon field — no extra steps for the customer.

=== Key Features ===
1. Gift card product type — predefined amounts (e.g., $25, $50, $100) or custom amounts within configurable min/max.
2. Email delivery using WooCommerce email templates — same look as your order emails.
3. Coupon field redemption — gift card codes work in the standard WooCommerce coupon field, no setup required.
4. Optional dedicated "Apply Gift Card" field on cart/checkout via settings or `[bgcw_apply_field]` shortcode.
5. Auto-apply from email — the "Shop Now" button in the delivery email automatically applies the gift card to the recipient's cart.
6. Balance tracking with partial redemption — remaining balance carries over to the next purchase.
7. My Account tab — customers view their gift cards, balances, and full transaction history.
8. Personal message displayed in cart and order details.
9. Price range display — gift card products show "From $25" or "$25 – $100" in your shop catalog.
10. Admin dashboard with stats: total issued, outstanding balance, redeemed, and expired counts.
11. Gift card management list with search, status filters, pagination, and bulk actions (disable/delete).
12. Manual gift card creation from the admin panel — no order required.
13. Order meta box showing gift cards created by and used on each order.
14. Automatic balance restore on order cancel/refund, including proportional partial refund support.
15. Loyalty Rewards integration — optionally block or allow customers from using loyalty points to purchase gift cards (requires Loyalty Rewards for WooCommerce).
16. Shortcode `[bgcw_product_form]` for page builders (Bricks, Elementor, etc.).
17. HPOS compatible — works with WooCommerce High-Performance Order Storage.
18. Email settings (subject, heading, on/off) under WooCommerce > Settings > Emails.
19. Gift card source tracking (shop order, paid offline, promotion, compensation) — the redeeming order records whether the card was paid or free, for accounting.
20. REST API (`wc-bgcw/v1`) to list, create, update, adjust, and delete gift cards from external systems; works with WooCommerce REST API keys.

=== How It Works ===
1. Create a "Gift Card" product in WooCommerce and set the predefined amounts.
2. Customer purchases the gift card, picks an amount, and enters recipient details and an optional message.
3. When the order is processed, a unique code is generated and emailed to the recipient.
4. Recipient enters the code at checkout in the coupon field — the gift card balance is applied as a discount.
5. Partial use is tracked. The remaining balance stays on the gift card for future orders.

== Installation ==
1. Upload the `beltoft-gift-cards` folder to `/wp-content/plugins/` or install via the Plugins screen.
2. Activate the plugin.
3. Go to **WooCommerce > Gift Cards > Settings** to configure.
4. Create a new product and select **"Gift card"** as the product type.
5. Optionally adjust the delivery email under **WooCommerce > Settings > Emails > Gift Card Delivery**.

== Configuration ==

=== Gift Card Product ===
- Create a product, select "Gift card" as the product type.
- Set predefined amounts in the Gift Card data panel (e.g., 25,50,75,100).
- Custom amounts and their min/max are controlled from the global settings page.

=== Redemption ===
- Gift card codes always work in the standard WooCommerce coupon field — this is automatic.
- To also show a dedicated "Apply Gift Card" field, enable it in settings with automatic placement or shortcode-only.
- Shortcode: `[bgcw_apply_field]` — place it on cart or checkout pages.
- Recipients can click "Shop Now" in the delivery email to auto-apply the gift card to their cart.

=== Email Template ===
- Uses WooCommerce's email system — same header, footer, and colours as your other store emails.
- Customise subject and heading under WooCommerce > Settings > Emails > Gift Card Delivery.
- Override the template by copying `templates/emails/gift-card-delivery.php` to your theme's `woocommerce/emails/` folder.

=== Page Builders ===
- For Bricks, Elementor, or other page builders that replace WooCommerce templates, use the WooCommerce Add to Cart element or the `[bgcw_product_form]` shortcode.

== REST API ==

Base: `https://your-store.example/wp-json/wc-bgcw/v1/`. Authenticate with WooCommerce REST API keys (Basic auth over HTTPS) or a WordPress application password. Requires the `manage_woocommerce` capability.

GET /gift-cards — List. Params: page, per_page (<= 100), search, status, source, orderby, order.
POST /gift-cards — Create. Body: amount*, source* (paid_offline, promotion, compensation), recipient_name, recipient_email, sender_name, sender_email, message, expires_at (ISO 8601 or null), send_email (default true).
GET /gift-cards/{id} — Single card.
GET /gift-cards/code/{code} — Single card by code.
PATCH /gift-cards/{id} — Update status (active/disabled), source, recipient/sender fields, message, expires_at.
POST /gift-cards/{id}/adjust — Change balance. Body: amount (positive credit, negative debit), note.
GET /gift-cards/{id}/transactions — Ledger.
DELETE /gift-cards/{id}?force=true — Permanently delete card and ledger.

All datetimes in responses (`created_at`, `expires_at`, transaction `created_at`) are ISO 8601 in UTC with a trailing `Z` (e.g. `2032-01-31T00:00:00Z`). On input, `expires_at` accepts an ISO 8601 datetime (interpreted as UTC when no offset is given), a MySQL datetime string, or `null` to clear it. `recipient_email` and `sender_email` accept a valid email address or an empty string to clear the field.

Every card includes `source` and `is_paid`. On redeemed orders, each gift card coupon line carries `bgcw_gift_card_id`, `bgcw_source`, `bgcw_is_paid`, `bgcw_source_order_id`, and the order carries `_bgcw_paid_redeemed_total` / `_bgcw_free_redeemed_total`. Those two order totals reflect amounts actually deducted and are not reduced by later refunds; refunds appear as separate `refund` transactions in the ledger.

Validation failures return HTTP 400; failures while creating, updating, deleting a card, or recording a ledger entry return HTTP 500 (`bgcw_rest_create_failed`, `bgcw_rest_update_failed`, `bgcw_rest_delete_failed`, `bgcw_rest_ledger_failed`). The `/adjust` amount is bounded to +/-1,000,000.

Example:
`curl -u ck_xxx:cs_xxx "https://your-store.example/wp-json/wc-bgcw/v1/gift-cards?source=promotion"`

=== Hooks & Filters ===
Developers can extend the plugin:

* `bgcw_gift_card_created` — fires after a gift card is created (used by the email system).
* `bgcw_show_recipient_name_field` — return false to hide the Recipient Name field on the product page.
* `bgcw_show_recipient_email_field` — return false to hide the Recipient Email field on the product page. The buyer's billing email is used as the recipient and the email validation is skipped.
* `bgcw_show_personal_message_field` — return false to hide the Personal Message field on the product page.
* `bgcw_rest_permission` — filter REST access (default: `manage_woocommerce`).

Example — hide the Recipient Email field on every gift card product:

`add_filter( 'bgcw_show_recipient_email_field', '__return_false' );`

== Frequently Asked Questions ==

= Do gift cards work with the standard coupon field? =
Yes. Gift card codes are always accepted in the WooCommerce coupon field. This works automatically — customers just enter the code where they would enter a coupon.

= Can I also show a separate gift card field? =
Yes. Go to WooCommerce > Gift Cards > Settings and enable the "Dedicated Gift Card Field." You can choose automatic placement (cart & checkout) or shortcode-only (`[bgcw_apply_field]`).

= Do gift cards support partial redemption? =
Yes. If a gift card balance exceeds the order total, only the needed amount is deducted. The remaining balance stays on the gift card for future use.

= What happens when an order is refunded? =
Gift card balances are automatically restored when an order is cancelled or fully refunded. Partial refunds proportionally restore the gift card balance.

= Can I create gift cards manually? =
Yes. Go to WooCommerce > Gift Cards > Gift Cards tab and click "Add Gift Card." You can specify the amount, recipient, and message. You must also choose a Source (Paid offline, Promotion, or Compensation), which determines whether the card is treated as paid or free when it is redeemed.

= Are gift cards taxable? =
No. Gift card products are set as non-taxable, and gift card discounts are applied as non-taxable negative fees.

= Does it work with page builders like Bricks or Elementor? =
Yes. Use the `[bgcw_product_form]` shortcode inside your page builder's product template to display the gift card amount selector and recipient fields.

= Do emails match my store's design? =
Yes. They use WooCommerce's email template — same header, footer, and styling as order emails.

= Can customers see their gift card balances? =
Yes. A "Gift Cards" tab is added to My Account where customers can view all their gift cards (purchased and received), balances, and transaction history.

= Can customers use loyalty points to buy gift cards? =
By default, no. If you have the Loyalty Rewards for WooCommerce plugin active, an "Integrations" section appears in the gift card settings where you can allow or block loyalty point redemption on gift card purchases.

= Can I access gift cards from my accounting system? =
Yes. Use the REST API (`wc-bgcw/v1`) with WooCommerce REST API keys to list, create, update, adjust, and delete gift cards, including their source and paid/free status. See the REST API section above.

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
