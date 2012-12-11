<?php

require_once($CFG->dirroot.'/course/lib.php');
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
        case 'int':
            return is_int($value) or is_string($value) and $value === (string) (int) $value;
        case 'bool':
            return is_bool($value) or $value === 0 or $value === 1 or $value === '0' or $aalue === '1';
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

    private $get_user_lastaccess_parameters = array(
        'username' => array('type' => 'username'),
    );

    private $create_user_parameters = array(
        'properties' => array(
            'type' => 'dict',
            'required' => array(
                'username' => array('type' => 'username'),
                'firstname' => array('type' => 'notags'),
                'lastname' => array('type' => 'notags'),
                'email' => array('type' => 'email'),
            ),
            'optional' => array(
                'password' => array('type' => 'raw'),
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

    private $get_user_groups_parameters = array(
        'user' => array('type' => 'username'),
        'course' => array('type' => 'text'),
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

    /* Surveys */

    private $get_survey_templates_parameters = array(
        'course' => array('type' => 'text'),
    );

    private $create_survey_parameters = array(
        'properties' => array(
            'type' => 'dict',
            'required' => array(
                'course' => array('type' => 'text'),
                'section' => array('type' => 'int'),
                'idnumber' => array('type' => 'raw'),
                'name' => array('type' => 'text'),
                'summary' => array('type' => 'raw'),
                'template' => array(
                    'type' => 'dict',
                    'required' => array(
                        'course' => array('type' => 'text'),
                        'idnumber' => array('type' => 'raw'),
                    ),
                ),
            ),
            'optional' => array(
                'opendate' => array(
                    'type' => 'dict',
                    'required' => array(
                        'year' => array('type' => 'int'),
                        'month' => array('type' => 'int'),
                        'day' => array('type' => 'int'),
                    ),
                ),
                'closedate' => array(
                    'type' => 'dict',
                    'required' => array(
                        'year' => array('type' => 'int'),
                        'month' => array('type' => 'int'),
                        'day' => array('type' => 'int'),
                    ),
                ),
            ),
        ),
    );

    /* Misc */

    private $send_mail_parameters = array(
        'message' => array(
            'type' => 'dict',
            'required' => array(
                'sender' => array('type' => 'username'),
                'course' => array('type' => 'text'),
                'subject' => array('type' => 'text'),
                'content' => array('type' => 'raw'),
                'to' => array('type' => 'list', 'of' => array('type' => 'username')),
            ),
            'optional' => array(
                'cc' => array('type' => 'list', 'of' => array('type' => 'username')),
                'bcc' => array('type' => 'list', 'of' => array('type' => 'username')),
            ),
        ),
    );

    private $has_course_parameters = array(
        'course' => array('type' => 'text'),
    );

    private $get_courses_parameters = array();
}

class local_secretaria_moodle_19 implements local_secretaria_moodle {

    private $transaction = false;

    function auth_plugin() {
        global $CFG;
        return isset($CFG->local_secretaria_auth) ? $CFG->local_secretaria_auth : 'manual';
    }

    function check_password($password) {
        return $password and check_password_policy($password);
    }

    function commit_transaction() {
        commit_sql();
        $this->transaction = false;
    }

    function create_survey($courseid, $section, $idnumber, $name, $summary,
                           $opendate, $closedate, $templateid) {
        global $CFG;
        require_once($CFG->dirroot.'/mod/questionnaire/lib.php');
        require_once($CFG->dirroot.'/mod/questionnaire/locallib.php');

        $course = get_record('course', 'id', $courseid);
        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        $module = get_record('modules', 'name', 'questionnaire');
        if (!$course or !$context or !$module) return;

        $qrecord = new object;
        $qrecord->course = $course->id;
        $qrecord->name = addslashes($name);
        $qrecord->summary = addslashes($summary);
        $qrecord->qtype = QUESTIONNAIREONCE;
        $qrecord->respondenttype = 'anonymous';
        $qrecord->resp_view = 0;
        $qrecord->opendate = $opendate;
        $qrecord->closedate = $closedate;
        $qrecord->resume = 0;
        $qrecord->navigate = 1; // not used
        $qrecord->grade = 0;
        $qrecord->timemodified = time();

        // questionnaire_add_instance
        $cm = new object;
        $qobject = new questionnaire(0, $qrecord, $course, $cm);
        $qobject->add_survey($templateid);
        $qobject->add_questions($templateid);
        $qrecord->sid = $qobject->survey_copy($course->id);
        $cm->instance = insert_record('questionnaire', $qrecord);
        set_field('questionnaire_survey', 'realm', 'private', 'id', $qrecord->sid);
        questionnaire_set_events($qrecord);

        // modedit.php
        $cm->course = $course->id;
        $cm->section = $section;
        $cm->visible = 0;
        $cm->module = $module->id;
        $cm->groupmode = !empty($course->groupmodeforce) ? $course->groupmode : 0;
        $cm->groupingid = $course->defaultgroupingid;
        $cm->groupmembersonly = 0;
        $cm->idnumber = addslashes($idnumber);

        $cm->coursemodule = add_course_module($cm);
        $sectionid = add_mod_to_section($cm);
        set_field('course_modules', 'section', $sectionid, 'id', $cm->coursemodule);
        set_coursemodule_visible($cm->coursemodule, $cm->visible);
        rebuild_course_cache($course->id);
    }

    function create_user($auth, $username, $password, $firstname, $lastname, $email) {
        global $CFG;

        $record = new object;
        $record->auth = $auth;
        $record->mnethostid = $CFG->mnet_localhost_id;
        $record->username = $username;
        $record->password = $password ? hash_internal_user_password($password) : 'not cached';
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
        role_unassign($roleid, $userid, 0, $context->id);
    }

    function get_course_id($shortname) {
        return get_field('course', 'id', 'shortname', addslashes($shortname));
    }

    function get_courses() {
        return get_records('course', 'id != '. SITEID, '', 'id, shortname');
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

    function get_role_assignments_by_course($courseid) {
        global $CFG;
        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        $sql = sprintf("SELECT ra.id, u.username AS user, r.shortname AS role " .
                       "FROM {$CFG->prefix}role_assignments ra " .
                       "JOIN {$CFG->prefix}user u ON u.id = ra.userid " .
                       "JOIN {$CFG->prefix}role r ON r.id = ra.roleid " .
                       "WHERE ra.contextid = %d AND u.mnethostid = %d AND u.deleted = 0",
                       $context->id, $CFG->mnet_localhost_id);
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

    function get_survey_id($courseid, $idnumber) {
        global $CFG;
        $sql = sprintf("SELECT q.sid " .
                       "FROM {$CFG->prefix}modules m " .
                       "JOIN {$CFG->prefix}course_modules cm ON cm.module = m.id " .
                       "JOIN {$CFG->prefix}questionnaire q ON q.id = cm.instance " .
                       "WHERE m.name = 'questionnaire' ".
                       "AND cm.course = %d AND cm.idnumber = '%s'",
                       $courseid, $idnumber);
        return get_field_sql($sql);
    }

    function get_survey_templates($courseid) {
        global $CFG;
        $sql = sprintf("SELECT q.id, q.name, cm.idnumber " .
                       "FROM {$CFG->prefix}modules m " .
                       "JOIN {$CFG->prefix}course_modules cm ON cm.module = m.id " .
                       "JOIN {$CFG->prefix}questionnaire q ON q.id = cm.instance " .
                       "JOIN {$CFG->prefix}questionnaire_survey qs ON qs.id = q.sid " .
                       "WHERE m.name = 'questionnaire' AND cm.course = %d " .
                       "AND qs.realm = 'template' AND qs.status != 4",
                       $courseid);
        return get_records_sql($sql);
    }

    function get_user_id($username) {
        global $CFG;
        $select = sprintf("username = '%s' AND mnethostid = %d AND deleted = 0",
                          addslashes($username), $CFG->mnet_localhost_id);
        return get_field_select('user', 'id', $select);
    }

    function get_user_lastaccess($userid) {
        global $CFG;

        $sql = sprintf("SELECT l.id, c.shortname AS course, l.timeaccess AS time " .
                       "FROM {$CFG->prefix}user_lastaccess l " .
                       "JOIN {$CFG->prefix}course c ON c.id = l.courseid " .
                       "WHERE l.userid = %d", $userid);

        return get_records_sql($sql);
    }

    function get_user_record($username) {
        global $CFG;
        $select = sprintf("mnethostid = %d AND username = '%s' AND deleted = 0",
                          $CFG->mnet_localhost_id, addslashes($username));
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
        $courseid = get_field('groups', 'courseid', 'id', $groupid);
        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        if (has_capability('moodle/course:view', $context, $userid)) {
            groups_add_member($groupid, $userid);
        }
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

    function groups_get_all_groups($courseid, $userid=0) {
        return groups_get_all_groups($courseid, $userid);
    }

    function groups_remove_member($groupid, $userid) {
        groups_remove_member($groupid, $userid);
    }

    function insert_role_assignment($courseid, $userid, $roleid) {
        $context = get_context_instance(CONTEXT_COURSE, $courseid);
        role_assign($roleid, $userid, 0, $context->id);
    }

    function make_timestamp($year, $month, $day, $hour=0, $minute=0, $second=0) {
        return make_timestamp($year, $month, $day, $hour, $minute, $second);
    }

    function prevent_local_passwords($auth) {
        return get_auth_plugin($auth)->prevent_local_passwords();
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

    function section_exists($courseid, $section) {
        return record_exists('course_sections', 'course', $courseid, 'section', $section);
    }

    function send_mail($sender, $courseid, $subject, $content, $to, $cc, $bcc) {
        global $CFG;

        require_once($CFG->dirroot . '/blocks/email_list/email/lib.php');

        $mail = new stdClass;
        $mail->userid = $sender;
        $mail->course = $courseid;
        $mail->subject = $subject;
        $mail->body = $content;
        $mail->timecreated = time();
        $mail->respondedid = 0;
        $mailid = insert_record('block_email_list_mail', $mail);

        $foldermail = new stdClass;
        $foldermail->mailid = $mailid;
        $folder = email_get_root_folder($sender, EMAIL_SENDBOX);
        $foldermail->folderid = $folder->id;
        insert_record('block_email_list_foldermail', $foldermail);

        $send = new stdClass;
        $send->course = $courseid;
        $send->mailid = $mailid;
        $send->readed = 0;
        $send->sended = 1;
        $send->answered = 0;

        foreach (array_merge($to, $cc, $bcc) as $userid) {
            email_create_parents_folders($userid);

            $send->userid = $userid;
            if (in_array($userid, $to)) {
                $send->type = 'to';
            } else if (in_array($userid, $cc)) {
                $send->type = 'cc';
            } else if (in_array($userid, $bcc)) {
                $send->type = 'bcc';
            }
            insert_record('block_email_list_send', $send);

            $foldermail = new stdClass;
            $foldermail->mailid = $mailid;
            $folder = email_get_root_folder($userid, EMAIL_INBOX);
            $foldermail->folderid = $folder->id;
            insert_record('block_email_list_foldermail', $foldermail);
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
