<?php

declare (strict_types=1);
namespace Zxcvbn_Php\Math\Impl;

use TypeError;
class Binomial_Provider_Int64 extends Abstract_Binomial_Provider_With_Fallback
{
    protected function init_fallback_provider(): Abstract_Binomial_Provider
    {
        return new Binomial_Provider_Float64();
    }
    protected function try_calculate(int $n, int $k): ?float
    {
        try {
            $c = 1;
            for ($i = 1; $i <= $k; $i++, $n--) {
                // We're aiming for $c * $n / $i, but the $c * $n part could overflow, so use $c / $i * $n instead. The caveat here is that in
                // order to get a precise answer, we need to avoid floats, which means we need to deal with whole part and the remainder
                // separately.
                $c = intdiv($c, $i) * $n + intdiv($c % $i * $n, $i);
            }
            return (float) $c;
        } catch (TypeError $ex) {
            return null;
        }
    }
}