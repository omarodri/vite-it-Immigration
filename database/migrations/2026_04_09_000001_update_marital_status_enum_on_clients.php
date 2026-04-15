<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE clients MODIFY marital_status ENUM(
            'single',
            'married',
            'divorced',
            'widowed',
            'common_law',
            'separated',
            'legally_separated',
            'annulled_marriage',
            'unknown'
        ) NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE clients MODIFY marital_status ENUM(
            'single',
            'married',
            'divorced',
            'widowed',
            'common_law',
            'separated'
        ) NULL");
    }
};
