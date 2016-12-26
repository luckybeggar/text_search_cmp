<?php
/**
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 26.10.16
 * Time: 23:30
 */
$projectPath = realpath(dirname(__FILE__) . '/..');
set_include_path(get_include_path() . PATH_SEPARATOR . $projectPath);
require_once('TSC/NGramParser.php');
require_once('Common/Logger.php');
require_once('TSC/Hash.php');

$config = parse_ini_file('config.ini', 1);
mb_internal_encoding($config['common']['encoding']);
setlocale(LC_ALL, $config['common']['locale']);

$logger = new Common_Logger(8);

$currentConfig = $config['current_read'];
$currentIndex = $currentConfig['index'];


$indexConfig  = $config[$currentIndex];

//$logOutput = $projectPath. '/Demo/' . $indexConfig['log_file'];
//$logger->info('log file: ' . $logOutput);
//$logger->registerOutput($logOutput);
$logger->info('index: ' . $currentIndex);
$logger->info('index config: ' . print_r($indexConfig, 1));

$parser = new TSC_NGramParser(5, $logger);

$logger->info('db config: ' . print_r($config['db'], 1));
$db            = new PDO(
    $config['db']['init'],
    $config['db']['user'],
    $config['db']['pass']
);
$currentSource = $currentConfig['source'];
$sourceConfig  = $config[$currentSource];
$logger->info('source: ' . $currentSource);
$logger->info('source config: ' . print_r($sourceConfig, 1));

$hashClassName = $indexConfig['storage_hash_class'];
include($indexConfig['storage_hash_file']);
$hasher = new $hashClassName($indexConfig, $db, $logger);
/**
 * @var TSC_Hash $hasher
 */
$dbaShingle    = dba_open($projectPath . '/' . $indexConfig['dba_shingle'], 'r', $indexConfig['dba_engine']);
$dbaCollisions    = dba_open($projectPath . '/' . $indexConfig['dba_collisions'], 'r', $indexConfig['dba_engine']);
if ($dbaShingle === false) {
    throw new Exception('cant open $dbaShingle');
}
if ($dbaCollisions === false) {
    throw new Exception('cant open $dbaCollisions');
}


$sqlCount = "SELECT max({$sourceConfig['input_text_id_field']}) as max_id FROM {$sourceConfig['input_text_db']}";
$limits   = $db->query($sqlCount, PDO::FETCH_OBJ)->fetchObject();
$logger->info('result: ' . print_r($limits, 1));

$dupNum = 0;
for ($curHashId = dba_firstkey($dbaCollisions); $curHashId !== false; $curHashId = dba_nextkey($dbaCollisions))
{
    $dupNum = bcadd($dupNum, 1);
    $curCollisionListLine = dba_fetch($curHashId, $dbaCollisions);
    $curCollisions = explode('|', $curCollisionListLine);
    $logger->info('HASH: ' . $curHashId . ': ' . print_r($curCollisions,1));
}
$logger->info('TOTAL DUPLICATES: ' . $dupNum);

