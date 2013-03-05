<?php

require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(__FILE__) . '/web.php');

$views = array(
    'job_queue',
    'create_courses',
    'delete_courses',
    'restart_courses',
    'export_courses',
    'config_courses',
    'db_maint',
);

$view = optional_param('view', 'job_queue', PARAM_ALPHAEXT);

new batch_web($views, $view);
