<?php

require_once($CFG->dirroot . '/local/profiling/lib.php');
require_once($CFG->dirroot . '/local/batch/lib.php');

unset($ACCESS[$USER->id]);
unset($USER->access);
mtrace('');

local_profiling_cron();
batch_cron();
