<?php

declare (strict_types=1);
namespace Zxcvbn_Php;

/**
 * The main entry point.
 *
 * @see  zxcvbn/src/main.coffee
 */
class Zxcvbn
{
    /** @var \Zxcvbn_Php\Matcher Runs all registered matchers against a password */
    protected $matcher;
    /** @var \Zxcvbn_Php\Scorer Determines the minimum-guesses match sequence */
    protected $scorer;
    /** @var \Zxcvbn_Php\Time_Estimator Converts guesses to human-readable crack times */
    protected $time_estimator;
    /** @var \Zxcvbn_Php\Feedback Produces user-facing warning and suggestion strings */
    protected $feedback;

    public function __construct()
    {
        $this->matcher = new \Zxcvbn_Php\Matcher();
        $this->scorer = new \Zxcvbn_Php\Scorer();
        $this->time_estimator = new \Zxcvbn_Php\Time_Estimator();
        $this->feedback = new \Zxcvbn_Php\Feedback();
    }

    /**
     * Register a custom matcher class to run alongside the built-in matchers.
     *
     * The class must implement {@see \Zxcvbn_Php\Matchers\Match_Interface}.
     *
     * @param string $class_name Fully-qualified class name implementing Match_Interface
     *
     * @return self Fluent interface — allows chaining: (new Zxcvbn)->add_matcher(Foo::class)->password_strength(...)
     *
     * @throws \InvalidArgumentException if the class does not implement Match_Interface
     */
    public function add_matcher(string $class_name): self
    {
        $this->matcher->add_matcher($class_name);
        return $this;
    }
    /**
     * Calculate password strength via non-overlapping minimum entropy patterns.
     *
     * @param string $password   Password to measure
     * @param array  $userInputs Optional user inputs
     *
     * @return array Strength result array with keys:
     *               password
     *               entropy
     *               match_sequence
     *               score
     */
    public function password_strength(string $password, array $user_inputs = []): array
    {
        $time_start = microtime(true);
        $sanitized_inputs = array_map(function ($input) {
            return mb_strtolower((string) $input);
        }, $user_inputs);
        // Get matches for $password.
        // Although the coffeescript upstream sets $sanitizedInputs as a property,
        // doing this immutably makes more sense and is a bit easier
        $matches = $this->matcher->get_matches($password, $sanitized_inputs);
        $result = $this->scorer->get_most_guessable_match_sequence($password, $matches);
        $attack_times = $this->time_estimator->estimate_attack_times($result['guesses']);
        $feedback = $this->feedback->get_feedback($attack_times['score'], $result['sequence']);
        return array_merge($result, $attack_times, ['feedback' => $feedback, 'calc_time' => microtime(true) - $time_start]);
    }
}