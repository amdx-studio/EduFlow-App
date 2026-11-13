<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Pasangan DatabaseTruncation: truncation hanya membersihkan SEBELUM tes, jadi bersihkan juga sesudahnya
 * agar tes RefreshDatabase tidak melihat data sisa. Hanya untuk PostgreSQL (pg_tables).
 */
trait TruncatesAfterTest
{
    protected function tearDown(): void
    {
        $tables = collect(DB::select('select tablename from pg_tables where schemaname = current_schema()'))
            ->pluck('tablename')
            ->reject(fn ($name) => $name === 'migrations')
            ->map(fn ($name) => '"'.$name.'"')
            ->implode(', ');

        if ($tables !== '') {
            DB::statement("TRUNCATE TABLE {$tables} RESTART IDENTITY CASCADE");
        }

        parent::tearDown();
    }
}
