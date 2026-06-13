<?php

declare(strict_types=1);

namespace Ministan\Analyser;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * ソースを AST にし、名前を完全修飾へ解決する。
 *
 * php-parser の {@see NameResolver} を通すことで、`use Foo\Bar;` 下の `Bar` が
 * `Foo\Bar` に解決され、クラス・関数宣言には `namespacedName` が付く。リフレクションが
 * 正しい FQN で引けるようになる前提。`analyse` と `annotate` で共有する。
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
