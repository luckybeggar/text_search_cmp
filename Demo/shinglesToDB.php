<?php
/**
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 26.10.16
 * Time: 23:30
 */
require_once ('../Shingles/NGramParser.php');
require_once ('../Common/Logger.php');
mb_internal_encoding('UTF-8');
$config = parse_ini_file('config.ini', 1);

$logger = new Common_Logger(8);
$parser = new Shingles_NGramParser(5, $logger);
$logger->info('db config: '. print_r($config,1));
$db = new PDO(
    $config['db']['init'],
    $config['db']['user'],
    $config['db']['pass']
);
