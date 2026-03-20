<?php

declare (strict_types=1);
namespace Zxcvbn_Php\Matchers;

use Zxcvbn_Php\Matcher;
use Zxcvbn_Php\Scorer;
/** @phpstan-consistent-constructor */
class Repeat_Match extends Base_Match
{
    public const GREEDY_MATCH = '/(.+)\1+/u';
    public const LAZY_MATCH = '/(.+?)\1+/u';
    public const ANCHORED_LAZY_MATCH = '/^(.+?)\1+$/u';
    public $pattern = 'repeat';
    /** @var MatchInterface[] An array of matches for the repeated section itself. */
    public $base_matches = [];
    /** @var int The number of guesses required for the repeated section itself. */
    public $base_guesses;
    /** @var int The number of times the repeated section is repeated. */
    public $repeat_count;
    /** @var string The string that was repeated in the token. */
    public $repeated_char;
    /**
     * Match 3 or more repeated characters.
     *
     * @return RepeatMatch[]
     */
    public static function match(string $password, array $user_inputs = []): array
    {
        $matches = [];
        $last_index = 0;
        while ($last_index < mb_strlen($password)) {
            $greedy_matches = self::find_all($password, self::GREEDY_MATCH, $last_index);
            $lazy_matches = self::find_all($password, self::LAZY_MATCH, $last_index);
            if (empty($greedy_matches)) {
                break;
            }
            if (mb_strlen($greedy_matches[0][0]['token']) > mb_strlen($lazy_matches[0][0]['token'])) {
                $match = $greedy_matches[0];
                preg_match(self::ANCHORED_LAZY_MATCH, $match[0]['token'], $anchored_match);
                $repeated_char = $anchored_match[1];
            } else {
                $match = $lazy_matches[0];
                $repeated_char = $match[1]['token'];
            }
            $scorer = new Scorer();
            $matcher = new Matcher();
            $base_analysis = $scorer->get_most_guessable_match_sequence($repeated_char, $matcher->get_matches($repeated_char));
            $base_matches = $base_analysis['sequence'];
            $base_guesses = $base_analysis['guesses'];
            $repeat_count = mb_strlen($match[0]['token']) / mb_strlen($repeated_char);
            $matches[] = new static($password, $match[0]['begin'], $match[0]['end'], $match[0]['token'], ['repeated_char' => $repeated_char, 'base_guesses' => $base_guesses, 'base_matches' => $base_matches, 'repeat_count' => $repeat_count]);
            $last_index = $match[0]['end'] + 1;
        }
        return $matches;
    }
    /**
     * @return array{'warning': string, "suggestions": string[]}
     */
    public function get_feedback(bool $is_sole_match): array
    {
        $warning = mb_strlen($this->repeated_char) == 1 ? 'Repeats like "aaa" are easy to guess' : 'Repeats like "abcabcabc" are only slightly harder to guess than "abc"';
        return ['warning' => $warning, 'suggestions' => ['Avoid repeated words and characters']];
    }
    /**
     * @param array $params An array with keys: [repeated_char, base_guesses, base_matches, repeat_count].
     */
    public function __construct(string $password, int $begin, int $end, string $token, array $params = [])
    {
        parent::__construct($password, $begin, $end, $token);
        if (!empty($params)) {
            $this->repeated_char = $params['repeated_char'] ?? '';
            $this->base_guesses = $params['base_guesses'] ?? 0;
            $this->base_matches = $params['base_matches'] ?? [];
            $this->repeat_count = $params['repeat_count'] ?? 0;
        }
    }
    protected function get_raw_guesses(): float
    {
        return $this->base_guesses * $this->repeat_count;
    }
}