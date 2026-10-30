<?php

namespace Tests\Payroll;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The recorded golden cases (see GoldenCases) as a PHPUnit suite. */
class GoldenParityTest extends TestCase
{
    public static function suites(): array
    {
        return array_map(fn ($s) => [$s], array_combine(array_keys(GoldenCases::suites()), array_keys(GoldenCases::suites())));
    }

    #[DataProvider('suites')]
    public function test_suite_matches_the_recorded_cases(string $suite): void
    {
        [$count, $failures] = GoldenCases::run($suite);
        $this->assertGreaterThan(0, $count);
        $this->assertSame([], array_slice($failures, 0, 5));
    }

    public function test_the_files_the_bir_module_accepted_rebuild_byte_for_byte(): void
    {
        $this->assertCount(2, glob(__DIR__ . '/golden/bir-accepted/*-input.json'));
        $this->assertSame([], GoldenCases::birAccepted());
    }
}
