<?php

/**
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 03.11.16
 * Time: 17:06
 */
class Hash_RK extends Hash
{
    /**
     * 2 bytes for store one char
     * @var int
     */
    public static $numBase = 0x10000;

    protected $p, $baseMod;

    /**
     * previous hash storing for speed up circle hash
     * @var int
     */
    protected $prevHash;


    /**
     * Hash_RK constructor.
     *
     * @param array         $config
     * @param PDO           $db
     * @param Common_Logger $logger
     *
     * @throws Exception
     */
    public function __construct($config, $db, $logger)
    {
        parent::__construct($config, $db, $logger);
        $initMode = $config['hash_init_mode'];
        switch ($initMode) {
            case 'ready_prime':
                self::initByCurrentPrime($config['hash_prime']);
                break;
            default:
            case 'calculate':
                self::initSearch($config['hash_n_len'], $config['hash_m_len']);
                break;
        }

        if (!function_exists('gmp_strval')) {
            throw new Exception('GMP required. http://php.net/manual/en/gmp.installation.php');
        }
    }

    public function getInsertSql($tableName)
    {
        return 'INSERT IGNORE INTO ' . $tableName .
        ' SET text_id = :text_id, shingle_text = :shingle_text,' .
        ' shingle_hash = :shingle_hash';
    }

    public function getHash($curSubstring, $prevSubstring)
    {
            if ($prevSubstring == null) {
                $curHash = $this->circleHash($curSubstring);
            } else {
//                self::log('DEBUG PREV SUBSTR: "' . $prevSubstring . '"');

                $curHash = $this->prevHash;
                $subWord = mb_substr($prevSubstring, 0, mb_strpos($prevSubstring, ' ') + 1);
//                self::log('SUBWORD: "' . $subWord . '"');
                for ($i = 0; $i < mb_strlen($subWord); $i++) {
                    $subChar = mb_substr($subWord, $i, 1);
//                    self::log('PS: "' . $prevSubstring . '"');
//                    self::log('SUB CHAR: "' . $subChar . '"');
                    $curHash    = $this->hashSubChar($prevSubstring, $curHash, $subChar);
                    $prevSubstring = mb_substr($prevSubstring, 1);
                }
                $addWord = mb_substr($curSubstring, mb_strrpos($curSubstring, ' '));
//                self::log('ADDWORD: "' . $addWord . '"');
                for ($i = 0; $i < mb_strlen($addWord); $i++) {
                    $addChar = mb_substr($addWord, $i, 1);
                    $curHash = $this->hashAddChar($curHash, $addChar);
//                    self::log('ADD CHAR: "' . $addChar . '"');
                }
                $curHash = gmp_strval($curHash);
            }
        $this->prevHash = $curHash;
//            self::log('for shingle "' . $shingle . '" hash is: ' . $curHash);
        return $curHash;
    }

    public function initSearch($needleLength, $haystackLength)
    {
        self::log('NEEDLE LENGTH: ' . $needleLength);
        self::log('HAYSTACK LENGTH: ' . $haystackLength);
        $maxP = gmp_strval(gmp_mul($needleLength, gmp_pow($haystackLength, 2)));
        self::log('DEBUG p: ' . $maxP);
        $curStart = $maxP;
        do {
            $randomSub = rand(gmp_strval(gmp_div($maxP, 32)), gmp_strval(gmp_div($maxP, 16)));
            $curStart  = gmp_strval(gmp_sub($curStart, $randomSub));
            $curPrime  = gmp_strval(gmp_nextprime($curStart));
            self::log('DEBUG CUR START: ' . $curStart);
            self::log('DEBUG CUR PRIME: ' . $curPrime);
        } while ($curPrime > $maxP);
        $this->baseMod = gmp_strval($curPrime);
        self::log('DEBUG BASE MOD: ' . $this->baseMod);
    }

    public function initByCurrentPrime($curPrime)
    {
        $this->baseMod = $curPrime;
        self::log('DEBUG BASE MOD: ' . $this->baseMod);
    }

    public function hashSubChar($curString, $prevHash, $prevChar)
    {
        $prevOrd     = self::unistr_to_ords($prevChar);
        $len         = mb_strlen($curString);
        $positionMod = gmp_mod(gmp_pow(self::$numBase, $len - 1), $this->baseMod);
        $prevOrdSub  = gmp_mod(gmp_mul($prevOrd, $positionMod), $this->baseMod);
        $hash        = gmp_sub($prevHash, $prevOrdSub);

        return $hash;
    }

    public function hashAddChar($hash, $newChar)
    {
        $newOrd = self::unistr_to_ords($newChar);
        $hash   = gmp_mod(gmp_mul($hash, self::$numBase), $this->baseMod);
        $hash   = gmp_mod(gmp_add($hash, $newOrd), $this->baseMod);
        $hash   = gmp_strval($hash);

        return $hash;
    }

    public function circleHash($curString, $prevHash = null, $prevChar = null)
    {
        if ($this->baseMod == null) {
            throw new Exception ('No base module defined!');
        }
        $len = mb_strlen($curString);
        if ($prevHash == null) {
            $curChar = mb_substr($curString, 0, 1);
            $hash    = gmp_strval(gmp_mod(self::unistr_to_ords($curChar), $this->baseMod));
            for ($i = 1; $i < $len; $i++) {
                $hash    = gmp_mod(gmp_mul($hash, self::$numBase), $this->baseMod);
                $curChar = mb_substr($curString, $i, 1);
                $curOrd  = self::unistr_to_ords($curChar);
                $hash    = gmp_strval($hash);
                $curOrd  = gmp_strval($curOrd);
                $hash    = gmp_mod(gmp_add($hash, $curOrd), $this->baseMod);
                $hash    = gmp_strval($hash);
            }
        } elseif ($prevChar) {
            $hash    = $this->hashSubChar($curString, $prevHash, $prevChar);
            $newChar = mb_substr($curString, -1);
            $hash    = $this->hashAddChar($hash, $newChar);
            $hash    = gmp_strval($hash);
        } else {
            throw new Exception ('No previous Char but Previous hash passed!');
        }

        return $hash;
    }

    /**
     * Turns an array of ordinal values into a string of unicode characters
     *
     * @param              $ordLists
     * @param string       $encoding
     *
     * @return string
     */
    static function ords_to_unistr($ordLists, $encoding = 'UTF-8')
    {
        $str = '';
        for ($i = 0; $i < sizeof($ordLists); $i++) {
            // Pack this number into a 4-byte string
            // (Or multiple one-byte strings, depending on context.)
            $v = $ordLists[$i];
            $str .= pack("N", $v);
        }
        $str = mb_convert_encoding($str, $encoding, "UCS-4BE");

        return ($str);
    }

    /**
     * Turns a string of unicode characters into an array of ordinal values,
     * Even if some of those characters are multibyte.
     *
     * @param        $str
     * @param string $encoding
     *
     * @return mixed
     */
    static function unistr_to_ords($str, $encoding = 'UTF-8')
    {
        $str  = mb_convert_encoding($str, "UCS-4BE", $encoding);
        $ords = array();

        // Visit each unicode character
        for ($i = 0; $i < mb_strlen($str, "UCS-4BE"); $i++) {
            // Now we have 4 bytes. Find their total
            // numeric value.
            $s2     = mb_substr($str, $i, 1, "UCS-4BE");
            $val    = unpack("N", $s2);
            $ords[] = $val[1];
        }

        return ($ords[0]);
    }

}