<?php

class batch_view_job_queue extends batch_view_base {

     function _print_filter_select($filter) {
        $options = array(
            batch_queue::FILTER_ALL => batch_string('filter_all'),
            batch_queue::FILTER_PENDING => batch_string('filter_pending'),
            batch_queue::FILTER_FINISHED => batch_string('filter_finished'),
            batch_queue::FILTER_ERRORS => batch_string('filter_errors'),
        );
        choose_from_menu($options, 'filter', $filter, '');
    }

    function _print_table($jobs, $count, $page, $perpage, $filter) {
        global $CFG;
        $table = new flexible_table('queue_table');
        $columns = array('timecreated' => batch_string('column_timecreated'),
                         'type' => batch_string('column_type'),
                         'params' => batch_string('column_params'),
                         'timestarted' => batch_string('column_timestarted'),
                         'timeended' => batch_string('column_timeended'),
                         'error' => batch_string('column_error'),
                         'actions' => '');
        $table->define_columns(array_keys($columns));
        $table->define_headers(array_values($columns));
        $table->set_attribute('id', 'queue-table');
        $table->set_attribute('class', 'generaltable');
        $table->setup();

        foreach ($jobs as $job) {
            $strtype = batch_string('type_' . $job->type);
            $type = batch_type($job->type);
            $strparams = $type->params_info($job->params);
        
            $timecreated = $this->web->strtime($job->timecreated);
            $timestarted = $this->web->strtime($job->timestarted);
            $timeended = $this->web->strtime($job->timeended);

            $url = $this->web->url(false, array('cancel_job' => $job->id));
            $actions = '';
            if ($job->timestarted == 0) {
                $actions .= '<a title="' . batch_string('cancel')
                    . '" href="' . $url->out_action() . '">'
                    . '<img src="' . $CFG->pixpath . '/t/delete.gif" '
                    .'class="iconsmall edit" alt="'
                    . batch_string('cancel') . '" /></a>';
            }

            $table->add_data(array($timecreated, $strtype, $strparams,
                                   $timestarted, $timeended, $job->error,
                                   $actions));
        }

        $table->print_html();
        $url = $this->web->url('job_queue', array('filter' => $filter));
        print_paging_bar($count, $page, $perpage, $url);
    }

    function view() {
        $cancel_job = optional_param('cancel_job', 0, PARAM_INT);
        $filter = optional_param('filter', batch_queue::FILTER_ALL,
                                 PARAM_INT);
        $page = optional_param('page', 0, PARAM_INT);
        $perpage = 10;
        $count = batch_queue::count_jobs($filter);
        $jobs = batch_queue::get_jobs($filter, $page * $perpage, $perpage);

        if ($cancel_job) {
            $this->web->require_sesskey();
            batch_queue::cancel_job($cancel_job);
            $this->web->redirect();
        }

        $this->web->print_header(true);
        echo '<form id="queue-filter" action="'
            . $this->web->url('job_queue')->out() . '">';
        $this->_print_filter_select($filter);
        echo '<input type="submit" value="Mostra"/></form>';

        $this->_print_table($jobs, $count, $page, $perpage, $filter);

        $this->web->print_footer();
    }

}
