<?php

require_once($CFG->dirroot . '/local/batch/lib.php');

unset($ACCESS[$USER->id]);
unset($USER->access);
mtrace('');

batch_cron();
