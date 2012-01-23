<?php

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/querylib.php');

class local_secretaria_service {

    private $moodle;

    function __construct($moodle=null) {
        $this->moodle = $moodle ? $moodle : new local_secretaria_moodle;
    }

    /* Users */

    function get_user($username) {
        global $CFG;

        if ($record = $this->moodle->get_user_record($username)) {
            $pixurl = "{$CFG->wwwroot}/user/pix.php/{$record->id}/f1.jpg";
            return array('username' => $username,
                         'firstname' => $record->firstname,
                         'lastname' => $record->lastname,
                         'email' => $record->email,
                         'picture' => $record->picture ? $pixurl : null);
        }
    }

    function create_user($properties) {
        if (empty($properties['username']) or
            empty($properties['password']) or
            empty($properties['firstname']) or
            empty($properties['lastname']) or
            empty($properties['email'])) {
            return false;
        }

        if ($this->moodle->get_user_id($properties['username'])) {
            return false;
        }

        return $this->moodle->insert_user($properties['username'], $properties['password'],
                                          $properties['firstname'], $properties['lastname'],
                                          $properties['email']);
    }

    function update_user($username, $properties) {
        $record = new stdClass;

        if (!$record->id = $this->moodle->get_user_id($username)) {
            return false;
        }

        if (!empty($properties['username']) and $properties['username'] != $username) {
            if ($this->moodle->get_user_id($properties['username'])) {
                return false;
            }
            $record->username = $properties['username'];
        }
        if (!empty($properties['password'])) {
            $record->password = hash_internal_user_password($properties['password']);
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

        return $this->moodle->update_record('user', $record);
    }

    function delete_user($username) {
        if (!$record = $this->moodle->get_user_record($username)) {
            return false;
        }
        $this->moodle->delete_user($record);
        return true;
    }

    /* Enrolments */

    function get_course_enrolments($course) {
        if (!$contextid = $this->moodle->get_context_id($course)) {
            return null;
        }

        $enrolments = array();
        if ($records = $this->moodle->get_role_assignments_by_context($contextid)) {
            foreach ($records as $record) {
                $enrolments[] = array('user' => $record->user, 'role' => $record->role);
            }
        }

        return $enrolments;
    }

    function get_user_enrolments($username) {
        if (!$userid = $this->moodle->get_user_id($username)) {
            return null;
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
        $this->moodle->start_transaction();

        foreach ($enrolments as $enrolment) {
            $contextid = $this->moodle->get_context_id($enrolment['course']);
            $userid = $this->moodle->get_user_id($enrolment['user']);
            $roleid = $this->moodle->get_role_id($enrolment['role']);

            if (!$contextid or !$userid or !$roleid) {
                $this->moodle->rollback_transaction();
                return false;
            }

            if (!$this->moodle->role_assignment_exists($contextid, $userid, $roleid)) {
                if (!$this->moodle->insert_role_assignment($contextid, $userid, $roleid)) {
                    $this->moodle->rollback_transaction();
                    return false;
                }
            }
        }

        $this->moodle->commit_transaction();

        return true;
    }

    function unenrol_users($enrolments) {
        $this->moodle->start_transaction();

        foreach ($enrolments as $enrolment) {
            $contextid = $this->moodle->get_context_id($enrolment['course']);
            $userid = $this->moodle->get_user_id($enrolment['user']);
            $roleid = $this->moodle->get_role_id($enrolment['role']);

            if (!$contextid or !$userid or !$roleid) {
                $this->moodle->rollback_transaction();
                return false;
            }

            $this->moodle->delete_role_assignment($contextid, $userid, $roleid);
        }

        $this->moodle->commit_transaction();

        return true;
    }

    /* Groups */

    function get_groups($course) {
        global $CFG;

        if (!$courseid = $this->moodle->get_course_id($course)) {
            return null;
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
            return null;
        }
        if ($this->moodle->get_group_id($courseid, $name)) {
            return null;
        }
        return $this->moodle->insert_group($courseid, $name, $description);
    }

    function delete_group($course, $name) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            return false;
        }
        if (!$groupid = $this->moodle->get_group_id($courseid, $name)) {
            return false;
        }
        $this->moodle->groups_delete_group($groupid);
        return true;
    }

