<?php
/**
 * Trust Payments Prestashop
 *
 * This Prestashop module enables to process payments with Trust Payments (https://www.trustpayments.com/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2023 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

class TrustPaymentsFeehelper
{
    public static function removeFeeSurchargeProductsFromCart(Cart $cart)
    {
        $surchargeProductId = Configuration::get(TrustPaymentsBasemodule::CK_SURCHARGE_ITEM);
        $surchargeProduct = new Product($surchargeProductId, false, Configuration::get('PS_LANG_DEFAULT'), $cart->id_shop);
        if (Validate::isLoadedObject($surchargeProduct)) {
            $defaultAttributeId = Product::getDefaultAttribute($surchargeProductId);
            SpecificPrice::deleteByIdCart($cart->id, $surchargeProductId, $defaultAttributeId);
            $cart->deleteProduct($surchargeProductId, $defaultAttributeId);
        }
        $feeProductId = Configuration::get(TrustPaymentsBasemodule::CK_FEE_ITEM);
        $feeProduct = new Product($feeProductId, false, Configuration::get('PS_LANG_DEFAULT'), $cart->id_shop);
        if (Validate::isLoadedObject($feeProduct)) {
            $defaultAttributeId = Product::getDefaultAttribute($feeProductId);

            SpecificPrice::deleteByIdCart($cart->id, $feeProductId, $defaultAttributeId);
            $cart->deleteProduct($feeProductId, $defaultAttributeId);
        }

        TrustPaymentsVersionadapter::clearCartRuleStaticCache();
    }

    public static function addSurchargeProductToCart(Cart $cart)
    {
        $surchargeProductId = Configuration::get(TrustPaymentsBasemodule::CK_SURCHARGE_ITEM);
        $surchargeProduct = new Product($surchargeProductId, false, Configuration::get('PS_LANG_DEFAULT'), $cart->id_shop);
        if (Validate::isLoadedObject($surchargeProduct)) {
            $defaultAttributeId = Product::getDefaultAttribute($surchargeProductId);
            $surchargeValues = self::getSurchargeValues($cart);
            if ($surchargeValues['surcharge_total'] > 0) {
                $cart->updateQty(1, $surchargeProductId, $defaultAttributeId);
                $specificPrice = new SpecificPrice();
                $specificPrice->id_product = (int) $surchargeProductId;
                $specificPrice->id_product_attribute = (int) $defaultAttributeId;
                $specificPrice->id_cart = (int) $cart->id;
                $specificPrice->id_shop = (int) $cart->id_shop;
                $specificPrice->id_currency = $cart->id_currency;
                $specificPrice->id_country = 0;
                $specificPrice->id_group = 0;
                $specificPrice->id_customer = 0;
                $specificPrice->from_quantity = 1;
                $specificPrice->price = $surchargeValues['surcharge_total'];
                $specificPrice->reduction_type = 'amount';
                $specificPrice->reduction_tax = 1;
                $specificPrice->reduction = 0;
                $specificPrice->from = date("Y-m-d H:i:s", time() - 3600);
                $specificPrice->to = date("Y-m-d H:i:s", time() + 48 * 3600);
                $specificPrice->add();
            }
        }

        TrustPaymentsVersionadapter::clearCartRuleStaticCache();
    }

    public static function addFeeProductToCart(
        TrustPaymentsModelMethodconfiguration $methodConfiguration,
        Cart $cart
    ) {
        $feeProductId = Configuration::get(TrustPaymentsBasemodule::CK_FEE_ITEM);
        $feeProduct = new Product($feeProductId, false, Configuration::get('PS_LANG_DEFAULT'), $cart->id_shop);

        if (Validate::isLoadedObject($feeProduct)) {
            $defaultAttributeId = Product::getDefaultAttribute($feeProductId);
            $feeValues = self::getFeeValues($cart, $methodConfiguration);

            if ($feeValues['fee_total'] > 0) {
                $cart->updateQty(1, $feeProductId, $defaultAttributeId, false, 'up', 0, null, true, true);
                $specificPrice = new SpecificPrice();
                $specificPrice->id_product = (int) $feeProductId;
                $specificPrice->id_product_attribute = (int) $defaultAttributeId;
                $specificPrice->id_cart = (int) $cart->id;
                $specificPrice->id_shop = (int) $cart->id_shop;
                $specificPrice->id_currency = $cart->id_currency;
                $specificPrice->id_country = 0;
                $specificPrice->id_group = 0;
                $specificPrice->id_customer = 0;
                $specificPrice->from_quantity = 1;
                $specificPrice->price = $feeValues['fee_total'];
                $specificPrice->reduction_type = 'amount';
                $specificPrice->reduction_tax = 1;
                $specificPrice->reduction = 0;
                $specificPrice->from = date("Y-m-d H:i:s", time() - 3600);
                $specificPrice->to = date("Y-m-d H:i:s", time() + 48 * 3600);
                $specificPrice->add();
            }
        }
        TrustPaymentsVersionadapter::clearCartRuleStaticCache();
    }

    public static function getSurchargeValues(Cart $cart)
    {
        $surchargeProductId = Configuration::get(TrustPaymentsBasemodule::CK_SURCHARGE_ITEM);
        $surchargeProduct = new Product($surchargeProductId, false, Configuration::get('PS_LANG_DEFAULT'), $cart->id_shop);
        if (! Validate::isLoadedObject($surchargeProduct)) {
            return array(
                'surcharge_total' => 0,
                'surcharge_total_wt' => 0
            );
        }
        $configuration = TrustPaymentsVersionadapter::getConfigurationInterface();

        $amount = (float) Configuration::get(TrustPaymentsBasemodule::CK_SURCHARGE_AMOUNT);

        $amountConverted = Tools::convertPrice($amount, Currency::getCurrencyInstance((int) $cart->id_currency));

        $surchargeBaseType = Configuration::get(TrustPaymentsBasemodule::CK_SURCHARGE_BASE);

        switch ($surchargeBaseType) {
            case TrustPaymentsBasemodule::TOTAL_MODE_BOTH_INC:
                $taxes = true;
                $surchargeType = Cart::BOTH;
                break;
            case TrustPaymentsBasemodule::TOTAL_MODE_BOTH_EXC:
                $taxes = false;
                $surchargeType = Cart::BOTH;
                break;
            case TrustPaymentsBasemodule::TOTAL_MODE_WITHOUT_SHIPPING_INC:
                $taxes = true;
                $surchargeType = Cart::BOTH_WITHOUT_SHIPPING;
                break;
            case TrustPaymentsBasemodule::TOTAL_MODE_WITHOUT_SHIPPING_EXC:
                $taxes = false;
                $surchargeType = Cart::BOTH_WITHOUT_SHIPPING;
                break;
            case TrustPaymentsBasemodule::TOTAL_MODE_PRODUCTS_INC:
                $taxes = true;
                $surchargeType = Cart::ONLY_PRODUCTS;
                break;
            case TrustPaymentsBasemodule::TOTAL_MODE_PRODUCTS_EXC:
                $taxes = false;
                $surchargeType = Cart::ONLY_PRODUCTS;
                break;
        }

        $total = $cart->getOrderTotal($taxes, $surchargeType);
        $surchargeBase = (float) Configuration::get(TrustPaymentsBasemodule::CK_SURCHARGE_TOTAL);
        $surchargeBaseConverted = Tools::convertPrice(
            $surchargeBase,
            Currency::getCurrencyInstance((int) $cart->id_currency)
        );

        $computePrecision = $configuration->get('_PS_PRICE_COMPUTE_PRECISION_');

        if (Tools::ps_round($total, $computePrecision) >= Tools::ps_round($surchargeBaseConverted, $computePrecision)) {
            return array(
                'surcharge_total' => 0,
                'surcharge_total_wt' => 0
            );
        }

        $product = new Product($surchargeProductId);
        $taxGroup = $product->getIdTaxRulesGroup();
        $result = array(
            'surcharge_total' => Tools::ps_round($amountConverted, $computePrecision),
            'surcharge_total_wt' => Tools::ps_round($amountConverted, $computePrecision)
        );

        if ($taxGroup != 0) {
            $addressFactory = TrustPaymentsVersionadapter::getAddressFactory();
            $taxAddressType = Configuration::get('PS_TAX_ADDRESS_TYPE');
            if ($taxAddressType == 'id_address_invoice') {
                $idAddress = (int) $cart->id_address_invoice;
            } else {
                $idAddress = (int) $cart->id_address_delivery;
            }
            $address = $addressFactory->findOrCreate($idAddress, true);
            $taxCalculator = TaxManagerFactory::getManager($address, $taxGroup)->getTaxCalculator();

            if ((int) Configuration::get(TrustPaymentsBasemodule::CK_SURCHARGE_TAX)) {
                $result['surcharge_total_wt'] = Tools::ps_round(
                    $taxCalculator->addTaxes($amountConverted),
                    $computePrecision
                );
            } else {
                $result['surcharge_total'] = Tools::ps_round(
                    $taxCalculator->removeTaxes($amountConverted),
                    $computePrecision
                );
            }
        }
        return $result;
    }

    public static function getFeeValues(Cart $cart, TrustPaymentsModelMethodconfiguration $methodConfiguration)
    {
        $feeProductId = Configuration::get(TrustPaymentsBasemodule::CK_FEE_ITEM);
        $feeProduct = new Product($feeProductId, false, Configuration::get('PS_LANG_DEFAULT'), $cart->id_shop);
        if (! Validate::isLoadedObject($feeProduct)) {
            return array(
                'fee_total' => 0,
                'fee_total_wt' => 0
            );
        }

        $configuration = TrustPaymentsVersionadapter::getConfigurationInterface();

        $fixed = $methodConfiguration->getFeeFixed();

        $feeFixedConverted = Tools::convertPrice($fixed, Currency::getCurrencyInstance((int) $cart->id_currency));

        $rate = $methodConfiguration->getFeeRate();
        $feeBaseType = $methodConfiguration->getFeeBase();

        switch ($feeBaseType) {
            case TrustPaymentsBasemodule::TOTAL_MODE_BOTH_INC:
                $taxes = true;
                $feeType = Cart::BOTH;
                break;
            case TrustPaymentsBasemodule::TOTAL_MODE_BOTH_EXC:
                $taxes = false;
                $feeType = Cart::BOTH;
                break;
            case TrustPaymentsBasemodule::TOTAL_MODE_WITHOUT_SHIPPING_INC:
                $taxes = true;
                $feeType = Cart::BOTH_WITHOUT_SHIPPING;
                break;
            case TrustPaymentsBasemodule::TOTAL_MODE_WITHOUT_SHIPPING_EXC:
                $taxes = false;
                $feeType = Cart::BOTH_WITHOUT_SHIPPING;
                break;
            case TrustPaymentsBasemodule::TOTAL_MODE_PRODUCTS_INC:
                $taxes = true;
                $feeType = Cart::ONLY_PRODUCTS;
                break;
            case TrustPaymentsBasemodule::TOTAL_MODE_PRODUCTS_EXC:
                $taxes = false;
                $feeType = Cart::ONLY_PRODUCTS;
                break;
        }

        $feeBase = $cart->getOrderTotal($taxes, $feeType);
        $feeRateAmount = $feeBase * $rate / 100;

        $feeTotal = $feeFixedConverted + $feeRateAmount;

        $product = new Product($feeProductId);

        $taxGroup = $product->getIdTaxRulesGroup();
        $computePrecision = $configuration->get('_PS_PRICE_COMPUTE_PRECISION_');

        $result = array(
            'fee_total' => Tools::ps_round($feeTotal, $computePrecision),
            'fee_total_wt' => Tools::ps_round($feeTotal, $computePrecision)
        );

        if ($taxGroup != 0) {
            $addressFactory = TrustPaymentsVersionadapter::getAddressFactory();
            $taxAddressType = Configuration::get('PS_TAX_ADDRESS_TYPE');
            if ($taxAddressType == 'id_address_invoice') {
                $idAddress = (int) $cart->id_address_invoice;
            } else {
                $idAddress = (int) $cart->id_address_delivery;
            }
            $address = $addressFactory->findOrCreate($idAddress, true);
            $taxCalculator = TaxManagerFactory::getManager($address, $taxGroup)->getTaxCalculator();
            if ($methodConfiguration->isFeeAddTax()) {
                $result['fee_total_wt'] = Tools::ps_round($taxCalculator->addTaxes($feeTotal), $computePrecision);
            } else {
                $result['fee_total'] = Tools::ps_round($taxCalculator->removeTaxes($feeTotal), $computePrecision);
            }
        }
        return $result;
    }
}
