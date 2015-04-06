<?php

/**
 * Paynova Card implementation.
 *
 * @author jonathan@madepeople.se
 */
class Made_Paynova_Model_Payment_Card
    extends Made_Paynova_Model_Payment_Abstract
{
    protected $_code = 'made_paynova_card';
    protected $_formBlockType = 'made_paynova/payment_form_card';

    protected $_paynovaGroup = 'card';
}