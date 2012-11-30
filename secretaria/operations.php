<?php

class local_secretaria_exception extends Exception {
    public $errorcode;
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
            'picture' => $record->picture ? $pixurl : '',
            'lastaccess' => (int) $record->lastaccess,
        );
    }

    function get_user_lastaccess($username) {
        $mnethostid = $this->moodle->mnet_host_id();

        if (!$userid = $this->moodle->get_user_id($mnethostid, $username)) {
            throw new local_secretaria_exception('Unknown user');
        }

        $result = array();

        if ($records = $this->moodle->get_user_lastaccess($userid)) {
            foreach ($records as $record) {
                $result[] = array('course' => $record->course,
                                  'time' => (int) $record->time);
            }
        }

        return $result;
    }

    function create_user($properties) {
        if (empty($properties['username']) or
            empty($properties['firstname']) or
            empty($properties['lastname'])) {
            throw new local_secretaria_exception('Invalid parameters');
        }

        $mnethostid = $this->moodle->mnet_host_id();
        $mnetlocalhostid = $this->moodle->mnet_localhost_id();

        if ($this->moodle->get_user_id($mnethostid, $properties['username'])) {
            throw new local_secretaria_exception('Duplicate username');
        }

        if ($mnethostid == $mnetlocalhostid) {
            $auth = 'manual';
            $password = $properties['password'];
            if (!$this->moodle->check_password($password)) {
                throw new local_secretaria_exception('Invalid password');
            }
        } else {
            $auth = 'mnet';
            $password = null;
        }

        $this->moodle->start_transaction();
        $this->moodle->create_user(
            $auth,
            $mnethostid,
            $properties['username'],
            $password,
            $properties['firstname'],
            $properties['lastname'],
            $properties['email']
        );
        $this->moodle->commit_transaction();
    }

    function update_user($username, $properties) {
        $record = new stdClass;
        $mnethostid = $this->moodle->mnet_host_id();
        $mnetlocalhostid = $this->moodle->mnet_localhost_id();

        if (!$record->id = $this->moodle->get_user_id($mnethostid, $username)) {
            throw new local_secretaria_exception('Unknown user');
        }

        if (isset($properties['username'])) {
            if (empty($properties['username'])) {
                throw new local_secretaria_exception('Invalid parameters');
            }
            if ($properties['username'] != $username) {
                if ($this->moodle->get_user_id($mnethostid, $properties['username'])) {
                    throw new local_secretaria_exception('Duplicate username');
                }
                $record->username = $properties['username'];
            }
        }
        if (isset($properties['password']) and $mnethostid == $mnetlocalhostid) {
            if (!$this->moodle->check_password($properties['password'])) {
                throw new local_secretaria_exception('Invalid password');
            }
        }
        if (isset($properties['firstname'])) {
            if (empty($properties['firstname'])) {
                throw new local_secretaria_exception('Invalid parameters');
            }
            $record->firstname = $properties['firstname'];
        }
        if (isset($properties['lastname'])) {
            if (empty($properties['lastname'])) {
                throw new local_secretaria_exception('Invalid parameters');
            }
            $record->lastname = $properties['lastname'];
        }
        if (isset($properties['email'])) {
            $record->email = $properties['email'];
        }

        $this->moodle->start_transaction();
        if (count((array) $record) > 1) {
            $this->moodle->update_record('user', $record);
        }
        if (isset($properties['password']) and $mnethostid == $mnetlocalhostid) {
            $this->moodle->update_password($record->id, $properties['password']);
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

        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }

        $enrolments = array();
        if ($records = $this->moodle->get_role_assignments_by_course($courseid, $mnethostid)) {
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
            if (!$courseid = $this->moodle->get_course_id($enrolment['course'])) {
                throw new local_secretaria_exception('Unknown course');
            }
            if (!$userid = $this->moodle->get_user_id($mnethostid, $enrolment['user'])) {
                throw new local_secretaria_exception('Unknown user');
            }
            if (!$roleid = $this->moodle->get_role_id($enrolment['role'])) {
                throw new local_secretaria_exception('Unknown role');
            }
            if (!$this->moodle->role_assignment_exists($courseid, $userid, $roleid)) {
                $this->moodle->insert_role_assignment($courseid, $userid, $roleid);
            }
        }

        $this->moodle->commit_transaction();
    }

    function unenrol_users($enrolments) {
        $mnethostid = $this->moodle->mnet_host_id();

        $this->moodle->start_transaction();

        foreach ($enrolments as $enrolment) {
            if (!$courseid = $this->moodle->get_course_id($enrolment['course'])) {
                throw new local_secretaria_exception('Unknown course');
            }
            if (!$userid = $this->moodle->get_user_id($mnethostid, $enrolment['user'])) {
                throw new local_secretaria_exception('Unknown user');
            }
            if (!$roleid = $this->moodle->get_role_id($enrolment['role'])) {
                throw new local_secretaria_exception('Unknown role');
            }
            $this->moodle->delete_role_assignment($courseid, $userid, $roleid);
        }

        $this->moodle->commit_transaction();
    }

    /* Groups */

    function get_groups($course) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }

        $groups = array();

        if ($records = $this->moodle->groups_get_all_groups($courseid)) {
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
        if (empty($name)) {
            throw new local_secretaria_exception('Invalid parameters');
        }
        if ($this->moodle->get_group_id($courseid, $name)) {
            throw new local_secretaria_exception('Duplicate group');
        }
        $this->moodle->start_transaction();
        $this->moodle->groups_create_group($courseid, $name, $description);
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
                throw new local_secretaria_exception('Unknown user');
            }
            $this->moodle->groups_remove_member($groupid, $userid);
        }

        $this->moodle->commit_transaction();
    }

    function get_user_groups($user, $course) {
        $mnethostid = $this->moodle->mnet_host_id();

        if (!$userid = $this->moodle->get_user_id($mnethostid, $user)) {
            throw new local_secretaria_exception('Unknown user');
        }
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }

        $groups = array();

        if ($records = $this->moodle->groups_get_all_groups($courseid, $userid)) {
            foreach ($records as $record) {
                $groups[] = $record->name;
            }
        }

        return $groups;
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
                    $item['grades'][] = array(
                        'user' => $username,
                        'grade' => $grade->str_grade,
                    );
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
            $result[] = array(
                'course' => $course,
                'grade' => $grade ? $grade->str_grade : '',
            );
        }

        return $result;
    }

    /* Surveys */

    function get_survey_templates($course) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }

        $result = array();

        if ($records = $this->moodle->get_survey_templates($courseid)) {
            foreach ($records as $record) {
                if ($record->idnumber) {
                    $result[] = array('name' => $record->name, 'idnumber' => $record->idnumber);
                }
            }
        }

        return $result;
    }

    function create_survey($properties) {
        if (empty($properties['idnumber']) or
            empty($properties['name']) or
            empty($properties['summary']) or
            empty($properties['template']['course']) or
            empty($properties['template']['idnumber'])) {
            throw new local_secretaria_exception('Invalid parameters');
        }

        if (!$courseid = $this->moodle->get_course_id($properties['course'])) {
            throw new local_secretaria_exception('Unknown course');
        }

        if (!$this->moodle->section_exists($courseid, $properties['section'])) {
            throw new local_secretaria_exception('Unknown section');
        }

        if ($this->moodle->get_survey_id($courseid, $properties['idnumber'])) {
            throw new local_secretaria_exception('Duplicate idnumber');
        }

        if (!$templatecourseid = $this->moodle->get_course_id($properties['template']['course'])) {
            throw new local_secretaria_exception('Unknown course');
        }

        if (!$templateid = $this->moodle->get_survey_id($templatecourseid,
                                                        $properties['template']['idnumber'])) {
            throw new local_secretaria_exception('Unknown survey');
        }

        $opendate = 0;
        if (isset($properties['opendate'])) {
            $opendate = $this->moodle->make_timestamp($properties['opendate']['year'],
                                                      $properties['opendate']['month'],
                                                      $properties['opendate']['day']);
        }
        $closedate = 0;
        if (isset($properties['closedate'])) {
            $closedate = $this->moodle->make_timestamp($properties['closedate']['year'],
                                                       $properties['closedate']['month'],
                                                       $properties['closedate']['day'],
                                                       23, 55);
        }

        $this->moodle->start_transaction();
        $this->moodle->create_survey($courseid, $properties['section'], $properties['idnumber'],
                                     $properties['name'], $properties['summary'],
                                     $opendate, $closedate, $templateid);
        $this->moodle->commit_transaction();
    }

    /* Control */

    function has_course($shortname) {
        return (bool) $this->moodle->get_course_id($shortname);
    }

    function get_courses() {
        $result = array();
        if ($records = $this->moodle->get_courses()) {
            foreach ($records as $record) {
                $result[] = $record->shortname;
            }
        }
        return $result;
    }

    /* Misc */

    function send_mail($message) {

        $mnethostid = $this->moodle->mnet_host_id();

        if (!$courseid = $this->moodle->get_course_id($message['course'])) {
            throw new local_secretaria_exception('Unknown course');
        }

        $usernames = array_merge(array($message['sender']), $message['to']);
        if (isset($message['cc'])) {
            $usernames = array_merge($usernames, $message['cc']);
        }
        if (isset($message['bcc'])) {
            $usernames = array_merge($usernames, $message['bcc']);
        }
        if (!$message['to'] or count($usernames) != count(array_unique($usernames))) {
            throw new local_secretaria_exception('Invalid parameters');
        }

        $sender = false;
        $to = array();
        $cc = array();
        $bcc = array();

        foreach ($usernames as $username) {
            if (!$userid = $this->moodle->get_user_id($mnethostid, $username)) {
                throw new local_secretaria_exception('Unknown user');
            }
            if ($username == $message['sender']) {
                $sender = $userid;
            } else if (in_array($username, $message['to'])) {
                $to[] = $userid;
            } else if (in_array($username, $message['cc'])) {
                $cc[] = $userid;
            } else if (in_array($username, $message['bcc'])) {
                $bcc[] = $userid;
            }
        }

        $this->moodle->send_mail($sender, $courseid, $message['subject'],
                                 $message['content'], $to, $cc, $bcc);
    }
}

