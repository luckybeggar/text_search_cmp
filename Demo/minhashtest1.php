<?php
/**
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 24.11.16
 * Time: 1:56
 */

require_once('cli.php');
require_once('TSC/Hash.php');
require_once('TSC/Minhash.php');
require_once('TSC/Hash/Murmur3.php');
require_once('TSC/Megashingles.php');

$logger = new Common_Logger(8);
$logger->info('hello!');

$minhashConfig = array(
'nof_functions' => 84,
'init_mode' => 'new_functions'
);
$murmur3 = new TSC_Hash_Murmur3(array(), new ArrayObject(), $logger);
$minhash = new TSC_Minhash($minhashConfig, $murmur3, $logger);
$megashingles = new TSC_Megashingles(array('supershingle_size' => 14), $logger);
$testSet1 = range(1,2000);

$logger->info('original set legth is: '. count($testSet1));


$tesminiset = $minhash->getMinList($testSet1);

$logger->info('minified set is: '. print_r($tesminiset,1));

$megashingleList =  $megashingles->getMegashingles($tesminiset);

$logger->info('megashingles set is: '. print_r($megashingleList,1));
