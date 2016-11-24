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

    protected $supershingleSize = 10;

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
     * @param Common_Logger $logger
     */
    public function __construct($config, $logger)
    {
        self::$logger = $logger;
        $this->supershingleSize = $config['supershingle_size'];
    }

    public function getMegashingles($shingleHashList)
    {
        sort($shingleHashList);
        $shingleChunkedHashList = array_chunk($shingleHashList, $this->supershingleSize);
        self::log('DEBUG CHUNKED: '. print_R($shingleChunkedHashList,1));
        $superShingleList       = array();
        foreach ($shingleChunkedHashList as $chunk) {
            $superShingleList[] = implode('', $chunk);
        }
        self::log('DEBUG SH LIST: '. print_R($superShingleList,1));

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