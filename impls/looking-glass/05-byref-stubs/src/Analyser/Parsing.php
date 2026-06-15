<?php

declare(strict_types=1);

namespace Ministan\Analyser;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * Turn source into an AST and resolve names to their fully qualified form.
 *
 * Running it through php-parser's {@see NameResolver} resolves a `Bar` under
 * `use Foo\Bar;` to `Foo\Bar`, and attaches a `namespacedName` to class and function
 * declarations. This is the precondition for reflection to look things up by the
 * correct FQN. Shared by `analyse` and `annotate`.
 */
final class Parsing
{
    /**
     * @return Node[]
     *
     * @throws \PhpParser\Error
     */
    public static function parse(string $code): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code) ?? [];

        return (new NodeTraverser(new NameResolver()))->traverse($ast);
    }
}
