<?php

declare (strict_types=1);
namespace Zxcvbn_Php\Matchers;

use Zxcvbn_Php\Matcher;
use Zxcvbn_Php\Math\Binomial;
/** @phpstan-consistent-constructor */
class Dictionary_Match extends Base_Match
{
    public $pattern = 'dictionary';
    /** @var string The name of the dictionary that the token was found in. */
    public $dictionary_name;
    /** @var int The rank of the token in the dictionary. */
    public $rank;
    /** @var string The word that was matched from the dictionary. */
    public $matched_word;
    /** @var bool Whether or not the matched word was reversed in the token. */
    public $reversed = false;
    /** @var bool Whether or not the token contained l33t substitutions. */
    public $l33t = false;
    /** @var array A cache of the frequency_lists json file */
    protected static $ranked_dictionaries = [];
    protected const START_UPPER = '/^[A-Z][^A-Z]+$/u';
    protected const END_UPPER = '/^[^A-Z]+[A-Z]$/u';
    protected const ALL_UPPER = '/^[^a-z]+$/u';
    protected const ALL_LOWER = '/^[^A-Z]+$/u';
    /**
     * Match occurrences of dictionary words in password.
     *
     * @return DictionaryMatch[]
     */
    public static function match(string $password, array $user_inputs = [], array $ranked_dictionaries = []): array
    {
        $matches = [];
        if ($ranked_dictionaries) {
            $dicts = $ranked_dictionaries;
        } else {
            $dicts = static::get_ranked_dictionaries();
        }
        if (!empty($user_inputs)) {
            $dicts['user_inputs'] = [];
            foreach ($user_inputs as $rank => $input) {
                $input_lower = mb_strtolower($input);
                $dicts['user_inputs'][$input_lower] = $rank + 1;
                // rank starts at 1, not 0
            }
        }
        foreach ($dicts as $name => $dict) {
            $results = static::dictionary_match($password, $dict);
            foreach ($results as $result) {
                $result['dictionary_name'] = $name;
                $matches[] = new static($password, $result['begin'], $result['end'], $result['token'], $result);
            }
        }
        Matcher::usort_stable($matches, [Matcher::class, 'compareMatches']);
        return $matches;
    }
    /**
     * @param array $params An array with keys: [dictionary_name, matched_word, rank].
     */
    public function __construct(string $password, int $begin, int $end, string $token, array $params = [])
    {
        parent::__construct($password, $begin, $end, $token);
        if (!empty($params)) {
            $this->dictionary_name = $params['dictionary_name'] ?? '';
            $this->matched_word = $params['matched_word'] ?? '';
            $this->rank = $params['rank'] ?? 0;
        }
    }
    /**
     * @return array{'warning': string, "suggestions": string[]}
     */
    public function get_feedback(bool $is_sole_match): array
    {
        $start_upper = '/^[A-Z][^A-Z]+$/u';
        $all_upper = '/^[^a-z]+$/u';
        $feedback = ['warning' => $this->get_feedback_warning($is_sole_match), 'suggestions' => []];
        if (preg_match($start_upper, $this->token)) {
            $feedback['suggestions'][] = "Capitalization doesn't help very much";
        } elseif (preg_match($all_upper, $this->token) && mb_strtolower($this->token) != $this->token) {
            $feedback['suggestions'][] = 'All-uppercase is almost as easy to guess as all-lowercase';
        }
        return $feedback;
    }
    public function get_feedback_warning(bool $is_sole_match): string
    {
        switch ($this->dictionary_name) {
            case 'passwords':
                if ($is_sole_match && !$this->l33t && !$this->reversed) {
                    if ($this->rank <= 10) {
                        return 'This is a top-10 common password';
                    }
                    if ($this->rank <= 100) {
                        return 'This is a top-100 common password';
                    }
                    return 'This is a very common password';
                }
                if ($this->get_guesses_log10() <= 4) {
                    return 'This is similar to a commonly used password';
                }
                break;
            case 'english_wikipedia':
                if ($is_sole_match) {
                    return 'A word by itself is easy to guess';
                }
                break;
            case 'surnames':
            case 'male_names':
            case 'female_names':
                if ($is_sole_match) {
                    return 'Names and surnames by themselves are easy to guess';
                }
                return 'Common names and surnames are easy to guess';
        }
        return '';
    }
    /**
     * Attempts to find the provided password (as well as all possible substrings) in a dictionary.
     */
    protected static function dictionary_match(string $password, array $dict): array
    {
        $result = [];
        $length = mb_strlen($password);
        $pw_lower = mb_strtolower($password);
        foreach (range(0, $length - 1) as $i) {
            foreach (range($i, $length - 1) as $j) {
                $word = mb_substr($pw_lower, $i, $j - $i + 1);
                if (isset($dict[$word])) {
                    $result[] = ['begin' => $i, 'end' => $j, 'token' => mb_substr($password, $i, $j - $i + 1), 'matched_word' => $word, 'rank' => $dict[$word]];
                }
            }
        }
        return $result;
    }
    /**
     * Load ranked frequency dictionaries.
     */
    protected static function get_ranked_dictionaries(): array
    {
        if (empty(self::$ranked_dictionaries)) {
            $json = file_get_contents(__DIR__ . '/frequency_lists.json');
            $data = json_decode($json, true);
            $ranked_lists = [];
            foreach ($data as $name => $words) {
                $ranked_lists[$name] = array_combine($words, range(1, count($words)));
            }
            self::$ranked_dictionaries = $ranked_lists;
        }
        return self::$ranked_dictionaries;
    }
    protected function get_raw_guesses(): float
    {
        $guesses = $this->rank;
        return $guesses * $this->get_uppercase_variations();
    }
    protected function get_uppercase_variations(): float
    {
        $word = $this->token;
        if (preg_match(self::ALL_LOWER, $word) || mb_strtolower($word) === $word) {
            return 1;
        }
        // a capitalized word is the most common capitalization scheme,
        // so it only doubles the search space (uncapitalized + capitalized).
        // allcaps and end-capitalized are common enough too, underestimate as 2x factor to be safe.
        foreach ([self::START_UPPER, self::END_UPPER, self::ALL_UPPER] as $regex) {
            if (preg_match($regex, $word)) {
                return 2;
            }
        }
        // otherwise calculate the number of ways to capitalize U+L uppercase+lowercase letters
        // with U uppercase letters or less. or, if there's more uppercase than lower (for eg. PASSwORD),
        // the number of ways to lowercase U+L letters with L lowercase letters or less.
        $uppercase = count(array_filter(preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY), 'ctype_upper'));
        $lowercase = count(array_filter(preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY), 'ctype_lower'));
        $variations = 0;
        for ($i = 1; $i <= min($uppercase, $lowercase); $i++) {
            $variations += Binomial::binom($uppercase + $lowercase, $i);
        }
        return $variations;
    }
}