<?php

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/querylib.php');
require_once($CFG->dirroot . '/local/secretaria/operations.php');

class local_secretaria_service {

    private $operations;

    function __construct() {
        $moodle = new local_secretaria_moodle;
        $this->operations = new local_secretaria_operations($moodle);
    }

    function execute($func, $args) {
        if (!is_callable(array($this->operations, $func))) {
            throw new local_secretaria_exception('Unknown function');
        }
        $var = "{$func}_parameters";
        $params = $this->$var;
        if (count($params) !== count($args)) {
            throw new local_secretaria_exception('Invalid parameters');
        }
        $arg = reset($args);
        foreach ($params as $param) {
            if (!self::valid_param($param, $arg)) {
                throw new local_secretaria_exception('Invalid parameters');
            }
            $arg = next($args);
        }
        return call_user_func_array(array($this->operations, $func), $args);
    }

    static function valid_param($param, $value) {
        switch ($param['type']) {
        case 'string':
            if (!is_string($value)) return false;
            if (empty($value) and empty($param['optional'])) return false;
            return true;
        case 'list':
            if (!is_array($value)) return false;
            for ($i = 0; $i < count($value); $i++) {
                if (!isset($value[$i])) return false;
                if (!self::valid_param($param['of'], $value[$i])) return false;
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

    private $create_user_parameters = array(
        'properties' => array(
            'type' => 'dictionary',
            'required' => array('username', 'password',
                                'firstname', 'lastname', 'email'),
        ),
    );

    private $update_user_parameters = array(
        'username' => array('type' => 'string'),
        'properties' => array('type' => 'dictionary'),
    );

    private $delete_user_parameters = array(
        'username' => array('type' => 'string'),
    );

    /* Enrolments */

    private $get_course_enrolments_parameters = array(
        'course' => array('type' => 'string'),
    );

    private $get_user_enrolments_parameters = array(
        'username' => array('type' => 'string'),
    );

    private $enrol_users_parameters = array(
        'enrolments' => array(
            'type' => 'list',
            'of' => array('type' => 'dictionary',
                          'required' => array('course', 'user', 'role')),
        ),
    );

    private $unenrol_users_parameters = array(
        'enrolments' => array(
            'type' => 'list',
            'of' => array('type' => 'dictionary',
                          'required' => array('course', 'user', 'role')),
        ),
    );


    /* Groups */

    private $get_groups_parameters = array(
        'course' => array('type' => 'string'),
    );

    private $create_group_parameters = array(
        'course' => array('type' => 'string'),
        'name' => array('type' => 'string'),
        'description' => array('type' => 'string', 'optional' => 'true'),
    );

    private $delete_group_parameters = array(
        'course' => array('type' => 'string'),
        'name' => array('type' => 'string'),
    );

    private $get_group_members_parameters = array(
        'course' => array('type' => 'string'),
        'name' => array('type' => 'string'),
    );

    private $add_group_members_parameters = array(
        'course' => array('type' => 'string'),
        'name' => array('type' => 'string'),
        'users' => array('type' => 'list', 'of' => array('type' => 'string')),
    );

    private $remove_group_members_parameters = array(
        'course' => array('type' => 'string'),
        'name' => array('type' => 'string'),
        'users' => array('type' => 'list', 'of' => array('type' => 'string')),
    );

    /* Grades */

    private $get_course_grades_parameters = array(
        'course' => array('type' => 'string'),
        'users' => array('type' => 'list', 'of' => array('type' => 'string')),
    );

    private $get_user_grades_parameters = array(
        'user' => array('type' => 'string'),
        'courses' => array('type' => 'list', 'of' => array('type' => 'string')),
    );
}

class local_secretaria_moodle {

    function commit_transaction() {
        commit_sql();
    }

    function create_user($auth, $mnethostid, $username, $password,
                         $firstname, $lastname, $email) {

        $record = new object;
        $record->auth = $auth;
        $record->mnethostid = $mnethostid;
        $record->username = $username;
        $record->password = hash_internal_user_password($password);
        $record->firstname = $firstname;
        $record->lastname = $lastname;
        $record->email = $email;
        $record->deleted = 0;
        $record->confirmed = 1;
        $record->lang = 'ca_utf8';
        $record->timemodified = time();

        insert_record('user', $record);
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

    function get_group_members($groupid, $mnethostid) {
        global $CFG;
        $sql = sprintf("SELECT DISTINCT u.id, u.username " .
                       "FROM {$CFG->prefix}groups_members gm " .
                       "JOIN {$CFG->prefix}user u ON u.id = gm.userid " .
                       "WHERE gm.groupid = %d AND u.mnethostid = %d AND u.deleted = 0",
                       $groupid, $mnethostid);
        return get_records_sql($sql);
    }

    function get_groups($courseid) {
        return get_records('groups', 'courseid', $courseid);
    }

    function get_role_assignments_by_context($contextid, $mnethostid) {
        global $CFG;
        $sql = sprintf("SELECT ra.id, u.username AS user, r.shortname AS role " .
                       "FROM {$CFG->prefix}role_assignments ra " .
                       "JOIN {$CFG->prefix}user u ON u.id = ra.userid " .
                       "JOIN {$CFG->prefix}role r ON r.id = ra.roleid " .
                       "WHERE ra.contextid = %d AND u.mnethostid = %d AND u.deleted = 0",
                       $contextid, $mnethostid);
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

    function get_user_id($mnethostid, $username) {
        $select = sprintf("username = '%s' AND mnethostid = %d AND deleted = 0",
                          addslashes($username), $mnethostid);
        return get_field_select('user', 'id', $select);
    }

    function get_user_record($mnethostid, $username) {
        $select = sprintf("mnethostid = %d AND username = '%s' AND deleted = 0",
                          $mnethostid, addslashes($username));
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
        insert_record('groups', addslashes_recursive($record));
    }

    function insert_role_assignment($contextid, $userid, $roleid) {
        $record = (object) array(
            'contextid' => $contextid,
            'userid' => $userid,
            'roleid' => $roleid,
            'enrol' => 'manual',
            'timemodified' => time(),
        );
        insert_record('role_assignments', addslashes_recursive($record));
    }

    function mnet_host_id() {
        return $this->mnet_localhost_id();
    }

    function mnet_localhost_id() {
        global $CFG;
        return (int) $CFG->mnet_localhost_id;
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

    function update_user_password($userid, $password) {
        $record = get_record('user', 'id', $userid);
        update_internal_user_password($record, $password);
    }

    function update_record($table, $record) {
        update_record($table, addslashes_recursive($record));
    }

    function user_picture_url($userid) {
        global $CFG;
        return "{$CFG->wwwroot}/user/pix.php/$userid/f1.jpg";
    }
}
