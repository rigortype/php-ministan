<?php

declare(strict_types=1);

namespace Ministan\Analyser;

/**
 * ある地点で「いま何が分かっているか」を表す不変オブジェクト。
 *
 * PHPStan の {@see \PHPStan\Analyser\Scope} に対応する。Part 2 では
 * 「どの変数が定義済みか」だけを持つ。Part 3〜4 で、ここに変数→**型**の
 * 対応が乗る。
 *
 * 不変なのが肝心。木を下りながら `assignVariable()` で**新しい** Scope を
 * 作って渡すことで、分岐ごとに別の事実を持たせ、後から安全に合流できる。
 */
final readonly class Scope
{
    /**
     * @param array<string, true> $variables 定義済み変数名の集合
     */
    private function __construct(
        private array $variables,
    ) {
    }

    /**
     * ファイル直下（グローバル）スコープ。スーパーグローバルは常に定義済み。
     */
    public static function createForFile(): self
    {
        return self::createForFunction();
    }

    /**
     * 関数境界の新しいスコープ。外側のローカル変数は見えないが、
     * スーパーグローバルは関数内でも参照できる。
     */
    public static function createForFunction(): self
    {
        $superglobals = [
            'GLOBALS', '_SERVER', '_GET', '_POST', '_FILES',
            '_COOKIE', '_SESSION', '_REQUEST', '_ENV',
        ];

        $variables = [];
        foreach ($superglobals as $name) {
            $variables[$name] = true;
        }

        return new self($variables);
    }

    public function hasVariable(string $name): bool
    {
        return isset($this->variables[$name]);
    }

    public function assignVariable(string $name): self
    {
        if (isset($this->variables[$name])) {
            return $this;
        }

        return new self([...$this->variables, $name => true]);
    }

    /**
     * 2 つのスコープを合流する。
     *
     * Part 2 では「どちらかで定義されていれば定義済み」とする楽観的な和集合。
     * これにより条件分岐をまたいでも**偽陽性を出さない**（non-rejecting）。
     * PHPStan は「全経路で定義された場合のみ確定」とし、そうでなければ
     * 「未定義かもしれない」を別途報告する——その精密化は Part 5 で扱う。
     */
    public function mergeWith(self $other): self
    {
        return new self($this->variables + $other->variables);
    }
}
