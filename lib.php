<?php

function local_raise_resource_limits() {
    global $CFG;

    if (ini_get('max_execution_time')) {
        set_time_limit(3600);
    }

    if (empty($CFG->extramemorylimit)) {
        raise_memory_limit('128M');
    } else {
        raise_memory_limit($CFG->extramemorylimit);
    }

    if ($CFG->dbtype == 'mysql' or $CFG->dbtype == 'mysqli') {
        execute_sql("SET SESSION wait_timeout=3600", false);
    }
}
