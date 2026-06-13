<?php

declare(strict_types=1);

namespace Ministan\Reflection;

use Ministan\Type\ArrayType;
use Ministan\Type\BooleanType;
use Ministan\Type\Constant\ConstantBooleanType;
use Ministan\Type\FloatType;
use Ministan\Type\IntegerType;
use Ministan\Type\MixedType;
use Ministan\Type\NeverType;
use Ministan\Type\NullType;
use Ministan\Type\ObjectType;
use Ministan\Type\StringType;
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
 * docblock を解析し、`@return`/`@param`/`@var` の型を {@see Type} に写す。
 *
 * phpstan/phpdoc-parser で文字列をパースし、その型 AST を ministan の型へ変換する。
 * PHPStan の `TypeNodeResolver` に対応。クラス名の名前空間解決（use の解釈）までは
 * 追わない——そこは応用編で。
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

    public function parse(?string $docComment): ParsedPhpDoc
    {
        if ($docComment === null || trim($docComment) === '') {
            return ParsedPhpDoc::empty();
        }

        $tokens = new TokenIterator($this->lexer->tokenize($docComment));
        $doc = $this->parser->parse($tokens);

        $returnType = null;
        foreach ($doc->getReturnTagValues() as $tag) {
            $returnType = $this->toType($tag->type);
            break;
        }

        $paramTypes = [];
        foreach ($doc->getParamTagValues() as $tag) {
            $paramTypes[ltrim($tag->parameterName, '$')] = $this->toType($tag->type);
        }

        $varTypes = [];
        foreach ($doc->getVarTagValues() as $tag) {
            $varTypes[ltrim($tag->variableName, '$')] = $this->toType($tag->type);
        }

        return new ParsedPhpDoc($returnType, $paramTypes, $varTypes);
    }

    private function toType(TypeNode $node): Type
    {
        return match (true) {
            $node instanceof IdentifierTypeNode => $this->fromIdentifier($node->name),
            $node instanceof NullableTypeNode => TypeCombinator::union($this->toType($node->type), new NullType()),
            $node instanceof UnionTypeNode => TypeCombinator::union(...array_map($this->toType(...), $node->types)),
            $node instanceof GenericTypeNode => $this->fromGeneric($node),
            $node instanceof ArrayTypeNode => new ArrayType(new MixedType(), $this->toType($node->type)),
            default => new MixedType(),
        };
    }

    private function fromIdentifier(string $name): Type
    {
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

    private function fromGeneric(GenericTypeNode $node): Type
    {
        $base = strtolower($node->type->name);
        $args = array_map($this->toType(...), $node->genericTypes);

        return match (true) {
            $base === 'array' && count($args) === 2 => new ArrayType($args[0], $args[1]),
            $base === 'array' && count($args) === 1 => new ArrayType(new MixedType(), $args[0]),
            ($base === 'list' || $base === 'non-empty-list') && count($args) >= 1
                => new ArrayType(new IntegerType(), $args[0]),
            default => new MixedType(),
        };
    }
}
