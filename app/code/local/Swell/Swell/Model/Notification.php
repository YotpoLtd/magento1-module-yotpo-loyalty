<?php
/**
 * @category    Swell
 * @package     Swell_Swell
 * @copyright   Copyright (c) Swell Rewards (https://www.swellrewards.com/)
 */

/**
 * Class Swell_Swell_Model_Notification
 */
class Swell_Swell_Model_Notification extends Mage_Core_Model_Abstract
{
    /**
     * Internal constructor not depended on params. Can be used for object initialization
     */
    public function _construct()
    {
        $this->_init('swell/notification');
    }
}
