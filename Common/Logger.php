<?php

/**
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 26.10.16
 * Time: 16:11
 */
class Common_Logger
{
    protected $priority = 7;

    protected $loggerPointers = array();

    protected function getOutput()
    {
        return array('php://stdout');
    }

    public function __construct($priority)
    {
        $this->priority = $priority;
        foreach ($this->getOutput() as $source) {
            $this->loggerPointers[] = fopen($source, 'w');
        }
    }

    public function __destruct()
    {
        foreach ($this->loggerPointers as $source) {
            fclose($source);
        }
    }

    public function log($message, $priority = 1)
    {
        if ($priority <= $this->priority) {
            foreach ($this->loggerPointers as $source) {
                fwrite($source, $message . "\n");
            }
        }
    }

    public function info($message)
    {
        $this->log($message, 1);
    }

    public function debug($message)
    {
        $this->log($message, 8);
    }

}