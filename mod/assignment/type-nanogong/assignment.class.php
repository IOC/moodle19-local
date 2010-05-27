<?php

class assignment_nanogong extends assignment_base {

    function print_student_answer($userid, $return=true) {
        $output = '';

        if ($file_url = $this->file_url($userid)) {
            global $CFG;
            require_once($CFG->libdir.'/filelib.php');

            $file_name = $this->file_name($userid);
            $icon = mimeinfo('icon', $file_name);
            $output = '<img src="'.$CFG->pixpath.'/f/'.$icon.'" class="icon" alt="'.$icon.'" />'.
                '<a href="'.$file_url.'" >'.$file_name.'</a><br />';
            $output = '<div class="files">'.$output.'</div>';
        }

        if ($return) {
            return $output;
        }
        echo $output;
    }

    function assignment_nanogong($cmid='staticonly', $assignment=NULL, $cm=NULL, $course=NULL) {
        parent::assignment_base($cmid, $assignment, $cm, $course);
        $this->type = 'nanogong';
    }

    function view() {

        global $CFG, $USER;

        $context = get_context_instance(CONTEXT_MODULE,$this->cm->id);
        require_capability('mod/assignment:view', $context);

        add_to_log($this->course->id, "assignment", "view", "view.php?id={$this->cm->id}", $this->assignment->id, $this->cm->id);

        $this->view_header();

        $this->view_intro();

        $this->view_dates();

        $filecount = $this->count_user_files($USER->id);

        if ($submission = $this->get_submission()) {
            if ($submission->timemarked) {
                $this->view_feedback();
            }
        }

        if (has_capability('mod/assignment:submit', $context)  && $this->isopen() && (!$filecount || $this->assignment->resubmit || !$submission->timemarked)) {
            $this->view_upload_form($USER->id, $filecount > 0);
        } else {
            $file_url = false;
            if ($filecount > 0) {
                $file_url = $this->file_url($USER->id);
            }
            require_once($CFG->dirroot . '/local/lib/nanogong/lib.php');
            echo '<div style="text-align:center">';
            echo '<p>' . nanogong_applet('recorder', $file_url, false) . '</p>';
            echo '</div>';
        }

        $this->view_footer();
    }


    function view_upload_form($userid=0, $submited=false) {
        global $CFG;

        require_once($CFG->dirroot . '/local/lib/nanogong/lib.php');

        $struploadafile = get_string("uploadafile");

        $file_url = false;
        if ($userid and $submited) {
            $file_url = $this->file_url($userid);
        }

        $upload_url = $CFG->wwwroot . '/mod/assignment/upload.php?id=' . $this->cm->id;

        if ($file_url) {
            notify(get_string('submitted', 'assignment') . '.');
        } else {
            notify(get_string('notsubmittedyet', 'assignment') . '.');
        }

        echo '<div style="text-align:center">';
        echo '<form method="post" action="view.php?id='.$this->cm->id.'">';
        echo '<fieldset class="invisiblefieldset">';
        echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
        echo '<p>' . nanogong_applet('recorder', $file_url, true) . '</p>';
        echo '<input type="submit" name="save" value="'.get_string('savechanges').'"'
            . " onclick=\"return nanogong_submit('recorder','$upload_url')\" />";
        echo '</fieldset>';
        echo '</form>';
        echo '</div>';
    }


    function upload() {

        global $CFG, $USER;

        if (!has_capability('mod/assignment:submit', get_context_instance(CONTEXT_MODULE, $this->cm->id))) {
            die;
        }
        $filecount = $this->count_user_files($USER->id);
        $submission = $this->get_submission($USER->id);
        if ($this->isopen() && (!$filecount || $this->assignment->resubmit || !$submission->timemarked)) {

            $dir = $this->file_area_name($USER->id);

            require_once($CFG->dirroot.'/lib/uploadlib.php');
            if (isset($_FILES['newfile'])) {
                $_FILES['newfile']['name'] = 'file.wav';
            }
            $um = new upload_manager('newfile',true,false,$this->course,false);
            if ($um->process_file_uploads($dir)) {
                if ($submission) {
                    $submission->timemodified = time();
                    $submission->numfiles     = 1;
                    $submission->submissioncomment = addslashes($submission->submissioncomment);
                    unset($submission->data1);  // Don't need to update this.
                    unset($submission->data2);  // Don't need to update this.
                    if (update_record("assignment_submissions", $submission)) {
                        add_to_log($this->course->id, 'assignment', 'upload',
                                'view.php?a='.$this->assignment->id, $this->assignment->id, $this->cm->id);
                        $submission = $this->get_submission($USER->id);
                        $this->update_grade($submission);
                        $this->email_teachers($submission);
                        echo 'success';
                    }
                } else {
                    $newsubmission = $this->prepare_new_submission($USER->id);
                    $newsubmission->timemodified = time();
                    $newsubmission->numfiles = 1;
                    if (insert_record('assignment_submissions', $newsubmission)) {
                        add_to_log($this->course->id, 'assignment', 'upload',
                                'view.php?a='.$this->assignment->id, $this->assignment->id, $this->cm->id);
                        $submission = $this->get_submission($USER->id);
                        $this->update_grade($submission);
                        $this->email_teachers($newsubmission);
                        echo 'success';
                    }
                }
            }
        }
    }

    function setup_elements(&$mform) {
        global $CFG, $COURSE;

        $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));

        $mform->addElement('select', 'resubmit', get_string("allowresubmit", "assignment"), $ynoptions);
        $mform->setHelpButton('resubmit', array('resubmit', get_string('allowresubmit', 'assignment'), 'assignment'));
        $mform->setDefault('resubmit', 0);

        $mform->addElement('select', 'emailteachers', get_string("emailteachers", "assignment"), $ynoptions);
        $mform->setHelpButton('emailteachers', array('emailteachers', get_string('emailteachers', 'assignment'), 'assignment'));
        $mform->setDefault('emailteachers', 0);

    }

    function print_user_files($userid=0, $return=false) {
        global $CFG, $USER;

        if (!$userid) {
            if (!isloggedin()) {
                return '';
            }
            $userid = $USER->id;
        }

        $output = '';

        if ($file_url = $this->file_url($userid)) {
            require_once($CFG->dirroot . '/local/lib/nanogong/lib.php');
            $output = nanogong_applet('recorder', $file_url);
        }

        $output = '<div class="files">'.$output.'</div>';

        if ($return) {
            return $output;
        }
        echo $output;
    }

    function file_name($userid) {
        global $CFG;

        if ($filearea = $this->file_area_name($userid)) {
            $basedir = $CFG->dataroot . '/' . $filearea;
            if ($files = get_directory_list($basedir)) {
                if (in_array('file.wav', $files)) {
                    return 'file.wav';
                } elseif (in_array('file.spx', $files)) {
                    return 'file.spx';
                }
            }
        }
    }

    function file_url($userid) {
        if ($filename = $this->file_name($userid)) {
            global $CFG;
            require_once($CFG->libdir.'/filelib.php');

            $filearea = $this->file_area_name($userid);
            return get_file_url($filearea . '/' . $filename);
        }
    }

}
