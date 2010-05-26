<?php

class local_profiling_unit {

    var $count;
    var $symbol;

    function __construct($hits) {
        $this->count = ($hits >= 1000000000 ? 1000000000 :
                        ($hits >= 1000000 ? 1000000 :
                         ($hits >= 1000 ? 1000 : 1)));
        $this->symbol = ($this->count == 1000000000 ? 'G' :
                         ($this->count == 1000000 ? 'M' :
                          ($this->count == 1000 ? 'K' : '')));
    }

    function number($number) {
        return ((float) $number / $this->count);
    }

    function string($number, $suffix='') {
        $number = $this->number($number);
        $decimals = ($number == (int) $number) ? 0 : 2;
        return number_format($number, $decimals)
            . $this->symbol . $suffix;
    }

}

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/local/profiling/lib.php');

require_login();
require_capability('moodle/site:doanything',
                   get_context_instance(CONTEXT_SYSTEM, SITEID));

$action = required_param('action', PARAM_ACTION);

$data = new object;
$data->year =  (required_param('year') !== '' ?
                required_param('year', PARAM_INT) : false);
$data->month =  (required_param('month') !== '' ?
                required_param('month', PARAM_INT) : false);
$data->day =  (required_param('day') !== '' ?
                required_param('day', PARAM_INT) : false);
$data->hour =  (required_param('hour') !== '' ?
                required_param('hour', PARAM_INT) : false);

if ($action == 'init') {

    $data->years = local_profiling_stats::time_values();
    $valid = local_profiling_stats::exists($data->year, $data->month,
                                           $data->day, $data->hour);

    if ($data->year === false or !$valid) {
        $data->year = $data->years[count($data->years) - 1];
        $data->months = local_profiling_stats::time_values($data->year);
        $data->month = $data->months[count($data->months) - 1];
        $data->days = local_profiling_stats::time_values($data->year, $data->month);
        $data->day = $data->days[count($data->days) - 1];
        $data->hours = local_profiling_stats::time_values($data->year, $data->month,
                                                          $data->day);
        $data->hour = $data->hours[count($data->hours) - 1];
    } else {
        $data->months = local_profiling_stats::time_values($data->year);
        if ($data->month !== false) {
            $data->days = local_profiling_stats::time_values($data->year,
                                                             $data->month);
            if ($data->day !== false) {
                $data->hours = local_profiling_stats::time_values($data->year,
                                                                  $data->month,
                                                                  $data->day);
            }
        }
    }

}

if ($action == 'changeday') {
    $data->hours = $data->day !== false ?
        local_profiling_stats::time_values($data->year, $data->month,
                                           $data->day) : false;
    $data->hour = false;
}

if ($action == 'changemonth') {
    $data->days = $data->month !== false ?
        local_profiling_stats::time_values($data->year, $data->month) : false;
    $data->day = false;
    $data->hours = false;
    $data->hour = false;
}

if ($action == 'changeyear') {
    $data->months = local_profiling_stats::time_values($data->year);
    $data->month = $data->months[0];
    $data->days = false;
    $data->day = false;
    $data->hours = false;
    $data->hour = false;
}

$where = array('year' => $data->year, 'month' => $data->month,
               'day' => $data->day, 'hour' => $data->hour,
               'course' => null, 'script' => null);
$records = local_profiling_stats::fetch($where);
$record = array_pop($records);
$unit = new local_profiling_unit($record->hits);
$data->hits = $unit->string($record->hits);
$data->time = $unit->string($record->time, 's');

if ($data->hour === false) {
    $where = array('year' => $data->year, 'month' => $data->month,
                   'day' => $data->day, 'hour' => $data->hour,
                   'course' => null, 'script' => null);

    if ($data->month === false) {
        $field = 'month';
    } elseif ($data->day === false) {
        $field = 'day';
    } else {
        $field = 'hour';
    }
    $where[$field] = true;
    $data->chart = array();
    $data->chart[0]->label = get_string('profiling_time', 'local')
        . " ({$unit->symbol}s)";
    $data->chart[1]->label = get_string('profiling_hits', 'local')
        . ($unit->symbol ? " ({$unit->symbol})" : '');
    $records = local_profiling_stats::fetch($where);
    $data->chart[0]->data = array();
    $data->chart[1]->data = array();
    foreach ($records as $record) {
        $data->chart[0]->data[] = array((int) $record->$field,
                                        $unit->number($record->time));
        $data->chart[1]->data[] = array((int) $record->$field,
                                        $unit->number($record->hits));
    }
}

$where = array('year' => $data->year, 'month' => $data->month,
               'day' => $data->day, 'hour' => $data->hour,
               'course' => true, 'script' => null);
$records = local_profiling_stats::fetch($where, 'time DESC', 0, 10);
$data->top_courses = array();
foreach ($records as $record) {
    $data->top_courses[] = array($record->course,
                                 $unit->string($record->hits),
                                 $unit->string($record->time, 's'));
}

$where = array('year' => $data->year, 'month' => $data->month,
               'day' => $data->day, 'hour' => $data->hour,
               'course' => null, 'script' => true);
$records = local_profiling_stats::fetch($where, 'time DESC', 0, 10);
$data->top_scripts = array();
foreach ($records as $record) {
    $data->top_scripts[] = array($record->script,
                                 $unit->string($record->hits),
                                 $unit->string($record->time, 's'));
}

echo json_encode($data);
