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

    $settings->add(
        new admin_setting_configtext('local_materials_url',
                                     get_string('materials_url', 'local'),
                                     '', '', PARAM_URL)
    );

    $settings->add(
        new admin_setting_configtext('local_materials_secret_url',
                                     get_string('materials_secret_url', 'local'),
                                     '', '', PARAM_URL)
    );

    $settings->add(
        new admin_setting_configtext('local_materials_secret_token',
                                     get_string('materials_secret_token', 'local'),
                                     '', '')
    );

    $settings->add(
        new admin_setting_configcheckbox('local_profiling_enable',
                                         get_string('profiling_enable', 'local'),
                                         '', '0')
    );

    $settings->add(
        new admin_setting_configcheckbox('local_testing_mode',
                                         get_string('testing_mode', 'local'),
                                         '', '0')
    );

    $ADMIN->add('local', $settings);

    $pages = array('batch', 'errorlog', 'profiling');
    foreach ($pages as $page) {
        $ADMIN->add('local',
                    new admin_externalpage("local_$page",
                                           get_string($page, 'local'),
                                           "{$CFG->wwwroot}/local/$page/"));
    }
}
