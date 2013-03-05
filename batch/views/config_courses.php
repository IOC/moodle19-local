<?php

class batch_view_config_courses extends batch_view_base {

    function view() {
        if ($data = $this->web->data_submitted()) {
            $errors = array();

            foreach ($data as $name => $value) {
                if (preg_match("/^course-(\d+)$/", $name, $match)) {
                    $courseid = (int) $match[1];
                    if ($data['suffix']) {
                        if (!batch_course::change_suffix($courseid, $data['suffix'])) {
                            $errors[] = $courseid;
                        }
                    }
                    if ($data['visible'] == 'yes') {
                        batch_course::show_course($courseid);
                    } elseif ($data['visible'] == 'no') {
                        batch_course::hide_course($courseid);
                    }
                }
            }

            if ($errors) {
                $message = '<p>' . batch_string('config_courses_error') . '</p><ul>';
                foreach ($errors as $courseid) {
                    $message .= '<li>' . get_field('course', 'fullname', 'id', $courseid) . '</li>';
                }
                $message .= '</ul>';
            } else {
                $message = batch_string('config_courses_ok');
            }

            $this->web->print_header();
            print_simple_box($message);
            $url = $this->web->url('config_courses');
            print_continue($url->out());
            $this->web->print_footer();
            die;
        }

        include('config_courses.html');
    }
}
