$(document).ready(function() {
    var resize = function() {
        var iframe = document.getElementById("material");
        iframe.height = iframe.contentWindow.document.body.scrollHeight + 20;
    };
    $('#material').load(resize);
    $(window).resize(resize);
});
