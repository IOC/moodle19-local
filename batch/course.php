<?php

class batch_course {

    function backup_course($courseid) {
        global $CFG, $preferences;

        if (!$course = get_record('course', 'id', $courseid)) {
            throw new Exception("backup_course: nonexistent course $courseid");
        }

        $prefs = array('backup_metacourse' => 0,
                       'backup_users' => 1,
                       'backup_logs' => 0,
                       'backup_user_files' => 0,
                       'backup_course_files' => 1,
                       'backup_site_files' => 0,
                       'backup_messages' => 0);

        $preferences = backup_generate_preferences_artificially($course,
                                                                $prefs);

        $backup_dir = SITEID . '/backupdata';
        $backup_name = backup_get_zipfile_name($course);
        $preferences->backup_destination = $CFG->dataroot . '/' . $backup_dir;
        $preferences->backup_name = $backup_name;
        $preferences->backuproleassignments = get_records('role');

        if ($allmods = get_records("modules") ) {
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

        return "$backup_dir/$backup_name";
    }

    function clean_group($groupid, $users) {
        $members = get_records('groups_members', 'groupid',
                               $groupid, '', 'id, userid');
        if ($members) {
            foreach ($members as $member) {
                if (!isset($users[$member->userid])) {
                    delete_records('groups_members', 'id', $member->id);
                }
            }
        }
    }

    function clean_groups($courseid) {
        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        $users = get_users_by_capability($context, 'moodle/course:view',
                                         'u.id');
        $groups = get_records('groups', 'courseid', $courseid, '', 'id');
        if ($groups) {
            foreach ($groups as $group) {
                self::clean_group($group->id, $users);
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
        $groups = get_records('groups', 'courseid', $courseid);
        if ($groups) {
            foreach ($groups as $group) {
                delete_records('groups_members', 'groupid', $group->id);
                delete_records('groups', 'id', $group->id);
            }
        }
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
        $restore->groups = 1;
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
