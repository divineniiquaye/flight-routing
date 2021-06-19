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

namespace Flight\Routing\Exceptions;

use Flight\Routing\Interfaces\ExceptionInterface;

/**
 * HTTP 405 exception.
 */
class MethodNotAllowedException extends \RuntimeException implements ExceptionInterface
{
    /** @var string[] */
    private $methods;

    /**
     * @param string[] $methods
     */
    public function __construct(array $methods, string $path, string $method)
    {
        $this->methods = $methods;
        $message = 'Unfortunately current uri "%s" is allowed on [%s] request methods, "%s" is invalid.';

        parent::__construct(\sprintf($message, $path, \implode(',', $methods), $method), 405);
    }

    /**
     * Gets allowed methods.
     *
     * @return string[]
     */
    public function getAllowedMethods(): array
    {
        return $this->methods;
    }
}
