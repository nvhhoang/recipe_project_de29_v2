<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
header('Content-Type: text/plain; version=0.0.4; charset=utf-8');
header('Cache-Control: no-store');
try {
    $pdo = db();
    $stats = [
        'recipe_db_up' => 1,
        'recipe_recipes_total' => (int)$pdo->query('SELECT COUNT(*) FROM recipes')->fetchColumn(),
        'recipe_published_recipes_total' => (int)$pdo->query("SELECT COUNT(*) FROM recipes WHERE status = 'published'")->fetchColumn(),
        'recipe_categories_total' => (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
        'recipe_ingredients_total' => (int)$pdo->query('SELECT COUNT(*) FROM ingredients')->fetchColumn(),
    ];
    foreach ($stats as $name => $value) {
        echo '# TYPE ' . $name . ' gauge' . PHP_EOL;
        echo $name . ' ' . $value . PHP_EOL;
    }
} catch (Throwable $ex) {
    app_log('ERROR', 'Metrics endpoint could not query MySQL', ['error' => $ex->getMessage()]);
    http_response_code(503);
    echo "recipe_db_up 0\n";
}
