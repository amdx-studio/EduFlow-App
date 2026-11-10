<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SecureSchema extends Command
{
    protected $signature = 'eduflow:secure-schema';

    protected $description = 'Aktifkan row level security dan policy app_full_access untuk eduflow_app pada semua tabel di schema aktif (A.2, P10.3)';

    private const APP_ROLE = 'eduflow_app';

    private const POLICY = 'app_full_access';

    public function handle(): int
    {
        $ctx = DB::selectOne('select current_user as u, current_schema() as s');

        if (! DB::selectOne('select 1 as ok from pg_roles where rolname = ?', [self::APP_ROLE])) {
            $this->error('Role '.self::APP_ROLE.' tidak ditemukan. Tidak ada yang diubah.');

            return self::FAILURE;
        }

        $tables = DB::select(
            "select c.relname as name, pg_get_userbyid(c.relowner) as owner from pg_class c join pg_namespace n on n.oid = c.relnamespace where n.nspname = ? and c.relkind in ('r', 'p') order by c.relname",
            [$ctx->s]
        );

        if ($tables === []) {
            $this->error("Tidak ada tabel di schema {$ctx->s}. Tidak ada yang diubah.");

            return self::FAILURE;
        }

        $foreign = array_values(array_filter($tables, fn ($t) => $t->owner !== $ctx->u));
        if ($foreign !== []) {
            $names = implode(', ', array_map(fn ($t) => $t->name, $foreign));
            $this->error("Pengguna {$ctx->u} bukan pemilik tabel: {$names}. Jalankan dengan --env=migrate. Tidak ada yang diubah.");

            return self::FAILURE;
        }

        DB::transaction(function () use ($tables, $ctx) {
            foreach ($tables as $t) {
                $q = $this->quote($ctx->s).'.'.$this->quote($t->name);
                DB::statement("alter table {$q} enable row level security");
                DB::statement('drop policy if exists '.self::POLICY." on {$q}");
                DB::statement('create policy '.self::POLICY." on {$q} for all to ".self::APP_ROLE.' using (true) with check (true)');
            }
        });

        $this->info('RLS dan policy '.self::POLICY.' aktif pada '.count($tables)." tabel di schema {$ctx->s}.");

        return self::SUCCESS;
    }

    private function quote(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
