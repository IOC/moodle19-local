<?php

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/querylib.php');

class local_secretaria_exception extends Exception {
}

class local_secretaria_service {

    private $moodle;

    function __construct($moodle=null) {
        $this->moodle = $moodle ? $moodle : new local_secretaria_moodle;
    }

    function execute($func, $args) {
        if (!is_callable(array($this, $func))) {
            throw new local_secretaria_exception('Unknown function');
        }
        $var = "{$func}_parameters";
        $params = $this->$var;
        if (count($params) !== count($args)) {
            throw new local_secretaria_exception('Invalid parameters');
        }
        $arg = reset($args);
        foreach ($params as $param) {
            if (!$this->valid_param($param, $arg)) {
                throw new local_secretaria_exception('Invalid parameters');
            }
            $arg = next($args);
        }
        return call_user_func_array(array($this, $func), $args);
    }

    function valid_param($param, $value) {
        switch ($param['type']) {
        case 'string':
            if (!is_string($value)) return false;
            if (empty($value) and empty($param['optional'])) return false;
            return true;
        case 'list':
            if (!is_array($value)) return false;
            for ($i = 0; $i < count($value); $i++) {
                if (!isset($value[$i])) return false;
                if (!$this->valid_param($param['of'], $value[$i])) return false;
            }
            return true;
        case 'dictionary':
            if (!is_array($value)) return false;
            foreach ($value as $k => $v) {
                if (!is_string($k) or !is_string($v)) return false;
            }
            if (isset($param['required'])) {
                foreach ($param['required'] as $k) {
                    if (empty($value[$k])) return false;
                }
            }
            return true;
        }
    }

    /* Users */

    private $get_user_parameters = array(
        'username' => array('type' => 'string'),
    );

    function get_user($username) {
        global $CFG;

        if (!$record = $this->moodle->get_user_record($username)) {
            throw new local_secretaria_exception('Unknown user');
        }

        $pixurl = "{$CFG->wwwroot}/user/pix.php/{$record->id}/f1.jpg";
        return array('username' => $username,
                     'firstname' => $record->firstname,
                     'lastname' => $record->lastname,
                     'email' => $record->email,
                     'picture' => $record->picture ? $pixurl : null);
    }

    private $create_user_parameters = array(
        'properties' => array(
            'type' => 'dictionary',
            'required' => array('username', 'password',
                                'firstname', 'lastname', 'email'),
        ),
    );

    function create_user($properties) {
        if ($this->moodle->get_user_id($properties['username'])) {
            throw new local_secretaria_exception('Duplicate username');
        }

       $this->moodle->insert_user(
            $properties['username'],
            $properties['password'],
            $properties['firstname'],
            $properties['lastname'],
            $properties['email']
        );
    }

    private $update_user_parameters = array(
        'username' => array('type' => 'string'),
        'properties' => array('type' => 'dictionary'),
    );

