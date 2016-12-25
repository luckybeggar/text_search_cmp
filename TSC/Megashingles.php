<?php

/**
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 25.11.16
 * Time: 2:11
 */
class TSC_Megashingles
{
    /**
     * @var Common_Logger
     */
    public static $logger;


    /**
     * @var TSC_Hash
     */
    protected $hash;


    protected $supershingleSize = 10;

    protected $hashByteSize = 8;

    protected $nofSupershingles = 10;

    protected $nofMegashingles = 10;

    public static function log($message)
    {
        if (self::$logger !== null) {
            self::$logger->debug($message);
        }
    }

    /**
     * TSC_Megashingles constructor.
     *
     * @param array         $config
     * @param TSC_Hash      $hash
     * @param Common_Logger $logger
     */
    public function __construct($config, $hash, $logger)
    {
        self::$logger           = $logger;
        $this->supershingleSize = $config['supershingle_size'];
        $this->hashByteSize     = $config['hash_byte_size'];
        $this->hash             = $hash;
    }

    /**
     * @param $shingleHashList
     *
     * @return array
     */
    public function getSupershingles($shingleHashList)
    {
        $formatLen = $this->hashByteSize;
        foreach ($shingleHashList as $sId => $shingleHash) {
            $shingleHash = base_convert($shingleHash, 10, 16);
            $prefix      = '';
            if (mb_strlen($shingleHash) < $formatLen) {
                $prefix = str_repeat('0', $formatLen - mb_strlen($shingleHash));
            }
            $shingleHashList[$sId] = $prefix . $shingleHash;
        }

        $shingleChunkedHashList = array_chunk($shingleHashList, $this->supershingleSize);
        self::log('DEBUG CHUNKED: ' . print_r($shingleChunkedHashList, 1));
        $superShingleList = array();
        foreach ($shingleChunkedHashList as $chunk) {
            $superShingleList[] = $this->hash->getHash(implode('', $chunk));
        }

//        self::log('DEBUG SH LIST: ' . print_r($superShingleList, 1));


        return $superShingleList;
    }

    public function getMegashingles2($shingleHashList)
    {
        sort($shingleHashList);
        $formatLen  = $this->hashByteSize * 2;
        $formatMask = '%0' . $formatLen . 'X';
        foreach ($shingleHashList as $sId => $shingleHash) {
            $shingleHashList[$sId] = sprintf($formatMask, $shingleHash);
        }
        $shingleChunkedHashList = array_chunk($shingleHashList, $this->supershingleSize);
        self::log('DEBUG CHUNKED: ' . print_r($shingleChunkedHashList, 1));
        $superShingleList = array();
        foreach ($shingleChunkedHashList as $chunk) {
            $superShingleList[] = implode('', $chunk);
        }
        self::log('DEBUG SH LIST: ' . print_r($superShingleList, 1));


        $result = array();
        foreach ($superShingleList as $val1) {
            foreach ($superShingleList as $val2) {
                if ($val1 < $val2) {
                    $result[] = $val1 . $val2;
                }
            }
        }

        return $result;
    }

}