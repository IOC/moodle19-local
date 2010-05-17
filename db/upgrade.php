<?php

function xmldb_local_upgrade($oldversion) {
    global $CFG;

    $result = true;

    $index = array('assignment' => 'grade',
                   'forum' => 'scale',
                   'glossary' => 'scale',
                   'journal' => 'assessed',
                   'questionnaire_quest_choice' => 'question_id');
    foreach ($index as $table => $field) {
        $table = new XMLDBTable($table);
        $index = new XMLDBIndex($field);
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array($field));
        if ($result && !index_exists($table, $index)) {
            $result = add_index($table, $index, false);
        }
    }

    return $result;
}
