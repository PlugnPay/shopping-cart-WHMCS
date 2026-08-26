# PlugnPay Remote API Module for WHMCS 9.0.x

**Version:** 1.0.2  
**Target:** WHMCS **9.0.7** (compatible with 9.0.x)  
**Status:** Protocol tests included; validate on a non-production WHMCS 9.0.7 system before production

Credit card payments via PlugnPay’s Remote API (`https://pay1.plugnpay.com/payment/pnpremote.cgi`).

This is a WHMCS **merchant** gateway (`modules/gateways/`). It collects card data in WHMCS and posts from the server with PHP cURL.

## Features

- Onsite credit card collection (checkout stays in the WHMCS Client Area)
- Authorize-only (`authonly`) or Sale (`authpostauth`)
- Production only (HTTPS required; no Test/Production toggle)
- Debug logging with PAN / CVV / password redaction (including PAN found in free-text)
- Input filtering on all fields sent to PlugnPay
- PHP 8.2+ / WHMCS 9.0.x (gateway API 1.1)
- Requires PHP cURL with SSL

This module does **not** include WHMCS admin Refund. Settle or reverse transactions in [PlugnPay Merchant Admin](https://pay1.plugnpay.com/admin/). For hosted (lower PCI) checkout, use Smart Screens v2 (`plugnpayss2`).

## When to use this vs Smart Screens v2

| | Remote API (this module) | Smart Screens v2 |
|---|---|---|
| Card data | Collected in WHMCS | Collected on PlugnPay |
| Customer experience | Stays on the invoice / checkout | Redirect to hosted billing page |
| PCI scope | Higher | Lower |
| Checkout endpoint | `pnpremote.cgi` (server-to-server) | `https://pay1.plugnpay.com/pay/` |
| Transaction mode | Auth-only or sale | Authorization-only |
| Admin Refund in WHMCS | No | No |
| Public demo account | None — merchant credentials only | None — merchant credentials only |

## PCI notice

This module collects cardholder data on your server, which increases PCI DSS scope. Client Area **HTTPS is required**. The module:

- Transmits CHD only over TLS to `pnpremote.cgi` (peer verify, HTTPS-only, no redirects)
- Does not store PAN, CVV, or the Remote Client Password
- Filters inbound fields (type, length, control characters) before they are sent
- Masks PAN (last four only) and redacts CVV, track data, PIN, and passwords in debug logs and WHMCS `rawdata`
- Unsets card fields after the capture request
- Writes debug files under `logs/` with restrictive permissions (web access denied)

Full PAN, CVV, and publisher-password are never written to debug logs or the WHMCS gateway log.

## Requirements

- WHMCS **9.0.x** (target **9.0.7**)
- PHP **8.2** or **8.3** with **cURL + OpenSSL**
- Client Area **HTTPS** (System URL must start with `https://`)
- PlugnPay **gateway account username** (`publisher-name`)
- **Remote Client Password** from PlugnPay Security Administration (`publisher-password` — not your admin login password)

There is no public demo / test publisher.

## Installation

1. Unzip `whmcs_9.0.7_api_module.zip` into your WHMCS **root** (the folder that contains `init.php` and `modules/`) so you have:

   ```
   modules/gateways/plugnpayapi.php
   modules/gateways/plugnpayapi/
   ```

2. Configuration → System Settings → Payment Gateways → All Payment Gateways → activate **PlugnPay Remote API**.

3. Enter **Gateway Account** and **Remote Client Password**. Choose Authorization Type. Optionally enable Debug Logging.

4. Place a test invoice payment with your merchant account (per your PlugnPay test procedures).

### Configuration reference

| Setting | Key | Notes |
|---|---|---|
| Gateway Account | `accountId` | PlugnPay username (`publisher-name`) |
| Remote Client Password | `remotePassword` | `publisher-password` |
| Authorization Type | `authType` | `authonly` or `authpostauth` |
| Debug Logging | `debugLog` | Off, or sanitized files under `modules/gateways/plugnpayapi/logs/` |

**Authorization Type mapping**

- `authonly` → authorize only; settle in PlugnPay Admin
- `authpostauth` → authorize and settle (sale)

WHMCS still marks the invoice **paid** on a successful capture so services can provision.

There is **no** Test/Production toggle and **no** public demo publisher.

## Checkout flow

1. Client opens an unpaid invoice and chooses PlugnPay Remote API.
2. WHMCS collects card number, expiry, and CVV (cardholder-present) on the invoice.
3. `plugnpayapi_capture()` POSTs hyphenated Remote API fields to `pnpremote.cgi` with PHP cURL.
4. On `FinalStatus=success`, WHMCS records the payment (invoice Paid).
5. On decline / error, the client sees the gateway message and the invoice stays Unpaid.

### Key fields submitted to `pnpremote.cgi`

| Field | Purpose |
|---|---|
| `publisher-name` | Merchant gateway account |
| `publisher-password` | Remote Client Password (never logged) |
| `mode` / `paymethod` | `auth` / `credit` (`checkcard` when amount is `$0`) |
| `authtype` | `authonly` or `authpostauth` |
| `card-amount` / `currency` | Invoice amount due |
| `card-number` / `card-exp` / `card-name` / `card-cvv` | Card data (never logged in full) |
| `card-address*` / `phone` / `email` | Billing |
| `orderID` / `acct_code` | WHMCS invoice id |
| `ipaddress` | Client IP |
| `client` | `WHMCS_API` |
| `dontsndmail` | `yes` (WHMCS sends its own confirmation) |

WHMCS supplies expiry as `MMYY`; the module sends `card-exp` as `MM/YY`. Line items are not sent; WHMCS invoice rows plus tax/credit often do not match `card-amount`.

## Logging

WHMCS records sanitized `rawdata` in the Gateway Log.

Enable **Debug Logging** for sanitized files:

```
modules/gateways/plugnpayapi/logs/plugnpay_api_YYYYMMDD.log
```

Never logged: full card number (PAN is last-four only, including PAN found in free-text), CVV, track data, PIN, or publisher-password.

## Troubleshooting

| Symptom | What to check |
|---|---|
| “not configured” | Gateway Account and Remote Client Password are both required |
| “HTTPS is required” | System URL must be `https://…` |
| “valid card number and expiry are required” | Card number must be 13–19 digits; expiry must be a valid MMYY |
| Declined | Gateway Log `MErrMsg` / `FinalStatus`; card, AVS, or account settings |
| cURL / empty response | PHP cURL + OpenSSL; firewall to `pay1.plugnpay.com` |
| Need to capture / void / refund | Use PlugnPay Merchant Admin (not this WHMCS module) |
| 3-D Secure / extra authentication | This module does not implement 3-D Secure; those cards may decline |

## Manual test checklist

Use this list for a merchant regression pass on WHMCS **9.0.7**:

- [ ] Gateway appears under Configuration → System Settings → Payment Gateways
- [ ] Invoice shows WHMCS card fields (not a redirect button only)
- [ ] HTTP System URL refuses capture (HTTPS required)
- [ ] Approved payment marks the invoice Paid
- [ ] Declined card leaves the invoice Unpaid
- [ ] `authonly` vs `authpostauth` matches the configured type in debug log
- [ ] Debug log / Gateway Log redacts PAN/CVV/password (PAN in free-text is last-four only)
- [ ] Admin invoice screen has **no** working Refund for this gateway (use PlugnPay Admin)

## File map

```
modules/gateways/plugnpayapi.php
modules/gateways/plugnpayapi/
  PnPApi.php
  PnPLogger.php
  changelog.txt
  index.html
README.md
```

## Uninstall

1. Configuration → System Settings → Payment Gateways → deactivate **PlugnPay Remote API**.
2. Delete the uploaded module files if you are not keeping them.

## Support

Provided AS IS. See [PlugnPay docs](https://docs.plugnpay.com/) and the WHMCS developer docs for integration questions.
