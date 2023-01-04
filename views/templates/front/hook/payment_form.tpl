{*
 * Trust Payments Prestashop
 *
 * This Prestashop module enables to process payments with Trust Payments (https://www.trustpayments.com/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2023 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
<form action="{$orderUrl|escape:'html'}" class="trustpayments-payment-form" data-method-id="{$methodId|escape:'html':'UTF-8'}">
	<div id="trustpayments-{$methodId|escape:'html':'UTF-8'}">
		<input type="hidden" id="trustpayments-iframe-possible-{$methodId|escape:'html':'UTF-8'}" name="trustpayments-iframe-possible-{$methodId|escape:'html':'UTF-8'}" value="false" />
		<div id="trustpayments-loader-{$methodId|escape:'html':'UTF-8'}" class="trustpayments-loader"></div>
	</div>
</form>