<?php
/**
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 26.10.16
 * Time: 19:08
 */
require_once ('../Shingles/NGramParser.php');
require_once ('../Common/Logger.php');
mb_internal_encoding('UTF-8');

$logger = new Common_Logger(8);
$parser = new Shingles_NGramParser(5, $logger);
$text = <<<TEXT
Lorem ipsum dolor sit amet, consectetur adipiscing elit, 
sed do eiusmod tempor incididunt ut labore et dolore 
magna aliqua. Ut enim ad minim veniam, quis nostrud 
exercitation ullamco laboris nisi ut aliquip ex ea commodo
consequat. Duis aute irure dolor in reprehenderit in 
voluptate velit esse cillum dolore eu fugiat nulla 
pariatur. Excepteur sint occaecat cupidatat non proident,
sunt in culpa qui officia deserunt mollit anim id 
est laborum
TEXT;
$logger->info(print_r($parser->parseText($text),1));

