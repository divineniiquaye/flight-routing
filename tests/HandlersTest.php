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

use Flight\Routing\Exceptions\InvalidControllerException;
use Flight\Routing\Handlers\{CallbackHandler, FileHandler, ResourceHandler, RouteHandler, RouteInvoker};
use Flight\Routing\RouteCollection;
use Flight\Routing\Router;
use Flight\Routing\Tests\Fixtures;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework as t;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

test('if route callback handler will return a response', function (): void {
    $callback = new CallbackHandler(function (ServerRequestInterface $req): ResponseInterface {
        ($res = new Response())->getBody()->write($req->getMethod());

        return $res;
    });
    t\assertSame('GET', (string) $callback->handle(new ServerRequest('GET', '/hello'))->getBody());
});

test('if route resource handler does not contain valid data', function (): void {
    new ResourceHandler(new ResourceHandler('phpinfo'));
})->throws(
    InvalidControllerException::class,
    'Expected a class string or class object, got a type of "callable" instead'
);

test('if route handler does return a plain valid response', function (): void {
    $collection = new RouteCollection();
    $collection->add('/a', handler: fn (): ResponseInterface => new Response());
    $collection->add('/b', handler: fn (): string => 'Hello World');
    $handler = new RouteHandler(new Psr17Factory());

    $req = (new ServerRequest('GET', '/a'))->withAttribute(Router::class, $collection->offsetGet(0));
    t\assertInstanceOf(ResponseInterface::class, $res = $handler->handle($req));
    t\assertSame('text/plain; charset=utf-8', $res->getHeaderLine('Content-Type'));
    t\assertSame(204, $res->getStatusCode());

    $req = (new ServerRequest('GET', '/b'))->withAttribute(Router::class, $collection->offsetGet(1));
    t\assertInstanceOf(ResponseInterface::class, $res = $handler->handle($req));
    t\assertSame('text/plain', $res->getHeaderLine('Content-Type'));
    t\assertSame(200, $res->getStatusCode());

    $req = (new ServerRequest('GET', '/c'))->withAttribute(Router::class, []);
    t\assertInstanceOf(ResponseInterface::class, $res = $handler->handle($req));
    t\assertSame('text/plain; charset=utf-8', $res->getHeaderLine('Content-Type'));
    t\assertSame(204, $res->getStatusCode());
});

test('if route handler does return a html valid response', function (): void {
    $handler = new RouteHandler(new Psr17Factory());
    $req = (new ServerRequest('GET', '/a'))->withAttribute(Router::class, ['handler' => function (): string {
        return <<<'HTML'
        <html lang="en">
            <head><meta charset="UTF-8"></head>
            <body><h1>Hello World</h1></body>
        </html>
        HTML;
    }]);
    t\assertInstanceOf(ResponseInterface::class, $res = $handler->handle($req));
    t\assertSame('text/html', $res->getHeaderLine('Content-Type'));
    t\assertSame(200, $res->getStatusCode());
});

test('if route handler does return a xml valid response', function (): void {
    $handler = new RouteHandler(new Psr17Factory());
    $req = (new ServerRequest('GET', '/a'))->withAttribute(Router::class, ['handler' => function (): string {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <project version="4">
        <component name="PHPUnit">
            <option name="directories">
                <list><option value="$PROJECT_DIR$/tests" /></list>
            </option>
        </component>
        </project>
        XML;
    }]);
    t\assertInstanceOf(ResponseInterface::class, $res = $handler->handle($req));
    t\assertSame('text/xml; charset=utf-8', $res->getHeaderLine('Content-Type'));
    t\assertSame(200, $res->getStatusCode());
});
test('if route handler does return a rss valid response', function (): void {
    $handler = new RouteHandler(new Psr17Factory());
    $req = (new ServerRequest('GET', '/a'))->withAttribute(Router::class, ['handler' => function (): string {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:slash="http://purl.org/rss/1.0/modules/slash/"></rss>
        XML;
    }]);
    t\assertInstanceOf(ResponseInterface::class, $res = $handler->handle($req));
    t\assertSame('application/rss+xml; charset=utf-8', $res->getHeaderLine('Content-Type'));
    t\assertSame(200, $res->getStatusCode());
});

