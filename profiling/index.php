<?php

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('moodle/site:doanything',
                   get_context_instance(CONTEXT_SYSTEM, SITEID));

admin_externalpage_setup('local_profiling');

require_js($CFG->wwwroot . '/local/lib/jquery/jquery.min.js');
require_js($CFG->wwwroot . '/local/lib/jqplot/jquery.jqplot.min.js');
require_js($CFG->wwwroot . '/local/lib/jqplot/plugins/jqplot.categoryAxisRenderer.min.js');
require_js($CFG->wwwroot . '/local/lib/jqplot/plugins/jqplot.canvasTextRenderer.min.js');
require_js($CFG->wwwroot . '/local/lib/jqplot/plugins/jqplot.canvasAxisTickRenderer.min.js');
require_js($CFG->wwwroot . '/local/lib/jqplot/plugins/jqplot.highlighter.min.js');
require_js($CFG->wwwroot . '/local/lib/jqplot/plugins/jqplot.cursor.min.js');
require_js($CFG->wwwroot . '/local/profiling/index.js');

$CFG->stylesheets[] = "{$CFG->wwwroot}/local/lib/jqplot/jquery.jqplot.min.css";
$CFG->stylesheets[] = "{$CFG->wwwroot}/local/profiling/index.css";

admin_externalpage_print_header();

include 'index.html';

admin_externalpage_print_footer();
