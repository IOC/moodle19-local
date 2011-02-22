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

    return $result;
}
