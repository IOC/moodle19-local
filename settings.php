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

    $where = 'id != '.$CFG->mnet_localhost_id
        . ' AND  id != '.$CFG->mnet_all_hosts_id
        . ' AND deleted = 0';
    $hosts = get_records_select_menu('mnet_host', $where, '', 'id, name');
    $hosts[0] = '';
    asort($hosts);
    $settings->add(
        new admin_setting_configselect('local_myremotehost',
                                       get_string('myremotehost', 'local'),
                                       '', '0', $hosts)
    );

    $settings->add(
        new admin_setting_configtextarea('local_myremote_message',
                                        get_string('myremotemessage', 'local'), '', '')
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

    $ADMIN->add('local', $settings);

    $pages = array('batch', 'errorlog', 'profiling');
    foreach ($pages as $page) {
        $ADMIN->add('local',
                    new admin_externalpage("local_$page",
                                           get_string($page, 'local'),
                                           "{$CFG->wwwroot}/local/$page/"));
    }
}
