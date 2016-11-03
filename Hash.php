<?php

/**
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 03.11.16
 * Time: 15:09
 */
abstract class Hash
{
    /**
     * MurMur_v3 constructor.
     *
     * @param array $config
     * @param PDO   $db
     */
    public function __construct($config, $db)
    {
    }

    public function getCreateIndexTable($tableName)
    {
        return 'CREATE TABLE ' . $tableName . PHP_EOL .
        ' (' . PHP_EOL .
        ' text_id INT(11) DEFAULT \'0\' NOT NULL, ' . PHP_EOL .
        ' shingle_text VARCHAR(255), ' . PHP_EOL .
        ' shingle_hash BIGINT(11) DEFAULT \'0\' NOT NULL, ' . PHP_EOL .
        ' CONSTRAINT `PRIMARY` PRIMARY KEY (text_id, shingle_hash)' . PHP_EOL .
        ' ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;';
    }

    public function getDropIndexTable($tableName)
    {
        return 'DROP TABLE IF EXISTS ' . $tableName . ';';
    }

    public function getInsertSql($tableName)
    {
        return 'INSERT IGNORE INTO ' . $tableName .
        ' SET text_id = :text_id, shingle_text = :shingle_text,' .
        ' shingle_hash = :shingle_text';
    }

    public function getHash($curSubstring, $prevSubctring)
    {
        return $curSubstring;
    }
}