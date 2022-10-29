<?php declare(strict_types=1);

/*
 * This file is part of Flight Routing.
 *
 * PHP version 8.0 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Divine Niiquaye Ibok (https://divinenii.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Flight\Routing\Exceptions\{UriHandlerException, UrlGenerationException};
use Flight\Routing\RouteUri as GeneratedUri;
use PHPUnit\Framework as t;

dataset('patterns', [
    [
        '/foo',
        '{^/foo$}',
        [],
        ['/foo'],
    ],
    [
        '/foo/',
        '{^/foo/?$}',
        [],
        ['/foo', '/foo/'],
    ],
    [
        '/foo/{bar}',
        '{^/foo/(?P<bar>[^\/]+)$}',
        ['bar' => null],
        ['/foo/bar', '/foo/baz'],
    ],
    [
        '/foo/{bar}@',
        '{^/foo/(?P<bar>[^\/]+)@$}',
        ['bar' => null],
        ['/foo/bar@', '/foo/baz@'],
    ],
    [
        '/foo-{bar}',
        '{^/foo-(?P<bar>[^\/]+)$}',
        ['bar' => null],
        ['/foo-bar', '/foo-baz'],
    ],
    [
        '/foo/{bar}/{baz}/',
        '{^/foo/(?P<bar>[^\/]+)/(?P<baz>[^\/]+)/?$}',
        ['bar' => null, 'baz' => null],
        ['/foo/bar/baz', '/foo/bar/baz/'],
    ],
    [
        '/foo/{bar:\d+}',
        '{^/foo/(?P<bar>\d+)$}',
        ['bar' => null],
        ['/foo/123', '/foo/444'],
    ],
    [
        '/foo/{bar:\d+}/{baz}/',
        '{^/foo/(?P<bar>\d+)/(?P<baz>[^\/]+)/?$}',
        ['bar' => null, 'baz' => null],
        ['/foo/123/baz', '/foo/123/baz/'],
    ],
    [
        '/foo/{bar:\d+}/{baz:slug}',
        '{^/foo/(?P<bar>\d+)/(?P<baz>[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*)$}',
        ['bar' => null, 'baz' => null],
        ['/foo/123/foo', '/foo/44/baz'],
    ],
    [
        '/foo/{bar=0}',
        '{^/foo/(?P<bar>[^\/]+)$}',
        ['bar' => '0'],
        ['/foo/0'],
    ],
    [
        '/foo/{bar=baz}/{baz}/',
        '{^/foo/(?P<bar>[^\/]+)/(?P<baz>[^\/]+)/?$}',
        ['bar' => 'baz', 'baz' => null],
        ['/foo/baz/baz', '/foo/baz/baz/'],
    ],
    [
        '/[{foo}]',
        '{^/?(?:(?P<foo>[^\/]+))?$}',
        ['foo' => null],
        ['/', '/foo', '/bar'],
    ],
    [
        '/[{bar:(foo|bar)}]',
        '{^/?(?:(?P<bar>(foo|bar)))?$}',
        ['bar' => null],
        ['/', '/foo', '/bar'],
    ],
    [
        '/foo[/{bar}]/',
        '{^/foo(?:/(?P<bar>[^\/]+))?/?$}',
        ['bar' => null],
        ['/foo', '/foo/', '/foo/bar', '/foo/bar/'],
    ],
    [
        '/[{foo:upper}]/[{bar:lower}]',
        '{^/?(?:(?P<foo>[A-Z]+))?/?(?:(?P<bar>[a-z]+))?$}',
        ['foo' => null, 'bar' => null],
        ['/', '/FOO', '/FOO/', '/FOO/bar', '/bar'],
    ],
    [
        '/[{foo}][/{bar:month}]',
        '{^/?(?:(?P<foo>[^\/]+))?(?:/(?P<bar>0[1-9]|1[012]+))?$}',
        ['foo' => null, 'bar' => null],
        ['/', '/foo', '/bar', '/foo/12', '/foo/01'],
    ],
    [
        '/[{foo:lower}/[{bar:upper}]]',
        '{^/?(?:(?P<foo>[a-z]+)/?(?:(?P<bar>[A-Z]+))?)?$}',
        ['foo' => null, 'bar' => null],
        ['/', '/foo', '/foo/', '/foo/BAR', '/foo/BAZ'],
    ],
    [
        '/[{foo}/{bar}]',
        '{^/?(?:(?P<foo>[^\/]+)/(?P<bar>[^\/]+))?$}',
        ['foo' => null, 'bar' => null],
        ['/', '/foo/bar', '/foo/baz'],
    ],
    [
        '/who{are}you',
        '{^/who(?P<are>[^\/]+)you$}',
        ['are' => null],
        ['/whoareyou', '/whoisyou'],
    ],
    [
        '/[{lang:[a-z]{2}}/]hello',
        '{^/?(?:(?P<lang>[a-z]{2})/)?hello$}',
        ['lang' => null],
        ['/hello', '/en/hello', '/fr/hello'],
    ],
    [
        '/[{lang:[\w+\-]+=english}/]hello',
        '{^/?(?:(?P<lang>[\w+\-]+)/)?hello$}',
        ['lang' => 'english'],
        ['/hello', '/en/hello', '/fr/hello'],
    ],
    [
        '/[{lang:[a-z]{2}}[-{sublang}]/]{name}[/page-{page=0}]',
        '{^/?(?:(?P<lang>[a-z]{2})(?:-(?P<sublang>[^\/]+))?/)?(?P<name>[^\/]+)(?:/page-(?P<page>[^\/]+))?$}',
        ['lang' => null, 'sublang' => null, 'name' => null, 'page' => '0'],
        ['/hello', '/en/hello', '/en-us/hello', '/en-us/hello/page-1', '/en-us/hello/page-2'],
    ],
    [
        '/hello/{foo:[a-z]{3}=bar}{baz}/[{id:[0-9a-fA-F]{1,8}}[.{format:html|php}]]',
        '{^/hello/(?P<foo>[a-z]{3})(?P<baz>[^\/]+)/?(?:(?P<id>[0-9a-fA-F]{1,8})(?:\.(?P<format>html|php))?)?$}',
        ['foo' => 'bar', 'baz' => null, 'id' => null, 'format' => null],
        ['/hello/barbaz', '/hello/barbaz/', '/hello/barbaz/1', '/hello/barbaz/1.html', '/hello/barbaz/1.php'],
    ],
    [
        '/hello/{foo:\w{3}}{bar=bar1}/world/[{name:[A-Za-z]+}[/{page:int=1}[/{baz:year}]]]/abs.{format:html|php}',
        '{^/hello/(?P<foo>\w{3})(?P<bar>[^\/]+)/world/?(?:(?P<name>[A-Za-z]+)(?:/(?P<page>[0-9]+)(?:/(?P<baz>[0-9]{4}))?)?)?/abs\.(?P<format>html|php)$}',
        ['foo' => null, 'bar' => 'bar1', 'name' => null, 'page' => '1', 'baz' => null, 'format' => null],
        [
            '/hello/foobar/world/abs.html',
            '/hello/barfoo/world/divine/abs.php',
            '/hello/foobaz/world/abs.php',
            '/hello/bar100/world/divine/11/abs.html',
            '/hello/true/world/divine/11/2022/abs.html',
        ],
    ],
    [
        '{foo}.example.com',
        '{^(?P<foo>[^\/]+)\.example\.com$}',
        ['foo' => null],
        ['foo.example.com', 'bar.example.com'],
    ],
    [
        '{locale}.example.{tld}',
        '{^(?P<locale>[^\/]+)\.example\.(?P<tld>[^\/]+)$}',
        ['locale' => null, 'tld' => null],
        ['en.example.com', 'en.example.org', 'en.example.co.uk'],
    ],
    [
        '[{lang:[a-z]{2}}.]example.com',
        '{^(?:(?P<lang>[a-z]{2})\.)?example\.com$}',
        ['lang' => null],
        ['en.example.com', 'example.com', 'fr.example.com'],
    ],
    [
        '[{lang:[a-z]{2}}.]example.{tld=com}',
        '{^(?:(?P<lang>[a-z]{2})\.)?example\.(?P<tld>[^\/]+)$}',
        ['lang' => null, 'tld' => 'com'],
        ['en.example.com', 'example.com', 'fr.example.gh'],
    ],
    [
        '{id:int}.example.com',
        '{^(?P<id>[0-9]+)\.example\.com$}',
        ['id' => null],
        ['1.example.com', '2.example.com'],
    ],
]);

dataset('reversed', [
    [
        '/foo',
        ['/foo' => []],
    ],
    [
        '/foo/{bar}',
        ['/foo/bar' => ['bar' => 'bar']],
    ],
    [
        '/foo/{bar}/{baz}',
        ['/foo/bar/baz' => ['bar' => 'bar', 1 => 'baz']],
    ],
    [
        '/divine/{id:\d+}[/{a}{b}[{c}]][.p[{d}]]',
        [
            '/divine/23' => ['id' => '23'],
            '/divine/23/ab' => ['23', 'a' => 'a', 'b' => 'b'],
            '/divine/23/abc' => ['23', 'a' => 'a', 'b' => 'b', 'c' => 'c'],
            '/divine/23/abc.php' => ['23', 'a' => 'a', 'b' => 'b', 'c' => 'c', 'd' => 'hp'],
            '/divine/23.phtml' => ['id' => '23', 'd' => 'html'],
        ],
    ],
]);

test('if route path is a valid regex', function (string $path, string $regex, array $vars, array $matches): void {
    $compiler = new \Flight\Routing\RouteCompiler();
    [$pathRegex, $pathVar] = $compiler->compile($path);

    t\assertEquals($regex, $pathRegex);
    t\assertSame($vars, $pathVar);

    // Match every pattern...
    foreach ($matches as $match) {
        t\assertMatchesRegularExpression($pathRegex, $match);
    }
})->with('patterns');

test('if compiled route path is reversible', function (string $path, array $matches): void {
    $compiler = new \Flight\Routing\RouteCompiler();

    foreach ($matches as $match => $params) {
        t\assertEquals($match, (string) $compiler->generateUri(['path' => $path], $params));
    }
})->with('reversed');

test('if route path placeholder is characters length is invalid', function (): void {
    $compiler = new \Flight\Routing\RouteCompiler();
    $compiler->compile('/{sfkdfglrjfdgrfhgklfhgjhfdjghrtnhrnktgrelkrngldrjhglhkjdfhgkj}');
})->throws(
    UriHandlerException::class,
    \sprintf(
        'Variable name "%s" cannot be longer than 32 characters in route pattern "/{%1$s}".',
        'sfkdfglrjfdgrfhgklfhgjhfdjghrtnhrnktgrelkrngldrjhglhkjdfhgkj'
    )
);

test('if route path placeholder begins with a number', function (): void {
    $compiler = new \Flight\Routing\RouteCompiler();
    $compiler->compile('/{1foo}');
})->throws(
    UriHandlerException::class,
    'Variable name "1foo" cannot start with a digit in route pattern "/{1foo}". Use a different name.'
);

test('if route path placeholder is used more than once', function (): void {
    $compiler = new \Flight\Routing\RouteCompiler();
    $compiler->compile('/{foo}/{foo}');
})->throws(
    UriHandlerException::class,
    'Route pattern "/{foo}/{foo}" cannot reference variable name "foo" more than once.'
);

test('if route path placeholder has regex values', function (string $path, array $segment): void {
    $compiler = new \Flight\Routing\RouteCompiler();
    [$pathRegex] = $compiler->compile($path, $segment);
    t\assertMatchesRegularExpression($pathRegex, '/a');
    t\assertMatchesRegularExpression($pathRegex, '/b');
})->with([
    ['/{foo}', ['foo' => ['a', 'b']]],
    ['/{foo}', ['foo' => 'a|b']],
]);

test('if route path placeholder requirement is empty', function (string $assert): void {
    $compiler = new \Flight\Routing\RouteCompiler();
    $compiler->compile('/{foo}', ['foo' => $assert]);
})->with(['', '^$', '^', '$', '\A\z', '\A', '\z'])->throws(
    UriHandlerException::class,
    'Routing requirement for "foo" cannot be empty.'
);

test('if reversed generated route path can contain http scheme and host', function (): void {
    $compiler = new \Flight\Routing\RouteCompiler();
    $_SERVER['HTTP_HOST'] = 'example.com';
    $route = ['path' => '/{foo}', 'schemes' => ['http' => true]];

    t\assertEquals('/a', (string) $compiler->generateUri($route, ['foo' => 'a']));
    t\assertEquals('./b', (string) $compiler->generateUri($route, ['foo' => 'b'], GeneratedUri::RELATIVE_PATH));
    t\assertEquals('//example.com/c', (string) $compiler->generateUri($route, ['c'], GeneratedUri::NETWORK_PATH));
    t\assertEquals('http://example.com/d', (string) $compiler->generateUri($route, [0 => 'd'], GeneratedUri::ABSOLUTE_URL));
    t\assertEquals('http://localhost/e', (string) $compiler->generateUri(
        $route += ['hosts' => ['localhost' => true]],
        ['foo' => 'e'],
        GeneratedUri::ABSOLUTE_URL
    ));
});

test('if reversed generated route fails to certain placeholders', function (): void {
    $compiler = new \Flight\Routing\RouteCompiler();
    $compiler->generateUri(['path' => '/{foo:int}'], ['foo' => 'a']);
})->throws(
    UriHandlerException::class,
    'Expected route path "/<foo>" placeholder "foo" value "a" to match "[0-9]+".'
);

test('if reversed generate route is missing required placeholders', function (): void {
    $compiler = new \Flight\Routing\RouteCompiler();
    $compiler->generateUri(['path' => '/{foo}'], []);
})->throws(
    UrlGenerationException::class,
    'Some mandatory parameters are missing ("foo") to generate a URL for route path "/<foo>".'
);

test('if reversed generate route can contain a negative port port', function (): void {
    $compiler = new \Flight\Routing\RouteCompiler();
    $compiler->generateUri(['path' => '/{foo}'], ['flight-routing'])->withPort(-9);
})->throws(UrlGenerationException::class, 'Invalid port: -9. Must be between 0 and 65535');
