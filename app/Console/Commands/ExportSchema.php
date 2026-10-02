<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ExportSchema extends Command
{
    protected $signature = 'ledger:erd';

    protected $description = 'Generate an ERD and column catalog from the migrated database';

    public function handle(): int
    {
        $tables = array_column(Schema::getTables(), 'name');
        sort($tables);
        $diagram = "erDiagram\n";
        $catalog = '';
        foreach ($tables as $table) {
            if (in_array($table, ['cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs', 'migrations', 'password_reset_tokens', 'personal_access_tokens', 'sessions'])) {
                continue;
            }
            $diagram .= "    {$table} {\n";
            $catalog .= "\n## {$table}\n\n| Column | Database type | Nullable |\n|---|---|---|\n";
            foreach (Schema::getColumns($table) as $column) {
                $type = preg_replace('/[^a-zA-Z0-9_]/', '_', $column['type_name']);
                $diagram .= "        {$type} {$column['name']}\n";
                $catalog .= "| {$column['name']} | {$column['type']} | ".($column['nullable'] ? 'Yes' : 'No')." |\n";
            }
            $diagram .= "    }\n";
            foreach (Schema::getForeignKeys($table) as $fk) {
                $label = implode('_', $fk['columns']);
                $diagram .= "    {$fk['foreign_table']} ||--o{ {$table} : {$label}\n";
            }
        }
        file_put_contents(base_path('docs/schema.md'), "# Implemented database ERD\n\nGenerated from the migrated database using `php artisan ledger:erd`. Composite links include company_id.\n\n```mermaid\n{$diagram}```\n{$catalog}");
        $this->info('Generated docs/schema.md');

        return self::SUCCESS;
    }
}
