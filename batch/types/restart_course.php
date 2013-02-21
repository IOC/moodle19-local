<?php

class batch_type_restart_course extends batch_type_base {

    function execute($params) {
        if (!$course = get_record('course', 'shortname',
                                  addslashes($params->shortname))) {
            throw new Exception('nonexistent');
        }

        if (time() - $course->startdate < 30 * 86400) {
            throw new Exception('started recently');
        }

        $old_shortname = $course->shortname . '~';
        $old_fullname = $course->fullname . strftime(' ~ %B %G');

        if ($old_course = get_record('course', 'shortname', addslashes($old_shortname))) {
            throw new Exception("backup exists");
        }

        $backup_path = batch_course::backup_course($course, SITEID.'/backupdata');

        batch_course::hide_course($course->id);
        batch_course::rename_course($course->id, $old_shortname, $old_fullname);

        $courseid = batch_course::restore_backup($backup_path,
                                                 $params->startyear,
                                                 $params->startmonth,
                                                 $params->startday);

        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        if ($params->roleassignments == 'none') {
            delete_records('role_assignments', 'contextid', $context->id);
        } else if ($params->roleassignments == 'teachers') {
            $roleids = array();
            $capabilities = array('moodle/legacy:editingteacher', 'moodle/legacy:teacher');
            foreach ($capabilities as $cap) {
                foreach (get_roles_with_capability($cap, CAP_ALLOW) as $role) {
                    $roleids[] = $role->id;
                }
            }
            if ($roleids) {
                $select = ('contextid = ' . $context->id .' AND ' .
                           'roleid NOT IN (' . implode(',', $roleids) . ')');
                delete_records_select('role_assignments', $select);
            }
        }

        if (!$params->groups) {
            batch_course::delete_groups($courseid);
        }

        batch_course::clean_groups($courseid);

        if ($params->category) {
            move_courses(array($course->id), $params->category);
        }
    }

    function params_info($params) {
        $date_info = "{$params->startday}/{$params->startmonth}/{$params->startyear}";
        return (get_string('course') . ": {$params->shortname}<br/>" .
                get_string('date') . ": $date_info");
    }

}
