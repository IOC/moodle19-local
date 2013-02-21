<?php

class batch_type_delete_course extends batch_type_base {

    function execute($params) {
        $courseid = get_field('course', 'id', 'shortname', $params->shortname);
        batch_course::delete_course($courseid);
    }

    function params_info($params) {
        return get_string('course') . ': ' . $params->shortname;
    }

}
