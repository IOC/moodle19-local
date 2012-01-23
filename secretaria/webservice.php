<?php

global $CFG;

require_once('../../config.php');
require_once($CFG->dirroot . '/local/secretaria/lib.php');

function local_secretaria_error($msg) {
    echo json_encode(array('error' => $msg, 'result' => null));
    die;
}

@ini_set('display_errors', '0');
@ini_set('log_errors', '1');

if (!(isset($_SERVER['HTTPS']) or
      (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) and
       $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'))) {
    local_secretaria_error('https');
}

if (empty($CFG->local_webservice_password)) {
    local_secretaria_error('config');
}

if (empty($_POST['data'])) {
    local_secretaria_error('data');
}

$data = json_decode(stripslashes($_POST['data']), true);

if (!$data) {
    local_secretaria_error('json');
}

if (empty($data['token']) or $data['token'] !== $CFG->local_webservice_password) {
    local_secretaria_error('token');
}

$service = new local_secretaria_service();

if (empty($data['func']) or !is_callable(array($service, $data['func']))) {
    local_secretaria_error('func');
}

if (!isset($data['params']) or !is_array($data['params'])) {
    local_secretaria_error('params');
}

$result = call_user_func_array(array($service, $data['func']), $data['params']);

echo json_encode(array('result' => $result, 'error' => null));
