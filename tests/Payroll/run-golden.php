<?php

// Runs the golden payroll cases without Composer: php tests/Payroll/run-golden.php

spl_autoload_register(function (string $class) {
    $root = dirname(__DIR__, 2);
    $map = ['App\\' => "{$root}/app/", 'Tests\\' => "{$root}/tests/"];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            require $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        }
    }
});

$failed = 0;
foreach (array_keys(Tests\Payroll\GoldenCases::suites()) as $suite) {
    [$count, $failures] = Tests\Payroll\GoldenCases::run($suite);
    printf("%-14s %5d cases  %s\n", $suite, $count, $failures ? count($failures) . ' FAILED' : 'ok');
    foreach (array_slice($failures, 0, 8) as $f) {
        echo "    {$f}\n";
    }
    $failed += count($failures);
}
$accepted = glob(__DIR__ . '/golden/bir-accepted/*-input.json');
$differ = Tests\Payroll\GoldenCases::birAccepted();
printf("%-14s %5d files  %s\n", 'bir-accepted', count($accepted), $differ ? 'DIFFER: ' . implode(', ', $differ) : 'byte-identical');
$failed += count($differ);
exit($failed ? 1 : 0);
