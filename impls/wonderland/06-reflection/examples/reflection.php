<?php

declare(strict_types=1);

class Greeter
{
    public function greet(string $name): string
    {
        return 'Hello, ' . $name;
    }
}

$greeter = new Greeter();
$message = $greeter->greet('world'); // string（メソッド戻り値）
$length = strlen($message);          // int（組み込み関数の戻り値）

$greeter->shout('!'); // 未定義メソッド → エラー