    function update_user($username, $properties) {
        $record = new stdClass;

        if (!$record->id = $this->moodle->get_user_id($username)) {
            throw new local_secretaria_exception('Unknown user');
        }

        if (!empty($properties['username']) and $properties['username'] != $username) {
            if ($this->moodle->get_user_id($properties['username'])) {
                throw new local_secretaria_exception('Duplicate username');
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

        $this->moodle->update_record('user', $record);
    }

    private $delete_user_parameters = array(
        'username' => array('type' => 'string'),
    );

    function delete_user($username) {
        if (!$record = $this->moodle->get_user_record($username)) {
            throw new local_secretaria_exception('Unknown user');
        }
        $this->moodle->delete_user($record);
    }

    /* Enrolments */

    private $get_course_enrolments_parameters = array(
        'course' => array('type' => 'string'),
    );

    function get_course_enrolments($course) {
        if (!$contextid = $this->moodle->get_context_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }

        $enrolments = array();
        if ($records = $this->moodle->get_role_assignments_by_context($contextid)) {
            foreach ($records as $record) {
                $enrolments[] = array('user' => $record->user, 'role' => $record->role);
            }
        }

        return $enrolments;
    }

    private $get_user_enrolments_parameters = array(
        'username' => array('type' => 'string'),
    );

    function get_user_enrolments($username) {
        if (!$userid = $this->moodle->get_user_id($username)) {
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

    private $enrol_users_parameters = array(
        'enrolments' => array(
            'type' => 'list',
            'of' => array('type' => 'dictionary',
                          'required' => array('course', 'user', 'role')),
        ),
    );

    function enrol_users($enrolments) {
        $this->moodle->start_transaction();

        foreach ($enrolments as $enrolment) {
            if (!$contextid = $this->moodle->get_context_id($enrolment['course'])) {
                $this->moodle->rollback_transaction();
                throw new local_secretaria_exception('Unknown course');
            }
            if (!$userid = $this->moodle->get_user_id($enrolment['user'])) {
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

    private $unenrol_users_parameters = array(
        'enrolments' => array(
            'type' => 'list',
            'of' => array('type' => 'dictionary',
                          'required' => array('course', 'user', 'role')),
        ),
    );

    function unenrol_users($enrolments) {
        $this->moodle->start_transaction();

        foreach ($enrolments as $enrolment) {
            if (!$contextid = $this->moodle->get_context_id($enrolment['course'])) {
                $this->moodle->rollback_transaction();
                throw new local_secretaria_exception('Unknown course');
            }
            if (!$userid = $this->moodle->get_user_id($enrolment['user'])) {
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

    private $get_groups_parameters = array(
        'course' => array('type' => 'string'),
    );

    function get_groups($course) {
        global $CFG;

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

    private $create_group_parameters = array(
        'course' => array('type' => 'string'),
        'name' => array('type' => 'string'),
        'description' => array('type' => 'string', 'optional' => 'true'),
    );

    function create_group($course, $name, $description) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }
        if ($this->moodle->get_group_id($courseid, $name)) {
            throw new local_secretaria_exception('Duplicate group');
        }
        $this->moodle->insert_group($courseid, $name, $description);
    }

    private $delete_group_parameters = array(
        'course' => array('type' => 'string'),
        'name' => array('type' => 'string'),
    );

    function delete_group($course, $name) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }
        if (!$groupid = $this->moodle->get_group_id($courseid, $name)) {
            throw new local_secretaria_exception('Unknown group');
        }
        $this->moodle->groups_delete_group($groupid);
    }


    private $get_group_members_parameters = array(
        'course' => array('type' => 'string'),
        'name' => array('type' => 'string'),
    );

    function get_group_members($course, $name) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }
        if (!$groupid = $this->moodle->get_group_id($courseid, $name)) {
            throw new local_secretaria_exception('Unknown group');
        }
        $users = array();
        if ($records = $this->moodle->get_group_members($groupid)) {
            foreach ($records as $record) {
                $users[] = $record->username;
            }
        }
        return $users;
    }

    private $add_group_members_parameters = array(
        'course' => array('type' => 'string'),
        'name' => array('type' => 'string'),
        'users' => array('type' => 'list', 'of' => 'string'),
    );

    function add_group_members($course, $name, $users) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
       }
        if (!$groupid = $this->moodle->get_group_id($courseid, $name)) {
            throw new local_secretaria_exception('Unknown group');
        }

        $this->moodle->start_transaction();

        foreach ($users as $user) {
            if (!$userid = $this->moodle->get_user_id($user)) {
                $this->moodle->rollback_transaction();
                throw new local_secretaria_exception('Unknown user');
            }
            $this->moodle->groups_add_member($groupid, $userid);
        }

        $this->moodle->commit_transaction();
    }

    private $remove_group_members_parameters = array(
        'course' => array('type' => 'string'),
        'name' => array('type' => 'string'),
        'users' => array('type' => 'list', 'of' => 'string'),
    );

    function remove_group_members($course, $name, $users) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }
        if (!$groupid = $this->moodle->get_group_id($courseid, $name)) {
            throw new local_secretaria_exception('Unknown group');
        }

        $this->moodle->start_transaction();

        foreach ($users as $user) {
            if (!$userid = $this->moodle->get_user_id($user)) {
                $this->moodle->rollback_transaction();
                throw new local_secretaria_exception('Unknown user');
            }
            $this->moodle->groups_remove_member($groupid, $userid);
        }

        $this->moodle->commit_transaction();
    }

    /* Grades */

    private $get_course_grades_parameters = array(
        'course' => array('type' => 'string'),
        'users' => array('type' => 'list', 'of' => 'string'),
    );

    function get_course_grades($course, $users) {
        if (!$courseid = $this->moodle->get_course_id($course)) {
            throw new local_secretaria_exception('Unknown course');
        }

        $usernames = array();
        foreach ($users as $user) {
            if (!$userid = $this->moodle->get_user_id($user)) {
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

    private $get_user_grades_parameters = array(
        'user' => array('type' => 'string'),
        'courses' => array('type' => 'list', 'of' => 'string'),
    );

    function get_user_grades($user, $courses)  {
        if (!$userid = $this->moodle->get_user_id($user)) {
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
