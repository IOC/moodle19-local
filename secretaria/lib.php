<?php

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/querylib.php');
require_once($CFG->dirroot . '/local/secretaria/operations.php');

class local_secretaria_service {

    private $moodle;
    private $operations;

    function __construct() {
        $this->moodle = new local_secretaria_moodle_19;
        $this->operations = new local_secretaria_operations($this->moodle);
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
        try {
            return call_user_func_array(array($this->operations, $func), $args);
        } catch (Exception $e) {
            $this->moodle->rollback_transaction($e);
            throw $e;
        }
    }

    static function valid_param($param, $value) {
        switch ($param['type']) {
        case 'raw':
            return is_string($value);
        case 'alphanumext':
            return is_string($value) and preg_match('/^[a-zA-Z0-9_-]*$/', $value);
        case 'notags':
            return is_string($value) and $value === clean_param($value, PARAM_NOTAGS);
        case 'text':
            return is_string($value) and $value === clean_param($value, PARAM_TEXT);
        case 'username':
            return is_string($value) and preg_match('/^[a-z0-9\.-]*$/', $value);
        case 'email':
            return is_string($value) and validate_email($value);
        case 'list':
            if (!is_array($value)) return false;
            for ($i = 0; $i < count($value); $i++) {
                if (!isset($value[$i])) return false;
                if (!self::valid_param($param['of'], $value[$i])) return false;
            }
            return true;
        case 'dict':
            $required = isset($param['required']) ? $param['required'] : array();
            $optional = isset($param['optional']) ? $param['optional'] : array();
            $items = array_merge($required, $optional);
            if (!is_array($value)) return false;
            foreach ($required as $k => $v) {
                if (!isset($value[$k])) return false;
            }
            foreach ($value as $k => $v) {
                if (!isset($items[$k])) return false;
                if (!self::valid_param($items[$k], $v)) return false;
            }
            return true;
        }
    }

    /* Users */

    private $get_user_parameters = array(
        'username' => array('type' => 'username'),
    );

    private $create_user_parameters = array(
        'properties' => array(
            'type' => 'dict',
            'required' => array(
                'username' => array('type' => 'username'),
                'password' => array('type' => 'raw'),
                'firstname' => array('type' => 'notags'),
                'lastname' => array('type' => 'notags'),
                'email' => array('type' => 'email'),
            ),
        ),
    );

    private $update_user_parameters = array(
        'username' => array('type' => 'username'),
        'properties' => array(
            'type' => 'dict',
            'optional' => array(
                'username' => array('type' => 'username'),
                'password' => array('type' => 'raw'),
                'firstname' => array('type' => 'notags'),
                'lastname' => array('type' => 'notags'),
                'email' => array('type' => 'email'),
            ),
        ),
    );

    private $delete_user_parameters = array(
        'username' => array('type' => 'username'),
    );

    /* Enrolments */

    private $get_course_enrolments_parameters = array(
        'course' => array('type' => 'text'),
    );

    private $get_user_enrolments_parameters = array(
        'username' => array('type' => 'username'),
    );

    private $enrol_users_parameters = array(
        'enrolments' => array(
            'type' => 'list',
            'of' => array(
                'type' => 'dict',
                'required' => array(
                    'course' => array('type' => 'text'),
                    'user' => array('type' => 'username'),
                    'role' => array('type' => 'alphanumext'),
                ),
            ),
        ),
    );

    private $unenrol_users_parameters = array(
        'enrolments' => array(
            'type' => 'list',
            'of' => array(
                'type' => 'dict',
                'required' => array(
                    'course' => array('type' => 'text'),
                    'user' => array('type' => 'username'),
                    'role' => array('type' => 'alphanumext'),
                ),
            ),
        ),
    );


    /* Groups */

    private $get_groups_parameters = array(
        'course' => array('type' => 'text'),
    );

    private $create_group_parameters = array(
        'course' => array('type' => 'text'),
        'name' => array('type' => 'text'),
        'description' => array('type' => 'raw'),
    );

    private $delete_group_parameters = array(
        'course' => array('type' => 'text'),
        'name' => array('type' => 'text'),
    );

