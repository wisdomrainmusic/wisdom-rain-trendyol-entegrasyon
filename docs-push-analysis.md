# Trendyol Product Push Chain Analysis

## 1. Call Chain Overview
- Admin UI button: `#wr_trendyol_push_product_btn` is rendered inside the WooCommerce product edit screen by `WR\Trendyol\Admin\WR_Trendyol_Product_Tab::render_product_panel()` with `data-product-id` set to the current product ID.
- JavaScript handler: `assets/js/wr-trendyol-admin-product.js` binds a click listener to `#wr_trendyol_push_product_btn` and triggers an AJAX `POST` to `admin-ajax.php` with `action=wr_trendyol_push_product`, `product_id`, and `nonce` pulled from `wr_trendyol_product_data`.
- AJAX hook registration: `add_action( 'wp_ajax_wr_trendyol_push_product', [ $this, 'ajax_push_product' ] );` inside `WR\Trendyol\Admin\WR_Trendyol_Product_Tab::__construct()`.
- PHP callback: `WR\Trendyol\Admin\WR_Trendyol_Product_Tab::ajax_push_product()` verifies capability (`edit_products`), checks `wp_verify_nonce( 'wr_trendyol_product_nonce' )`, and reads `$_POST['product_id']` via `absint`. It then `require_once`s the mapper and sync classes and instantiates `WR_Trendyol_Product_Mapper` and `WR_Trendyol_Product_Sync` with the API client from the plugin singleton before calling `$sync->push_single_product( $product_id );`.
- Mapping: `WR_Trendyol_Product_Mapper::map_single_product()` (no namespace) builds a single item payload from WooCommerce meta, including category/brand/barcode checks, images, attributes, and stock data.
- Sync layer: `WR_Trendyol_Product_Sync::push_single_product()` wraps the mapped item into `['items' => [ $payload_item ]]` and POSTs to `/sapigw/suppliers/{sellerId}/products` through `WR\Trendyol\WR_Trendyol_API_Client::request()`.
- API client: `WR\Trendyol\WR_Trendyol_API_Client::request()` builds the base URL (`https://apigw.trendyol.com` or stage), adds Basic Auth headers, JSON-encodes the body, performs `wp_remote_request`, and returns decoded responses or `WP_Error` on failures.

## 2. Current Request Payload Structure
- Top-level payload sent by `push_single_product`: `{ "items": [ payload_item ] }`.
- `payload_item` built by the mapper includes:
  - `barcode`: `_wr_trendyol_barcode` meta (required check).
  - `title`: WooCommerce product name.
  - `brandId`: `_wr_trendyol_brand_id` meta (required check).
  - `categoryId`: `_wr_trendyol_category_id` (fallback `_trendyol_category_id`) meta (required check).
  - `stockCode`: WooCommerce SKU (fallback `WR-{product_id}`).
  - `quantity`: stock quantity (falls back to `0` when null).
  - `dimensionalWeight`: `_wr_trendyol_dimensional_weight` (defaults to `1` if empty).
  - `description`: stripped full description, falling back to short description.
  - `productMainId`: same as `stockCode`.
  - `images`: array of `{ url }` entries from main image + gallery.
  - `attributes`: built by `build_attributes_payload()`:
    - Pulls category attributes from API client, reads stored meta `_wr_trendyol_attr_{attributeId}`.
    - For stored value IDs: outputs `{ attributeId, attributeValueId }` (one entry per value when multiple).
    - For custom text and when allowed: outputs `{ attributeId, customValue }`.
    - Missing required attributes trigger `WP_Error` `wr_trendyol_missing_attribute`.

## 3. Comparison With Trendyol Official Product API
- Trendyol v2 product creation/update (per public docs) expects a POST/PUT to `/suppliers/{supplierId}/v2/products` with each item typically containing fields such as: `barcode`, `title`, `productMainId`, `brandId`, `categoryId`, `quantity`, `stockCode`, `dimensionalWeight`, `description`, `attributes[]`, `images[]`, plus logistics/price fields like `cargoCompanyId`, `deliveryDuration`, `shipmentAddressId`, `returnAddressId`, `listPrice`, `salePrice`, `vatRate`.
- Plugin vs. Trendyol expectations (high-level):
  - **Endpoint**: Plugin posts to `/sapigw/suppliers/{sellerId}/products` (no `/v2`), while current docs emphasize `/suppliers/{supplierId}/v2/products`.
  - **Prices**: Plugin omits `listPrice`, `salePrice`, and `vatRate`; relies on future “price-and-inventory” endpoint but does not call it in the push chain.
  - **Logistics**: No `cargoCompanyId`, `deliveryDuration`, `shipmentAddressId`, or `returnAddressId` fields are supplied.
  - **Attributes**: Plugin builds `[ { attributeId, attributeValueId } | { attributeId, customValue } ]`; doc samples usually use `attributeValue`/`attributeValueId` with optional `customAttributeValue`. Custom values may need `attributeValueId` + `customAttributeValue` instead of `customValue`.
  - **Images**: Plugin sends `[ { url } ]`, which aligns with doc samples using `{ url: "..." }`.
  - **Stock**: `quantity` included, but API often expects per-variant stock tied to `barcode`; plugin sends single quantity with `barcode`.

