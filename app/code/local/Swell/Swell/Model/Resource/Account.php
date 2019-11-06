<?php
/**
 * @category    Swell
 * @package     Swell_Swell
 * @copyright   Copyright (c) Swell Rewards (https://www.swellrewards.com/)
 */

/**
 * Class Swell_Swell_Model_Resource_Account
 */
class Swell_Swell_Model_Resource_Account extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Initialization here
     *
     */
    protected function _construct()
    {
        $this->_init('swell/account', 'id');
    }
}
