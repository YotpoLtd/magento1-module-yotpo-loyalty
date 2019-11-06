<?php
/**
 * @category    Swell
 * @package     Swell_Swell
 * @copyright   Copyright (c) Swell Rewards (https://www.swellrewards.com/)
 */

/**
 * Class Swell_Swell_Model_Account
 */
class Swell_Swell_Model_Account extends Mage_Core_Model_Abstract
{
    /**
     * @var Swell_Swell_Helper_Data
     */
    protected $helper;

    /**
     * @var Swell_Swell_Helper_Response
     */
    protected $response;

    /**
     * Internal constructor not depended on params. Can be used for object initialization
     */
    public function _construct()
    {
        $this->response = Mage::helper('swell/response');
        $this->helper = Mage::helper('swell/data');
        $this->_init('swell/account');
    }

    /**
     * Check if request is authorized
     *
     * @param string $secret
     *
     * @return bool
     */
    public function isAuthorized($secret = null)
    {
        if (is_null($secret)) {
            return false;
        }

        return $this->helper->apiKeyExists($secret);
    }

    /**
     * Get order count
     *
     * @param string $secret
     * @param string $orderStates
     * @param bool $last30Days
     *
     * @return int
     */
    public function getOrderCount($secret, $orderStates, $last30Days = false)
    {
        $orderCollection = $this->getOrderCollaction($secret, $orderStates);

        if (!$orderCollection) {
            return false;
        }

        $orderCollection->addAttributeToSelect('entity_id');

        if ($last30Days) {
            $orderCollection->addAttributeToFilter(
                'created_at',
                [
                    'from' => date('Y-m-d', strtotime("-30 day"))
                ]
            );
        }

        return $orderCollection->getSize();
    }

    /**
     * Get orders
     *
     * @param string $secret
     * @param string $orderStates
     * @param int $page
     * @param int $pageSize
     *
     * @return array
     */
    public function getOrders($secret, $orderStates, $page = 1, $pageSize = 250)
    {
        $orderCollection = $this->getOrderCollaction($secret, $orderStates);

        if (!$orderCollection) {
            return false;
        }

        $data = $this->collactionPaging($orderCollection, $pageSize, $page, 'orders');

        return $data;
    }

    /**
     * Get order
     *
     * @param string $secret
     * @param int $orderId
     *
     * @return array
     */
    public function getOrder($secret, $orderId)
    {
        $authorize = $this->isAuthorized($secret);
        if (!$authorize) {
            return false;
        }

        $storeId = $this->helper->getStoreIdForApiKey($secret);

        $data = [];

        $order = Mage::getModel("sales/order")->load($orderId);

        if ($order->getStoreId() == $storeId) {
            $data = $this->response->prepareOrder($order);
        }

        return $data;
    }

    /**
     * Get customers
     *
     * @param string $secret
     * @param int $page
     * @param int $pageSize
     *
     * @return array
     */
    public function getCustomers($secret, $page = 1, $pageSize = 250)
    {
        $authorize = $this->isAuthorized($secret);
        if (!$authorize) {
            return false;
        }

        $storeId = $this->helper->getStoreIdForApiKey($secret);

        $customerCollection = Mage::getModel('customer/customer')
            ->getCollection()
            ->addAttributeToSelect('firstname')
            ->addAttributeToSelect('lastname')
            ->addAttributeToSelect('default_billing')
            ->addAttributeToFilter('store_id', $storeId);

        $data = $this->collactionPaging($customerCollection, $pageSize, $page, 'customers');

        return $data;
    }

    /**
     * Get customer
     *
     * @param string $secret
     * @param int $customerId
     * @param string $email
     *
     * @return array
     */
    public function getCustomer($secret, $customerId, $email)
    {
        $authorize = $this->isAuthorized($secret);
        if (!$authorize) {
            return false;
        }

        $storeId = $this->helper->getStoreIdForApiKey($secret);

        $data = [];

        $customer = Mage::getModel("customer/customer")->load($customerId);

        // TODO: this needs to be further refactored, but is restricted by original API logic
        if ($customer->getStoreId() != $storeId) {
            $customer = Mage::getModel("customer/customer")
                ->getCollection()
                ->addAttributeToSelect('firstname')
                ->addAttributeToSelect('lastname')
                ->addAttributeToSelect('default_billing')
                ->addAttributeToFilter('store_id', $storeId)
                ->addAttributeToFilter('email', $email)
                ->getFirstItem();
        }

        if ($customer->getId()) {
            $data = $this->response->prepareCustomer($customer);
        }

        return $data;
    }

