<?php
    //This function executes all the restore procedure about this mod
    function choosegroup_restore_mods($mod,$restore) {

        global $CFG;

        $status = true;

        //Get record from backup_ids
        $data = backup_getid($restore->backup_unique_code,$mod->modtype,$mod->id);

        if ($data) {
            //Now get completed xmlized object
            $info = $data->info;
            // if necessary, write to restorelog and adjust date/time fields
            if ($restore->course_startdateoffset) {
                restore_log_date_changes('Choosegroups', $restore, $info['MOD']['#'], array('TIMEOPEN', 'TIMECLOSE'));
            }
            //traverse_xmlize($info);                                                                     //Debug
            //print_object ($GLOBALS['traverse_array']);                                                  //Debug
            //$GLOBALS['traverse_array']="";                                                              //Debug

            //Now, build the choosegroups record structure
            $choosegroup->course = $restore->course_id;
            $choosegroup->name = backup_todb($info['MOD']['#']['NAME']['0']['#']);
            $choosegroup->intro = backup_todb($info['MOD']['#']['INTRO']['0']['#']);
            $choosegroup->groups = backup_todb(choosegroup_groups_ids($choosegroup->course, $info['MOD']['#']['GROUPS']['0']['#']));
            $choosegroup->grouplimit = backup_todb($info['MOD']['#']['GROUPLIMIT']['0']['#']);
            $choosegroup->showmembers = backup_todb($info['MOD']['#']['SHOWMEMBERS']['0']['#']);
            $choosegroup->allowupdate = backup_todb($info['MOD']['#']['ALLOWUPDATE']['0']['#']);
            $choosegroup->timeopen = backup_todb($info['MOD']['#']['TIMEOPEN']['0']['#']);
            $choosegroup->timeclose = backup_todb($info['MOD']['#']['TIMECLOSE']['0']['#']);
            $choosegroup->timecreated = backup_todb($info['MOD']['#']['TIMECREATED']['0']['#']);
            $choosegroup->timemodified = backup_todb($info['MOD']['#']['TIMEMODIFIED']['0']['#']);

            //The structure is equal to the db, so insert the choosegroup
            $newid = insert_record ("choosegroup",$choosegroup);
            
            if ($newid) {
                //We have the newid, update backup_ids
                backup_putid($restore->backup_unique_code,$mod->modtype,
                             $mod->id, $newid);
            } else {
                $status = false;
            }

            //Do some output     
            if (!defined('RESTORE_SILENTLY')) {
                echo "<li>".get_string("modulename","choosegroup")." \"".format_string(stripslashes($choosegroup->name),true)."\"</li>";
            }
            backup_flush(300);

        } else {
            $status = false;
        }
        return $status;
    }
    
    
    function choosegroup_groups_ids($course, $groups) {
		$ids = '';
    	if (!empty($groups)){
    		$groups = explode(',', $groups);
    		foreach ($groups as $group){
    			if (record_exists('groups', 'courseid', $course, 'name', $group)) {
    				$record = get_field('groups','id','courseid', $course, 'name', $group);
	    			if (empty($ids)) {
	    					$ids = $record;
	    			} else {
	    				$ids .= ',' . $record;
	    			}
    			}
    		}
    	}
    	return $ids;
    }