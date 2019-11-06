<?php
/**
 * @category    Swell
 * @package     Swell_Swell
 * @copyright   Copyright (c) Swell Rewards (https://www.swellrewards.com/)
 */

$entities = ['quote_item', 'order_item'];

$options = [
    'type' => Varien_Db_Ddl_Table::TYPE_INTEGER,
    'visible' => true,
    'visible_on_front' => true,
    'required' => false
];

$installer = new Mage_Sales_Model_Resource_Setup('core_setup');

$installer->startSetup();

foreach ($entities as $entity) {
    $installer->addAttribute($entity, 'swell_redemption_id', $options);
    $installer->addAttribute($entity, 'swell_points_used', $options);
}

$installer->endSetup();
