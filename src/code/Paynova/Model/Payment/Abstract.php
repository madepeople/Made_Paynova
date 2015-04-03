<?php

/**
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

    public function authorize(Varien_Object $payment, $amount)
    {

    }

}