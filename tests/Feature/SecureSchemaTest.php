<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecureSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_secure_schema_enables_rls_and_policy_on_every_table_and_is_idempotent(): void
    {
        $this->artisan('eduflow:secure-schema')->assertSuccessful();
        $this->artisan('eduflow:secure-schema')->assertSuccessful();

        $tables = DB::select("select c.relname as name, c.relrowsecurity as rls from pg_class c join pg_namespace n on n.oid = c.relnamespace where n.nspname = current_schema() and c.relkind = 'r'");
        $this->assertNotEmpty($tables);
        foreach ($tables as $t) {
            $this->assertTrue((bool) $t->rls, "RLS belum aktif pada {$t->name}");
        }

        $policies = DB::select("select tablename, roles, cmd, qual, with_check from pg_policies where schemaname = current_schema() and policyname = 'app_full_access'");
        $this->assertCount(count($tables), $policies);
        foreach ($policies as $p) {
            $this->assertSame('{eduflow_app}', (string) $p->roles, "Peran policy salah pada {$p->tablename}");
            $this->assertSame('ALL', $p->cmd);
            $this->assertSame('true', $p->qual);
            $this->assertSame('true', $p->with_check);
        }
    }
}
