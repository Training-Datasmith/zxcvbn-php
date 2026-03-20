<?php

declare(strict_types=1);

/**
 * Custom matcher example for zxcvbn-php.
 *
 * Demonstrates registering a domain-specific matcher that penalises
 * passwords containing the company name.
 *
 * Run: php examples/custom_matcher.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Zxcvbn_Php\Matchers\Base_Match;
use Zxcvbn_Php\Matchers\Match_Interface;
use Zxcvbn_Php\Zxcvbn;

/**
 * Matches passwords that contain a brand/company name verbatim.
 */
class Brand_Name_Match extends Base_Match
{
    public string $pattern = 'brand_name';

    private static array $brand_names = ['acme', 'mycorp', 'example'];

    public static function match(string $password, array $user_inputs = []): array
    {
        $matches = [];
        $lower = mb_strtolower($password);

        foreach (self::$brand_names as $name) {
            $pos = mb_strpos($lower, $name);
            if ($pos !== false) {
                $end = $pos + mb_strlen($name) - 1;
                $matches[] = new self(
                    $password,
                    $pos,
                    $end,
                    mb_substr($password, $pos, mb_strlen($name))
                );
            }
        }

        return $matches;
    }

    public function get_feedback(bool $is_sole_match): array
    {
        return [
            'warning' => 'Avoid using your company or brand name in passwords.',
            'suggestions' => ['Choose a password unrelated to your organisation.'],
        ];
    }

    protected function get_raw_guesses(): float
    {
        // Brand names are trivially guessable — very low guesses count
        return 1.0;
    }
}

$zxcvbn = (new Zxcvbn())->add_matcher(Brand_Name_Match::class);

$result = $zxcvbn->password_strength('acme2024!');
echo 'Password: acme2024!' . PHP_EOL;
echo 'Score: ' . $result['score'] . '/4' . PHP_EOL;
echo 'Warning: ' . ($result['feedback']['warning'] ?: '(none)') . PHP_EOL;
