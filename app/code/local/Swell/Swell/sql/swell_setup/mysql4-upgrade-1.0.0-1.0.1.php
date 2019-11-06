<?php
/**
 * @category    Swell
 * @package     Swell_Swell
 * @copyright   Copyright (c) Swell Rewards (https://www.swellrewards.com/)
 */

$installer = $this;
$installer->startSetup();

$installer->getConnection()->addIndex(
    $installer->getTable('swell_accounts'),
    $installer->getIdxName(
        'swell_accounts',
        ['shared_secret'],
        Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE
    ),
    ['shared_secret'],
    Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE
);

$installer->endSetup();
