<script>
    var datatrics_projectid = {$datatrics_projectid};
    {if $datatrics_user}
        var datatrics_user = {$datatrics_user|@json_encode nofilter};
    {/if}
    {if $datatrics_product}
        var datatrics_product = {$datatrics_product|@json_encode nofilter};
    {/if}
    {if $datatrics_category}
        var datatrics_category = {$datatrics_category|@json_encode nofilter};
    {/if}
    {if $datatrics_cart}
        var datatrics_cart = {$datatrics_cart|@json_encode nofilter};
    {/if}
</script>
{literal}
<script>
    var _paq = _paq || [];
    if(datatrics_user !== null){
        _paq.push(['setCustomData', { 'profileid': datatrics_user.id, 'profilesource': 'Prestashop', 'firstname': datatrics_user.firstname,'lastname': datatrics_user.lastname,'email': datatrics_user.email}]);
    }
    if(typeof datatrics_product !== 'undefined'){
        _paq.push(['setEcommerceView', datatrics_product.id, datatrics_product.name, datatrics_product.category, datatrics_product.price]);
    }
    if(typeof datatrics_category !== 'undefined'){
        _paq.push(['setEcommerceView', false, false, datatrics_category.name, false]);
    }
    if(typeof datatrics_cart !== 'undefined'){
        for (i = 0; i < datatrics_cart.products.length; i++) {
            var product = datatrics_cart.products[i];
            _paq.push(['addEcommerceItem', product.id, product.name, product.category, product.price, product.quantity]);
        }
        _paq.push(['trackEcommerceCartUpdate', datatrics_cart.total]);
    }
    _paq.push(['trackPageView']);
    _paq.push(['enableLinkTracking']);
    (function() {
        _paq.push(['setTrackerUrl', 'https://tr.datatrics.com']);
        _paq.push(['setProjectId', datatrics_projectid]);
        var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
        g.type='text/javascript';
        g.defer=true; g.async=true; g.src='https://tr.datatrics.com', s.parentNode.insertBefore(g,s);
    })();
</script>
{/literal}