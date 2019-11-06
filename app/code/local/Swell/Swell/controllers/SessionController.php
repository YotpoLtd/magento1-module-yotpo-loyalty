<?php
/**
 * @category    Swell
 * @package     Swell_Swell
 * @copyright   Copyright (c) Swell Rewards (https://www.swellrewards.com/)
 */

/**
 * Class Swell_Swell_IndexController
 */

require_once 'Mage/Checkout/controllers/CartController.php';

class Swell_Swell_SessionController extends Mage_Checkout_CartController
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
    }

    /**
     * Send JSON response back to client
     *
     * @param array $response
     * @return bool
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

    public function respondWithCart()
    {
        $quote = $this->_getQuote();

        $cart = [
            "items" => [],
            "quoteId" => $quote->getId()
        ];

        $couponCode = $quote->getData("coupon_code");

        if (isset($couponCode)) {
            $cart["coupons"] = explode(",", $couponCode);
        } else {
            $cart["coupons"] = [];
        }

        foreach ($quote->getAllVisibleItems() as $item) {
            $product = $item->getProduct();

            $cart["items"][] = [
                "name" => $product->getName(),
                "sku" => $product->getSku(),
                "price" => $item->getPrice(),
                "custom_price" => $item->getCustomPrice(),
                "qty" => $item->getQty(),
                "swell_redemption_id" => $item->getSwellRedemptionId(),
                "swell_points_used" => $item->getSwellPointsUsed()
            ];
        }

        $this->sendResponse($cart);
    }

    public function savecartAction()
    {
        $cart = Mage::getSingleton('checkout/cart');
        $cart->save();
        $this->respondWithCart();
    }

    public function getcartAction()
    {
        $this->respondWithCart();
    }

    public function couponTestAction()
    {
        $code = (string)$this->getRequest()->getParam('coupon_code');
        $codesToRemove = (string)$this->getRequest()->getParam('swell_coupon_codes');

        $this->codesHandler($code, $codesToRemove);
        $this->respondWithCart();
    }

    public function removeCodeAction()
    {
        $codesToRemove = (string)$this->getRequest()->getParam('swell_coupon_code_cancel');

        $this->codesHandler($codesToRemove);
        $this->_goBack();
    }

    public function couponAction()
    {
        $code = (string)$this->getRequest()->getParam('coupon_code');
        $codesToRemove = (string)$this->getRequest()->getParam('swell_coupon_codes');

        $this->codesHandler($code, $codesToRemove);
        $this->_goBack();
    }

    private function codesHandler($code = null, $codesToRemove)
    {
        $existingCodes = $this->_getQuote()->getData("coupon_code");
        $codes = [];

        if ($code) {
            $codes[] = $code;
        }

        if (isset($codesToRemove) && isset($existingCodes)) {
            $codesToRemove = explode(",", strtoupper($codesToRemove));
            $existingCodes = explode(",", strtoupper($existingCodes));

            foreach ($existingCodes as $existingCode) {
                if (!in_array($existingCode, $codesToRemove)) {
                    $codes[] = $existingCode;
                }
            }
        }

        $couponCode = implode(",", $codes);

        $this->_getQuote()->getShippingAddress()->setCollectShippingRates(true);
        $this->_getQuote()->setData("coupon_code", $couponCode)
            ->collectTotals()
            ->save();
    }

    public function snippet_responseAction()
    {
        $guid = Mage::helper('swell')->getGuidForStoreId();
        if (empty($guid)) {
          return false;
        }

        $script_url = Mage::helper('swell')->getSwellCdnHost() . "/loader/" . $guid . ".js";
        $data = null;

        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
          $customer_data = Mage::getSingleton('customer/session')->getCustomer();
          $customer_group = Mage::getModel('customer/group')->load($customer_data->getData('group_id'))->getCode();
          $data = "data-authenticated='true' data-email='" . $customer_data->getData('email') . "' data-id='" . $customer_data->getData('entity_id') . "' data-tags='[" . $customer_group . "]'";
        }

        $html = "<div id='swell-customer-identification' " . $data . " style='display:none'></div><script type='text/javascript' src='" . $script_url . "'></script>";
        echo $html;
    }
}
