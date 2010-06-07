<?php

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/local/profiling/lib.php');

require_login();
require_capability('moodle/site:doanything',
                   get_context_instance(CONTEXT_SYSTEM, SITEID));

$data = new object;

foreach (array('year', 'month', 'day', 'hour') as $param) {
    $data->$param = false;
    $value = optional_param($param, '');
    if ($value !== '') {
        $data->$param = clean_param($value, PARAM_INT);
    }
}

$stats = new local_profiling_stats();

$data->years = $stats->years();
if (!in_array($data->year, $data->years)) {
    $data->year = max($data->years);
}

$data->months = $stats->months($data->year);
if (!isset($data->months[$data->month])) {
    $data->month = false;
}

if ($data->month === false) {
    $data->day = false;
    $data->days = false;
} else {
    $data->days = $stats->days($data->year, $data->month);
    if (!isset($data->days[$data->day])) {
        $data->day = false;
    }
}

if ($data->day === false) {
    $data->hour = false;
    $data->hours = false;
} else {
    $data->hours = $stats->hours($data->year, $data->month, $data->day);
    if (!isset($data->hours[$data->hour])) {
        $data->hour = false;
    }
}


$r = $stats->fetch($data->year, $data->month, $data->day, $data->hour);
$data->time = $r->time;
$data->hits = $r->hits;

if ($data->hour) {
    $data->context = 'hour';
    $data->chart = false;
} elseif ($data->day) {
    $data->context = 'day';
    $data->chart = $stats->fetch_hours($data->year, $data->month, $data->day);
} elseif ($data->month) {
    $data->context = 'month';
    $data->chart = $stats->fetch_days($data->year, $data->month);
} else {
    $data->context = 'year';
    $data->chart = $stats->fetch_months($data->year);
}

$data->courses = $stats->fetch_courses($data->year, $data->month,
                                       $data->day, $data->hour);
$data->scripts = $stats->fetch_scripts($data->year, $data->month,
                                       $data->day, $data->hour);

$data->string = array('hits' => get_string('profiling_hits', 'local'),
                      'time' => get_string('profiling_time', 'local'));

echo json_encode($data);
