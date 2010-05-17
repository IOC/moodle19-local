<?php

class choosegroup {

    const TYPE_GROUP = 0;
    const TYPE_SUBGROUP = 1;
    const PATTERN_GROUP = '/^([A-Z]):/';
    const PATTERN_SUBGROUP = '/^([A-Z])[1-9]:/';

    function __construct($record, $cm, $userid) {
        $this->cm = $cm;
        $this->id = $record->id;
        $this->courseid = $record->course;
        $this->name = $record->name;
        $this->intro = $record->intro;
        $this->type = $record->type;
        $this->grouplimit = $record->grouplimit;
        $this->timeopen = $record->timeopen;
        $this->timeclose = $record->timeclose;
        $this->userid = $userid;
        $this->groups = $this->_groups();
        $this->usergroup = $this->_user_group();
        $this->subgroups = $this->_subgroups();
        $this->usersubgroup = $this->_user_subgroup();
    }

    function _groups() {
        $records = get_records('groups', 'courseid', $this->courseid, 'name');
        if (!$records) {
            return false;
        }

        $groups = array();
        foreach ($records as $record) {
            if (preg_match(self::PATTERN_GROUP, $record->name, &$matches)) {
                $record->letter = $matches[1];
                $record->members = count_records('groups_members',
                                                 'groupid', $record->id);
                $record->vacancies = max(0, $this->grouplimit
                                         - $record->members);
                $groups[$record->id] = $record;
            }
        }
        return $groups;
    }

    function _subgroups() {
        if (!$this->usergroup) {
            return false;
        }

        $records = get_records('groups', 'courseid', $this->courseid, 'name');
        if (!$records) {
            return false;
        }

        $subgroups = array();
        foreach ($records as $record) {
            if (preg_match(self::PATTERN_SUBGROUP, $record->name, &$matches)) {
                if ($matches[1] == $this->usergroup->letter) {
                    $subgroups[$record->id] = $record;
                    $record->members = count_records('groups_members',
                                                     'groupid', $record->id);
                    $record->vacancies = max(0, $this->grouplimit
                                             - $record->members);
                }
            }
        }
        return $subgroups;    
    }

    function _user_group() {
        if (!$this->groups) {
            return false;
        }

        foreach ($this->groups as $group) {
            if (record_exists('groups_members', 'groupid', $group->id,
                              'userid', $this->userid)) {
                return $group;
            }
        }
        return false;
    }

    function _user_subgroup() {
        if (!$this->subgroups) {
            return false;
        }

        foreach ($this->subgroups as $subgroup) {
            if (record_exists('groups_members', 'groupid', $subgroup->id,
                              'userid', $this->userid)) {
                return $subgroup;
            }
        }

        return false;
    }

    function can_choose() {
        $context = get_context_instance(CONTEXT_MODULE, $this->cm->id);
        return has_capability('mod/choosegroup:choose', $context, null, false);
    }

    function chosen() {
        return ($this->type == self::TYPE_GROUP and $this->usergroup)
            or ($this->type == self::TYPE_SUBGROUP and $this->usersubgroup);
    }

    function choose($groupid) {
        $groups = false;        
        if ($this->type == self::TYPE_GROUP and !$this->usergroup) {
            $groups = $this->groups;
        } elseif ($this->type == self::TYPE_SUBGROUP
                  and !$this->usersubgroup) {
            $groups = $this->subgroups;
        }

        if (!$groups or !isset($groups[$groupid])) {
            return;
        }

        if ($this->grouplimit and
            $groups[$groupid]->members >= $this->grouplimit) {
            return;
        }

        $record = (object) array('groupid' => $groupid,
                                 'userid' => $this->userid,
                                 'timeadded' => time());
        insert_record('groups_members', $record);
    }

    function is_open() {
        return (!$this->timeopen or $this->timeopen <= time())
            and (!$this->timeclose or $this->timeclose > time());
    }

}

function choosegroup_add_instance($record) {
    $record->grouplimit = max(0, min(9999, $record->grouplimit));
    $record->timecreated = time();
    $record->timemodified = time();

    return insert_record('choosegroup', $record);
}

function choosegroup_delete_instance($id) {
    return delete_records('choosegroup', 'id', $id);
}

function choosegroup_detected_groups($courseid) {
    $records = get_records('groups', 'courseid', $courseid, 'name');
    if (!$records) {
        return array();
    }

    $groups = array();
    foreach ($records as $record) {
        if (preg_match(choosegroup::PATTERN_GROUP,
                       $record->name, &$matches)
            or preg_match(choosegroup::PATTERN_SUBGROUP,
                          $record->name, &$matches)) {
            $groups[] = $record->name;
        }
    }
    return $groups;
}

function choosegroup_update_instance($record) {
    $record->id = $record->instance;
    $record->grouplimit = max(0, min(9999, $record->grouplimit));
    $record->timemodified = time();

    return update_record('choosegroup', $record);
}

