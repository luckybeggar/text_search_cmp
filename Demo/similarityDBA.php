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

$step = 0;
for ($curHash = dba_firstkey($dbaHashCount); $curHash != false; $curHash = dba_nextkey($dbaHashCount)) {
    $curCount = dba_fetch($curHash, $dbaHashCount);
    if ($curCount > 1) {
        $textIdListLine = dba_fetch($curHash, $dbaHashToText);
        $textIdList = explode(',', $textIdListLine);
        $logger->info('cur hash ' . $curHash . ' site ids: ' . $textIdListLine);
        foreach ($textIdList as $curTextId)
        {
            if (dba_exists($curTextId, $dbaNonUniqueText))
            {
                continue;
            }
            $curTextMetaLine = dba_fetch($curTextId, $dbaTextToHash);
            $curTextMeta = json_decode($curTextMetaLine, true);
            //$logger->info('cur text ' . $curTextId . ' meta: ' . print_r($curTextMeta,1));
            $curTextHashList = array_column($curTextMeta, 'shingle_hash');
            //$logger->info('cur text hash list: ' . print_r($curTextHashList,1));
            $candidateTextIdList = array();
            foreach ($curTextHashList as $tHash) {
                $tTextIdListLine = dba_fetch($curHash, $dbaHashToText);
                if(strpos($tTextIdListLine, ',') == false)
                {
                    continue;
                }
                $tTextIdList = explode(',', $tTextIdListLine);
                foreach ($tTextIdList as $tTextId)
                {
                    $candidateTextIdList[$tTextId] = $tTextId;
                }
            }
            unset($candidateTextIdList[$curTextId]);
            $logger->info('List of candidates for text #' . $curTextId . ': '. implode(',', $candidateTextIdList));
            $candidateHashList = array();
            foreach ($candidateTextIdList as $candidateTextId)
            {
                $curCandidateTextMetaLine = dba_fetch($candidateTextId, $dbaTextToHash);
                $curCandidateTextMeta = json_decode($curCandidateTextMetaLine, true);
                $curCandidateTextHashList = array_column($curCandidateTextMeta, 'shingle_hash');
                $candidateHashList[$candidateTextId] = $curCandidateTextHashList;
            }
//            $logger->info('List of candidates hashes:'. print_r($candidateHashList,1));
            $curMaxSimilarity = 0;
            $curMaxSimilarityTextId = 0;
            $curMaxOuterInclusivity = 0;
            $curMaxOuterInclusivityTextId = 0;
            $curMaxInnerInclusivity = 0;
            $curMaxInnerInclusivityTextId = 0;
            foreach ($candidateHashList as $curCandidateTextId => $curCandidateHashList)
            {
                $commonHashList = array_intersect($curCandidateHashList, $curTextHashList);
                $curSimilarity = (count($commonHashList) * 2)/(count($curCandidateHashList) + count($curTextHashList));
                $curOuterInclusivity = count($commonHashList)/count($curCandidateHashList);
                $curInnerInclusivity = count($commonHashList)/count($curTextHashList);
                if($curSimilarity > $curMaxSimilarity)
                {
                    $curMaxSimilarity = $curSimilarity;
                    $curMaxSimilarityTextId = $curCandidateTextId;
                }
                if($curOuterInclusivity > $curMaxOuterInclusivity)
                {
                    $curMaxOuterInclusivity = $curOuterInclusivity;
                    $curMaxOuterInclusivityTextId = $curCandidateTextId;
                }
                if($curInnerInclusivity > $curMaxInnerInclusivity)
                {
                    $curMaxInnerInclusivity = $curInnerInclusivity;
                    $curMaxInnerInclusivityTextId = $curCandidateTextId;
                }
            }
            $curTextSimMeta = array(
                'sim' => round($curMaxSimilarity, 4),
                'sim_id' => $curMaxSimilarityTextId,
                'inc_inner' => round($curMaxInnerInclusivity, 4),
                'inc_inner_id' => $curMaxInnerInclusivityTextId,
                'out_inner' => round($curMaxOuterInclusivity, 4),
                'out_inner_id' => $curMaxOuterInclusivityTextId,
            );
            $logger->info('text similarity params: '. print_r($curTextSimMeta,1));
            dba_insert($curTextId, json_encode($curTextSimMeta), $dbaNonUniqueText);
            $step++;
            $logger->info('STEP: '. $step);
        }
    }
}
