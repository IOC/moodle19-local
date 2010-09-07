<?php

function xmldb_local_upgrade($oldversion) {
    global $CFG;

    $result = true;

    $xmldb_file = new XMLDBFile("{$CFG->dirroot}/local/db/install.xml");
    $xmldb_file->loadXMLStructure();
    $structure = $xmldb_file->getStructure();

    foreach ($structure->getTables() as $table) {
        if ($result and !table_exists($table)) {
            $result = create_table($table, false);
        }
    }

    $index = array('assignment' => 'grade',
                   'forum' => 'scale',
                   'glossary' => 'scale',
                   'journal' => 'assessed',
                   'questionnaire_quest_choice' => 'question_id',
                   'local_batch_job' => 'timecreated');
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
