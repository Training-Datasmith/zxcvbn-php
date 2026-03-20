<?php

declare (strict_types=1);
namespace Zxcvbn_Php\Matchers;

interface Match_Interface
{
    /**
     * Match this password.
     *
     * @param string $password   Password to check for match
     * @param array  $userInputs Array of values related to the user (optional)
     * @code array('Alice Smith')
     * @endcode
     *
     * @return array|BaseMatch[] Array of Match objects
     */
    public static function match(string $password, array $user_inputs = []): array;
    public function get_guesses(): float;
    public function get_guesses_log10(): float;
}