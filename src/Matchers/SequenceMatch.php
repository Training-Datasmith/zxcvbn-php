<?php

declare (strict_types=1);
namespace Zxcvbn_Php\Matchers;

/** @phpstan-consistent-constructor */
class Sequence_Match extends Base_Match
{
    public const MAX_DELTA = 5;
    public $pattern = 'sequence';
    /** @var string The name of the detected sequence. */
    public $sequence_name;
    /** @var int The number of characters in the complete sequence space. */
    public $sequence_space;
    /** @var bool True if the sequence is ascending, and false if it is descending. */
    public $ascending;
    /**
     * Match sequences of three or more characters.
     *
     * @return SequenceMatch[]
     */
    public static function match(string $password, array $user_inputs = []): array
    {
        $matches = [];
        $password_length = mb_strlen($password);
        if ($password_length <= 1) {
            return [];
        }
        $begin = 0;
        $last_delta = null;
        for ($index = 1; $index < $password_length; $index++) {
            $delta = mb_ord(mb_substr($password, $index, 1)) - mb_ord(mb_substr($password, $index - 1, 1));
            if ($last_delta === null) {
                $last_delta = $delta;
            }
            if ($last_delta === $delta) {
                continue;
            }
            static::find_sequence_match($password, $begin, $index - 1, $last_delta, $matches);
            $begin = $index - 1;
            $last_delta = $delta;
        }
        static::find_sequence_match($password, $begin, $password_length - 1, $last_delta, $matches);
        return $matches;
    }
    public static function find_sequence_match(string $password, int $begin, int $end, int $delta, array &$matches): void
    {
        if ($end - $begin > 1 || abs($delta) === 1) {
            if (abs($delta) > 0 && abs($delta) <= self::MAX_DELTA) {
                $token = mb_substr($password, $begin, $end - $begin + 1);
                if (preg_match('/^[a-z]+$/u', $token)) {
                    $sequence_name = 'lower';
                    $sequence_space = 26;
                } elseif (preg_match('/^[A-Z]+$/u', $token)) {
                    $sequence_name = 'upper';
                    $sequence_space = 26;
                } elseif (preg_match('/^\d+$/u', $token)) {
                    $sequence_name = 'digits';
                    $sequence_space = 10;
                } else {
                    $sequence_name = 'unicode';
                    $sequence_space = 26;
                }
                $matches[] = new static($password, $begin, $end, $token, ['sequenceName' => $sequence_name, 'sequenceSpace' => $sequence_space, 'ascending' => $delta > 0]);
            }
        }
    }
    /**
     * @return array{'warning': string, "suggestions": string[]}
     */
    public function get_feedback(bool $is_sole_match): array
    {
        return ['warning' => 'Sequences like abc or 6543 are easy to guess', 'suggestions' => ['Avoid sequences']];
    }
    /**
     * @param array $params An array with keys: [sequenceName, sequenceSpace, ascending].
     */
    public function __construct(string $password, int $begin, int $end, string $token, array $params = [])
    {
        parent::__construct($password, $begin, $end, $token);
        if (!empty($params)) {
            $this->sequence_name = $params['sequenceName'] ?? '';
            $this->sequence_space = $params['sequenceSpace'] ?? 0;
            $this->ascending = $params['ascending'] ?? false;
        }
    }
    protected function get_raw_guesses(): float
    {
        $first_character = mb_substr($this->token, 0, 1);
        $guesses = 0;
        if (in_array($first_character, ['a', 'A', 'z', 'Z', '0', '1', '9'], true)) {
            $guesses += 4;
            // lower guesses for obvious starting points
        } elseif (ctype_digit($first_character)) {
            $guesses += 10;
            // digits
        } else {
            // could give a higher base for uppercase,
            // assigning 26 to both upper and lower sequences is more conservative
            $guesses += 26;
        }
        if (!$this->ascending) {
            // need to try a descending sequence in addition to every ascending sequence ->
            // 2x guesses
            $guesses *= 2;
        }
        return $guesses * mb_strlen($this->token);
    }
}