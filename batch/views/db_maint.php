<?php

class batch_view_db_maint extends batch_view_base {

    function view() {
        $actions = array('fix_quizzes',
                         'clean_groups',
                         'optimize_tables');

        if ($data = $this->web->data_submitted()) {
            if (in_array($data['action'], $actions))  {
                batch_queue::add_job($data['action']);
            }
            $this->web->redirect();
        }

        $options = array();
        foreach ($actions as $action) {
            $options[$action] = batch_string("type_$action");
        }

        include('db_maint.html');
    }

}
