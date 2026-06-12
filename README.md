**English** | [العربية](README.ar.md)

# Sham Cash payment gateway for Magento 2

Accept **Sham Cash** (شام كاش) e-wallet payments in Magento 2 Open Source / Adobe
Commerce **2.4.7+** across all three storefront surfaces: **Luma**, **GraphQL**
and **REST**.

## How it works (read this first)

The public Sham Cash API (`https://api.shamcash-api.com/v1`) is **read-only** —
it exposes only `GET /accounts`, `GET /balances` and `GET /transactions`, with a
`Authorization: Bearer <token>` header. It has **no payment-initiation endpoint,
no hosted page, and no webhook**.

So this module is a **reconciliation gateway**, not a "charge" gateway:

1. At checkout the customer chooses **Sham Cash**; the order is placed as
   *pending payment* with the order increment id as its reference.
2. The success page shows the merchant's **wallet address**, **QR payload**, the
   **amount/currency**, and the **reference to put in the transfer note**.
3. The customer transfers the amount from the Sham Cash app.
4. The module reads `GET /transactions` for the merchant account and, when it
   finds the matching incoming transfer, **invoices the order automatically**.

Matching is **note/reference first** (then verified by amount + currency), with
an amount+currency time-window fallback. A transfer can only ever be credited to
one order (unique index on `transaction_id`). Reconciliation runs from **cron**,
from a customer **"I've paid"** button, and from an admin **"Check now"** button,
with an admin **manual-match** override.

## Install

Install as a Composer package (recommended). The repository root *is* the
module (`type: magento2-module`), so Composer places it under `vendor/` and
Magento picks it up through `registration.php` — nothing is copied into
`app/code`.

```bash
composer config repositories.shamcash-magento vcs https://github.com/zkriahac/shamcash-magento2.git
composer require shamcash/module-payment:dev-main

bin/magento module:enable ShamCash_Payment
bin/magento setup:upgrade
bin/magento setup:di:compile        # production mode
bin/magento cache:flush
```

Once the package is published on Packagist the `composer config repositories`
step can be skipped.

<details>
<summary>Manual install (app/code) — not recommended</summary>

Copy the repository contents to `app/code/ShamCash/Payment/`, then run the same
`bin/magento` commands as above.

</details>

## Configure

**Stores → Configuration → Sales → Payment Methods → Sham Cash (شام كاش)**

| Setting | Notes |
| --- | --- |
| Enabled / Title | Show the method at checkout. |
| API Base URL | Defaults to `https://api.shamcash-api.com/v1`. |
| API Token | Bearer token from the Sham Cash dashboard. Stored encrypted. |
| Account ID | The linked account that receives transfers. |
| **Refresh account info** | Calls `/accounts`, caches wallet **address**, **QR payload** and status. |
| Allowed Currencies | Default `SYP,USD,TRY`. Method hides for other currencies. |
| Currency → coin_id map | Optional `/transactions` filter, e.g. `SYP:1, USD:2, TRY:3`. |
| Matching Mode | Reference-first (default), reference-only, or amount-only. |
| Amount Tolerance | Absolute tolerance when verifying the amount. |
| Time Window Grace / Order Max Age | Search window and how long cron keeps polling an order. |
| Reconciliation Cron Schedule | Crontab expression, default `*/5 * * * *`. |
| Checkout Instructions | Placeholders: `{amount}`, `{currency}`, `{address}`, `{reference}`. |

The token never leaves the server; the storefront only sees the wallet address,
QR payload, amount and reference.

## API mapping

| Module concept | Sham Cash field |
| --- | --- |
| Merchant wallet shown to customer | `account.address` |
| QR shown to customer | `account.qr_payload` |
| Payment reference (in note) | order increment id ↔ `transaction.note` |
| Confirmed amount / currency | `transaction.amount` / `transaction.currency.code` |
| Payment txn id on the order | `transaction.transaction_id` |

Error codes are mapped to typed exceptions: `AUTH_*`/`FORBIDDEN` →
`AuthenticationException`, `SUBSCRIPTION_UNAVAILABLE` →
`SubscriptionUnavailableException` (pauses that store for the run),
`RATE_LIMIT_EXCEEDED` → `RateLimitException` (honors `Retry-After`),
`FETCH_FAILED` → retried with backoff.

## GraphQL

Set the method on the cart with the standard mutation:

```graphql
mutation {
  setPaymentMethodOnCart(input: { cart_id: "CART", payment_method: { code: "shamcash" } }) {
    cart { selected_payment_method { code } }
  }
}
```

Then (authenticated customer) read instructions and trigger a check:

```graphql
query {
  shamCashPaymentInstructions(order_number: "000000123") {
    reference amount currency wallet_address qr_payload status instructions
  }
}

mutation {
  shamCashCheckPayment(order_number: "000000123") {
    status message transaction_id
  }
}
```

## REST (admin / integration token)

```bash
# Payment instructions for an order
curl -H "Authorization: Bearer <ADMIN_TOKEN>" \
  "https://store.example/rest/V1/shamcash/orders/15/instructions"

# Reconcile an order now
curl -X POST -H "Authorization: Bearer <ADMIN_TOKEN>" \
  "https://store.example/rest/V1/shamcash/orders/15/check"
```

These endpoints require the `ShamCash_Payment::reconcile` ACL resource.
Customer-facing checks go through the Luma success page or GraphQL.

## Testing

Framework-light units (matching rules, response-envelope parsing, DTOs, config
parsing, retry/backoff) run **without a full Magento install**:

```bash
mkdir -p .tools && ( cd .tools && composer require "phpunit/phpunit:^11" )
./.tools/vendor/bin/phpunit -c phpunit.xml.dist
```

CI runs the same suite (`.github/workflows/unit-tests.yml`) on PHP 8.2 and 8.3.

## Notes & limitations

- Requires an **active Sham Cash subscription** on the linked account; otherwise
  the API returns `SUBSCRIPTION_UNAVAILABLE` and reconciliation pauses.
- The QR payload is shown as copyable text; render it as an image in your theme
  if desired.
- Companion builds: [WooCommerce](https://github.com/zkriahac/shamcash-woocommerce)
  and [Shopify](https://github.com/zkriahac/shamcash-shopify). The `Gateway/`
  client is written to be portable.
