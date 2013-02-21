<?php

class batch_view_export_courses extends batch_view_base {

    function view() {
        if ($data = $this->web->data_submitted()) {
            $materials = !empty($data['materials']);
            foreach ($data as $name => $value) {
                if (preg_match("/^course-/", $name)) {
                    $params = array(
                        'shortname' => stripslashes($value),
                        'materials' => $materials,
                    );
                    batch_queue::add_job('export_course', (object) $params);
                }
            }
            $this->web->redirect();
        }

        include('export_courses.html');
    }
}
