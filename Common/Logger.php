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

    protected $loggerSources = array();

    public function __construct($priority)
    {
        $this->startTime = time();
        $this->priority  = $priority;
        $this->linkAllOutput();
    }

    public function __destruct()
    {
        $this->unlinkAllOutput();
    }

    protected function linkAllOutput()
    {
        foreach ($this->getOutput() as $source) {
            $openRes = fopen($source, 'w');
            $this->loggerPointers[] = $openRes;
        }
    }

    protected function unlinkAllOutput()
    {
        foreach ($this->loggerPointers as $sourceId => $source) {
            fclose($source);
            unset($this->loggerPointers[$sourceId]);
        }
    }

    protected function getOutput()
    {
        if (empty($this->loggerSources)) {
            return array('php://stdout');
        } else {
            return $this->loggerSources;
        }
    }

    public function registerOutput($filePath)
    {
        $this->loggerSources[] = $filePath;
        $this->unlinkAllOutput();
        $this->linkAllOutput();
    }

    protected function getFormatedMessage($message)
    {
        $vars            = $this->formatVars;
        $formatedMessage = $this->format;
        $vars['time']    = date('Y-m-d H:i:s');
        $vars['message'] = $message;
        $vars['ptime']   = bcsub(time(), $this->startTime);

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