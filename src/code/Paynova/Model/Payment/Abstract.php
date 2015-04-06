<?php

/**
 * Paynova Payment Module
 *
 * @author jonathan@madepeople.se
 */
class Made_Paynova_Model_Payment_Abstract
    extends Mage_Payment_Model_Method_Abstract
{
    const PAYMENT_CHANNEL_WEB = 1;
    const PAYMENT_CHANNEL_MAIL_TELEPHONE = 2;
    const PAYMENT_CHANNEL_RECURRING = 7;

    protected $_isGateway = false;
    protected $_canOrder = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = true;
    protected $_canManageRecurringProfiles = false;

    /**
     * This saves our extra custom data in the database so we can re-use it
     * when the customer for instance enters a SSN and then goes away and back
     * to the checkout. Why isn't this the Magento default?
     *
     * @param Varien_Object $data
     * @return \Made_Paynova_Model_Payment_Abstract
     */
    public function assignData($data)
    {
        if (is_object($data)) {
            $data = $data->toArray();
        }
        if (!empty($data) && isset($data[$this->_code])) {
            $this->getInfoInstance()->setAdditionalInformation($this->_code,
                $data[$this->_code]);
        }
        return parent::assignData($data);
    }

    /**
     * Retrieve global Paynova settings information from payment configuration
     *
     * @param string $field
     * @param int|string|null|Mage_Core_Model_Store $storeId
     *
     * @return mixed
     */
    public function getGeneralConfigData($field, $storeId = null)
    {
        if (null === $storeId) {
            $storeId = $this->getStore();
        }
        $path = 'payment/made_paynova_general/'.$field;
        return Mage::getStoreConfig($path, $storeId);
    }

    /**
     * Get the API URL depending on the test settings
     *
     * @return mixed
     */
    public function getApiUrl()
    {
        if ($this->getIsTest()) {
            $url = $this->getGeneralConfigData('api_url_test');
        } else {
            $url = $this->getGeneralConfigData('api_url_live');
        }
        $url = preg_replace('#/+$#', '', $url);
        return $url;
    }

    /**
     * Determine if we are in test mode or not
     *
     * @return bool
     */
    public function getIsTest()
    {
        $testMode = (bool)$this->getGeneralConfigData('test');
        return $testMode;
    }

    /**
     * Flatten array and concatenate keys
     *
     * @see http://stackoverflow.com/a/9546215/533801
     * @param $array
     * @param string $prefix
     * @return array
     */
    protected final function _flattenArray($array, $prefix = '')
    {
        $result = array();
        foreach ($array as $key => $value) {
            if (is_object($value)) {
                $value = (array)$value;
            }
            if (is_array($value)) {
                $result = $result + $this->_flattenArray($value, $prefix . $key . '.');
            } else {
                $result[$prefix . $key] = $value;
            }
        }
        return $result;
    }

    /**
     * Perform a REST API call to Paynova
     *
     * @param $action
     * @param null|array $parameters
     * @param string $method
     * @return mixed
     * @throws Mage_Core_Exception
     * @throws Zend_Http_Client_Exception
     */
    protected function _call($action, $parameters = null, $method = Zend_Http_Client::GET)
    {
        $action = '/' . preg_replace('#^/+#', '', $action);
        $url = $this->getApiUrl() . $action;

        $httpClient = new Zend_Http_Client($url);
        $httpClient->setAdapter('Zend_Http_Client_Adapter_Curl');
        $httpClient->setHeaders('Content-Type', 'application/json');
        $httpClient->setMethod($method);

        $username = $this->getGeneralConfigData('merchant_id');
        $password = $this->getGeneralConfigData('password');
        $password = Mage::helper('core')->decrypt($password);
        $httpClient->setAuth($username, $password, Zend_Http_Client::AUTH_BASIC);

        if (null !== $parameters) {
            $parametersJson = Mage::helper('core')->jsonEncode($parameters);
            $httpClient->setRawData($parametersJson);
        }

        $response = $httpClient->request();
        if (!preg_match('#^2#', $response->getStatus())) {
            Mage::throwException('An error occurred when communicating with Paynova: ' . $response->getMessage() . ' (' . $response->getStatus() . ')');
        }

        $resultJson = $response->getBody();
        $result = Mage::helper('core')->jsonDecode($resultJson);
        return $result;
    }

    /**
     * Returns an array of lineItems data
     *
     * @param $object  Order, invoice or credit memo
     */
    protected function _getLineItemsArray(Mage_Sales_Model_Abstract $object)
    {
        $allItemsData = array();
        foreach ($object->getAllItems() as $item) {
            if ($item->getParentItemId()) {
                // Only use visible items
                continue;
            }

            switch (get_class($object)) {
                case 'Mage_Sales_Model_Order':
                    $qty = $item->getQtyOrdered();
                    break;
                case 'Mage_Sales_Model_Order_Invoice':
                    $qty = $item->getQtyInvoiced();
                    break;
                case 'Mage_Sales_Model_Order_Creditmemo':
                    $qty = $item->getQtyRefunded();
                    break;
            }

            $itemData = array(
                'id' => $item->getId(),
                'articleNumber' => $item->getSku(),
                'name' => $item->getName(),
                'description' => '',
                'productUrl' => '',
                'quantity' => $qty,
                'unitMeasure' => 'unit',
                'unitAmountExcludingTax' => $item->getPrice(),
                'taxPercent' => $item->getTaxPercent(),
                'totalLineTaxAmount' => $item->getTaxAmount(),
                'totalLineAmount' => $item->getRowTotalInclTax()
            );
            $allItemsData[] = $itemData;
        }

        if ($object->getShippingAmount()) {
            $store = $object->getStore();
            $taxCalculation = Mage::getModel('tax/calculation');
            $request = $taxCalculation->getRateRequest(null, null, null, $store);
            $taxRateId = Mage::getStoreConfig('tax/classes/shipping_tax_class', $store);
            $taxPercent = $taxCalculation->getRate($request->setProductClassId($taxRateId));

            $lineItems[] = array(
                'id' => 'shipping',
                'articleNumber' => $object->getShippingMethod(),
                'name' => $object->getShippingDescription(),
                'description' => '',
                'productUrl' => '',
                'quantity' => 1,
                'unitMeasure' => 'unit',
                'unitAmountExcludingTax' => $object->getShippingAmount(),
                'taxPercent' => $taxPercent,
                'totalLineTaxAmount' => $object->getShippingTaxAmount(),
                'totalLineAmount' => $object->getShippingInclTax()
            );
        }

        return $allItemsData;
    }

    /**
     * Order payment method, calls Paynova Create Order
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @see http://docs.paynova.com/display/API/Create+Order
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function order(Varien_Object $payment, $amount)
    {
        $order = $payment->getOrder();
        $method = join('/', array(
            'orders',
            'create',
        ));

        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();
        $parameters = array(
            'orderNumber' => $order->getIncrementId(),
            'currencyCode' => $order->getOrderCurrencyCode(),
            'totalAmount' => $amount,
            'salesChannel' => Mage::app()->getWebsite()->getName(),
            'customer' => array(
                'customerId' => null,
                'governmentId' => null,
                'emailAddress' => $order->getCustomerEmail(),
                'name' => array(
                    'companyName' => $billingAddress->getCompany(),
                    'title' => $billingAddress->getTitle(),
                    'firstName' => $billingAddress->getFirstname(),
                    'middleNames' => $billingAddress->getMiddlename(),
                    'lastName' => $billingAddress->getLastname(),
                    'suffix' => $billingAddress->getSuffix(),
                ),
            ),
            'billTo' => array(
                'name' => array(
                    'companyName' => $billingAddress->getCompany(),
                    'title' => $billingAddress->getTitle(),
                    'firstName' => $billingAddress->getFirstname(),
                    'middleNames' => $billingAddress->getMiddlename(),
                    'lastName' => $billingAddress->getLastname(),
                    'suffix' => $billingAddress->getSuffix(),
                ),
                'address' => array(
                    'street1' => $billingAddress->getStreet(1),
                    'street2' => $billingAddress->getStreet(2),
                    'street3' => $billingAddress->getStreet(3),
                    'street4' => $billingAddress->getStreet(4),
                    'city' => $billingAddress->getCity(),
                    'postalCode' => $billingAddress->getPostcode(),
                    'regionCode' => $billingAddress->getRegionId(),
                    'countryCode' => $billingAddress->getCountryId()
                ),
            ),
            'shipTo' => array(
                'name' => array(
                    'companyName' => $shippingAddress->getCompany(),
                    'title' => $shippingAddress->getTitle(),
                    'firstName' => $shippingAddress->getFirstname(),
                    'middleNames' => $shippingAddress->getMiddlename(),
                    'lastName' => $shippingAddress->getLastname(),
                    'suffix' => $shippingAddress->getSuffix(),
                ),
                'address' => array(
                    'street1' => $shippingAddress->getStreet(1),
                    'street2' => $shippingAddress->getStreet(2),
                    'street3' => $shippingAddress->getStreet(3),
                    'street4' => $shippingAddress->getStreet(4),
                    'city' => $shippingAddress->getCity(),
                    'postalCode' => $shippingAddress->getPostcode(),
                    'regionCode' => $shippingAddress->getRegionId(),
                    'countryCode' => $shippingAddress->getCountryId()
                ),
            ),
        );

        $additionalInformation = $payment->getAdditionalInformation();
        if (isset($additionalInformation[$this->getCode()])) {
            if (isset($additionalInformation[$this->getCode()]['government_id'])) {
                $parameters['customer']['governmentId'] = $additionalInformation[$this->getCode()]['government_id'];
            }
        }

        $lineItems = $this->_getLineItemsArray($order);
        $parameters['lineItems'] = $lineItems;

        $result = $this->_call($method, $parameters, Zend_Http_Client::POST);
        if ($result['status']['isSuccess'] === false) {
            Mage::throwException('Paynova Payment Failed: ' . $result['status']['statusMessage']);
        }

        $payment->setTransactionId($result['orderId']);

        return $this;
    }

    /**
     * Capture the authorized invoice order
     *
     * @param Varien_Object $payment
     * @param float $amount
     */
    public function capture(Varien_Object $payment, $amount)
    {
        $parentTransactionId = $payment->getParentTransactionId();
        $parentTransaction = $payment->lookupTransaction($parentTransactionId);
        $additionalInfo = $parentTransaction->getTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS);

        $paynovaTransactionId = $additionalInfo['transactionId'];
        $paynovaOrderId = $parentTransaction->getId();

        $method = join('/', array(
            'orders',
            $paynovaOrderId,
            'transactions',
            $paynovaTransactionId,
            'finalize',
            $amount
        ));

        $invoice = $payment->getCreatedInvoice();

        $parameters = array(
            'orderId' => $paynovaOrderId,
            'invoiceId' => $invoice->getIncrementId()
        );

        $lineItems = $this->_getLineItemsArray($invoice);
        $parameters['lineItems'] = $lineItems;

        $result = $this->_call($method, $parameters, Zend_Http_Client::POST);
        if ($result['status']['isSuccess'] === false) {
            Mage::throwException('Paynova Authorization Failed: ' . $result['status']['statusMessage']);
        }

        $payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
            $this->_flattenArray($result));

        return $this;
    }

    /**
     * Refund specified amount for payment
     *
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function refund(Varien_Object $payment, $amount)
    {
        $parentTransactionId = $payment->getParentTransactionId();
        $parentTransaction = $payment->lookupTransaction($parentTransactionId);
        $additionalInfo = $parentTransaction->getTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS);

        $paynovaTransactionId = $additionalInfo['transactionId'];
        $paynovaOrderId = $parentTransaction->getId();

        $method = join('/', array(
            'orders',
            $paynovaOrderId,
            'transactions',
            $paynovaTransactionId,
            'refund',
            $amount
        ));

        $creditmemo = $payment->getCreditmemo();

        $parameters = array(
            'orderId' => $paynovaOrderId,
            'invoiceId' => $creditmemo->getIncrementId()
        );

        $lineItems = $this->_getLineItemsArray($creditmemo);
        $parameters['lineItems'] = $lineItems;

        $result = $this->_call($method, $parameters, Zend_Http_Client::POST);
        if ($result['status']['isSuccess'] === false) {
            Mage::throwException('Paynova Authorization Failed: ' . $result['status']['statusMessage']);
        }

        $payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
            $this->_flattenArray($result));

        return $this;
    }

    /**
     * Get addresses for the supplied governemnt ID and country code.
     *
     * @param $countryCode
     * @param $governmentId
     * @return array
     */
    public function getAddresses($countryCode, $governmentId)
    {
        $method = join('/', array(
            'addresses',
            $countryCode,
            $governmentId
        ));
        $result = $this->_call($method);
        return $result;
    }

    /**
     * Returns the available Paynova payment methods for the supplied quote
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return array
     */
    public function getPaymentOptions(Mage_Sales_Model_Quote $quote)
    {
        $method = 'paymentoptions';
        $parameters = array(
            'TotalAmount' => $quote->getGrandTotal(),
            'CurrencyCode' => $quote->getQuoteCurrencyCode(),
            'PaymentChannelId' => 1, // What's this?
            'CountryCode' => $quote->getBillingAddress()->getCountryId(),
            'LanguageCode' => 'SWE', // What's this?
        );

        $result = $this->_call($method, $parameters, Zend_Http_Client::POST);
        return $result;
    }

}