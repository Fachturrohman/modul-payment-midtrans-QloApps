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

class AdminMidtransCommerceTransactionController extends ModuleAdminController
{
    public function __construct()
    {
        $this->identifier = 'id_midtrans_commerce_order';
        parent::__construct();

        $this->bootstrap = true;
        $this->list_no_link = true;

        $this->table = 'wk_midtrans_commerce_order';
        $this->className = 'WKMidtransCommerceOrder';

        $this->_select = 'a.*, CONCAT(c.firstname, \' \', c.lastname) as customer_name, c.email';
        $this->_join = ' LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON (c.id_customer = a.id_customer)';
        $this->_where = ' AND a.`id_cart` != 0';
        $this->_orderBy = 'id_midtrans_commerce_order';
        $this->_defaultOrderWay = 'DESC';


        $this->toolbar_title = $this->l('Midtrans Transactions');

        $this->fields_list = array(
            'order_reference' => array(
                'title' => $this->l('Order Reference'),
                'align' => 'center',
                'havingFilter' => true,
                'hint' => $this->l('Order reference in QloApps of the Midtrans transaction'),
            ),
            'pp_transaction_id' => array(
                'title' => $this->l('Midtrans Transaction ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
                'havingFilter' => true,
                'hint' => $this->l('Id of the Midtrans transaction'),
            ),
            'pp_paid_total' => array(
                'title' => $this->l('Transaction Total'),
                'align' => 'center',
                'havingFilter' => true,
                'callback' => 'setCurrency',
                'hint' => $this->l('Total amount paid in the Midtrans transaction'),
            ),
            'customer_name' => array(
                'title' => $this->l('Customer'),
                'align' => 'center',
                'havingFilter' => true,
                'align' => 'center',
                'callback' => 'getCustomerInfo',
                'havingFilter' => true,
                'filter_key' => 'customer_name',
                'hint' => $this->l('Customer in the QloApps who did Midtrans transaction.'),
            ),
            'pp_payment_status' => array(
                'title' => $this->l('Status'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
                'hint' => $this->l('Payment Status'),
                'orderBy' => false,
                'hint' => $this->l('Current status of the Midtrans transaction.'),
            ),
            'order_date' => array(
                'title' => $this->l('Order Date'),
                'type' => 'datetime',
                'align' => 'center',
                'hint' => $this->l('Date of order creation.'),
            )
        );
    }

    public function getCustomerInfo($customerName, $row)
    {
        return '<a href="'.$this->context->link->getAdminLink('AdminCustomers').'&id_customer='.$row['id_customer'].'&viewcustomer">'.$customerName.'<br>'.'('.$row['email'].')</a>';
    }

    public function setCurrency($val, $row)
    {
        $objCart = new Cart($row['id_cart']);
        $currency = new Currency($objCart->id_currency);

        return Tools::displayPrice($val, $currency);
    }

    public function initPageHeaderToolbar()
    {
        if ($this->display == 'edit' || $this->display == 'add' || $this->display == 'view') {
            $this->page_header_toolbar_btn['back_to_list'] = array(
                'href' => Context::getContext()->link->getAdminLink(
                    'AdminMidtransCommerceTransaction',
                    true
                ),
                'desc' => $this->l('Back to list'),
                'icon' => 'process-icon-back'
            );
        }
        parent::initPageHeaderToolbar();
    }

    public function renderList()
    {
        $this->addRowAction('view');
        return parent::renderList();
    }

    public function initToolbar()
    {
        parent::initToolbar();
        unset($this->toolbar_btn['new']);
    }

    public function renderView()
    {
        $idTrans = (int)Tools::getValue('id_midtrans_commerce_order');
        if ($idTrans > 0) {
            $smartyVars = array();
            $refundData = array();
            $totalRefunded = 0;
            $transactionData = WKMidtransCommerceOrder::getTransactionDetails((int)$idTrans);
            $orderCurrency = new Currency((int)$transactionData['id_currency']);
            $refundData = WkMidtransCommerceRefund::getRefundListByTransID((int)$idTrans);
            $totalRefundedFormatted = WkMidtransCommerceRefund::getTotalRefundedAmount((int)$idTrans, true);

            $totalRefunded = WkMidtransCommerceRefund::getTotalRefundedAmount((int)$idTrans, false);
            $remainingRefund = (float)($transactionData['pp_paid_total'] - $totalRefunded);
            $response = Tools::jsonDecode($transactionData['response'], true);

            // Buyer making a payment in a different currency (ex: EUR) which is different from the default currency of merchant (Ex: USD), In all those cross currency cases, After Capture, transaction will fall into Pending state and will require merchant to manually go to his Midtrans account and accept the payment.
            $objPPOrder = new WKMidtransCommerceOrder();
            if (isset($response['data']['purchase_units'][0]['payments']['captures'][0]['status_details']['reason'])
                && ($transactionData['pp_payment_status'] == 'PENDING' || $transactionData['pp_payment_status'] == 'DENIED')
                && isset($response['data']['purchase_units'][0]['payments']['captures'][0]['status_details']['reason'])
            ) {
                if (isset($objPPOrder->ppStatusDetail[$response['data']['purchase_units'][0]['payments']['captures'][0]['status_details']['reason']])) {
                    $smartyVars['ppstatusDetailMsg'] = $objPPOrder->ppStatusDetail[$response['data']['purchase_units'][0]['payments']['captures'][0]['status_details']['reason']];
                }
            }

            $smartyVars['transaction_data'] = $transactionData;
            $smartyVars['refund_data'] = $refundData;
            $smartyVars['refunded_amount'] = $totalRefundedFormatted;
            $smartyVars['remaining_refund'] = $remainingRefund;
            $smartyVars['remaining_refund_format'] = Tools::displayPrice($remainingRefund, $orderCurrency);
            $smartyVars['currency'] = $orderCurrency;
            $smartyVars['WK_MIDTRANS_COMMERCE_REFUND_TYPE_FULL'] = WkMidtransCommerceRefund::WK_MIDTRANS_COMMERCE_REFUND_TYPE_FULL;
            $smartyVars['WK_MIDTRANS_COMMERCE_REFUND_TYPE_PARTIAL'] = WkMidtransCommerceRefund::WK_MIDTRANS_COMMERCE_REFUND_TYPE_PARTIAL;

            $this->context->smarty->assign($smartyVars);
        }
        $this->base_tpl_view = 'view.tpl';
        return parent::renderView();
    }

    public function postProcess()
    {

        if (Tools::getValue('refund_midtrans_form')
            && (Tools::getValue('token')) == Tools::getAdminTokenLite('AdminMidtransCommerceTransaction')
        ) {
            $idTrans = (int)Tools::getValue('id_midtrans_commerce_order');
            $refundAmt = (float)Tools::getValue('refund_amount');
            $refundReason = Tools::getValue('refund_reason');
            $refundType = (int)Tools::getValue('refund_type');

            if (!$refundType) {
                $this->errors[] = $this->l('Invalid refund type.');
            }
            if (!Validate::isPrice($refundAmt)) {
                $this->errors[] = $this->l('Invalid refund amount.');
            }
            if (!Validate::isMessage($refundReason)) {
                $this->errors[] = $this->l('Enter valid refund reason (only alphanumeric allowed).');
            }

            if ((!count($this->errors))) {
                $transactionData = WKMidtransCommerceOrder::getTransactionDetails((int)$idTrans);
                if ($transactionData) {
                    $totalRefunded = WkMidtransCommerceRefund::getTotalRefundedAmount((int)$idTrans, false);
                    if ($remainingRefund = (float)($transactionData['pp_paid_total'] - $totalRefunded)) {
                        // if refund type is partial then check the amount to be refunded
                        if (($refundType == WkMidtransCommerceRefund::WK_MIDTRANS_COMMERCE_REFUND_TYPE_PARTIAL)
                            && ($refundAmt > $remainingRefund)
                        ) {
                            $orderCurrency = new Currency((int)$transactionData['id_currency']);
                            $this->errors[] = $this->l('Invalid refund amount. Max. available amount for refund is').' '.Tools::displayPrice($remainingRefund, $orderCurrency);
                        }

                        // if no errors the proceed for the refund
                        if ((!count($this->errors))) {
                            if ($refundType == WkMidtransCommerceRefund::WK_MIDTRANS_COMMERCE_REFUND_TYPE_FULL) {
                                $refundAmt = (float)($transactionData['pp_paid_total'] - $totalRefunded);
                            }
                            if ($transactionData['pp_paid_total'] == $refundAmt) {
                                $refundType = WkMidtransCommerceRefund::WK_MIDTRANS_COMMERCE_REFUND_TYPE_FULL;
                            }
                            if ($refundAmt) {
                                $postData = array();
                                $postData['amount'] = array(
                                    'currency_code' => $transactionData['pp_paid_currency'],
                                    'value' => $refundAmt
                                );
                                $postData['transaction_id'] = $transactionData['pp_transaction_id'];
                                $postData['refund_reason'] = $refundReason;
                                $postData['auth_assertion'] = WkMidtransCommerceHelper::midtransAuthAssertion();

                                WkMidtransCommerceHelper::logMsg('refund', 'Refund initiated...', true);
                                WkMidtransCommerceHelper::logMsg('refund', 'Environment: '. Configuration::get('MP_MIDTRANS_PAYMENT_MODE'));
                                WkMidtransCommerceHelper::logMsg('refund', 'Order Ref: '. $transactionData['order_reference']);
                                WkMidtransCommerceHelper::logMsg('refund', 'Customer ID: '. $transactionData['id_customer']);
                                WkMidtransCommerceHelper::logMsg('refund', 'Midtrans Transaction ID: '. $transactionData['pp_transaction_id']);
                                WkMidtransCommerceHelper::logMsg('refund', 'Midtrans Order ID: '. $transactionData['pp_order_id']);
                                WkMidtransCommerceHelper::logMsg('refund', 'Refund request data: ');
                                WkMidtransCommerceHelper::logMsg('refund', Tools::jsonEncode($postData));

                                $objPPCommerce = new MidtransCommerce();
                                $refundData = $objPPCommerce->orders->refund($postData);

                                // check if refund id is there
                                if (isset($refundData['data']['status']) && isset($refundData['data']['id'])) {
                                    $refundID = $refundData['data']['id'];

                                    WkMidtransCommerceHelper::logMsg('refund', 'Refund success: ', true);
                                    WkMidtransCommerceHelper::logMsg('refund', 'Midtrans Refund Id: '. $refundID);
                                    WkMidtransCommerceHelper::logMsg('refund', 'Refund reponse data: ');
                                    WkMidtransCommerceHelper::logMsg('refund', Tools::jsonEncode($refundData));
                                    WkMidtransCommerceHelper::logMsg('refund', '----------------------- ', true);

                                    $refundObj = new WkMidtransCommerceRefund();
                                    $refundObj->order_trans_id = (int)$idTrans;
                                    $refundObj->midtrans_refund_id = $refundID;
                                    $refundObj->refund_amount = (float)$refundAmt;
                                    $refundObj->refund_type = (int)$refundType;
                                    $refundObj->currency_code = $transactionData['pp_paid_currency'];
                                    $refundObj->refund_reason = $refundReason;
                                    $refundObj->response = Tools::jsonEncode($refundData);
                                    $refundObj->refund_status = $refundData['data']['status'];
                                    if ($refundObj->save()) {
                                        $urlString = '&viewwk_midtrans_commerce_order=&id_midtrans_commerce_order=' . (int)Tools::getValue('id_midtrans_commerce_order');

                                        Tools::redirectAdmin(self::$currentIndex.$urlString.'&conf=4&token='.$this->token);
                                    }
                                } else {
                                    WkMidtransCommerceHelper::logMsg('refund', 'Refund failed: ', true);
                                    WkMidtransCommerceHelper::logMsg('refund', 'Refund reponse data: ');
                                    WkMidtransCommerceHelper::logMsg('refund', Tools::jsonEncode($refundData));
                                    WkMidtransCommerceHelper::logMsg('refund', '----------------------- ', true);
                                    $this->errors[] = $refundData['data']['message'];
                                }
                            } else {
                                $this->errors[] = $this->l('Refund is already done for this transaction.');
                            }
                        }
                    } else {
                        $this->errors[] = $this->l('Refund is already done for this transaction.');
                    }
                } else {
                    $this->errors[] = $this->l('Invalid request');
                }
            }
        }

        parent::postProcess();
    }

    public function setMedia()
    {
        parent::setMedia();

        $this->addJS(_PS_MODULE_DIR_ . 'qlomidtranscommerce/views/js/admin/wk_midtrans_transaction.js');
        $this->addCSS(_PS_MODULE_DIR_ . 'qlomidtranscommerce/views/css/admin/wk_midtrans_transaction.css');
    }
}