| Alan / Beklenti | Trendyol bekliyor | Plugin gönderiyor |
| --- | --- | --- |
| Endpoint | `/suppliers/{supplierId}/v2/products` | `/sapigw/suppliers/{sellerId}/products` |
| Fiyat alanları | `listPrice`, `salePrice`, `vatRate` (zorunlu) | Hiçbiri (payload yok) |
| Kargo alanları | `cargoCompanyId`, `deliveryDuration`, `shipmentAddressId`, `returnAddressId` | Yok |
| Attributes | `{ attributeId, attributeValueId, customAttributeValue? }` | `{ attributeId, attributeValueId }` ve `{ attributeId, customValue }` |
| productMainId | Genellikle varyant gruplama için | SKU ile eşitlenmiş (aynı değer) |

## 4. Root Cause Candidates for 500 Error
- **Type-hint mismatch causes fatal error**: `WR_Trendyol_Product_Mapper::__construct( WR_Trendyol_API_Client $client )` and `WR_Trendyol_Product_Sync::__construct( WR_Trendyol_API_Client $client, WR_Trendyol_Product_Mapper $mapper )` use global class names, but the instantiated API client is `WR\Trendyol\WR_Trendyol_API_Client` (namespaced). PHP will throw `TypeError: Argument 1 passed to WR_Trendyol_Product_Mapper::__construct() must be an instance of WR_Trendyol_API_Client, instance of WR\Trendyol\WR_Trendyol_API_Client given`, yielding a 500 during the AJAX call before any API request.
- **Required meta missing**: Mapper returns `WP_Error` when `_wr_trendyol_category_id`, `_wr_trendyol_brand_id`, or `_wr_trendyol_barcode` are empty, or when any required category attribute is not filled. JS only displays the error string; the absence of defensive checks in sync means WP_Error is handled, but the upstream AJAX might still show a generic failure.
- **Attribute payload vs API schema**: Custom attributes use `customValue` without `attributeValueId`, whereas docs expect `customAttributeValue` (and often a paired `attributeValueId`). Trendyol could reject the payload with 400/500 depending on validation.
- **Missing price/logistics fields**: Posting to product creation endpoint without mandatory pricing/logistics fields may result in API-side 4xx/5xx responses. Current code does not add them or fall back to defaults.

## 5. Suggested Patch Plan (NO CODE YET)
- **Constructor type compatibility**: Remove global type hints or import the namespaced class so the API client instance passes validation. Ensure mapper/sync classes either share the namespace or accept `\WR\Trendyol\WR_Trendyol_API_Client`.
- **Endpoint alignment**: Switch to `/suppliers/{supplierId}/v2/products` and confirm base URL paths; keep staging/prod host logic in the API client.
- **Payload completeness**:
  - Add required pricing fields (`listPrice`, `salePrice`, `vatRate`) sourced from WooCommerce regular/sale price and tax class; validate presence before POST.
  - Include logistics fields (`cargoCompanyId`, `deliveryDuration`, `shipmentAddressId`, `returnAddressId`, optional `cargoId`) pulled from plugin settings or per-product meta.
  - Distinguish create vs update (PUT) using stored `_wr_trendyol_product_id` when present.
- **Attribute mapping**: When reading `_wr_trendyol_attr_{attributeId}` meta:
  - For value selections, emit `{ attributeId, attributeValueId }` (or multiple entries if `multipleValues`).
  - For custom entries, emit `{ attributeId, attributeValueId: 0, customAttributeValue: <text> }` (field name per doc) instead of `customValue`, optionally pairing with the selected `attributeValueId` if the category requires it.
- **Error surfacing**: In `ajax_push_product()`, detect `WP_Error` separately for type errors vs API errors and return structured messages; log full response when debug is enabled. Add server-side guards for missing settings (API key/secret/seller ID).
- **Follow-up flow**: After successful creation/update, trigger price+inventory endpoint or enqueue a secondary call so quantities/prices are consistent.
