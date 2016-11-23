<?php
/**
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 20.11.16
 * Time: 18:01
 */
$projectPath = realpath(dirname(__FILE__) . '/..');
set_include_path(get_include_path() . PATH_SEPARATOR . $projectPath);
require_once('Common/Logger.php');
