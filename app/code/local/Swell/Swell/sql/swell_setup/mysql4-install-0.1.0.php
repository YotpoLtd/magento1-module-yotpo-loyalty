<?php
/**
 * @category    Swell
 * @package     Swell_Swell
 * @copyright   Copyright (c) Swell Rewards (https://www.swellrewards.com/)
 */

$installer = $this;
$installer->startSetup();

$tableName = $installer->getTable('swell_accounts');
if ($installer->getConnection()->isTableExists($tableName) === false) {
    $tableSwellAccount = $installer->getConnection()
        ->newTable($tableName)
        ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, 11, [
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
        ], 'Id')
        ->addColumn('welcome_url', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
            'nullable' => false,
        ], 'Welcome Url')
        ->addColumn('login_url', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
            'nullable' => false,
        ], 'Login Url')
        ->addColumn('swell_user_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 11, [
            'nullable' => false,
        ], 'Swell User Id')
        ->addColumn('swell_merchant_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 11, [
            'nullable' => false,
        ], 'Swell Merchant Id')
        ->addColumn('swell_api_key', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
            'nullable' => false,
        ], 'Swell api key')
        ->addColumn('swell_merchant_guid', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
            'nullable' => false,
        ], 'Swell Merchant Guid')
        ->addColumn('swell_script_url', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
            'nullable' => false,
        ], 'Swell Script Url')
        ->addColumn('unique_merchant_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
            'nullable' => false,
        ], 'Unique Merchant Id')
        ->addColumn('shared_secret', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
            'nullable' => false,
        ], 'Shared Secret')
        ->addColumn('magento_store_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
            'nullable' => false,
        ], 'Magento Store Id');
}

$tableName = $installer->getTable('swell_notifications');
if ($installer->getConnection()->isTableExists($tableName) === false) {
    $tableSwellNotifications = $installer->getConnection()
        ->newTable($tableName)
        ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, 11, [
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
        ], 'Id')
        ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 11, [
            'nullable' => false,
        ], 'Entity Id')
        ->addColumn('entity_type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
            'nullable' => false,
        ], 'Entity Type')
        ->addColumn('entity_status', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
            'nullable' => false,
        ], 'Entity Status')
        ->addColumn('attempts', Varien_Db_Ddl_Table::TYPE_INTEGER, 11, [
            'nullable' => false,
            'default' => 0,
        ], 'attempts')
        ->addColumn('last_attempted_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
            'nullable' => false,
        ], 'Last Attempted At')
        ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
            'nullable' => false,
        ], 'Created At')
        ->addColumn('magento_store_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
            'nullable' => false,
        ], 'Magento Store Id')
        ->addColumn('entity_json', Varien_Db_Ddl_Table::TYPE_TEXT, null, [
            'nullable' => false,
        ], 'Entity Json');
}

$tableName = $installer->getTable('swell_referrals');
if ($installer->getConnection()->isTableExists($tableName) === false) {
    $tableSwellReferrals = $installer->getConnection()
        ->newTable($tableName)
        ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, 11, [
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
        ], 'Id')
        ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, [
            'nullable' => false,
        ], 'Created At')
        ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 11, [
            'nullable' => false,
        ], 'Entity Id')
        ->addColumn('entity_type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
            'nullable' => false,
        ], 'Entity Type')
        ->addColumn('ip_address', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
            'nullable' => false,
        ], 'Ip Address')
        ->addColumn('user_agent', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
            'nullable' => false,
        ], 'User Agent')
        ->addColumn('magento_store_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, [
            'nullable' => false,
        ], 'Magento Store Id')
        ->addIndex(
            $installer->getIdxName('swell_referrals', ['entity_id', 'entity_type', 'magento_store_id']),
            ['entity_id', 'entity_type', 'magento_store_id']
        );
}

$installer->getConnection()->createTable($tableSwellAccount);
$installer->getConnection()->createTable($tableSwellNotifications);
$installer->getConnection()->createTable($tableSwellReferrals);

$installer->endSetup();
