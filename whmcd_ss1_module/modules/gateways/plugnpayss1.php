<?php
/**
 * WHMCS Sample Payment Gateway Module
 *
 * Payment Gateway modules allow you to integrate payment solutions with the
 * WHMCS platform.
 *
 * This sample file demonstrates how a payment gateway module for WHMCS should
 * be structured and all supported functionality it can contain.
 *
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For this
 * example file, the filename is 'plugnpayss1' and therefore all functions
 * begin 'plugnpayss1_'.
 *
 * If your module or third party API does not support a given function, you
 * should not define that function within your module. Only the _config
 * function is required.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

if (!defined('WHMCS')) {
  die('This file cannot be accessed directly');
}

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see https://developers.whmcs.com/payment-gateways/meta-data-params/
 * @return array
 */
function plugnpayss1_MetaData()
{
  return array(
    'DisplayName' => 'PlugnPay SSv1 Payment Gateway Module',
    'APIVersion' => '1.1', // Use API Version 1.1
    'DisableLocalCreditCardInput' => true,
    'TokenisedStorage' => false,
  );
}

/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each field type and their possible configuration parameters are
 * provided in the sample function below.
 *
 * @return array
 */
function plugnpayss1_config()
{
  return array(
    // the friendly display name for a payment gateway should be
    // defined here for backwards compatibility
    'FriendlyName' => array(
      'Type' => 'System',
      'Value' => 'PlugnPay Smart Screens v1 Payment Gateway Module',
    ),
    // a text field type allows for single line text input
    'accountId' => array(
      'FriendlyName' => 'Gateway Account',
      'Type' => 'text',
      'Size' => '25',
      'Default' => '',
      'Description' => 'Enter your PlugnPay username here',
    ),
  );
}

/**
 * Payment link.
 *
 * Required by third party payment gateway modules only.
 *
 * Defines the HTML output displayed on an invoice. Typically consists of an
 * HTML form that will take the user to the payment gateway endpoint.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/third-party-gateway/
 *
 * @return string
 */
function plugnpayss1_link($params)
{
  // Gateway Configuration Parameters
  $accountId = $params['accountId'];

  // Invoice Parameters
  $invoiceId    = $params['invoiceid'];
  $description  = $params['description'];
  $amount       = $params['amount'];
  $currencyCode = $params['currency'];

  // Client Parameters
  $firstname = $params['clientdetails']['firstname'];
  $lastname  = $params['clientdetails']['lastname'];
  $email     = $params['clientdetails']['email'];
  $address1  = $params['clientdetails']['address1'];
  $address2  = $params['clientdetails']['address2'];
  $city      = $params['clientdetails']['city'];
  $state     = $params['clientdetails']['state'];
  $postcode  = $params['clientdetails']['postcode'];
  $country   = $params['clientdetails']['country'];
  $phone     = $params['clientdetails']['phonenumber'];

  // System Parameters
  $companyName  = $params['companyname'];
  $systemUrl    = $params['systemurl'];
  $returnUrl    = $params['returnurl'];
  $langPayNow   = $params['langpaynow'];
  $moduleDisplayName = $params['name'];
  $moduleName   = $params['paymentmethod'];
  $whmcsVersion = $params['whmcsVersion'];

  $url = 'https://pay1.plugnpay.com/payment/pay.cgi';

  $postfields = array(
    'publisher-name' => $accountId,
    'username'       => $username,
    'acct_code'      => $invoiceId,
    'x_description'  => $description,
    'card-amount'    => $amount,
    'currency'       => $currencyCode,
    'card-name'      => $firstname . ' ' .$lastname,
    'card-address1'  => $address1,
    'card-address2'  => $address2,
    'card-city'      => $city,
    'card-state'     => $state,
    'card-zip'       => $postcode,
    'card-country'   => $country,
    'phone'          => $phone,
    'email'          => $email,
    'transitiontype' => 'hidden',
    'success-link'   => $systemUrl . '/modules/gateways/callback/' . $moduleName . '.php',
  );

  $htmlOutput = '<form method="post" action="' . $url . '">';
  foreach ($postfields as $k => $v) {
    $htmlOutput .= '<input type="hidden" name="' . $k . '" value="' . urlencode($v) . '" />';
  }
  $htmlOutput .= '<input type="submit" value="' . $langPayNow . '" />';
  $htmlOutput .= '</form>';

  return $htmlOutput;
}

/**
 * Refund transaction.
 *
 * Called when a refund is requested for a previously successful transaction.
 *
 * @param array $params Payment Gateway Module Parameters
 * @see https://developers.whmcs.com/payment-gateways/refunds/
 * @return array Transaction response status
 */
function plugnpayss1_refund($params)
{
  return array(
    'status'  => 'error',
    'rawdata' => 'This feature is not implimented.',
    'transid' => $params['transid'],
    'fees'    => $params['amount'],
  );
}

/**
 * Cancel subscription.
 *
 * If the payment gateway creates subscriptions and stores the subscription
 * ID in tblhosting.subscriptionid, this function is called upon cancellation
 * or request by an admin user.
 *
 * @param array $params Payment Gateway Module Parameters
 * @see https://developers.whmcs.com/payment-gateways/subscription-management/
 * @return array Transaction response status
 */
function plugnpayss1_cancelSubscription($params)
{
  return array(
    'status'  => 'error',
    'rawdata' => 'This feature is not implimented.',
  );
}
