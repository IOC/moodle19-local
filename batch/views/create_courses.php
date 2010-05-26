<?php

class batch_view_create_courses extends batch_view_base {

    var $backup;
    var $courses;
    var $lastindex;
    var $startyear;
    var $startmonth;
    var $startday;

    function get_data() {
        if (!$data = $this->web->data_submitted()) {
            return false;
        }

        $this->backup = $data['backup'];
        $this->lastindex = (int) $data['lastindex'];

        $this->courses = array();
        for ($i = 0; $i <= $this->lastindex; $i++) {
            if (isset($data["shortname-$i"])) {
                $shortname = stripslashes($data["shortname-$i"]);
                $fullname = stripslashes($data["fullname-$i"]);
                $category = (int) $data["category-$i"];
                if ($shortname and $fullname and $category) {
                    $this->courses[$i] = (object)  array(
                        'shortname' => $shortname,
                        'fullname' => $fullname,
                        'category' => $category,
                    );
                }
            }
        }

        if ($csvfile = $_FILES['csvfile']['tmp_name']) {
            if ($content = trim(file_get_contents($csvfile))) {
                foreach (explode("\n", $content) as $line) {
                    $fields = explode(",", $line);
                    if (count($fields) == 3) {
                        $this->lastindex++;
                        $this->courses[$this->lastindex] = (object)  array(
                            'shortname' => trim($fields[0]),
                            'fullname' => trim($fields[1]),
                            'category' => trim($fields[2]),
                        );
                    }
                }
            }
        }

        if (preg_match("/([0-9]+)\/([0-9]+)\/([0-9]+)/",
                           $data['startdate'], $match)) {
            $this->startday = (int) $match[1];
            $this->startmonth = (int) $match[2];
            $this->startyear = (int) $match[3];
        } else {
            return false;
        }

        return ($this->backup and $this->courses and !$csvfile);
    }

    function view() {
        global $CFG;

        $this->backup = '';
        $this->lastindex = -1;
        $this->courses = array();
        $date = getdate();
        $this->startyear = $date['year'];
        $this->startmonth = $date['mon'];
        $this->startday = $date['mday'];

        if ($this->get_data()) {
            foreach ($this->courses as $params) {
                $params->startday = $this->startday;
                $params->startmonth = $this->startmonth;
                $params->startyear = $this->startyear;
                $params->backup = $this->backup;
                batch_queue::add_job('create_course', (object) $params);
            }
            $this->web->redirect();
        }

        if ($this->lastindex < 0) {
            $this->lastindex = 0;
            $this->courses[0] = (object) array(
                'shortname' => '',
                'fullname' => '',
                'category' => 0
            );
        }

        include('create_courses.html');
    }

}

    