interface local_secretaria_moodle {
    function check_password($password);
    function commit_transaction();
    function create_survey($courseid, $section, $name, $summary, $idnumber,
                           $opendate, $closedate, $templateid);
    function create_user($auth, $mnethostid, $username, $password,
                         $firstname, $lastname, $email);
    function delete_user($record);
    function delete_role_assignment($courseid, $userid, $roleid);
    function get_course_id($shortname);
    function get_courses();
    function get_group_id($courseid, $name);
    function get_group_members($groupid, $mnethostid);
    function get_role_assignments_by_course($courseid, $mnethostid);
    function get_role_assignments_by_user($userid);
    function get_role_id($role);
    function get_survey_id($courseid, $idnumber);
    function get_survey_templates($courseid);
    function get_user_id($mnethostid, $username);
    function get_user_lastaccess($userid);
    function get_user_record($mnethostid, $username);
    function grade_get_course_grade($userid, $courseid);
    function grade_get_grades($courseid, $itemtype, $itemmodule,
                              $iteminstance, $userids);
    function grade_item_fetch_all($courseid);
    function groups_add_member($groupid, $userid);
    function groups_create_group($courseid, $name, $description);
    function groups_delete_group($groupid);
    function groups_get_all_groups($courseid, $userid=0);
    function groups_remove_member($groupid, $userid);
    function insert_role_assignment($courseid, $userid, $roleid);
    function make_timestamp($year, $month, $day, $hour=0, $minute=0, $second=0);
    function mnet_host_id();
    function mnet_localhost_id();
    function role_assignment_exists($courseid, $userid, $roleid);
    function rollback_transaction(Exception $e);
    function section_exists($courseid, $section);
    function send_mail($sender, $courseid, $subject, $content, $to, $cc, $bcc);
    function start_transaction();
    function update_password($userid, $password);
    function update_record($table, $record);
    function user_picture_url($userid);
}
