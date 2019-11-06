<?php
/**
 * @category    Swell
 * @package     Swell_Swell
 * @copyright   Copyright (c) Swell Rewards (https://www.swellrewards.com/)
 */

/**
 * Class Swell_Swell_Helper_Data
 */
class Swell_Swell_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function handleNotification($notification)
    {
        $use_cron = Mage::getStoreConfig('swell/swellconfig/cron_notifications', $notification->getData("magento_store_id"));

        $response = 0;

        // try to send it to SWELL immediately
        if ($use_cron == 0 || $use_cron == "no") {
            $response = $this->processNotification($notification);
        }

        // if use_cron enabled or response was a failure, save it for cron job
        if (intval($response) != 200) {
            $notification->save();
            Mage::log("Swell_Swell_Helper_Data::handleNotification - notification saved: " . $notification->getId() . ".", null, 'swell.log');
        }
    }

    public function processNotification($notification)
    {
        $entity = json_decode($notification['entity_json'], true);

        $referrals = Mage::getResourceModel('swell/referral_collection');
        $referrals->addFieldToFilter('entity_id', $notification['entity_id']);
        $referrals->addFieldToFilter('entity_type', $notification['entity_type']);
        $referrals->addFieldToFilter('magento_store_id', $notification['magento_store_id']);

        if ($referrals->count() > 0) {
            $referral = $referrals->getFirstItem();
            $entity["ip_address"] = $referral->getIpAddress();
            $entity["user_agent"] = $referral->getUserAgent();
        }

        $entity["topic"] = $notification["entity_type"] . "/" . $notification['entity_status']; // order/created, customer/updated, etc
        $entity["magento_store_id"] = $notification["magento_store_id"];

        $response = 0;

        try {
            $response = $this->sendWebhookNotification($entity);
            $customerEmail = isset($entity["customer_email"]) ? $entity["customer_email"] : $entity["email"];
            $this->swellLogger(
                'Swell_Swell_Helper_Data::processNotification',
                "notification sent - topic: " . $entity["topic"] . " - customer_email: " . $customerEmail . "."
            );
        } catch (Exception $e) {
            $response = 500;
            $this->swellLogger(
                'Swell_Swell_Helper_Data::processNotification',
                "entity: " . print_r($entity, 1) . " - Error: " . $e->getMessage() . "."
            );
        }

        return $response;
    }

    /**
     * POST referral data to https://www.swellrewards.com/magento/webhooks
     *
     * @param $object
     *
     * @return int
     */
    public function sendWebhookNotification($object)
    {
        $api_key = $this->getApiKeyForStoreId($object["magento_store_id"]);
        $guid = $this->getGuidForStoreId($object["magento_store_id"]);

        if ($api_key != null && $guid != null) {
            $object["guid"] = $guid;
            $object["api_key"] = $api_key;

            $object_json = json_encode($object);

            $url = $this->getSwellHost() . "/magento/webhooks";

            $method_name = 'POST';

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $object_json);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                ['Content-Type: application/json', 'Content-Length: ' . strlen($object_json)]
            );
            $api_response = curl_exec($ch);
            $api_response_info = curl_getinfo($ch);
            curl_close($ch);

            return $api_response_info['http_code'];
        } else {
            return 500;
        }
    }

    // Supposed to be store domain name: mystore.com
    public function getStoreDomainName($storeId)
    {
        if (empty($storeId)) {
            $store = Mage::app()->getStore();
        } else {
            $store = Mage::getModel('core/store')->load($storeId);
        }

        $base_url = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $domain_name = str_replace("http://", "", $base_url);
        $domain_name = str_replace("https://", "", $domain_name);

        return $domain_name;
    }

    // Supposed to be store homepage: https://www.mystore.com
    public function getStoreWebsiteURL($storeId)
    {
        if (empty($storeId)) {
            $store = Mage::app()->getStore();
        } else {
            $store = Mage::getModel('core/store')->load($storeId);
        }

        return $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
    }

    // supposed to be currency code like "USD" or "EUR"
    public function getStoreCurrencyCode($storeId)
    {
        if (empty($storeId)) {
            $store = Mage::app()->getStore();
        } else {
            $store = Mage::getModel('core/store')->load($storeId);
        }

        return $store->getCurrentCurrencyCode();
    }

    /**
     * Get Magento Version number
     *
     * @return string
     */
    public function getMagentoVersion()
    {
        return "Magento " . Mage::getVersion();
    }

    /**
     * Get Swell Host
     * TODO: host should be a const
     *
     * @return string
     */
    public function getSwellHost()
    {
        // return "http://localhost:3000";
        // TODO: should be inside system.xml and moved to a configuration instead of hardcoding the domain
        return "https://app.swellrewards.com";
    }

    public function getSwellCdnHost()
    {
        return "https://cdn.swellrewards.com";
    }

    public function getSetupUrl()
    {
        return $this->getSwellHost() . "/magento/setup";
    }

    public function getLoaderUrl($storeId)
    {
        return $this->getSwellCdnHost() . "/loader/" . $this->getGuidForStoreId($storeId) . ".js";
    }

    public function setupSwellAccount($storeId)
    {
        $data = [];
        $data["api_key"] = $this->getApiKeyForStoreId($storeId);
        $data["guid"] = $this->getGuidForStoreId($storeId);
        $data["version"] = $this->getMagentoVersion();
        $data["currency"] = $this->getStoreCurrencyCode($storeId);
        $data["id"] = $storeId . $this->getStoreDomainName($storeId);
        $data["website"] = $this->getStoreWebsiteURL($storeId);
        $data["root_api_url"] = $this->getStoreWebsiteURL($storeId);

        $json_data = json_encode($data);
        $url = $this->getSetupUrl();
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            ['Content-Type: application/json', 'Content-Length: ' . strlen($json_data)]
        );
        $result = curl_exec($ch);
        $response = json_decode($result);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode == 200) {
            Mage::getSingleton('adminhtml/session')->addSuccess(
                "Your Magento instance has successfully been connected to your Swell account."
            );
        } else {
            Mage::getSingleton('adminhtml/session')->addError($response->error_message);
        }
    }

    public function getGuidForStoreId($storeId = '')
    {
        if (empty($storeId)) {
            $storeId = Mage::app()->getStore()->getStoreId();
        }
        return Mage::getStoreConfig('swell/swellconfig/swell_merchant_guid', $storeId);
    }

    public function getApiKeyForStoreId($storeId = '')
    {
        if (empty($storeId)) {
            $storeId = Mage::app()->getStore()->getStoreId();
        }

        return Mage::getStoreConfig('swell/swellconfig/shared_secret', $storeId);
    }

    public function apiKeyExists($apiKey)
    {
        return $this->getStoreIdForApiKey($apiKey) != null;
    }

    public function getGuidForApiKey($apiKey)
    {
        $storeId = $this->getStoreIdForApiKey($apiKey);
        if ($storeId != null) {
            return $this->getGuidForStoreId($storeId);
        } else {
            return null;
        }
    }

    public function getStoreIdForApiKey($apiKey)
    {
        $storeId = null;
        $allStores = Mage::app()->getStores();

        foreach ($allStores as $store => $val) {
            $id = Mage::app()->getStore($store)->getId();
            $api_key_for_id = Mage::getStoreConfig('swell/swellconfig/shared_secret', $id);

            if ($api_key_for_id == $apiKey) {
                $storeId = $id;
            }
        }

        return $storeId;
    }

    public function swellLogger($message, $fileName)
    {
        Mage::log($fileName . '-' . $message, null, 'swell.log', true);
    }
}
