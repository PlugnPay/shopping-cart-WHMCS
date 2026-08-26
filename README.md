# Shopping Cart - WHMCS Payment Modules

Easy to install payment modules for the [WHMCS](https://www.whmcs.com/) billing cart.
**WHMCS 9.0** includes Remote API (onsite) and Smart Screens v2 (hosted) checkout. **WHMCS 8.x** keeps the earlier sample-based Smart Screens v2 beta.

## Downloads by WHMCS version

### WHMCS v9.0.x (current — target 9.0.7)

* **Remote API** — onsite card collection via `pnpremote.cgi`
  - [Download](./WHMCS_v9.0.x/whmcs_9.0.7_api_module.zip)
  - Source: [./WHMCS_v9.0.x/src/api/](./WHMCS_v9.0.x/src/api/)
  - Docs: [package README](./WHMCS_v9.0.x/README.md) · [INSTALL.txt](./WHMCS_v9.0.x/INSTALL.txt) · [module README](./WHMCS_v9.0.x/src/api/README.md)
* **Smart Screens v2** — gateway hosted checkout
  - [Download](./WHMCS_v9.0.x/whmcs_9.0.7_ss2_module.zip)
  - Source: [./WHMCS_v9.0.x/src/ss2/](./WHMCS_v9.0.x/src/ss2/)
  - Docs: [package README](./WHMCS_v9.0.x/README.md) · [INSTALL_SS2.txt](./WHMCS_v9.0.x/INSTALL_SS2.txt) · [module README](./WHMCS_v9.0.x/src/ss2/README.md)

Package overview: [./WHMCS_v9.0.x/README.md](./WHMCS_v9.0.x/README.md)

### WHMCS v8.x (legacy)

* **Smart Screens v2** — untested beta converted from the WHMCS sample gateway
  - [Download](./WHMCS_v8.x/whmcs_ss2_module.zip)
  - Source: [./WHMCS_v8.x/src/ss2/](./WHMCS_v8.x/src/ss2/)
  - Docs: [package README](./WHMCS_v8.x/README.md) · [INSTALL_SS2.txt](./WHMCS_v8.x/INSTALL_SS2.txt) · [module README](./WHMCS_v8.x/src/ss2/README.md)

Package overview: [./WHMCS_v8.x/README.md](./WHMCS_v8.x/README.md)

## Installation

For complete instructions, open the README inside the zip (or the linked docs above).

### WHMCS 9.0.x — Remote API

1. Download [whmcs_9.0.7_api_module.zip](./WHMCS_v9.0.x/whmcs_9.0.7_api_module.zip).
2. Unzip into the WHMCS **root** so `modules/gateways/` merges in place.
3. Configuration → System Settings → Payment Gateways → activate **PlugnPay Remote API**.
4. Enter Gateway Account, Remote Client Password, and Authorization Type.

- Quick install: [WHMCS_v9.0.x/INSTALL.txt](./WHMCS_v9.0.x/INSTALL.txt)

### WHMCS 9.0.x — Smart Screens v2

1. Download [whmcs_9.0.7_ss2_module.zip](./WHMCS_v9.0.x/whmcs_9.0.7_ss2_module.zip).
2. Unzip into the WHMCS **root** so `modules/gateways/` merges in place.
3. Configuration → System Settings → Payment Gateways → activate **PlugnPay Smart Screens v2**.
4. Enter the Gateway Account (PlugnPay username).

- Quick install: [WHMCS_v9.0.x/INSTALL_SS2.txt](./WHMCS_v9.0.x/INSTALL_SS2.txt)

### WHMCS 8.x — Smart Screens v2 (legacy)

1. Download [whmcs_ss2_module.zip](./WHMCS_v8.x/whmcs_ss2_module.zip).
2. Copy `modules/gateways/plugnpayss2.php` and `modules/gateways/callback/plugnpayss2.php` into the matching paths under the WHMCS root.
3. Configuration → System Settings → Payment Gateways → activate the module.
4. Enter the Gateway Account (PlugnPay username).

- Quick install: [WHMCS_v8.x/INSTALL_SS2.txt](./WHMCS_v8.x/INSTALL_SS2.txt)

You may install both 9.0.x modules; enable only the payment method(s) you need.

## Usage

### Remote API (WHMCS 9.0.x)

* WHMCS collects card data on the invoice (increases PCI scope).
* Authorization is submitted to PlugnPay via cURL (`pnpremote.cgi`).
* Client Area HTTPS and PHP cURL are required.
* Authorization Type: `authonly` or `authpostauth`.
* Capture / void / refund are done in PlugnPay Merchant Admin (not from WHMCS).
* On successful capture the invoice is marked paid in WHMCS so services can provision.
* For WHMCS 9.0.x: payment module **v1.0.2**; target **9.0.7**.
* No public demo / test publisher — use merchant-supplied credentials.

### Smart Screens v2 (WHMCS 9.0.x)

* Hosted checkout at `https://pay1.plugnpay.com/pay/`.
* Supports Credit Card and other payment options configured on your PlugnPay account.
* WHMCS does **not** collect sensitive payment data at checkout.
* Customer is redirected to PlugnPay, then returned to the invoice after approval or decline.
* HTTPS on your Client Area is required (return URL / callback).
* Transactions are authorization-only (`pb_post_auth=no`); capture / void / refund in PlugnPay Merchant Admin.
* On successful authorization the invoice is marked paid in WHMCS so services can provision.
* For WHMCS 9.0.x: payment module **v1.0.2**; target **9.0.7**.
* No public demo / test publisher — use merchant-supplied credentials.

### Smart Screens v2 (WHMCS 8.x legacy)

* Same hosted checkout idea; sample-based **untested beta**. Prefer the 9.0.x package when you are on WHMCS 9.
* This 8.x sample does **not** include the 9.0.x PCI input filtering and log-masking controls.

## Repository layout

```
shopping-cart-WHMCS/
  README.md
  .gitignore
  WHMCS_v9.0.x/               # current (9.0.7) — Remote API + Smart Screens v2
    README.md
    INSTALL.txt
    INSTALL_SS2.txt
    whmcs_9.0.7_api_module.zip
    whmcs_9.0.7_ss2_module.zip
    src/api/
    src/ss2/
    tests/
  WHMCS_v8.x/                 # legacy (8.x) — Smart Screens v2 beta
    README.md
    INSTALL_SS2.txt
    whmcs_ss2_module.zip
    src/ss2/
```

## Support

Provided AS IS. See [PlugnPay docs](https://docs.plugnpay.com/) and the module README for integration details.
