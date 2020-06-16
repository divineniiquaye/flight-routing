<?php

declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 7.2 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flight\Routing\Traits;

use Flight\Routing\Interfaces\RouteInterface;

trait DomainsTrait
{
    /**
     * HTTP schemes supported by this route.
     *
     * @var null|string[]
     */
    protected $schemes;

    /**
     * Route domain.
     *
     * @var string
     */
    protected $domain;

    /**
     * {@inheritdoc}
     */
    public function addDomain(?string $domain): RouteInterface
    {
        if (\preg_match('#(?:(https?):)?(//.*)#A', $domain ?? '', $matches)) {
            [, $scheme, $domain] = $matches;

            if (!empty($scheme)) {
                $this->addSchemes($scheme);
            }
        }
        $this->domain = \trim($domain ?? '', '//');

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDomain(): string
    {
        return \str_replace(['http://', 'https://'], '', $this->domain ?? '');
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemes(): ?array
    {
        return $this->schemes;
    }

    /**
     * {@inheritdoc}
     */
    public function addSchemes($schemes): RouteInterface
    {
        if (null === $schemes) {
            return $this;
        }

        $this->schemes = \array_map('strtolower', (array) $schemes);

        return $this;
    }
}
