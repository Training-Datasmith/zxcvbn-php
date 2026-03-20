<?php

declare (strict_types=1);
namespace Zxcvbn_Php\Math;

use Zxcvbn_Php\Math\Impl\Binomial_Provider_Float64;
use Zxcvbn_Php\Math\Impl\Binomial_Provider_Int64;
use Zxcvbn_Php\Math\Impl\Binomial_Provider_Php73gmp;
class Binomial
{
    private static $provider;
    private function __construct()
    {
        throw new \LogicException(self::class . ' is static');
    }
    /**
     * Calculate binomial coefficient (n choose k).
     */
    public static function binom(int $n, int $k): float
    {
        return self::get_provider()->binom($n, $k);
    }
    public static function get_provider(): Binomial_Provider
    {
        if (self::$provider === null) {
            self::$provider = self::init_provider();
        }
        return self::$provider;
    }
    /**
     * @return string[]
     */
    public static function get_usable_provider_classes(): array
    {
        // In order of priority.  The first provider with a value of true will be used.
        $possible_provider_classes = [Binomial_Provider_Php73gmp::class => function_exists('gmp_binomial'), Binomial_Provider_Int64::class => PHP_INT_SIZE >= 8, Binomial_Provider_Float64::class => PHP_FLOAT_DIG >= 15];
        $possible_provider_classes = array_filter($possible_provider_classes);
        return array_keys($possible_provider_classes);
    }
    private static function init_provider(): Binomial_Provider
    {
        $provider_classes = self::get_usable_provider_classes();
        if (!$provider_classes) {
            throw new \LogicException('No valid providers');
        }
        $best_provider_class = reset($provider_classes);
        return new $best_provider_class();
    }
}