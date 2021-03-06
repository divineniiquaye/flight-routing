# The PHP HTTP Flight Router

[![Latest Version](https://img.shields.io/packagist/v/divineniiquaye/flight-routing.svg?style=flat-square)](https://packagist.org/packages/divineniiquaye/flight-routing)
[![Software License](https://img.shields.io/badge/License-BSD--3-brightgreen.svg?style=flat-square)](LICENSE)
[![Workflow Status](https://img.shields.io/github/workflow/status/divineniiquaye/flight-routing/Tests?style=flat-square)](https://github.com/divineniiquaye/flight-routing/actions?query=workflow%3ATests)
[![Code Maintainability](https://img.shields.io/codeclimate/maintainability/divineniiquaye/flight-routing?style=flat-square)](https://codeclimate.com/github/divineniiquaye/flight-routing)
[![Coverage Status](https://img.shields.io/codecov/c/github/divineniiquaye/flight-routing?style=flat-square)](https://codecov.io/gh/divineniiquaye/flight-routing)
[![Quality Score](https://img.shields.io/scrutinizer/g/divineniiquaye/flight-routing.svg?style=flat-square)](https://scrutinizer-ci.com/g/divineniiquaye/flight-routing)
[![Sponsor development of this project](https://img.shields.io/badge/sponsor%20this%20package-%E2%9D%A4-ff69b4.svg?style=flat-square)](https://biurad.com/sponsor)

**divineniiquaye/flight-routing** is a HTTP router for [PHP] 7.1+ based on [PSR-7] and [PSR-15] with support for annotations, created by [Divine Niiquaye][@divineniiquaye]. This library helps create a human friendly urls (also more cool & prettier) while allows you to use any current trends of **`PHP Http Router`** implementation and fully meets developers' desires.

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

This project requires [PHP] 7.2 or higher. The recommended way to install, is via [Composer]. Simply run:

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

Please note that the following documentation only covers how to use this router in a project without an existing framework using [DefaultMatcher] class. If you are using a framework or/and a different `Flight\Routing\Interfaces\RouteMatcherInterface` class instance in your project, the implementation varies.

It's not required, but you can set `namespace for classes eg: 'Demo\\Controllers\\';` to prefix all routes with the namespace to your controllers. This will simplify things a bit, as you won't have to specify the namespace for your controllers on each route.

This library uses any [PSR-7] implementation, for the purpose of this tutorial, we wil use [biurad-http-galaxy] library to provide [PSR-7] complaint request, stream and response objects to your controllers and middleware

run this in command line if the package has not be added.

```bash
composer require biurad/http-galaxy
```

Flight routing allows you to call any controller action with namespace using `*<namespace\controller@action>` pattern, also you have have domain on route pattern using `//` followed by the host and path, or add a scheme to the pattern.

For dispatching a router, use an instance of `Laminas\HttpHandlerRunner\Emitter\EmitterInterface` to dispatch the router.

```php
use Flight\Routing\{Router, RouteCollection};
use Biurad\Http\Factory\GuzzleHttpPsr7Factory as Psr17Factory;
use Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter;

$collector = new RouteCollection();

// Add routes
$route = $collector->get('/phpinfo', 'phpinfo'); // Will create a phpinfo route.

// Incase you want to name the route use `bind` method
$route->bind('phpinfo');

// Need to have an idea about php before using this dependency, though it easy to use.
$psr17Factory = new Psr17Factory();

$router = new Router($psr17Factory, $psr17Factory);

/**
 * The default namespace for route-callbacks, so we don't have to specify it each time.
 * Can be overwritten by using the namespace config option on your routes.
 */
$router->setOptions(['namespace' => 'Demo\\Controllers\\']);

// Incase you working with API and need a response served on HTTP OPTIONS request method.
$router->setOptions(['options_skip' => true]);

// All router configurations should be set before adding routes
$router->addRoute(...$collector->getRoutes());

// Start the routing
(new SapiStreamEmitter())->emit($router->handle(Psr17Factory::fromGlobalRequest()));
```

> **NOTE**: If your handler return type isn't instance of ResponseInterface, FLight Routing will choose the best content-type for http response. Returning strings can be a bit of conflict for Flight routing, so it fallback is "text/html", a plain text where isn't xml or doesn't have a <html>...</html> wrapped around contents will return a content-type of text/plain.

> The Route class can accept a handler of type `Psr\Http\Server\RequestHandlerInterface`, callable,invocable class, or array of [class, method]. Simply pass a class or a binding name instead of a real object if you want it to be constructed on demand.

### Loading Annotated Routes

---

This library is shipped with annotations support, check **Annotation** directory to find out more about collecting anotations using `Flight\Routing\Router::loadAnnotation` method.

```php
use Biurad\Annotations\AnnotationLoader;
use Biurad\Http\Factory\GuzzleHttpPsr7Factory as Psr17Factory;
use Flight\Routing\Annotation\Listener;
use Flight\Routing\Router;
use Spiral\Attributes\AnnotationReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\MergeReader;

$loader = new AnnotationLoader(new MergeReader([new AnnotationReader(), new AttributeReader()]));
$loader->attachListener(new Listener());

$loader->attach(
    'src/Controller',
    'src/Bundle/BundleName/Controller',
];

// Need to have an idea about php before using this dependency, though it easy to use.
$psr17Factory = new Psr17Factory();

$router = new Router($psr17Factory, $psr17Factory);
$router->loadAnnotation($loader);

```

### Basic Routing

---

As stated earlier, this documentation for route pattern is based on [DefaultMatcher] class. Route pattern are path string with curly brace placeholders. Possible placeholder format are:

- `{name}` - required placeholder.
- `{name=<foo>}` - placeholder with default value.
- `{name:regex}` - placeholder with regex definition.
- `{name:regex=<foo>}` - placeholder with regex definition and default value.
- `[{name}]` - optionnal placeholder.

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

$route = new Route('/', 'GET|HEAD', fn () => 'Hello world'});

// Create a new route using $router.
$router->addRoute($route);
```

Incase you do not want to use the `Flight\Routing\Router` class, Flight Routing provides option to use only the [DefaultMatcher] while skipping the rest of routes handling processes.

```php
use Flight\Routing\{Route, Router, RouteCollection};
use Flight\Routing\Matchers\{SimpleRouteMatcher, SimpleRouteDumper};
use Biurad\Http\Factory\GuzzleHttpPsr7Factory as Psr17Factory;

$route = new Route('/blog/{slug}', 'GET', BlogController::class);

$routes = new RouteCollection();
$routes->add($route->bind('blog_show'));


$matcher = new SimpleRouteMatcher($routes); // A simple matcher for matching routes
// or
// $matcher = new SimpleRouteDumper($routes); A symfony's style of dumping and matching routes.

// Routing can match routes with incoming requests
$matchedRoute = $matcher->match(new ServerRequest(Router::METHOD_GET, '/blog/lorem-ipsum'));

// Will match and return a $route with new aeguments accesed from $route->getArguments() method.
// [ 'slug' => 'lorem-ipsum']

// Routing can also generate URLs for a given route
$url = $matcher->generateUri('blog_show', [
    'slug' => 'my-blog-post',
]);
// $url = '/blog/my-blog-post'
```

### Closure Handler

---

It is possible to pass the `closure` as route handler, in this case our callable will receive two
arguments: `Psr\Http\Message\ServerRequestInterface` and `Psr\Http\Message\ResponseInterface` by default, including from [PSR-11] container services. The problem with routes with closure handlers are that, it cannot be cached or serialized.

```php
use Flight\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

$route = new Route(
    '/{name}',
    'GET|HEAD',
    function (ServerRequestInterface $request, ResponseInterface $response) {
        $response->getBody()->write("hello world");

        return $response;
    }
);


$router->addRoute($route);
));
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

$router->addRoute(...$collector->getRoutes()); // Add new collection list to $router
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

$router->addRoute(...$collector->getRoutes()); // Add new collection list to $router
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

$router->addRoute(...$collector->getRoutes()); // Add new collection list to $router
```

### Multiple HTTP-Verbs

---

Sometimes you might need to create a route that accepts multiple HTTP-verbs. If you need to match all HTTP-verbs you can use the `any` method.

```php
$collector->addRoute('/', 'get|post', function() {
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
$collector->get('/user/[{name=<Simon>}]', function ($name) {
  return $name;
});
//or with rule
$collector->get('/user/[{name:\w+=<Simon>}]', function ($name) {
  return $name;
});
```

Obviously, if a parameter is inside an optional sequence, it's optional too and defaults to `null`. Sequence should define it's surroundings, in this case a slash which must follow a parameter, if set. The technique may be used for example for optional language subdomains:

```php
$collector->get('//[{lang=<en>}.]example.com/hello', ...);
```

Sequences may be freely nested and combined:

```php
$collector->get('[{lang:[a-z]{2}}[-{sublang}]/]{name}[/page-{page=<0>}]', ...);

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
use Demo\Middleware\ParamWatcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Flight\Routing\Route;

$collector->get(
    'watch',
    '/{param}',
    function (ServerRequestInterface $request, ResponseInterface $response) {
        return $request->getAttribute(Route::class)->getArguments();
    }
))
->middleware(ParamWatcher::class);
```

where `ParamWatcher` is:

```php
namespace Demo\Middleware;


use Flight\Routing\Route;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Biurad\Http\Exceptions\ClientException\UnauthorizedException;

class ParamWatcher implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $arguments = $request->getAttribute(Route::class)->getAttributes();

        if ($arguments['param'] === 'forbidden') {
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

Flight Routing increases SEO (search engine optimization) as it prevents multiple URLs to link to different content (without a proper redirect). If more than one addresses link to the same target, the router choices the first (makes it canonical), (**NB:** static routes priority is highest and runned first), while the other routes are later reached. Thanks to that your page won't have duplicities on search engines and their rank won't be split.

> Router will match all routes in the order they were registered. Make sure to avoid situations where previous route matches the conditions of the following routes. Also static routes are addressed first before any other type of route.

```php
use Flight\Routing\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// this route will be trigger after static routes.
$collector->get(
    '/{param}',
    function (ServerRequestInterface $request, ResponseInterface $response) {
        return $request->getAttribute(Route::class)->getAttributes();
    }
))

// this route will be trigger first
$collector->get(
    '/hello',
    function (ServerRequestInterface $request, ResponseInterface $response) {
        return $request->getAttribute(Route::class)->getAttributes();
    }
))
```

### Subdomain Routing

---

Route groups may also be used to handle sub-domain routing. The sub-domain may be specified using the `domain` key on the group attribute array:

```php
use Flight\Routing\Interfaces\RouteCollection;

// Domain
$collector->get('/', 'Controller@method')->domain('domain.com');

// Subdomain
$collector->get('/', 'Controller:method')->domain('server2.domain.com');

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

All of `Flight\Routing\Route` has a restful implementation, which specifies the method selection behavior. Add a `api://` scheme to a route path, or use `Flight\Routing\RouteCollection::resource` method to automatically prefix all the methods in `Flight\Routing\Router::HTTP_METHODS_STANDARD` with HTTP verb. you can also set `_api` to for prefixed methods, using Route's default method.

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

Add route using `Flight\Routing\Router::addRoute`:

```php
use Demo\UserController;

$router->addRoute(new Route('api://user/user/{id:\d+}', 'GET|POST', UserController::class));

// The api:// means, the route is restful, the first "user" is to be prefixed on class object method. Eg: getUser() and the last part means its gonna be served on uri: /user/23
```

Add route using `Flight\Routing\RouteCollection::resource`:

```php
use Demo\UserController;

$collector->resource('user', '/user/{id:\d+}', UserController::class);
```

> Invoking `/user/1` with different HTTP methods will call different controller methods. Note, you still need
> to specify the action name.

### Custom Route Matcher

---

If these offered route pattern do not fit your needs, you may create your own route matcher and add it to `router`. Router is nothing more than an implementation of [RouteMatcherInterface](https://github.com/divineniiquaye/flight-routing/blob/master/src/Interfaces/RouteMatcherInterface.php) with its three methods:

```php
use Flight\Routing\Interfaces\RouteInterface;
use Flight\Routing\RouteCollection;
use Flight\Routing\Traits\ValidationTrait;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Psr\Http\Message\ServerRequestInterface;

class MyRouteMatcher implements RouteMatcherInterface
{
    use ValidationTrait;

    /** @var Route[] */
    private $routes;

    /**
     * @param RouteCollection|Route[] $collection
     */
    public function __construct($collection)
    {
        if ($collection instanceof RouteCollection) {
            // compile routes from $collection->getRoutes() for matching against request
            $collection = $collection->getRoutes();
        }

        $this->routes = $collection;
        // You can add your compiler instance here, if any.
    }

    /**
     * {@inheritdoc}
     */
    public function match(ServerRequestInterface $request): ?Route
    {
        $requestUri    = $request->getUri();
        $requestMethod = $request->getMethod();
        $requestPath   = \rawurldecode($this->resolvePath($request));

        foreach ($this->routes as $route) {
            // ... compile the route and match using $requestUri, $requestMethod and $requestPath.
        }

        // Return null if route wasn't matched.
        return null;
    }

	/**
     * {@inheritdoc}
     */
    public function generateUri(string $routeName, array $parameters = [], array $queryParams = [])
    {
        static $uriRoute;

        // If this route matcher support caching, then name can be found as key in $this->routes.
        // else you can remove it and only work with code inside the `else` statement.
        if (isset($this->routes[$routeName])) {
            $uriRoute = $this->routes[$routeName];
        } else {
            foreach ($this->routes as $route) {
                if ($routeName === $route->getName()) {
                    $uriRoute = $route;

                    break;
                }
            }
        }

        // ... generate a named route path using $parameters and $queryParams from $uriRoute
    }
}
```

If your custom route matcher support caching you can create a new class implementing [MatchDumperInterface](https://github.com/divineniiquaye/flight-routing/blob/master/src/Interfaces/MatchDumperInterface.php), then extend it to your newly created route matcher.

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
[biurad-http-galaxy]: https://github.com/biurad/php-http-galaxy
[DefaultMatcher]: https://github.com/divineniiquaye/flight-routing/blob/master/src/Matchers/SimpleRouteMatcher.php
[Anatoly Fenric]: https://anatoly.fenric.ru/
[Sunrise Http Router]: https://github.com/sunrise-php/http-router
