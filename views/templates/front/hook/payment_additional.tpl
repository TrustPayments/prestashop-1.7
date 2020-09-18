{*
 * Trust Payments Prestashop
 *
 * This Prestashop module enables to process payments with Trust Payments (https://www.trustpayments.com/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2020 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
<div sytle="display:none" class="trustpayments-method-data" data-method-id="{$methodId|escape:'html':'UTF-8'}" data-configuration-id="{$configurationId|escape:'html':'UTF-8'}"></div>
<section>
  {if !empty($description)}
    {* The description has to be unfiltered to dispaly html correcty. We strip unallowed html tags before we assign the variable to smarty *}
    <p>{trustpayments_clean_html text=$description}</p>
  {/if}
  {if !empty($surchargeValues)}
	<span class="trustpayments-surcharge trustpayments-additional-amount"><span class="trustpayments-surcharge-text trustpayments-additional-amount-test">{l s='Minimum Sales Surcharge:' mod='trustpayments'}</span>
		<span class="trustpayments-surcharge-value trustpayments-additional-amount-value">
			{if $priceDisplayTax}
				{Tools::displayPrice($surchargeValues.surcharge_total)|escape:'html':'UTF-8'} {l s='(tax excl.)' mod='trustpayments'}
	        {else}
	        	{Tools::displayPrice($surchargeValues.surcharge_total_wt)|escape:'html':'UTF-8'} {l s='(tax incl.)' mod='trustpayments'}
	        {/if}
       </span>
   </span>
  {/if}
  {if !empty($feeValues)}
	<span class="trustpayments-payment-fee trustpayments-additional-amount"><span class="trustpayments-payment-fee-text trustpayments-additional-amount-test">{l s='Payment Fee:' mod='trustpayments'}</span>
		<span class="trustpayments-payment-fee-value trustpayments-additional-amount-value">
			{if ($priceDisplayTax)}
	          	{Tools::displayPrice($feeValues.fee_total)|escape:'html':'UTF-8'} {l s='(tax excl.)' mod='trustpayments'}
	        {else}
	          	{Tools::displayPrice($feeValues.fee_total_wt)|escape:'html':'UTF-8'} {l s='(tax incl.)' mod='trustpayments'}
	        {/if}
       </span>
   </span>
  {/if}
  
</section>
