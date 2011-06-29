$(document).ready(function() {

    var parse_hash = function() {
        var result = {year: '', month: '', day: '', hour: ''};
        var names = ['year', 'month', 'day', 'hoir'];
        var hash = window.location.hash;
        var i, values;

        if (hash.match(/^#\d+(\.\d+){0,3}$/)) {
            values = hash.slice(1).split(".");
            for (i = 0; i < values.length; i++) {
                result[names[i]] = values[i];
            }
        }

        return result;
    }

    var init = function() {
        var params = parse_hash();
        update(params);
    }

    var update = function(params) {
        $('body').css('cursor', 'wait');
        $.getJSON('ajax.php', params, function(data) {
            var hits, time, chart;
            update_select('stats-year', data.years, data.year);
            update_select('stats-month', data.months, data.month, true);
            update_select('stats-day', data.days, data.day, true);
            update_select('stats-hour', data.hours, data.hour, true);
            $("#stats-hits").html(format_hits(data.hits));
            $("#stats-time").html(format_time(data.time));
            update_chart(data);
            update_table('stats-courses', data.courses);
            update_table('stats-scripts', data.scripts);
            update_hash(data.year, data.month, data.day, data.hour);
            $('body').css('cursor', 'auto');
        });
    }

    var format_hits = function(number) {
        var units = ['K', 'M', 'G', 'T'];
        var current_unit = '';

        if (number < 1000) {
            return number.toFixed(0);
        }

        $.each(units, function(index, unit) {
            if (number >= 1000) {
                number /= 1000;
                current_unit = unit;
            }
        });

        return number.toFixed(1) + current_unit;
    }

    var format_time = function(number) {
        if (typeof(number) === 'undefined') {
            number = format;
        }
        return number.toFixed(2) + "s";
    }

    var update_chart = function(data) {
        if (data.chart) {
            $("#stats-chart").empty();
            $("#stats-chart").show();
            var hits = [], time = [];
            $.each(data.chart, function (index, item) {
                hits.push([item.label, item.hits]);
                time.push([item.label, item.time]);
            });
            var formatter_hits = function (format, number) {
                return format_hits(number);
            }
            var formatter_time = function (format, number) {
                return format_time(number);
            }
            $.jqplot('stats-chart', [hits, time],
                     {legend: {show: true},
                      series: [{label: data.string.hits },
                               {label: data.string.time, yaxis: "y2axis" }],
                      axesDefaults: {useSeriesColor: true},
                      axes: {xaxis: {renderer: $.jqplot.CategoryAxisRenderer},
                             yaxis: {autoscale: true,
                                     tickOptions: {formatter: formatter_hits}},
                             y2axis: {autoscale: true,
                                      tickOptions: {formatter: formatter_time}}},
                      highlighter: {tooltipAxes: 'y', formatString: '%s'},
                      cursor: {show: false}});
        } else {
            $("#stats-chart").hide();
        }
    }

    var update_hash = function(year, month, day, hour) {
        var hash = "#" + year;
        if (month !== false) {
            hash += "." + month;
        }
        if (day !== false) {
            hash += "." + day;
        }
        if (hour !== false) {
            hash += "." + hour;
        }
        window.location.hash = hash
    }

    var update_select = function(id, values, selected, nulloption) {
        var $select = $("#" + id);
        $select.empty();
        if (values) {
            $select.removeAttr("disabled");
            if (nulloption) {
                $("<option>").attr("value", "").appendTo($select);
            }
            $.each(values, function(value, label) {
                $("<option>").attr("value", value).append(label).appendTo($select);
            });
            if (selected !== false) {
                $select.val(selected);
            }
        } else {
            $select.attr("disabled", "disabled");
            $("<option>").attr("value", "").append("&nbsp;").appendTo($select);
        }
    }

    var update_table = function(id, items) {
        $("#" + id + " tr:gt(0)").remove();
        $.each(items, function(index, item) {
            var $tr = $("<tr>").appendTo("#" + id);
            $("<td>").append(item.name).appendTo($tr);
            $("<td>").append(format_hits(item.hits)).appendTo($tr);
            $("<td>").append(format_time(item.time)).appendTo($tr);
        });
    };

    var change = function() {
        var params = {year: $('#stats-year').val(),
                      month:  $('#stats-month').val(),
                      day: $('#stats-day').val(),
                      hour: $('#stats-hour').val()};
        update(params);
    }


    $('#stats-year').change(change);
    $('#stats-month').change(change);
    $('#stats-day').change(change);
    $('#stats-hour').change(change);

    init();
});
