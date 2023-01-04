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

/**
 * Webhook processor to handle transaction completion state transitions.
 */
class TrustPaymentsBackendStrategy1774 extends TrustPaymentsBackendDefaultstrategy {

    public function isVoucherOnlyTrustPayments(Order $order, array $postData)
    {
        return isset($postData['cancel_product']['voucher']) && $postData['cancel_product']['voucher'] == 1;
    }

}
