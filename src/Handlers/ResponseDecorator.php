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

namespace Flight\Routing\Handlers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Provides ability to detect right response content-type.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class ResponseDecorator implements RequestHandlerInterface
{
    public const CONTENT_TYPE = 'Content-Type';

    /** @var ResponseInterface */
    private $response;

    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    /**
     * Convert endpoint result into valid PSR 7 response.
     * content-type fallback is "text/html; charset=utf-8".
     *
     * {@inheritdoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $contents = (string) $this->response->getBody();

        if ($this->isJson($contents)) {
            return $this->response->withHeader(self::CONTENT_TYPE, 'application/json');
        }

        if ($this->isXml($contents)) {
            return $this->response->withHeader(self::CONTENT_TYPE, 'application/xml; charset=utf-8');
        }

        // Set content-type to plain text if string doesn't contain </html> tag.
        if (0 === \preg_match('/(.*)(<\/html[^>]*>)/i', $contents)) {
            return $this->response->withHeader(self::CONTENT_TYPE, 'text/plain; charset=utf-8');
        }

        if (!$this->response->hasHeader(self::CONTENT_TYPE)) {
            $response = $this->response->withHeader(self::CONTENT_TYPE, 'text/html; charset=utf-8');
        }

        return $response;
    }

    private function isJson(string $contents): bool
    {
        \json_decode($contents, true);

        return \JSON_ERROR_NONE === \json_last_error();
    }

    private function isXml(string $contents): bool
    {
        $previousValue = \libxml_use_internal_errors(true);
        $isXml = \simplexml_load_string($contents);
        \libxml_use_internal_errors($previousValue);

        return false !== $isXml && false !== \strpos($contents, '<?xml');
    }
}
