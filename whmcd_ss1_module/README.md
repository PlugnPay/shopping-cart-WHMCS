# PlugnPay Smart Screens v1 Payment Gateway Module For WHMCS #

## Summary ##

PlugnPay's Smart Screens v1 Payment Gateway modules allow you to integrate
our payment solution with the WHMCS platform.

There are two types of gateway modules:

* Third Party Gateways - these are payment solutions where checkout occurs
on a remote website, usually hosted by the payment gateway themselves.

* Merchant Gateways - these are payment solutions where credit card details
are collected - usually within the WHMCS application, though more and more
often this will be done remotely, typically via an iframe, with a page hosted
remotely by the payment gateway enabling tokenised storage.

The files here demonstrate how we suggest a Third Party Payment Gateway
module work woth WHMCS be structured and implemented.

For more information, please refer to the documentation at:
https://developers.whmcs.com/payment-gateways/

## Recommended Module Content ##

The recommended structure of a third party gateway module is as follows.

```
 modules/gateways/
  |- callback/plugnpayss1.php
  |  plugnpayss1.php
```

## Minimum Requirements ##

For the latest WHMCS minimum system requirements, please refer to
https://docs.whmcs.com/System_Requirements

We recommend your module follows the same minimum requirements wherever
possible.

## Useful Resources
* [Developer Resources](https://developers.whmcs.com/)
* [Hook Documentation](https://developers.whmcs.com/hooks/)
* [API Documentation](https://developers.whmcs.com/api/)

[WHMCS Limited](https://www.whmcs.com)


## History ##

12/04/2020
- Initial beta release of scriptc to support Smart Screens v1 in WHMCS.
- Due to not having a license for WHMCS, this code was not tested.
- The code is NOT considered production ready; so consider an untested beta.
- Some minor coding may be necessary to make this module fully functional.
- Please fully test the code on a non-production system, before going live with it.
- PlugnPay cannot be held accountable for issues due to using tihs beta code - so use at your own risk.


