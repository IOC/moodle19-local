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

function local_root_category_name() {
    global $COURSE;

    if (empty($COURSE->category)) {
        return false;
    }

    $path = get_field('course_categories', 'path', 'id', $COURSE->category);
    $path = explode('/', $path);
    $id = $path[1];
    $name = get_field('course_categories', 'name', 'id', $id);

    return $name;
}
