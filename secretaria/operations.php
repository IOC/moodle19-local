<?php

class local_secretaria_exception extends Exception {
}

class local_secretaria_operations {

    function __construct($moodle=null) {
        $this->moodle = $moodle;
    }

    /* Users */

    function get_user($username) {
        $mnethostid = $this->moodle->mnet_host_id();

        if (!$record = $this->moodle->get_user_record($mnethostid, $username)) {
            throw new local_secretaria_exception('Unknown user');
        }

        $pixurl = $this->moodle->user_picture_url($record->id);

        return array(
            'username' => $username,
            'firstname' => $record->firstname,
            'lastname' => $record->lastname,
            'email' => $record->email,
            'picture' => $record->picture ? $pixurl : null,
        );
    }


    function create_user($properties) {
        $mnethostid = $this->moodle->mnet_host_id();
        $mnetlocalhostid = $this->moodle->mnet_localhost_id();
        $auth = ($mnethostid == $mnetlocalhostid ? 'manual' : 'mnet');

        if ($this->moodle->get_user_id($mnethostid, $properties['username'])) {
            throw new local_secretaria_exception('Duplicate username');
        }

        $this->moodle->start_transaction();
        $this->moodle->create_user(
            $auth,
            $mnethostid,
            $properties['username'],
            $properties['password'],
            $properties['firstname'],
            $properties['lastname'],
            $properties['email']
        );
        $this->moodle->commit_transaction();
    }

    function update_user($username, $properties) {
        $record = new stdClass;
        $mnethostid = $this->moodle->mnet_host_id();

        if (!$record->id = $this->moodle->get_user_id($mnethostid, $username)) {
            throw new local_secretaria_exception('Unknown user');
        }

        if (!empty($properties['username']) and $properties['username'] != $username) {
            if ($this->moodle->get_user_id($mnethostid, $properties['username'])) {
                throw new local_secretaria_exception('Duplicate username');
            }
            $record->username = $properties['username'];
        }
        if (!empty($properties['firstname'])) {
            $record->firstname = $properties['firstname'];
        }
        if (!empty($properties['lastname'])) {
            $record->lastname = $properties['lastname'];
        }
        if (!empty($properties['email'])) {
            $record->email = $properties['email'];
        }

        $this->moodle->start_transaction();
        $this->moodle->update_record('user', $record);
        if (!empty($properties['password'])) {
            $this->moodle->update_user_password($record->id, $properties['password']);
        }
        $this->moodle->commit_transaction();
    }

    function delete_user($username) {
        $mnethostid = $this->moodle->mnet_host_id();
        if (!$record = $this->moodle->get_user_record($mnethostid, $username)) {
            throw new local_secretaria_exception('Unknown user');
        }
        $this->moodle->start_transaction();
        $this->moodle->delete_user($record);
        $this->moodle->commit_transaction();
    }

    /* Enrolments */

    function get_course_enrolments($course) {
        $mnethostid = $this->moodle->mnet_host_id();

        if (!$contextid = $this->moodle->get_context_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }

        $enrolments = array();
        if ($records = $this->moodle->get_role_assignments_by_context($contextid, $mnethostid)) {
            foreach ($records as $record) {
                $enrolments[] = array('user' => $record->user, 'role' => $record->role);
            }
        }

        return $enrolments;
    }

    function get_user_enrolments($username) {
        $mnethostid = $this->moodle->mnet_host_id();

        if (!$userid = $this->moodle->get_user_id($mnethostid, $username)) {
            throw new local_secretaria_exception('Unknown user');
        }

        $enrolments = array();
        if ($records = $this->moodle->get_role_assignments_by_user($userid)) {
            foreach ($records as $record) {
                $enrolments[] = array('course' => $record->course, 'role' => $record->role);
            }
        }

        return $enrolments;
    }

    function enrol_users($enrolments) {
        $mnethostid = $this->moodle->mnet_host_id();

        $this->moodle->start_transaction();

        foreach ($enrolments as $enrolment) {
            if (!$contextid = $this->moodle->get_context_id($enrolment['course'])) {
                $this->moodle->rollback_transaction();
                throw new local_secretaria_exception('Unknown course');
            }
            if (!$userid = $this->moodle->get_user_id($mnethostid, $enrolment['user'])) {
                $this->moodle->rollback_transaction();
                throw new local_secretaria_exception('Unknown user');
            }
            if (!$roleid = $this->moodle->get_role_id($enrolment['role'])) {
                $this->moodle->rollback_transaction();
                throw new local_secretaria_exception('Unknown role');
            }
            if (!$this->moodle->role_assignment_exists($contextid, $userid, $roleid)) {
                $this->moodle->insert_role_assignment($contextid, $userid, $roleid);
            }
        }

        $this->moodle->commit_transaction();
    }

