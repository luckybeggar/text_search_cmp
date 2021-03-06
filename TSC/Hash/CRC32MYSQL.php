<?php
/**
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 03.11.16
 * Time: 17:06
 */
class TSC_Hash_CRC32MYSQL extends TSC_Hash
{
    /**
     * Hash_CRC32 constructor.
     *
     * @param array $config
     * @param PDO   $db
     * @param Common_Logger $logger
     */
    public function __construct($config, $db, $logger)
    {
        parent::__construct($config, $db, $logger);
    }

    public function getInsertSql($tableName)
    {
        return 'INSERT IGNORE INTO ' . $tableName .
        ' SET text_id = :text_id, shingle_text = :shingle_text,' .
        ' shingle_hash = CRC32(:shingle_hash), shingle_length = :shingle_length';
    }

    public function getHash($curSubstring, $prevSubstring)
    {
        return $curSubstring;
    }
}