    function get_group_members($course, $name) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            return null;
        }
        if (!$groupid = $this->moodle->get_group_id($courseid, $name)) {
            return null;
        }
        $users = array();
        if ($records = $this->moodle->get_group_members($groupid)) {
            foreach ($records as $record) {
                $users[] = $record->username;
            }
        }
        return $users;
    }

    function add_group_members($course, $name, $users) {
        $this->moodle->start_transaction();

        if (!$courseid = $this->moodle->get_course_id($course)) {
            return false;
        }
        if (!$groupid = $this->moodle->get_group_id($courseid, $name)) {
            return false;
        }

        foreach ($users as $user) {
            $userid = $this->moodle->get_user_id($user);
            if (!$userid or !$this->moodle->groups_add_member($groupid, $userid)) {
                $this->moodle->rollback_transaction();
                return false;
            }
        }

        $this->moodle->commit_transaction();

        return true;
    }

    function remove_group_members($course, $name, $users) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            return false;
        }
        if (!$groupid = $this->moodle->get_group_id($courseid, $name)) {
            return false;
        }

        $this->moodle->start_transaction();

        foreach ($users as $user) {
            if (!$userid = $this->moodle->get_user_id($user)) {
                $this->moodle->rollback_transaction();
                return false;
            }
            $this->moodle->groups_remove_member($groupid, $userid);
        }

        $this->moodle->commit_transaction();

        return true;
    }

    /* Grades */

    function get_course_grades($course, $users) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            return null;
        }

        $usernames = array();
        foreach ($users as $user) {
            if (!$userid = $this->moodle->get_user_id($user)) {
                return null;
            }
            $usernames[$userid] = $user;
        }

        if (!$grade_items = $this->moodle->grade_item_fetch_all($courseid)) {
            return null;
        }

        $result = array();

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

        return $result;
    }

    function get_user_grades($user, $courses)  {
        if (!$userid = $this->moodle->get_user_id($user)) {
            return null;
        }

        $grades = array();

        foreach ($courses as $course) {
            if (!$courseid = $this->moodle->get_course_id($course)) {
                return null;
            }
            if (!$grade = $this->moodle->grade_get_course_grade($userid, $courseid)) {
                return null;
            }
            $grades[$course] = $grade->str_grade;
        }

        return $grades;
    }
}

class local_secretaria_moodle {

    function commit_transaction() {
        commit_sql();
    }

    function delete_user($record) {
        delete_user($record);
    }

    function delete_role_assignment($contextid, $userid, $roleid) {
        delete_records('role_assignments', 'contextid', $contextid,
                       'userid', $userid, 'roleid', $roleid);
    }

    function get_context_id($course) {
        if (!$courseid = get_field('course', 'id', 'shortname', addslashes($course))) {
            return false;
        }
        if (!$context = get_context_instance(CONTEXT_COURSE, $courseid)) {
            return false;
        }
        return $context->id;
    }

    function get_course_id($shortname) {
        return get_field('course', 'id', 'shortname', addslashes($shortname));
    }

    function get_group_id($courseid, $name) {
        return get_field('groups', 'id', 'courseid', $courseid, 'name', addslashes($name));
    }

    function get_group_members($groupid) {
        global $CFG;
        $sql = sprintf("SELECT DISTINCT u.id, u.username " .
                       "FROM {$CFG->prefix}groups_members gm " .
                       "JOIN {$CFG->prefix}user u ON u.id = gm.userid " .
                       "WHERE gm.groupid = %d AND u.mnethostid = %d AND u.deleted = 0",
                       $groupid, $CFG->mnet_localhost_id);
        return get_records_sql($sql);
    }

    function get_groups($courseid) {
        return get_records('groups', 'courseid', $courseid);
    }

