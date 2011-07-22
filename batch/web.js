$(document).ready(function() {
    $('#course-tree li.category span.title')
	.css('cursor', 'pointer')
	.click(function() {
	    $(this).toggleClass('hidden');
	    $(this).next().slideToggle();
	})
	.click();
    $('#course-tree li.course label')
	.css('cursor', 'pointer');
});
