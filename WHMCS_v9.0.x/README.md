# WHMCS 9.0.x — PlugnPay Payment Modules

Payment modules for WHMCS **9.0.x** (target **9.0.7**, gateway API 1.1). Current packages: Remote API and Smart Screens v2.

## Choose a module

| | Remote API | Smart Screens v2 |
|---|---|---|
| Package | `plugnpayapi` | `plugnpayss2` |
| Version | 1.0.0 | 1.0.1 |
| Status | Target WHMCS 9.0.7; validate before production | Target WHMCS 9.0.7; validate before production |
| Download | [whmcs_9.0.7_api_module.zip](./whmcs_9.0.7_api_module.zip) | [whmcs_9.0.7_ss2_module.zip](./whmcs_9.0.7_ss2_module.zip) |
| Checkout | Onsite card fields → `pnpremote.cgi` | Redirect → `https://pay1.plugnpay.com/pay/` |
| Card data on your server | Yes | No |
| PCI scope | Higher | Lower |
| Transaction mode | `authonly` or `authpostauth` | Authorization-only (`pb_post_auth=no`) |
| Admin Refund / Cancel subscription | No (use PlugnPay Admin) | No (use PlugnPay Admin) |
| Public demo account | No — merchant credentials only | No — merchant credentials only |

You may install both modules; enable only the method(s) you need.

## Remote API (onsite)

- Source: [src/api/](./src/api/)
- Download: [whmcs_9.0.7_api_module.zip](./whmcs_9.0.7_api_module.zip)
- Quick install: [INSTALL.txt](./INSTALL.txt)
- Docs: [src/api/README.md](./src/api/README.md)
- Module version: **1.0.0**

WHMCS collects card data on the invoice and posts from the server to PlugnPay Remote API. Capture / void / refund are done in PlugnPay Merchant Admin.

### Requirements

- WHMCS **9.0.x** (target **9.0.7**)
- PHP **8.2** or **8.3** with cURL + OpenSSL
- Overlay path: `modules/gateways/`
- HTTPS System URL (required)
- PlugnPay gateway account username and Remote Client Password

### Install steps

1. Download [whmcs_9.0.7_api_module.zip](./whmcs_9.0.7_api_module.zip) (or use `src/api/`).
2. Copy these files into the WHMCS root so they merge in place:
   - `modules/gateways/plugnpayapi.php`
   - `modules/gateways/plugnpayapi/`
3. Configuration → System Settings → Payment Gateways → activate **PlugnPay Remote API**.
4. Enter Gateway Account, Remote Client Password, and Authorization Type.
5. Place a test invoice payment on a non-production system.

## Smart Screens v2 (hosted)

- Source: [src/ss2/](./src/ss2/)
- Download: [whmcs_9.0.7_ss2_module.zip](./whmcs_9.0.7_ss2_module.zip)
- Quick install: [INSTALL_SS2.txt](./INSTALL_SS2.txt)
- Docs: [src/ss2/README.md](./src/ss2/README.md)
- Module version: **1.0.1**

Redirects customers to PlugnPay hosted Smart Screens. WHMCS does not collect card data. Capture / void / refund are done in PlugnPay Merchant Admin.

### Requirements

- WHMCS **9.0.x** (target **9.0.7**)
- PHP **8.2** or **8.3**
- Overlay path: `modules/gateways/`
- HTTPS recommended for the invoice return / callback
- PlugnPay gateway account username

### Install steps

1. Download [whmcs_9.0.7_ss2_module.zip](./whmcs_9.0.7_ss2_module.zip) (or use `src/ss2/`).
2. Copy these files into the WHMCS root so they merge in place:
   - `modules/gateways/plugnpayss2.php`
   - `modules/gateways/callback/plugnpayss2.php`
   - `modules/gateways/plugnpayss2/`
3. Configuration → System Settings → Payment Gateways → activate **PlugnPay Smart Screens v2**.
4. Enter the Gateway Account (PlugnPay username).
5. Place a test invoice payment on a non-production system.

## Development layout

```
WHMCS_v9.0.x/
  README.md
  INSTALL.txt                 # Remote API
  INSTALL_SS2.txt
  whmcs_9.0.7_api_module.zip
  whmcs_9.0.7_ss2_module.zip
  src/api/
  src/ss2/
  tests/
```
