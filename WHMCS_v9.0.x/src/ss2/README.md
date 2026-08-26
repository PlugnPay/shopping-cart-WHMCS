# PlugnPay Smart Screens v2 Module for WHMCS 9.0.x

**Version:** 1.0.0  
**Target:** WHMCS **9.0.7** (compatible with 9.0.x)  
**Status:** Protocol tests included; validate on a non-production WHMCS 9.0.7 system before production

Hosted **authorization-only** payments via PlugnPay Smart Screens v2 (`https://pay1.plugnpay.com/pay/`).

This is a WHMCS third-party gateway (`modules/gateways/`). It does not collect card data in WHMCS.

## Features

- Offsite / hosted checkout (card data collected on PlugnPay)
- Authorization-only (`pb_post_auth=no`) — capture / settle in PlugnPay Admin
- Return checks: `pi_response_status`, amount, gateway account, invoice id
- Optional debug logging with PAN / CVV / password redaction
- PHP 8.2+ / WHMCS 9.0.x (gateway API 1.1)

This module does **not** include WHMCS admin Refund or Cancel Subscription. Settle or reverse transactions in [PlugnPay Merchant Admin](https://pay1.plugnpay.com/admin/).

## PCI notice

This module does **not** collect cardholder data on your server. Customers are redirected to PlugnPay’s hosted Smart Screens pages.

## Requirements

- WHMCS **9.0.x** (target **9.0.7**)
- PHP **8.2** or **8.3** (WHMCS 9.0 system requirement)
- PlugnPay **gateway account username** (merchant-supplied; there is no public demo account)
- HTTPS on the Client Area is **strongly recommended** so the return POST to the callback stays secure

No Remote Client Password, cURL, or IP whitelist is required for this module.

## Installation

1. Unzip `whmcs_9.0.7_ss2_module.zip` into your WHMCS **root** (the folder that contains `init.php` and `modules/`) so you have:

   ```
   modules/gateways/plugnpayss2.php
   modules/gateways/callback/plugnpayss2.php
   modules/gateways/plugnpayss2/
   ```

2. Configuration → System Settings → Payment Gateways → All Payment Gateways → activate **PlugnPay Smart Screens v2**.

3. Enter **Gateway Account** (PlugnPay username). Optionally enable Debug Logging.

4. Place a test invoice payment with your merchant account (per your PlugnPay test procedures).

### Configuration reference

| Setting | Key | Notes |
|---|---|---|
| Gateway Account | `accountId` | PlugnPay username (`pt_gateway_account`) |
| Debug Logging | `debugLog` | Off, or sanitized files under `modules/gateways/plugnpayss2/logs/` |

Checkout always sends `pb_post_auth=no`. On a successful authorization the callback records the invoice payment in WHMCS so services can provision. Capture/settle the transaction in PlugnPay Admin.

There is **no** Test/Production toggle and **no** public demo publisher.

## Checkout flow

1. Client opens an unpaid invoice and chooses PlugnPay Smart Screens v2 (Pay Now).
2. WHMCS POSTs hidden invoice fields to `https://pay1.plugnpay.com/pay/`.
3. Client completes payment on PlugnPay Smart Screens (authorization only).
4. PlugnPay POSTs back to `modules/gateways/callback/plugnpayss2.php` (`pb_success_url`, `pb_transition_type=post`).
5. Callback validates the return (see below).
6. On success, WHMCS logs the gateway transaction and applies the invoice payment.
7. Client is redirected to `viewinvoice.php`.

### Return validation

Accepted return POST must include `pi_response_status`. On `success`, the module also checks:

- Returned `pt_transaction_amount` matches the amount sent at submit (`whmcsamount` custom field)
- Returned `pt_gateway_account` matches the configured Gateway Account (when present)
- Invoice id from `whmcsinvoice` custom field or `pt_account_code_1` is a valid WHMCS invoice

Cryptographic response-link / hash verification is **not** included.

The shopper session cookie is not required. Matching uses the invoice id in the POST.

### Key fields submitted to `/pay/`

| Field | Purpose |
|---|---|
| `pt_gateway_account` | Merchant gateway account |
| `pt_transaction_amount` | Invoice amount due |
| `pt_currency` / `pt_currency_code` | Invoice currency |
| `pb_post_auth` | Always `no` (authorization-only) |
| `pt_account_code_1` | WHMCS invoice id |
| `pt_payment_name` + billing fields | Prefill billing on hosted page |
| `pb_success_url` | Return URL → WHMCS callback |
| `pb_transition_type` | `post` |
| `pd_display_items` | `no` |
| `pd_collect_shipping_information` | `no` |
| `pt_client_identifier` | `WHMCS_SS2` |
| `pt_custom_name_1` / `pt_custom_value_1` | Custom pair: name `whmcsinvoice`, value = invoice id |
| `pt_custom_name_2` / `pt_custom_value_2` | Custom pair: name `whmcsamount`, value = amount due |

Arbitrary custom keys are not part of the Smart Screens v2 spec; use `pt_custom_name_N` / `pt_custom_value_N` instead.

## Logging

WHMCS always writes a Gateway Log row (`logTransaction`).

Enable **Debug Logging** for sanitized files:

```
modules/gateways/plugnpayss2/logs/plugnpay_ss2_YYYYMMDD.log
```

Never logged: full card number, CVV, or publisher-password.

## Troubleshooting

| Symptom | What to check |
|---|---|
| Pay Now does nothing / empty form | Gateway Account is empty; re-save gateway settings |
| Client returns but invoice stays Unpaid | Decline (`pi_response_status` not `success`), amount mismatch, or gateway account mismatch — see Gateway Log |
| “Amount Mismatch” in Gateway Log | Invoice total changed between Pay Now and return |
| “Gateway Account Mismatch” | `pt_gateway_account` on return ≠ configured Gateway Account |
| Duplicate transaction die | Same `pt_order_id` already recorded (`checkCbTransID`) |
| Need to capture / void / refund | Use PlugnPay Merchant Admin (not this WHMCS module) |

## Manual test checklist

Use this list for a merchant regression pass on WHMCS **9.0.7**:

- [ ] Gateway appears under Configuration → System Settings → Payment Gateways
- [ ] Pay Now redirects to `https://pay1.plugnpay.com/pay/`
- [ ] Approved payment marks the invoice Paid and logs Success
- [ ] Declined card leaves the invoice Unpaid and logs Failure
- [ ] Submit uses `pb_post_auth=no` (visible in debug log)
- [ ] Debug log redacts PAN/CVV/password
- [ ] Admin invoice screen has **no** working Refund for this gateway (use PlugnPay Admin)

## File map

```
modules/gateways/plugnpayss2.php
modules/gateways/callback/plugnpayss2.php
modules/gateways/plugnpayss2/
  PnPSs2Protocol.php
  PnPSs2Logger.php
  changelog.txt
  index.html
README.md
```

## Uninstall

1. Configuration → System Settings → Payment Gateways → deactivate **PlugnPay Smart Screens v2**.
2. Delete the uploaded module files if you are not keeping them.

## Support

Provided AS IS. See [PlugnPay docs](https://docs.plugnpay.com/) and the WHMCS developer docs for integration questions.
