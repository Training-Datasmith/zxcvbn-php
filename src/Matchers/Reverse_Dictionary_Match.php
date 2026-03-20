<?php

declare (strict_types=1);
namespace Zxcvbn_Php\Matchers;

use Zxcvbn_Php\Matcher;
class Reverse_Dictionary_Match extends Dictionary_Match
{
    /** @var bool Whether or not the matched word was reversed in the token. */
    public $reversed = true;
    /**
     * Match occurences of reversed dictionary words in password.
     *
     * @param $password
     * @return ReverseDictionaryMatch[]
     */
    public static function match(string $password, array $user_inputs = [], array $ranked_dictionaries = []): array
    {
        /** @var ReverseDictionaryMatch[] $matches */
        $matches = parent::match(self::mb_str_rev($password), $user_inputs, $ranked_dictionaries);
        foreach ($matches as $match) {
            $temp_begin = $match->begin;
            // Change the token, password and [begin, end] values to match the original password
            $match->token = self::mb_str_rev($match->token);
            $match->password = self::mb_str_rev($match->password);
            $match->begin = mb_strlen($password) - 1 - $match->end;
            $match->end = mb_strlen($password) - 1 - $temp_begin;
        }
        Matcher::usort_stable($matches, [Matcher::class, 'compareMatches']);
        return $matches;
    }
    protected function get_raw_guesses(): float
    {
        return parent::get_raw_guesses() * 2;
    }
    /**
     * @return array{'warning': string, "suggestions": string[]}
     */
    public function get_feedback(bool $is_sole_match): array
    {
        $feedback = parent::get_feedback($is_sole_match);
        if (mb_strlen($this->token) >= 4) {
            $feedback['suggestions'][] = "Reversed words aren't much harder to guess";
        }
        return $feedback;
    }
    public static function mb_str_rev(string $string, ?string $encoding = null): string
    {
        if ($encoding === null) {
            $encoding = mb_detect_encoding($string) ?: 'UTF-8';
        }
        $length = mb_strlen($string, $encoding);
        $reversed = '';
        while ($length-- > 0) {
            $reversed .= mb_substr($string, $length, 1, $encoding);
        }
        return $reversed;
    }
}