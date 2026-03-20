<?php

declare (strict_types=1);
namespace Zxcvbn_Php;

/**
 * Feedback - gives some user guidance based on the strength
 * of a password
 *
 * @see zxcvbn/src/time_estimates.coffee
 */
class Time_Estimator
{
    public function estimate_attack_times(float $guesses): array
    {
        $crack_times_seconds = ['online_throttling_100_per_hour' => $guesses / (100 / 3600), 'online_no_throttling_10_per_second' => $guesses / 10, 'offline_slow_hashing_1e4_per_second' => $guesses / 10000.0, 'offline_fast_hashing_1e10_per_second' => $guesses / 10000000000.0];
        $crack_times_display = array_map([$this, 'displayTime'], $crack_times_seconds);
        return ['crack_times_seconds' => $crack_times_seconds, 'crack_times_display' => $crack_times_display, 'score' => $this->guesses_to_score($guesses)];
    }
    protected function guesses_to_score(float $guesses): int
    {
        $DELTA = 5;
        if ($guesses < 1000.0 + $DELTA) {
            # risky password: "too guessable"
            return 0;
        }
        if ($guesses < 1000000.0 + $DELTA) {
            # modest protection from throttled online attacks: "very guessable"
            return 1;
        }
        if ($guesses < 100000000.0 + $DELTA) {
            # modest protection from unthrottled online attacks: "somewhat guessable"
            return 2;
        }
        if ($guesses < 10000000000.0 + $DELTA) {
            # modest protection from offline attacks: "safely unguessable"
            # assuming a salted, slow hash function like bcrypt, scrypt, PBKDF2, argon, etc
            return 3;
        }
        # strong protection from offline attacks under same scenario: "very unguessable"
        return 4;
    }
    protected function display_time(float $seconds): string
    {
        $callback = function (float $seconds): array {
            $minute = 60;
            $hour = $minute * 60;
            $day = $hour * 24;
            $month = $day * 31;
            $year = $month * 12;
            $century = $year * 100;
            if ($seconds < 1) {
                return [null, 'less than a second'];
            }
            if ($seconds < $minute) {
                $base = round($seconds);
                return [$base, "{$base} second"];
            }
            if ($seconds < $hour) {
                $base = round($seconds / $minute);
                return [$base, "{$base} minute"];
            }
            if ($seconds < $day) {
                $base = round($seconds / $hour);
                return [$base, "{$base} hour"];
            }
            if ($seconds < $month) {
                $base = round($seconds / $day);
                return [$base, "{$base} day"];
            }
            if ($seconds < $year) {
                $base = round($seconds / $month);
                return [$base, "{$base} month"];
            }
            if ($seconds < $century) {
                $base = round($seconds / $year);
                return [$base, "{$base} year"];
            }
            return [null, 'centuries'];
        };
        [$display_num, $display_str] = $callback($seconds);
        if ($display_num > 1) {
            $display_str .= 's';
        }
        return $display_str;
    }
}