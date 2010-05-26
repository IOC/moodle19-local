<?php

function xmldb_local_upgrade($oldversion) {
    global $CFG;

    $result = true;

    $xmldb_file = new XMLDBFile("{$CFG->dirroot}/local/db/install.xml");
    $xmldb_file->loadXMLStructure();
    $structure = $xmldb_file->getStructure();

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

    $table = new XMLDBTable('batch_job');
    if ($result and table_exists($table)) {
        $result = rename_table($table, 'local_batch_job', false);
    }

    $table = $structure->getTable('local_batch_job');
    if ($result and !table_exists($table)) {
        $result = create_table($table, false);
    }

    return $result;
}
