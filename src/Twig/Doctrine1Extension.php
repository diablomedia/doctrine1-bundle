<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle\Twig;

use SqlFormatter;
use Symfony\Component\VarDumper\Cloner\Data;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

use function addslashes;
use function array_key_exists;
use function bin2hex;
use function implode;
use function is_array;
use function is_bool;
use function is_object;
use function is_string;
use function method_exists;
use function preg_match;
use function preg_replace_callback;
use function strtoupper;
use function substr;

/**
 * This class contains the needed functions in order to do the query highlighting
 */
class Doctrine1Extension extends AbstractExtension
{
    /**
     * Escape parameters of a SQL query
     * DON'T USE THIS FUNCTION OUTSIDE ITS INTENDED SCOPE
     *
     * @internal
     *
     * @param mixed $parameter
     */
    public static function escapeFunction($parameter): string
    {
        $result = $parameter;

        switch (true) {
            // Check if result is non-unicode string using PCRE_UTF8 modifier
            case is_string($result) && ! preg_match('//u', $result):
                $result = '0x' . strtoupper(bin2hex($result));
                break;

            case is_string($result):
                $result = "'" . addslashes($result) . "'";
                break;

            case is_array($result):
                foreach ($result as &$value) {
                    $value = static::escapeFunction($value);
                }

                $result = implode(', ', $result);
                break;

            case is_object($result) && method_exists($result, '__toString'):
                $result = addslashes((string) $result);
                break;

            case $result === null:
                $result = 'NULL';
                break;

            case is_bool($result):
                $result = $result ? '1' : '0';
                break;

            case is_int($result):
            case is_float($result):
                $result = (string) $result;
                break;

            default:
                throw new \Exception('Invalid parameter type: ' . gettype($result));
        }

        return $result;
    }

    /**
     * Formats and/or highlights the given SQL statement.
     *
     * @param  bool   $highlightOnly If true the query is not formatted, just highlighted
     */
    public function formatQuery(string $sql, bool $highlightOnly = false): string
    {
        SqlFormatter::$pre_attributes            = 'class="highlight highlight-sql"';
        SqlFormatter::$quote_attributes          = 'class="string"';
        SqlFormatter::$backtick_quote_attributes = 'class="string"';
        SqlFormatter::$reserved_attributes       = 'class="keyword"';
        SqlFormatter::$boundary_attributes       = 'class="symbol"';
        SqlFormatter::$number_attributes         = 'class="number"';
        SqlFormatter::$word_attributes           = 'class="word"';
        SqlFormatter::$error_attributes          = 'class="error"';
        SqlFormatter::$comment_attributes        = 'class="comment"';
        SqlFormatter::$variable_attributes       = 'class="variable"';

        if ($highlightOnly) {
            $html = SqlFormatter::highlight($sql);
            $html = preg_replace('/<pre class=".*">([^"]*+)<\/pre>/Us', '\1', $html);
            if ($html === null) {
                throw new \RuntimeException('Error replacing: ' . preg_last_error_msg());
            }
        } else {
            $html = SqlFormatter::format($sql);
            $html = preg_replace('/<pre class="(.*)">([^"]*+)<\/pre>/Us', '<div class="\1"><pre>\2</pre></div>', $html);
            if ($html === null) {
                throw new \RuntimeException('Error replacing: ' . preg_last_error_msg());
            }
        }

        return $html;
    }

    /**
     * Define our functions
     *
     * @return TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('doctrine1_pretty_query', [$this, 'formatQuery'], ['is_safe' => ['html']]),
            new TwigFilter('doctrine1_replace_query_parameters', [$this, 'replaceQueryParameters']),
        ];
    }

    /**
     * Get the name of the extension
     */
    public function getName(): string
    {
        return 'doctrine1_extension';
    }

    /**
     * Return a query with the parameters replaced
     *
     * @param mixed[]|Data $parameters
     */
    public function replaceQueryParameters(string $query, $parameters): string
    {
        if ($parameters instanceof Data) {
            $parameters = $parameters->getValue(true);
        }

        $i = 0;

        if (!array_key_exists(0, $parameters) && array_key_exists(1, $parameters)) {
            $i = 1;
        }

        $query = preg_replace_callback(
            '/\?|((?<!:):[a-z0-9_]+)/i',
            static function (array $matches) use ($parameters, &$i): string {
                $key = substr($matches[0], 1);

                if (!array_key_exists($i, $parameters) && ($key === false || !array_key_exists($key, $parameters))) {
                    return $matches[0];
                }

                $value = array_key_exists($i, $parameters) ? $parameters[$i] : $parameters[$key];
                $result = self::escapeFunction($value);
                $i++;

                return $result;
            },
            $query
        );

        if ($query === null) {
            throw new \RuntimeException('Error replacing: ' . preg_last_error_msg());
        }

        return $query;
    }

    /**
     * Get the possible combinations of elements from the given array
     */
    private function getPossibleCombinations(array $elements, int $combinationsLevel): array
    {
        $baseCount = count($elements);
        $result    = [];

        if ($combinationsLevel === 1) {
            foreach ($elements as $element) {
                $result[] = [$element];
            }

            return $result;
        }

        $nextLevelElements = $this->getPossibleCombinations($elements, $combinationsLevel - 1);

        foreach ($nextLevelElements as $nextLevelElement) {
            $lastElement = $nextLevelElement[$combinationsLevel - 2];
            $found       = false;

            foreach ($elements as $key => $element) {
                if ($element === $lastElement) {
                    $found = true;
                    continue;
                }

                if ($found !== true || $key >= $baseCount) {
                    continue;
                }

                $tmp              = $nextLevelElement;
                $newCombination   = array_slice($tmp, 0);
                $newCombination[] = $element;
                $result[]         = array_slice($newCombination, 0);
            }
        }

        return $result;
    }
}
