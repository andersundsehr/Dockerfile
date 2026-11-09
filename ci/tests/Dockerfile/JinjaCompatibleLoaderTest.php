<?php

declare(strict_types=1);

namespace Webdevops\Build\Tests\Dockerfile;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Webdevops\Build\Dockerfile\JinjaCompatibleLoader;

#[CoversClass(JinjaCompatibleLoader::class)]
final class JinjaCompatibleLoaderTest extends TestCase
{
    /**
     * Each case renders a template with Twig through the loader; the expected output is what Jinja renders
     * with the settings of bin/console generate:dockerfile (trim_blocks and keep_trailing_newline off).
     *
     * @return iterable<string, array{0: array<string, string>, 1: string}>
     */
    public static function templates(): iterable
    {
        yield 'line break after a block tag stays' => [['main' => "{% if true %}\nA\n{% endif %}\nB"], "\nA\n\nB"];
        yield 'whitespace control still removes it' => [['main' => "{% if true -%}\nA\n{%- endif %}\nB"], "A\nB"];
        yield 'line break after a comment stays' => [['main' => "A {# note #}\nB"], "A \nB"];
        yield 'last line break of the file is dropped' => [['main' => "A\n"], 'A'];
        yield 'only the last line break' => [['main' => "A\n\n"], "A\n"];
        yield 'elif' => [['main' => "{% if false %}A{% elif true %}B{% else %}C{% endif %}"], 'B'];
        yield 'macro sees the imports of its file' => [
            [
                'main' => "{% import 'lib' as lib %}{{ lib.outer() }}",
                'lib' => "{% import 'inner' as inner %}\n{% macro outer() -%}\n    {{ inner.text() }}\n{%- endmacro %}\n",
                'inner' => "{% macro text() %}inner{% endmacro %}\n",
            ],
            'inner',
        ];
        yield 'macro without whitespace control keeps its first line break' => [
            [
                'main' => "{% import 'lib' as lib %}[{{ lib.outer() }}]",
                'lib' => "{% import 'inner' as inner %}\n{% macro outer() %}\n{{ inner.text() }}\n{% endmacro %}\n",
                'inner' => "{% macro text() %}inner{% endmacro %}\n",
            ],
            "[\ninner\n]",
        ];
    }

    /**
     * @param array<string, string> $templates
     */
    #[DataProvider('templates')]
    public function testRendersLikeJinja(array $templates, string $expected): void
    {
        $twig = new Environment(new JinjaCompatibleLoader(new ArrayLoader($templates)), ['autoescape' => false]);

        self::assertSame($expected, $twig->render('main'));
    }

    public function testCodeThatIsNoFileKeepsItsLastLineBreak(): void
    {
        self::assertSame("A\n", JinjaCompatibleLoader::convert("A\n", false));
    }

    public function testForwardsToTheLoader(): void
    {
        $loader = new JinjaCompatibleLoader(new ArrayLoader(['main' => 'A']));

        self::assertTrue($loader->exists('main'));
        self::assertFalse($loader->exists('other'));
        self::assertSame('main:A', $loader->getCacheKey('main'));
        self::assertTrue($loader->isFresh('main', 0));
    }
}
