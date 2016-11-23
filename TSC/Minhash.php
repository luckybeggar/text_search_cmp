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
     * @param array $config
     * @param TSC_Hash $hash
     * @param Common_Logger $logger
     */
    function __construct($config, $hash, $logger)
    {
        self::$logger = $logger;
        $this->hash = $hash;
        $this->nofFunctions = $config['nof_functions'];
        $initMode = $config['init_mode'];
        switch ($initMode) {
            case 'current_functions':
                $this->initFromCurrent($config['current_function_params']);
                break;
            default:
            case 'new_functions':
                $this->initFromScratch();
                break;
        }
        self::log('LIST OF MINHASH PARAMS IS: '. print_r($this->functionParams,1));
    }

    protected function initFromScratch()
    {
        for ($funcId = 1; $funcId<=$this->nofFunctions; $funcId++)
        {
            $this->functionParams[$funcId] = rand(0x10000000, 0xFFFFFFFF);
        }
    }

    protected function initFromCurrent($currentFunctionParams)
    {
        $this->functionParams = $currentFunctionParams;
    }

    public function getValue($functionId, $argument)
    {
        return sprintf('%u', $this->hash->getHash($argument,0) ^ $this->functionParams[$functionId]
         );
    }

    public function getMinList($hashList)
    {
        $minhashList = array();
        $valueList = array();
        foreach ($hashList as $curHash)
        {
            for ($funcId = 1; $funcId<=$this->nofFunctions; $funcId++)
            {
                $curFuncHashValue = $this->getValue($funcId, $curHash);
                self::log('FOR FUNCTION ' . $funcId. ' VALUE IS: ' . $curFuncHashValue);
                if(!isset($minhashList[$funcId]) || $minhashList[$funcId]>$curFuncHashValue)
                {
                    $minhashList[$funcId] = $curFuncHashValue;
                    $valueList[$funcId] = $curHash;
                }
            }
        }
        return $valueList;
    }
}