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

    protected $format = '[{time}] [{ptime}] {message}';

    protected $formatVars = array(
        'ptime' => 0
    );

    protected $startTime;

    protected $loggerPointers = array();

    protected function getOutput()
    {
        return array('php://stdout');
    }

    public function __construct($priority)
    {
        $this->startTime = mktime(true);
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

    protected function getFormatedMessage($message)
    {
        $vars            = $this->formatVars;
        $formatedMessage = $this->format;
        $vars['time']    = date('Y-m-d H:i:s');
        $vars['message'] = $message;
        $vars['ptime']   = bcsub(mktime(true) , $this->startTime);

        foreach ($vars as $varName => $varVal) {
            $formatedMessage = str_replace('{' . $varName . '}', $varVal, $formatedMessage);
        }

        return $formatedMessage;
    }

    protected function setVar($name, $value)
    {
        $this->formatVars[$name] = $value;
    }

    public function log($message, $priority = 1)
    {
        if ($priority <= $this->priority) {
            $fMessage = $this->getFormatedMessage($message);
            foreach ($this->loggerPointers as $source) {
                fwrite($source, $fMessage . "\n");
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