$(document).ready(function() {
    $('#startdate').datepicker({
	dateFormat: 'dd/mm/yy',
	dayNamesMin: ["Dg", "Dl", "Dt", "Dc", "Dj", "Dv", "Ds"],
	monthNames: ["Gener", "Febrer", "Març", "Abril",
		     "Maig","Juny","Juliol", "Agost",
		     "Setembre", "Octubre", "Novembre", "Desembre"],
	firstDay: 1,
    });
});
