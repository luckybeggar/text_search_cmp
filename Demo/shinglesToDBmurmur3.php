<?php
/**
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 26.10.16
 * Time: 23:30
 */
require_once('../Shingles/NGramParser.php');
require_once('../Common/Logger.php');

mb_internal_encoding('UTF-8');
setlocale(LC_ALL, 'ru_RU');
$config = parse_ini_file('config.ini', 1);

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


$sqlCount = "SELECT max({$sourceConfig['input_text_id_field']}) as max_id FROM {$sourceConfig['input_text_db']}";
$limits   = $db->query($sqlCount, PDO::FETCH_OBJ)->fetchObject();
$logger->info('result: ' . print_r($limits));
$stepSize = 100;


$sqlGetText = "SELECT {$sourceConfig['input_text_id_field']}, {$sourceConfig['input_text_field']} 
 FROM {$sourceConfig['input_text_db']} 
 WHERE 
 {$sourceConfig['input_text_id_field']} > :id_from 
 ORDER BY {$sourceConfig['input_text_id_field']} ASC
 LIMIT {$stepSize}";

$sqlSaveShingle = "INSERT IGNORE INTO {$sourceConfig['output_text_db']} 
SET text_id = :text_id, shingle_text = :shingle_text, shingle_hash = murmur_hash_v3(:shingle_text, 0)";

$idFrom = 0;
do {
    $logger->info('STEP ID : ' . $idFrom . ' OF ' . $limits->max_id);
    $textListRequest = $db->prepare($sqlGetText);
    $textListRequest->bindParam('id_from', $idFrom);
    $textListRequest->execute();
    $textList = $textListRequest->fetchAll(PDO::FETCH_ASSOC|PDO::FETCH_GROUP);
    if(!empty($textList))
    {
        $textList = array_map('reset', $textList);
    }
    $textNum  = 1;
    $db->beginTransaction();

    foreach ($textList as $textId => $textMeta) {
        $idFrom = $textId;
        $shingleList = $parser->parseText($textMeta['article_text']);
        $logger->info('TEXT: ' . $textNum);
        $textNum++;
        foreach ($shingleList as $shingle) {
            $saver   = $db->prepare($sqlSaveShingle);
            $saver->bindParam('text_id', $textMeta['id']);
            $saver->bindParam('shingle_text', $shingle);
            $saver->execute();
        }
    }
    $db->commit();
} while (count($textList) > 0);