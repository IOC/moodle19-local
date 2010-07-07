<?php

require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/local/batch/views/base.php');

class batch_web {

    var $views;
    var $current_view;

    function __construct($views, $current_view) {
        global $CFG;

        $this->views = $views;
        $this->current_view = $current_view;
        if (!in_array($this->current_view, $this->views)) {
            $this->current_view = $this->views[0];
        }

        require_login();
        require_capability('moodle/site:doanything',
                           get_context_instance(CONTEXT_SYSTEM, SITEID));

        $file = "{$CFG->dirroot}/local/batch/views/{$this->current_view}.php";
        $class = "batch_view_{$this->current_view}";
        require_once($file);
        new $class($this);
    }

    function data_submitted() {
        if ($data = data_submitted()) {
            if (!confirm_sesskey()) {
                print_error('invalidsesskey');
            }
            return (array) $data;
        }
    }
    
    function print_category_menu($name="category", $value=0) {
        $records = get_records('course_categories', '', '');
        $categories = array();
        foreach ($records as $id => $record) {
            $categories[$id] = $record->name;
            $parent = $record->parent;
            while ($parent) {
                $categories[$id] = $records[$parent]->name
                    . ' / ' . $categories[$id];
                $parent = $records[$parent]->parent;
            }
        }
        asort($categories);
        echo '<select name="'. $name . '" id="' . $name . '">';
        echo '<option value="0">&nbsp;</option>';
        foreach ($categories as $id => $name) {
            echo '<option value="' . $id . '"'
                . ($id == $value ? ' selected="selected"' : '')
                . '>' . $name . '</option>';
        }
        echo '</select>';
    }

    function print_courses_tree() {
        $this->_print_tree($this->_get_categories(), $this->_get_courses(), 0);
    }

    function print_footer() {
        admin_externalpage_print_footer();
    }

    function print_header($include_javascript=false) {
        global $CFG;

        admin_externalpage_setup('local_batch');

        require_js("{$CFG->wwwroot}/local/lib/jquery/jquery-1.4.2.min.js");
        require_js("{$CFG->wwwroot}/local/lib/jquery/jquery-ui-1.8.1.custom.min.js");
        require_js($this->wwwroot() . '/web.js');
        if ($include_javascript) {
            require_js($this->wwwroot() . "/views/{$this->current_view}.js" );
        }

        $CFG->stylesheets[] = "{$CFG->wwwroot}//local/lib/jquery/jquery-ui-1.8.1.custom.css";
        $CFG->stylesheets[] = "{$CFG->wwwroot}/local/batch/styles.css";

        admin_externalpage_print_header();

        $tabrow = array();
        foreach ($this->views as $view) {
            $url = $this->url($view);
            $tabrow[] = new tabobject($view, $url->out(),
                                      batch_string("view_$view"));
        }
        print_tabs(array($tabrow), $this->current_view);
    }

    function redirect($view=false, $params=array()) {
        $url = $this->url($view, $params);
        redirect($url->out());
    }

    function require_sesskey() {
        if (!confirm_sesskey()) {
            print_error('invalidsesskey');
        }
    }

    function strtime($time) {
        return $time ? strftime("%e %B, %R", $time) : '';
    }

    function url($view=false, $params=array()) {
        if ($view) {
            $params['view'] = $view;
        }
        return new moodle_url($this->wwwroot() . '/', $params);
    }

    function view() {
    }

    function wwwroot() {
        global $CFG;
        return $CFG->wwwroot . '/local/batch';
    }

    function _get_categories() {
        $records = get_records('course_categories', '', '', 'name');
        $categories = array();
        foreach ($records as $record) {
            $categories[$record->parent][] = $record;
        }
        return $categories;
    }

    function _get_courses() {
        $records = get_records('course', '', '', 'shortname');
        $courses = array();
        foreach ($records as $record) {
            if ($record->id != SITEID) {
                $courses[$record->category][] = $record;
            }
        }
        return $courses;
    }

    function _print_tree($categories, $courses, $categoryid) {
        global $CFG;

        $cat_categories = isset($categories[$categoryid]) ?
            $categories[$categoryid] : array();
        $cat_courses = isset($courses[$categoryid]) ?
            $courses[$categoryid] : array();

        if (!$cat_categories and !$cat_courses) {
            return;
        }

        $img = '<img src="' . $CFG->pixpath . '/t/switch_plus.gif" /> '
            . '<img class="hidden" src="' . $CFG->pixpath
            . '/t/switch_minus.gif" /> ';
        echo '<ul>';
        foreach ($cat_categories as $category) {
            echo '<li class="category"><span class="title">'
                . $img . $category->name . '</span>';
            $this->_print_tree($categories, $courses, $category->id);
            echo '</li>';
        }
        foreach ($cat_courses as $course) {
            echo '<li class="course"><span><input type="checkbox"'
                . ' id="course-' . $course->id . '"'
                . ' name="course-' . $course->id . '"'
                . ' value="' . $course->shortname . '" />'
                . ' <label for="course-' . $course->id .'">'
                . $course->fullname . ' (' .  strftime('%x', $course->startdate)
                . ')</label></span></li>';
        }
        echo '</ul>';
    }

}