    function get_role_assignments_by_context($contextid) {
        global $CFG;
        $sql = sprintf("SELECT ra.id, u.username AS user, r.shortname AS role " .
                       "FROM {$CFG->prefix}role_assignments ra " .
                       "JOIN {$CFG->prefix}user u ON u.id = ra.userid " .
                       "JOIN {$CFG->prefix}role r ON r.id = ra.roleid " .
                       "WHERE ra.contextid = %d AND u.mnethostid = %d AND u.deleted = 0",
                       $contextid, $CFG->mnet_localhost_id);
        return get_records_sql($sql);
    }

    function get_role_assignments_by_user($userid) {
        global $CFG;
        $sql = sprintf("SELECT ra.id, c.shortname AS course, r.shortname AS role " .
                       "FROM {$CFG->prefix}role_assignments ra " .
                       "JOIN {$CFG->prefix}context ct ON ct.id = ra.contextid " .
                       "JOIN {$CFG->prefix}course c ON c.id = ct.instanceid " .
                       "JOIN {$CFG->prefix}role r ON r.id = ra.roleid " .
                       "WHERE ra.userid = %d AND ct.contextlevel = %d",
                       $userid, CONTEXT_COURSE);
        return get_records_sql($sql);
    }

    function get_role_id($role) {
        return get_field('role', 'id', 'shortname', addslashes($role));
    }

    function get_user_id($username) {
        global $CFG;
        $select = sprintf("username = '%s' AND mnethostid = %d AND deleted = 0",
                          addslashes($username), $CFG->mnet_localhost_id);
        return get_field_select('user', 'id', $select);
    }

    function get_user_record($username) {
        global $CFG;
        $select = sprintf("username = '%s' AND mnethostid = %d AND deleted = 0",
                          addslashes($username), $CFG->mnet_localhost_id);
        return get_record_select('user', $select);
    }

    function grade_get_course_grade($userid, $courseid) {
        return grade_get_course_grade($userid, $courseid);
    }

    function grade_get_grades($courseid, $itemtype, $itemmodule, $iteminstance, $userids) {
        $grades = grade_get_grades($courseid, $itemtype, $itemmodule, $iteminstance, $userids);
        return current($grades->items)->grades;
    }

    function grade_item_fetch_all($courseid) {
        $items = grade_item::fetch_all(array('courseid' => $courseid));
        if ($items) {
            foreach ($items as $item) {
                if ($item->itemtype == 'category') {
                    $category = $item->load_parent_category();
                    $item->itemname = $category->get_name();
                }
            }
        }
        return $items;
    }

    function groups_add_member($groupid, $userid) {
        return groups_add_member($groupid, $userid);
    }

    function groups_delete_group($groupid) {
        groups_delete_group($groupid);
    }

    function groups_remove_member($groupid, $userid) {
        groups_remove_member($groupid, $userid);
    }

    function insert_group($courseid, $name, $description) {
        $record = new stdClass;
        $record->courseid = $courseid;
        $record->name = $name;
        $record->description = $description;
        $record->timemodified = time();
        $record->timecreated = time();
        return (bool) insert_record('groups', addslashes_recursive($record));
    }

    function insert_role_assignment($contextid, $userid, $roleid) {
        $record = (object) array(
            'contextid' => $contextid,
            'userid' => $userid,
            'roleid' => $roleid,
            'enrol' => 'manual',
            'timemodified' => time(),
        );
        return (bool) insert_record('role_assignments', addslashes_recursive($record));
    }

    function insert_user($username, $password, $firstname, $lastname, $email) {
        global $CFG;

        $record = new object;
        $record->username = $username;
        $record->password = hash_internal_user_password($password);
        $record->firstname = $firstname;
        $record->lastname = $lastname;
        $record->email = $email;
        $record->mnethostid = $CFG->mnet_localhost_id;
        $record->deleted = 0;
        $record->confirmed = 1;
        $record->lang = 'ca_utf8';
        $record->timemodified = time();

        return (bool) insert_record('user', $record);
    }

    function role_assignment_exists($contextid, $userid, $roleid) {
        return record_exists('role_assignments', 'contextid', $contextid,
                             'userid', $userid, 'roleid', $roleid);
    }

    function rollback_transaction() {
        rollback_sql();
    }

    function start_transaction() {
        begin_sql();
    }

    function update_record($table, $record) {
        return (bool) update_record($table, addslashes_recursive($record));
    }
}
