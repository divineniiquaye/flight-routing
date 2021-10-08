<div align="center">

# The PHP HTTP Flight Router

[![PHP Version](https://img.shields.io/packagist/php-v/divineniiquaye/flight-routing.svg?style=flat-square&colorB=%238892BF)](http://php.net)
[![Latest Version](https://img.shields.io/packagist/v/divineniiquaye/flight-routing.svg?style=flat-square)](https://packagist.org/packages/divineniiquaye/flight-routing)
[![Workflow Status](https://img.shields.io/github/workflow/status/divineniiquaye/flight-routing/Tests?style=flat-square)](https://github.com/divineniiquaye/flight-routing/actions?query=workflow%3ATests)
[![Code Maintainability](https://img.shields.io/codeclimate/maintainability/divineniiquaye/flight-routing?style=flat-square)](https://codeclimate.com/github/divineniiquaye/flight-routing)
[![Coverage Status](https://img.shields.io/codecov/c/github/divineniiquaye/flight-routing?style=flat-square)](https://codecov.io/gh/divineniiquaye/flight-routing)
[![Psalm Type Coverage](https://img.shields.io/endpoint?style=flat-square&url=https%3A%2F%2Fshepherd.dev%2Fgithub%2Fdivineniiquaye%2Frade-di%2Fcoverage)](https://shepherd.dev/github/divineniiquaye/flight-routing)
[![Quality Score](https://img.shields.io/scrutinizer/g/divineniiquaye/flight-routing.svg?style=flat-square)](https://scrutinizer-ci.com/g/divineniiquaye/flight-routing)

</div>

---

**divineniiquaye/flight-routing** is a HTTP router for [PHP] 7.4+ based on [PSR-7] and [PSR-15] with support for annotations, created by [Divine Niiquaye][@divineniiquaye]. This library helps create a human friendly urls (also more cool & prettier) while allows you to use any current trends of **`PHP Http Router`** implementation and fully meets developers' desires.

[![Xcode](https://xscode.com/assets/promo-banner.svg)](https://xscode.com/divineniiquaye/flight-routing)

## 🏆 Features

- Basic routing (`GET`, `POST`, `PUT`, `PATCH`, `UPDATE`, `DELETE`) with support for custom multiple verbs.
- Regular Expression Constraints for parameters.
- Named routes.
- Generating named routes to [PSR-15] URL.
- Route groups.
- [PSR-15] Middleware (classes that intercepts before the route is rendered).
- Namespaces.
- Advanced route pattern syntax.
- Sub-domain routing and more.
- Restful Routing
- Custom matching strategy

## 📦 Installation & Basic Usage

This project requires [PHP] 7.4 or higher. The recommended way to install, is via [Composer]. Simply run:

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

### Getting Started

---

This library uses any [PSR-7] implementation, for the purpose of this tutorial, we wil use [biurad-http-galaxy] library to provide [PSR-7] complaint request, stream and response objects to your controllers and middleware.

>run this in command line if the package has not be added.

```bash
composer require biurad/http-galaxy
```

Route supports adding a scheme, host, pattern and handler all in one. a scheme must end with **:**, while a domain must begin with **//** e.g. `http://biurad.com/blog/{slug}*<App\Controller\BlogController@indexAction>`. Incase a class name or class object is passed into route's handler parameter, you can specify and callable method as `*<indexAction>` or  just route to a function using same syntax as callable method.

For dispatching a route handler response to the browser, use an instance of `Laminas\HttpHandlerRunner\Emitter\EmitterInterface` to dispatch the router.

>run this in command line if the package has not be added.

```bash
composer require laminas/laminas-httphandlerrunner
```

```php
use App\Controller\BlogController;
use Biurad\Http\Factory\NyholmPsr7Factory as Psr17Factory;
use Flight\Routing\{RouteCollection, RouteMatch};

$routes = new RouteCollection();
$routes->add(new Route('/blog/{slug}*<indexAction>', handler: BlogController::class))->bind('blog_show');

$psr17Factory = new Psr17Factory();
$matcher = new RouteMatcher($routes);

// Routing can match routes with incoming request
$route = $matcher->matchRequest($psr17Factory->fromGlobalRequest());
// Should return a route class object, if request is made on a path like: /blog/lorem-ipsum

// Routing can also generate URLs for a given route
$url = $matcher->generateUri('blog_show', ['slug' => 'my-blog-post']);
// $url = '/blog/my-blog-post' if stringified else return a GeneratedUri class object

```

To use the router class, Flight Routing has a default route request handler class to use with router. Also there are two ways of using router with collection:

Router one #1:

```php
$router = new Router();

$router->setCollection(static function (RouteCollection $routes): void {
    $routes->add(new Route('/blog/{slug}*<indexAction>', handler: BlogController::class))->bind('blog_show');
});
```

Router two #2:

```php
$router = Router::withCollection();
$router->addRoute((new Route('/blog/{slug}*<indexAction>', handler: BlogController::class))->bind('blog_show'));
```

Default way of dispatching Router's route to web browser:

```php
use App\Controller\BlogController;
use Biurad\Http\Factory\NyholmPsr7Factory as Psr17Factory;
use Flight\Routing\{Handlers\RouteHandler, RouteCollection, Router};
use Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter;

$router->pipe(...); # Add PSR-15 middlewares ...

$handlerResolver = ... // You can add your own route handler resolver else default is null

// Default route handler, a custom request route handler can be used also.
$handler = new RouteHandler($psr17Factory = new Psr17Factory(), $handlerResolver);

// Match routes with incoming request and return a response
$response = $router->process($psr17Factory->fromGlobalRequest(), $handler);

// Send response to the browser ...
(new SapiStreamEmitter())->emit($response);

```

> **NOTE**: Using the default request route handler class has many advantages, features like custom route handler resolver and auto-detection of PSR-7 response content type for plain, html, xml and svg contents are supported.

> The Route class accepts handler types of `Psr\Http\Server\RequestHandlerInterface`, callable, invocable class object, class::method, or array of [class, method].

### Loading Annotated Routes

---

This library is shipped with annotations support, check **Annotation** directory to find out more about collecting annotations into the routes collection's class.

I suggests using [biurad-annotations] to use doctrine annotations and PHP 8 attributes on route classes. You can also create your own implementation to use load annotations using the `Flight\Routing\Annotation\Route` class.

run this in command line if the package has not be added.

```bash
composer require biurad/annotations
```

```php
use Biurad\Annotations\AnnotationLoader;
use Biurad\Http\Factory\NyholmPsr7Factory as Psr17Factory;
use Flight\Routing\Annotation\Listener;
use Flight\Routing\RouteCollection;
use Spiral\Attributes\{AnnotationReader, AttributeReader};

// Setting a reader means spiral/attributes package must exist, if you're on PHP >= 8 a reader is not required.
$reader = new AttributeReader();
$loader = new AnnotationLoader($reader);

$loader->listener(new Listener($routes = new RouteCollection()));
$loader->resource('src/Controller', 'src/Bundle/BundleName/Controller']);
$loader->build(); // Load and cache attributes found for reusability

$matcher = new RouteMatcher($routes);
// or
$router = new Router();
$router->setCollection(static function (RouteCollection $routes) use ($loader): void {
    $annotations = $loader->load('Flight\Routing\Annotation\Route');

    $routes->populate($annotations);
    // or you can use grouping
    $routes->group('', $annotations);
});

```

### Basic Routing

---

This documentation for route pattern is based on [DefaultCompiler] class. Route pattern are path string with curly brace placeholders. Possible placeholder format are:

- `{name}` - required placeholder.
- `{name=foo}` - placeholder with default value.
- `{name:regex}` - placeholder with regex definition.
- `{name:regex=foo}` - placeholder with regex definition and default value.
- `[{name}]` - optional placeholder.

Variable placeholders may contain only word characters (latin letters, digits, and underscore) and must be unique within the pattern. For placeholders without an explicit regex, a variable placeholder matches any number of characters other than '/' (i.e [^/]+).

> **NB:** Do not use digit for placeholder or it's value shouldn't be greater than 31 characters.

Examples:

- `/foo/` - Matches only if the path is exactly '/foo/'. There is no special treatment for trailing slashes, and patterns have to match the entire path, not just a prefix.
- `/user/{id}` - Matches '/user/bob' or '/user/1234!!!' but not '/user/' or '/user' or even '/user/bob/details'.
- `/user/{id:[^/]+}` - Same as the previous example.
- `/user[/{id}]` - Same as the previous example, but also match '/user'.
- `/user[/{id}]/` - Same as the previous example, but also match '/user/'.
- `/user/{id:[0-9a-fA-F]{1,8}}` - Only matches if the id parameter consists of 1 to 8 hex digits.
- `/files/{path:.*}` - Matches any URL starting with '/files/' and captures the rest of the path into the parameter 'path'.

Below is a very basic example of setting up a route. First parameter is the url which the route should match - next parameter is a `Closure` or callback function that will be triggered once the route matches.

```php
use Flight\Routing\Route;

$route = new Route('/', ['GET', 'HEAD'], fn () => 'Hello world'});

// Create a new route using $router.
$routes->add($route);
```

### Closure Handler

---

```php
use Flight\Routing\Route;
use Psr\Http\Message\{ResponseFactoryInterface, ServerRequestInterface};

$route = new Route(
    '/{name}',
    Route::DEFAULT_METHODS,
    function (ServerRequestInterface $request, ResponseFactoryInterface $responseFactory) {
        $response = $responseFactory->createResponse()->withHeader('Content-Type', 'text/html; charset=utf-8');
        $response->getBody()->write("hello world");

        return $response;
    }
);
```

### Route Request

---

You can catch the request object like this example:

```php
use Biurad\Http\Response\{EmptyResponse, JsonResponse};
use Flight\Routing\RouteCollection;
use Psr\Http\Message\ServerRequestInterface;

$collector = new RouteCollection();

$collector->get(
    '/',
    function (ServerRequestInterface $request) {
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

$collector->post(
    '/blog/posts',
    function (ServerRequestInterface $request) {
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
use Biurad\Http\Response\{EmptyResponse, HtmlResponse, JsonResponse, TextResponse, RedirectResponse};
use Flight\Routing\RouteCollection;

$collector = new RouteCollection();

$collector
    ->get(
        '/html/1',
        function () {
            return '<html>This is an HTML response</html>';
        }
    );
$collector
    ->get(
        '/html/2',
        function () {
            return new HtmlResponse('<html>This is also an HTML response</html>', 200);
        }
    );
$collector
    ->get(
        '/json',
        function () {
            return new JsonResponse(['message' => 'Unauthorized!'], 401);
        }
    );
$collector
    ->get(
        '/text',
        function () {
            return new TextResponse('This is a plain text...');
        }
    );
$collector
    ->get(
        '/empty',
        function () {
            return new EmptyResponse();
        }
    );

// In case of needing to redirecting user to another URL
$collector
    ->get(
        '/redirect',
        function () {
            return new RedirectResponse('https://biurad.com');
        }
    );
```

## Available Methods in RouteCollection

Here you can see how to declare different routes with different http methods:

```php
use Flight\Routing\RouteCollection;

$collector = new RouteCollection();

$collector
    ->head('/', function () {
        return '<b>HEAD method</b>';
    });
$collector
    ->get('/', function () {
        return '<b>GET method</b>';
    });
$collector
    ->post('/', function () {
        return '<b>POST method</b>';
    });
$collector
    ->patch('/', function () {
        return '<b>PATCH method</b>';
    });
$collector
    ->put('/', function () {
        return '<b>PUT method</b>';
    });
$collector
    ->options('/', function () {
        return '<b>OPTIONS method</b>';
    });
$collector
    ->delete('/', function () {
        return '<b>DELETE method</b>';
    });
```

### Multiple HTTP-Verbs

---

Sometimes you might need to create a route that accepts multiple HTTP-verbs. If you need to match all HTTP-verbs you can use the `any` method.

```php
$collector->addRoute('/', ['get', 'post'], function() {
  // ...
});

$collector->any('foo', function() {
  // ...
});
```

### Route Pattern and Parameters

---

You can use route pattern to specify any number of required and optional parameters, these parameters will later be passed
to our route handler via `ServerRequestInterface` attribute `Flight\Routing\Route::class`.

Use the `{parameter_name:pattern}` form to define a route parameter, where pattern is a regexp friendly expression. You can
omit pattern and just use `{parameter_name}`, in this case the parameter will match `[^\/]+`.

### Required Parameters

---

You'll properly wondering by know how you parse parameters from your urls. For example, you might want to capture the users id from an url. You can do so by defining route-parameters.

```php
$collector->get('/user/{userId}', function ($userId) {
  return 'User with id: ' . $userId;
});
```

You may define as many route parameters as required by your route:

```php
$collector->get('/posts/{postId}/comments/{commentId}', function ($postId, $commentId) {
  // ...
});
```

### Optional Parameters

---

Occasionally you may need to specify a route parameter, but make the presence of that route parameter optional. Use `[]` to make a part of route (including the parameters) optional, for example:

```php
// Optional parameter
$collector->get('/user[/{name}]', function ($name = null) {
  return $name;
});
//or
$collector->get('/user[/{name}]', function ($name) {
  return $name;
});
```

```php
// Optional parameter with default value
$collector->get('/user/[{name}]', function ($name = 'Simon') {
  return $name;
});
//or
$collector->get('/user/[{name=Simon}]', function ($name) {
  return $name;
});
//or with rule
$collector->get('/user/[{name:\w+=Simon}]', function ($name) {
  return $name;
});
```

Obviously, if a parameter is inside an optional sequence, it's optional too and defaults to `null`. Sequence should define it's surroundings, in this case a slash which must follow a parameter, if set. The technique may be used for example for optional language subdomains:

```php
$collector->get('//[{lang=en}.]example.com/hello', ...);
```

Sequences may be freely nested and combined:

```php
$collector->get('[{lang:[a-z]{2}}[-{sublang}]/]{name}[/page-{page=0}]', ...);

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
$collector->get('/user/{name}', function ($name) {
    //
})->assert('name', '[A-Za-z]+');

$collector->get( '/user/{id}', function (int $id) {
    //
})->assert('id', '[0-9]+');

$collector->get('/user/{id}/{name}', function (int $id, string $name) {
    //
})->assert('id', '[0-9]+')->assert('name', '[a-z]+');

$collector->get('/user/{id:[0-9]+}/{name:[a-z]+}', function (int $id, string $name) {
    //
});
```

### Named Routes

---

Named routes allow the convenient generation of URLs or redirects for specific routes. It is mandatory to specify a name for a route by chaining the name onto the first argument of route definition:

```php
$collector->get('/user/profile', function () {
    // Your code here
}->bind('profile');
```

You can also specify names for grouping route with a prefixed name:

```php
use Flight\Routing\RouteCollection;

$collector->group('user.', function (RouteCollection $group) {
    $group->get('/user/profile', 'UserController@profile')->bind('profile');
}); // Will produce "user.profile"
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
$collector->get('/user/{id}/profile', function ($id) {
    //
})->bind('profile');

$url = $router->generateUri('profile', ['id' => 1]); // will produce "user/1/profile"
// or
$url = $router->generateUri('profile', [1]); // will produce "user/1/profile"
```

### Route Groups

---

Route groups allow you to share route attributes, such as middlewares, namespace, domain, name, prefix, patterns, or defaults, across a large number of routes without needing to define those attributes on each individual route. Shared attributes are specified in route method prefixed with a `with` name to the `$collector->group` method.

```php
use Flight\Routing\Interfaces\RouteCollection;

$group = $collector->group(
    'group_name',
    function (RouteCollection $route) {
        // Define your routes using $route...
    }
);

// eg: $group->withPrefix(...), $group->withMethod(...), etc.
```

### Route Middlewares

---

Router supports middleware, you can use it for different purposes like authentication, authorization, throttles and so forth. Middleware run before controllers and it can check and manipulate http requests and response.:

Here you can see the request lifecycle considering some middleware:

```text
Input --[Request]↦ Router ↦ Middleware 1 ↦ ... ↦ Middleware N ↦ Controller
                                                                      ↧
Output ↤[Response]- Router ↤ Middleware 1 ↤ ... ↤ Middleware N ↤ [Response]
```

We using [laminas-stratigility] to allow better and saver middleware usage.

run this in command line if the package has not be added.

```bash
composer require laminas/laminas-stratigility
```

To declare a middleware, you must implements Middleware `Psr\Http\Server\MiddlewareInterface` interface.

Middleware must have a `process()` method that catches http request and a request handler (which runs the next middleware or the controller) and it returns a response at the end. Middleware can break the lifecycle and return a response itself or it can run the `$handler` implementing `Psr\Http\Server\RequestHandlerInterface` to continue lifecycle.

For example see the following snippet. In this snippet, we will demonstrate how a middleware works:

```php
use Flight\Routing\Route;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};

$collector->get(
    '/{param}',
    function (ServerRequestInterface $request, ResponseInterface $response) {
        return $request->getAttribute(Route::class)->getArguments();
    }
))->bind('watch');
```

where `ParamWatcher` middleware is:

```php
namespace Demo\Middleware;


use Demo\Exception\UnauthorizedException;
use Flight\Routing\Route;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

class ParamWatcher implements MiddlewareInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $arguments = $request->getAttribute(Route::class)->getArguments();

        if ($arguments['param'] === 'forbidden') {
           throw new UnauthorizedException();
        }

        return $handler->handle($request);
    }
}
```

This route will trigger Unauthorized exception on `/forbidden`.

> The default way of associating a middleware to a route is via the `Flight\Routing\Router::pipe` method. since route is present in server request attributes, you can create a middleware to work on only a particular named route(s). Again, you can add as many middlewares as you want. Middlewares can be implemented using closures but it doesn’t make sense to do so!

### Multiple Routes

---

Flight Routing supports **MRM (Multiple Routes Match)**. This increases SEO (search engine optimization) as it prevents multiple URLs to link to different content (without a proper redirect), the **MRM** feature is to serve static routes first, making other routes declared reachable.

This feature was not fully implemented due to performance reasons, Route collection will return a list of routes starting with static routes eg: '/hello/world'. Make sure to avoid situations where dynamic route `/hello/{world}` matches a condition such as `/[{foo}/{bar}]` or `/[{foo}/{bar}]`.

```php
use Flight\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// this route will be trigger after static routes.
$collector->get(
    '/{param}',
    function (ServerRequestInterface $request, ResponseInterface $response) {
        return $request->getAttribute(Route::class)->getArguments();
    }
))

// this route will be trigger first
$collector->get(
    '/hello',
    function (ServerRequestInterface $request, ResponseInterface $response) {
        return $request->getAttribute(Route::class)->getArguments();
    }
))
```

### Subdomain Routing

---

Route groups may also be used to handle sub-domain routing. The sub-domain may be specified using the `domain` key on the group attribute array:

```php
use Flight\Routing\Interfaces\RouteCollection;

// Domain
$collector->get('/', 'Controller::staticMethod')->domain('domain.com');

// Subdomain
$collector->get('/', 'function_handler')->domain('server2.domain.com');

// Subdomain regex pattern
$collector->get('/', ['Controller', 'method'])->domain('{accounts:.*}.domain.com');

$collector->group(function (RouteCollection $route) {
    $route->get('/user/{id}', function ($id) {
        //
    });
})->domain('account.myapp.com');
```

### RESTful Routing

---

All of `Flight\Routing\Route` has a restful implementation, which specifies the method selection behavior. use `Flight\Routing\Handlers\ResourceHandler` class receiving the real handler, or use `Flight\Routing\RouteCollection::resource` method to automatically prefix all the methods in `Flight\Routing\Router::HTTP_METHODS_STANDARD` with HTTP verb.

For example, we can use the following controller:

```php
namespace Demo\Controller;

class UserController
{
    public function getUser(int $id): string
    {
        return "get {$id}";
    }

    public function postUser(int $id): string
    {
        return "post {$id}";
    }

    public function deleteUser(int $id): string
    {
        return "delete {$id}";
    }
}
```

Add route using `Flight\Routing\Handlers\ResourceHandler`:

```php
use Demo\UserController;
use Flight\Routing\Handlers\ResourceHandler;

$route = new Route('/user/{id:\d+}', ['GET', 'POST'], new ResourceHandler(UserController::class, 'user'));

// Using the `ResourceHandler` class means, the route is restful, the "user" passed into resource handler second parameter, is to be prefixed on class object method. Eg: getUser() which can be served on uri like /user/23
```

Add route using `Flight\Routing\RouteCollection::resource`, doing this means, route becomes available for all standard request methods:

```php
use Demo\UserController;

$collector->resource('/user/{id:\d+}', UserController::class, 'user');
```

> Invoking `/user/1` with different HTTP methods will call different controller methods. Note, you still need
> to specify the action name.

### Custom Route Compiler

---

If these offered route pattern do not fit your needs, you may create your own route compiler. Route matching is nothing more than an implementation of [RouteCompilerInterface](https://github.com/divineniiquaye/flight-routing/blob/master/src/Interfaces/RouteCompilerInterface.php). Your custom compiler must fit in the rules of the [DefaultCompiler]:

```php
use Flight\Routing\Generator\GeneratedUri;
use Flight\Routing\Routes\Route;
use Flight\Routing\Interfaces\RouteCompilerInterface;

class MyRouteCompiler implements RouteCompilerInterface
{
    /**
     * {@inheritdoc}
     */
    public function compile(Route $route): array
    {
        if (!empty($hosts = $route->get('domain'))) {
            $hostsRegex = ... // Compile host if supported else, return an empty array
        }

        $pathRegex = ... // Compile path and return the regex excluding anything starting ^ and ending $.
        $hostRegexs = ... // Compile route hosts and return the regex excluding anything starting ^ and ending $.
        $variables = ... // A merged array from $hostsRegex and $pathRegex.

        return [$pathRegex, $hostRegexps, $variables]; // The results ...
    }

    /**
     * {@inheritdoc}
     */
    public function generateUri(Route $route, array $parameters): GeneratedUri
    {
        // Same as compile method implementation or may differ, result should reverse the route and/or domain
        // patterns into URI.

        return new GeneratedUri(...); // The results ...
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

(This policy may change in the future and exceptions may be made on a case-by-case basis.)

- A new **patch version released** (e.g. `1.0.10`, `1.1.6`) comes out roughly every month. It only contains bug fixes, so you can safely upgrade your applications.
- A new **minor version released** (e.g. `1.1`, `1.2`) comes out every six months: one in June and one in December. It contains bug fixes and new features, but it doesn’t include any breaking change, so you can safely upgrade your applications;
- A new **major version released** (e.g. `1.0`, `2.0`, `3.0`) comes out every two years. It can contain breaking changes, so you may need to do some changes in your applications before upgrading.

When a **major** version is released, the number of minor versions is limited to five per branch (X.0, X.1, X.2, X.3 and X.4). The last minor version of a branch (e.g. 1.4, 2.4) is considered a **long-term support (LTS) version** with lasts for more that 2 years and the other ones cam last up to 8 months:

**Get a professional support from [Biurad Lap][] after the active maintenance of a released version has ended**.

## 🧪 Testing

```bash
$ ./vendor/bin/phpunit
```

This will tests divineniiquaye/rade-di will run against PHP 7.4 version or higher.

## 🏛️ Governance

This project is primarily maintained by [Divine Niiquaye Ibok][@divineniiquaye]. Contributions are welcome 👷‍♀️! To contribute, please familiarize yourself with our [CONTRIBUTING] guidelines.

To report a security vulnerability, please use the [Biurad Security](https://security.biurad.com). We will coordinate the fix and eventually commit the solution in this project.

## 🙌 Sponsors

Are you interested in sponsoring development of this project? Reach out and support us on [Patreon](https://www.patreon.com/biurad) or see <https://biurad.com/sponsor> for a list of ways to contribute.

## 👥 Credits & Acknowledgements

- [Divine Niiquaye Ibok][@divineniiquaye]
- [Anatoly Fenric][]
- [All Contributors][]

Version 1.0 of this code was partly a referenced implementation of [Sunrise Http Router][] which is written, maintained and copyrighted by [Anatoly Fenric][]. Starting from version 2.0 a referenced implementation was taken from [Symfony Routing Component][].

## 📄 License

The **divineniiquaye/flight-routing** library is copyright © [Divine Niiquaye Ibok](https://divinenii.com) and licensed for use under the [![Software License](https://img.shields.io/badge/License-BSD--3-brightgreen.svg?style=flat-square)](./LICENSE).

[Composer]: https://getcomposer.org
[PHP]: https://php.net
[PSR-7]: http://www.php-fig.org/psr/psr-6/
[PSR-11]: http://www.php-fig.org/psr/psr-11/
[PSR-15]: http://www.php-fig.org/psr/psr-15/
[@divineniiquaye]: https://github.com/divineniiquaye
[docs]: https://docs.biurad.com/flight-routing
[commit]: https://commits.biurad.com/flight-routing.git
[UPGRADE]: UPGRADE-1.x.md
[CHANGELOG]: CHANGELOG-1.x.md
[CONTRIBUTING]: ./.github/CONTRIBUTING.md
[All Contributors]: https://github.com/divineniiquaye/flight-routing/contributors
[Biurad Lap]: https://team.biurad.com
[email]: support@biurad.com
[message]: https://projects.biurad.com/message
[biurad-annotations]: https://github.com/biurad/annotations
[biurad-http-galaxy]: https://github.com/biurad/php-http-galaxy
[DefaultCompiler]: https://github.com/divineniiquaye/flight-routing/blob/master/src/RouteCompiler.php
[Anatoly Fenric]: https://anatoly.fenric.ru/
[Sunrise Http Router]: https://github.com/sunrise-php/http-router
[Symfony Routing Component]: https://github.com/symfony/routing
