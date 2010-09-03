<?php

function local_course_backup($id) {
    $data = new object;

    $record = local_course_record($id);
    $data->gradeserror = $record->gradeserror;
    $data->restrictemail = $record->restrictemail;

    $data->materials = array();
    $records = get_records('local_materials', 'course', $id);
    if ($records) {
        foreach ($records as $record) {
            $data->materials[] = $record->path;
        }
    }

    return json_encode($data);
}

function local_course_create($id, $data) {
    local_course_update($id, $data);
}

function local_course_delete($id) {
    delete_records('local_course', 'course', $id);
    delete_records('local_materials', 'course', $id);
}

function local_course_edit_form($id, $mform) {
    $mform->addElement('header','',
                       get_string('email_list', 'block_email_list'));
    $choices = array();
    $choices['0'] = get_string('no');
    $choices['1'] = get_string('yes');
    $mform->addElement('select', 'local_restrictemail',
                       "Prohibeix entre alumnes", $choices);

    if ($id) {
        $record = local_course_record($id);
        $mform->setDefault('local_restrictemail', $record->restrictemail);
    }
}

function local_course_record($id) {
    $record = get_record('local_course', 'course', $id);
    if (!$record) {
        insert_record('local_course', (object) array('course' => $id));
        return local_course_record($id);
    }
    return $record;
}

function local_course_restore($id, $data) {
    $data = json_decode($data);

    $record = local_course_record($id);
    $record->gradeserror = $data->gradeserror;
    $record->restrictemail = $data->restrictemail;
    update_record('local_course', $record);

    foreach ($data->materials as $path) {
        $record = (object) array('course' => $id, 'path' => addslashes($path));
        insert_record('local_materials', $record);
    }
}

function local_course_update($id, $data) {
    $record = local_course_record($id);
    $record->restrictemail = $data->local_restrictemail;
    update_record('local_course', $record);
}

function local_login($userid, $password, $urltogo) {
    $validpassword = (int) check_password_policy($password);
    set_user_preference('local_validpassword', $validpassword, $userid);
}

function local_raise_resource_limits() {
    global $CFG;

    if (ini_get('max_execution_time')) {
        set_time_limit(3600);
    }

    if (empty($CFG->extramemorylimit)) {
        raise_memory_limit('128M');
    } else {
        raise_memory_limit($CFG->extramemorylimit);
    }

    if ($CFG->dbtype == 'mysql' or $CFG->dbtype == 'mysqli') {
        execute_sql("SET SESSION wait_timeout=3600", false);
    }
}

function local_root_category_name() {
    global $COURSE;

    if (empty($COURSE->category)) {
        return false;
    }

    $path = get_field('course_categories', 'path', 'id', $COURSE->category);
    $path = explode('/', $path);
    $id = $path[1];
    $name = get_field('course_categories', 'name', 'id', $id);

    return $name;
}
