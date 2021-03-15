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

use Flight\Routing\Matchers\ExpressionCollection;
use Flight\Routing\Route;

/**
 * @codeCoverageIgnore
 */
trait DumperTrait
{
    /** @var bool */
    protected $export = true;

    protected static function indent(string $code, int $level = 1): string
    {
        return (string) \preg_replace('/^./m', \str_repeat('    ', $level) . '$0', $code);
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
            if ($value instanceof Route) {
                return self::exportRoute($value);
            } elseif (\is_object($value)) {
                return \sprintf('unserialize(\'%s\')', \serialize($value));
            } elseif (is_string($value) && str_starts_with($value, 'static function ()')) {
                return $value;
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
            } elseif ($v instanceof Route) {
                $v .= self::exportRoute($v);
            } elseif (\is_object($v)) {
                $v = \sprintf('unserialize(\'%s\'), ', \serialize($v));
            } else {
                $v = self::export($v) . ', ';
            }

            $export .= $v;
        }

        return \substr_replace($export, ']', -2);
    }

    /**
     * @param Route $route
     *
     * @return string
     */
    protected static function exportRoute(Route $route): string
    {
        $properties = $route->get('all');
        $controller = $properties['controller'];
        $exported   = '';

        if ($controller instanceof \Closure) {
            $closureRef = new \ReflectionFunction($controller);

            if (empty($closureRef->getParameters()) && null === $closureRef->getClosureThis()) {
                \ob_start();

                $closureReturn = $controller();
                $closureEcho   = \ob_get_clean();

                $properties['controller'] = \sprintf(
                    "static function () {\n            %s %s;\n        }",
                    null !== $closureReturn ? 'return' : 'echo',
                    self::export($closureReturn ?? $closureEcho)
                );
            }
        } elseif (\is_object($controller) || (\is_array($controller) && \is_object($controller[0]))) {
            $properties['controller'] = \sprintf('unserialize(\'%s\')', \serialize($controller));
        }

        foreach ($properties as $key => $value) {
            $exported .= \sprintf('        %s => ', self::export($key));
            $exported .= self::export($value);
            $exported .= ",\n";
        }

        return "\Flight\Routing\Route::__set_state([\n{$exported}    ])";
    }

    /**
     * Compiles a regexp tree of subpatterns that matches nested same-prefix routes.
     *
     * @param array<string,string> $vars
     */
    private function compileExpressionCollection(ExpressionCollection $tree, int $prefixLen, array &$vars): string
    {
        $code   = '';
        $routes = $tree->getRoutes();

        foreach ($routes as $route) {
            if ($route instanceof ExpressionCollection) {
                $prefix = \substr($route->getPrefix(), $prefixLen);
                $rx     = "|{$prefix}(?";

                $regexpCode = $this->compileExpressionCollection($route, $prefixLen + \strlen($prefix), $vars);

                $code .= $this->export ? "\n        ." . self::export($rx) : $rx;
                $code .= $this->export ? self::indent($regexpCode) : $regexpCode;
                $code .= $this->export ? "\n        .')'" : ')';

                continue;
            }
            [$name, $regex, $variable] = $route;

            $rx = \sprintf('|%s(*:%s)', \substr($regex, $prefixLen), $name);
            $code .= $this->export ? "\n        ." . self::export($rx) : $rx;

            $vars[$name] = $variable;
        }

        return $code;
    }

    /**
     * Warm up routes to speed up routes handling.
     *
     * @internal
     */
    private function generateCompiledRoutes(): string
    {
        $code = '';

        [$staticRoutes, $dynamicRoutes, $regexList, $collection] = $this->getCompiledRoutes();
        $code .= '[ // $staticRoutes' . "\n";

        foreach ($staticRoutes as $path => $route) {
            $code .= \sprintf('    %s => ', self::export($path));
            $code .= self::export($route);
            $code .= ", \n";
        }
        $code .= "],\n";

        $code .= '[ // $dynamicRoutes' . "\n";

        foreach ($dynamicRoutes as $name => $route) {
            $code .= \sprintf('    %s => ', self::export($name));
            $code .= self::export($route);
            $code .= ", \n";
        }
        $code .= "],\n";

        [$regex, $variables] = $regexList;

        $regexpCode = "    {$regex},\n    [\n";

        foreach ($variables as $key => $value) {
            $regexpCode .= \sprintf('        %s => ', self::export($key));
            $regexpCode .= self::export($value);
            $regexpCode .= ",\n";
        }

        $code .= \sprintf("[ // \$regexpList\n%s    ],\n],\n", $regexpCode);

        $code .= '[ // $routeCollection' . "\n";

        foreach ($collection as $name => $route) {
            $code .= \sprintf('    %s => ', self::export($name));
            $code .= self::export($route);
            $code .= ", \n";
        }
        $code .= "],\n";

        $generatedCode = self::indent($code);

        return <<<EOF
<?php

/**
 * This file has been auto-generated by the Flight Routing.
 */
return [
{$generatedCode}];

EOF;
    }
}
