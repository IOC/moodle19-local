$(document).ready(function() {
    var update = function() {
        $("#prefix").attr("disabled", $("#remove_prefix").is(":checked"));
    };
    $("#remove_prefix").change(update);
    update();
});
