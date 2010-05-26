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

    var update = function(action) {
        $('body').css('cursor', 'wait');
        if (action === 'init') {
            var params = parse_hash();
        } else {
            var params = {year: $('#stats-year').val(),
                          month:  $('#stats-month').val(),
                          day: $('#stats-day').val(),
                          hour: $('#stats-hour').val()};
        }
        params.action = action;

        $.getJSON('ajax.php', params, function(data) {
            update_select('stats-year', data.years, data.year);
            update_select('stats-month', data.months, data.month);
            update_select('stats-day', data.days, data.day);
            update_select('stats-hour', data.hours, data.hour);
            $("#stats-hits").html(data.hits);
            $("#stats-time").html(data.time);
            update_chart(data.chart);
            update_table('stats-courses', data.top_courses);
            update_table('stats-scripts', data.top_scripts);
            update_hash(data.year, data.month, data.day, data.hour);
            $('body').css('cursor', 'auto');
        });
    }

    var update_chart = function(chart) {
        if (chart) {
            $.plot($("#stats-chart"), chart, {
                series: { lines : { fill: true } },
                xaxis: { tickSize: 1, tickDecimals: 0 }
            });
            $("#stats-chart").show();
        } else {
            $("#stats-chart").hide();
        }
    }

    var update_hash = function(year, month, day, hour) {
        var hash = "#" + year;
        if (month) {
            hash += "." + month;
        }
        if (day) {
            hash += "." + day;
        }
        if (hour) {
            hash += "." + hour;
        }
        window.location.hash = hash
    }

    var update_select = function(id, values, selected) {
        var $select = $("#" + id);
        if (values !== undefined) {
            $select.empty();
            if (values) {
                $.each(values, function(index, value) {
                    var $option = $("<option>");
                    $option.attr('value', value);
                    $option.append(value);
                    $select.append($option);
                });
                $select.removeAttr('disabled');
            } else {
                $select.attr('disabled', 'disabled');
            }
        }
        if (selected !== undefined) {
            $select.val(selected);
        }
    }

    var update_table = function(id, rows) {
        if (rows) {
            $("#" + id + " tr:gt(0)").remove();
            $.each(rows, function(index, row) {
                var $tr = $("<tr>");
                $.each(row, function(index, value) {
                    $tr.append("<td>" + value + "</td>");
                });
                $("#" + id).append($tr).show();
            });
        } else {
            $("#" + id).hide();
        }
    };

    $('#stats-year').change(function() {
        update('changeyear');
    });

    $('#stats-month').change(function() {
        update('changemonth');
    });

    $('#stats-day').change(function() {
        update('changeday');
    });

    $('#stats-hour').change(function() {
        update('changehour');
    });

   update('init');
});
