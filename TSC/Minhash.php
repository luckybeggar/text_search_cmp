<?php

/**
 * Class uses N random functions to get N minimum values from array
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 24.11.16
 * Time: 1:08
 */
class TSC_Minhash
{
    /**
     * @var Common_Logger
     */
    public static $logger;

    protected $nofFunctions = 7;

    protected $functionParams = array();

    /**
     * @var TSC_Hash
     */
    protected $hash;

    public static function log($message)
    {
        if (self::$logger !== null) {
            self::$logger->debug($message);
        }
    }

    /**
     * TSC_Minhash constructor.
     *
     * @param array         $config
     * @param TSC_Hash      $hash
     * @param Common_Logger $logger
     */
    function __construct($config, $hash, $logger)
    {
        self::$logger       = $logger;
        $this->hash         = $hash;
        $this->nofFunctions = $config['nof_functions'];
        $initMode           = $config['init_mode'];
        switch ($initMode) {
            case 'current_functions':
                $this->initFromCurrent($config['current_function_params']);
                break;
            default:
            case 'new_functions':
                $this->initFromScratch();
                break;
        }
        self::log('LIST OF MINHASH PARAMS IS: ' . print_r($this->functionParams, 1));
    }

    protected function initFromScratch()
    {
        for ($funcId = 1; $funcId <= $this->nofFunctions; $funcId++) {

            $this->functionParams[$funcId] = self::get64bitRand();
        }
    }

    protected static function get64bitRand()
    {
        $random1    = sprintf('%06x', mt_rand(0, 0xFFFFFF));
        $random2    = sprintf('%06x', mt_rand(0, 0xFFFFFF));
        $random3    = sprintf('%04x', mt_rand(0, 0xFFFF));
        $numberLine = $random1 . $random2 . $random3;

        return $numberLine;
    }

    protected function initFromCurrent($currentFunctionParams)
    {
        $this->functionParams = $currentFunctionParams;
    }

    public function getValue($functionId, $argument)
    {
        $xor1 = gmp_init($this->hash->getHash($argument, 0), 10);
        $xor2 = gmp_init($this->functionParams[$functionId], 16);
        $xor3 = gmp_xor($xor1, $xor2);

        return gmp_strval($xor3, 10);
    }

    public function getMinList($hashList)
    {
        $minhashList = array();
        $valueList   = array();
        foreach ($hashList as $curHash) {
            for ($funcId = 1; $funcId <= $this->nofFunctions; $funcId++) {
                $curFuncHashValue = $this->getValue($funcId, $curHash);
//                self::log('FOR FUNCTION ' . $funcId. ' VALUE IS: ' . $curFuncHashValue);
                if (!isset($minhashList[$funcId]) || $minhashList[$funcId] > $curFuncHashValue) {
                    $minhashList[$funcId] = $curFuncHashValue;
                    $valueList[$funcId]   = $curHash;
                }
            }
        }

        return $valueList;
    }
}