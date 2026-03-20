<?php

declare (strict_types=1);
namespace Zxcvbn_Php\Matchers;

use Zxcvbn_Php\Math\Binomial;
use Zxcvbn_Php\Scorer;
abstract class Base_Match implements Match_Interface
{
    /**
     * @var
     */
    public $password;
    /**
     * @var
     */
    public $begin;
    /**
     * @var
     */
    public $end;
    /**
     * @var
     */
    public $token;
    /**
     * @var
     */
    public $pattern;
    public function __construct(string $password, int $begin, int $end, string $token)
    {
        $this->password = $password;
        $this->begin = $begin;
        $this->end = $end;
        $this->token = $token;
    }
    /**
     * Get feedback to a user based on the match.
     *
     * @param  bool $isSoleMatch
     *   Whether this is the only match in the password
     * @return array{'warning': string, "suggestions": string[]}
     */
    abstract public function get_feedback(bool $is_sole_match): array;
    /**
     * Find all occurrences of regular expression in a string.
     *
     * @param string $string
     *   String to search.
     * @param string $regex
     *   Regular expression with captures.
     * @return array
     *   Array of capture groups. Captures in a group have named indexes: 'begin', 'end', 'token'.
     *     e.g. fishfish /(fish)/
     *     array(
     *       array(
     *         array('begin' => 0, 'end' => 3, 'token' => 'fish'),
     *         array('begin' => 0, 'end' => 3, 'token' => 'fish')
     *       ),
     *       array(
     *         array('begin' => 4, 'end' => 7, 'token' => 'fish'),
     *         array('begin' => 4, 'end' => 7, 'token' => 'fish')
     *       )
     *     )
     */
    public static function find_all(string $string, string $regex, int $offset = 0): array
    {
        // $offset is the number of multibyte-aware number of characters to offset, but the offset parameter for
        // preg_match_all counts bytes, not characters: to correct this, we need to calculate the byte offset and pass
        // that in instead.
        $chars_before_offset = mb_substr($string, 0, $offset);
        $byte_offset = strlen($chars_before_offset);
        $count = preg_match_all($regex, $string, $matches, PREG_SET_ORDER, $byte_offset);
        if (!$count) {
            return [];
        }
        $groups = [];
        foreach ($matches as $group) {
            $capture_begin = 0;
            $match = array_shift($group);
            $match_begin = mb_strpos($string, $match, $offset);
            $captures = [['begin' => $match_begin, 'end' => $match_begin + mb_strlen($match) - 1, 'token' => $match]];
            foreach ($group as $capture) {
                $capture_begin = mb_strpos($match, $capture, $capture_begin);
                $captures[] = ['begin' => $match_begin + $capture_begin, 'end' => $match_begin + $capture_begin + mb_strlen($capture) - 1, 'token' => $capture];
            }
            $groups[] = $captures;
            $offset += mb_strlen($match) - 1;
        }
        return $groups;
    }
    /**
     * Calculate binomial coefficient (n choose k).
     *
     * @deprecated Use {@see Binomial::binom()} instead
     */
    public static function binom(int $n, int $k): float
    {
        return Binomial::binom($n, $k);
    }
    abstract protected function get_raw_guesses(): float;
    public function get_guesses(): float
    {
        return max($this->get_raw_guesses(), $this->get_minimum_guesses());
    }
    protected function get_minimum_guesses(): float
    {
        if (mb_strlen($this->token) >= mb_strlen($this->password)) {
            return 0;
        }
        if (mb_strlen($this->token) === 1) {
            return Scorer::MIN_SUBMATCH_GUESSES_SINGLE_CHAR;
        }
        return Scorer::MIN_SUBMATCH_GUESSES_MULTI_CHAR;
    }
    public function get_guesses_log10(): float
    {
        return log10($this->get_guesses());
    }
}