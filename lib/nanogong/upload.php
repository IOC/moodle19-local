<?php

require_once('../config.php');
require_once($CFG->dirroot.'/lib/uploadlib.php');

$course = optional_param('course', 0, PARAM_INTEGER);
$dir = optional_param('dir', '', PARAM_PATH);
$name = optional_param('name', false, PARAM_FILE);
$sesskey = optional_param('sesskey', false, PARAM_RAW);

if (!$course or !$name or !$sesskey) {
    die;
}


require_login($course, false);

if (!has_capability('moodle/course:managefiles', get_context_instance(CONTEXT_COURSE, $course))) {
    die;
}

if (!confirm_sesskey($sesskey)) {
    die;
}

$_FILES['newfile']['name'] = $name;


$um = new upload_manager('newfile', false, false, $course, false, 0, true);

if ($um->process_file_uploads($course . $dir)) {
    echo 'success';
}
