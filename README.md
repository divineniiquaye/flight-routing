# The PHP HTTP Flight Router

[![Latest Version](https://img.shields.io/packagist/v/divineniiquaye/flight-routing.svg?style=flat-square)](https://packagist.org/packages/divineniiquaye/flight-routing)
[![Software License](https://img.shields.io/badge/License-BSD--3-brightgreen.svg?style=flat-square)](LICENSE)
[![Workflow Status](https://img.shields.io/github/workflow/status/divineniiquaye/flight-routing/Tests?style=flat-square)](https://github.com/divineniiquaye/flight-routing/actions?query=workflow%3ATests)
[![Code Maintainability](https://img.shields.io/codeclimate/maintainability/divineniiquaye/flight-routing?style=flat-square)](https://codeclimate.com/github/divineniiquaye/flight-routing)
[![Coverage Status](https://img.shields.io/codecov/c/github/divineniiquaye/flight-routing?style=flat-square)](https://codecov.io/gh/divineniiquaye/flight-routing)
[![Quality Score](https://img.shields.io/scrutinizer/g/divineniiquaye/flight-routing.svg?style=flat-square)](https://scrutinizer-ci.com/g/divineniiquaye/flight-routing)
[![Sponsor development of this project](https://img.shields.io/badge/sponsor%20this%20package-%E2%9D%A4-ff69b4.svg?style=flat-square)](https://biurad.com/sponsor)

**divineniiquaye/flight-routing** is a HTTP router for [PHP] 7.1+ based on [PSR-7] and [PSR-15] with support for annotations, created by [Divine Niiquaye][@divineniiquaye]. This library helps create a human friendly urls (also more cool & prettier) while allows you to use any current trends of **`PHP Http Router`** implementation and fully meets developers' desires.

## 🏆 Features

- Basic routing (`GET`, `POST`, `PUT`, `PATCH`, `UPDATE`, `DELETE`) with support for custom multiple verbs.
- Regular Expression Constraints for parameters.
- Named routes.
- Generating url to routes.
- Route groups.
- Middleware (classes that intercepts before the route is rendered).
- Namespaces.
- Route prefixes.
- Optional parameters
- Sub-domain routing and more.

## 📦 Installation & Basic Usage

This project requires PHP 7.1 or higher. The recommended way to install, is via [Composer]. Simply run:

```bash
$ composer require divineniiquaye/flight-routing
```

First of all, you need to configure your web server to handle all the HTTP requests with a single PHP file like `index.php`. Here you can see required configurations for Apache HTTP Server and NGINX.

### Setting up Nginx:

> If you are using Nginx please make sure that url-rewriting is enabled.

You can easily enable url-rewriting by adding the following configuration for the Nginx configuration-file for the demo-project.

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### Setting up Apache:

Nothing special is required for Apache to work. We've include the `.htaccess` file in the `public` folder. If rewriting is not working for you, please check that the `mod_rewrite` module (htaccess support) is enabled in the Apache configuration.

```htaccess
<IfModule mod_rewrite.c>
    Options -MultiViews
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>

<IfModule !mod_rewrite.c>
    <IfModule mod_alias.c>
        RedirectMatch 307 ^/$ /index.php/
    </IfModule>
</IfModule>
```

### Setting up IIS:

On IIS you have to add some lines your `web.config` file. If rewriting is not working for you, please check that your IIS version have included the `url rewrite` module or download and install them from Microsoft web site.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <defaultDocument>
            <files>
                <remove value="index.php" />
                <add value="index.php" />
            </files>
        </defaultDocument>
        <rewrite>
            <!-- Remove slash '/' from the en of the url -->
            <rules>
                <rule name="request_filename" stopProcessing="true">
                    <match url="^.*$" ignoreCase="true" />
                    <!-- When requested file or folder don't exists, will request again through index.php -->
                    <conditions logicalGrouping="MatchAll">
                        <add input="{REQUEST_FILENAME}" matchType="IsFile" ignoreCase="false" negate="true" />
                        <add input="{REQUEST_FILENAME}" matchType="IsDirectory" ignoreCase="false" negate="true" />
                    </conditions>
                    <action type="Rewrite" url="index.php" />
                </rule>
            </rules>
        </rewrite>
    </system.webServer>
    <system.web>
        <httpRuntime requestPathInvalidCharacters="&lt;,&gt;,*,%,&amp;,\,?" />
    </system.web>
</configuration>
```

### Configuration

---

Please note that the following snippets only covers how to use this router in a project without an existing framework using [DefaultPilot] class. If you are using a framework or/and a different `Flight\Routing\Interfaces\RouterInterface` class instance in your project, the implementation varies.

It's not required, but you can set `namespace method parameter's value to eg: 'Demo\\Controllers\\';` to prefix all routes with the namespace to your controllers. This will simplify things a bit, as you won't have to specify the namespace for your controllers on each route.

This library uses any [PSR-7] implementation, for the purpose of this tutorial, we wil use [biurad-http] library to provide [PSR-7] complaint request, stream and response objects to your controllers and middleware

run this in command line if the package has not be added.

```bash
composer require biurad/biurad-http
```

Flight routing allows you to call any controller action with namespace using `*<namespace\controller@action>` pattern, also you have have domain on route pattern using `//` followed by the host and path, or add a scheme to the pattern.

> Create a new file, name it `routes.php` and place it in your library folder or any private folder. This will be the file where you define all the routes for your project.

In your `index.php` require your newly-created `routes.php` and call the `$router->handle()` method on [Publisher] `publish` method, passing an instance of [PSR-7] `ServerRequestInterface`. This will trigger and do the actual routing of the requests to response.

```php
<?php

use Flight\Routing\RouteCollector as Router;

return static function (Router &$router): void {
    $router->get('/phpinfo', 'phpinfo'); // Will create a phpinfo route.

    // Add more routes here...
}
```

There are two ways of dispatching a router, either by using the default [Publisher] or use an instance of `Laminas\HttpHandlerRunner\Emitter\EmitterInterface` to dispatch the router.

**This is an example of a basic `index.php` file:**

```php
<?php

use Flight\Routing\Services\HttpPublisher;
use BiuradPHP\Http\Factory\GuzzleHttpPsr7Factory as Psr17Factory;

// Need to have an idea about php before using this dependency, though it easy to use.
$router = new Router(new Psr17Factory);

/**
 * The default namespace for route-callbacks, so we don't have to specify it each time.
 * Can be overwritten by using the namespace config option on your routes.
 */
$router->setNamespace('Demo\\Controllers\\');

/* Load external routes file */
(require_once __DIR__.'/routes.php')($router);

// Start the routing
(new HttpPublisher)->publish($router->handle(Psr17Factory::fromGlobalRequest()));

// or use an instance of `Laminas\HttpHandlerRunner\Emitter\EmitterInterface`
```

Remember the `routes.php` file you required in your `index.php`? This file be where you place all your custom rules for routing.

> **NOTE**: If your handler return type isn't instance of ResponseInterface, FLight Routing will choose the best content-type for http response. Returning strings can be a bit of conflict for Flight routing, so it fallback is "text/html", a plain text where isn't xml or doesn't have a <html>...</html> wrapped around contents will return a content-type of text/plain.

> The Route class can accept a handler of type `Psr\Http\Server\RequestHandlerInterface`, callable,invocable class, or array of [class, method]. Simply pass a class or a binding name instead of a real object if you want it to be constructed on demand.

### Basic routing

---

Below is a very basic example of setting up a route. First parameter is the url which the route should match - next parameter is a `Closure` or callback function that will be triggered once the route matches.

```php
$router->get('/', function() {
  return 'Hello world';
}); // $router from routes.php file.
```

### Closure Handler

---

It is possible to pass the `closure` as route handler, in this case our function will receive two
arguments: `Psr\Http\Message\ServerRequestInterface` and `Psr\Http\Message\ResponseInterface`.

```php
$router->get(
    '/{name}',
    function (ServerRequestInterface $request, ResponseInterface $response) {
        $response->getBody()->write("hello world");

        return $response;
    }
));
```

### Route Request

---

You can catch the request object like this example:

```php
<?php

use BiuradPHP\Http\ServerRequest;
use BiuradPHP\Http\Response\EmptyResponse;
use BiuradPHP\Http\Response\JsonResponse;

$router->get(
    '/',
    function (ServerRequest $request) {
        return new JsonResponse([
            'method'            => $request->getMethod(),
            'uri'               => $request->getUri(),
            'body'              => $request->getBody(),
            'parsedBody'        => $request->getParsedBody(),
            'headers'           => $request->getHeaders(),
            'queryParameters'   => $request->getQueryParams(),
            'attributes'        => $request->getAttributes(),
        ]);
    }
);

$router->post(
    '/blog/posts',
    function (ServerRequest $request) {
        $post           = new \Demo\Models\Post();
        $post->title    = $request->getQueryParams()['title'];
        $post->content  = $request->getQueryParams()['content'];
        $post->save();

        return new EmptyResponse(201);
    }
);
```

### Route Response

---

The example below illustrates supported kinds of responses.

```php
<?php

use BiuradPHP\Http\Response\EmptyResponse;
use BiuradPHP\Http\Response\HtmlResponse;
use BiuradPHP\Http\Response\JsonResponse;
use BiuradPHP\Http\Response\TextResponse;

$router
    ->get(
        '/html/1',
        function () {
            return '<html>This is an HTML response</html>';
        }
    );
$router
    ->get(
        '/html/2',
        function () {
            return new HtmlResponse('<html>This is also an HTML response</html>', 200);
        }
    );
$router
    ->get(
        '/json',
        function () {
            return new JsonResponse(['message' => 'Unauthorized!'], 401);
        }
    );
$router
    ->get(
        '/text',
        function () {
            return new TextResponse('This is a plain text...');
        }
    );
$router
    ->get(
        '/empty',
        function () {
            return new EmptyResponse();
        }
    );

```

### Route Redirection Response

---

In case of needing to redirecting user to another URL:

```php
<?php

use BiuradPHP\Http\Response\RedirectResponse;

$router
    ->get('/redirect', function () {
        return new RedirectResponse('https://biurad.com');
    });
```

## Available Methods

Here you can see how to declare different routes with different http methods:

```php
$router
    ->get('/', function () {
        return '<b>GET method</b>';
    });
$router
    ->post('/', function () {
        return '<b>POST method</b>';
    });
$router
    ->patch('/', function () {
        return '<b>PATCH method</b>';
    });
$router
    ->put('/', function () {
        return '<b>PUT method</b>';
    });
$router
    ->delete('/', function () {
        return '<b>DELETE method</b>';
    });
```

### Multiple HTTP-Verbs

---

Sometimes you might need to create a route that accepts multiple HTTP-verbs. If you need to match all HTTP-verbs you can use the `any` method.

```php
$router->map(['get', 'post'], '/', function() {
  // ...
});

$router->any('foo', function() {
  // ...
});
```

### Route Pattern and Parameters

---

You can use route pattern to specify any number of required and optional parameters, these parameters will later be passed
to our route handler via `ServerRequestInterface` attribute `route`.

Use the `{parameter_name:pattern}` form to define a route parameter, where pattern is a regexp friendly expression. You can
omit pattern and just use `{parameter_name}`, in this case the parameter will match `[^\/]+`.

### Required Parameters

---

You'll properly wondering by know how you parse parameters from your urls. For example, you might want to capture the users id from an url. You can do so by defining route-parameters.

```php
$router->get('/user/{userId}', function ($userId) {
  return 'User with id: ' . $userId;
});
```

You may define as many route parameters as required by your route:

```php
$router->get('/posts/{postId}/comments/{commentId}', function ($postId, $commentId) {
  // ...
});
```

### Optional Parameters

---

Occasionally you may need to specify a route parameter, but make the presence of that route parameter optional. Use `[]` to make a part of route (including the parameters) optional, for example:

```php
// Optional parameter
$router->get('/user[/{name}]', function ($name = null) {
  return $name;
});
//or
$router->get('/user[/{name}]', function ($name) {
  return $name;
});
```

```php
// Optional parameter with default value
$router->get('/user/[{name}]', function ($name = 'Simon') {
  return $name;
});
//or
$router->get('/user/[{name=<Simon>}]', function ($name) {
  return $name;
});
```

Obviously, if a parameter is inside an optional sequence, it's optional too and defaults to `null`. Sequence should define it's surroundings, in this case a slash which must follow a parameter, if set. The technique may be used for example for optional language subdomains:

```php
$router->get('//[{lang=<en>}.]example.com/hello', ...);
```

Sequences may be freely nested and combined:

```php
$router->get('[{lang:[a-z]{2}}[-{sublang}]/]{name}[/page-{page=<0>}]', ...);

// Accepted URLs:
// /cs/hello
// /en-us/hello
// /hello
// /hello/page-12
// /ru/hello/page-12
```

**Note:** Route parameters are always encased within {} braces and should consist of alphabetic characters. Route parameters may not contain a - character. Use an underscore (\_) instead.

### Regular Expression Constraints

---

You may constrain the format of your route parameters using the where method on a route instance. The where method accepts the name of the parameter and a regular expression defining how the parameter should be constrained:

```php
$router->get('/user/{name}', function ($name) {
    //
})->setPattern('name', '[A-Za-z]+');

$router->get('/user/{id}', function (int $id) {
    //
})->setPattern('id', '[0-9]+');

$router->get('/user/{id}/{name}', function (int $id, string $name) {
    //
})->whereArray(['id' => '[0-9]+', 'name' => '[a-z]+']);

$router->get('/user/{id:[0-9]+}/{name:[a-z]+}', function (int $id, string $name) {
    //
});
```

### Named Routes

---

Named routes allow the convenient generation of URLs or redirects for specific routes. You may specify a name for a route by chaining the name method onto the route definition:

```php
$router->get('/user/profile', function () {
    // Your code here
})->setName('profile');
```

You can also specify names for Controller-actions:

```php
$router->get('/user/profile', 'UserController@profile')->setName('profile');
```

### Generating URLs From Named Routes

---

URL generator tries to keep the URL as short as possible (while unique), so what can be omitted is not used. The behavior of generating urls from route depends on the respective parameters sequence given.

Once you have assigned a name to a given route, you may use the route's name, its parameters and maybe add query, when generating URLs:

```php
// Generating URLs...
$url = $router->generateUri('profile');
```

If the named route defines parameters, you may pass the parameters as the second argument to the `url` function. The given parameters will automatically be inserted into the URL in their correct positions:

```php
$router->get('/user/{id}/profile', function ($id) {
    //
})->setName('profile');

$url = $router->generateUri('profile', ['id' => 1]); // will produce "user/1/profile"
```

### Route Groups

---

Route groups allow you to share route attributes, such as middlewares, namespace, domain, name, prefix, patterns, or defaults, across a large number of routes without needing to define those attributes on each individual route. Shared attributes are specified in an array format as the first parameter to the `$router->group` method.

```php
<?php

use Flight\Routing\Interfaces\RouterProxyInterface;

$router->group(
    [...], // Add your group attributes
    function (RouterProxyInterface $route) {
        // Define your routes using $route...
    }
);
```

### Route Middlewares

---

Router supports middleware, you can use it for different purposes like authentication, authorization, throttles and so forth. Middleware run before controllers and it can check and manipulate http requests. To associate route specific middleware use `addMiddleware`, you can access route parameters via `arguments` attribute of the request object:

Here you can see the request lifecycle considering some middleware:

```text
Input --[Request]↦ Router ↦ Middleware 1 ↦ ... ↦ Middleware N ↦ Controller
                                                                      ↧
Output ↤[Response]- Router ↤ Middleware 1 ↤ ... ↤ Middleware N ↤ [Response]
```

We using using [laminas-stratigility] to allow better and saver middleware usage.

run this in command line if the package has not be added.

```bash
composer require laminas/laminas-stratigility
```

To declare a middleware, you must implements Middleware `Psr\Http\Server\MiddlewareInterface` interface.

Middleware must have a `process()` method that catches http request and a closure (which runs the next middleware or the controller) and it returns a response at the end. Middleware can break the lifecycle and return a response itself or it can run the `$handler` implementing `Psr\Http\Server\RequestHandlerInterface` to continue lifecycle.

For example see the following snippet. In this snippet, we will demonstrate how a middleware works:

```php
<?php

use Demo\Middleware\ParamWatcher;

$router->get(
    '/{param}',
    function (ServerRequestInterface $request, ResponseInterface $response) {
        return $request->getAttribute('arguments');
    }
))
->addMiddleware(ParamWatcher::class);
```

where `ParamWatcher` is:

```php
<?php

namespace Demo\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use BiuradPHP\Http\Exceptions\ClientException\UnauthorizedException;

class ParamWatcher implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if ($request->getAttribute('arguments')['param'] === 'forbidden') {
           throw new UnauthorizedException();
        }

        return $handler->handle($request);
    }
}
```

This route will trigger Unauthorized exception on `/forbidden`.

> You can add as many middlewares as you want. Middlewares can be implemented using closures but it doesn’t make sense to do so!

### Multiple Routes

---

Flight Routing increases SEO (search engine optimization) as it prevents multiple URLs to link to different content (without a proper redirect). If more than one addresses link to the same target, the router choices the first (makes it canonical), while the other routes are never reached. Thanks to that your page won't have duplicities on search engines and their rank won't be split.

> Router will match all routes in the order they were registered. Make sure to avoid situations where previous route matches the conditions of the following routes.

```php
$router->get(
    '/{param}',
    function (ServerRequestInterface $request, ResponseInterface $response) {
        return $request->getAttribute('arguments');
    }
))

// this route will never trigger
$router->get(
    '/hello',
    function (ServerRequestInterface $request, ResponseInterface $response) {
        return $request->getAttribute('arguments');
    }
))
```

### Subdomain Routing

---

Route groups may also be used to handle sub-domain routing. The sub-domain may be specified using the `domain` key on the group attribute array:

```php
// Domain
$router->get('/', 'Controller@method')->setDomain('domain.com');

// Subdomain
$router->get('/', 'Controller:method')->setDomain('server2.domain.com');

// Subdomain regex pattern
$router->get('/', ['Controller', 'method'])->setDomain('{accounts:.*}.domain.com');

$router->group(['domain' => 'account.myapp.com'], function (RouterProxyInterface $route) {
    $route->get('/user/{id}', function ($id) {
        //
    });
});
```

### Custom Router Pilot

---

If these offered routes do not fit your needs, you may create your own router pilot and add it to your `router collection`. Router is nothing more than an implementation of [RouterInterface](https://github.com/divineniiquaye/flight-routing/blob/master/src/Interfaces/RouterInterface.php) with its five methods:

```php
<?php

use ArrayIterator;
use Psr\Http\Message\ServerRequestInterface;
use Flight\Routing\Interfaces\RouterInterface;
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\RouteResults;

class MyRouter implements RouterInterface
{
    /** @var array */
    private $routes = [];

    /**
     * {@inheritdoc}
     */
    public function addRoute(RouteInterface $route) : void
    {
        $this->routes[] = $route;
    }

	/**
     * {@inheritdoc}
     */
    public function match(ServerRequestInterface $request): RouteResults
	{
		// ...
	}

    /**
     * {@inheritdoc}
     */
	public function generateUri(RouteInterface $route, array $substitutions = []): string
	{
		// ...
    }

    /**
     * {@inheritdoc}
     */
    public function __clone()
    {
        foreach ($this->routes as $name => $route) {
            $this->routes[$name] = clone $route;
        }
    }

    /**
     * Gets the current RouterInterface as an Iterator that includes all routes.
     *
     * It implements IteratorAggregate.
     *
     * @return ArrayIterator|RouteInterface[] An ArrayIterator object for iterating over routes
     */
    public function getIterator()
    {
        return new ArrayIterator($this->routes);
    }
}
```

## 📓 Documentation

For in-depth documentation before using this library.. Full documentation on advanced usage, configuration, and customization can be found at [docs.biurad.com][docs].

## ⏫ Upgrading

Information on how to upgrade to newer versions of this library can be found in the [UPGRADE].

## 🏷️ Changelog

[SemVer](http://semver.org/) is followed closely. Minor and patch releases should not introduce breaking changes to the codebase; See [CHANGELOG] for more information on what has changed recently.

Any classes or methods marked `@internal` are not intended for use outside of this library and are subject to breaking changes at any time, so please avoid using them.

## 🛠️ Maintenance & Support

When a new **major** version is released (`1.0`, `2.0`, etc), the previous one (`0.19.x`) will receive bug fixes for _at least_ 3 months and security updates for 6 months after that new release comes out.

(This policy may change in the future and exceptions may be made on a case-by-case basis.)

**Professional support, including notification of new releases and security updates, is available at [Biurad Commits][commit].**

## 👷‍♀️ Contributing

To report a security vulnerability, please use the [Biurad Security](https://security.biurad.com). We will coordinate the fix and eventually commit the solution in this project.

Contributions to this library are **welcome**, especially ones that:

- Improve usability or flexibility without compromising our ability to adhere to [PSR-7] and [PSR-15]
- Optimize performance
- Fix issues with adhering to [PSR-7], [PSR-15] and this library

Please see [CONTRIBUTING] for additional details.

## 🧪 Testing

```bash
$ composer test
```

This will tests biurad/php-cache will run against PHP 7.2 version or higher.

## 👥 Credits & Acknowledgements

- [Divine Niiquaye Ibok][@divineniiquaye]
- [Anatoly Fenric][]
- [All Contributors][]

This code is partly a reference implementation of [Sunrise Http Router][] which is written, maintained and copyrighted by [Anatoly Fenric][]. This project new features  starting from version `1.0` was referenced from [Sunrise Http Router][]

## 🙌 Sponsors

Are you interested in sponsoring development of this project? Reach out and support us on [Patreon](https://www.patreon.com/biurad) or see <https://biurad.com/sponsor> for a list of ways to contribute.

## 📄 License

**divineniiquaye/flight-routing** is licensed under the BSD-3 license. See the [`LICENSE`](LICENSE) file for more details.

## 🏛️ Governance

This project is primarily maintained by [Divine Niiquaye Ibok][@divineniiquaye]. Members of the [Biurad Lap][] Leadership Team may occasionally assist with some of these duties.

## 🗺️ Who Uses It?

You're free to use this package, but if it makes it to your production environment we highly appreciate you sending us an [email] or [message] mentioning this library. We publish all received request's at <https://patreons.biurad.com>.

Check out the other cool things people are doing with `divineniiquaye/flight-routing`: <https://packagist.org/packages/divineniiquaye/flight-routing/dependents>

[Composer]: https://getcomposer.org
[PHP]: https://php.net
[PSR-7]: http://www.php-fig.org/psr/psr-6/
[PSR-15]: http://www.php-fig.org/psr/psr-15/
[@divineniiquaye]: https://github.com/divineniiquaye
[docs]: https://docs.biurad.com/flight-routing
[commit]: https://commits.biurad.com/flight-routing.git
[UPGRADE]: UPGRADE-1.x.md
[CHANGELOG]: CHANGELOG-0.x.md
[CONTRIBUTING]: ./.github/CONTRIBUTING.md
[All Contributors]: https://github.com/divineniiquaye/flight-routing/contributors
[Biurad Lap]: https://team.biurad.com
[email]: support@biurad.com
[message]: https://projects.biurad.com/message
[biurad-http]: https://github.com/biurad/biurad-http
[Publisher]: https://github.com/divineniiquaye/flight-routing/blob/master/src/Publisher.php
[DefaultPilot]: https://github.com/divineniiquaye/flight-routing/blob/master/src/Services/DefaultFlightRouter.php
[Anatoly Fenric]: https://anatoly.fenric.ru/
[Sunrise Http Router]: https://github.com/sunrise-php/http-router
