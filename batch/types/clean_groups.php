<?php

class batch_type_clean_groups extends batch_type_base {

    function execute($params) {
        $courses = get_records('course', '', '', '', 'id');
        if ($courses) {
            foreach ($courses as $course) {
                batch_course::clean_groups($course->id);
            }
        }
    }

}
