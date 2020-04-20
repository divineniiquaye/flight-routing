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

use Flight\Routing\Interfaces\RouteInterface;
use DomainException;
use LogicException;
use InvalidArgumentException;

use function substr;
use function str_replace;
use function preg_replace_callback;
use function array_merge;
use function strpbrk;
use function trim;
use function mb_substr;
use function array_unique;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function in_array;
use function sprintf;
use function strlen;
use function count;
use function strpos;
use function array_reverse;
use function rtrim;
use function substr_replace;
use function str_repeat;

/**
 * RouteCompiler compiles Route instances to regex.
 *
 * Based on https://github.com/symfony/routing/blob/master/RouteCompiler.php by Fabien
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
 */
class SymfonyRouteCompiler
{
    const REGEX_DELIMITER = '#';
    const DEFAULT_DISPATCH_REGEX = '[^/]++';

    /**
     * This string defines the characters that are automatically considered separators in front of
     * optional placeholders (with default and no static text following). Such a single separator
     * can be left out together with the optional placeholder from matching and generating URLs.
     */
    const SEPARATORS = '/,;.:-_~+*=@|';

    /**
     * The maximum supported length of a PCRE subpattern name
     * http://pcre.org/current/doc/html/pcre2pattern.html#SEC16.
     *
     * @internal
     */
    const VARIABLE_MAXIMUM_LENGTH = 32;

    private $variables;
    private $tokens;
    private $staticPrefix;
    private $regex;
    private $pathVariables;
    private $hostVariables;
    private $hostRegex;
    private $hostTokens;
    private $staticRegex;

    /**
     * Sets the pattern for the path.
     *
     * Based on https://github.com/symfony/routing/blob/master/Route.php by Fabien
     */
    protected function fixRouteUri(RouteInterface $route, bool $isHost = false): array
    {
        // The path pattern or host pattern
        $pattern = $isHost ? $route->getDomain() : $route->getPath();

        if (false !== strpbrk($pattern, '?<')) {
            $pattern = preg_replace_callback('#\{(\w++)(<.*?>)?(\?[^\}]*+)?\}#', function ($match) use ($isHost, $route) {
                if (isset($match[3][0])) {
                    if (false !== $isHost) {
                        $route->addDefaults([$match[1] => '?' !== $match[3] ? substr($match[3], 1) : null]);
                    } else {
                        $route->addDefaults([$match[1] => null]);
                        $route->setArgument($match[1], '?' !== $match[3] ? substr($match[3], 1) : null);
                    }
                }
                if (isset($match[2][0])) {
                    $route->setPattern($match[1], substr($match[2], 1, -1));
                }

                return '{'.$match[1].'}';
            }, $pattern);
        }

        // Get the final Defaults from $route.
        $defaults = $route->getDefaults();
        $this->staticRegex = $pattern;

        // Place Defaults on Uri.
        $pattern = preg_replace_callback('/\??\{(.*?)\??\}/', function ($match) use (&$defaults, $isHost) {
            return (isset($defaults[$match[1]]) || $isHost) ? $defaults[$match[1]] : $match[0];
        }, ($isHost || '/' === $pattern) ? $pattern : trim($pattern, '/'));

        // A the finalised $route and $pattern
        return [$route, !$isHost ? str_replace("\\", '/', $pattern) : $pattern];
    }

    /**
     * Get the route requirements.
     *
     * @return  array
     */
    protected function getRouteRequirements(array $requirements)
    {
        $newParamters = [];
        foreach ($requirements as $key => $regex) {
            $newParamters[$key] = $this->sanitizeRequirement($key, $regex);
        }

        return $newParamters;
    }

