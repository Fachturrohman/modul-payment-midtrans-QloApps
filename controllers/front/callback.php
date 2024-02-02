<?php
/**
* 2010-2021 Webkul.
*
* NOTICE OF LICENSE
*
* All right is reserved,
* Please go through LICENSE.txt file inside our module
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade this module to newer
* versions in the future. If you wish to customize this module for your
* needs please refer to CustomizationPolicy.txt file inside our module for more information.
*
* @author Webkul IN
* @copyright 2010-2021 Webkul IN
* @license LICENSE.txt
*/

class QloMidtransCommerceCallbackModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $headers = getallheaders();
        $headers = array_change_key_case($headers, CASE_UPPER);

        $json = Tools::file_get_contents('php://input');

        $payload = array();

        if ($headers) {
            $payload['transmission_id'] = $headers['MIDTRANS-TRANSMISSION-ID'];
            $payload['cert_url'] = $headers['MIDTRANS-CERT-URL'];
            $payload['transmission_time'] = $headers['MIDTRANS-TRANSMISSION-TIME'];
            $payload['auth_algo'] = $headers['MIDTRANS-AUTH-ALGO'];
            $payload['transmission_sig'] = $headers['MIDTRANS-TRANSMISSION-SIG'];
            $payload['webhook_id'] = Configuration::get('WK_MIDTRANS_COMMERCE_LIVE_WEBHOOK_ID');
        }

        if ($json) {
            $payload['webhook_event'] = Tools::jsonDecode($json, true);
        }

        if ($payload) {
            WkMidtransCommerceHelper::logMsg('webhook', 'Webhook initiated...', true);
            WkMidtransCommerceHelper::logMsg('webhook', 'Environment: '. Configuration::get('WK_MIDTRANS_COMMERCE_PAYMENT_MODE'));
            WkMidtransCommerceHelper::logMsg('webhook', 'Webhook payload data: ');
            WkMidtransCommerceHelper::logMsg('webhook', Tools::jsonEncode($payload));
            WkMidtransCommerceHelper::logMsg('webhook', 'Validating webhook signature...');

            $validateSig = WkMidtransCommerceHelper::validateWebhookSig($payload);

            WkMidtransCommerceHelper::logMsg('webhook', 'Webhook respose data: ');
            WkMidtransCommerceHelper::logMsg('webhook', Tools::jsonEncode($validateSig));

            if (isset($validateSig['verification_status'])
                && $validateSig['verification_status'] == 'SUCCESS'
            ) {
                $eventData = Tools::jsonDecode($json, true);
                $objWebhook = new WkMidtransCommerceWebhook();
                switch ($eventData['event_type']) {
                    case 'CHECKOUT.ORDER.APPROVED':
                        $objWebhook->orderCompleted($eventData);
                        break;
                    case 'CHECKOUT.ORDER.COMPLETED':
                        $objWebhook->orderCompleted($eventData);
                        break;

                    case 'PAYMENT.CAPTURE.COMPLETED':
                        $objWebhook->captureCompleted($eventData);
                        break;

                    case 'PAYMENT.CAPTURE.DENIED':
                        $objWebhook->captureDenied($eventData);
                        break;

                    case 'PAYMENT.CAPTURE.PENDING':
                        $objWebhook->capturePending($eventData);
                        break;

                    case 'PAYMENT.CAPTURE.REFUNDED':
                        $objWebhook->captureRefunded($eventData);
                        break;

                    case 'PAYMENT.CAPTURE.REVERSED':
                        $objWebhook->captureRefunded($eventData);
                        break;
                    default:
                        # code...
                        break;
                }
            }
        }

        header("HTTP/1.1 200 OK");
        die;
    }
}
