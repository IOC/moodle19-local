$(document).ready(function() {
    $('.js-only')
        .removeClass("js-only");

    $("#choose-backup").click(function() {
        var course = $("#form input[name=course]").val();
        var url = "/files/index.php?id=" + course + "&choose=backup";
        openpopup(url, "name", "menubar=0,location=0,scrollbars"
                  + ",resizable,width=750,height=500", 0);
        return false;
    });

    var delete_row = function() {
        $(this).closest("tr").remove();
        return false;
    };
    $('.delete-course').click(delete_row);

    var course_row = $("#course-list tr:last").clone();
    $('#add-course').click(function() {
        var index = parseInt($("#form input[name=lastindex]").val()) + 1;
        course_row.clone()
            .find("input:eq(0)").attr("name", "shortname-" + index).end()
            .find("input:eq(1)").attr("name", "fullname-" + index).end()
            .find("select").attr("name", "category-" + index).end()
            .find(".delete-course").click(delete_row).end()
            .insertAfter("#course-list tr:last")
            .find("input:first").focus();
        $("#form input[name=lastindex]").val(index);
        return false;
    });

    $("#import-csv-file").change(function(input) {
        $("#form").submit();
    });
    $('#startdate').datepicker({
	dateFormat: 'dd/mm/yy',
	dayNamesMin: ["Dg", "Dl", "Dt", "Dc", "Dj", "Dv", "Ds"],
	monthNames: ["Gener", "Febrer", "Març", "Abril",
		     "Maig","Juny","Juliol", "Agost",
		     "Setembre", "Octubre", "Novembre", "Desembre"],
	firstDay: 1,
    });
});