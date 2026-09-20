# Product-locked gift cards ("Give as a gift") — Design

**Date:** 2026-09-20. **Free:** 1.6.0 (DB 1.4). **Pro:** 1.5.0 (also ships the new card layout).

## Problem
A store wants to sell a gift card for a specific product, e.g. a workshop, without creating a gift card product per workshop. The buyer pays now; the recipient redeems later and, for dated products, picks the date then.

## Decisions

### Free 1.6.0 — the lock
- `bgcw_gift_cards.product_id bigint unsigned NULL` (+ index). NULL = ordinary value card. Stores the product the card is locked to (a parent product when variable, so the recipient can choose the variation).
- `Repository::insert()` / `update()` accept `product_id` (null to unlock). REST read exposes `product_id` and `product_name`; REST create/update accept `product_id` (nullable int).
- Virtual coupon (`CartHandler::virtual_coupon_data`): when locked, `product_ids => [product_id]`. WooCommerce then applies the discount only to that product (or its variations) and rejects the code when the product is not in the cart.
- Coupon label: "Gift Card (····ABCD) for Ceramics workshop".
- Redemption link `?bgcw_apply=CODE`: locked card + simple purchasable product → add the product to the cart (if not already there), apply the code, redirect to the cart. Locked + variable/other → apply the code, redirect to the product page so the recipient chooses options. Unlocked → unchanged (shop page).
- Order items: `_bgcw_product_id` line-item meta (from cart item data `bgcw_product_id`) → `GiftCardCreator::create_single()` writes `product_id`.
- Cart/My Account/admin/email show "For: {product name}" when locked. Admin list gets a bulk action "Remove product restriction" (sets product_id NULL) so a sold-out or removed workshop card becomes normal store credit.
- New filter `bgcw_validate_amount_limits( bool $check, int $product_id, float $amount )` in `ProductPage::validate()` so a programmatic add (Pro gifting) can skip the custom-amount min/max.
- Free plugin does not render any "Give as a gift" UI; that is Pro.

### Pro 1.5.0 — "Give as a gift" + new card layout
- **Giftable products**: product edit checkbox "Can be given as a gift" (`_bgcw_pro_giftable`), plus Pro Settings multi-select of categories that are giftable.
- **Carrier product**: one hidden gift-card product created on demand (`catalog_visibility=hidden`, price 0, meta `_bgcw_pro_carrier=1`), id in option `gift_carrier_product_id`. Re-created if deleted.
- **Product page**: on giftable products, a "Give as a gift" toggle under the add-to-cart button reveals recipient name, email, message, the scheduled delivery fields and the card preview link (Pro re-fires `bgcw_product_form_after_recipient_fields`). Submitting posts `bgcw_gift=1` with the normal add-to-cart form.
- **Add-to-cart**: `woocommerce_add_to_cart_handler` returns `bgcw_gift` when `bgcw_gift=1` and the product is giftable; `woocommerce_add_to_cart_handler_bgcw_gift` validates recipient email, computes the amount (`wc_get_price_to_display()`, min price for variable), sets `$_POST['bgcw_amount']`, adds the carrier to the cart with `bgcw_product_id`, shows the standard added-to-cart message and follows the normal redirect setting. Free's min/max check is skipped via `bgcw_validate_amount_limits`.
- **Cart**: line name "Gift: {product name}" (`woocommerce_cart_item_name`), thumbnail of the gifted product (`woocommerce_cart_item_thumbnail`).
- **Email/PDF**: card and notice email show "For: {product name}"; email button reads "Redeem your gift".
- **Card layout** (from the reference PDF): A5 landscape, white, one brand color, Onest (Regular/Bold/ExtraBold). Centered: logo or store name; two-line headline (`pdf_heading_classic`, default "A gift card, just for you!"); paragraph = personal message signed "— Sender" when present, otherwise `pdf_intro` (default "Congratulations! You've received a gift card to spend at {store}."); rounded box with amount left, code + "Valid until" right; for locked cards a line "For: {product}" above the box; footer "Visit us at {host}". Recipient name is not printed.
- Preview lightbox unchanged; live fields: amount, message paragraph, heading.

## Edge cases
- Price rises after purchase: recipient pays the difference. Drops: remainder stays, still locked. Admin can unlock.
- Product deleted/unpurchasable: redemption link applies the code and lands on the shop with a notice; coupon stays restricted until unlocked.
- Quantity > 1 gift: N cards, each locked.
- Variable product gifted: locked to the parent; WooCommerce matches parent id for variations.

## Testing
Free: `tests/test-product-lock.php` (schema, insert/update, coupon product_ids, label, creator meta → product_id, REST product_id, unlock). Pro: `tests/test-gifting.php` (carrier, handler adds carrier with data, purchase flow → locked card → coupon applies only with product in cart → zero total; unlocked card unaffected), renderer tests updated for the new layout, Playwright: gift toggle + preview.
