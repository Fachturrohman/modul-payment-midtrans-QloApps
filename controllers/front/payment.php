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

class QloMidtransCommercePaymentModuleFrontController extends ModuleFrontController
{
    public function init()
    {
        parent::init();

        $cart = $this->context->cart;

        // cehck cart details
        if ($cart->id_customer == 0
            || !$this->module->active
        ) {
            Tools::redirect($this->context->link->getPageLink('order-opc', true, null));
        }

        // Check that this payment option is still available in case the customer
        // changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'qlomidtranscommerce') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'payment'));
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect($this->context->link->getPageLink('order-opc', true, null));
        }
    }

    public function initContent()
    {
        parent::initContent();

        //Get Token untuk generate snap
        // create order
        $orderDetails = $this->getOrderDetails();
        WkMidtransCommerceHelper::logMsg('payment', Tools::jsonEncode($orderDetails));

        $wkClientID = trim(Configuration::get('WK_MIDTRANS_COMMERCE_CLIENT_ID'));
        $wkClientSecret = trim(Configuration::get('WK_MIDTRANS_COMMERCE_SERVER_KEY'));
        $wkEnvironment = Configuration::get('WK_MIDTRANS_COMMERCE_PAYMENT_MODE');

        $base_url = ($wkEnvironment == 'sandbox') ? MidtransHelper::WK_MIDTRANS_SANDBOX_URL : MidtransHelper::WK_MIDTRANS_LIVE_URL;

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $base_url . "/" . MidtransHelper::WK_MIDTRANS_ACCESS_TOKEN_URI,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => Tools::jsonEncode($orderDetails),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                "authorization: Basic " . base64_encode($wkClientSecret . ":"),
            ),
        ));

        $response = Tools::jsonDecode(curl_exec($curl), true);
        $err = curl_error($curl);

        curl_close($curl);

        // $this->context->smarty->assign(array(
        //     'token' => $response['token'],
        //     'url' => $response['redirect_url'],
        // ));

        // $this->setTemplate('payment_execution.tpl');

        $cart = $this->context->cart;

        if ($cart->is_advance_payment) {
			$total = $cart->getOrderTotal(true, Cart::ADVANCE_PAYMENT);
        } else {
            $total = $cart->getOrderTotal(true, Cart::BOTH);
		}
        
        $this->context->smarty->assign(array(
            'nbProducts' => $cart->nbProducts(),
            'cust_currency' => $cart->id_currency,
            'total' => $total,
            'token' => $response['token'],
            'url' => $response['redirect_url'],
            'done_payment' => 0
        ));

        $this->setTemplate('payment_exec.tpl');
    }

    // Save order data
    private function saveOrderData($orderData)
    {
        if ($orderData) {
            $midtrans_transaction_id = Tools::getValue('midtrans_transaction_id');
            $midtrans_order_id = Tools::getValue('midtrans_order_id');

            //cek payment status
            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.sandbox.midtrans.com/v2/'.$midtrans_order_id.'/status',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Basic U0ItTWlkLXNlcnZlci04Ql9DWEx0VTJUSUVjOHYwMjNHVnNYRmw6'
            ),
            ));

            $response = json_decode(curl_exec($curl), true);

            curl_close($curl);

            
            $cart = $this->context->cart;
            $orderStatus = Configuration::get('PS_OS_AWAITING_PAYMENT');
            $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
            if ($cart->is_advance_payment) {
                $total = (float)$cart->getOrderTotal(true, Cart::ADVANCE_PAYMENT);
            } else {
                $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
            }
            $currency = $this->context->currency;
            $customer = new Customer($cart->id_customer);
            $purchase = $orderData['transaction_details'];

            $payment_total = $purchase['gross_amount'];
            $payment_status = $response['transaction_status'];

            $cart = $this->context->cart;
            $currency = Currency::getCurrency((int) $cart->id_currency);

            // total order amount
            if ($cart->is_advance_payment) {
                $cartTotalAmountTI = $cart->getOrderTotal(true, Cart::ADVANCE_PAYMENT);
            } else {
                $cartTotalAmountTI = $cart->getOrderTotal(true, Cart::BOTH);
            }

            $orderObj = new WkMidtransCommerceOrder();
            $orderObj->order_reference = '';
            $orderObj->id_cart = (int)$cart->id;
            $orderObj->id_currency = (int)$cart->id_currency;
            $orderObj->environment = Configuration::get('WK_MIDTRANS_COMMERCE_PAYMENT_MODE');
            $orderObj->id_customer = (int)$cart->id_customer;
            $orderObj->order_total = (float)$cartTotalAmountTI;
            $orderObj->checkout_currency = $currency['iso_code'];

            // Midtrans Returned Data
            $orderObj->pp_paid_total = (float)$payment_total;
            $orderObj->pp_paid_currency = $currency['iso_code'];
            $orderObj->pp_reference_id = $purchase['reference_id'];
            $orderObj->pp_order_id = $midtrans_order_id;
            $orderObj->pp_transaction_id = $midtrans_transaction_id;
            $orderObj->pp_payment_status = $payment_status;
            // $orderObj->response = Tools::jsonEncode($orderData);
            $orderObj->response = $response;
            $orderObj->order_date = date('Y-m-d H:i:s');
            $orderObj->save();

            $this->module->validateOrder(
                $cart->id,
                $orderStatus,
                $total,
                $this->module->l('Midtrans Checkout', 'payment'),
                null,
                null,
                (int)$currency->id,
                false,
                $customer->secure_key
            );

            $order = new Order($this->module->currentOrder);
            $order_state = new OrderState(3);
            $current_order_state = $order->getCurrentOrderState();
            $history = new OrderHistory();
            $history->id_order = $order->id;
            $use_existings_payment = false;
            if (!$order->hasInvoice()) {
                $use_existings_payment = true;
            }
            $history->changeIdOrderState((int)$order_state->id, $order, $use_existings_payment);
            $history->addWithemail(true, null);

            Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key.'');
        }

        return false;
    }

    // Create order data to send to Midtrans
    private function getOrderDetails()
    {
        //versi Midtrans
        $orderData = array();
        $cart = $this->context->cart;
        $customer = new Customer((int)$cart->id_customer);

        $ppHelper = new WkMidtransCommerceHelper();
        $bilAddr = $ppHelper->getSimpleAddress(
            (int)$cart->id_customer,
            (int)$cart->id_address_invoice
        );

        $timestamp = time().rand(100, 999);
        $currency = Currency::getCurrency((int) $cart->id_currency);

        $discountTI = $cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS);
        $discountTE = $cart->getOrderTotal(false, Cart::ONLY_DISCOUNTS);

        // total cart amount
        // items totals will be calculated after removing discount from the total cart as additional facility is also considered as an item
        if ($cart->is_advance_payment) {
            $cartTotalAmountTI = $cart->getOrderTotal(true, Cart::ADVANCE_PAYMENT);
            $cartTotalAmountTE = $cart->getOrderTotal(false, Cart::ADVANCE_PAYMENT);

            $itemTotalAmountTI = $cartTotalAmountTI + $discountTI;
            $itemTotalAmountTE = $cartTotalAmountTE + $discountTE;
        } else {
            $cartTotalAmountTI = $cart->getOrderTotal(true, Cart::BOTH);
            $cartTotalAmountTE = $cart->getOrderTotal(false, Cart::BOTH);

            $itemTotalAmountTI = $cartTotalAmountTI + $discountTI;
            $itemTotalAmountTE = $cartTotalAmountTE + $discountTE;
        }

        $orderData['transaction_details'] = array(
            'order_id' => 'MidPay-'.$timestamp,
            'gross_amount' => Tools::ps_round($cartTotalAmountTI, 2)
        );

        $orderData['credit_card'] = array(
            'secure' => true
        );

        $orderData['customer_details'] = array(
            'first_name' => $customer->firstname,
            'last_name' => $customer->lastname,
            'email' => $customer->email,
            'phone' => $bilAddr['phone_mobile']
        );

        return $orderData;
    }

    /**
     * getPriceDecimalPrecision
     * @return void
     */
    private function getPriceDecimalPrecision()
    {
        return _PS_PRICE_COMPUTE_PRECISION_;
    }

    public function postProcess()
    {
        if (Tools::getValue('order_midtrans_form')
        ) {
		    $orderDetails = $this->getOrderDetails();
            $this->saveOrderData($orderDetails);
        }
    }
}
