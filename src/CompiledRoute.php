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
 * CompiledRoutes are returned by the RouteCompilerInterface instance.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class CompiledRoute implements \Serializable
{
    /** @var string */
    private $pathRegex;

    /** @var string|string[]|null */
    private $hostRegexps;

    /** @var array */
    private $variables;

    /**
     * @param string          $pathRegex   The regular expression to use to match this route
     * @param string|string[] $hostRegexps A list of Host regexps else a combined single regex of hosts
     * @param array           $variables   An array of variables (variables defined in the path and in the host patterns)
     */
    public function __construct(string $pathRegex, $hostRegexps, array $variables)
    {
        $this->pathRegex = $pathRegex;
        $this->hostRegexps = $hostRegexps;
        $this->variables = $variables;
    }

    /**
     * @internal
     */
    final public function serialize(): string
    {
        return \serialize([
            'vars' => $this->variables,
            'path_regex' => $this->pathRegex,
            'host_regexps' => $this->hostRegexps,
        ]);
    }

    /**
     * @internal
     */
    final public function unserialize($data): void
    {
        $data = \unserialize($data, ['allowed_classes' => false]);

        $this->variables = $data['vars'];
        $this->hostRegexps = $data['host_regexps'];
        $this->pathRegex = $data['path_regex'];
    }

    /**
     * Returns the path regex with modifies.
     * Static path should begin wih a "/" while dynamic route begins with "\\/".
     */
    public function getPathRegex(): string
    {
        return $this->pathRegex;
    }

    /**
     * Returns the hosts regex with modifies.
     *
     * @return string[] The hosts regex
     */
    public function getHostsRegex(): array
    {
        return $this->hostRegexps;
    }

    /**
     * Returns the compiled variables.
     *
     * @return array<string,string|null>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }
}
