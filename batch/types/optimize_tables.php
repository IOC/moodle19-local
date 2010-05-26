<?php

class batch_type_optimize_tables extends batch_type_base {

    function can_start($params) {
        $hour = (int) strftime("%H", time());
        return  $hour == 3;
    }

    function execute($params) {
        global $CFG;

        $records = get_records_sql('SHOW TABLES');
        foreach (array_keys($records) as $table) {
            if ($table != "{$CFG->prefix}log") {
                execute_sql("OPTIMIZE TABLE $table", false);
            }
        }
    }
}
