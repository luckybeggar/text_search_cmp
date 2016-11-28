<?php

/**
 * Class TSC_Hash_CRC64
 */
class TSC_Hash_CRC64 extends TSC_Hash
{
    /**
     * TSC_Hash_CRC32 constructor.
     *
     * @param array         $config
     * @param PDO           $db
     * @param Common_Logger $logger
     */
    public function __construct($config, $db, $logger)
    {
        parent::__construct($config, $db, $logger);
    }

    public function getHash($curSubstring, $prevSubstring)
    {
        return crc32($curSubstring) . crc32(strrev($curSubstring));
    }
}