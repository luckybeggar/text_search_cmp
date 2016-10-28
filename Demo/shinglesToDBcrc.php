<?php
/**
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 26.10.16
 * Time: 23:30
 */
require_once('../Shingles/NGramParser.php');
require_once('../Common/Logger.php');
require_once('../Shingles/CarpRabin.php');

mb_internal_encoding('UTF-8');
setlocale(LC_ALL, 'ru_RU');
$config = parse_ini_file('config.ini', 1);

$logger = new Common_Logger(8);
$parser = new Shingles_NGramParser(5, $logger);
$cr     = new Shingles_CarpRabin($logger);

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

$cr->initSearch(200, 10000);


$sqlCount = "SELECT min({$sourceConfig['input_text_id_field']}) as min_id, max({$sourceConfig['input_text_id_field']}) as max_id FROM {$sourceConfig['input_text_db']}";
$limits   = $db->query($sqlCount, PDO::FETCH_OBJ)->fetchObject();
$logger->info('sql: ' . $sqlCount);
$logger->info('result: ' . print_r($limits));
$stepSize = 100;
$stepNof  = bcdiv(bcsub($limits->max_id, $limits->min_id), $stepSize);
$logger->info('number of steps: ' . $stepNof);

$sqlGetText = "SELECT {$sourceConfig['input_text_id_field']}, {$sourceConfig['input_text_field']} 
 FROM {$sourceConfig['input_text_db']} 
 WHERE 
 {$sourceConfig['input_text_id_field']} >= :id_from 
 AND {$sourceConfig['input_text_id_field']} < :id_to";

$sqlSaveShingle = "INSERT IGNORE INTO {$sourceConfig['output_text_db']} 
SET text_id = :text_id, shingle_text = :shingle_text, shingle_hash = :shingle_hash";

for ($id = $limits->min_id; $id < $limits->max_id; $id = bcadd($id, $stepSize)) {
    $logger->info('STEP ID: ' . $id . ' OF ' . $limits->max_id);
    $textListRequest = $db->prepare($sqlGetText);
    $textListRequest->bindParam('id_from', $id);
    $textListRequest->bindParam('id_to', bcadd($id, $stepSize));
    $textListRequest->execute();
    $textList = $textListRequest->fetchAll(PDO::FETCH_ASSOC);
    $textNum  = 1;
    $db->beginTransaction();

    foreach ($textList as $textMeta) {
        $shingleList = $parser->parseText($textMeta['article_text']);
        $logger->info('TEXT: ' . $textNum);
        $textNum++;
        $prevHash = null;
        $prevChar = null;
        foreach ($shingleList as $shingle) {
            $curHash = crc32($shingle);
            $saver   = $db->prepare($sqlSaveShingle);
            $saver->bindParam('text_id', $textMeta['id']);
            $saver->bindParam('shingle_hash', $curHash);
            $saver->bindParam('shingle_text', $shingle);
            $saver->execute();
        }
    }
    $db->commit();
}