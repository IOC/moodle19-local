<?php

class resource_materialweb extends resource_base {

    function resource_naterialweb($cmid=0) {
        parent::resource_base($cmid);
    }

    function add_instance($resource) {
        return parent::add_instance($resource);
    }

    function update_instance($resource) {
        return parent::update_instance($resource);
    }

    function display() {
        global $CFG;

        $cm = $this->cm;
        $course = $this->course;
        $resource = $this->resource;

        $url = $resource->reference;
        if (!preg_match('/https?:\/\//', $url)) {
            $url = "{$CFG->wwwroot}/file.php/{$course->id}/$url";
        }

        add_to_log($course->id, "resource", "view", "view.php?id={$cm->id}",
                   $resource->id, $cm->id);

        require_js($CFG->wwwroot . '/local/lib/jquery/jquery-1.4.2.min.js');
        require_js($CFG->wwwroot . '/mod/resource/type/materialweb/client.js');

        parent::display();

        $pagetitle = strip_tags($course->shortname . ': '
                                . format_string($resource->name));
        $navigation = build_navigation($this->navlinks, $cm);

        print_header($pagetitle, $course->fullname, $navigation, "", "", true,
                     update_module_button($cm->id, $course->id,
                                          $this->strresource),
                     navmenu($course, $cm));

        echo '<object id="material" name="material" type="text/html"'
            .  ' data="' . s($url) . '" width="100%" height="500">'
            . '<a href="' . s($url) . '">' . $pagetitle .'</a>'
            . '</object>';

        print_footer($course);
    }

    function setup_preprocessing(&$defaults){
    }

    function setup_elements(&$mform) {
        global $CFG;

        $mform->addElement('choosecoursefile', 'reference',
                           get_string('location'), null,
                           array('maxlength' => 255, 'size' => 48));
        $mform->setDefault('reference', $CFG->resource_defaulturl);
        $mform->addRule('name', null, 'required', null, 'client');
    }

}
