<?php

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

class choosegroup_view {

    var $choosegroup;
    var $cm;
    var $course;
    var $url;

    function __construct() {
        global $CFG, $USER;

        $id = optional_param('id', 0, PARAM_INT); // course_module ID

        if ($id) {
            if (!$this->cm = get_coursemodule_from_id('choosegroup', $id)) {
                error('Course Module ID was incorrect');
            }

            if (!$this->course = get_record('course', 'id', $this->cm->course)) {
                error('Course is misconfigured');
            }

            if (!$record = get_record('choosegroup', 'id',
                                      $this->cm->instance)) {
                error('Course module is incorrect');
            }

            $this->choosegroup = new choosegroup($record, $this->cm, $USER->id);
        } else {
            error('You must specify a course_module ID');
        }

        require_course_login($this->course, true, $this->cm);

        $this->url = new moodle_url(null, array('id' => $id));

        if ($data = $this->data_submitted()) {
            $this->post($data);
        } else {
            $this->get();
        }
    }


    function data_submitted() {
        $data = data_submitted();
        if ($data and !confirm_sesskey()) {
            print_error('confirmsesskeybad', 'error', $this->url->out());            
        }
        return $data;
    }

    function get() {
        $this->print_header();
        $this->print_intro();
        $this->print_dates();

        if ($this->choosegroup->can_choose()) {
            $this->print_main();
        }

        $this->print_footer();
        $this->log_view();
    }

    function log_choose($groupid) {
        add_to_log($this->course->id, 'choosegroup', 'choose',
                   "view.php?id={$this->cm->id}", "$groupid",
                   $this->cm->id, $this->choosegroup->userid);
    }

    function log_view() {
        add_to_log($this->course->id, 'choosegroup', 'view',
                   "view.php?id={$this->cm->id}", '',
                   $this->cm->id, $this->choosegroup->userid);
    }

    function post($data) {
        $groupid = required_param('group', PARAM_INT);

        if ($this->choosegroup->can_choose()
            and $this->choosegroup->is_open()
            and !$this->choosegroup->chosen()) {
            $this->choosegroup->choose($groupid);
        }

        $this->log_choose($groupid);

        redirect($this->url->out());
    }

    function print_dates() {
        if (!$this->choosegroup->timeopen && !$this->choosegroup->timeclose) {
            return;
        }

        print_simple_box_start('center', '', '', 0, 'generalbox', 'dates');
        echo '<table>';
        if ($this->choosegroup->timeopen) {
            echo '<tr><td class="c0">'.get_string('timeopen','choosegroup').':</td>';
            echo '    <td class="c1">'.userdate($this->choosegroup->timeopen).'</td></tr>';
        }
        if ($this->choosegroup->timeclose) {
            echo '<tr><td class="c0">'.get_string('timeclose','choosegroup').':</td>';
            echo '    <td class="c1">'.userdate($this->choosegroup->timeclose).'</td></tr>';
        }
        echo '</table>';
        print_simple_box_end();
    }

    function print_footer() {
        print_footer($this->course);
    }

    function print_form($groups, $message) {
        echo '<p>' . get_string($message, 'choosegroup') . ':</p>';
        echo '<form method="post" action="' . s($this->url->out()) . '">'
            . '<input type="hidden" name="sesskey" value="'
            . sesskey() . '"/><ul>';
        foreach ($groups as $group) {
            $vacancies = '';
            $disabled = '';
            $dimmed = '';
            if ($this->choosegroup->grouplimit) {
                $vacancies = '(' .  get_string('vacancies', 'choosegroup',
                                               $group->vacancies) . ')';
                if (!$group->vacancies) {
                    $disabled = 'disabled="disabled"';
                    $dimmed = 'class="dimmed"';
                }
            }

            $checkbox = "<input $disabled type=\"radio\" name=\"group\" "
                . "id=\"group-{$group->id}\" value=\"{$group->id}\" />";
            $label = "<label $dimmed for=\"group-{$group->id}\">"
                . s($group->name) . " $vacancies</label>";
            echo "<li>$checkbox $label</li>";
        }
        echo '</ul><input type="submit" value="' . get_string('submit') . '"/>'
            .'</form>';
    }

    function print_header() {
        $strchoosegroups = get_string('modulenameplural', 'choosegroup');
        $strchoosegroup  = get_string('modulename', 'choosegroup');

        $navlinks = array();
        $navlinks[] = array('name' => $strchoosegroups,
                            'link' => 'index.php?id=' . $this->course->id,
                            'type' => 'activity');
        $navlinks[] = array('name' => format_string($this->choosegroup->name),
                            'link' => '',
                            'type' => 'activityinstance');

        $navigation = build_navigation($navlinks);

        print_header_simple(format_string($this->choosegroup->name), '',
                            $navigation, '', '', true,
                            update_module_button($this->cm->id,
                                                 $this->course->id,
                                                 $strchoosegroup),
                            navmenu($this->course, $this->cm));
    }

    function print_intro() {
        print_box_start('generalbox', 'intro');
        echo format_text( $this->choosegroup->intro, FORMAT_HTML);
        print_box_end();
    }

    function print_main() {
        print_box_start('generalbox boxaligncenter main');

        if ($this->choosegroup->chosen()) {
            if ($this->choosegroup->type == choosegroup::TYPE_GROUP) {
                print_string('groupchosen', 'choosegroup',
                             $this->choosegroup->usergroup->name);
            } elseif ($this->choosegroup->type == choosegroup::TYPE_SUBGROUP) {
                print_string('subgroupchosen', 'choosegroup',
                             $this->choosegroup->usersubgroup->name);
            }
        } else {
            if ($this->choosegroup->is_open()) {
                if ($this->choosegroup->type == choosegroup::TYPE_GROUP) {
                    $this->print_form($this->choosegroup->groups,
                                      'chooseagroup');
                } elseif ($this->choosegroup->type
                          == choosegroup::TYPE_SUBGROUP) {
                    if ($this->choosegroup->usergroup) {
                        if ($this->choosegroup->subgroups) {
                            $this->print_form($this->choosegroup->subgroups,
                                              'chooseasubgroup');
                        } else {
                            print_string('nosubgroups', 'choosegroup');
                        }
                    } else {
                        print_string('notinagroup', 'choosegroup');
                    }
                }
            } else {
                print_string('activityclosed', 'choosegroup');
            }
        }

        print_box_end();
    }
}

new choosegroup_view;
