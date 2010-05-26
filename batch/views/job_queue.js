$(document).ready(function() {
    var $form = $('#queue-filter');
    $form.find('select').change(function() {
	$form.submit();
    });
    $form.find(':submit').hide();
});
