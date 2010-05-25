<?php

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/dmllib.php');

require_login();
require_capability('moodle/site:doanything',
                   get_context_instance(CONTEXT_SYSTEM));

admin_externalpage_setup('local_errorlog');

require_js("{$CFG->wwwroot}/local/lib/jquery/jquery-1.4.2.min.js");
require_js("{$CFG->wwwroot}/local/errorlog/index.js");
$CFG->stylesheets[] = "{$CFG->wwwroot}/local/errorlog/index.css";

admin_externalpage_print_header();
print_heading(get_string('errorlog', 'local'));

$filter = optional_param('filter', '', PARAM_TEXT);

$lines = array();
$error = false;
$file = false;

if (!empty($CFG->local_errorlog_path)) {
    $file = fopen($CFG->local_errorlog_path, 'r');
}

if ($file) {
    fseek($file, -64*1024, SEEK_END);
    fgets($file);
    while (!feof($file)) {
        $line = fgets($file);
        if (trim($line)) {
            $lines[] = $line;
        }
    }
    fclose($file);
    if ($filter) {
        $lines = preg_grep("/$filter/", $lines);
    }
} else {
    $error = get_string('errorlog_notfound', 'local');
}

$content = implode('', $lines);

include 'index.html';

admin_externalpage_print_footer();
