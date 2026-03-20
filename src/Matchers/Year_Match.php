<?php

declare (strict_types=1);
namespace Zxcvbn_Php\Matchers;

use Zxcvbn_Php\Matcher;
final class Year_Match extends Base_Match
{
    public const NUM_YEARS = 119;
    public $pattern = 'regex';
    public $regex_name = 'recent_year';
    /**
     * Match occurrences of years in a password
     *
     * @return YearMatch[]
     */
    public static function match(string $password, array $user_inputs = []): array
    {
        $matches = [];
        $groups = static::find_all($password, "/(19\\d\\d|20\\d\\d)/u");
        foreach ($groups as $captures) {
            $matches[] = new static($password, $captures[1]['begin'], $captures[1]['end'], $captures[1]['token']);
        }
        Matcher::usort_stable($matches, [Matcher::class, 'compareMatches']);
        return $matches;
    }
    /**
     * @return array{'warning': string, "suggestions": string[]}
     */
    public function get_feedback(bool $is_sole_match): array
    {
        return ['warning' => 'Recent years are easy to guess', 'suggestions' => ['Avoid recent years', 'Avoid years that are associated with you']];
    }
    protected function get_raw_guesses(): float
    {
        $year_space = abs($this->token - Date_Match::get_reference_year());
        return max($year_space, Date_Match::MIN_YEAR_SPACE);
    }
}