test('if route handler does return a svg valid response', function (): void {
    $handler = new RouteHandler(new Psr17Factory());
    $req = (new ServerRequest('GET', '/a'))->withAttribute(Router::class, [
        'handler' => fn (): string => '<?xml version="1.0" encoding="UTF-8"?><svg><metadata>Hello World</metadata></svg>',
    ]);
    t\assertInstanceOf(ResponseInterface::class, $res = $handler->handle($req));
    t\assertSame('image/svg+xml', $res->getHeaderLine('Content-Type'));
    t\assertSame(200, $res->getStatusCode());
});

test('if route handler does return a csv valid response', function (): void {
    $handler = new RouteHandler(new Psr17Factory());
    $req = (new ServerRequest('GET', '/a'))->withAttribute(Router::class, ['handler' => function (): string {
        return <<<'CSV'
        a,b,c
        d,e,f
        g,h,i

        CSV;
    }]);
    t\assertInstanceOf(ResponseInterface::class, $res = $handler->handle($req));
    t\assertSame('text/csv', $res->getHeaderLine('Content-Type'));
    t\assertSame(200, $res->getStatusCode());
});

test('if route handler does return a json valid response', function (): void {
    $collection = new RouteCollection();
    $collection->add('/a', handler: fn () => \json_encode(['Hello', 'World', 'Cool' => 'Yeah', 2022]));
    $handler = new RouteHandler(new Psr17Factory());
    $req = (new ServerRequest('GET', '/a'))->withAttribute(Router::class, $collection->offsetGet(0));
    t\assertInstanceOf(ResponseInterface::class, $res = $handler->handle($req));
    t\assertSame('application/json', $res->getHeaderLine('Content-Type'));
    t\assertSame(200, $res->getStatusCode());
});

test('if route handler cannot detect content type', function (): void {
    $handler = new RouteHandler(new Psr17Factory());
    $req = (new ServerRequest('GET', '/a'))->withAttribute(Router::class, ['handler' => function () {
        return <<<'CSS'
        body {
            padding-top: 10px;
        }
        svg text {
            font-family: "Lucida Grande", "Lucida Sans Unicode", Verdana, Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #666;
            fill: #666;
        }
        CSS;
    }]);
    t\assertInstanceOf(ResponseInterface::class, $res = $handler->handle($req));
    t\assertSame('text/plain', $res->getHeaderLine('Content-Type'));
    t\assertSame(200, $res->getStatusCode());
});

test('if route handler does return a echoed xml valid response', function (): void {
    $handler = new RouteHandler(new Psr17Factory());
    $req = (new ServerRequest('GET', '/a'))->withAttribute(Router::class, ['handler' => function (): void {
        echo <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:slash="http://purl.org/rss/1.0/modules/slash/"></rss>
        XML;
    }]);
    t\assertInstanceOf(ResponseInterface::class, $res = $handler->handle($req));
    t\assertSame('application/rss+xml; charset=utf-8', $res->getHeaderLine('Content-Type'));
    t\assertSame(200, $res->getStatusCode());
});

test('if route handler can handle exception from route', function (): void {
    $handler = new RouteHandler(new Psr17Factory());
    $req = (new ServerRequest('GET', '/a'))->withAttribute(Router::class, ['handler' => function (): void {
        throw new \RuntimeException('Testing error');
    }]);
    $handler->handle($req);
    $this->fail('Expected an invalid controller exception as route handler is invalid');
})->throws(\RuntimeException::class, 'Testing error');

test('if route handler does return an invalid response', function (): void {
    $handler = new RouteHandler(new Psr17Factory());
    $req = (new ServerRequest('GET', '/a'))->withAttribute(Router::class, ['handler' => fn () => new \finfo()]);
    $handler->handle($req);
    $this->fail('Expected an invalid controller exception as route handler is invalid');
})->throws(
    InvalidControllerException::class,
    'The route handler\'s content is not a valid PSR7 response body stream.'
);

