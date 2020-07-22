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

namespace Flight\Routing\Services;

use Flight\Routing\Interfaces\PublisherInterface;
use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use LogicException;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Class StreamPublisher.
 * StreamPublisher publishes the given response.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class HttpPublisher implements PublisherInterface
{
    /**
     * {@inheritdoc}
     *
     * @throws LogicException
     */
    public function publish($content, ?EmitterInterface $emitter): bool
    {
        try {
            if (null !== $emitter && $content instanceof PsrResponseInterface) {
                return $emitter->emit($content);
            }

            if (null === $emitter && $content instanceof PsrResponseInterface) {
                $this->emitResponseHeaders($content);
                $content = $content->getBody();
            }
    
            \flush();

            if ($content instanceof StreamInterface) {
                return $this->emitStreamBody($content);
            }
        } finally {
            if (\function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        }

        throw new LogicException('The response body must be instance of PsrResponseInterface or StreamInterface');
    }

    /**
     * Emit the message body.
     *
     * @param StreamInterface $body
     *
     * @return bool
     */
    private function emitStreamBody(StreamInterface $body): bool
    {
        if ($body->isSeekable()) {
            $body->rewind();
        }

        if (!$body->isReadable()) {
            echo $body;

            return true;
        }

        while (!$body->eof()) {
            echo $body->read(8192);
        }

        return true;
    }

    /**
     * Emit the response header.
     *
     * @param PsrResponseInterface $response
     */
    private function emitResponseHeaders(PsrResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        foreach ($response->getHeaders() as $name => $values) {
            $name  = \ucwords($name, '-'); // Filter a header name to wordcase
            $first = $name !== 'Set-Cookie';

            foreach ($values as $value) {
                \header(\sprintf('%s: %s', $name, $value), $first, $statusCode);
                $first = false;
            }
        }
    }
}
