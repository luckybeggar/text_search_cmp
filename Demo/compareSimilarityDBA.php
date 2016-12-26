<?php
/**
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 26.12.16
 * Time: 21:52
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
$currentIndex = $config['current']['index'];
$indexConfig  = $config[$currentIndex];

$logOutput = $projectPath. '/Demo/' . $indexConfig['log_compare_file'];
$logger->info('log file: ' . $logOutput);
$logger->registerOutput($logOutput);


$logger->info('index: ' . $currentIndex);
$logger->info('index config: ' . print_r($indexConfig, 1));



$parser = new TSC_NGramParser(5, $logger);

$logger->info('db config: ' . print_r($config['db'], 1));
$db            = new PDO(
    $config['db']['init'],
    $config['db']['user'],
    $config['db']['pass']
);
$currentSource = $config['current']['source'];
$sourceConfig  = $config[$currentSource];
$logger->info('source: ' . $currentSource);
$logger->info('source config: ' . print_r($sourceConfig, 1));

$hashClassName = $indexConfig['storage_hash_class'];
include($indexConfig['storage_hash_file']);
$hasher = new $hashClassName($indexConfig, $db, $logger);
/**
 * @var TSC_Hash $hasher
 */
$dbaNonUniqueText = dba_open($projectPath . '/' . $indexConfig['dba_non_unique'], 'r', $indexConfig['dba_engine']);
$dbaNonUniqueSuperText = dba_open($projectPath . '/' . $indexConfig['dba_non_unique_super'], 'r', $indexConfig['dba_engine']);

if ($dbaNonUniqueText === false) {
    throw new Exception('cant open non unique dba');
}
if ($dbaNonUniqueSuperText === false) {
    throw new Exception('cant open non unique dba');
}

$textList = array();
$nofNonUniqueText = 0;
for ($curTextId = dba_firstkey($dbaNonUniqueText); $curTextId !== false; $curTextId = dba_nextkey($dbaNonUniqueText))
{
    $curTextMetaLine = dba_fetch($curTextId, $dbaNonUniqueText);
    $curTextMeta = json_decode($curTextMetaLine, true);
    $nofNonUniqueText++;
    if($curTextMeta['sim']<0.7)
    {
        continue;
    }
    $logger->info('TEXT ID #' . $curTextId . ': ' . print_r($curTextMeta,1));
    $textList[$curTextId] = $curTextMeta;
    $curTextMetaExport = array('text_id' => $curTextId) + $curTextMeta;
}
$logger->info('NOF TEXTS: '  . count($textList));
$logger->info('NOF NON UNIQUE TEXTS: ' . $nofNonUniqueText);
$textFullIdList = array_keys($textList);

$textSuperList = array();
for ($curTextId = dba_firstkey($dbaNonUniqueSuperText); $curTextId !== false; $curTextId = dba_nextkey($dbaNonUniqueSuperText))
{
    $curTextMetaLine = dba_fetch($curTextId, $dbaNonUniqueSuperText);
    $curTextMeta = json_decode($curTextMetaLine, true);
    $logger->info('TEXT ID #' . $curTextId . ': ' . print_r($curTextMeta,1));
    $textSuperList[$curTextId] = $curTextMeta;
}
$textSuperIdList = array_keys($textSuperList);

array_walk($textFullIdList, 'intval');
array_walk($textSuperIdList, 'intval');

$logger->info('NOF SUPER TEXTS: '  . count($textSuperList));
$logger->info('SUPER TEXTS: '  . implode(',', $textSuperIdList));
$logger->info('TEXTS: '  . implode(',', $textFullIdList));



$missedIdList = array_diff($textFullIdList, $textSuperIdList);
$logger->info('MISSED TEXTS: '  . implode(',', array_keys($missedIdList)));

$wrongIdList = array_diff($textSuperIdList, $textFullIdList);
$logger->info('WRONG TEXTS: '  . implode(',', array_keys($wrongIdList)));