YAHOO.util.Event.onDOMReady(function() {
    var iframe = YAHOO.util.Dom.get('material');
    var resize = function() {
        iframe.height = 500;
        iframe.height = iframe.contentWindow.document.body.scrollHeight + 20;
    };
    YAHOO.util.Event.addListener(iframe, 'load', resize);
    YAHOO.util.Event.addListener(window, 'resize', resize);
});
