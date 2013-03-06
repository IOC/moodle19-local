<?php

class batch_course {

    function backup_course($course, $backup_dir, $export=false, $materials=false) {
        global $CFG, $preferences;

        if (!make_upload_directory($backup_dir)) {
            throw new Exception("backup_course: could not create directory $backup_dir");
        }

        $prefs = array('backup_metacourse' => 0,
                       'backup_users' => $export ? 2 : 1,
                       'backup_logs' => 0,
                       'backup_user_files' => 0,
                       'backup_course_files' => 1,
                       'backup_site_files' => 0,
                       'backup_messages' => 0);

        $preferences = backup_generate_preferences_artificially($course, $prefs);

        $backup_name = backup_get_zipfile_name($course);
        $preferences->backup_destination = $CFG->dataroot . '/' . $backup_dir;
        $preferences->backup_name = $backup_name;
        $preferences->backuproleassignments = get_records('role');
        $preferences->export = $export;
        $preferences->materials = $materials;

        if ($allmods = get_records("modules")) {
            foreach ($allmods as $mod) {
                $modvar = "{$mod->name}_instances";
                if (isset($preferences->$modvar)) {
                    $var = "backup_user_info_{$mod->name}";
                    $preferences->$var = false;
                    $preferences->mods[$mod->name]->userinfo = false;
                    foreach ($preferences->$modvar as $instance) {
                        $var = "backup_user_info_{$mod->name}_instance_{$instance->id}";
                        $preferences->$var = false;
                        $preferences->mods[$mod->name]->instances[$instance->id]->userinfo = false;
                        if ($export and
                            (($mod->name == 'assignment' and
                              $instance->assignmenttype == 'peerreview') or
                             ($mod->name == 'resource' and
                              $instance->type == 'directory' and
                              preg_match('#^($|__materials__/)#', $instance->reference)))) {
                            $var = "backup_{$mod->name}_instance_{$instance->id}";
                            $preferences->$var = false;
                            $preferences->mods[$mod->name]->instances[$instance->id]->backup = false;
                        }
                    }
                }
            }
        }

        user_check_backup($course->id, $preferences->backup_unique_code,
                          $preferences->backup_users,
                          $preferences->backup_messages,
                          $preferences->backup_blogs);

        define('BACKUP_SILENTLY', true);

        $errorstr = '';
        $status = backup_execute($preferences, $errorstr);
        if ($errorstr) {
            throw new Exception('backup_course: ' . $errorstr);
        }

        return $backup_dir . '/' . $backup_name;
    }

    function change_prefix($courseid, $prefix) {
        $course = get_record('course', 'id', $courseid);

        if (preg_match('/^\[.*?\](.*)$/', $course->fullname, $match)) {
            $course->fullname = trim($match[1]);
        }

        if ($prefix) {
            $course->fullname = "[$prefix] {$course->fullname}";
        }

        return update_record('course', $course);
    }

    function change_suffix($courseid, $suffix) {
        $course = get_record('course', 'id', $courseid);

        if (preg_match('/^(.*)([~\*])$/', $course->shortname, $match)) {
            $course->shortname = $match[1];
            if ($match[2] == '~') {
                if (preg_match('/(.*) ~ .*?$/', $course->fullname, $match)) {
                    $course->fullname = $match[1];
                }
            }
        }

        if ($suffix == 'restarted') {
            $course->shortname .= '~';
            $course->fullname .= strftime(' ~ %B %G');
        } elseif ($suffix == 'imported') {
            $course->shortname .= '*';
        }

        $id = get_field('course', 'id', 'shortname', $course->shortname);
        if (!$id or $id == $courseid) {
            return update_record('course', $course);
        }
    }

    function clean_groups($courseid) {
        global $CFG;

        $select = "groupid IN (SELECT id FROM {$CFG->prefix}groups WHERE courseid=$courseid)";
        $members = get_records_select('groups_members', $select);
        if ($members) {
            $context = get_context_instance(CONTEXT_COURSE, $courseid);
            $users = get_users_by_capability($context, 'moodle/course:view', 'u.id');
            foreach ($members as $member) {
                if (!isset($users[$member->userid])) {
                    delete_records('groups_members', 'id', $member->id);
                }
            }
        }
    }

