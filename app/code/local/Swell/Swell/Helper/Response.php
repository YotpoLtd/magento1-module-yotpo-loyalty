<?php
/**
 * @category    Swell
 * @package     Swell_Swell
 * @copyright   Copyright (c) Swell Rewards (https://www.swellrewards.com/)
 */

/**
 * Class Swell_Swell_Helper_Response
 */
class Swell_Swell_Helper_Response extends Mage_Core_Helper_Abstract
{
    /**
     * Prepare order object to be converted to JSON
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return array
     */
    public function prepareOrder(Mage_Sales_Model_Order $order)
    {
        $orderData = $order->getData();

        // Get order credit memos
        $creditMemoData = [];
        $creditMemoCollection = Mage::getResourceModel('sales/order_creditmemo_collection')
            ->addFieldToFilter('order_id', $order->getId())
            ->setOrder('created_at', 'DESC');
        foreach ($creditMemoCollection as $creditMemo) {
            $creditMemoItemData = [];
            $creditMemoItemCollection = Mage::getResourceModel('sales/order_creditmemo_item_collection')
                ->addFieldToFilter('parent_id', $creditMemo->getId());
            foreach ($creditMemoItemCollection as $creditMemoItem) {
                $creditMemoItemData[] = $creditMemoItem->getData();
            }

            $creditMemo->setItems($creditMemoItemData);
            $creditMemoData[] = $creditMemo->getData();
        }
        $orderData['refunds'] = $creditMemoData;

        // Get order items
        $itemsData = [];
        $itemsCollection = $order->getItemsCollection();
        foreach ($itemsCollection as $item) {
            $itemsData[] = $item->getData();
        }
        $orderData['items'] = $itemsData;

        // Get order billing address
        $billingAddress = $order->getBillingAddress();
        if ($billingAddress->getId()) {
            $orderData['billing_address'] = [
                'country_code' => $billingAddress->getCountryId(),
                'first_name' => $billingAddress->getFirstname(),
                'last_name' => $billingAddress->getLastname(),
                'address1' => $billingAddress->getStreet(1),
                'address2' => $billingAddress->getStreet(2),
                'city' => $billingAddress->getCity(),
                'phone' => $billingAddress->getTelephone(),
                'zip' => $billingAddress->getPostcode()
            ];
        }

        // Get order shipping address
        $shippingAddress = $order->getShippingAddress();
        // Need to check if shipping address is an object
        // because downloadable orders don't have shipping
        // address information
        if (is_object($shippingAddress) && $shippingAddress->getId()) {
            $orderData['shipping_address'] = [
                'country_code' => $shippingAddress->getCountryId(),
                'first_name' => $shippingAddress->getFirstname(),
                'last_name' => $shippingAddress->getLastname(),
                'address1' => $shippingAddress->getStreet(1),
                'address2' => $shippingAddress->getStreet(2),
                'city' => $shippingAddress->getCity(),
                'phone' => $shippingAddress->getTelephone(),
                'zip' => $shippingAddress->getPostcode()
            ];
        }

        return $orderData;
    }

    /**
     * Prepare customer object to be converted to JSON
     *
     * @param Mage_Customer_Model_Customer $customer
     *
     * @return array
     */
    public function prepareCustomer(Mage_Customer_Model_Customer $customer)
    {
        $customerData = [
            'email' => $customer->getEmail(),
            'created_at' => $customer->getCreatedAt(),
            'first_name' => $customer->getFirstname(),
            'last_name' => $customer->getLastname(),
            'group_id' => $customer->getGroupId(),
            'id' => $customer->getId()
        ];

        if ($customer->getDefaultBilling()) {
            $address = Mage::getModel('customer/address')->load($customer->getDefaultBilling());
            if ($address) {
                $customerData['phone'] = $address->getTelephone();
            }
        }

        return $customerData;
    }
}
