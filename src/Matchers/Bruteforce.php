<?php

declare (strict_types=1);
namespace Zxcvbn_Php\Matchers;

use Zxcvbn_Php\Scorer;
final class Bruteforce extends Base_Match
{
    public const BRUTEFORCE_CARDINALITY = 10;
    public $pattern = 'bruteforce';
    /**
     * @return Bruteforce[]
     */
    public static function match(string $password, array $user_inputs = []): array
    {
        // Matches entire string.
        $match = new static($password, 0, mb_strlen($password) - 1, $password);
        return [$match];
    }
    /**
     * @return array{'warning': string, "suggestions": string[]}
     */
    public function get_feedback(bool $is_sole_match): array
    {
        return ['warning' => '', 'suggestions' => []];
    }
    public function get_raw_guesses(): float
    {
        $guesses = self::BRUTEFORCE_CARDINALITY ** mb_strlen($this->token);
        if ($guesses === INF) {
            return PHP_FLOAT_MAX;
        }
        // small detail: make bruteforce matches at minimum one guess bigger than smallest allowed
        // submatch guesses, such that non-bruteforce submatches over the same [i..j] take precedence.
        if (mb_strlen($this->token) === 1) {
            $min_guesses = Scorer::MIN_SUBMATCH_GUESSES_SINGLE_CHAR + 1;
        } else {
            $min_guesses = Scorer::MIN_SUBMATCH_GUESSES_MULTI_CHAR + 1;
        }
        return max($guesses, $min_guesses);
    }
}