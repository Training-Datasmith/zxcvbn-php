<?php

declare (strict_types=1);
namespace Zxcvbn_Php\Math\Impl;

class Binomial_Provider_Php73gmp extends Abstract_Binomial_Provider
{
    /**
     * @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection
     * @noinspection PhpComposerExtensionStubsInspection
     */
    protected function calculate(int $n, int $k): float
    {
        return (float) gmp_strval(gmp_binomial($n, $k));
    }
}