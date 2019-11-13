{if $datatrics_order}
    <script>
        var datatrics_order = {$datatrics_order|@json_encode nofilter};
    </script>
    {literal}
    <script>
        for (i = 0; i < datatrics_order.products.length; i++) {
            var product = datatrics_order.products[i];
            _paq.push(['addEcommerceItem', product.id, product.name, product.category, product.price, product.quantity]);
        }
        _paq.push(['trackEcommerceOrder', datatrics_order.id, datatrics_order.total, datatrics_order.subtotal, datatrics_order.tax, datatrics_order.shipping, 0]);
        _paq.push(['trackPageView']);
    </script>
    {/literal}
{/if}