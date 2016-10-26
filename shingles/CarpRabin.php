<?php

/**
 * Created by PhpStorm.
 * User: lucky_beggar
 * Date: 26.10.16
 * Time: 15:44
 */
class CarpRabin
{

    public static $numBase = 0x10000;

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

    public function __construct()
    {
        if (!function_exists('gmp_strval')) {
            throw new Exception('GMP required. http://php.net/manual/en/gmp.installation.php');
        }
    }

    public function circleHashMod($curString, $prevHash = null, $prevChar = null)
    {
        if ($this->baseMod == null) {
            throw new Exception ('No base module defined!');
        }
        $len = mb_strlen($curString);
        if ($prevHash == null) {
            $curChar = mb_substr($curString, 0, 1);
            $hash    = gmp_strval(gmp_mod(self::unistr_to_ords($curChar), $this->baseMod));
            for ($i = 1; $i < $len; $i++) {
                $hash      = gmp_mod(gmp_mul($hash, self::$numBase), $this->baseMod);
                $curChar   = mb_substr($curString, $i, 1);
                $curOrd    = self::unistr_to_ords($curChar);
                $curOrdHex = base_convert($curOrd, 10, 16);

                $hash    = gmp_strval($hash);
                $curOrd  = gmp_strval($curOrd);
                $hash    = gmp_mod(gmp_add($hash, $curOrd), $this->baseMod);
                $hash    = gmp_strval($hash);
                $hashHex = base_convert($hash, 10, 16);
                //echo("DEBUG i: $i curchar: $curChar curOrd: $curOrdHex hash: $hashHex \n");
            }
        } elseif ($prevChar) {
            $newChar = mb_substr($curString, -1);
            $newOrd  = self::unistr_to_ords($newChar);

            $prevOrd     = self::unistr_to_ords($prevChar);
            $positionMod = gmp_mod(gmp_pow(self::$numBase, $len - 1), $this->baseMod);
            $prevOrdSub  = gmp_mod(gmp_mul($prevOrd, $positionMod), $this->baseMod);
            $hash        = gmp_sub($prevHash, $prevOrdSub);
            $hash        = gmp_mod(gmp_mul($hash, self::$numBase), $this->baseMod);
            $hash        = gmp_mod(gmp_add($hash, $newOrd), $this->baseMod);
            $hash        = gmp_strval($hash);
        } else {
            throw new Exception ('No previous Char but Previous hash passed!');
        }

        return $hash;
    }

    public $needle, $haystack, $n, $m, $p, $baseMod;

    public function initSearch($needle, $haystack)
    {
        $this->needle   = $needle;
        $this->haystack = $haystack;
        $n              = mb_strlen($this->needle);
        echo('DEBUG N: ' . $n . "\n");

        $m = mb_strlen($this->haystack);
        echo('DEBUG M: ' . $m . "\n");
        $this->p = gmp_strval(gmp_mul($n, gmp_pow($m, 2)));
        echo('DEBUG p: ' . $this->p . "\n");

        $randomDivPart = rand(7, 99);
        echo('DEBUG DIV PART: ' . $randomDivPart . "\n");
        $randomIndPart = rand(7, $randomDivPart);
        echo('DEBUG IND PART: ' . $randomIndPart . "\n");
        $this->baseMod = gmp_strval(gmp_nextprime(gmp_mul(gmp_div_q($this->p, $randomDivPart), $randomIndPart)));
        echo('DEBUG BASE MOD: ' . $this->baseMod . "\n");
    }
}

mb_internal_encoding('UTF-8');

$cr     = new CarpRabin();
$hs     = 'Магистерская диссертация является завершающим этапом в единой системе теоретической и практической подготовки магистрантов в рамках магистерской программы 01040001 «Математическое и информационное обеспечение экономической деятельности».';
$needle = '01040001';

$cr->initSearch($needle, $hs);
//$hash1 = $cr->circleHashMod($needle);
//echo("DEBUG cold hash of $needle is: ". $hash1. "\n");

//$needle = 'bcdefg';
//$hash2 = $cr->circleHashMod($needle, $hash1, 'a');
//echo("DEBUG hot hash of $needle is: ". $hash2. "\n");


//$needle = 'bcdefg';
//$hash3 = $cr->circleHashMod($needle);
//echo("DEBUG cold hash of $needle is: ". $hash3. "\n");
echo('DEBUG needle is: ' . $needle . "\n");
$needleHash = $cr->circleHashMod($needle);

$ln = mb_strlen($needle);
for ($i = 0; $i < (mb_strlen($hs) - $ln + 1); $i++) {
    $frag = mb_substr($hs, $i, $ln);
    echo('DEBUG frag is: ' . $frag . "\n");
    if (isset($tHash)) {
        $tHash = $cr->circleHashMod($frag, $tHash, $f1);
        echo("DEBUG hot hash from old hash: $tHash frag: $frag and prev char: $f1 is:  $tHash \n");
    } else {
        $tHash = $cr->circleHashMod($frag);
        echo("DEBUG cold hash from frag $frag is: $tHash\n");
    }
    if ((int)$tHash == (int)$needleHash) {
        echo("$tHash equals $needleHash FOUND!! \n");
        break;
    } else {
        echo("$tHash not equals $needleHash \n");
    }
    $f1 = mb_substr($frag, 0, 1);
    echo('DEBUG f1 is: ' . $f1 . "\n");
}



//$hash1 = CarpRabin::circleHash('abcde');
//echo('DEBUG cold hash of abcde is: '. $hash1. "\n");
//
//echo('DEBUG cold hash of bcdef is: '. CarpRabin::circleHash('bcdef'). "\n");
//
//echo('DEBUG hash of bcdef, based abcde is: '. CarpRabin::circleHash('bcdef', $hash1). "\n");