{*
 * Trust Payments Prestashop
 *
 * This Prestashop module enables to process payments with Trust Payments (https://www.trustpayments.com/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2022 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
<div id="trustpayments_documents" style="display:none">
{if !empty($trustPaymentsInvoice)}
	<a target="_blank" href="{$trustPaymentsInvoice|escape:'html':'UTF-8'}">{l s='Download your %name% invoice as a PDF file.' sprintf=['%name%' => 'Trust Payments'] mod='trustpayments'}</a>
{/if}
{if !empty($trustPaymentsPackingSlip)}
	<a target="_blank" href="{$trustPaymentsPackingSlip|escape:'html':'UTF-8'}">{l s='Download your %name% packing slip as a PDF file.' sprintf=['%name%' => 'Trust Payments'] mod='trustpayments'}</a>
{/if}
</div>