    /**
     * Subscribe
     *
     * @param string $secret
     * @param string $email
     *
     * @return array
     */
    public function subscribe($secret, $email)
    {
        $authorize = $this->isAuthorized($secret);
        if (!$authorize) {
            return false;
        }

        $storeId = $this->helper->getStoreIdForApiKey($secret);

        $subscriber = Mage::getModel('newsletter/subscriber');

        $subscription = $subscriber->getCollection()
            ->addFieldToFilter('store_id', $storeId)
            ->addFieldToFilter('subscriber_email', $email)
            ->getFirstItem();

        if ($subscription->getId()) {
            $data = $subscription->getData();
        } else {
            try {
                $subscriber->subscribe($email);
                $subscriber->setStoreId($storeId);
                $subscriber->setState(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);

                $customer = Mage::getModel("customer/customer")
                    ->getCollection()
                    ->addAttributeToSelect('entity_id')
                    ->addAttributeToFilter('store_id', $storeId)
                    ->addAttributeToFilter("email", $email)
                    ->getFirstItem();

                if ($customer->getId()) {
                    $subscriber->setCustomerId($customer->getId());
                }

                $subscriber->save();

                $data = $subscriber->getData();
            } catch (Exception $e) {
                Mage::log($e->getMessage());
                $data = ['error' => 'an error has occurred trying to subscribe ' . $email];
            }
        }

        return $data;
    }

    /**
     * Create coupon
     *
     * @param string $secret
     * @param string $couponCode
     * @param int $thirdPartyId
     * @param string $name
     * @param string $discountType
     * @param float $amount
     * @param int $usageLimit
     * @param bool $oncePerCustomer
     * @param string $groupIds
     * @param float $freeShippingLessThanCents
     * @param float $cartGreaterThanCents
     * @param string $appliesToAttributes
     * @param string $appliesToValues
     * @param string $appliesToAnyOrAllAttributes
     * @param string $cartId
     *
     * @return array
     */
    public function createCoupon(
        $secret,
        $couponCode,
        $thirdPartyId,
        $name,
        $discountType,
        $amount,
        $usageLimit,
        $oncePerCustomer,
        $groupIds,
        $freeShippingLessThanCents,
        $cartGreaterThanCents,
        $appliesToAttributes,
        $appliesToValues,
        $appliesToAnyOrAllAttributes,
        $cartId
    )
    {
        $authorize = $this->isAuthorized($secret);
        if (!$authorize) {
            return false;
        }

        $storeId = $this->helper->getStoreIdForApiKey($secret);

        $websiteId = Mage::getModel('core/store')
            ->load($storeId)
            ->getWebsiteId();

        $rule = Mage::getModel('salesrule/rule');

        if (isset($thirdPartyId)) {
            $rule->load($thirdPartyId);
        }
        // this means the first time we create a coupon for a redemption option
        // we will create the salesrule on magento
        if (is_null($rule->getId())) {
            switch ($discountType) {
                case 'fixed_amount':
                    $simpleAction = 'cart_fixed';
                    break;
                case 'fixed':
                    $simpleAction = 'by_fixed';
                    break;
                case 'buy_x_get_y':
                    $simpleAction = 'buy_x_get_y';
                    break;
                case 'percentage':
                    $simpleAction = 'by_percent';
                    break;
                default:
                    $simpleAction = '';

            }

            $groupIds = Mage::getModel('customer/group')
                ->getCollection()
                ->getAllIds();

            try {
                $rule = Mage::getModel('salesrule/rule');
                $rule->setName($name)
                    ->setDescription('Yotpo Code: ' . $name)
                    ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
                    ->setUsesPerCoupon($usageLimit)
                    ->setCustomerGroupIds($groupIds)
                    ->setIsActive(1)
                    ->setStopRulesProcessing(0)
                    ->setIsAdvanced(1)
                    ->setSimpleAction($simpleAction)
                    ->setDiscountAmount($amount)
                    ->setSimpleFreeShipping('0')
                    ->setApplyToShipping('0')
                    ->setUseAutoGeneration('1')
                    ->setIsRss(0)
                    ->setWebsiteIds([$websiteId]);

                if ($oncePerCustomer == "true") {
                    $rule->setUsesPerCustomer(1);
                }

                $rule = $this->couponConditionsPrefer(
                    $appliesToAttributes,
                    $appliesToValues,
                    $appliesToAnyOrAllAttributes,
                    $rule
                );

                $rule->save();
            } catch (Exception $e) {
                Mage::log($e->getMessage());
                $data = ['error' => 'an error has occurred trying create a new sales rule'];
            }
        }

        // Generate coupon code for rule
        try {
            $coupon = Mage::getModel('salesrule/coupon');
            $coupon->setRuleId($rule->getRuleId())
                ->setCode($couponCode)
                ->setUsageLimit($usageLimit)
                ->setIsPrimary(0)
                ->setCreatedAt(time())
                ->setType(1)
                ->save();

            $data = $coupon->getData();

            try {
                if(isset($cartId))
                {
                    $quote = Mage::getModel('sales/quote')->load($cartId);

                    if (! is_null($quote->getId()))
                    {
                        $quote->getShippingAddress()->setCollectShippingRates(true);
                        $quote->setData("coupon_code",$couponCode)->collectTotals();
                        $quote->save();
                    }
                }
            } catch (Exception $e) {
                Mage::log($e->getMessage());
                $data = array('error' => 'an error has occurred trying to add coupon to cart');
            }
        } catch (Exception $e) {
            Mage::log($e->getMessage());
            $data = ['error' => 'an error has occurred trying create a new coupon'];
        }

        return $data;
    }

