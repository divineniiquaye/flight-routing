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
    /** @var Route|null */
    private $route;

    /** @var string */
    private $name;

    /** @var bool */
    private $matched = false;

    /** @var array<string,float|int> */
    private $starts = [];

    /** @var array<string,float|int> */
    private $ends = [];

    /** @var DebugRoute[] */
    private $profiles = [];

    public function __construct(string $name = 'main', ?Route $route = null)
    {
        $this->route = $route;
        $this->name = $name;
        $this->enter();
    }

    /**
     * Add Matched info of route.
     */
    public function setMatched(string $name, Route $route): void
    {
        if ([] !== $this->profiles) {
            $this->profiles[$name] = $matched = new static($name, $route);
            $matched->matched = true;

            return;
        }

        if ($name === $this->name) {
            $this->matched = true;
        }
    }

    public function getRoute(): ?Route
    {
        return $this->route;
    }

    public function getName(): string
    {
        return $this->name;
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
     * Add a new profiled route.
     */
    public function addProfile(string $name, Route $route): void
    {
        if (!isset($this->profiles[$name])) {
            $this->profiles[$name] = new static($name, $route);
        }
    }

    /**
     * Returns the duration in microseconds.
     */
    public function getDuration(): float
    {
        if (!empty($this->profiles)) {
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
    public function enter(): void
    {
        $this->starts = [
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
