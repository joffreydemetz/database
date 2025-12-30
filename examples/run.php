<?php

/**
 * Example Runner - Executes all database examples
 * 
 * This script runs through all available database examples to demonstrate
 * the JDZ Database library capabilities across different drivers.
 */

echo "╔═════════════════════════════════════════════════════════════════╗\n";
echo "║------------- JDZ Database Library - Example Runner -------------║\n";
echo "╚═════════════════════════════════════════════════════════════════╝\n\n";

$examples = [
    'factory_example.php' => 'Database Factory Examples',
    'sqlite_example.php' => 'SQLite In-Memory Database',
    'mysql_example.php' => 'MySQL PDO Driver',
    'mysqli_example.php' => 'MySQLi Native Driver',
    'postgresql_example.php' => 'PostgreSQL Driver',
];

$results = [];
$totalTime = 0;

foreach ($examples as $file => $description) {
    $filePath = __DIR__ . '/' . $file;

    if (!file_exists($filePath)) {
        $results[] = [
            'file' => $file,
            'description' => $description,
            'status' => 'SKIP',
            'message' => 'File not found',
            'time' => 0
        ];
        continue;
    }

    echo "\n";
    echo "┌──────────────────────────────────────┐\n";
    echo "│ Running: {$description}\n";
    echo "│ File: {$file}\n";
    echo "└──────────────────────────────────────┘\n";

    $startTime = microtime(true);

    // Capture output
    ob_start();

    try {
        // Include the example file
        include $filePath;

        $output = ob_get_clean();
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);

        // Display the output
        echo $output;

        $results[] = [
            'file' => $file,
            'description' => $description,
            'status' => 'SUCCESS',
            'message' => 'Completed successfully',
            'time' => $executionTime
        ];

        $totalTime += $executionTime;
    } catch (Exception $e) {
        $output = ob_get_clean();
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);

        // Display what we got before the error
        if ($output) {
            echo $output;
        }

        $results[] = [
            'file' => $file,
            'description' => $description,
            'status' => 'ERROR',
            'message' => $e->getMessage(),
            'time' => $executionTime
        ];

        $totalTime += $executionTime;

        echo "\n✗ Error: " . $e->getMessage() . "\n";
    }
}

// Summary
echo "\n\n";
echo "┌───────────────────┐\n";
echo "│ EXECUTION SUMMARY │\n";
echo "└───────────────────────────────────────────────\n\n";

$successCount = 0;
$errorCount = 0;
$skipCount = 0;

foreach ($results as $result) {
    $status = $result['status'];
    $symbol = match ($status) {
        'SUCCESS' => '✓',
        'ERROR' => '✗',
        'SKIP' => '○',
        default => '?'
    };

    $color = match ($status) {
        'SUCCESS' => "\033[32m", // Green
        'ERROR' => "\033[31m",   // Red
        'SKIP' => "\033[33m",    // Yellow
        default => "\033[0m"     // Reset
    };

    $reset = "\033[0m";

    printf(
        "%s%s%s %-25s - %-35s (%6s ms)\n",
        $color,
        $symbol,
        $reset,
        substr($result['file'], 0, 25),
        substr($result['message'], 0, 35),
        number_format($result['time'], 2)
    );

    if ($status === 'SUCCESS') $successCount++;
    elseif ($status === 'ERROR') $errorCount++;
    elseif ($status === 'SKIP') $skipCount++;
}

echo "│\n";
echo "│───────────────────────────────────────────────\n";
echo sprintf(
    "Total: %d | Success: %d | Errors: %d | Skipped: %d | Time: %.2f ms\n",
    count($results),
    $successCount,
    $errorCount,
    $skipCount,
    $totalTime
);
echo "│───────────────────────────────────────────────\n";

// Exit with appropriate code
exit($errorCount > 0 ? 1 : 0);
