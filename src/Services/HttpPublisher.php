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

use BiuradPHP\Http\Interfaces\EmitterInterface;
use Flight\Routing\Interfaces\PublisherInterface;
use Psr\Http\Message\StreamInterface, LogicException;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

/**
 * Class StreamPublisher
 * StreamPublisher publishes the given response.
 */
class HttpPublisher implements PublisherInterface
{
    /**
     * {@inheritdoc}
     */
    public function publish($content, ?EmitterInterface $emitter)
    {
        $content = empty($content) ? '' : $content;

        if ($content instanceof StreamInterface) {
            return $this->publish($content, $emitter);
        }

        if ($content instanceof PsrResponseInterface) {
            http_response_code($content->getStatusCode());
        }

        if (null !== $emitter) {
            return $emitter->emit($content);
        } elseif (is_null($emitter) && $content instanceof PsrResponseInterface) {
            foreach ($content->getHeaders() as $name => $values) {
                $name  = ucwords($name, '-'); // Filter a header name to wordcase
                $value = $content->getHeaderLine($name);

                $first = $name !== 'Set-Cookie';
                if (true !== $first) {
                    foreach ($values as $cookie) {
                        header(sprintf('%s: %s', $name, $cookie), $first);
                    }
                } elseif ($first) {
                    header($name.': '.$value, $first);
                }
            }
        }

        // We want to make sure that we pass raw content's into empty resopnse.
        if (is_scalar($content) || $content instanceof PsrResponseInterface) {
            $output = fopen('php://output', 'a');

            if ($content instanceof PsrResponseInterface) {
                $content = $content->getBody()->getContents();
            }

            fwrite($output, $content);

            return fclose($output);
        }

        throw new LogicException('The response body must be instance of PsrResponseInterface\StreamInterface or is scalar');
    }
}
