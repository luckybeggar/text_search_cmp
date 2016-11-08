<?php
/**
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 06.11.16
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

$currentIndex = $config['current']['index'];
$indexConfig  = $config[$currentIndex];
$logger->info('index: ' . $currentIndex);
$logger->info('index config: ' . print_r($indexConfig, 1));
$hashClassName = $indexConfig['storage_hash_class'];
include($indexConfig['storage_hash_file']);
$hasher = new $hashClassName($indexConfig, $db, $logger);
/**
 * @var TSC_Hash $hasher
 */
$dbaTextToHash    = dba_open($projectPath . '/' . $indexConfig['dba_hash_to_text'], 'r', $indexConfig['dba_engine']);
$dbaHashToText    = dba_open($projectPath . '/' . $indexConfig['dba_text_to_hash'], 'r', $indexConfig['dba_engine']);
$dbaHashCount     = dba_open($projectPath . '/' . $indexConfig['dba_hash_count'], 'r', $indexConfig['dba_engine']);
$dbaNonUniqueText = dba_open($projectPath . '/' . $indexConfig['dba_non_unique'], 'r', $indexConfig['dba_engine']);


if ($dbaHashCount === false) {
    throw new Exception('cant open counter dba');
}
if ($dbaHashToText === false) {
    throw new Exception('cant open h2t dba');
}
if ($dbaTextToHash === false) {
    throw new Exception('cant open t2h dba');
}
if ($dbaTextToHash === false) {
    throw new Exception('cant open non unique dba');
}

$textList = array();

for ($curTextId = dba_firstkey($dbaNonUniqueText); $curTextId != false; $curTextId = dba_nextkey($dbaNonUniqueText))
{
    $curTextMetaLine = dba_fetch($curTextId, $dbaNonUniqueText);
    $curTextMeta = json_decode($curTextMetaLine, true);
    if($curTextMeta['sim']<0.7)
    {
        continue;
    }
    $logger->info('TEXT ID #' . $curTextId . ': ' . print_r($curTextMeta,1));
    $textList[$curTextId] = $curTextMeta;

}

$logger->info('NOF TEXTS: '  . count($textList));
$logger->info('TEXTS: '  . implode(',', array_keys($textList)));


$sqlGetText = "SELECT 
{$sourceConfig['input_text_id_field']}, 
{$sourceConfig['input_text_field']} 

 FROM {$sourceConfig['input_text_db']} 
 WHERE 
 {$sourceConfig['input_text_id_field']} = :id_text
  OR
 {$sourceConfig['input_text_id_field']} = :id_text_dup
 
 ";



foreach ($textList as $textId => $textSimMeta)
{
    $textRequest = $db->prepare($sqlGetText);
    $textRequest->bindParam('id_text', $textId);
    $textRequest->bindParam('id_text_dup', $textSimMeta['sim_id']);
    $textRequest->execute();
    $curTextMeta = $textRequest->fetchAll(PDO::FETCH_ASSOC);
    $logger->info('CUR TEXT META: '. print_r($curTextMeta, 1));
    $logger->info('CUR TEXT SIMILARITY: '. print_r($textSimMeta['sim'], 1));
}
