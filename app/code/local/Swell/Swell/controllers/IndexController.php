<?php
/**
 * @category    Swell
 * @package     Swell_Swell
 * @copyright   Copyright (c) Swell Rewards (https://www.swellrewards.com/)
 */

/**
 * Class Swell_Swell_IndexController
 */
class Swell_Swell_IndexController extends Mage_Core_Controller_Front_Action
{
    /**
     * @var $apiRequest
     */
    protected $apiRequest;

    /**
     * Initialize and authenticate each API request
     *
     */
    protected function _construct()
    {
        $params = $this->getRequest()->getParams();

        // Convert GET parameters to an object
        // to use getters and setters
        $this->apiRequest = new Varien_Object();
        $this->apiRequest->setData($params);

        $apiKey = $this->apiRequest->getSharedSecret();

        $authorized = Mage::getSingleton('swell/account')
            ->isAuthorized($apiKey);

        if (!$authorized) {
            $this->sendResponse(['error' => 'invalid request']);
        }
    }

    /**
     * Send JSON response back to client
     *
     * @param array $response
     */
    public function sendResponse($response = [])
    {
        $this->getResponse()->clearHeaders()->setHeader(
            'Content-type',
            'application/json'
        );

        $this->getResponse()->setBody(
            Mage::helper('core')->jsonEncode($response)
        );

        $this->getResponse()->sendResponse();

        exit;
    }

    /**
     * Get order count filtered by order state
     *
     * GET /swell/index/order_count
     *
     * @param shared_secret
     * @param state
     */
    public function order_countAction()
    {
        if (!$this->apiRequest->getState()) {
            $this->sendResponse(['error' => 'invalid parmas']);
            return;
        }

        $account = Mage::getSingleton('swell/account');
        $orderCount = $account->getOrderCount(
            $this->apiRequest->getSharedSecret(),
            $this->apiRequest->getState()
        );

        $this->sendResponse(
            ['orders' => $orderCount]
        );
    }

    /**
     * Get order count for past 30 days filtered by order state
     *
     * GET /swell/index/thirty_day_order_volume
     *
     * @param shared_secret
     * @param state
     */
    public function thirty_day_order_volumeAction()
    {
        if (!$this->apiRequest->getState()) {
            $this->sendResponse(['error' => 'invalid parmas']);
            return;
        }

        $account = Mage::getSingleton('swell/account');
        $orderCount = $account->getOrderCount(
            $this->apiRequest->getSharedSecret(),
            $this->apiRequest->getState(),
            true
        );

        $this->sendResponse(
            ['orders' => $orderCount]
        );
    }

    /**
     * Get order collection by order state using pagination
     *
     * GET /swell/index/orders
     *
     * @param shared_secret
     * @param state
     * @param page
     * @param page_size
     */
    public function ordersAction()
    {
        if (!$this->apiRequest->getState()) {
            $this->sendResponse(['error' => 'invalid parmas']);
            return;
        }

        $page = $this->apiRequest->getPage();
        if (!is_numeric($page)) {
            $page = 1;
        }

        $pageSize = $this->apiRequest->getPageSize();
        if (!is_numeric($pageSize)) {
            $pageSize = 250;
        }

        $account = Mage::getSingleton('swell/account');
        $orders = $account->getOrders(
            $this->apiRequest->getSharedSecret(),
            $this->apiRequest->getState(),
            $page,
            $pageSize
        );

        $this->sendResponse($orders);
    }

    /**
     * Get order by order id
     *
     * GET /swell/index/order
     *
     * @param shared_secret
     * @param id
     */
    public function orderAction()
    {
        $orderId = $this->apiRequest->getId();
        if (!is_numeric($orderId)) {
            $this->sendResponse(['error' => 'invalid parmas']);
            return;
        }

        $account = Mage::getSingleton('swell/account');
        $order = $account->getOrder(
            $this->apiRequest->getSharedSecret(),
            $orderId
        );

        $this->sendResponse($order);
    }

