<?php
/**
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 26.10.16
 * Time: 16:33
 */

require_once ('../Shingles/CarpRabin.php');
require_once ('../Common/Logger.php');

mb_internal_encoding('UTF-8');

$logger = new Common_Logger(8);
$cr     = new Shingles_CarpRabin($logger);

$hs     = 'Quidquid latine dictum sit, altum sonatur';
$needle = 'dictum';

$cr->initSearch(mb_strlen($needle), mb_strlen($hs));
$logger->info('DEBUG needle is: ' . $needle . "\n");


$needleHash = $cr->circleHash($needle);

$ln = mb_strlen($needle);
for ($i = 0; $i < (mb_strlen($hs) - $ln + 1); $i++) {
    $frag = mb_substr($hs, $i, $ln);
    $logger->info('DEBUG frag is: ' . $frag . "\n");
    if (isset($tHash)) {
        $tHash = $cr->circleHash($frag, $tHash, $f1);
        $logger->info("DEBUG hot hash from old hash: $tHash frag: $frag and prev char: $f1 is:  $tHash \n");
    } else {
        $tHash = $cr->circleHash($frag);
        $logger->info("DEBUG cold hash from frag $frag is: $tHash\n");
    }
    if ((int)$tHash == (int)$needleHash) {
        $logger->info("$tHash equals $needleHash FOUND!! \n");
        break;
    } else {
        $logger->info("$tHash not equals $needleHash \n");
    }
    $f1 = mb_substr($frag, 0, 1);
    $logger->info('DEBUG f1 is: ' . $f1 . "\n");
}

$frag = 'ABCD';
$tHash = $cr->circleHash($frag);
$logger->info($frag. ' COLD IS '. $tHash);

$frag2 = 'BCDE';
$tHash2 = $cr->circleHash($frag2);
$logger->info($frag2. ' COLD IS '. $tHash2);


$tHash3 = $cr->circleHash($frag2, $tHash, 'A');
$logger->info($frag2. ' HOT IS '. $tHash3);


$tHash4 = $cr->hashSubChar($frag2, $tHash, 'A');
$tHash4 = $cr->hashAddChar($tHash4, 'E');

$logger->info($frag2. ' HOT A-S IS '. $tHash4);

