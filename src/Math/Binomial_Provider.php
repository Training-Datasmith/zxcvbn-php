<?php

declare (strict_types=1);
namespace Zxcvbn_Php\Math;

interface Binomial_Provider
{
    /**
     * Calculate binomial coefficient (n choose k).
     */
    public function binom(int $n, int $k): float;
}