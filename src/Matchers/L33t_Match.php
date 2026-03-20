<?php

declare (strict_types=1);
namespace Zxcvbn_Php\Matchers;

use Zxcvbn_Php\Matcher;
use Zxcvbn_Php\Math\Binomial;
/**
 * Class L33tMatch extends DictionaryMatch to translate l33t into dictionary words for matching.
 * @package ZxcvbnPhp\Matchers
 */
class L33t_Match extends Dictionary_Match
{
    /** @var array An array of substitutions made to get from the token to the dictionary word. */
    public $sub = [];
    /** @var string A user-readable string that shows which substitutions were detected. */
    public $sub_display;
    /** @var bool Whether or not the token contained l33t substitutions. */
    public $l33t = true;
    /**
     * Match occurences of l33t words in password to dictionary words.
     *
     * @return L33tMatch[]
     */
    public static function match(string $password, array $user_inputs = [], array $ranked_dictionaries = []): array
    {
        // Translate l33t password and dictionary match the translated password.
        $maps = array_filter(static::get_l33t_substitutions(static::get_l33t_subtable($password)));
        if (empty($maps)) {
            return [];
        }
        $matches = [];
        if (!$ranked_dictionaries) {
            $ranked_dictionaries = static::get_ranked_dictionaries();
        }
        foreach ($maps as $map) {
            $translated_word = static::translate($password, $map);
            /** @var L33tMatch[] $results */
            $results = parent::match($translated_word, $user_inputs, $ranked_dictionaries);
            foreach ($results as $match) {
                $token = mb_substr($password, $match->begin, $match->end - $match->begin + 1);
                # only return the matches that contain an actual substitution
                if (mb_strtolower($token) === $match->matched_word) {
                    continue;
                }
                # filter single-character l33t matches to reduce noise.
                # otherwise '1' matches 'i', '4' matches 'a', both very common English words
                # with low dictionary rank.
                if (mb_strlen($token) === 1) {
                    continue;
                }
                $display = [];
                foreach ($map as $i => $t) {
                    if (mb_strpos($token, (string) $i) !== false) {
                        $match->sub[$i] = $t;
                        $display[] = "{$i} -> {$t}";
                    }
                }
                $match->token = $token;
                $match->sub_display = implode(', ', $display);
                $matches[] = $match;
            }
        }
        Matcher::usort_stable($matches, [Matcher::class, 'compareMatches']);
        return $matches;
    }
    /**
     * @param array $params An array with keys: [sub, sub_display].
     */
    public function __construct(string $password, int $begin, int $end, string $token, array $params = [])
    {
        parent::__construct($password, $begin, $end, $token, $params);
        if (!empty($params)) {
            $this->sub = $params['sub'] ?? [];
            $this->sub_display = $params['sub_display'] ?? null;
        }
    }
    /**
     * @return array{'warning': string, "suggestions": string[]}
     */
    public function get_feedback(bool $is_sole_match): array
    {
        $feedback = parent::get_feedback($is_sole_match);
        $feedback['suggestions'][] = "Predictable substitutions like '@' instead of 'a' don't help very much";
        return $feedback;
    }
    protected static function translate(string $string, array $map): string
    {
        return str_replace(array_keys($map), array_values($map), $string);
    }
    protected static function get_l33t_table(): array
    {
        return ['a' => ['4', '@'], 'b' => ['8'], 'c' => ['(', '{', '[', '<'], 'e' => ['3'], 'g' => ['6', '9'], 'i' => ['1', '!', '|'], 'l' => ['1', '|', '7'], 'o' => ['0'], 's' => ['$', '5'], 't' => ['+', '7'], 'x' => ['%'], 'z' => ['2']];
    }
    protected static function get_l33t_subtable(string $password): array
    {
        // The preg_split call below is a multibyte compatible version of str_split
        $password_chars = array_unique(preg_split('//u', $password, -1, PREG_SPLIT_NO_EMPTY));
        $sub_table = [];
        $table = static::get_l33t_table();
        foreach ($table as $letter => $substitutions) {
            foreach ($substitutions as $sub) {
                if (in_array($sub, $password_chars)) {
                    $sub_table[$letter][] = $sub;
                }
            }
        }
        return $sub_table;
    }
    protected static function get_l33t_substitutions(array $subtable): array
    {
        $keys = array_keys($subtable);
        $substitutions = self::substitution_table_helper($subtable, $keys, [[]]);
        // Converts the substitution arrays from [ [a, b], [c, d] ] to [ a => b, c => d ]
        $substitutions = array_map(function (array $sub_array): array {
            return array_combine(array_column($sub_array, 0), array_column($sub_array, 1));
        }, $substitutions);
        return $substitutions;
    }
    protected static function substitution_table_helper(array $table, array $keys, array $subs): array
    {
        if (empty($keys)) {
            return $subs;
        }
        $first_key = array_shift($keys);
        $other_keys = $keys;
        $next_subs = [];
        foreach ($table[$first_key] as $l33t_character) {
            foreach ($subs as $sub) {
                $dup_l33t_index = false;
                foreach ($sub as $index => $char) {
                    if ($char[0] === $l33t_character) {
                        $dup_l33t_index = $index;
                        break;
                    }
                }
                if ($dup_l33t_index === false) {
                    $sub_extension = $sub;
                    $sub_extension[] = [$l33t_character, $first_key];
                    $next_subs[] = $sub_extension;
                } else {
                    $sub_alternative = $sub;
                    array_splice($sub_alternative, $dup_l33t_index, 1);
                    $sub_alternative[] = [$l33t_character, $first_key];
                    $next_subs[] = $sub;
                    $next_subs[] = $sub_alternative;
                }
            }
        }
        $next_subs = array_unique($next_subs, SORT_REGULAR);
        return self::substitution_table_helper($table, $other_keys, $next_subs);
    }
    protected function get_raw_guesses(): float
    {
        return parent::get_raw_guesses() * $this->get_l33t_variations();
    }
    protected function get_l33t_variations(): float
    {
        $variations = 1;
        foreach ($this->sub as $substitution => $letter) {
            $characters = preg_split('//u', mb_strtolower($this->token), -1, PREG_SPLIT_NO_EMPTY);
            $subbed = count(array_filter($characters, function ($character) use ($substitution): bool {
                return (string) $character === (string) $substitution;
            }));
            $unsubbed = count(array_filter($characters, function ($character) use ($letter): bool {
                return (string) $character === (string) $letter;
            }));
            if ($subbed === 0 || $unsubbed === 0) {
                // for this sub, password is either fully subbed (444) or fully unsubbed (aaa)
                // treat that as doubling the space (attacker needs to try fully subbed chars in addition to
                // unsubbed.)
                $variations *= 2;
            } else {
                $possibilities = 0;
                for ($i = 1; $i <= min($subbed, $unsubbed); $i++) {
                    $possibilities += Binomial::binom($subbed + $unsubbed, $i);
                }
                $variations *= $possibilities;
            }
        }
        return $variations;
    }
}