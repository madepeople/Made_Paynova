<?php

abstract class Made_Paynova_Block_Payment_Form_Abstract
    extends Mage_Payment_Block_Form
{
    /**
     * This one lets us get arbitrary values stored on the payment method object
     * such as SSN, customer type, VAT number etc
     *
     * @param $key  Additional data key
     * @return string  The value or an empty string
     */
    public function getAdditionalData($key, $type = null)
    {
        $method = $this->getMethod();
        $infoInstance = $method->getInfoInstance();
        $methodInfo = $infoInstance->getAdditionalInformation($method->getCode());
        if (null !== $type) {
            $methodInfo = isset($methodInfo[$type])
                ? $methodInfo[$type] : array();
        }
        return isset($methodInfo[$key])
            ? $methodInfo[$key] : '';
    }
}