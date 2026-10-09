<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function getDB(): PDO
{
    global $pdo;

    return $pdo;
}