    function unenrol_users($enrolments) {
        $mnethostid = $this->moodle->mnet_host_id();

        $this->moodle->start_transaction();

        foreach ($enrolments as $enrolment) {
            if (!$contextid = $this->moodle->get_context_id($enrolment['course'])) {
                $this->moodle->rollback_transaction();
                throw new local_secretaria_exception('Unknown course');
            }
            if (!$userid = $this->moodle->get_user_id($mnethostid, $enrolment['user'])) {
                $this->moodle->rollback_transaction();
                throw new local_secretaria_exception('Unknown user');
            }
            if (!$roleid = $this->moodle->get_role_id($enrolment['role'])) {
                $this->moodle->rollback_transaction();
                throw new local_secretaria_exception('Unknown role');
            }
            $this->moodle->delete_role_assignment($contextid, $userid, $roleid);
        }

        $this->moodle->commit_transaction();
    }

    /* Groups */

    function get_groups($course) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }

        $groups = array();

        if ($records = $this->moodle->get_groups($courseid)) {
            foreach ($records as $record) {
                $groups[] = array('name' => $record->name,
                                  'description' => $record->description);
            }
        }

        return $groups;
    }

    function create_group($course, $name, $description) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }
        if ($this->moodle->get_group_id($courseid, $name)) {
            throw new local_secretaria_exception('Duplicate group');
        }
        $this->moodle->start_transaction();
        $this->moodle->insert_group($courseid, $name, $description);
        $this->moodle->commit_transaction();
    }

    function delete_group($course, $name) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }
        if (!$groupid = $this->moodle->get_group_id($courseid, $name)) {
            throw new local_secretaria_exception('Unknown group');
        }
        $this->moodle->start_transaction();
        $this->moodle->groups_delete_group($groupid);
        $this->moodle->commit_transaction();
    }

    function get_group_members($course, $name) {
        $mnethostid = $this->moodle->mnet_host_id();

        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }
        if (!$groupid = $this->moodle->get_group_id($courseid, $name)) {
            throw new local_secretaria_exception('Unknown group');
        }
        $users = array();
        if ($records = $this->moodle->get_group_members($groupid, $mnethostid)) {
            foreach ($records as $record) {
                $users[] = $record->username;
            }
        }
        return $users;
    }

    function add_group_members($course, $name, $users) {
        $mnethostid = $this->moodle->mnet_host_id();

        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
       }
        if (!$groupid = $this->moodle->get_group_id($courseid, $name)) {
            throw new local_secretaria_exception('Unknown group');
        }

        $this->moodle->start_transaction();

        foreach ($users as $user) {
            if (!$userid = $this->moodle->get_user_id($mnethostid, $user)) {
                $this->moodle->rollback_transaction();
                throw new local_secretaria_exception('Unknown user');
            }
            $this->moodle->groups_add_member($groupid, $userid);
        }

        $this->moodle->commit_transaction();
    }

    function remove_group_members($course, $name, $users) {
        $mnethostid = $this->moodle->mnet_host_id();

        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }
        if (!$groupid = $this->moodle->get_group_id($courseid, $name)) {
            throw new local_secretaria_exception('Unknown group');
        }

        $this->moodle->start_transaction();

        foreach ($users as $user) {
            if (!$userid = $this->moodle->get_user_id($mnethostid, $user)) {
                $this->moodle->rollback_transaction();
                throw new local_secretaria_exception('Unknown user');
            }
            $this->moodle->groups_remove_member($groupid, $userid);
        }

        $this->moodle->commit_transaction();
    }

    /* Grades */

    function get_course_grades($course, $users) {
        $mnethostid = $this->moodle->mnet_host_id();

        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }

        $usernames = array();
        foreach ($users as $user) {
            if (!$userid = $this->moodle->get_user_id($mnethostid, $user)) {
                throw new local_secretaria_exception('Unknown user');
            }
            $usernames[$userid] = $user;
        }

        $result = array();

        if ($grade_items = $this->moodle->grade_item_fetch_all($courseid)) {
            foreach ($grade_items as $grade_item) {
                $item = array('type' => $grade_item->itemtype,
                              'module' => $grade_item->itemmodule,
                              'idnumber' => $grade_item->idnumber,
                              'name' => $grade_item->itemname,
                              'grades' => array());

                $grades = $this->moodle->grade_get_grades(
                    $courseid, $grade_item->itemtype, $grade_item->itemmodule,
                    $grade_item->iteminstance, array_keys($usernames));

                foreach ($grades as $userid => $grade) {
                    $username = $usernames[$userid];
                    $item['grades'][$username] = $grade->str_grade;
                }

                $result[] = $item;
            }
        }

        return $result;
    }

    function get_user_grades($user, $courses)  {
        $mnethostid = $this->moodle->mnet_host_id();

        if (!$userid = $this->moodle->get_user_id($mnethostid, $user)) {
            throw new local_secretaria_exception('Unknown user');
        }

        $result = array();

        foreach ($courses as $course) {
            if (!$courseid = $this->moodle->get_course_id($course)) {
                throw new local_secretaria_exception('Unknown course');
            }
            $grade = $this->moodle->grade_get_course_grade($userid, $courseid);
            $result[$course] = $grade ? $grade->str_grade : null;
        }

        return $result;
    }
}
