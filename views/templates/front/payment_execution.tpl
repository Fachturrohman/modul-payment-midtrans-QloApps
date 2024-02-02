<h1 class="page-heading">
    {l s='Order summary' mod='qlomidtranscommerce'}
</h1>

    <div class="box cheque-box">
        <h3 class="page-subheading">
            {l s='Midtrans Payment' mod='bankwire'}
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
    </div><!-- .cheque-box -->
    <p class="cart_navigation clearfix" id="cart_navigation">
        <a class="btn" href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}">
            <i class="icon-chevron-left"></i>&nbsp;{l s='Other payment methods' mod='bankwire'}
        </a>
        {*By webkul To Check Order restrict condition before Payment by the customer*}
        {if !$restrict_order}
            <button id="pay-button" data-token="{$token}" class="btn pull-right button button-medium">
                <span>{l s='I confirm my order' mod='bankwire'}&nbsp;<i class="icon-chevron-right right"></i></span>
            </button>
        {/if}
    </p>
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
        },
        onPending: function(result){
        /* You may add your own implementation here */
        alert("wating your payment!"); console.log(result);
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