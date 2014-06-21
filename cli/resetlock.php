<?php

define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
global $CFG;
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot.'/blocks/mhaairs/lib/lock/abstractlock.php');

cli_heading('Running lock reset');

try {
    $instance = new block_mhaairs_locinst(false);
    $instance->lock()->unlock(true);
    echo 'Unlocked MHaairs lock!', PHP_EOL;
} catch (Exception $e) {
    cli_problem($e->getMessage());
}
