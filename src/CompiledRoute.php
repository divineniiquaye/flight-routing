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
class CompiledRoute implements \Serializable, \Stringable
{
    /** @var string */
    private $pathRegex;

    /** @var string|string[] */
    private $hostRegexps;

    /** @var array */
    private $variables;

    /** @var string|null */
    private $staticRoute;

    /**
     * @param string          $pathRegex   The regular expression to use to match this route
     * @param string|string[] $hostRegexps A list of Host regexps else a combined single regex of hosts
     * @param array           $variables   An array of variables (variables defined in the path and in the host patterns)
     */
    public function __construct(string $pathRegex, $hostRegexps, array $variables, string $static = null)
    {
        $this->pathRegex = $pathRegex;
        $this->hostRegexps = $hostRegexps;
        $this->variables = $variables;
        $this->staticRoute = $static;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        if (!empty($this->hostRegexps)) {
            $hostsRegex = '\/{2}' . (!\is_array($this->hostRegexps) ? $this->hostRegexps : \implode('|', $this->hostRegexps));
        }

        return '#^' . ($hostsRegex ?? '') . $this->pathRegex . '$#Ju';
    }

    /**
     * @internal
     */
    final public function serialize(): string
    {
        return \serialize([
            'vars' => $this->variables,
            'static' => $this->staticRoute,
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
        $this->staticRoute = $data['static'];
    }

    /**
     * Returns the path regex.
     */
    public function getPathRegex(): string
    {
        return $this->pathRegex;
    }

    /**
     * This method should return a combined array of hosts as
     * single string wrapped inside `(?|...)` and separated by `|`.
     *
     * If route was compiled reversely, return a array string of hosts.
     *
     * @return string|string[] The hosts regex
     */
    public function getHostsRegex()
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

    /**
     * If the path is static, return it else null.
     */
    public function getPath(): ?string
    {
        return $this->staticRoute;
    }
}
