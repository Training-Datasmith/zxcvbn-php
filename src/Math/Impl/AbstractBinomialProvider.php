<?php

declare (strict_types=1);
namespace Zxcvbn_Php\Math\Impl;

use Zxcvbn_Php\Math\Binomial_Provider;
abstract class Abstract_Binomial_Provider implements Binomial_Provider
{
    public function binom(int $n, int $k): float
    {
        if ($k < 0 || $n < 0) {
            throw new \DomainException('n and k must be non-negative');
        }
        if ($k > $n) {
            return 0;
        }
        // $k and $n - $k will always produce the same value, so use smaller of the two
        $k = min($k, $n - $k);
        return $this->calculate($n, $k);
    }
    abstract protected function calculate(int $n, int $k): float;
}