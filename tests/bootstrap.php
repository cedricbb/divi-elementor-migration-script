<?php

declare(strict_types=1);

// Autoload vendor (PHPUnit, Brain\Monkey, etc.)
require_once __DIR__ . '/../vendor/autoload.php';

// Brain\Monkey sets up Mockery + WP function stubs — no additional stubs needed here.
// Individual tests call Brain\Monkey\setUp() / tearDown() in their setUp/tearDown.
