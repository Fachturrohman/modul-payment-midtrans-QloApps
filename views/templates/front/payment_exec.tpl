{*
* 2007-2017 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2017 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

{capture name=path}
    <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}" title="{l s='Go back to the Checkout' mod='bankwire'}">{l s='Checkout' mod='bankwire'}</a><span class="navigation-pipe">{$navigationPipe}</span>{l s='Bank-wire payment' mod='bankwire'}
{/capture}

<h1 class="page-heading">
    {l s='Order summary' mod='bankwire'}
</h1>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./errors.tpl"}
{include file="$tpl_dir./order-steps.tpl"}

{if $nbProducts <= 0}
    <p class="alert alert-warning">
        {l s='Your shopping cart is empty.' mod='bankwire'}
    </p>
{else}
    <form action="" method="post">
        <input type="hidden" name="order_midtrans_form" value="1">
        <input id="midtrans_transaction_id" type="hidden" name="midtrans_transaction_id" value="">
        <input id="midtrans_order_id" type="hidden" name="midtrans_order_id" value="">
        <input type="hidden" name="token" value="{$token}">
        <input type="hidden" name="midtrans_url" value="{$redirect_url}">
        <div class="box cheque-box">
            <h3 class="page-subheading">
                {l s='Midtrans payment' mod='bankwire'}
            </h3>
            <p class="cheque-indent">
                <strong class="dark">
                    {l s='You have chosen to pay by midtrans.' mod='bankwire'} {l s='Here is a short summary of your order:' mod='bankwire'}
                </strong>
            </p>
            <p>
                - {l s='The total amount of your order is' mod='bankwire'}
                <span id="amount" class="price">{displayPrice price=$total}</span>
                {if $use_taxes == 1}
                    {l s='(tax incl.)' mod='bankwire'}
                {/if}
            </p>
            <p>
                -
                {if $currencies|@count > 1}
                    {l s='We allow several currencies to be sent via midtrans.' mod='bankwire'}
                    <div class="form-group row">
                        <label class="col-xs-12">{l s='Choose one of the following:' mod='bankwire'}</label>
                        <div class="col-xs-4">
                            <select id="currency_payment" class="form-control" name="currency_payment">
                                {foreach from=$currencies item=currency}
                                    <option value="{$currency.id_currency}" {if $currency.id_currency == $cust_currency}selected="selected"{/if}>
                                        {$currency.name}
                                    </option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                {else}
                    {l s='We allow the following currency to be sent via midtrans:' mod='bankwire'}&nbsp;<b>{$currencies.0.name}</b>
                    <input type="hidden" name="currency_payment" value="{$currencies.0.id_currency}" />
                {/if}
            </p>
            <p>
                - {l s='Click Pay button to pay your order.' mod='bankwire'}
                <br />
                - {l s='After Payment done confirm your order by clicking "I confirm my order".' mod='bankwire'}
            </p>
        </div><!-- .cheque-box -->
        <p class="cart_navigation clearfix" id="cart_navigation">
            <a class="btn" href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}">
                <i class="icon-chevron-left"></i>&nbsp;{l s='Other payment methods' mod='bankwire'}
            </a>
            {*By webkul To Check Order restrict condition before Payment by the customer*}
            {if !$restrict_order}
                <button id="submit-button" class="btn pull-right button button-medium" type="submit" style="display:none;">
                    <span>{l s='I confirm my order' mod='bankwire'}&nbsp;<i class="icon-chevron-right right"></i></span>
                </button>
            {/if}
            {if $done_payment eq '0'}
            <a href="#" id="pay-button" data-token="{$token}" class="btn pull-right button button-medium">
                <span>{l s='Pay' mod='bankwire'}&nbsp;<i class="icon-check right"></i></span>
            </a>
            {/if}
        </p>
    </form>
    <script type="text/javascript" src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="SET_YOUR_CLIENT_KEY_HERE"></script>
    <script type="text/javascript">
        // For example trigger on button clicked, or any time you need
        var payButton = document.getElementById('pay-button');
        payButton.addEventListener('click', function (e) {
        // Trigger snap popup. @TODO: Replace TRANSACTION_TOKEN_HERE with your transaction token
        window.snap.pay($(this).attr('data-token'), {
            onSuccess: function(result){
            /* You may add your own implementation here */
            alert("payment success!"); console.log(result);
            $('#midtrans_transaction_id').val(result['transaction_id']);
            $('#midtrans_order_id').val(result['order_id']);
            $('#pay-button').hide();
            $('#submit-button').show();
            },
            onPending: function(result){
            /* You may add your own implementation here */
            alert("wating your payment!"); console.log(result);
            $('#midtrans_transaction_id').val(result['transaction_id']);
            $('#midtrans_order_id').val(result['order_id']);
            $('#pay-button').hide();
            $('#submit-button').show();
            },
            onError: function(result){
            /* You may add your own implementation here */
            alert("payment failed!"); console.log(result);
            },
            onClose: function(){
            /* You may add your own implementation here */
            alert('you closed the popup without finishing the payment');
            }
        })
        });
    </script>
{/if}
