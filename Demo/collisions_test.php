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

$currentIndex = $config['current']['index'];
$indexConfig  = $config[$currentIndex];

$logOutput = $projectPath. '/Demo/' . $indexConfig['log_file'];
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
$dbaShingle    = dba_open($projectPath . '/' . $indexConfig['dba_shingle'], 'n', $indexConfig['dba_engine']);
$dbaCollisions    = dba_open($projectPath . '/' . $indexConfig['dba_collisions'], 'n', $indexConfig['dba_engine']);
if ($dbaShingle === false) {
    throw new Exception('cant open $dbaShingle');
}
if ($dbaCollisions === false) {
    throw new Exception('cant open $dbaCollisions');
}


$sqlCount = "SELECT max({$sourceConfig['input_text_id_field']}) as max_id FROM {$sourceConfig['input_text_db']}";
$limits   = $db->query($sqlCount, PDO::FETCH_OBJ)->fetchObject();
$logger->info('result: ' . print_r($limits, 1));
$stepSize = 100;


$sqlGetText = "SELECT {$sourceConfig['input_text_id_field']} as id, {$sourceConfig['input_text_id_field']}, {$sourceConfig['input_text_field']} 
 FROM {$sourceConfig['input_text_db']} 
 WHERE 
 {$sourceConfig['input_text_id_field']} > :id_from 
 ORDER BY {$sourceConfig['input_text_id_field']} ASC
 LIMIT {$stepSize}";

$idFrom = 0;
$textNum = 1;
$hashNum = 1;

do {
    $logger->info('STEP ID : ' . $idFrom . ' OF ' . $limits->max_id);
    $textListRequest = $db->prepare($sqlGetText);
    $textListRequest->bindParam('id_from', $idFrom);
    $textListRequest->execute();
    $textList = $textListRequest->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);
    if (!empty($textList)) {
        $textList = array_map('reset', $textList);
    }


    $db->beginTransaction();

    foreach ($textList as $textId => $textMeta) {
        $idFrom      = $textId;
        $shingleList = $parser->parseText($textMeta['article_text']);
        $logger->info('TEXT: ' . $textNum);
        $textNum = bcadd($textNum, 1);
        $prevShingle = null;
        $shingleMeta = array();
        foreach ($shingleList as $shingle) {
            $curHash       = $hasher->getHash($shingle, $prevShingle);
            $hashNum = bcadd($hashNum, 1);

            if (dba_exists($curHash, $dbaShingle)) {
                $shingleText = dba_fetch($curHash, $dbaShingle);
                $shingleTextList = explode('|', $shingleText);
                $shingleTextList = array_flip($shingleTextList);
                if(!isset($shingleTextList[$shingle]))
                {
                    $shingleText .= '|' . $shingle;
                    dba_replace($curHash, $shingleText, $dbaShingle);
                    dba_replace($curHash, $shingleText, $dbaCollisions);
                }
            } else {
                dba_insert($curHash, $shingle, $dbaShingle);
            }
            $prevShingle = $shingle;
        }
    }
    $db->commit();
} while (count($textList) > 0);
$logger->info('NUMBER OF TEXTS TOTAL: ' . $textNum);
$logger->info('NUMBER OF hashes TOTAL: ' . $hashNum);
/**
 *INDEX 7 -CRC32
 * [2016-11-26 14:55:13] [4491] TEXT: 54034
 * [2016-11-26 14:55:13] [4491] STEP ID : 398358 OF 398358
* [2016-11-26 14:55:14] [4492] NUMBER OF TEXTS TOTAL: 54035
* [2016-11-26 14:55:14] [4492] NUMBER OF hashes TOTAL: 10376876
 * TOTAL DUPLICATES: 12320
INDEX 6 - RK
 * [2016-11-26 16:16:40] [25] TOTAL DUPLICATES: 13089
*
 * INDEX 8 murmur3
 *TOTAL DUPLICATES: 12294

 */