    /**
     * Match the RouteInterface instance and compiles the current route instance.
     *
     * @throws InvalidArgumentException if a path variable is named _fragment
     * @throws LogicException           if a variable is referenced more than once
     * @throws DomainException          if a variable name starts with a digit or if it is too long to be successfully used as
     *                                   a PCRE subpattern
     * @throws LogicException           If the Route cannot be compiled because the
     *                                   path or host pattern is invalid
     */
    public function compile(RouteInterface $route): self
    {
        $hostVariables = [];
        $variables = [];
        $hostRegex = null;
        $hostTokens = [];

        if ('' !== $host = $route->getDomain()) {
            [$route, $host] = $this->fixRouteUri($route, true);
            $result = $this->compilePattern($route, $host, true, $this->getRouteRequirements($route->getPatterns()));

            $hostVariables = $result['variables'];
            $variables = $hostVariables;

            $hostTokens = $result['tokens'];
            $hostRegex = $result['regex'];
        }

        [$route, $path] = $this->fixRouteUri($route, false);
        $result = $this->compilePattern($route, $path, false, $this->getRouteRequirements($route->getPatterns()));
        $pathVariables = $result['variables'];

        foreach ($pathVariables as $pathParam) {
            if ('_fragment' === $pathParam) {
                throw new \InvalidArgumentException(sprintf('Route pattern "%s" cannot contain "_fragment" as a path parameter.', $route->getPath()));
            }
        }

        $this->staticPrefix = $result['staticPrefix'];
        $this->regex = $result['regex'];
        $this->tokens = $result['tokens'];
        $this->pathVariables = $pathVariables;
        $this->hostRegex = $hostRegex;
        $this->hostTokens = $hostTokens;
        $this->hostVariables = $hostVariables;
        $this->variables = array_merge($variables, $pathVariables);

        return $this;
    }

    /**
     * Returns the static prefix.
     *
     * @return string The static prefix
     */
    public function getStaticPrefix()
    {
        return $this->staticPrefix;
    }

    /**
     * The default uri regex for matching.
     *
     * @return string The static regex
     */
    public function getStaticRegex()
    {
        return $this->staticRegex;
    }

    /**
     * Returns the regex.
     *
     * @return string The regex
     */
    public function getRegex()
    {
        return $this->regex;
    }

    /**
     * Returns the host regex.
     *
     * @return string|null The host regex or null
     */
    public function getHostRegex()
    {
        return $this->hostRegex;
    }

    /**
     * Returns the tokens.
     *
     * @return array The tokens
     */
    public function getTokens()
    {
        return $this->tokens;
    }

    /**
     * Returns the host tokens.
     *
     * @return array The tokens
     */
    public function getHostTokens()
    {
        return $this->hostTokens;
    }

    /**
     * Returns the variables.
     *
     * @return array The variables
     */
    public function getVariables()
    {
        return array_unique($this->variables);
    }

    /**
     * Returns the path variables.
     *
     * @return array The variables
     */
    public function getPathVariables()
    {
        return $this->pathVariables;
    }

    /**
     * Returns the host variables.
     *
     * @return array The variables
     */
    public function getHostVariables()
    {
        return $this->hostVariables;
    }

    private function sanitizeRequirement(string $key, string $regex)
    {
        if ('' !== $regex && '^' === $regex[0]) {
            $regex = (string) mb_substr($regex, 1); // returns false for a single character
        }

        if ('$' === mb_substr($regex, -1)) {
            $regex = substr($regex, 0, -1);
        }

        if ('' === $regex) {
            throw new InvalidArgumentException(sprintf('Routing requirement for "%s" cannot be empty.', $key));
        }

        return $regex;
    }

