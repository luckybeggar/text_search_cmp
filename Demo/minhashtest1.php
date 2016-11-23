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

$logger = new Common_Logger(8);
$logger->info('hello!');

$minhashConfig = array(
'nof_functions' => 8,
'init_mode' => 'new_functions'
);
$murmur3 = new TSC_Hash_Murmur3(array(), new ArrayObject(), $logger);
$minhash = new TSC_Minhash($minhashConfig, $murmur3, $logger);

$testSet1 = array(1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20);

$logger->info('original set is: '. print_r($testSet1,1));


$tesminiset = $minhash->getMinList($testSet1);

$logger->info('minified set is: '. print_r($tesminiset,1));