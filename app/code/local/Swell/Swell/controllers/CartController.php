<?php
/**
 * @category    Swell
 * @package     Swell_Swell
 * @copyright   Copyright (c) Swell Rewards (https://www.swellrewards.com/)
 */

require_once Mage::getModuleDir('controllers', 'Swell_Swell') . DS . 'IndexController.php';

/**
 * Class Swell_Swell_CartController
 *
 * TODO: if possible refactor IndexController as an abstract class
 */
class Swell_Swell_CartController extends Swell_Swell_IndexController
{

    /**
     * Add product to cart
     *
     * GET /swell/cart/add
     *
     * @param shared_secret
     * @param quote_id
     * @param sku
     * @param price
     * @param qty
     * @param redemption_id
     * @param points_used
     */
    public function addAction()
    {
        $price = $this->apiRequest->getPrice();
        if (!is_numeric($price)) {
            $price = 0.0;
        }

        $qty = $this->apiRequest->getQty();
        if (!is_numeric($qty)) {
            $qty = 1;
        }

        $redemptionId = intval($this->apiRequest->getRedemptionId());
        $pointsUsed = intval($this->apiRequest->getPointsUsed());

        // TODO: implement parameter validation
        $quote = Mage::getSingleton('swell/quote');
        $quote = $quote->add(
            $this->apiRequest->getSharedSecret(),
            $this->apiRequest->getQuoteId(),
            $this->apiRequest->getSku(),
            $price,
            $qty,
            $redemptionId,
            $pointsUsed
        );

        $this->sendResponse($quote);
    }

    /**
     * Add coupon to cart
     *
     * GET /swell/cart/coupon
     *
     * @param shared_secret
     * @param quote_id
     * @param code
     * @param codes_to_remove
     */
    public function couponAction()
    {
        $quote = Mage::getSingleton('swell/quote');
        $quote = $quote->applyCoupon(
            $this->apiRequest->getQuoteId(),
            $this->apiRequest->getCouponCode(),
            $this->apiRequest->getSwellCouponCodes()
        );

        $this->sendResponse($quote);
    }
}
