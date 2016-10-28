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
        if ($logger !== null) {
            self::$logger = $logger;
        }
    }

    public static function log($message)
    {
        if (self::$logger !== null) {
            self::$logger->debug($message);
        }
    }

    /**
     * @var Common_Logger
     */
    public static $logger;

    public function parseText($text)
    {
        $patternClearBadChars     = '/[^a-zA-Zа-яА-ЯёЁ0-9]/sm';
        $patternClearDoubleSpaces = '/\s+/smi';
        $clearText                = preg_replace($patternClearBadChars, ' ', $text);
        $clearText                = ' ' . $clearText . ' ';
        $clearText                = preg_replace($patternClearDoubleSpaces, ' ', $clearText);
        $clearText                = trim($clearText);
        $clearText                = strtoupper($clearText);
        $wordList                 = explode(' ', $clearText);
        foreach ($wordList as $wordId => $word) {
            if (mb_strlen($word) < 4) {
                unset($wordList[$wordId]);
            }
        }
        $offset    = 0;
        $ngramList = array();
        do {
            $currentNgramWords = array_slice($wordList, $offset, $this->n);
            $currentNgram      = implode(' ', $currentNgramWords);
            $ngramList[]       = $currentNgram;
            $offset++;
        } while ($offset < (count($wordList) - $this->n));

        return $ngramList;
    }
}