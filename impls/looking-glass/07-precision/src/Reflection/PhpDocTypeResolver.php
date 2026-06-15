<?php

declare(strict_types=1);

namespace Ministan\Reflection;

use Ministan\Type\ArrayType;
use Ministan\Type\BooleanType;
use Ministan\Type\Constant\ConstantBooleanType;
use Ministan\Type\FloatType;
use Ministan\Type\GenericObjectType;
use Ministan\Type\IntegerType;
use Ministan\Type\MixedType;
use Ministan\Type\NeverType;
use Ministan\Type\NullType;
use Ministan\Type\ObjectType;
use Ministan\Type\StringType;
use Ministan\Type\TemplateType;
use Ministan\Type\Type;
use Ministan\Type\TypeCombinator;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;

/**
 * Parses a docblock and maps the types of `@return`/`@param`/`@var`/`@template` onto {@see Type}.
 *
 * Handling type variables is the extension point introduced in S3. It collects `@template T`,
 * resolves identifiers with those names to {@see TemplateType}, and resolves named generics like
 * `Collection<int>` to {@see GenericObjectType}.
 */
final class PhpDocTypeResolver
{
    private Lexer $lexer;

    private PhpDocParser $parser;

    public function __construct()
    {
        $config = new ParserConfig([]);
        $constExpr = new ConstExprParser($config);
        $typeParser = new TypeParser($config, $constExpr);

        $this->lexer = new Lexer($config);
        $this->parser = new PhpDocParser($config, $typeParser, $constExpr);
    }

    /**
     * @param list<string> $outerTemplateNames type variable names visible from the outer scope (the class)
     */
    public function parse(?string $docComment, array $outerTemplateNames = []): ParsedPhpDoc
    {
        if ($docComment === null || trim($docComment) === '') {
            return ParsedPhpDoc::empty();
        }

        $tokens = new TokenIterator($this->lexer->tokenize($docComment));
        $doc = $this->parser->parse($tokens);

        $ownTemplates = [];
        foreach ($doc->getTemplateTagValues() as $tag) {
            $ownTemplates[] = $tag->name;
        }
        $active = [...$outerTemplateNames, ...$ownTemplates];

        $returnType = null;
        foreach ($doc->getReturnTagValues() as $tag) {
            $returnType = $this->toType($tag->type, $active);
            break;
        }

        $paramTypes = [];
        foreach ($doc->getParamTagValues() as $tag) {
            $paramTypes[ltrim($tag->parameterName, '$')] = $this->toType($tag->type, $active);
        }

        $varTypes = [];
        foreach ($doc->getVarTagValues() as $tag) {
            $varTypes[ltrim($tag->variableName, '$')] = $this->toType($tag->type, $active);
        }

        return new ParsedPhpDoc($returnType, $paramTypes, $varTypes, $ownTemplates);
    }

    /**
     * @param list<string> $templateNames
     */
    private function toType(TypeNode $node, array $templateNames): Type
    {
        return match (true) {
            $node instanceof IdentifierTypeNode => $this->fromIdentifier($node->name, $templateNames),
            $node instanceof NullableTypeNode => TypeCombinator::union($this->toType($node->type, $templateNames), new NullType()),
            $node instanceof UnionTypeNode => TypeCombinator::union(
                ...array_map(fn (TypeNode $t): Type => $this->toType($t, $templateNames), $node->types),
            ),
            $node instanceof GenericTypeNode => $this->fromGeneric($node, $templateNames),
            $node instanceof ArrayTypeNode => new ArrayType(new MixedType(), $this->toType($node->type, $templateNames)),
            default => new MixedType(),
        };
    }

    /**
     * @param list<string> $templateNames
     */
    private function fromIdentifier(string $name, array $templateNames): Type
    {
        if (in_array($name, $templateNames, true)) {
            return new TemplateType($name, new MixedType());
        }

        return match (strtolower($name)) {
            'int', 'integer' => new IntegerType(),
            'string' => new StringType(),
            'float', 'double' => new FloatType(),
            'bool', 'boolean' => new BooleanType(),
            'true' => new ConstantBooleanType(true),
            'false' => new ConstantBooleanType(false),
            'null', 'void' => new NullType(),
            'never', 'never-return' => new NeverType(),
            'array' => new ArrayType(new MixedType(), new MixedType()),
            'list' => new ArrayType(new IntegerType(), new MixedType()),
            'mixed', 'iterable', 'object', 'callable', 'scalar', 'self', 'static', '$this' => new MixedType(),
            default => new ObjectType(ltrim($name, '\\')),
        };
    }

    /**
     * @param list<string> $templateNames
     */
    private function fromGeneric(GenericTypeNode $node, array $templateNames): Type
    {
        $base = strtolower($node->type->name);
        $args = array_map(fn (TypeNode $t): Type => $this->toType($t, $templateNames), $node->genericTypes);

        return match (true) {
            $base === 'array' && count($args) === 2 => new ArrayType($args[0], $args[1]),
            $base === 'array' && count($args) === 1 => new ArrayType(new MixedType(), $args[0]),
            ($base === 'list' || $base === 'non-empty-list') && count($args) >= 1
                => new ArrayType(new IntegerType(), $args[0]),
            // Any other `Foo<…>` is a generic class.
            default => new GenericObjectType(ltrim($node->type->name, '\\'), array_values($args)),
        };
    }
}
