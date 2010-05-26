<?php

class batch_type_create_course extends batch_type_base {

    function execute($params) {
        global $CFG;
        $backup_path = SITEID . '/' . $params->backup;
        batch_course::restore_backup($backup_path, $params->startyear,
                                     $params->startmonth, $params->startday,
                                     $params->shortname, $params->fullname,
                                     $params->category);
    }

    function params_info($params) {
        $info = get_string('shortname') . ': ' . s($params->shortname) . '<br/>'
            . get_string('fullname') . ': ' . s($params->fullname) . '<br/>'
            . get_string('backup') . ': ' . s($params->backup) . '<br/>'
            . get_string('category') . ': ' . $params->category . '<br/>'
            . batch_string('start_date') . ': ' . $params->startday . '/'
            . $params->startmonth . '/' . $params->startyear;
        return $info;
    }

}
