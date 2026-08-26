# WHMCS 8.x — PlugnPay Payment Module

Package for **WHMCS 8.x** (third-party gateway API 1.1). Smart Screens v2 hosted checkout.

The module is an **untested beta**. It was converted from the WHMCS sample third-party gateway without a production WHMCS license. Test on a non-production system before going live.

## Choose a module

| | Smart Screens v2 |
|---|---|
| Package | `plugnpayss2` |
| Status | Untested beta (converted 2024) |
| Download | [whmcs_ss2_module.zip](./whmcs_ss2_module.zip) |
| Checkout | Redirect → `https://pay1.plugnpay.com/pay/` |
| Card data on your server | No |
| PCI scope | Lower |
| Admin Refund / Cancel subscription | No (use PlugnPay Admin) |

## Smart Screens v2 (hosted)

- Source: [src/ss2/](./src/ss2/)
- Download: [whmcs_ss2_module.zip](./whmcs_ss2_module.zip)
- Quick install: [INSTALL_SS2.txt](./INSTALL_SS2.txt)
- Docs: [src/ss2/README.md](./src/ss2/README.md)

Redirects customers to PlugnPay hosted Smart Screens. WHMCS does not collect card data. Capture / void / refund are done in PlugnPay Merchant Admin.

### Requirements

- WHMCS **8.x** (gateway module API 1.1)
- Overlay path: `modules/gateways/`
- HTTPS recommended for the invoice return / callback
- PlugnPay gateway account username

### Install steps

1. Download [whmcs_ss2_module.zip](./whmcs_ss2_module.zip) (or use `src/ss2/`).
2. Copy these files into the WHMCS root so they merge in place:
   - `modules/gateways/plugnpayss2.php`
   - `modules/gateways/callback/plugnpayss2.php`
3. Configuration → System Settings → Payment Gateways → activate **PlugnPay Smart Screens v2**.
4. Enter the Gateway Account (PlugnPay username).
5. Place a test invoice payment on a non-production system.

## Development layout

```
WHMCS_v8.x/
  README.md
  INSTALL_SS2.txt
  whmcs_ss2_module.zip
  src/
    ss2/
      README.md
      modules/gateways/plugnpayss2.php
      modules/gateways/callback/plugnpayss2.php
```
