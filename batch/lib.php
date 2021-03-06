<?php

require_once(dirname(__FILE__) . '/../../config.php');

require_once($CFG->dirroot . '/local/batch/course.php');
require_once($CFG->dirroot . '/local/batch/types/base.php');
require_once($CFG->dirroot . '/backup/backuplib.php');
require_once($CFG->dirroot . '/backup/lib.php');
require_once($CFG->dirroot . '/backup/restorelib.php');
require_once($CFG->dirroot . '/lib/ddllib.php');

define('BATCH_CRON_TIME', 600);
define('BATCH_JOB_AGE', 90 * 86400);

class batch_job {
    var $id;
    var $type;
    var $params;
    var $timecrerated;
    var $timestarted;
    var $timeended;
    var $error;

    function __construct($record) {
        $this->id = $record->id;
        $this->type = $record->type;
        $this->params = json_decode($record->params);
        $this->timecreated = $record->timecreated ?
            $record->timecreated : false;
        $this->timestarted = $record->timestarted ?
            $record->timestarted : false;
        $this->timeended = $record->timeended ? $record->timeended : false;
        $this->error = $record->error;
    }

    function can_start() {
        $type = batch_type($this->type);
        return $type->can_start($this->params);
    }

    function execute() {
        $type = batch_type($this->type);
        $this->timestarted = time();
        $this->save();
        try {
            $type->execute($this->params);
        } catch (Exception $e) {
            $this->error = $e->getMessage();
        }
        $this->timeended = time();
        $this->save();
    }

    function record() {
        return (object) array('id' => $this->id,
                              'type' => $this->type,
                              'params' => json_encode($this->params),
                              'timecreated' => $this->timecreated,
                              'timestarted' => $this->timestarted,
                              'timeended' => $this->timeended,
                              'error' => $this->error);
    }

    function save() {
        update_record('local_batch_job', addslashes_recursive($this->record()));
    }

}

class batch_queue {

    const FILTER_ALL = 0;
    const FILTER_PENDING = 1;
    const FILTER_FINISHED = 2;
    const FILTER_ERRORS = 3;
    const FILTER_ABORTED = 4;

    function add_job($type, $params=false) {
        $record = (object) array('type' => $type,
                                 'params' => json_encode($params),
                                 'timecreated' => time(),
                                 'timestarted' => 0,
                                 'timeended' => 0,
                                 'error' => '');
        $record->id = insert_record('local_batch_job',
                                    addslashes_recursive($record));
        return new batch_job($record);
    }

    function cancel_job($id) {
        if ($job = self::get_job($id)) {
            if ($job->timestarted == 0) {
                delete_records('local_batch_job', 'id', $job->id);
            }
        }
    }

    function count_jobs($filter) {
        return count_records_select('local_batch_job',
                                    self::filter_select($filter));
    }

    function delete_old_jobs() {
        $select = 'timecreated < ' . (time() - BATCH_JOB_AGE);
        delete_records_select('local_batch_job', $select);
    }

    function filter_select($filter) {
        if ($filter == self::FILTER_PENDING) {
            $select = "timeended = 0";
        } elseif ($filter == self::FILTER_FINISHED) {
            $select = "timeended > 0 AND error = ''";
        } elseif ($filter == self::FILTER_ERRORS) {
            $select = "timeended > 0 AND error != ''";
        } elseif ($filter == self::FILTER_ABORTED) {
            $select = "timestarted > 0 AND timeended = 0";
        } else {
            $select = '';
        }
        return $select;
    }

    function get_job($id) {
        $record = get_record('local_batch_job', 'id', $id);
        return new batch_job($record);
    }

    function get_jobs($filter, $start=false, $count=false) {
        $jobs = array();

        $sort = ($filter == self::FILTER_PENDING) ? 'id ASC' : 'id DESC';
        $records = get_records_select('local_batch_job', self::filter_select($filter),
                                      $sort, '*', $start, $count);
        if ($records) {
            foreach ($records as $record) {
                $jobs[] = new batch_job($record);
            }
        }

        return $jobs;
    }

    function retry_job($id) {
        if ($job = self::get_job($id)) {
            if ($job->error) {
                self::add_job($job->type, $job->params);
            }
        }
    }
}

function batch_cron() {
    global $CFG;

    batch_queue::delete_old_jobs();

    $jobs = batch_queue::get_jobs(batch_queue::FILTER_ABORTED);
    foreach ($jobs as $job) {
        mtrace("batch: job {$job->id} aborted");
        $job->timeended = time();
        $job->error = 'aborted';
        $job->save();
    }

    $start_hour = isset($CFG->local_batch_start_hour) ? (int) $CFG->local_batch_start_hour : 0;
    $stop_hour = isset($CFG->local_batch_stop_hour) ? (int) $CFG->local_batch_stop_hour : 0;
    $date = getdate();
    $hour = $date['hours'];
    if ($start_hour < $stop_hour) {
        $execute = ($hour >= $start_hour and $hour < $stop_hour);
    } else {
        $execute = ($hour >= $start_hour or $hour < $stop_hour);
    }
    if (!$execute) {
        mtrace("batch: execution will start at $start_hour");
        flush();
        return;
    }

    $jobs = batch_queue::get_jobs(batch_queue::FILTER_PENDING);
    if (!$jobs) {
        mtrace("batch: no pending jobs");
        flush();
        return;
    }

    $start_time = time();
    foreach ($jobs as $job) {
        if (time() - $start_time >= BATCH_CRON_TIME) {
            return;
        }
        if ($job->can_start()) {
            mtrace("batch: executing job {$job->id}... ", "");
            flush();
            $job->execute();
            mtrace($job->error ? "ERROR" : "OK");
            flush();
        }
    }
}

function batch_string($identifier, $a=null) {
    global $CFG;
    return get_string($identifier, 'batch', $a,
                      $CFG->dirroot . '/local/batch/lang/');
}

function batch_type($name) {
    global $CFG;
    require_once("{$CFG->dirroot}/local/batch/types/$name.php");
    $class = "batch_type_$name";
    return new $class;
}
