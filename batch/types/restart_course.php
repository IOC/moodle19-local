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

        if ($old_course = get_record('course', 'shortname',
                                     addslashes($old_shortname))) {
            throw new Exception("backup exists");
        }

        $backup_path = batch_course::backup_course($course->id);

        batch_course::hide_course($course->id);
        batch_course::rename_course($course->id, $old_shortname, $old_fullname);

        $courseid = batch_course::restore_backup($backup_path,
                                                 $params->startyear,
                                                 $params->startmonth,
                                                 $params->startday);

        if ($params->delete_all_role_assignments) {
            $context = get_context_instance(CONTEXT_COURSE, $courseid);
            delete_records('role_assignments', 'contextid', $context->id);
        }

        if ($params->delete_groups) {
            batch_course::delete_groups($courseid);
        } else {
            batch_course::clean_groups($courseid);
        }

        if ($params->category) {
            move_courses(array($course->id), $params->category);
        }
    }

    function params_info($params) {
        return batch_string('params_restart_course', $params);
    }

}
