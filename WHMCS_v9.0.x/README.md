# WHMCS 9.0.x — PlugnPay Payment Module

Payment module for WHMCS **9.0.x** (target **9.0.7**, third-party gateway API 1.1). Current package: Smart Screens v2.

## Choose a module

| | Smart Screens v2 |
|---|---|
| Package | `plugnpayss2` |
| Version | 1.0.0 |
| Status | Target WHMCS 9.0.7; validate before production |
| Download | [whmcs_9.0.7_ss2_module.zip](./whmcs_9.0.7_ss2_module.zip) |
| Checkout | Redirect → `https://pay1.plugnpay.com/pay/` |
| Card data on your server | No |
| PCI scope | Lower |
| Transaction mode | Authorization-only (`pb_post_auth=no`) |
| Admin Refund / Cancel subscription | No (use PlugnPay Admin) |
| Public demo account | No — merchant credentials only |

## Smart Screens v2 (hosted)

- Source: [src/ss2/](./src/ss2/)
- Download: [whmcs_9.0.7_ss2_module.zip](./whmcs_9.0.7_ss2_module.zip)
- Quick install: [INSTALL_SS2.txt](./INSTALL_SS2.txt)
- Docs: [src/ss2/README.md](./src/ss2/README.md)
- Module version: **1.0.0**

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
  INSTALL_SS2.txt
  whmcs_9.0.7_ss2_module.zip
  src/ss2/
    README.md
    modules/gateways/plugnpayss2.php
    modules/gateways/callback/plugnpayss2.php
    modules/gateways/plugnpayss2/
  tests/
```