    private function compilePattern(RouteInterface $route, string $pattern, bool $isHost, array $requirements): array
    {
        $tokens = [];
        $variables = [];
        $matches = [];
        $pos = 0;
        $defaultSeparator = $isHost ? '.' : '/';
        $useUtf8 = preg_match('//u', $pattern);
        $needsUtf8 = true;

        if (!$needsUtf8 && $useUtf8 && preg_match('/[\x80-\xFF]/', $pattern)) {
            throw new LogicException(sprintf('Cannot use UTF-8 route patterns without setting the "utf8" option for route "%s".', $route->getPath()));
        }
        if (!$useUtf8 && $needsUtf8) {
            throw new LogicException(sprintf('Cannot mix UTF-8 requirements with non-UTF-8 pattern "%s".', $pattern));
        }

        // Match all variables enclosed in "{}" and iterate over them. But we only want to match the innermost variable
        // in case of nested "{}", e.g. {foo{bar}}. This in ensured because \w does not match "{" or "}" itself.
        preg_match_all('#\{(!)?(\w+)\}#', $pattern, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        foreach ($matches as $match) {
            $important = $match[1][1] >= 0;
            $varName = $match[2][0];
            // get all static text preceding the current variable
            $precedingText = substr($pattern, $pos, $match[0][1] - $pos);
            $pos = $match[0][1] + strlen($match[0][0]);

            if (!strlen($precedingText)) {
                $precedingChar = '';
            } elseif ($useUtf8) {
                preg_match('/.$/u', $precedingText, $precedingChar);
                $precedingChar = $precedingChar[0];
            } else {
                $precedingChar = substr($precedingText, -1);
            }
            $isSeparator = '' !== $precedingChar && false !== strpos(static::SEPARATORS, $precedingChar);

            // A PCRE subpattern name must start with a non-digit. Also a PHP variable cannot start with a digit so the
            // variable would not be usable as a Controller action argument.
            if (preg_match('/^\d/', $varName)) {
                throw new DomainException(sprintf('Variable name "%s" cannot start with a digit in route pattern "%s". Please use a different name.', $varName, $pattern));
            }
            if (in_array($varName, $variables)) {
                throw new LogicException(sprintf('Route pattern "%s" cannot reference variable name "%s" more than once.', $pattern, $varName));
            }

            if (strlen($varName) > self::VARIABLE_MAXIMUM_LENGTH) {
                throw new DomainException(sprintf('Variable name "%s" cannot be longer than %s characters in route pattern "%s". Please use a shorter name.', $varName, self::VARIABLE_MAXIMUM_LENGTH, $pattern));
            }

            if ($isSeparator && $precedingText !== $precedingChar) {
                $tokens[] = ['text', substr($precedingText, 0, -strlen($precedingChar))];
            } elseif (!$isSeparator && strlen($precedingText) > 0) {
                $tokens[] = ['text', $precedingText];
            }

            $regexp = $requirements[$varName] ?? self::DEFAULT_DISPATCH_REGEX;
            if (null === $regexp) {
                $followingPattern = (string) substr($pattern, $pos);
                // Find the next static character after the variable that functions as a separator. By default, this separator and '/'
                // are disallowed for the variable. This default requirement makes sure that optional variables can be matched at all
                // and that the generating-matching-combination of URLs unambiguous, i.e. the params used for generating the URL are
                // the same that will be matched. Example: new Route('/{page}.{_format}', ['_format' => 'html'])
                // If {page} would also match the separating dot, {_format} would never match as {page} will eagerly consume everything.
                // Also even if {_format} was not optional the requirement prevents that {page} matches something that was originally
                // part of {_format} when generating the URL, e.g. _format = 'mobile.html'.
                $nextSeparator = $this->findNextSeparator($followingPattern, $useUtf8);
                $regexp = sprintf(
                    '[^%s%s]+',
                    preg_quote($defaultSeparator, self::REGEX_DELIMITER),
                    $defaultSeparator !== $nextSeparator && '' !== $nextSeparator ? preg_quote($nextSeparator, self::REGEX_DELIMITER) : ''
                );
                if (('' !== $nextSeparator && !preg_match('#^\{\w+\}#', $followingPattern)) || '' === $followingPattern) {
                    // When we have a separator, which is disallowed for the variable, we can optimize the regex with a possessive
                    // quantifier. This prevents useless backtracking of PCRE and improves performance by 20% for matching those patterns.
                    // Given the above example, there is no point in backtracking into {page} (that forbids the dot) when a dot must follow
                    // after it. This optimization cannot be applied when the next char is no real separator or when the next variable is
                    // directly adjacent, e.g. '/{x}{y}'.
                    $regexp .= '+';
                }
            } else {
                if (!preg_match('//u', $regexp)) {
                    $useUtf8 = false;
                } elseif (!$needsUtf8 && preg_match('/[\x80-\xFF]|(?<!\\\\)\\\\(?:\\\\\\\\)*+(?-i:X|[pP][\{CLMNPSZ]|x\{[A-Fa-f0-9]{3})/', $regexp)) {
                    throw new LogicException(sprintf('Cannot use UTF-8 route requirements without setting the "utf8" option for variable "%s" in pattern "%s".', $varName, $pattern));
                }
                if (!$useUtf8 && $needsUtf8) {
                    throw new LogicException(sprintf('Cannot mix UTF-8 requirement with non-UTF-8 charset for variable "%s" in pattern "%s".', $varName, $pattern));
                }
                $regexp = self::transformCapturingGroupsToNonCapturings($regexp);
            }

            if ($important) {
                $token = ['variable', $isSeparator ? $precedingChar : '', $regexp, $varName, false, true];
            } else {
                $token = ['variable', $isSeparator ? $precedingChar : '', $regexp, $varName];
            }

            $tokens[] = $token;
            $variables[] = $varName;
        }

        if ($pos < strlen($pattern)) {
            $tokens[] = ['text', substr($pattern, $pos)];
        }

        // find the first optional token
        $firstOptional = PHP_INT_MAX;
        if (!$isHost) {
            for ($i = count($tokens) - 1; $i >= 0; --$i) {
                $token = $tokens[$i];
                // variable is optional when it is not important and has a default value
                if ('variable' === $token[0] && !($token[5] ?? false) && $route->hasDefault($token[3])) {
                    $firstOptional = $i;
                } else {
                    break;
                }
            }
        }

        // compute the matching regexp
        $regexp = '';
        for ($i = 0, $nbToken = count($tokens); $i < $nbToken; ++$i) {
            $regexp .= self::computeRegexp($tokens, $i, $firstOptional);
        }
        $regexp = self::REGEX_DELIMITER.'^'.$regexp.'$'.self::REGEX_DELIMITER.'sD'.($isHost ? 'i' : '');

        // enable Utf8 matching if really required
        if ($needsUtf8) {
            $regexp .= 'u';
            for ($i = 0, $nbToken = count($tokens); $i < $nbToken; ++$i) {
                if ('variable' === $tokens[$i][0]) {
                    $tokens[$i][4] = true;
                }
            }
        }

        return [
            'staticPrefix' => self::determineStaticPrefix($route, $tokens),
            'regex' => $regexp,
            'tokens' => array_reverse($tokens),
            'variables' => $variables,
        ];
    }

    /**
     * Determines the longest static prefix possible for a route.
     */
    private function determineStaticPrefix(RouteInterface $route, array $tokens): string
    {
        if ('text' !== $tokens[0][0]) {
            return ($route->hasDefault($tokens[0][3]) || '/' === $tokens[0][1]) ? '' : $tokens[0][1];
        }

        $prefix = $tokens[0][1];

        if (isset($tokens[1][1]) && '/' !== $tokens[1][1] && false === $route->hasDefault($tokens[1][3])) {
            $prefix .= $tokens[1][1];
        }

        return rtrim($prefix, "/");
    }

    /**
     * Returns the next static character in the Route pattern that will serve as a separator (or the empty string when none available).
     */
    private function findNextSeparator(string $pattern, bool $useUtf8): string
    {
        if ('' == $pattern) {
            // return empty string if pattern is empty or false (false which can be returned by substr)
            return '';
        }
        // first remove all placeholders from the pattern so we can find the next real static character
        if ('' === $pattern = preg_replace('#\{\w+\}#', '', $pattern)) {
            return '';
        }
        if ($useUtf8) {
            preg_match('/^./u', $pattern, $pattern);
        }

        return false !== strpos(static::SEPARATORS, $pattern[0]) ? $pattern[0] : '';
    }

    /**
     * Computes the regexp used to match a specific token. It can be static text or a subpattern.
     *
     * @param array $tokens        The route tokens
     * @param int   $index         The index of the current token
     * @param int   $firstOptional The index of the first optional token
     *
     * @return string The regexp pattern for a single token
     */
    private function computeRegexp(array $tokens, int $index, int $firstOptional): string
    {
        $token = $tokens[$index];
        if ('text' === $token[0]) {
            // Text tokens
            return preg_quote($token[1], self::REGEX_DELIMITER);
        } else {
            // Variable tokens
            if (0 === $index && 0 === $firstOptional) {
                // When the only token is an optional variable token, the separator is required
                return sprintf('%s(?P<%s>%s)?', preg_quote($token[1], self::REGEX_DELIMITER), $token[3], $token[2]);
            } else {
                $regexp = sprintf('%s(?P<%s>%s)', preg_quote($token[1], self::REGEX_DELIMITER), $token[3], $token[2]);
                if ($index >= $firstOptional) {
                    // Enclose each optional token in a subpattern to make it optional.
                    // "?:" means it is non-capturing, i.e. the portion of the subject string that
                    // matched the optional subpattern is not passed back.
                    $regexp = "(?:$regexp";
                    $nbTokens = count($tokens);
                    if ($nbTokens - 1 == $index) {
                        // Close the optional subpatterns
                        $regexp .= str_repeat(')?', $nbTokens - $firstOptional - (0 === $firstOptional ? 1 : 0));
                    }
                }

                return $regexp;
            }
        }
    }

    private function transformCapturingGroupsToNonCapturings(string $regexp): string
    {
        for ($i = 0; $i < strlen($regexp); ++$i) {
            if ('\\' === $regexp[$i]) {
                ++$i;
                continue;
            }
            if ('(' !== $regexp[$i] || !isset($regexp[$i + 2])) {
                continue;
            }
            if ('*' === $regexp[++$i] || '?' === $regexp[$i]) {
                ++$i;
                continue;
            }
            $regexp = substr_replace($regexp, '?:', $i, 0);
            ++$i;
        }

        return $regexp;
    }
}
