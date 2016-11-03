<?php
/**
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 26.10.16
 * Time: 23:30
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
$sqlCount = "SELECT max({$sourceConfig['input_text_id_field']}) as max_id FROM {$sourceConfig['input_text_db']}";
$limits   = $db->query($sqlCount, PDO::FETCH_OBJ)->fetchObject();
$logger->info('result: ' . print_r($limits));
$stepSize = 100;


$sqlGetText = "SELECT {$sourceConfig['input_text_id_field']} as id, {$sourceConfig['input_text_id_field']}, {$sourceConfig['input_text_field']} 
 FROM {$sourceConfig['input_text_db']} 
 WHERE 
 {$sourceConfig['input_text_id_field']} > :id_from 
 ORDER BY {$sourceConfig['input_text_id_field']} ASC
 LIMIT {$stepSize}";

$sqlSaveShingle      = $hasher->getInsertSql($indexConfig['storage_db']);
$sqlDropIndexTable   = $hasher->getDropIndexTable($indexConfig['storage_db']);
$sqlCreateIndexTable = $hasher->getCreateIndexTable($indexConfig['storage_db']);
$db->exec($sqlDropIndexTable);
$db->exec($sqlCreateIndexTable);

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
        foreach ($shingleList as $shingle) {
            $saver = $db->prepare($sqlSaveShingle);
            $saver->bindParam('text_id', $textMeta['id']);
            $saver->bindParam('shingle_text', $shingle);
            $saver->bindParam('shingle_hash', $hasher->getHash($shingle, $prevShingle));
            $saver->execute();
            $prevShingle = $shingle;
        }
    }
    $db->commit();
} while (count($textList) > 0);