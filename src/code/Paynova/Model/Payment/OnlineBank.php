<?php

/**
 * Paynova Online Bank implementation.
 *
 * @author jonathan@madepeople.se
 */
class Made_Paynova_Model_Payment_OnlineBank
    extends Made_Paynova_Model_Payment_Abstract
{
    protected $_code = 'made_paynova_online_bank';
    protected $_formBlockType = 'made_paynova/payment_form_onlineBank';

    protected $_paynovaGroup = 'online_bank';
}