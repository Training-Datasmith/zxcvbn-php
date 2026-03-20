<?php

declare (strict_types=1);
namespace Zxcvbn_Php;

use Zxcvbn_Php\Matchers\Base_Match;
use Zxcvbn_Php\Matchers\Match_Interface;
class Matcher
{
    private const DEFAULT_MATCHERS = [Matchers\Date_Match::class, Matchers\Dictionary_Match::class, Matchers\Reverse_Dictionary_Match::class, Matchers\L33t_Match::class, Matchers\Repeat_Match::class, Matchers\Sequence_Match::class, Matchers\Spatial_Match::class, Matchers\Year_Match::class];
    private $additional_matchers = [];
    /**
     * Get matches for a password.
     *
     * @param string $password  Password string to match
     * @param array $userInputs Array of values related to the user (optional)
     * @code array('Alice Smith')
     * @endcode
     *
     * @return MatchInterface[] Array of Match objects.
     *
     * @see  zxcvbn/src/matching.coffee::omnimatch
     */
    public function get_matches(string $password, array $user_inputs = []): array
    {
        $matches = [];
        foreach ($this->get_matchers() as $matcher) {
            $matched = $matcher::match($password, $user_inputs);
            if (is_array($matched) && !empty($matched)) {
                $matches[] = $matched;
            }
        }
        $matches = array_merge([], ...$matches);
        self::usort_stable($matches, [$this, 'compareMatches']);
        return $matches;
    }
    /**
     * Register an additional matcher class.
     *
     * @param string $class_name Fully-qualified class name that implements Match_Interface
     *
     * @return self Fluent interface for chaining
     *
     * @throws \InvalidArgumentException When the class does not implement Match_Interface
     */
    public function add_matcher(string $class_name): self
    {
        if (!is_a($class_name, Match_Interface::class, true)) {
            throw new \InvalidArgumentException(sprintf('Matcher class must implement %s', Match_Interface::class));
        }
        $this->additional_matchers[$class_name] = $class_name;
        return $this;
    }
    /**
     * A stable implementation of usort().
     *
     * Whether or not the sort() function in JavaScript is stable or not is implementation-defined.
     * This means it's impossible for us to match all browsers exactly, but since most browsers implement sort() using
     * a stable sorting algorithm, we'll get the highest rate of accuracy by using a stable sort in our code as well.
     *
     * This function taken from https://github.com/vanderlee/PHP-stable-sort-functions
     * Copyright © 2015-2018 Martijn van der Lee (http://martijn.vanderlee.com). MIT License applies.
     */
    public static function usort_stable(array &$array, callable $value_compare_func): bool
    {
        $index = 0;
        foreach ($array as &$item) {
            $item = [$index++, $item];
        }
        $result = usort($array, function (array $a, array $b) use ($value_compare_func) {
            $result = $value_compare_func($a[1], $b[1]);
            return $result == 0 ? $a[0] - $b[0] : $result;
        });
        foreach ($array as &$item) {
            $item = $item[1];
        }
        return $result;
    }
    /**
     * Compare two matches for stable sort ordering (by begin index, then end index).
     *
     * @param Base_Match $a First match to compare
     * @param Base_Match $b Second match to compare
     *
     * @return int Negative if $a comes first, positive if $b comes first, 0 if equal
     *
     * @complexity O(1)
     */
    public static function compare_matches(Base_Match $a, Base_Match $b): int
    {
        $begin_diff = $a->begin - $b->begin;
        if ($begin_diff) {
            return $begin_diff;
        }
        return $a->end - $b->end;
    }
    /**
     * Load available Match objects to match against a password.
     *
     * @return array Array of classes implementing MatchInterface
     */
    protected function get_matchers(): array
    {
        return array_merge(self::DEFAULT_MATCHERS, array_values($this->additional_matchers));
    }
}