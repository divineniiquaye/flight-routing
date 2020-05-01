<?php

declare(strict_types=1);

/*
 * This code is under BSD 3-Clause "New" or "Revised" License.
 *
 * PHP version 7 and above required
 *
 * @category  RoutingManager
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * @link      https://www.biurad.com/projects/routingmanager
 * @since     Version 0.1
 */

namespace Flight\Routing\Services;

use Flight\Routing\Interfaces\PublisherInterface;
use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use Psr\Http\Message\StreamInterface, LogicException;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

/**
 * Class StreamPublisher.
 * StreamPublisher publishes the given response.
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
        $content = empty($content) ? '' : $content;

        if (null !== $emitter && $content instanceof PsrResponseInterface) {
            return $emitter->emit($content);
        }

        if (null === $emitter && $content instanceof PsrResponseInterface) {
            $this->emitResponseHeaders($content);
            $content = $content->getBody();
        }

        if ($content instanceof StreamInterface) {
            return $this->emitStreamBody($content);
        }

        throw new LogicException('The response body must be instance of PsrResponseInterface\StreamInterface');
    }

    /**
     * Emit the message body.
     * @param StreamInterface $body
     * @return bool
     */
    private function emitStreamBody(StreamInterface $body) : bool
    {
        if ($body->isSeekable()) {
            $body->rewind();
        }

        if (! $body->isReadable()) {
            echo $body;
            return true;
        }

        while (! $body->eof()) {
            echo $body->read(8192);
        }

        return true;
    }

    /**
     * Emit the response header.
     * @param PsrResponseInterface $response
     */
    private function emitResponseHeaders(PsrResponseInterface $response) : void
    {
        $statusCode = $response->getStatusCode();

        foreach ($response->getHeaders() as $name => $values) {
            $name  = ucwords($name, '-'); // Filter a header name to wordcase
            $first = $name !== 'Set-Cookie';

            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), $first, $statusCode);
                $first = false;
            }
        }
    }
}
