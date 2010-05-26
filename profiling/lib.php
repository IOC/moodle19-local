<?php

require_once($CFG->dirroot . '/lib/dmllib.php');

class local_profiling {

    var $queries;
    var $query_timestamp;
    var $timestamp;

    function __construct() {
        $this->timestamp = microtime(true);
    }

    function log() {
        global $COURSE;

        $time = microtime(true) - $this->timestamp;
        $localtime = localtime($this->timestamp, true);

        $record = (object) array('year' => $localtime['tm_year'] + 1900,
                                 'month' => $localtime['tm_mon'] + 1,
                                 'day' => $localtime['tm_mday'],
                                 'hour' => $localtime['tm_hour'],
                                 'course' => $COURSE->shortname,
                                 'script' => $this->script(),
                                 'time' => $time);
        insert_record('local_profiling_log', addslashes_recursive($record));
    }

    function script() {
        global $CFG, $ME;

        $script = str_replace('https://', 'http://', $ME);
        $script = str_replace($CFG->wwwroot, '', $script);
        $script = trim($script, '/');

        if (strpos($script, '.php') === FALSE) {
            $script .= '/index.php';
        } else {
            $parts = explode('.php', $script);
            $script = $parts[0] . '.php';
        }

        return trim($script, '/');
    }

    static function init() {
        global $LOCAL_PROFILING;
        $LOCAL_PROFILING = new local_profiling;
    }

    static function shutdown() {
        global $CFG, $LOCAL_PROFILING;
        if (!empty($CFG->local_profiling_enable)) {
            $LOCAL_PROFILING->log();
        }
    }

}


class local_profiling_log {

    function fetch_aggregate($maxid, $fields) {
        global $CFG;
        $sql = "SELECT MAX(id), $fields, COUNT(*) AS hits, SUM(time) AS time"
            . " FROM {$CFG->prefix}local_profiling_log"
            . " WHERE id <= $maxid"
            . " GROUP BY $fields";
        $records = get_records_sql($sql);
        return $records ? $records : array();
    }

    function move_to_stats() {
        $result = true;
        $maxid = get_field('local_profiling_log', 'MAX(id)', '', '');

        $groups = array('year, month, day, hour, course',
                        'year, month, day, hour, script',
                        'year, month, day, hour',
                        'year, month, day, course',
                        'year, month, day, script',
                        'year, month, day',
                        'year, month, course',
                        'year, month, script',
                        'year, month',
                        'year, course',
                        'year, script',
                        'year');

        if ($maxid) {
            foreach ($groups as $fields) {
                $records = self::fetch_aggregate($maxid, $fields);
                $result = $result &&
                    local_profiling_stats::add_records($records);
            }

            $result = $result && delete_records_select('local_profiling_log',
                                                       "id <= $maxid");
        }

        return $result;
    }
}

class local_profiling_objects {

    function add_object($object) {
        delete_records('local_profiling_object', 'id', $object->id);
        $record = (object) array('id' => $object->id,
                                 'object' => addslashes(json_encode($object)));
        insert_record('local_profiling_object', $record);
    }

    function days($week_id) {
        return self::objects(str_replace('week-', 'day-', $week_id));
    }

    function hours($day_id) {
        return self::objects(str_replace('day-', 'hour-', $day_id));
    }

    function id($year, $month, $day, $hour) {
        $date = getdate(mktime($year, $month, $day));

        $year_start = mktime($year, 9, 1);

        $hour = (int) $hour;
        $wday = (int) $data->wday;

        $year = $year;
        $day = 0;

        return sprintf("day-%02d02d%01d%02d", $year, $week, $day, $hour);
    }

    function object($id) {
        $record = get_record('local_profiling_object', 'id', $id);
        return $record ? json_decode($record->object) : false;
    }

    function objects($prefix) {
        $records = get_records_select('local_profiling_object',
                                      "id LIKE '$prefix%'", 'id', 'id');
        return $records ? array_keys($records) : array();
    }

    function weeks($year_id) {
        return self::objects(str_replace('year-', 'week-', $year_id));
    }

    function years() {
        return self::objects('year-');
    }

}


class local_profiling_stats {

    function add($year, $month, $day, $hour, $course, $script, $hits, $time) {
        $where = array('year' => $year, 'month' => $month,
                       'day' => $day, 'hour' => $hour,
                       'course' => $course, 'script' => $script);
        $record = get_record_select('local_profiling_stats',
                                    local_profiling_array_to_select($where));
        if ($record) {
            $record->hits += $hits;
            $record->time += $time;
            return update_record('local_profiling_stats', $record);
        } else {
            $record = (object) $where;
            $record->hits = $hits;
            $record->time = $time;
            return insert_record('local_profiling_stats', $record);
        }
    }

    function add_records($records) {
        $result = true;
        foreach ($records as $r) {
            $result = $result &&
                self::add(isset($r->year) ? $r->year : null,
                          isset($r->month) ? $r->month : null,
                          isset($r->day) ? $r->day : null,
                          isset($r->hour) ? $r->hour : null,
                          isset($r->course) ? $r->course : null,
                          isset($r->script) ? $r->script : null,
                          $r->hits, $r->time);
        }
        return $result;
    }

    function exists($year, $month, $day, $hour) {
        $where = array('year' => $year, 'month' => $month,
                       'day' => $day, 'hour' => $hour);
        $select = local_profiling_array_to_select($where);
        return record_exists_select('local_profiling_stats', $select);
    }

    function fetch($where, $sort='year, month, day, hour, course, script',
                   $limitfrom='', $limitnum='') {
        global $CFG;

        $select = local_profiling_array_to_select($where);
        $records = get_records_select('local_profiling_stats', $select,
                                      $sort, '*', $limitfrom, $limitnum);
        return $records ? $records : array();
    }

    function time_values($year=false, $month=false, $day=false) {
        $where = array('year' => $year, 'month' => $month, 'day' => $day,
                       'hour' => false, 'course' => false, 'script' => false);
        $field = $day ? 'hour' : ($month ? 'day' : ($year ? 'month' : 'year'));
        unset($where[$field]);
        $select = local_profiling_array_to_select($where);
        $records = get_records_select('local_profiling_stats', $select,
                                      $field, $field);
        return array_keys($records);
    }
}


function local_profiling_array_to_select($array) {
    $select = array();
    foreach ($array as $name => $value) {
        if ($value === null or $value === false) {
            $select[] = "$name IS NULL";
        } elseif ($value === true) {
            $select[] = "$name IS NOT NULL";
        } elseif (is_numeric($value)) {
            $select[] = "$name = $value";
        } else {
            $select[] = "$name = '" . addslashes($value) . "'";
        }
    }
    return implode(' AND ', $select);
}


function local_profiling_cron() {
    global $CFG;

    begin_sql();
    $result = local_profiling_log::move_to_stats();
    if ($result) {
        commit_sql();
    } else {
        rollback_sql();
    }
}
