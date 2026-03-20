<?php

declare(strict_types=1);

/**
 * Basic usage example for zxcvbn-php.
 *
 * Run: php examples/basic_usage.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Zxcvbn_Php\Zxcvbn;

$zxcvbn = new Zxcvbn();

// Test a weak password
$weak = $zxcvbn->password_strength('password123');
echo 'Password: password123' . PHP_EOL;
echo 'Score: ' . $weak['score'] . '/4' . PHP_EOL;
echo 'Guesses: ' . number_format($weak['guesses']) . PHP_EOL;
echo 'Crack time (online throttled): ' . $weak['crack_times_display']['online_throttling_100_per_hour'] . PHP_EOL;
if ($weak['feedback']['warning']) {
    echo 'Warning: ' . $weak['feedback']['warning'] . PHP_EOL;
}
echo PHP_EOL;

// Test a stronger password with user-context inputs
$result = $zxcvbn->password_strength('alice2023!', ['alice', 'alice smith']);
echo 'Password: alice2023!' . PHP_EOL;
echo 'Score: ' . $result['score'] . '/4' . PHP_EOL;
echo 'Suggestions:' . PHP_EOL;
foreach ($result['feedback']['suggestions'] as $suggestion) {
    echo '  - ' . $suggestion . PHP_EOL;
}
echo PHP_EOL;

// Test a strong passphrase
$strong = $zxcvbn->password_strength('correct-horse-battery-staple');
echo 'Password: correct-horse-battery-staple' . PHP_EOL;
echo 'Score: ' . $strong['score'] . '/4' . PHP_EOL;
echo 'Crack time (offline fast): ' . $strong['crack_times_display']['offline_fast_hashing_1e10_per_second'] . PHP_EOL;