test('if route handler is a request handler type', function (): void {
    $handler = new RouteHandler(new Psr17Factory());
    $collection = new RouteCollection();
    $collection->add('/a', handler: new CallbackHandler(function (ServerRequestInterface $req): ResponseInterface {
        $res = new Response();
        $method = $req->getMethod();
        $res->getBody()->write(<<<HTML
        <html lang="en">
            <head><meta charset="UTF-8"></head>
            <body><h1>Hello World in {$method} REQUEST</h1></body>
        </html>
        HTML);

        return $res;
    }));
    $collection->add('/b', handler: Fixtures\BlankRequestHandler::class);

    $req = (new ServerRequest('GET', '/a'))->withAttribute(Router::class, $collection->offsetGet(0));
    t\assertInstanceOf(ResponseInterface::class, $res = $handler->handle($req));
    t\assertStringContainsString('GET REQUEST', (string) $res->getBody());
    t\assertSame('text/html', $res->getHeaderLine('Content-Type'));
    t\assertSame(200, $res->getStatusCode());

    $req = (new ServerRequest('GET', '/b'))->withAttribute(Router::class, $collection->offsetGet(1));
    t\assertInstanceOf(ResponseInterface::class, $res = $handler->handle($req));
    t\assertEmpty((string) $res->getBody());
    t\assertSame('text/plain; charset=utf-8', $res->getHeaderLine('Content-Type'));
    t\assertSame(204, $res->getStatusCode());
});

test('if route handler is an array like type', function (): void {
    $handler = new RouteHandler(new Psr17Factory());
    $collection = new RouteCollection();
    $collection->add('/a', handler: [Fixtures\BlankRequestHandler::class, 'handle']);
    $collection->add('/b', handler: [$h = new Fixtures\BlankRequestHandler(), 'handle']);
    $collection->add('/c', handler: fn (): array => [1, 2, 3, 4, 5]);
    $collection->add('/d', handler: [1, 2, 3, 'Error']);

    $req = (new ServerRequest('GET', '/a'))->withAttribute(Router::class, $collection->offsetGet(0));
    t\assertInstanceOf(ResponseInterface::class, $res = $handler->handle($req));
    t\assertEmpty((string) $res->getBody());
    t\assertSame('text/plain; charset=utf-8', $res->getHeaderLine('Content-Type'));
    t\assertSame(204, $res->getStatusCode());

    $req = (new ServerRequest('GET', '/b'))->withAttribute(Router::class, $collection->offsetGet(1));
    t\assertInstanceOf(ResponseInterface::class, $res = $handler->handle($req));
    t\assertEmpty((string) $res->getBody());
    t\assertSame('text/plain; charset=utf-8', $res->getHeaderLine('Content-Type'));
    t\assertSame(204, $res->getStatusCode());
    t\assertTrue($h->isDone());

    $req = (new ServerRequest('GET', '/c'))->withAttribute(Router::class, $collection->offsetGet(2));
    t\assertInstanceOf(ResponseInterface::class, $res = $handler->handle($req));
    t\assertSame('[1,2,3,4,5]', (string) $res->getBody());
    t\assertSame('application/json', $res->getHeaderLine('Content-Type'));
    t\assertSame(200, $res->getStatusCode());

    $req = (new ServerRequest('GET', '/d'))->withAttribute(Router::class, $collection->offsetGet(3));
    $handler->handle($req);
    $this->fail('Expected an invalid controller exception as route handler is invalid');
})->throws(InvalidControllerException::class, 'Route has an invalid handler type of "array".');

test('if route handler is a stringable type', function (): void {
    $handler = new RouteHandler(new Psr17Factory());
    $req = (new ServerRequest('GET', '/a'))->withAttribute(Router::class, ['handler' => new \RuntimeException()]);
    t\assertInstanceOf(ResponseInterface::class, $res = $handler->handle($req));
    t\assertSame('RuntimeException', \substr((string) $res->getBody(), 0, 16));
    t\assertSame('text/plain', $res->getHeaderLine('Content-Type'));
    t\assertSame(200, $res->getStatusCode());
});

test('if route handler is a file handler type', function (): void {
    $handler = new RouteHandler(new Psr17Factory());
    $collection = new RouteCollection();
    $collection->add('/a', handler: new FileHandler(__DIR__.'/../tests/Fixtures/template.html'));
    $collection->add('/b', handler: fn () => new FileHandler(__DIR__.'/../tests/Fixtures/style.css'));

    $req = (new ServerRequest('GET', '/a'))->withAttribute(Router::class, $collection->offsetGet(0));
    t\assertInstanceOf(ResponseInterface::class, $res = $handler->handle($req));
    t\assertSame('text/html', $res->getHeaderLine('Content-Type'));
    t\assertSame(200, $res->getStatusCode());

    $req = (new ServerRequest('GET', '/b'))->withAttribute(Router::class, $collection->offsetGet(1));
    t\assertInstanceOf(ResponseInterface::class, $res = $handler->handle($req));
    t\assertSame('text/css', $res->getHeaderLine('Content-Type'));
    t\assertSame(200, $res->getStatusCode());

    $req = (new ServerRequest('GET', '/b'))->withAttribute(Router::class, ['handler' => new FileHandler('hello')]);
    $handler->handle($req);
    $this->fail('Expected an invalid controller exception as route handler is invalid');
})->throws(InvalidControllerException::class, 'Failed to fetch contents from file "hello"');

