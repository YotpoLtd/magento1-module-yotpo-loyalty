<?php
/**
 * @category    Swell
 * @package     Swell_Swell
 * @copyright   Copyright (c) Swell Rewards (https://www.swellrewards.com/)
 */

/**
 * Class Swell_Swell_Model_Resource_Notification_Collection
 */
class Swell_Swell_Model_Resource_Notification_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Initialization here
     *
     */
    protected function _construct()
    {
        $this->_init('swell/notification');
    }
}
