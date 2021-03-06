<?php

class batch_type_export_course extends batch_type_base {

    function execute($params) {
        global $CFG;

        if (!$course = get_record('course', 'shortname',
                                  addslashes($params->shortname))) {
            throw new Exception('nonexistent');
        }

        $materials = false;
        if ($params->materials and !empty($CFG->local_materials_dir)) {
            $records = get_records('local_materials', 'course', $course->id);
            if ($records and count($records) == 1) {
                $record = reset($records);
                $select = "path = '" . addslashes($record->path) . "' AND course != $course->id";
                if ($id = get_field('course', 'id', 'shortname', $course->shortname . '~')) {
                    $select .= " AND course != $id";
                }
                if (!record_exists_select('local_materials', $select)
                    and is_dir($CFG->local_materials_dir.'/'.$record->path)) {
                    $materials = $record->path;
                }
            }
        }

        if (!empty($CFG->local_batch_export_dir)) {
            $backupdir = $CFG->local_batch_export_dir;
        } else {
            $backupdir = SITEID . '/backupdata';
        }

        batch_course::backup_course($course, $backupdir, true, $materials);
    }

    function params_info($params) {
        $materials = get_string($params->materials ? 'yes' : 'no');
        return (get_string('course') . ": {$params->shortname}<br/>" .
                batch_string('materials') . ": $materials");
    }
}