test('if route invoker can support a number of parameters binding', function (): void {
    $handler = new RouteInvoker();
    $container = new RouteInvoker(new class () implements ContainerInterface {
        public function get(string $id)
        {
            return match ($id) {
                \Countable::class => new \ArrayObject(),
                \Iterator::class => new \ArrayIterator([1, 2, 3]),
                'func' => fn (int $a): ContainerInterface => $this,
            };
        }

        public function has(string $id): bool
        {
            return \Countable::class === $id || \Iterator::class === $id || 'func' === $id;
        }
    });
    $h0 = $handler(fn ($var): bool => true, []);
    $h1 = $handler(fn (string $name = '', array $values = []): bool => true, []);
    $h2 = $handler(fn (?string $a, string $b = 'iiv'): string => $a.$b, []);
    $h3 = $handler(fn (string $a): array => \unpack('C*', $a), ['a' => '🚀']);
    $h4 = $handler(fn (string $a, string $b, string $c): string => $a.$b.$c, ['a&b' => 'i', 'c' => 'v']);
    $h5 = $handler(fn (int|string $a, int|bool $b = 3): string => $a.$b, ['a' => 1]);
    $h6 = $handler(fn (int|string|null $a): string|bool => null == $a ? '13' : false, []);
    $h7 = $handler(fn (string|RouteCollection $a, RouteCollection $b): bool => $a === $b, ['a&b' => new RouteCollection()]);
    $h8 = $handler($u = fn (RouteCollection|\Countable $a): bool => $a instanceof \Countable, [RouteCollection::class => new RouteCollection()]);
    $h9 = $container(fn (\Iterator $a): bool => [1, 2, 3] === \iterator_to_array($a), []);
    $h10 = $container(fn (mixed $a): bool => '🚀' === $a, ['a' => \pack('C*', 0xF0, 0x9F, 0x9A, 0x80)]);
    $h11 = $container(fn (?string $a, ?string $b): bool => $a === $b, []);

    t\assertSame($h0, $h1);
    t\assertSame($h2, $h4);
    t\assertSame($h5, $h6);
    t\assertSame($h7, $h8);
    t\assertSame($h9, $h10);
    t\assertSame($h11, $container($u, []));
    t\assertSame([1 => 0xF0, 2 => 0x9F, 3 => 0x9A, 4 => 0x80], $h3);
    t\assertSame($container('func', ['a' => 0]), $container->getContainer());
});

test('if route invoker can execute handlers which is prefixed with a \\', function (): void {
    $handler = new RouteInvoker(new class () implements ContainerInterface {
        public function get(string $id)
        {
            return new Fixtures\BlankRequestHandler();
        }

        public function has(string $id): bool
        {
            return Fixtures\BlankRequestHandler::class === $id;
        }
    });
    t\assertSame('123', $handler('\\debugFormat', ['value' => 123]));
    t\assertInstanceOf(ResponseInterface::class, $handler(
        '\\'.Fixtures\BlankRequestHandler::class.'@handle',
        [ServerRequestInterface::class => new ServerRequest('GET', '/a')]
    ));
});

test('if route invoker can parse a resource handler with parameters', function (string $method): void {
    $handler = new RouteHandler(new Psr17Factory());
    $resource = new ResourceHandler(new class () {
        public function getHandler(string $method): string
        {
            return 'I am in a '.$method.' request';
        }

        public function postHandler(string $method): string
        {
            return 'I am in a '.$method.' request';
        }
    }, 'handler');

    $req = (new ServerRequest($method, '/a'))->withAttribute(
        Router::class,
        ['handler' => $resource, 'arguments' => \compact('method')]
    );

    if ('PUT' === $method) {
        $this->expectExceptionObject(new InvalidControllerException('Method putHandler() for resource route '));
    }

    t\assertInstanceOf(ResponseInterface::class, $res = $handler->handle($req));
    t\assertSame('I am in a '.$method.' request', (string) $res->getBody());
    t\assertSame('text/plain', $res->getHeaderLine('Content-Type'));
    t\assertSame(200, $res->getStatusCode());
})->with(['GET', 'POST', 'PUT']);
