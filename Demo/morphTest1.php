<?php
/**
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 20.11.16
 * Time: 18:00
 */
require_once('cli.php');
require_once( 'lib/phpmorphy-0.3.7/src/common.php');

$dir = $projectPath. '/lib/phpmorphy-0.3.7/dicts/utf-8/';
$lang = 'ru_RU';
$opts = array(
    'storage' => PHPMORPHY_STORAGE_FILE,
);
try {
    $morphy = new phpMorphy($dir, $lang, $opts);
} catch(phpMorphy_Exception $e) {
    die('Error occured while creating phpMorphy instance: ' . $e->getMessage());
}

$config = parse_ini_file('config.ini', 1);
mb_internal_encoding($config['common']['encoding']);
setlocale(LC_ALL, $config['common']['locale']);

$logger = new Common_Logger(8);
$logger->info('hello!');

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
$logger->info('limits: ' . print_r($limits,1));

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
    }


    $word = 'ДОЙНАЯ КОРОВА';
    $logger->info('Word: '. $word);
    $paradigmList = $morphy->findWord($word);
    foreach ($paradigmList as $paradigm)
    {
        $logger->info('Paradigm: '. print_r($paradigm->getFoundWordForm(), 1));
    }
    return;

    $textNum = 1;
    $db->beginTransaction();

    foreach ($textList as $textId => $textMeta) {
        $logger->info('TEXT META: '. print_r($textMeta, 1));
        $textMeta['article_text'] = strip_tags($textMeta['article_text']);
        $textMeta['article_text'] = str_replace("\n", " ", $textMeta['article_text']);
        $textMeta['article_text'] = str_replace(". ", ".\n", $textMeta['article_text']);
        $sentList = explode("\n", $textMeta['article_text']);
        $logger->info('SENT LIST: '. print_r($sentList, 1));
        foreach ($sentList as $sent)
        {
            $sent = trim($sent);
            $sent = trim($sent, '.');
            $sent = mb_strtoupper($sent);
            $wordList = explode(' ', $sent);
            foreach ($wordList as $word)
            {
                $logger->info('Word: '. $word);
                $paradigmList = $morphy->findWord($word);
                foreach ($paradigmList as $paradigm)
                {
                    $logger->info('Paradigm: '. print_r($paradigm->getFoundWordForm(), 1));
                }


            }
            break;
        }

    break;
    }
    $db->commit();
    break;
} while (count($textList) > 0);