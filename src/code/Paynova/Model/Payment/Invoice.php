<?php

class Made_Paynova_Model_Payment_Invoice
    extends Made_Paynova_Model_Payment_Abstract
{
    protected $_code = 'made_paynova_invoice';
    protected $_formBlockType = 'made_paynova/payment_form_invoice';

    private $_paymentMethodId = 311;

    /**
     * Authorize a new payment
     *
     * @param Varien_Object $payment
     * @param float $amount
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        $this->order($payment, $amount);
        $method = join('/', array(
            'orders',
            $payment->getTransactionId(),
            'authorizePayment'
        ));

        $parameters = array(
            'AuthorizationType' => 'InvoicePayment',
            'TotalAmount' => $amount,
            'PaymentMethodId' => $this->_paymentMethodId,
            'PaymentMethodProductId' => 'DirectInvoice', // What's this?
            'PaymentChannelId' => self::PAYMENT_CHANNEL_WEB,
        );

        $result = $this->_call($method, $parameters, Zend_Http_Client::POST);
        if ($result['status']['isSuccess'] === false) {
            Mage::log(var_export($result, true), null, 'paynova.log');
            Mage::throwException('Paynova Authorization Failed: ' . $result['status']['statusMessage']);
        }

        $payment->setIsTransactionClosed(false);
        $payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
            $this->_flattenArray($result));

        return $this;
    }
}