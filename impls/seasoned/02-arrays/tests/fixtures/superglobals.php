<?php

declare(strict_types=1);

$id = $_GET['id'] ?? null;
$host = $_SERVER['HTTP_HOST'];

echo $id, $host;
