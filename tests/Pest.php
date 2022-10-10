<?php declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 7.4 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Flight\Routing\Handlers\ResourceHandler;

\spl_autoload_register(function (string $class): void {
    match ($class) {
        'Flight\Routing\Tests\Fixtures\Annotation\Route\Valid\MultipleMethodRouteController' => require __DIR__.'/Fixtures/Annotation/Route/Valid/MultipleMethodRouteController.php',
        'Flight\Routing\Tests\Fixtures\BlankRequestHandler' => require __DIR__.'/Fixtures/BlankRequestHandler.php',
        'Flight\Routing\Tests\Fixtures\BlankRestful' => require __DIR__.'/Fixtures/BlankRestful.php',
        'Flight\Routing\Tests\Fixtures\Annotation\Route\Invalid\PathEmpty' => require __DIR__.'/Fixtures/Annotation/Route/Invalid/PathEmpty.php',
        'Flight\Routing\Tests\Fixtures\Annotation\Route\Invalid\MethodWithResource' => require __DIR__.'/Fixtures/Annotation/Route/Invalid/MethodWithResource.php',
        'Flight\Routing\Tests\Fixtures\Annotation\Route\Invalid\ClassGroupWithResource' => require __DIR__.'/Fixtures/Annotation/Route/Invalid/ClassGroupWithResource.php',
        default => null,
    };
});

// uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/
expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/
function debugFormat(mixed $value, $indent = ''): string
{
    switch (true) {
        case \is_int($value) || \is_float($value):
            return \var_export($value, true);
        case [] === $value:
            return '[]';
        case false === $value:
            return 'false';
        case true === $value:
            return 'true';
        case null === $value:
            return 'null';
        case '' === $value:
            return "''";
        case $value instanceof \UnitEnum:
            return \ltrim(\var_export($value, true), '\\');
    }
    $subIndent = $indent.'    ';

    if (\is_string($value)) {
        return \sprintf("'%s'", \addcslashes($value, "'\\"));
    }

    if (\is_array($value)) {
        $j = -1;
        $code = '';

        foreach ($value as $k => $v) {
            $code .= $subIndent;

            if (!\is_int($k) || 1 !== $k - $j) {
                $code .= debugFormat($k, $subIndent).' => ';
            }

            if (\is_int($k) && $k > $j) {
                $j = $k;
            }
            $code .= debugFormat($v, $subIndent).",\n";
        }

        return "[\n".$code.$indent.']';
    }

    if (\is_object($value)) {
        if ($value instanceof ResourceHandler) {
            return 'new ResourceHandler('.debugFormat($value(''), $indent).')';
        }

        if ($value instanceof \stdClass) {
            return '(object) '.debugFormat((array) $value, $indent);
        }

        if (!$value instanceof \Closure) {
            return $value::class;
        }
        $ref = new \ReflectionFunction($value);

        if (0 === $ref->getNumberOfParameters()) {
            return 'fn() => '.debugFormat($ref->invoke(), $indent);
        }
    }

    throw new \UnexpectedValueException(\sprintf('Cannot format value of type "%s".', \get_debug_type($value)));
}
