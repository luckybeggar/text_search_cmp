<?php

/**
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 03.11.16
 * Time: 17:06
 */
class TSC_Hash_RK extends TSC_Hash
{
    /**
     * 2 bytes for store one char
     * @var int
     */
    public static $numBase = 0x100;

    protected $p, $baseMod;

    protected static $abc = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZАБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ ';

    protected $abcCharToOrd = array();

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
            case 'number_of_bytes':
                self::initByNumberOfBytes($config['hash_bytes']);
                break;
            default:
            case 'calculate':
                self::initSearch($config['hash_n_len'], $config['hash_m_len']);
                break;
        }

        if (!function_exists('gmp_strval')) {
            throw new Exception('GMP required. http://php.net/manual/en/gmp.installation.php');
        }
        $this->abcCharToOrd = array_flip(preg_split('//u', self::$abc, null, PREG_SPLIT_NO_EMPTY));
//        $word1 = 'МАМА';
//        $hash1 = $this->circleByteHash($word1);
//        self::log('DEBUG WORD1 :' . $word1);
//        self::log('DEBUG WORD1 HASH:' . $hash1);
//        $word2 = 'АМАМ';
//        $hash2 = $this->circleByteHash($word2);
//        self::log('DEBUG WORD2 :' . $word2);
//        self::log('DEBUG WORD2 COLD HASH:' . $hash2);
//        $hash22 = $this->circleByteHash($word2, $hash1, 'М');
//        self::log('DEBUG WORD2 HOT HASH:' . $hash22);
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
                $curHash       = $this->hashSubChar($prevSubstring, $curHash, $subChar);
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

    public function initByNumberOfBytes($nofBytes)
    {
        self::log('DEBUG Nof bytes: ' . $nofBytes);
        $maxP = gmp_strval(gmp_pow(2, $nofBytes * 8));
        self::log('DEBUG p: ' . $maxP);
        self::log('DEBUG p HEX: ' . base_convert($maxP, 10,16));
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
        self::log('DEBUG BASE MOD Hex: ' . base_convert($this->baseMod, 10, 16));
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
        self::log('DEBUG BASE MOD Hex: ' . base_convert($this->baseMod, 10, 16));
    }

    public function initByCurrentPrime($curPrime)
    {
        $this->baseMod = $curPrime;
        self::log('DEBUG BASE MOD: ' . $this->baseMod);
        self::log('DEBUG BASE MOD Hex: ' . base_convert($this->baseMod, 10, 16));
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

    public function hashByteAddChar($hash, $newChar)
    {
        $newOrd = $this->charToOrd($newChar);
        $hash   = gmp_mod(gmp_mul($hash, self::$numBase), $this->baseMod);
        $hash   = gmp_mod(gmp_add($hash, $newOrd), $this->baseMod);
        $hash   = gmp_strval($hash);

        return $hash;
    }

    public function hashByteSubChar($curString, $prevHash, $prevChar)
    {
        $prevOrd     = $this->charToOrd($prevChar);
        $len         = mb_strlen($curString);
        $positionMod = gmp_mod(pow(self::$numBase, $len - 1), $this->baseMod);
        $prevOrdSub  = gmp_mod(gmp_mul($prevOrd, $positionMod), $this->baseMod);
        $hash        = gmp_sub($prevHash, $prevOrdSub);

        return $hash;
    }

    public function circleByteHash($curString, $prevHash = null, $prevChar = null)
    {
        $len = mb_strlen($curString);
        if ($prevHash == null) {
            $hash = 0;
            for ($i = 0; $i < $len; $i++) {
                $curChar = mb_substr($curString, $i, 1);
                $hash    = $this->hashByteAddChar($hash, $curChar);
            }
        } elseif ($prevChar) {
            $hash    = $this->hashByteSubChar($curString, $prevHash, $prevChar);
            $newChar = mb_substr($curString, -1);
            $hash    = $this->hashByteAddChar($hash, $newChar);
        } else {
            throw new Exception ('No previous Char but Previous hash passed!');
        }

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

    protected function charToOrd($char)
    {
        if (!isset($this->abcCharToOrd[$char])) {
            return count($this->abcCharToOrd) + 1;
        }

        return $this->abcCharToOrd[$char];
    }

}