<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Guard KT-06: dijalankan sebelum trait seperti RefreshDatabase,
     * sehingga tidak pernah ada tabel yang dihapus atau dibuat di schema selain eduflow_test.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $default = config('database.default');
        $schema = config('database.connections.pgsql.search_path');

        if ($default !== 'pgsql' || $schema !== 'eduflow_test') {
            throw new RuntimeException(
                "Test dihentikan: koneksi default '{$default}' dan search_path '{$schema}'. "
                ."Test hanya boleh berjalan di pgsql dengan schema 'eduflow_test'."
            );
        }
    }
}
