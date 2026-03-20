<?php

declare (strict_types=1);
namespace Zxcvbn_Php\Matchers;

use Zxcvbn_Php\Matcher;
use Zxcvbn_Php\Math\Binomial;
/** @phpstan-consistent-constructor */
class Spatial_Match extends Base_Match
{
    public const SHIFTED_CHARACTERS = '~!@#$%^&*()_+QWERTYUIOP{}|ASDFGHJKL:"ZXCVBNM<>?';
    // Preset properties since adjacency graph is constant for qwerty keyboard and keypad.
    public const KEYBOARD_STARTING_POSITION = 94;
    public const KEYPAD_STARTING_POSITION = 15;
    public const KEYBOARD_AVERAGE_DEGREES = 4.5957446809;
    // 432 / 94
    public const KEYPAD_AVERAGE_DEGREES = 5.0666666667;
    // 76 / 15
    public $pattern = 'spatial';
    /** @var int The number of characters the shift key was held for in the token. */
    public $shifted_count;
    /** @var int The number of turns on the keyboard required to complete the token. */
    public $turns;
    /** @var string The keyboard layout that the token is a spatial match on. */
    public $graph;
    /** @var array A cache of the adjacency_graphs json file */
    protected static $adjacency_graphs = [];
    /**
     * Match spatial patterns based on keyboard layouts (e.g. qwerty, dvorak, keypad).
     *
     * @return SpatialMatch[]
     */
    public static function match(string $password, array $user_inputs = [], array $graphs = []): array
    {
        $matches = [];
        if (!$graphs) {
            $graphs = static::get_adjacency_graphs();
        }
        foreach ($graphs as $name => $graph) {
            $results = static::graph_match($password, $graph, $name);
            foreach ($results as $result) {
                $result['graph'] = $name;
                $matches[] = new static($password, $result['begin'], $result['end'], $result['token'], $result);
            }
        }
        Matcher::usort_stable($matches, [Matcher::class, 'compareMatches']);
        return $matches;
    }
    /**
     * @return array{'warning': string, "suggestions": string[]}
     */
    public function get_feedback(bool $is_sole_match): array
    {
        $warning = $this->turns == 1 ? 'Straight rows of keys are easy to guess' : 'Short keyboard patterns are easy to guess';
        return ['warning' => $warning, 'suggestions' => ['Use a longer keyboard pattern with more turns']];
    }
    /**
     * @param array $params An array with keys: [graph (required), shifted_count, turns].
     */
    public function __construct(string $password, int $begin, int $end, string $token, array $params = [])
    {
        parent::__construct($password, $begin, $end, $token);
        $this->graph = $params['graph'];
        if (!empty($params)) {
            $this->shifted_count = $params['shifted_count'] ?? null;
            $this->turns = $params['turns'] ?? null;
        }
    }
    /**
     * Match spatial patterns in a adjacency graph.
     */
    protected static function graph_match(string $password, array $graph, string $graph_name): array
    {
        $result = [];
        $i = 0;
        $password_length = mb_strlen($password);
        while ($i < $password_length - 1) {
            $j = $i + 1;
            $last_direction = null;
            $turns = 0;
            $shifted_count = 0;
            // Check if the initial character is shifted
            if ($graph_name === 'qwerty' || $graph_name === 'dvorak') {
                if (mb_strpos(self::SHIFTED_CHARACTERS, mb_substr($password, $i, 1)) !== false) {
                    $shifted_count++;
                }
            }
            while (true) {
                $prev_char = mb_substr($password, $j - 1, 1);
                $found = false;
                $cur_direction = -1;
                $adjacents = $graph[$prev_char] ?? [];
                // Consider growing pattern by one character if j hasn't gone over the edge.
                if ($j < $password_length) {
                    $cur_char = mb_substr($password, $j, 1);
                    foreach ($adjacents as $adj) {
                        $cur_direction += 1;
                        if ($adj === null) {
                            continue;
                        }
                        $cur_char_pos = static::index_of($adj, $cur_char);
                        if ($cur_char_pos !== -1) {
                            $found = true;
                            $found_direction = $cur_direction;
                            if ($cur_char_pos === 1) {
                                // index 1 in the adjacency means the key is shifted, 0 means unshifted: A vs a, % vs 5, etc.
                                // for example, 'q' is adjacent to the entry '2@'. @ is shifted w/ index 1, 2 is unshifted.
                                $shifted_count += 1;
                            }
                            if ($last_direction !== $found_direction) {
                                // adding a turn is correct even in the initial case when last_direction is null:
                                // every spatial pattern starts with a turn.
                                $turns += 1;
                                $last_direction = $found_direction;
                            }
                            break;
                        }
                    }
                }
                // if the current pattern continued, extend j and try to grow again
                if ($found) {
                    $j += 1;
                } else {
                    // otherwise push the pattern discovered so far, if any...
                    // Ignore length 1 or 2 chains.
                    if ($j - $i > 2) {
                        $result[] = ['begin' => $i, 'end' => $j - 1, 'token' => mb_substr($password, $i, $j - $i), 'turns' => $turns, 'shifted_count' => $shifted_count];
                    }
                    // ...and then start a new search for the rest of the password.
                    $i = $j;
                    break;
                }
            }
        }
        return $result;
    }
    /**
     * Get the index of a string a character first
     *
     *
     */
    protected static function index_of(string $string, string $char): int
    {
        $pos = mb_strpos($string, $char);
        return $pos === false ? -1 : $pos;
    }
    /**
     * Load adjacency graphs.
     */
    public static function get_adjacency_graphs(): array
    {
        if (empty(self::$adjacency_graphs)) {
            $json = file_get_contents(__DIR__ . '/adjacency_graphs.json');
            $data = json_decode($json, true);
            // This seems pointless, but the data file is not guaranteed to be in any particular order.
            // We want to be in the exact order below so as to match most closely with upstream, because when a match
            // can be found in multiple graphs (such as 789), the one that's listed first is that one that will be picked.
            $data = ['qwerty' => $data['qwerty'], 'dvorak' => $data['dvorak'], 'keypad' => $data['keypad'], 'mac_keypad' => $data['mac_keypad']];
            self::$adjacency_graphs = $data;
        }
        return self::$adjacency_graphs;
    }
    protected function get_raw_guesses(): float
    {
        if ($this->graph === 'qwerty' || $this->graph === 'dvorak') {
            $starting_position = self::KEYBOARD_STARTING_POSITION;
            $average_degree = self::KEYBOARD_AVERAGE_DEGREES;
        } else {
            $starting_position = self::KEYPAD_STARTING_POSITION;
            $average_degree = self::KEYPAD_AVERAGE_DEGREES;
        }
        $guesses = 0;
        $length = mb_strlen($this->token);
        $turns = $this->turns;
        // estimate the number of possible patterns w/ length L or less with t turns or less.
        for ($i = 2; $i <= $length; $i++) {
            $possible_turns = min($turns, $i - 1);
            for ($j = 1; $j <= $possible_turns; $j++) {
                $guesses += Binomial::binom($i - 1, $j - 1) * $starting_position * $average_degree ** $j;
            }
        }
        // add extra guesses for shifted keys. (% instead of 5, A instead of a.)
        // math is similar to extra guesses of l33t substitutions in dictionary matches.
        if ($this->shifted_count > 0) {
            $shifted = $this->shifted_count;
            $unshifted = $length - $shifted;
            if ($unshifted === 0) {
                $guesses *= 2;
            } else {
                $variations = 0;
                for ($i = 1; $i <= min($shifted, $unshifted); $i++) {
                    $variations += Binomial::binom($shifted + $unshifted, $i);
                }
                $guesses *= $variations;
            }
        }
        return $guesses;
    }
}