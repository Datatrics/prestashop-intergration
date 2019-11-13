<script>
    var datatricsProjectId = '{$datatrics_project_id}';
</script>

{literal}
    <script>
        var _paq = _paw || [];
        _paq.push(['trackPageView']);
        _paq.push(['enableLinkTracking']);
        (function() {
            var u=https://tr.datatrics.com/';
            _paq.push(['setTrackerUrl', u]);
            _paq.push(['setProjectId', datatricsProjectId]);
            var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
            g.type='text/javascript';
            g.defer=true; g.async=true; g.src=u; s.parentNode.insertBefore(g,s);
        })();
    </script>
{/literal}