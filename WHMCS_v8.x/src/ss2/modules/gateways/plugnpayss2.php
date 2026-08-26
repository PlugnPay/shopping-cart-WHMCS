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
 * example file, the filename is 'plugnpayss2' and therefore all functions
 * begin 'plugnpayss2_'.
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
function plugnpayss2_MetaData()
{
  return array(
    'DisplayName' => 'PlugnPay SSv2 Payment Gateway Module',
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
function plugnpayss2_config()
{
  return array(
    // the friendly display name for a payment gateway should be
    // defined here for backwards compatibility
    'FriendlyName' => array(
      'Type' => 'System',
      'Value' => 'PlugnPay Smart Screens v2 Payment Gateway Module',
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
function plugnpayss2_link($params)
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

  $url = 'https://pay1.plugnpay.com/pay/';

  $postfields = array(
    'pt_gateway_account'       => $accountId,
    'pm_username'              => $username,
    'pt_account_code_1'        => $invoiceId,
    'pt_custom_name_1'         => 'x_description',
    'pt_custom_value_1'        => $description,
    'pt_transaction_amount'    => $amount,
    'pt_currency'              => $currencyCode,
    'pt_billing_name'          => $firstname . ' ' .$lastname,
    'pt_billing_address_1'     => $address1,
    'pt_billing_address_2'     => $address2,
    'pt_billing_city'          => $city,
    'pt_billing_state'         => $state,
    'pt_billing_postal_code'   => $postcode,
    'pt_billing_country'       => $country,
    'pt_billing_phone_number'  => $phone,
    'pt_billing_email_address' => $email,
    'pb_transition_type'       => 'hidden',
    'pb_success_url'           => $systemUrl . '/modules/gateways/callback/' . $moduleName . '.php',
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
function plugnpayss2_refund($params)
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
function plugnpayss2_cancelSubscription($params)
{
  return array(
    'status'  => 'error',
    'rawdata' => 'This feature is not implimented.',
  );
}
