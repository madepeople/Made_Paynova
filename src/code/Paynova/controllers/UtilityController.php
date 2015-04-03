<?php

/**
 * Various utility actions
 *
 * @author jonathan@madepeople.se
 */
class Made_Paynova_UtilityController extends Mage_Core_Controller_Front_Action
{

    /**
     * The get addresses call is proxied through magento because we want the
     * possibility to save the returned information with the magento quote.
     * Also, we might want throttling in the future.
     *
     * @return void
     */
    public function getAddressesAction()
    {
        // We use the invoice model because getAddresses is part of
        // paynova_abstract and the credentials are always the same
        $governmentId = $this->getRequest()->getParam('government_id');
        $countryCode = $this->getRequest()->getParam('country_code');

        $method = Mage::getModel('made_paynova/payment_invoice');
        $addresses = $method->getAddresses($countryCode, $governmentId);
        $addressesJson = Mage::helper('core')->jsonEncode($addresses);
        $this->getResponse()->setHeader('Content-Type', 'application/json');
        $this->getResponse()->setBody($addressesJson);
    }
}