    /**
     * Delete coupon
     *
     * @param int $couponId
     *
     * @return array
     */
    public function deleteCoupon($couponId)
    {
        try {
            $coupon = Mage::getModel('salesrule/coupon')->load($couponId);
            $rule = Mage::getModel('salesrule/rule')->load($coupon->getRuleId());
            $ruleDescription = $rule->getData('description');
            if (strpos($ruleDescription, 'Yotpo Code') !== false) {
                $coupon->delete();
                $data = [];
            } else {
                $data = ['error' => 'an error has occurred trying delete coupon ID' . $couponId];
            }
        } catch (Exception $e) {
            Mage::log($e->getMessage());
            $data = ['error' => 'an error has occurred trying delete coupon ID' . $couponId];
        }

        return $data;
    }

    /**
     * Prefer order collection
     *
     * @param string $secret
     * @param string $orderStates
     *
     * @return object $orderCollection
     */
    private function getOrderCollaction($secret, $orderStates)
    {
        $authorize = $this->isAuthorized($secret);
        if (!$authorize) {
            return false;
        }

        $storeId = $this->helper->getStoreIdForApiKey($secret);
        $orderCollection = Mage::getModel("sales/order")
            ->getCollection()
            ->addAttributeToFilter('store_id', $storeId);

        $orderStates = explode(',', $orderStates);
        $orderStates = array_filter($orderStates);
        if (!empty($orderStates)) {
            $orderCollection->addAttributeToFilter('state', $orderStates);
        }

        return $orderCollection;
    }

    /**
     * Prefer paging for bulk collaction integration
     *
     * @param object $collection
     * @param int $page
     * @param int $pageSize
     * @param string $type
     *
     * @return array
     */
    private function collactionPaging($collection, $pageSize, $page, $type)
    {
        $data = [];

        $collection->setPageSize($pageSize);
        $collection->setCurPage($page);

        $data['last_page'] = $collection->getLastPageNumber();
        $data['current_page'] = $page;
        $data[$type] = [];

        foreach ($collection as $item) {
            if ($type == 'orders') {
                $data[$type][] = $this->response->prepareOrder($item);
            } else {
                $data[$type][] = $this->response->prepareCustomer($item);
            }
        }

        $collection->clear();

        return $data;
    }

    /**
     * Prefer condition that accepted on createCoupon function
     *
     * @param string $appliesToAttributes
     * @param string $appliesToValues
     * @param string $appliesToAnyOrAllAttributes
     * @param object sales_rule/rule
     *
     * @return object sales_rule/rule
     */
    private function couponConditionsPrefer(
        $appliesToAttributes,
        $appliesToValues,
        $appliesToAnyOrAllAttributes,
        $rule
    )
    {
        $appliesToAttributes = array_filter(explode(",", $appliesToAttributes), 'strlen');
        $appliesToValues = array_filter(explode(",", $appliesToValues), 'strlen');
        //$groupIds = array_filter(explode(",", $groupIds), 'strlen');

        if (is_null($appliesToAnyOrAllAttributes)) {
            $appliesToAnyOrAllAttributes = 'all';
        }

        if (count($appliesToAttributes) > 0) {
            $productFoundCondition = Mage::getModel('salesrule/rule_condition_product_found')
                ->setType('salesrule/rule_condition_product_found')
                ->setValue(1)// 1 == FOUND
                ->setAggregator($appliesToAnyOrAllAttributes); // match ALL or ANY conditions

            foreach ($appliesToAttributes as $index => $appliesToAttribute) {
                $appliesToValue = $appliesToValues[$index];

                $attributeCondition = Mage::getModel('salesrule/rule_condition_product')
                    ->setType('salesrule/rule_condition_product')
                    ->setAttribute($appliesToAttribute)
                    ->setOperator('==')
                    ->setValue($appliesToValue);

                $attributeAction = Mage::getModel('salesrule/rule_condition_product')
                    ->setType('salesrule/rule_condition_product')
                    ->setAttribute($appliesToAttribute)
                    ->setOperator('==')
                    ->setValue($appliesToValue);

                $productFoundCondition->addCondition($attributeCondition);
                $rule->getActions()->addCondition($attributeAction);
            }

            $rule->getConditions()->addCondition($productFoundCondition);
        }

        return $rule;
    }

    /**
     * Apply this new code to the current cart
     *
     * @param string $couponCode
     * @param string $cartId
     *
     */
    private function appleyCouponToCart($cartId, $couponCode)
    {
        try {
            if (isset($cartId)) {
                $quote = Mage::getModel('sales/quote')->load($cartId);

                if (!is_null($quote->getId())) {
                    $quote->getShippingAddress()->setCollectShippingRates(true);
                    $quote->setData("coupon_code", $couponCode)->collectTotals();
                    $quote->save();
                }
            }
        } catch (Exception $e) {
            Mage::log($e->getMessage());
            $data = ['error' => 'an error has occurred trying to add coupon to cart'];
        }
    }
}
