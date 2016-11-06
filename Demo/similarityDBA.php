<?php
/**
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 06.11.16
 * Time: 21:52
 */
$projectPath = realpath(dirname(__FILE__) . '/..');
set_include_path(get_include_path() . PATH_SEPARATOR . $projectPath);
require_once('Shingles/NGramParser.php');
require_once('Common/Logger.php');
require_once('Hash.php');

$config = parse_ini_file('config.ini', 1);
mb_internal_encoding($config['common']['encoding']);
setlocale(LC_ALL, $config['common']['locale']);

$logger = new Common_Logger(8);
$parser = new Shingles_NGramParser(5, $logger);

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
 * @var Hash $hasher
 */
$dbaTextToHash    = dba_open($projectPath . '/' . $indexConfig['dba_hash_to_text'], 'r', $indexConfig['dba_engine']);
$dbaHashToText    = dba_open($projectPath . '/' . $indexConfig['dba_text_to_hash'], 'r', $indexConfig['dba_engine']);
$dbaHashCount     = dba_open($projectPath . '/' . $indexConfig['dba_hash_count'], 'r', $indexConfig['dba_engine']);
$dbaNonUniqueText = dba_open($projectPath . '/' . $indexConfig['dba_non_unique'], 'n', $indexConfig['dba_engine']);


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


for ($curHash = dba_firstkey($dbaHashCount); $curHash != false; $curHash = dba_nextkey($dbaHashCount)) {
    $curCount = dba_fetch($curHash, $dbaHashCount);
    if ($curCount > 1) {
        $textIdListLine = dba_fetch($curHash, $dbaHashToText);
        $textIdList = explode(',', $textIdListLine);
        foreach ($textIdList as $curTextId)
        {
            $curTextMataLine = dba_fetch($curTextId, $dbaTextToHash);
            $curTextMeta = json_decode($curTextMataLine, true);
            $logger->info('cur text ' . $curTextId . ' meta: ' . print_r($curTextMeta,1));
            $curTextHashList = array_column($curTextMeta, 'shingle_hash');
            $logger->info('cur text hash list: ' . print_r($curTextHashList,1));
        }
        $logger->info('cur hash ' . $curHash . ' site ids: ' . $textIdListLine);
        die();
    }

}
die();

$sqlGetText = "SELECT {$sourceConfig['input_text_id_field']} as id, {$sourceConfig['input_text_id_field']}, {$sourceConfig['input_text_field']} 
 FROM {$sourceConfig['input_text_db']} 
 WHERE 
 {$sourceConfig['input_text_id_field']} > :id_from 
 ORDER BY {$sourceConfig['input_text_id_field']} ASC
 LIMIT {$stepSize}";

$sqlSaveShingle  = $hasher->getInsertSql($indexConfig['index_table']);
$sqlCountShingle = $hasher->getCounterInsertSql($indexConfig['counter_table']);

$idFrom = 0;
do {
    $logger->info('STEP ID : ' . $idFrom . ' OF ' . $limits->max_id);
    $textListRequest = $db->prepare($sqlGetText);
    $textListRequest->bindParam('id_from', $idFrom);
    $textListRequest->execute();
    $textList = $textListRequest->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);
    if (!empty($textList)) {
        $textList = array_map('reset', $textList);
    }

    $textNum = 1;
    $db->beginTransaction();

    foreach ($textList as $textId => $textMeta) {
        $idFrom      = $textId;
        $shingleList = $parser->parseText($textMeta['article_text']);
        $logger->info('TEXT: ' . $textNum);
        $textNum++;
        $prevShingle = null;
        $shingleMeta = array();
        foreach ($shingleList as $shingle) {
            $curHash       = $hasher->getHash($shingle, $prevShingle);
            $shingleMeta[] = array(
//                'text_id'      => $textMeta['id'],
'shingle_text'   => $shingle,
'shingle_hash'   => $curHash,
'shingle_length' => mb_strlen($shingle)
            );
//            $shingleMeta[] = $curHash . '|' . $shingle . '|' . mb_strlen($shingle);

            if (dba_exists($curHash, $dbaHashToText)) {
                $textIdListLine = dba_fetch($curHash, $dbaHashToText);
                $textIdListLine .= ',' . $textMeta['id'];
                dba_replace($curHash, $textIdListLine, $dbaHashToText);
            } else {
                dba_insert($curHash, $textMeta['id'], $dbaHashToText);
            }
            if (dba_exists($curHash, $dbaHashCount)) {
                $counter = dba_fetch($curHash, $dbaHashCount);
                $counter += 1;
                dba_replace($curHash, $counter, $dbaHashCount);
            } else {
                dba_insert($curHash, 1, $dbaHashCount);
            }
            $prevShingle = $shingle;
        }
        dba_insert((int)$textMeta['id'], json_encode($shingleMeta), $dbaTextToHash);
    }
    $db->commit();
} while (count($textList) > 0);