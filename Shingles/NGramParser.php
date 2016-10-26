<?php
/**
 * Parse text to n-grams
 * User: lucky_beggar
 * Date: 26.10.16
 * Time: 18:50
 */
class Shingles_NGramParser
{
    protected $n;

    public function __construct($n, $logger = null)
    {
        $this->n = $n;
        if($logger !== null)
        {
            self::$logger = $logger;
        }
    }

    public static function log($message)
    {
        if(self::$logger !== null) {
            self::$logger->debug($message);
        }
    }

    /**
     * @var Common_Logger
     */
    public static $logger;

    public function parseText($text)
    {
        $patternClearBadChars = '/[^a-zA-Zа-яА-ЯёЁ0-9]/smi';
        $patternClearShortWords = '/(\s[a-zA-Zа-яА-ЯёЁ0-9]{0,3}\s)/smi';
        $patternClearDoubleSpaces = '/\s+/smi';
        $clearText = preg_replace($patternClearBadChars, ' ', $text);
        $clearText = preg_replace($patternClearShortWords, ' ', $clearText);
        $clearText = preg_replace($patternClearDoubleSpaces, ' ', $clearText);
        $wordList = explode(' ', $clearText);
        $offset = 0;
        $ngramList = array();
        do {
            $currentNgramWords = array_slice($wordList, $offset, $this->n);
            $currentNgram = implode(' ', $currentNgramWords);
//            self::log(print_r($currentNgram, 1));
            $ngramList[] = $currentNgram;
            $offset++;
        } while ($offset < (count($wordList) - $this->n));

        return $ngramList;
    }
}