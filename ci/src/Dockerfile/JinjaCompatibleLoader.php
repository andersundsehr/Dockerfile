<?php

declare(strict_types=1);

namespace Webdevops\Build\Dockerfile;

use Twig\Loader\LoaderInterface;
use Twig\Source;

use function implode;
use function preg_match_all;
use function preg_replace;
use function preg_replace_callback;
use function str_ends_with;
use function substr;

/**
 * Loads the Dockerfile templates so that Twig renders them like Jinja did, byte for byte.
 *
 * - Jinja drops the last line break of a template file; Twig keeps it.
 * - Twig removes the line break after a block or comment tag ({% ... %}, {# ... #}); Jinja keeps it.
 * - The macros of a file see the imports of that file in Jinja; in Twig only the imports inside the macro.
 * - Twig spells elif as elseif.
 */
final readonly class JinjaCompatibleLoader implements LoaderInterface
{
    private const string IMPORT = '/\{%-?\s*import\s[^%]*?-?%\}/';

    private const string MACRO = '/\{%-?\s*macro\s[^%]*?(-?)%\}/';

    public function __construct(private LoaderInterface $loader)
    {
    }

    /**
     * The template code as Twig has to see it to render like Jinja.
     *
     * @param bool $file whether the code is a template file, whose last line break Jinja drops
     */
    public static function convert(string $code, bool $file = true): string
    {
        if ($file && str_ends_with($code, "\n")) {
            $code = substr($code, 0, -1);
        }

        $code = self::importIntoMacros($code);
        $code = (string)preg_replace('/(\{%-?\s*)elif\b/', '$1elseif', $code);
        // A second line break after each tag, so the one that Twig removes is not missing
        return (string)preg_replace('/([%#]\})\n/', "\$1\n\n", $code);
    }

    public function getSourceContext(string $name): Source
    {
        $source = $this->loader->getSourceContext($name);
        return new Source(self::convert($source->getCode()), $source->getName(), $source->getPath());
    }

    public function getCacheKey(string $name): string
    {
        return $this->loader->getCacheKey($name);
    }

    public function isFresh(string $name, int $time): bool
    {
        return $this->loader->isFresh($name, $time);
    }

    public function exists(string $name): bool
    {
        return $this->loader->exists($name);
    }

    /**
     * Repeats the imports of the file at the start of each macro. After a macro tag that removes the following
     * whitespace ("-%}"), the imports remove it, so the macro still starts where it did.
     */
    private static function importIntoMacros(string $code): string
    {
        preg_match_all(self::IMPORT, $code, $matches);
        if ($matches[0] === []) {
            return $code;
        }

        return (string)preg_replace_callback(self::MACRO, function (array $macro) use ($matches): string {
            $imports = $matches[0];
            if ($macro[1] === '-') {
                $imports = preg_replace('/-?%\}$/', '-%}', $imports);
            }

            return $macro[0] . implode('', $imports);
        }, $code);
    }
}