    private $get_group_members_parameters = array(
        'course' => array('type' => 'text'),
        'name' => array('type' => 'text'),
    );

    private $add_group_members_parameters = array(
        'course' => array('type' => 'text'),
        'name' => array('type' => 'text'),
        'users' => array('type' => 'list', 'of' => array('type' => 'username')),
    );

    private $remove_group_members_parameters = array(
        'course' => array('type' => 'text'),
        'name' => array('type' => 'text'),
        'users' => array('type' => 'list', 'of' => array('type' => 'username')),
    );

    /* Grades */

    private $get_course_grades_parameters = array(
        'course' => array('type' => 'text'),
        'users' => array('type' => 'list', 'of' => array('type' => 'username')),
    );

    private $get_user_grades_parameters = array(
        'user' => array('type' => 'username'),
        'courses' => array('type' => 'list', 'of' => array('type' => 'text')),
    );
}

class local_secretaria_moodle_19 implements local_secretaria_moodle {

    private $transaction = false;

    function check_password($password) {
        return check_password_policy($password);
    }

    function commit_transaction() {
        commit_sql();
        $this->transaction = false;
    }

    function create_user($auth, $mnethostid, $username, $password,
                         $firstname, $lastname, $email) {
        global $CFG;

        $record = new object;
        $record->auth = $auth;
        $record->mnethostid = $mnethostid;
        $record->username = $username;
        if ($password) {
            $record->password = hash_internal_user_password($password);
        } else {
            $record->password = 'not cached';
        }
        $record->firstname = $firstname;
        $record->lastname = $lastname;
        $record->email = $email;
        $record->deleted = 0;
        $record->confirmed = 1;
        $record->lang = $CFG->lang;
        $record->timemodified = time();

        insert_record('user', $record);
    }

    function delete_user($record) {
        delete_user($record);
    }

    function delete_role_assignment($courseid, $userid, $roleid) {
        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        delete_records('role_assignments', 'contextid', $context->id,
                       'userid', $userid, 'roleid', $roleid);
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

    function get_role_assignments_by_course($courseid, $mnethostid) {
        global $CFG;
        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        $sql = sprintf("SELECT ra.id, u.username AS user, r.shortname AS role " .
                       "FROM {$CFG->prefix}role_assignments ra " .
                       "JOIN {$CFG->prefix}user u ON u.id = ra.userid " .
                       "JOIN {$CFG->prefix}role r ON r.id = ra.roleid " .
                       "WHERE ra.contextid = %d AND u.mnethostid = %d AND u.deleted = 0",
                       $context->id, $mnethostid);
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
                if ($item->itemtype == 'course') {
                    $item->itemmodule = '';
                } elseif ($item->itemtype == 'category') {
                    $category = $item->load_parent_category();
                    $item->itemname = $category->get_name();
                    $item->itemmodule = '';
                }
            }
        }
        return $items;
    }

    function groups_add_member($groupid, $userid) {
        return groups_add_member($groupid, $userid);
    }

    function groups_create_group($courseid, $name, $description) {
        $record = new stdClass;
        $record->courseid = $courseid;
        $record->name = $name;
        $record->description = $description;
        $record->timemodified = time();
        $record->timecreated = time();
        insert_record('groups', addslashes_recursive($record));
    }

    function groups_delete_group($groupid) {
        groups_delete_group($groupid);
    }

    function groups_remove_member($groupid, $userid) {
        groups_remove_member($groupid, $userid);
    }

    function insert_role_assignment($courseid, $userid, $roleid) {
        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        $record = (object) array(
            'contextid' => $context->id,
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

    function role_assignment_exists($courseid, $userid, $roleid) {
        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        return record_exists('role_assignments', 'contextid', $context->id,
                             'userid', $userid, 'roleid', $roleid);
    }

    function rollback_transaction(Exception $e) {
        if ($this->transaction) {
            rollback_sql();
            $this->transaction = false;
        }
    }

    function start_transaction() {
        begin_sql();
        $this->transaction = true;
    }

    function update_password($userid, $password) {
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
