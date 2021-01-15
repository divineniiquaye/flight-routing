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

namespace Flight\Routing\Traits;

use Closure;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\Matchers\SimpleRouteMatcher;
use Flight\Routing\Route;
use Flight\Routing\RouteList;

/**
 * @codeCoverageIgnore
 */
trait DumperTrait
{
    /** @var array<string,RouteInterface> */
    private $cachedRoutes = [];

    /** @var mixed */
    private $compiledRoutes;

    /**
     * Get the compiled routes after warmpRoutes
     *
     * @return mixed
     */
    public function getCompiledRoutes()
    {
        return $this->compiledRoutes;
    }

    /**
     * Warm up routes to speed up routes handling.
     *
     * @param string $cacheFile
     * @param bool   $dump
     */
    public function warmRoutes(string $cacheFile, bool $dump = true): void
    {
        $cachedRoutes = \is_file($cacheFile) ? require $cacheFile : [[], []];

        if (!$dump || !empty(\current($cachedRoutes))) {
            list($this->compiledRoutes, $this->cachedRoutes) = $cachedRoutes;

            return;
        }

        $generatedCode = <<<EOF
<?php

/**
 * This file has been auto-generated
 * by the Flight Routing.
 */

return [
{$this->generateCompiledRoutes()}];

EOF;

        \file_put_contents($cacheFile, $generatedCode);
    }

    /**
     * @internal
     *
     * @param mixed $value
     */
    protected static function export($value): string
    {
        if (null === $value) {
            return 'null';
        }

        if (!\is_array($value)) {
            if ($value instanceof RouteInterface) {
                return self::exportRoute($value);
            }

            return \str_replace("\n", '\'."\n".\'', \var_export($value, true));
        }

        if (!$value) {
            return '[]';
        }

        $i      = 0;
        $export = '[';

        foreach ($value as $k => $v) {
            if ($i === $k) {
                ++$i;
            } else {
                $export .= self::export($k) . ' => ';

                if (\is_int($k) && $i < $k) {
                    $i = 1 + $k;
                }
            }

            if (\is_string($v) && 0 === \strpos($v, 'unserialize')) {
                $v = '\\' . $v . ', ';
            } elseif ($v instanceof RouteInterface) {
                $v .= self::exportRoute($v);
            } else {
                $v = self::export($v) . ', ';
            }

            $export .= $v;
        }

        return \substr_replace($export, ']', -2);
    }

    /**
     * @return string
     */
    protected function generateCompiledRoutes(): string
    {
        $collection = new RouteList();
        $collection->addForeach(...$this->getRoutes());

        $compiledRoutes = $this->matcher->warmCompiler($collection);

        $code = '[ // $compiledRoutes' . "\n";

        if ($this->matcher instanceof SimpleRouteMatcher) {
            $code .= $this->simpleRouteCompilerCode($compiledRoutes);
        } elseif (null !== $compiledRoutes || false !== $compiledRoutes) {
            $code .= self::export($compiledRoutes);
        }
        $code .= "],\n";

        $code .= '[ // $routes' . "\n";

        foreach ($collection->getRoutes() as $route) {
            if ($route->getController() instanceof Closure) {
                continue;
            }

            $code .= \sprintf('    %s => ', self::export($route->getName()));
            $code .= self::export($route);
            $code .= ", \n";
        }
        $code .= "],\n";

        return (string) \preg_replace('/^./m', \str_repeat('    ', 1) . '$0', $code);
    }

    /**
     * @param RouteInterface $route
     *
     * @return string
     */
    private static function exportRoute(RouteInterface $route): string
    {
        $controller = $route->getController();

        if (!\is_string($controller)) {
            $controller = \sprintf('unserialize(\'%s\')', \serialize($controller));
        }

        $exported = self::export([
            $route->getName(),
            $route->getMethods(),
            $route->getPath(),
            $route->getSchemes(),
            $route->getDomain(),
            $controller,
            $route->getMiddlewares(),
            $route->getPatterns(),
            $route->getDefaults(),
            $route->getArguments(),
        ]);

        return \sprintf('%s::__set_state(%s)', Route::class, $exported);
    }

    /**
     * @param mixed[] $compiledRoutes
     *
     * @return string
     */
    private function simpleRouteCompilerCode(array $compiledRoutes): string
    {
        [$staticRoutes, $dynamicRoutes] = $compiledRoutes;

        $code = '';
        $code .= '    [ // $staticRoutes' . "\n";

        foreach ($staticRoutes as $path => $route) {
            $code .= \sprintf('        %s => ', self::export($path));

            if (\is_array($route)) {
                $code .= \sprintf(
                    "[\n            %s,\n            %s\n        ]",
                    self::export(\current($route)),
                    \sprintf('\unserialize(\'%s\')', \serialize(\end($route)))
                );
            } else {
                $code .= self::export($route);
            }
            $code .= ", \n";
        }
        $code .= "    ],\n";

        $code .= '    [ // $dynamicRoutes' . "\n";

        foreach ($dynamicRoutes as $name => $route) {
            $code .= \sprintf('        %s =>', self::export($name));
            $code .= \sprintf(' \unserialize(\'%s\'),' . "\n", \serialize($route));
        }
        $code .= "    ],\n";

        return $code;
    }
}
