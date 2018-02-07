<?php

date_default_timezone_set('Europe/Copenhagen');

include('helpers.php');
spl_autoload_register(function ($class_name) {
    include $class_name . '.php';
});

$dryrun = (isset($argv[1]) && $argv[1] === 'true') ? true : false;

fileLog('STARTING');

try {
    $strategy = new Strategy($dryrun);
    $strategy->execute();
} catch (\Exception $e) {
    fileLog("EXCEPTION [$e->getMessage(), $e->getFile(), $e->getLine()]");
}

fileLog('DONE');
