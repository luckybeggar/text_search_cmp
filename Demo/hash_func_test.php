<?php
/**
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 29.11.16
 * Time: 19:08
 */
require_once('../TSC/Hash.php');
require_once('../TSC/Hash/RK.php');
require_once ('../Common/Logger.php');
mb_internal_encoding('UTF-8');

$logger = new Common_Logger(10);
//$options = array('hash_init_mode' => 'ready_prime', 'hash_prime' => '69605899126177831');
$options = array('hash_init_mode' => 'number_of_bytes', 'hash_bytes' => '8');


$hasher = new TSC_Hash_RK($options, null,  $logger);

$logger->info(print_r($hasher->getHash('TEST GOOD TEST', null),1));
$logger->info(print_r($hasher->getHash('GOOD TEST YES', 'TEST GOOD TEST'),1));
$logger->info(print_r($hasher->getHash('GOOD TEST YES', null),1));

$logger->info(print_r($hasher->getHash('ЗАИНТЕРЕСОВАЛО ДАННОЕ ПРЕДЛОЖЕНИЕ МОЖЕ  ТЕ ОЗНАКОМИТЬСЯ', null),1));
$logger->info(print_r($hasher->getHash('ЕСЛИ ЗАИНТЕРЕСОВАЛО ДАННОЕ ПРЕДЛОЖЕНИЕ ОЗНАКОМИТЬСЯ', null),1));
