<?php

/**
 * Trust Payments Prestashop
 *
 * This Prestashop module enables to process payments with Trust Payments (https://www.trustpayments.com/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2022 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

if (!defined('_PS_VERSION_')) {
    exit();
}

use PrestaShop\PrestaShop\Core\Domain\Order\CancellationActionType;

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'trustpayments_autoloader.php');
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'trustpayments-sdk' . DIRECTORY_SEPARATOR .
    'autoload.php');
class TrustPayments extends PaymentModule
{

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->name = 'trustpayments';
        $this->tab = 'payments_gateways';
        $this->author = 'Customweb GmbH';
        $this->bootstrap = true;
        $this->need_instance = 0;
        $this->version = '1.2.20';
        $this->displayName = 'Trust Payments';
        $this->description = $this->l('This PrestaShop module enables to process payments with %s.');
        $this->description = sprintf($this->description, 'Trust Payments');
        $this->module_key = '';
        $this->ps_versions_compliancy = array(
            'min' => '1.7',
            'max' => _PS_VERSION_
        );
        parent::__construct();
        $this->confirmUninstall = sprintf(
            $this->l('Are you sure you want to uninstall the %s module?', 'abstractmodule'),
            'Trust Payments'
        );

        // Remove Fee Item
        if (isset($this->context->cart) && Validate::isLoadedObject($this->context->cart)) {
            TrustPaymentsFeehelper::removeFeeSurchargeProductsFromCart($this->context->cart);
        }
        if (!empty($this->context->cookie->tru_error)) {
            $errors = $this->context->cookie->tru_error;
            if (is_string($errors)) {
                $this->context->controller->errors[] = $errors;
            } elseif (is_array($errors)) {
                foreach ($errors as $error) {
                    $this->context->controller->errors[] = $error;
                }
            }
            unset($_SERVER['HTTP_REFERER']); // To disable the back button in the error message
            $this->context->cookie->tru_error = null;
        }
    }

    public function addError($error)
    {
        $this->_errors[] = $error;
    }

    public function getContext()
    {
        return $this->context;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }

    public function install()
    {
        if (!TrustPaymentsBasemodule::checkRequirements($this)) {
            return false;
        }
        if (!parent::install()) {
            return false;
        }
        return TrustPaymentsBasemodule::install($this);
    }

    public function uninstall()
    {
        return parent::uninstall() && TrustPaymentsBasemodule::uninstall($this);
    }

    public function installHooks()
    {
        return TrustPaymentsBasemodule::installHooks($this) && $this->registerHook('paymentOptions') &&
            $this->registerHook('actionFrontControllerSetMedia');
    }

    public function getBackendControllers()
    {
        return array(
            'AdminTrustPaymentsMethodSettings' => array(
                'parentId' => Tab::getIdFromClassName('AdminParentPayment'),
                'name' => 'Trust Payments ' . $this->l('Payment Methods')
            ),
            'AdminTrustPaymentsDocuments' => array(
                'parentId' => -1, // No Tab in navigation
                'name' => 'Trust Payments ' . $this->l('Documents')
            ),
            'AdminTrustPaymentsOrder' => array(
                'parentId' => -1, // No Tab in navigation
                'name' => 'Trust Payments ' . $this->l('Order Management')
            ),
            'AdminTrustPaymentsCronJobs' => array(
                'parentId' => Tab::getIdFromClassName('AdminTools'),
                'name' => 'Trust Payments ' . $this->l('CronJobs')
            )
        );
    }

    public function installConfigurationValues()
    {
        return TrustPaymentsBasemodule::installConfigurationValues();
    }

    public function uninstallConfigurationValues()
    {
        return TrustPaymentsBasemodule::uninstallConfigurationValues();
    }

    public function getContent()
    {
        $output = TrustPaymentsBasemodule::getMailHookActiveWarning($this);
        $output .= TrustPaymentsBasemodule::handleSaveAll($this);
        $output .= TrustPaymentsBasemodule::handleSaveApplication($this);
        $output .= TrustPaymentsBasemodule::handleSaveEmail($this);
        $output .= TrustPaymentsBasemodule::handleSaveFeeItem($this);
        $output .= TrustPaymentsBasemodule::handleSaveDownload($this);
        $output .= TrustPaymentsBasemodule::handleSaveSpaceViewId($this);
        $output .= TrustPaymentsBasemodule::handleSaveOrderStatus($this);
        $output .= TrustPaymentsBasemodule::displayHelpButtons($this);
        return $output . TrustPaymentsBasemodule::displayForm($this);
    }

    public function getConfigurationForms()
    {
        return array(
            TrustPaymentsBasemodule::getEmailForm($this),
            TrustPaymentsBasemodule::getFeeForm($this),
            TrustPaymentsBasemodule::getDocumentForm($this),
            TrustPaymentsBasemodule::getSpaceViewIdForm($this),
            TrustPaymentsBasemodule::getOrderStatusForm($this)
        );
    }

    public function getConfigurationValues()
    {
        return array_merge(
            TrustPaymentsBasemodule::getApplicationConfigValues($this),
            TrustPaymentsBasemodule::getEmailConfigValues($this),
            TrustPaymentsBasemodule::getFeeItemConfigValues($this),
            TrustPaymentsBasemodule::getDownloadConfigValues($this),
            TrustPaymentsBasemodule::getSpaceViewIdConfigValues($this),
            TrustPaymentsBasemodule::getOrderStatusConfigValues($this)
        );
    }

    public function getConfigurationKeys()
    {
        return TrustPaymentsBasemodule::getConfigurationKeys();
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!isset($params['cart']) || !($params['cart'] instanceof Cart)) {
            return;
        }
        $cart = $params['cart'];
        try {
            $possiblePaymentMethods = TrustPaymentsServiceTransaction::instance()->getPossiblePaymentMethods(
                $cart
            );
        } catch (TrustPaymentsExceptionInvalidtransactionamount $e) {
            PrestaShopLogger::addLog($e->getMessage() . " CartId: " . $cart->id, 2, null, 'TrustPayments');
            $paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $paymentOption->setCallToActionText(
                $this->l('There is an issue with your cart, some payment methods are not available.')
            );
            $paymentOption->setAdditionalInformation(
                $this->context->smarty->fetch(
                    'module:trustpayments/views/templates/front/hook/amount_error.tpl'
                )
            );
            $paymentOption->setForm(
                $this->context->smarty->fetch(
                    'module:trustpayments/views/templates/front/hook/amount_error_form.tpl'
                )
            );
            $paymentOption->setModuleName($this->name . "-error");
            return array(
                $paymentOption
            );
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getMessage() . " CartId: " . $cart->id, 1, null, 'TrustPayments');
            return array();
        }
        $shopId = $cart->id_shop;
        $language = Context::getContext()->language->language_code;
        $methods = array();
        foreach ($possiblePaymentMethods as $possible) {
            $methodConfiguration = TrustPaymentsModelMethodconfiguration::loadByConfigurationAndShop(
                $possible->getSpaceId(),
                $possible->getId(),
                $shopId
            );
            if (!$methodConfiguration->isActive()) {
                continue;
            }
            $methods[] = $methodConfiguration;
        }
        $result = array();

        $this->context->smarty->registerPlugin(
            'function',
            'trustpayments_clean_html',
            array(
                'TrustPaymentsSmartyfunctions',
                'cleanHtml'
            )
        );

        foreach (TrustPaymentsHelper::sortMethodConfiguration($methods) as $methodConfiguration) {
            $parameters = TrustPaymentsBasemodule::getParametersFromMethodConfiguration($this, $methodConfiguration, $cart, $shopId, $language);
            $parameters['priceDisplayTax'] = Group::getPriceDisplayMethod(Group::getCurrent()->id);
            $parameters['orderUrl'] = $this->context->link->getModuleLink(
                'trustpayments',
                'order',
                array(),
                true
            );
            $this->context->smarty->assign($parameters);
            $paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $paymentOption->setCallToActionText($parameters['name']);
            $paymentOption->setLogo($parameters['image']);
            $paymentOption->setAction($parameters['link']);
            $paymentOption->setAdditionalInformation(
                $this->context->smarty->fetch(
                    'module:trustpayments/views/templates/front/hook/payment_additional.tpl'
                )
            );
            $paymentOption->setForm(
                $this->context->smarty->fetch(
                    'module:trustpayments/views/templates/front/hook/payment_form.tpl'
                )
            );
            $paymentOption->setModuleName($this->name);
            $result[] = $paymentOption;
        }
        return $result;
    }

    public function hookActionFrontControllerSetMedia($arr)
    {
        if ($this->context->controller->php_self == 'order' || $this->context->controller->php_self == 'cart') {
            $uniqueId = $this->context->cookie->tru_device_id;
            if ($uniqueId == false) {
                $uniqueId = TrustPaymentsHelper::generateUUID();
                $this->context->cookie->tru_device_id = $uniqueId;
            }
            $scriptUrl = TrustPaymentsHelper::getBaseGatewayUrl() . '/s/' . Configuration::get(
                TrustPaymentsBasemodule::CK_SPACE_ID
            ) . '/payment/device.js?sessionIdentifier=' . $uniqueId;
            $this->context->controller->registerJavascript(
                'trustpayments-device-identifier',
                $scriptUrl,
                array(
                    'server' => 'remote',
                    'attributes' => 'async="async"'
                )
            );
        }
        if ($this->context->controller->php_self == 'order') {
            $this->context->controller->registerStylesheet(
                'trustpayments-checkut-css',
                'modules/' . $this->name . '/views/css/frontend/checkout.css'
            );
            $this->context->controller->registerJavascript(
                'trustpayments-checkout-js',
                'modules/' . $this->name . '/views/js/frontend/checkout.js'
            );
            Media::addJsDef(
                array(
                    'trustPaymentsCheckoutUrl' => $this->context->link->getModuleLink(
                        'trustpayments',
                        'checkout',
                        array(),
                        true
                    ),
                    'trustpaymentsMsgJsonError' => $this->l(
                        'The server experienced an unexpected error, you may try again or try to use a different payment method.'
                    )
                )
            );
            if (isset($this->context->cart) && Validate::isLoadedObject($this->context->cart)) {
                try {
                    $jsUrl = TrustPaymentsServiceTransaction::instance()->getJavascriptUrl($this->context->cart);
                    $this->context->controller->registerJavascript(
                        'trustpayments-iframe-handler',
                        $jsUrl,
                        array(
                            'server' => 'remote',
                            'priority' => 45,
                            'attributes' => 'id="trustpayments-iframe-handler"'
                        )
                    );
                } catch (Exception $e) {
                }
            }
        }
        if ($this->context->controller->php_self == 'order-detail') {
            $this->context->controller->registerJavascript(
                'trustpayments-checkout-js',
                'modules/' . $this->name . '/views/js/frontend/orderdetail.js'
            );
        }
    }

    public function hookDisplayTop($params)
    {
        return  TrustPaymentsBasemodule::hookDisplayTop($this, $params);
    }

    public function hookActionAdminControllerSetMedia($arr)
    {
        TrustPaymentsBasemodule::hookActionAdminControllerSetMedia($this, $arr);
        $this->context->controller->addCSS(__PS_BASE_URI__ . 'modules/' . $this->name . '/views/css/admin/general.css');
    }

    public function hasBackendControllerDeleteAccess(AdminController $backendController)
    {
        return $backendController->access('delete');
    }

    public function hasBackendControllerEditAccess(AdminController $backendController)
    {
        return $backendController->access('edit');
    }

    public function hookTrustPaymentsCron($params)
    {
        return TrustPaymentsBasemodule::hookTrustPaymentsCron($params);
    }
    /**
     * Show the manual task in the admin bar.
     * The output is moved with javascript to the correct place as better hook is missing.
     *
     * @return string
     */
    public function hookDisplayAdminAfterHeader()
    {
        $result = TrustPaymentsBasemodule::hookDisplayAdminAfterHeader($this);
        $result .= TrustPaymentsBasemodule::getCronJobItem($this);
        return $result;
    }

    public function hookTrustPaymentsSettingsChanged($params)
    {
        return TrustPaymentsBasemodule::hookTrustPaymentsSettingsChanged($this, $params);
    }

    public function hookActionMailSend($data)
    {
        return TrustPaymentsBasemodule::hookActionMailSend($this, $data);
    }

    public function validateOrder(
        $id_cart,
        $id_order_state,
        $amount_paid,
        $payment_method = 'Unknown',
        $message = null,
        $extra_vars = array(),
        $currency_special = null,
        $dont_touch_amount = false,
        $secure_key = false,
        Shop $shop = null
    ) {
        TrustPaymentsBasemodule::validateOrder($this, $id_cart, $id_order_state, $amount_paid, $payment_method, $message, $extra_vars, $currency_special, $dont_touch_amount, $secure_key, $shop);
    }

    public function validateOrderParent(
        $id_cart,
        $id_order_state,
        $amount_paid,
        $payment_method = 'Unknown',
        $message = null,
        $extra_vars = array(),
        $currency_special = null,
        $dont_touch_amount = false,
        $secure_key = false,
        Shop $shop = null
    ) {
        parent::validateOrder($id_cart, $id_order_state, $amount_paid, $payment_method, $message, $extra_vars, $currency_special, $dont_touch_amount, $secure_key, $shop);
    }

    public function hookDisplayOrderDetail($params)
    {
        return TrustPaymentsBasemodule::hookDisplayOrderDetail($this, $params);
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        TrustPaymentsBasemodule::hookDisplayBackOfficeHeader($this, $params);
    }

    public function hookDisplayAdminOrderLeft($params)
    {
        return TrustPaymentsBasemodule::hookDisplayAdminOrderLeft($this, $params);
    }

    public function hookDisplayAdminOrderTabOrder($params)
    {
        return TrustPaymentsBasemodule::hookDisplayAdminOrderTabOrder($this, $params);
    }

    public function hookDisplayAdminOrderMain($params)
    {
        return TrustPaymentsBasemodule::hookDisplayAdminOrderMain($this, $params);
    }

    public function hookDisplayAdminOrderTabLink($params)
    {
        return TrustPaymentsBasemodule::hookDisplayAdminOrderTabLink($this, $params);
    }

    public function hookDisplayAdminOrderContentOrder($params)
    {
        return TrustPaymentsBasemodule::hookDisplayAdminOrderContentOrder($this, $params);
    }

    public function hookDisplayAdminOrderTabContent($params)
    {
        return TrustPaymentsBasemodule::hookDisplayAdminOrderTabContent($this, $params);
    }

    public function hookDisplayAdminOrder($params)
    {
        return TrustPaymentsBasemodule::hookDisplayAdminOrder($this, $params);
    }

    public function hookActionAdminOrdersControllerBefore($params)
    {
        return TrustPaymentsBasemodule::hookActionAdminOrdersControllerBefore($this, $params);
    }

    public function hookActionObjectOrderPaymentAddBefore($params)
    {
        TrustPaymentsBasemodule::hookActionObjectOrderPaymentAddBefore($this, $params);
    }

    public function hookActionOrderEdited($params)
    {
        TrustPaymentsBasemodule::hookActionOrderEdited($this, $params);
    }

    public function hookActionOrderGridDefinitionModifier($params)
    {
        TrustPaymentsBasemodule::hookActionOrderGridDefinitionModifier($this, $params);
    }

    public function hookActionOrderGridQueryBuilderModifier($params)
    {
        TrustPaymentsBasemodule::hookActionOrderGridQueryBuilderModifier($this, $params);
    }

    public function hookActionProductCancel($params)
    {
        // check version too here to only run on > 1.7.7 for now
        // as there is some overlap in functionality with some previous versions 1.7+
        if ($params['action'] === CancellationActionType::PARTIAL_REFUND && version_compare(_PS_VERSION_, '1.7.7', '>=')) {
            $idOrder = Tools::getValue('id_order');
            $refundParameters = Tools::getAllValues();

            $order = $params['order'];

            if (!Validate::isLoadedObject($order) || $order->module != $this->name) {
                return;
            }

            $strategy = TrustPaymentsBackendStrategyprovider::getStrategy();
            if ($strategy->isVoucherOnlyTrustPayments($order, $refundParameters)) {
                return;
            }

            // need to manually set this here as it's expected downstream
            $refundParameters['partialRefund'] = true;

            $backendController = Context::getContext()->controller;
            $editAccess = 0;

            $access = Profile::getProfileAccess(
                Context::getContext()->employee->id_profile,
                (int) Tab::getIdFromClassName('AdminOrders')
            );
            $editAccess = isset($access['edit']) && $access['edit'] == 1;

            if ($editAccess) {
                try {
                    $parsedData = $strategy->simplifiedRefund($refundParameters);
                    TrustPaymentsServiceRefund::instance()->executeRefund($order, $parsedData);
                } catch (Exception $e) {
                    $backendController->errors[] = TrustPaymentsHelper::cleanExceptionMessage($e->getMessage());
                }
            } else {
                $backendController->errors[] = Tools::displayError('You do not have permission to delete this.');
            }
        }
    }
}
