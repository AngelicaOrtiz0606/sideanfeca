<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/esquema_sideanfeca.sql')));
    }

    public function down(): void
    {
        throw new RuntimeException('Esta migración no se revierte. Para rehacerla: php artisan db:wipe --force && php artisan migrate --force');
    }
};
