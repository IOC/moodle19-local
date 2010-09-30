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
        if (empty($CFG->local_profiling_disable)) {
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

    function fetch($year, $month=false, $day=false, $hour=false) {
        $record = get_record_select('local_profiling_stats',
                                    $this->where($year, $month, $day, $hour));
        return (object) array('hits' => (int) $record->hits,
                              'time' => (float) $record->time / $record->hits);
    }

    function fetch_months($year) {
        $stats = array();
        foreach ($this->months($year) as $month => $label) {
            $record = $this->fetch($year, $month);
            $record->label = $label;
            $stats[$month] = $record;
        }
        return $stats;
    }

    function fetch_days($year, $month) {
        $stats = array();
        foreach ($this->days($year, $month) as $day => $label) {
            $record = $this->fetch($year, $month, $day);
            $record->label = $label;
            $stats[$day] = $record;
        }
        return $stats;
    }

    function fetch_hours($year, $month, $day) {
        $stats = array();
        foreach ($this->hours($year, $month, $day) as $hour => $label) {
            $record = $this->fetch($year, $month, $day, $hour);
            $record->label = $label;
            $stats[$hour] = $record;
        }
        return $stats;
    }

    function fetch_courses($year, $month=false, $day=false, $hour=false) {
        $courses = array();
        $select = $this->where($year, $month, $day, $hour, true, false);
        $records = get_records_select('local_profiling_stats', $select,
                                      'hits DESC', '*', 0, 10);
        foreach ($records as $record) {
            $courses[] = (object) array('name' => $record->course,
                                        'hits' => (int) $record->hits,
                                        'time' => (float) $record->time / $record->hits);
        }
        return $courses;
    }

    function fetch_scripts($year, $month=false, $day=false, $hour=false) {
        $scripts = array();
        $select = $this->where($year, $month, $day, $hour, false, true);
        $records = get_records_select('local_profiling_stats', $select,
                                  'hits DESC', '*', 0, 10);
        foreach ($records as $record) {
            $scripts[] = (object) array('name' => $record->script,
                                        'hits' => (int) $record->hits,
                                        'time' => (float) $record->time / $record->hits);
        }
        return $scripts;
    }

    function years() {
        global $CFG;

        $years = array();

        $sql = 'SELECT DISTINCT year'
            . " FROM {$CFG->prefix}local_profiling_stats"
            . ' WHERE year IS NOT NULL'
            . ' GROUP BY year'
            . ' ORDER BY year ASC';
        if ($records = get_records_sql($sql)) {
            foreach ($records as $year => $record) {
                $years[(int) $year] = "$year";
            }
        }

        return $years;
    }

    function months($year) {
        global $CFG;

        $months = array();

        $sql = 'SELECT DISTINCT month'
            . " FROM {$CFG->prefix}local_profiling_stats"
            . " WHERE year=$year AND month IS NOT NULL"
            . ' GROUP BY year, month'
            . ' ORDER BY month ASC';
        if ($records = get_records_sql($sql)) {
            foreach ($records as $month => $record) {
                $time = mktime(0, 0, 0, $month, 1, $year);
                $months[(int) $month] = strftime('%B', $time);
            }
        }

        return $months;
    }

    function days($year, $month) {
        global $CFG;

        $days = array();

        $sql = 'SELECT DISTINCT day'
            . " FROM {$CFG->prefix}local_profiling_stats"
            . " WHERE year=$year AND month=$month AND day IS NOT NULL"
            . ' GROUP BY year, month, day'
            . ' ORDER BY day ASC';

        if ($records = get_records_sql($sql)) {
            foreach ($records as $day => $record) {
                $days[(int) $day] = sprintf("%02d", $day);
            }
        }

        return $days;
    }

    function hours($year, $month, $day) {
        global $CFG;

        $hours = array();

        $sql = 'SELECT DISTINCT hour'
            . " FROM {$CFG->prefix}local_profiling_stats"
            . " WHERE year=$year AND month=$month AND day=$day"
            . ' AND hour IS NOT NULL'
            . ' GROUP BY year, month, day, hour'
            . ' ORDER BY hour ASC';

        if ($records = get_records_sql($sql)) {
            foreach ($records as $hour => $record) {
                $hours[(int) $hour] = sprintf("%02d", $hour);
            }
        }

        return $hours;
    }

    function where($year, $month, $day, $hour, $courses=false, $scripts=false) {
        $where = array($year !== false ? "year=$year" : "year IS NULL",
                       $month !== false ? "month=$month" : "month IS NULL",
                       $day !== false ? "day=$day" : "day IS NULL",
                       $hour !== false ? "hour=$hour" : "hour IS NULL",
                       $courses !== false ? 'course IS NOT NULL' : "course IS NULL",
                       $scripts !== false ? 'script IS NOT NULL' : "script IS NULL");
        return implode(' AND ', $where);
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
