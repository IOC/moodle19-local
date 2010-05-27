$(document).ready(function() {
    $('#material').load(function() {
        this.height = window.frames['material'].document.body.scrollHeight;
    });
});
