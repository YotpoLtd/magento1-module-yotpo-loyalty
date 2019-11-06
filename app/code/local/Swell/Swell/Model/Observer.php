<?php

class Swell_Swell_Model_Observer
{
    /**
     * Attempt to make a notification call to Swell server,
     * if fail, retry on next cron
     */
    public function processPendingNotifications()
    {
        // TODO: only select the one's that need to be retried based on exponential backoff
        //   2^(attempts+4) => 16, 32, 64, etc seconds to retry
        $helper = Mage::helper('swell');
        $notifications = Mage::getResourceModel('swell/notification_collection');
        $notifications->getSelect()->order('created_at ASC');

        foreach ($notifications as $notification) {
            $response = $helper->processNotification($notification->getData());

            if (intval($response) == 200 || intval($notification->getAttempts()) > 24) {
                $notification->delete();
                $helper->swellLogger(
                    'Swell_Swell_Model_Observer::processPendingNotifications',
                    "Notification deleted - nid: " . $notification->getId() . "."
                );
                continue;
            }

            try {
                $notification->setAttempts($notification->getAttempts() + 1);
                $notification->setLastAttemptedAt(Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s'));
                $notification->save();
                $helper->swellLogger(
                    'Swell_Swell_Model_Observer::processPendingNotifications',
                    "Notification saved - nid: " . $notification->getId() . "."
                );
            } catch (Exception $e) {
                $helper->swellLogger(
                    'Swell_Swell_Model_Observer::processPendingNotifications',
                    "Error saving notification - nid: " . $notification->getId() . " - Error: " . $e->getMessage() . "."
                );
            }
        }
    }

    /**
     * Register flags for new or existing customers
     *
     * @param Varien_Event_Observer $observer
     */
    public function beforeCustomerSaved(Varien_Event_Observer $observer)
    {
        $customer = $observer->getEvent()->getCustomer();

        $alreadyProcessed = Mage::registry("swell/customer/before");

        if (!$alreadyProcessed) {
            Mage::register("swell/customer/before", true);

            if ($customer->isObjectNew()) {
                Mage::register("swell/customer/created", true);
            } else {
                Mage::register("swell/customer/original/email", $customer->getOrigData("email"));
                Mage::register("swell/customer/original/group_id", $customer->getOrigData("group_id"));
            }
        }
    }

    /**
     * Update Swell Referrals and/or Notifications based on if the customer is new or updated
     *
     * @param Varien_Event_Observer $observer
     */
    public function afterCustomerSaved(Varien_Event_Observer $observer)
    {
        $customer = $observer->getEvent()->getCustomer();
        $alreadyProcessed = Mage::registry("swell/customer/after");

        if (!$alreadyProcessed) {
            Mage::register("swell/customer/after", true);

            $originalEmail = Mage::registry("swell/customer/original/email");
            $originalGroupId = Mage::registry("swell/customer/original/group_id");
            $customerCreated = Mage::registry("swell/customer/created");

            $newEmail = $customer->getData("email");
            $newGroupId = $customer->getData("group_id");

            $emailUpdated = isset($originalEmail) && $originalEmail != $newEmail;
            $groupUpdated = isset($originalGroupId) && $originalGroupId != $newGroupId;
            $customerUpdated = $emailUpdated || $groupUpdated;

            if ($customerCreated || $customerUpdated) {
                $status = $customerCreated ? "created" : "updated";

                $entity_json = json_encode(Mage::helper('swell/response')->prepareCustomer($customer));

                if ($customerCreated) {
                    $referral_data = ['entity_id' => $customer->getId(),
                        'entity_type' => "customer",
                        'ip_address' => Mage::helper('core/http')->getRemoteAddr(),
                        'user_agent' => Mage::helper('core/http')->getHttpUserAgent(),
                        'created_at' => $customer->getData("created_at"),
                        'magento_store_id' => $customer->getData("store_id")];

                    $referral = Mage::getModel("swell/referral")->setData($referral_data);
                    $referral->save();
                }

                $notification_data = ['entity_id' => $customer->getId(),
                    'entity_type' => "customer",
                    'entity_status' => $status,
                    'magento_store_id' => $customer->getData("store_id"),
                    'created_at' => $customer->getData("updated_at"),
                    'entity_json' => $entity_json];

                $notification = Mage::getModel('swell/notification')->setData($notification_data);

                Mage::helper("swell")->handleNotification($notification);
            }
        }
    }

    /**
     * Register flags for new or existing orders
     *
     * @param Varien_Event_Observer $observer
     */
    public function beforeAdminOrderSaved(Varien_Event_Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        $alreadyProcessed = Mage::registry("swell/order/" . $order->getId() . "/before");

        if (!$alreadyProcessed) {
            Mage::register("swell/order/" . $order->getId() . "/before", true);

            if ($order->isObjectNew()) {
                Mage::register("swell/order/created", true);
            } else {
                Mage::register("swell/order/" . $order->getId() . "/original/state", $order->getOrigData("state"));
                Mage::register("swell/order/" . $order->getId() . "/original/status", $order->getOrigData("status"));
                Mage::register("swell/order/" . $order->getId() . "/original/base_total_refunded", $order->getOrigData("base_total_refunded"));
            }
        }
    }

    /**
     * Update Swell Referrals and/or Notifications based on if the order is new or updated
     *
     * @param Varien_Event_Observer $observer
     */
    public function afterAdminOrderSaved(Varien_Event_Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        $alreadyProcessed = Mage::registry("swell/order/" . $order->getId() . "/after");

        if (!$alreadyProcessed) {
            Mage::register("swell/order/" . $order->getId() . "/after", true);

            $originalState = Mage::registry("swell/order/" . $order->getId() . "/original/state");
            $originalStatus = Mage::registry("swell/order/" . $order->getId() . "/original/status");
            $originalTotalRefunded = Mage::registry("swell/order/" . $order->getId() . "/original/base_total_refunded");
            $orderCreated = Mage::registry("swell/order/" . $order->getId() . "/created");

            $newState = $order->getData("state");
            $newStatus = $order->getData("status");
            $newTotalRefunded = $order->getData("base_total_refunded");

            $stateUpdated = isset($originalState) && $originalState != $newState;
            $statusUpdated = isset($originalStatus) && $originalStatus != $newStatus;
            $refundUpdated = isset($originalTotalRefunded) && $originalTotalRefunded != $newTotalRefunded;
            $refundCreated = !isset($originalTotalRefunded) && isset($newTotalRefunded);
            $orderUpdated = $stateUpdated || $statusUpdated;
            $orderRefunded = $refundCreated || $refundUpdated;

            if ($orderCreated || $orderUpdated || $orderRefunded) {
                if ($orderCreated) {
                    $status = "created";
                } elseif ($orderRefunded) {
                    $status = "refunded";
                } elseif ($orderUpdated) {
                    $status = "updated";
                }

                $entity_json = json_encode(Mage::helper('swell/response')->prepareOrder($order));

                if ($orderCreated) {
                    $referral_data = ['entity_id' => $order->getId(),
                        'entity_type' => "order",
                        'ip_address' => Mage::helper('core/http')->getRemoteAddr(),
                        'user_agent' => Mage::helper('core/http')->getHttpUserAgent(),
                        'created_at' => $order->getData("created_at"),
                        'magento_store_id' => $order->getData("store_id")];

                    $referral = Mage::getModel("swell/referral")->setData($referral_data);
                    $referral->save();
                }

                $notification_data = ['entity_id' => $order->getId(),
                    'entity_type' => "order",
                    'entity_status' => $status,
                    'magento_store_id' => $order->getData("store_id"),
                    'created_at' => $order->getData("updated_at"),
                    'entity_json' => $entity_json];

                $notification = Mage::getModel('swell/notification')->setData($notification_data);

                Mage::helper("swell")->handleNotification($notification);
            }
        }
    }

    /**
     * Upon saving Swell Setup configuration attempt generate an account
     * and retrieve account information from Swell server
     *
     * @param Varien_Event_Observer $observer
     */
    public function swellSetupSaveSuccess(Varien_Event_Observer $observer)
    {
        $store = Mage::app()->getRequest()->getParam('store');

        if ($store) {
            $c = Mage::getModel('core/store')->getCollection()->addFieldToFilter('code', $store);
            $item = $c->getFirstItem();
            $storeId = $item->getStoreId();
            Mage::helper('swell')->setupSwellAccount($storeId);
        }
    }
}
