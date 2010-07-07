<?php

require_once(dirname(__FILE__) . '/../../config.php');

require_once($CFG->dirroot . '/local/batch/course.php');
require_once($CFG->dirroot . '/local/batch/types/base.php');
require_once($CFG->dirroot . '/backup/backuplib.php');
require_once($CFG->dirroot . '/backup/lib.php');
require_once($CFG->dirroot . '/backup/restorelib.php');
require_once($CFG->dirroot . '/lib/ddllib.php');

define('BATCH_CRON_TIME', 600);
define('BATCH_ARCHIVE_AGE', 90 * 86400);

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
    const FILTER_ARCHIVED = 5;

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

    function filter_select($filter) {
        $time_archived = time() - BATCH_ARCHIVE_AGE;
        $select = "timecreated > $time_archived";
        if ($filter == self::FILTER_PENDING) {
            $select .= " AND timeended = 0";
        } elseif ($filter == self::FILTER_FINISHED) {
            $select .= " AND timeended > 0 AND error = ''";
        } elseif ($filter == self::FILTER_ERRORS) {
            $select .= " AND timeended > 0 AND error != ''";
        } elseif ($filter == self::FILTER_ABORTED) {
            $select .= " AND timestarted > 0 AND timeended = 0";
        } elseif ($filter == self::FILTER_ARCHIVED) {
            $select = "timecreated <= $time_archived";
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
}

function batch_cron() {
    $jobs = batch_queue::get_jobs(batch_queue::FILTER_ABORTED);
    foreach ($jobs as $job) {
        mtrace("batch: job {$job->id} aborted");
        $job->timeended = time();
        $job->error = 'aborted';
        $job->save();
    }

    $jobs = batch_queue::get_jobs(batch_queue::FILTER_PENDING);
    if ($jobs) {
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
    } else {
        mtrace("batch: no pending jobs");
        flush();
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
