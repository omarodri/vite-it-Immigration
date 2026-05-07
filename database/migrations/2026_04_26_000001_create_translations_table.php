<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->morphs('translatable');
            $table->string('locale', 5);
            $table->string('field', 50);
            $table->text('value');
            $table->timestamps();

            $table->unique(
                ['translatable_type', 'translatable_id', 'locale', 'field'],
                'translations_morph_locale_field_unique'
            );
            $table->index(['tenant_id', 'translatable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
