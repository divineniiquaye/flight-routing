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

use Flight\Routing\Interfaces\RouteCompilerInterface;
use Flight\Routing\Interfaces\RouteMapInterface;

/**
 * This class is used to retrieve cached RouteCollection's data.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class CachedData implements RouteMapInterface
{
    /** @var RouteCompilerInterface */
    private $compiler;

    /** @var array */
    private $collectionData;

    public function __construct(array $collectionData)
    {
        [$this->compiler, $this->collectionData] = $collectionData;
    }

    /**
     * {@inheritdoc}
     */
    public function getCompiler(): RouteCompilerInterface
    {
        return $this->compiler;
    }

    /**
     * {@inheritdoc}
     */
    public function getData(): array
    {
        return $this->collectionData;
    }
}
