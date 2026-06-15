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
$message = $greeter->greet('world'); // string (method return value)
$length = strlen($message);          // int (return value of a built-in function)

$greeter->shout('!'); // undefined method → error
