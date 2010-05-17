<?php

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/choosegroup/lib.php');

class mod_choosegroup_mod_form extends moodleform_mod {

    function definition() {
        global $COURSE;
        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name', 'choosegroup'),
                           array('size'=>'64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255),
                        'maxlength', 255, 'client');

        $mform->addElement('htmleditor', 'intro',
                           get_string('intro', 'choosegroup'));
        $mform->setType('intro', PARAM_RAW);
        $mform->addRule('intro', get_string('required'),
                        'required', null, 'client');
        $mform->setHelpButton('intro', array('writing', 'richtext'),
                              false, 'editorhelpbutton');

        $options = array(
            choosegroup::TYPE_GROUP => get_string('typegroup', 'choosegroup'),
            choosegroup::TYPE_SUBGROUP => get_string('typesubgroup', 'choosegroup'),
        );

        $mform->addElement('select', 'type',
                           get_string('type', 'choosegroup'), $options);

        $mform->addElement('text', 'grouplimit',
                           get_string('grouplimit', 'choosegroup'),
                           array('size' => 4));
        $mform->setType('grouplimit', PARAM_INT);

        $mform->addElement('date_time_selector', 'timeopen',
                           get_string('timeopen', 'choosegroup'));

        $mform->addElement('date_time_selector', 'timeclose',
                           get_string('timeclose', 'choosegroup'));

        $groups = choosegroup_detected_groups($COURSE->id);
        $mform->addElement('static', 'detected',
                           get_string('detectedgroups', 'choosegroup'),
                           implode('<br/>', $groups));
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();

    }
}
