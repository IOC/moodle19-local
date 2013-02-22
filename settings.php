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
        new admin_setting_configcheckbox('local_testing_mode',
                                         get_string('testing_mode', 'local'),
                                         '', '0')
    );

    $settings->add(
        new admin_setting_configcheckbox('local_passwordpolicy',
                                         get_string('passwordpolicy', 'local'),
                                         '', '0')
    );

    $settings->add(
        new admin_setting_configtext('local_myremotehost',
                                       get_string('myremotehost', 'local'),
                                       '', '', PARAM_URL, 50)
    );

    $settings->add(
        new admin_setting_configtextarea('local_myremote_message',
                                        get_string('myremotemessage', 'local'), '', '')
    );

    $settings->add(
        new admin_setting_configtext('local_redirect_url',
                                       get_string('redirect_url', 'local'),
                                       '', '', PARAM_URL, 50)
    );

    $settings->add(
        new admin_setting_configtextarea('local_redirect_courses',
                                        get_string('redirect_courses', 'local'), '', '')
    );

    $settings->add(
        new admin_setting_configselect('local_batch_start_hour',
                                       get_string('batch_start_hour', 'local'), '',
                                       '0', range(0, 23))
    );

    $settings->add(
        new admin_setting_configselect('local_batch_stop_hour',
                                       get_string('batch_stop_hour', 'local'), '',
                                       '0', range(0, 23))
    );

    $auth_plugins = get_enabled_auth_plugins(true);
    $options = array_combine($auth_plugins, $auth_plugins);
    $settings->add(
        new admin_setting_configselect('local_secretaria_auth',
                                       get_string('secretaria_auth_plugin', 'local'), '',
                                       'manual', $options)
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
