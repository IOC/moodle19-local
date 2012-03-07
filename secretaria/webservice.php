<?php

global $CFG;

require_once('../../config.php');
require_once($CFG->dirroot . '/local/secretaria/lib.php');

@ini_set('display_errors', '0');
@ini_set('log_errors', '1');

$service = new local_secretaria_service();

$result = null;
$error = null;

try {
    if (empty($CFG->local_webservice_password)) {
        throw new local_secretaria_exception('Access denied');
    }
    if (!isset($_SERVER['HTTPS']) and
        (!isset($_SERVER['HTTP_X_FORWARDED_PROTO']) or
         $_SERVER['HTTP_X_FORWARDED_PROTO'] !== 'https')) {
        throw new local_secretaria_exception('Access denied');
    }
    if (empty($_POST['data'])) {
        throw new local_secretaria_exception('Bad request');
    }
    if (!$data = json_decode(stripslashes($_POST['data']), true)) {
        throw new local_secretaria_exception('Bad request');
    }
    if (empty($data['token']) or empty($data['func']) or
        !isset($data['params']) or !is_array($data['params'])) {
        throw new local_secretaria_exception('Bad request');
    }
    if ($data['token'] !== $CFG->local_webservice_password) {
        throw new local_secretaria_exception('Access denied');
    }
    $result = $service->execute($data['func'], $data['params']);

} catch (local_secretaria_exception $e) {
    $error = $e->getMessage();

} catch (Exception $e) {
    $error = 'Internal error';
}

echo json_encode(array('result' => $result, 'error' => $error));
