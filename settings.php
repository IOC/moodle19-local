<?php

if ($hassiteconfig) {
    $ADMIN->add('root',
                new admin_category('local', get_string('local', 'local')));

    $settings = new admin_settingpage('local_settings',
                                      get_string('settings', 'local'));

    $settings->add(
        new admin_setting_configfile('local_errorlog_path',
                                     get_string('errorlog_path', 'local'),
                                     '', '')
    );

    $ADMIN->add('local', $settings);

    $ADMIN->add('local',
                new admin_externalpage('local_errorlog',
                                       get_string('errorlog', 'local'),
                                       "{$CFG->wwwroot}/local/errorlog/",
                                       'moodle/site:config'));
}
