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
require_once('TSC/Minhash.php');
require_once('TSC/Hash/CRC64.php');
require_once('TSC/Megashingles.php');

$config = parse_ini_file('config.ini', 1);
mb_internal_encoding($config['common']['encoding']);
setlocale(LC_ALL, $config['common']['locale']);

$logger = new Common_Logger(8);

$currentIndex = $config['current']['index'];
$indexConfig  = $config[$currentIndex];

$logOutput = $projectPath. '/Demo/' . $indexConfig['log_super_file'];
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
$superConfig   = $indexConfig['supershingle'];
$minhasher     = new TSC_Hash_CRC64(array(), new ArrayObject(), $logger);
$minhash       = new TSC_Minhash($superConfig, $minhasher, $logger);
$supershingles = new TSC_Megashingles($superConfig, $minhasher, $logger);
$logger->info('DEBUG SUPERCONFIG: ' . print_r($superConfig, 1));

$dbaTextToSuperhash = dba_open($projectPath . '/' . $indexConfig['dba_superhash_to_text'], 'n', $indexConfig['dba_engine']);
$dbaSuperhashToText = dba_open($projectPath . '/' . $indexConfig['dba_text_to_superhash'], 'n', $indexConfig['dba_engine']);
$dbaSuperhashCount  = dba_open($projectPath . '/' . $indexConfig['dba_superhash_count'], 'n', $indexConfig['dba_engine']);
if ($dbaSuperhashCount === false) {
    throw new Exception('cant open counter dba');
}
if ($dbaSuperhashToText === false) {
    throw new Exception('cant open h2t dba');
}
if ($dbaTextToSuperhash === false) {
    throw new Exception('cant open t2h dba');
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
do {
    $logger->info('STEP ID : ' . $idFrom . ' OF ' . $limits->max_id);
    $textListRequest = $db->prepare($sqlGetText);
    $textListRequest->bindParam('id_from', $idFrom);
    $textListRequest->execute();
    $textList = $textListRequest->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);
    if (!empty($textList)) {
        $textList = array_map('reset', $textList);
    } else {
        $logger->info('NO TEXT FOUND HERE');
        continue;
    }

    $textNum = 1;

    foreach ($textList as $textId => $textMeta) {
        $idFrom      = $textId;
        $shingleList = $parser->parseText($textMeta['article_text']);
        $logger->info('TEXT: ' . $textNum);
        $textNum++;

        $prevShingle = null;
        $shingleHashList = array();
        foreach ($shingleList as $shingle) {
            $shingleHashList[] = $hasher->getHash($shingle, $prevShingle);
            $prevShingle = $shingle;
        }
        $miniSet          = $minhash->getMinList($shingleHashList);
        $supershingleList =  $supershingles->getSupershingles($miniSet);

        foreach ($supershingleList as $curHash) {
            if (dba_exists($curHash, $dbaSuperhashToText)) {
                $textIdListLine = dba_fetch($curHash, $dbaSuperhashToText);
                $textIdListLine .= ',' . $textMeta['id'];
                dba_replace($curHash, $textIdListLine, $dbaSuperhashToText);
            } else {
                dba_insert($curHash, $textMeta['id'], $dbaSuperhashToText);
            }
            if (dba_exists($curHash, $dbaSuperhashCount)) {
                $counter = dba_fetch($curHash, $dbaSuperhashCount);
                $counter += 1;
                dba_replace($curHash, $counter, $dbaSuperhashCount);
            } else {
                dba_insert($curHash, 1, $dbaSuperhashCount);
            }
        }
        dba_insert((int)$textMeta['id'], json_encode($supershingleList), $dbaTextToSuperhash);
    }
} while (count($textList) > 0);

$logger->info('SUCCESSFULLY FINISHED');