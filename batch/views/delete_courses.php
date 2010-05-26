<?php

class batch_view_delete_courses extends batch_view_base {

    function view() {
        if ($data = $this->web->data_submitted()) {
            foreach ($data as $name => $value) {
                if (preg_match("/^course-/", $name)) {
                    $params = array('shortname' => stripslashes($value));
                    batch_queue::add_job('delete_course', (object) $params);
                }
            }
            $this->web->redirect();
        }

        include('delete_courses.html');
    }
}
