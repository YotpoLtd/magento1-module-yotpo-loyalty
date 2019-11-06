<?php
/**
 * @category    Swell
 * @package     Swell_Swell
 * @copyright   Copyright (c) Swell Rewards (https://www.swellrewards.com/)
 */

/**
 * Class Swell_Swell_Model_Quote
 */
class Swell_Swell_Model_Quote extends Mage_Core_Model_Abstract
{
    public function applyCoupon($cartId, $code, $codesToRemove)
    {
        $coupon = Mage::getModel('salesrule/coupon')->load($code, 'code');
        if (is_null($coupon->getId())) {
            return ['error' => 'coupon not found'];
        }

        $quote = Mage::getModel('sales/quote')->load($cartId);

        if (is_null($quote->getId())) {
            return ['error' => 'quote id not found'];
        }

        $existingCodes = $quote->getData("coupon_code");
        $codes = [];
        $codes[] = $code;

        if (isset($codesToRemove) && isset($existingCodes)) {
            $codesToRemove = strtoupper(explode(",", $codesToRemove));
            $existingCodes = strtoupper(explode(",", $existingCodes));

            foreach ($existingCodes as $existingCode) {
                if (!in_array($existingCode, $codesToRemove)) {
                    $codes[] = $existingCode;
                }
            }
        }

        $couponCode = implode(",", $codes);

        try {
            $quote->getShippingAddress()->setCollectShippingRates(true);
            $quote->setData("coupon_code", $couponCode)->collectTotals()->save();
            $data = $quote->getData();
        } catch (Exception $e) {
            Mage::log($e->getMessage());
            $data = ['error' => 'an error has occurred adding coupon to cart'];
        }

        return $data;
    }

    /**
     * Add product to quote
     *
     * @param string $secret
     * @param int $cartId
     * @param string $sku
     * @param float $price
     * @param int $qty
     *
     * @return array
     */
    public function add($secret, $cartId, $sku, $price, $qty, $redemptionId, $pointsUsed)
    {
        if (!Mage::helper('swell/data')->apiKeyExists($secret)) {
            return false;
        }

        $storeId = Mage::helper('swell/data')->getStoreIdForApiKey($secret);

        $quote = Mage::getModel('sales/quote')->load($cartId);

        if (is_null($quote->getId())) {
            return ['error' => 'quote id not found'];
        }

        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product');
        $product->setStoreId($storeId);
        $product->load($product->getIdBySku($sku));

        if (is_null($product->getId())) {
            return ['error' => 'product not found'];
        }

        try {
            $quoteItem = Mage::getModel('sales/quote_item');

            $quoteItem->addOption(
                new Varien_Object(
                    [
                        'product' => $product,
                        'code' => 'info_buyRequest',
                        'value' => serialize(['qty' => $qty])
                    ]
                )
            );

            $quoteItem->setProduct($product);

            $quoteItem->setQty($qty)
                ->setCustomPrice($price)
                ->setOriginalCustomPrice($price)
                ->setSwellRedemptionId($redemptionId)
                ->setSwellPointsUsed($pointsUsed)
                ->setWeeeTaxApplied(
                    'a:0:{}'
                )// Set WeeTaxApplied Value by default so there are no "warnings" later on during invoice creation
                ->setStoreId($storeId);

            // With the freeproduct_uniqid option, items of the same free product won't get combined.
            $quoteItem->addOption(
                new Varien_Object(
                    [
                        'product' => $product,
                        'code' => 'swell_api_added_product',
                        'value' => uniqid(null, true)
                    ]
                )
            );

            $quote->addItem($quoteItem);

            $taxCalculationModel = Mage::getSingleton('tax/calculation');
            /* @var $taxCalculationModel Mage_Tax_Model_Calculation */
            $request = $taxCalculationModel->getRateRequest(
                $quote->getShippingAddress(),
                $quote->getBillingAddress(),
                $quote->getCustomerTaxClassId(),
                $quoteItem->getStore()
            );
            $rate = $taxCalculationModel->getRate(
                $request->setProductClassId($quoteItem->getProduct()->getTaxClassId())
            );
            $quoteItem->setTaxPercent($rate);

            $quote->save();

            $data = $quote->getData();
        } catch (Exception $e) {
            Mage::log($e->getMessage());
            $data = ['error' => 'an error has occurred adding product to cart'];
        }

        return $data;
    }
}