    /**
     * Get customer collection using pagination
     *
     * GET /swell/index/customers
     *
     * @param shared_secret
     * @param page
     * @param page_size
     */
    public function customersAction()
    {
        $page = $this->apiRequest->getPage();
        if (!is_numeric($page)) {
            $page = 1;
        }

        $pageSize = $this->apiRequest->getPageSize();
        if (!is_numeric($pageSize)) {
            $pageSize = 250;
        }

        $account = Mage::getSingleton('swell/account');
        $customers = $account->getCustomers(
            $this->apiRequest->getSharedSecret(),
            $page,
            $pageSize
        );

        $this->sendResponse($customers);
    }

    /**
     * Get customer
     *
     * GET /swell/index/customer
     *
     * @param shared_secret
     * @param id
     * @param email
     * @throws Zend_Validate_Exception
     */
    public function customerAction()
    {
        $orderId = $this->apiRequest->getId();
        if (
            !Zend_Validate::is(trim($this->apiRequest->getEmail()), 'EmailAddress') ||
            !is_numeric($orderId)
        ) {
            $this->sendResponse(['error' => 'invalid parmas']);
            return;
        }

        $account = Mage::getSingleton('swell/account');
        $customer = $account->getCustomer(
            $this->apiRequest->getSharedSecret(),
            $this->apiRequest->getId(),
            $this->apiRequest->getEmail()
        );

        $this->sendResponse($customer);
    }

    /**
     * Subscribe by email
     *
     * GET /swell/index/create_subscriber
     *
     * @param shared_secret
     * @param email
     * @throws Zend_Validate_Exception
     */
    public function create_subscriberAction()
    {
        if (
        !Zend_Validate::is(trim($this->apiRequest->getEmail()), 'EmailAddress')
        ) {
            $this->sendResponse(['error' => 'invalid parmas']);
            return;
        }

        $account = Mage::getSingleton('swell/account');
        $subscriber = $account->subscribe(
            $this->apiRequest->getSharedSecret(),
            $this->apiRequest->getEmail()
        );

        $this->sendResponse($subscriber);
    }

    /**
     * Create coupon
     *
     * GET /swell/index/create_coupon
     *
     * @param secret
     * @param couponCode
     * @param thirdPartyId
     * @param name
     * @param discountType
     * @param amount
     * @param usageLimit
     * @param oncePerCustomer
     * @param groupIds
     * @param freeShippingLessThanCents
     * @param cartGreaterThanCents
     * @param appliesToAttributes
     * @param appliesToValues
     * @param appliesToAnyOrAllAttributes
     * @param quoteId
     */
    public function create_couponAction()
    {
        if (!$this->apiRequest->getCode()) {
            $this->sendResponse(['error' => 'invalid parmas']);
            return;
        }

        $account = Mage::getSingleton('swell/account');

        $coupon = $account->createCoupon(
            $this->apiRequest->getSharedSecret(),
            $this->apiRequest->getCode(),
            $this->apiRequest->getThirdPartyId(),
            $this->apiRequest->getName(),
            $this->apiRequest->getDiscountType(),
            $this->apiRequest->getValue(),
            $this->apiRequest->getUsageLimit(),
            $this->apiRequest->getOncePerCustomer(),
            $this->apiRequest->getGroupIds(),
            $this->apiRequest->getFreeShippingLessThanCents(),
            $this->apiRequest->getCartGreaterThanCents(),
            $this->apiRequest->getAppliesToAttributes(),
            $this->apiRequest->getAppliesToValues(),
            $this->apiRequest->getAppliesToAnyOrAllAttributes(),
            $this->apiRequest->getQuoteId()
        );

        $this->sendResponse($coupon);
    }

    /**
     * Delete coupon
     *
     * GET /swell/index/create_coupon
     *
     * @param id
     */
    public function delete_couponAction()
    {
        $couponId = $this->apiRequest->getId();
        if (!is_numeric($couponId)) {
            $this->sendResponse(['error' => 'invalid parmas']);
            return;
        }

        $account = Mage::getSingleton('swell/account');
        $coupon = $account->deleteCoupon($couponId);

        $this->sendResponse($coupon);
    }

    public function testAction()
    {
        $this->sendResponse(['success' => true, 'version' => '24-11-2019']);
    }

    public function manual_cronAction()
    {
        Mage::getModel('swell/observer')->processPendingNotifications();

        $this->sendResponse();
    }
}
