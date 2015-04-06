<?php

/**
 * Paynova Installment implementation.
 *
 * @author jonathan@madepeople.se
 */
class Made_Paynova_Model_Payment_Installment
    extends Made_Paynova_Model_Payment_Abstract
{
    protected $_code = 'made_paynova_installment';
    protected $_formBlockType = 'made_paynova/payment_form_installment';

    protected $_paynovaGroup = 'installment';
}