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

    /** @var string|string[] */
    private $hostRegexs;

    /** @var array */
    private $variables;

    /** @var string|null */
    private $staticRoute;

    /**
     * @param string   $pathRegex  The regular expression to use to match this route
     * @param string|string[] $hostRegexs A list of Host regexs else a combined single regex of hosts
     * @param array    $variables  An array of variables (variables defined in the path and in the host patterns)
     */
    public function __construct(string $pathRegex, $hostRegexs, array $variables, string $static = null)
    {
        $this->pathRegex = $pathRegex;
        $this->hostRegexs = $hostRegexs;
        $this->variables = $variables;
        $this->staticRoute = $static;
    }

    /**
     * @internal
     */
    final public function serialize(): string
    {
        return \serialize(['vars' => $this->variables, 'path_regex' => $this->pathRegex, 'host_regexs' => $this->hostRegexs]);
    }

    /**
     * @internal
     */
    final public function unserialize($data): void
    {
        $data = \unserialize($data, ['allowed_classes' => false]);

        $this->variables = $data['vars'];
        $this->hostRegexs = $data['host_regexs'];
        $this->pathRegex = $data['path_regex'];
    }

    /**
     * Returns the path regex.
     */
    public function getRegex(): string
    {
        return $this->pathRegex;
    }

    /**
     * Returns the hosts regex.
     *
     * @return string|string[] The hosts regex
     */
    public function getHostsRegex()
    {
        return $this->hostRegexs;
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

    /**
     * If the path is static, return it else null.
     */
    public function getStatic(): ?string
    {
        return $this->staticRoute;
    }
}
