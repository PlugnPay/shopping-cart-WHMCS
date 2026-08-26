# Shopping Cart - WHMCS Payment Modules

Easy to install payment modules for the [WHMCS](https://www.whmcs.com/) billing cart.
**WHMCS 8.x** includes Smart Screens v2 hosted checkout.

The package is an **untested beta** (no production WHMCS license was available when it was written). Test on a non-production system before going live.

## Downloads by WHMCS version

### WHMCS v8.x (current — third-party gateway API 1.1)

* **Smart Screens v2** — gateway hosted checkout
  - [Download](./WHMCS_v8.x/whmcs_ss2_module.zip)
  - Source: [./WHMCS_v8.x/src/ss2/](./WHMCS_v8.x/src/ss2/)
  - Docs: [package README](./WHMCS_v8.x/README.md) · [INSTALL_SS2.txt](./WHMCS_v8.x/INSTALL_SS2.txt) · [module README](./WHMCS_v8.x/src/ss2/README.md)

Package overview: [./WHMCS_v8.x/README.md](./WHMCS_v8.x/README.md)

## Installation

For complete instructions, open the README inside the zip (or the linked docs above).

### WHMCS 8.x — Smart Screens v2

1. Download [whmcs_ss2_module.zip](./WHMCS_v8.x/whmcs_ss2_module.zip).
2. Copy `modules/gateways/plugnpayss2.php` and `modules/gateways/callback/plugnpayss2.php` into the matching paths under the WHMCS root.
3. Configuration → System Settings → Payment Gateways → activate **PlugnPay Smart Screens v2**.
4. Enter the Gateway Account (PlugnPay username).

- Quick install: [WHMCS_v8.x/INSTALL_SS2.txt](./WHMCS_v8.x/INSTALL_SS2.txt)

## Usage

### Smart Screens v2

* Hosted checkout at `https://pay1.plugnpay.com/pay/`.
* Supports Credit Card and other payment options configured on your PlugnPay account.
* WHMCS does **not** collect sensitive payment data at checkout.
* Customer is redirected to PlugnPay, then returned to WHMCS after approval or decline.
* HTTPS on your store is strongly recommended (return URL / callback).
* Refund and subscription cancel are not implemented in the module; use PlugnPay Merchant Admin.
* Untested beta — validate on a non-production WHMCS 8.x system before production.

## Repository layout

```
shopping-cart-WHMCS/
  README.md
  .gitignore
  WHMCS_v8.x/                 # current (8.x) — Smart Screens v2
    README.md
    INSTALL_SS2.txt
    whmcs_ss2_module.zip
    src/ss2/
```

## Support

Provided AS IS. See [PlugnPay docs](https://docs.plugnpay.com/) and the module README for integration details.
