<?php

class batch_view_restart_courses extends batch_view_base {

    function view() {
        if ($data = $this->web->data_submitted()) {
            if (preg_match("/([0-9]{1,2})\/([0-9]{1,2})\/([0-9]{4})/", $data['startdate'], $match)) {
                $startday = (int) $match[1];
                $startmonth = (int) $match[2];
                $startyear = (int) $match[3];
            }

            $category = (int) $data['category'];
            $roleassignments = $data['roleassignments'];
            $delete_groups = !empty($data['delete_groups']);

            if ($match and checkdate($startmonth, $startday, $startyear)) {
                foreach ($data as $name => $value) {
                    if (preg_match("/^course-/", $name)) {
                        $params = array(
                            'shortname' => stripslashes($value),
                            'startyear' => $startyear,
                            'startmonth' => $startmonth,
                            'startday' => $startday,
                            'category' => $category,
                            'roleassignments' =>  $roleassignments,
                            'delete_groups' => $delete_groups,
                        );
                        batch_queue::add_job('restart_course', (object) $params);
                    }
                }
            }
            $this->web->redirect();
        }   

        $startdate = strftime('%d/%m/%Y');

        include('restart_courses.html');
    }
}
