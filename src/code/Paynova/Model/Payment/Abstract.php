<?php

/**
 * Paynova Payment Module
 *
 * @author jonathan@madepeople.se
 */
class Made_Paynova_Model_Payment_Abstract
    extends Mage_Payment_Model_Method_Abstract
{

    protected $_isGateway = false;
    protected $_canOrder = false;
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

    protected function _call($method, $parameters = null)
    {
        $method = '/' . preg_replace('#^/+#', '', $method);
        $url = $this->getApiUrl() . $method;

        $httpClient = new Zend_Http_Client($url);
        $httpClient->setAdapter('Zend_Http_Client_Adapter_Curl');
        $httpClient->setHeaders('Content-Type', 'application/json');

        $username = $this->getGeneralConfigData('merchant_id');
        $password = $this->getGeneralConfigData('password');
        $password = Mage::helper('core')->decrypt($password);
        $httpClient->setAuth($username, $password, Zend_Http_Client::AUTH_BASIC);

        $response = $httpClient->request();
        if ($response->getStatus() !== 200) {
            Mage::throwException('An error occurred when communicating with Paynova: ' . $response->getMessage() . ' (' . $response->getStatus() . ')');
        }

        $resultJson = $response->getBody();
        $result = Mage::helper('core')->jsonDecode($resultJson);
        return $result;
    }

    /**
     * Authorize a new payment
     *
     * @param Varien_Object $payment
     * @param float $amount
     */
    public function authorize(Varien_Object $payment, $amount)
    {

    }

    /**
     * Get addresses for the supplied governemnt ID and country code.
     *
     * @param $countryCode
     * @param $governmentId
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

}