    function delete_course($courseid) {
        if (!record_exists('course', 'id', $courseid)) {
            throw new Exception("delete_course: nonexistent_course $courseid");
        }

        if (!delete_course($courseid, false)) {
            throw new Exception('delete_course ' . $courseid);
        }
    }

    function delete_groups($courseid) {
        global $DB;

        $select = "groupid IN (SELECT id FROM {$CFG->prefix}groups WHERE courseid=$courseid)";
        delete_records_select('groups_members', $select);
        delete_records_select('groupings_groups', $select);
        delete_records('groups', 'courseid', $courseid);
        delete_records('groupings', 'courseid', $courseid);
    }

    function hide_course($courseid) {
        if (!set_field('course', 'visible', 0, 'id', $courseid)) {
            throw new Exception('hide_course');
        }
    }

    function rename_course($courseid, $shortname, $fullname) {
        if (!set_field('course', 'shortname', addslashes($shortname),
                       'id', $courseid)) {
            throw new Exception('rename_course: shortname');
        }

        if (!set_field('course', 'fullname', addslashes($fullname),
                       'id', $courseid)) {
            throw new Exception('rename_course: fullname');
        }
    }

    function restore_backup($backup_path, $startyear, $startmonth, $startday,
                            $shortname=false, $fullname=false, $category=false) {
        global $CFG, $SESSION, $restore;

        define('RESTORE_SILENTLY', true);
    
        $errorstr = '';
        $backup_unique_code = restore_precheck(0, $backup_path,
                                               $errorstr, true);

        if ($errorstr) {
            throw new Exception('restore backup: ' . $errorstr);
        }

        $restore->backup_unique_code = $backup_unique_code;
        $restore->restoreto = 2;
        $restore->metacourse = 0;
        $restore->users = 1;
        $restore->groups = RESTORE_GROUPS_GROUPINGS;
        $restore->logs = 0;
        $restore->user_files = 0;
        $restore->course_files = 1;
        $restore->site_files = 0;
        $restore->messages = 0;
        $restore->blogs = 0;
        $restore->restore_gradebook_history = 0;
        $restore->course_startdateoffset =
            86400 * (int) round((make_timestamp($startyear, $startmonth, $startday)
                                 - $SESSION->course_header->course_startdate) / 86400);
        $restore->restore_restorecatto = 0;
        $restore->backup_version = $SESSION->info->backup_backup_version;
        $restore->rolesmapping = array();
        if ($category !== false) {
            $restore->restore_restorecatto = $category;
        }

        $roles = get_records('role');
        foreach ($roles as $role) {
            $restore->rolesmapping[$role->id] = $role->id;
        }
        $SESSION->restore = $restore;

        if ($allmods = get_records("modules")) {
            foreach ($allmods as $mod) {
                $var = "restore_" . $mod->name;
                if (isset($SESSION->info->mods[$mod->name])) {
                    if ($SESSION->info->mods[$mod->name]->backup == "true") {
                        $restore->$var = 1;
                        $SESSION->restore->mods[$mod->name]->restore = true;
                        $SESSION->restore->mods[$mod->name]->userinfo = false;
                    }
                }
            }
        }

        if ($shortname !== false) {
            $SESSION->course_header->course_shortname = $shortname;
        }
        if ($fullname !== false) {
            $SESSION->course_header->course_fullname = $fullname;
        }

        restore_execute($SESSION->restore, $SESSION->info,
                        $SESSION->course_header, $errorstr);
        if ($errorstr) {
            throw new Exception("restore_backup: $errorstr");
        }

        $shortname = $SESSION->course_header->course_shortname;
        $course = get_record('course', 'shortname', addslashes($shortname));
        if (!$course) {
            throw new Exception('restore_backup: nonexistent course '
                                . $shortname);
        }

        fulldelete($CFG->dataroot . '/' . $course->id . '/email/');

        return $course->id;
    }

    function show_course($courseid) {
        if (!set_field('course', 'visible', 1, 'id', $courseid)) {
            throw new Exception('show course');
        }
    }

}
