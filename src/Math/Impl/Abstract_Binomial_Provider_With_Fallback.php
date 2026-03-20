<?php

declare (strict_types=1);
namespace Zxcvbn_Php\Math\Impl;

abstract class Abstract_Binomial_Provider_With_Fallback extends Abstract_Binomial_Provider
{
    /**
     * @var AbstractBinomialProvider|null
     */
    private $fallback;
    protected function calculate(int $n, int $k): float
    {
        return $this->try_calculate($n, $k) ?? $this->get_fallback_provider()->calculate($n, $k);
    }
    abstract protected function try_calculate(int $n, int $k): ?float;
    abstract protected function init_fallback_provider(): Abstract_Binomial_Provider;
    protected function get_fallback_provider(): Abstract_Binomial_Provider
    {
        if ($this->fallback === null) {
            $this->fallback = $this->init_fallback_provider();
        }
        return $this->fallback;
    }
}