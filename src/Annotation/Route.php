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

namespace Flight\Routing\Annotation;

/**
 * Annotation class for @Route().
 *
 * @Annotation
 * @NamedArgumentConstructor
 *
 * On PHP 7.2+ Attributes are supported except you want to use Doctrine annotations:
 * ```php
 *     #[Route('/blog/{_locale}', name: 'blog', defaults: ['_locale' => 'en'])]
 *     class Blog
 *     {
 *         #[Route('/', name: '_index', methods: ['GET', 'HEAD'] schemes: 'https')]
 *         public function index()
 *         {
 *         }
 *         #[Route('/{id}', name: '_post', methods: 'POST' where: ["id" => '\d+'])]
 *         public function show()
 *         {
 *         }
 *     }
 * ```
 *
 * @Target({"CLASS", "METHOD", "FUNCTION"})
 */
#[\Spiral\Attributes\NamedArgumentConstructor]
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final class Route
{
    /** @var array<int,string> */
    public array $methods, $hosts, $schemes;

    /**
     * @param array<int,string>|string $methods
     * @param array<int,string>|string $schemes
     * @param array<int,string>|string $hosts
     * @param array<string,mixed>      $where
     * @param array<string,mixed>      $defaults
     * @param array<string,mixed>      $arguments
     */
    public function __construct(
        public ?string $path = null,
        public ?string $name = null,
        string|array $methods = [],
        string|array $schemes = [],
        string|array $hosts = [],
        public array $where = [],
        public array $defaults = [],
        public array $arguments = [],
        public ?string $resource = null
    ) {
        $this->methods = (array) $methods;
        $this->schemes = (array) $schemes;
        $this->hosts = (array) $hosts;
    }
}
