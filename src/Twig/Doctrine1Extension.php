<?php

namespace DiabloMedia\Bundle\Doctrine1Bundle\Twig;

use Doctrine\SqlFormatter\HtmlHighlighter;
use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
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
    private SqlFormatter $sqlFormatter;

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

                $result = implode(', ', $result) ?: 'NULL';
                break;

            case is_object($result) && method_exists($result, '__toString'):
                $result = addslashes($result->__toString());
                break;

            case $result === null:
                $result = 'NULL';
                break;

            case is_bool($result):
                $result = $result ? '1' : '0';
                break;
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
        trigger_deprecation(
            'doctrine/doctrine-bundle',
            '2.1',
            'The "%s()" method is deprecated and will be removed in doctrine-bundle 3.0.',
            __METHOD__,
        );

        $this->setUpSqlFormatter(true, true);

        if ($highlightOnly) {
            return $this->sqlFormatter->highlight($sql);
        }

        return sprintf(
            '<div class="highlight highlight-sql"><pre>%s</pre></div>',
            $this->sqlFormatter->format($sql),
        );
    }

    public function formatSql(string $sql, bool $highlight): string
    {
        $this->setUpSqlFormatter($highlight);

        return $this->sqlFormatter->format($sql);
    }

    /**
     * Define our functions
     *
     * @return TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('doctrine1_pretty_query', [$this, 'formatQuery'], ['is_safe' => ['html'], 'deprecated' => true]),
            new TwigFilter('doctrine1_prettify_sql', [$this, 'prettifySql'], ['is_safe' => ['html']]),
            new TwigFilter('doctrine1_format_sql', [$this, 'formatSql'], ['is_safe' => ['html']]),
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

    public function prettifySql(string $sql): string
    {
        $this->setUpSqlFormatter();

        return $this->sqlFormatter->highlight($sql);
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

        return (string) preg_replace_callback(
            '/\?|((?<!:):[a-z0-9_]+)/i',
            static function (array $matches) use ($parameters, &$i): string {
                $key = substr($matches[0], 1);

                if (!array_key_exists($i, $parameters) && ($key === false || !array_key_exists($key, $parameters))) {
                    return $matches[0];
                }

                $value  = array_key_exists($i, $parameters) ? $parameters[$i] : $parameters[$key];
                $result = self::escapeFunction($value);
                $i++;

                return $result;
            },
            $query,
        );
    }

    private function setUpSqlFormatter(bool $highlight = true, bool $legacy = false): void
    {
        $this->sqlFormatter = new SqlFormatter($highlight ? new HtmlHighlighter([
            HtmlHighlighter::HIGHLIGHT_PRE            => 'class="highlight highlight-sql"',
            HtmlHighlighter::HIGHLIGHT_QUOTE          => 'class="string"',
            HtmlHighlighter::HIGHLIGHT_BACKTICK_QUOTE => 'class="string"',
            HtmlHighlighter::HIGHLIGHT_RESERVED       => 'class="keyword"',
            HtmlHighlighter::HIGHLIGHT_BOUNDARY       => 'class="symbol"',
            HtmlHighlighter::HIGHLIGHT_NUMBER         => 'class="number"',
            HtmlHighlighter::HIGHLIGHT_WORD           => 'class="word"',
            HtmlHighlighter::HIGHLIGHT_ERROR          => 'class="error"',
            HtmlHighlighter::HIGHLIGHT_COMMENT        => 'class="comment"',
            HtmlHighlighter::HIGHLIGHT_VARIABLE       => 'class="variable"',
        ], ! $legacy) : new NullHighlighter());
    }
}
