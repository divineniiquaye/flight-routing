<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 7.1 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flight\Routing;

/**
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class DebugRoute implements \IteratorAggregate
{
    /** @var bool */
    private $matched;

    /** @var Route|null */
    private $route = null;

    /** @var array<string,float|int> */
    private $starts = [];

    /** @var array<string,float|int> */
    private $ends = [];

    /** @var DebugRoute[] */
    private $profiles = [];

    /**
     * @param array<string,float|int>|null $previous of debugged starting point
     */
    public function __construct(?Route $route = null, bool $matched = false, ?array $previous = null)
    {
        if (null !== $route) {
            $this->route = $route;
        }

        $this->matched = $matched;
        $this->enter($previous);
    }

    /**
     * @param DebugRoute[] $profiles
     */
    public function populateProfiler(array $profiles): void
    {
        $this->profiles = $profiles;
    }

    /**
     * Add Matched info of route.
     *
     * @see addProfile() before using this method
     */
    public function setMatched(Route $route): void
    {
        $name = $route->get('name');

        if (isset($this->profiles[$name])) {
            $this->profiles[$name] = new static($route, true, $this->profiles[$name]->starts);
        }
    }

    public function getRoute(): ?Route
    {
        return $this->route;
    }

    public function isRoute(): bool
    {
        return $this->route instanceof Route;
    }

    public function isMatched(): bool
    {
        return $this->matched;
    }

    /**
     * Returns the duration in microseconds.
     */
    public function getDuration(): float
    {
        if ([] !== $this->profiles) {
            // for the root node with children, duration is the sum of all child durations
            $duration = 0;

            foreach ($this->profiles as $profile) {
                $duration += $profile->getDuration();
            }

            return $duration;
        }

        return isset($this->ends['wt']) && isset($this->starts['wt']) ? $this->ends['wt'] - $this->starts['wt'] : 0;
    }

    /**
     * Returns the memory usage in bytes.
     *
     * @return float|int
     */
    public function getMemoryUsage()
    {
        return isset($this->ends['mu']) && isset($this->starts['mu']) ? $this->ends['mu'] - $this->starts['mu'] : 0;
    }

    /**
     * Returns the peak memory usage in bytes.
     *
     * @return float|int
     */
    public function getPeakMemoryUsage()
    {
        return isset($this->ends['pmu']) && isset($this->starts['pmu']) ? $this->ends['pmu'] - $this->starts['pmu'] : 0;
    }

    /**
     * Starts the profiling.
     */
    public function enter(?array $previous = null): void
    {
        $this->starts = $previous ?? [
            'wt' => \microtime(true),
            'mu' => \memory_get_usage(),
            'pmu' => \memory_get_peak_usage(),
        ];
    }

    /**
     * Stops the profiling.
     */
    public function leave(): void
    {
        if ([] !== $this->profiles) {
            foreach ($this->profiles as $profile) {
                $profile->leave();
            }
        }

        $this->ends = [
            'wt' => \microtime(true),
            'mu' => \memory_get_usage(),
            'pmu' => \memory_get_peak_usage(),
        ];
    }

    public function reset(): void
    {
        $this->starts = $this->ends = $this->profiles = [];
        $this->enter();
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->profiles);
    }
}
