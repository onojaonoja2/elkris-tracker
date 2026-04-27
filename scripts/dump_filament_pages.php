<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Filament\Facades\Filament;
use Throwable;

$pages = Filament::getPages();
foreach ($pages as $page) {
    echo (is_string($page) ? $page : get_class($page)) . PHP_EOL;
}

echo "\nPanel 'admin' pages order:\n";
$panel = Filament::getPanel('admin');
foreach ($panel->getPages() as $p) {
    echo (is_string($p) ? $p : $p) . PHP_EOL;
}

echo "\nRoute names and paths:\n";
foreach ($pages as $page) {
    $class = is_string($page) ? $page : get_class($page);
    if (method_exists($class, 'getRouteName')) {
        try {
            $panel = Filament::getPanel('admin');
            echo $class . ' -> ' . $class::getRouteName($panel) . ' -> ' . $class::getRoutePath($panel) . PHP_EOL;
        } catch (Throwable $e) {
            echo $class . ' -> error: ' . $e->getMessage() . PHP_EOL;
        }
    }
}
