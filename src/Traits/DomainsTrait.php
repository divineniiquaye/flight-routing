<?php

declare(strict_types=1);

/*
 * This code is under BSD 3-Clause "New" or "Revised" License.
 *
 * PHP version 7 and above required
 *
 * @category  RoutingManager
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * @link      https://www.biurad.com/projects/routingmanager
 * @since     Version 0.1
 */

namespace Flight\Routing\Traits;

use Flight\Routing\Interfaces\RouteInterface;

trait DomainsTrait
{
    /**
     * HTTP schemes supported by this route.
     *
     * @var string[]|null
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
        if (preg_match('#(?:(https?):)?(//.*)#A', $domain ?? '', $matches)) {
            [, $scheme, $domain] = $matches;

            if (!empty($scheme)) {
                $this->addSchemes($scheme);
            }
        }
        $this->domain = trim($domain ?? '', '//');

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDomain(): string
    {
        return str_replace(['http://', 'https://'], '', $this->domain ?? '');
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

        $this->schemes = array_map('strtolower', (array) $schemes);

        return $this;
    }
}
