<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('external_url');
            $table->string('checksum', 64)->nullable()->after('version');
        });

        Schema::table('document_folders', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('sort_order');
            $table->string('category')->nullable()->after('is_default');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['version', 'checksum']);
        });

        Schema::table('document_folders', function (Blueprint $table) {
            $table->dropColumn(['is_default', 'category']);
        });
    }
};
