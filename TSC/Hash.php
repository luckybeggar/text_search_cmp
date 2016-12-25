<?php

/**
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 03.11.16
 * Time: 15:09
 */
abstract class TSC_Hash
{
    /**
     * @var Common_Logger
     */
    public static $logger;

    /**
     * MurMur_v3 constructor.
     *
     * @param array              $config
     * @param PDO|null           $db
     * @param Common_Logger|null $logger
     */
    public function __construct($config, $db, $logger)
    {
        if ($logger !== null) {
            self::$logger = $logger;
        }
    }

    public static function log($message)
    {
        if (self::$logger !== null) {
            self::$logger->debug($message);
        }
    }

    public function getCreateIndexTable($tableName)
    {
        return 'CREATE TABLE ' . $tableName . PHP_EOL .
        ' (' . PHP_EOL .
        ' text_id INT(11) DEFAULT \'0\' NOT NULL, ' . PHP_EOL .
        ' shingle_text VARCHAR(255), ' . PHP_EOL .
        ' shingle_length INT(11), ' . PHP_EOL .
        ' shingle_hash BIGINT(11) DEFAULT \'0\' NOT NULL, ' . PHP_EOL .
        ' CONSTRAINT `PRIMARY` PRIMARY KEY (text_id, shingle_hash)' . PHP_EOL .
        ' ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;';
    }

    public function getCreateCounterTable($tableName)
    {
        return 'CREATE TABLE ' . $tableName .
        '( ' .
        '    shingle_hash BIGINT(20) PRIMARY KEY NOT NULL, ' .
        '    number INT(11) DEFAULT \'1\' NOT NULL         ' .
        ');';
    }

    public function getDropCounterTable($tableName)
    {
        return 'DROP TABLE IF EXISTS ' . $tableName . ';';
    }

    public function getDropIndexTable($tableName)
    {
        return 'DROP TABLE IF EXISTS ' . $tableName . ';';
    }

    public function getInsertSql($tableName)
    {
        return 'INSERT IGNORE INTO ' . $tableName .
        ' SET text_id = :text_id, shingle_text = :shingle_text,' .
        ' shingle_hash = :shingle_hash, shingle_length = :shingle_length';
    }

    public function getCounterInsertSql($tableName)
    {
        return 'INSERT INTO ' . $tableName . ' SET ' .
        'shingle_hash = :shingle_hash, number = 1 ' .
        ' ON DUPLICATE KEY UPDATE number = number +1';
    }

    public function getHash($curSubstring, $prevSubstring = null)
    {
        return $curSubstring;
    }

    public function getHashByteLength()
    {
        return 8;
    }
}