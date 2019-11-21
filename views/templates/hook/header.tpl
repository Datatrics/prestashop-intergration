{*
* The MIT License (MIT)
*
* Copyright (c) 2016 Benichou
*
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
*
* The above copyright notice and this permission notice shall be included in all
* copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
* SOFTWARE.
*
*  @author    Benichou <benichou.software@gmail.com>
*  @copyright 2016 Benichou
*  @license   http://opensource.org/licenses/MIT  The MIT License (MIT)
*}
{literal}
<script>
    var _paq = _paq || [];
    if(datatrics.user !== null){
        _paq.push(['setCustomData', { 
            'profileid': datatrics.user.id,
            'profilesource': 'Prestashop',
            'firstname': datatrics.user.firstname,
            'lastname': datatrics.user.lastname,
            'email': datatrics.user.email
            'shop_id': datatrics.shop.id
        }]);
    }
    if(datatrics.product !== null){
        _paq.push(['setEcommerceView', datatrics.product.id, datatrics.product.name, datatrics.product.category, datatrics.product.price]);
    }
    if(datatrics.category !== null){
        _paq.push(['setEcommerceView', false, false, datatrics.category.name, false]);
    }
    if(datatrics.cart !== null){
        for (i = 0; i < datatrics.cart.products.length; i++) {
            var product = datatrics.cart.products[i];
            _paq.push(['addEcommerceItem', product.id, product.name, product.category, product.price, product.quantity]);
        }
        _paq.push(['trackEcommerceCartUpdate', datatrics.cart.total]);
    }
    _paq.push(['trackPageView']);
    _paq.push(['enableLinkTracking']);
    (function() {
        _paq.push(['setTrackerUrl', 'https://tr.datatrics.com']);
        _paq.push(['setProjectId', datatrics.projectid]);
        var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
        g.type='text/javascript';
        g.defer=true; g.async=true; g.src='https://tr.datatrics.com', s.parentNode.insertBefore(g,s);
    })();
</script>
{/literal}