<?php

namespace Tests\Unit\Workflow;

use App\Models\CaseType;
use App\Models\Tenant;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasTranslationsTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowStage $stage;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();
        $caseType = CaseType::factory()->create(['tenant_id' => null]);

        $this->stage = WorkflowStage::factory()->create([
            'tenant_id'    => $tenant->id,
            'case_type_id' => $caseType->id,
        ]);
    }

    public function test_trans_returns_value_for_requested_locale(): void
    {
        $this->stage->setTranslations('name', ['es' => 'Admisión', 'en' => 'Admission']);

        $this->assertEquals('Admisión', $this->stage->trans('name', 'es'));
        $this->assertEquals('Admission', $this->stage->trans('name', 'en'));
    }

    public function test_trans_falls_back_to_es_when_locale_missing(): void
    {
        $this->stage->setTranslations('name', ['es' => 'Admisión']);

        $result = $this->stage->trans('name', 'fr');

        $this->assertEquals('Admisión', $result);
    }

    public function test_trans_returns_null_when_no_translation_exists(): void
    {
        $result = $this->stage->trans('name', 'es');

        $this->assertNull($result);
    }

    public function test_trans_does_not_fall_back_when_locale_is_es(): void
    {
        // Only English exists; requesting ES should return null (no infinite fallback)
        $this->stage->setTranslations('name', ['en' => 'Admission']);

        $result = $this->stage->trans('name', 'es');

        $this->assertNull($result);
    }

    public function test_translations_by_field_returns_correct_structure(): void
    {
        $this->stage->setTranslations('name', ['es' => 'Admisión', 'en' => 'Admission']);
        $this->stage->setTranslations('description', ['es' => 'Primera fase']);

        $result = $this->stage->translationsByField();

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertEquals('Admisión', $result['name']['es']);
        $this->assertEquals('Admission', $result['name']['en']);
        $this->assertEquals('Primera fase', $result['description']['es']);
    }

    public function test_translations_by_field_returns_empty_array_when_no_translations(): void
    {
        $result = $this->stage->translationsByField();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_set_translations_overwrites_existing_value(): void
    {
        $this->stage->setTranslations('name', ['es' => 'Original']);
        $this->stage->setTranslations('name', ['es' => 'Actualizado']);

        $this->assertEquals('Actualizado', $this->stage->trans('name', 'es'));
    }

    public function test_set_translations_deletes_entry_when_value_is_null(): void
    {
        $this->stage->setTranslations('name', ['es' => 'Para borrar']);
        $this->stage->setTranslations('name', ['es' => null]);

        $this->assertNull($this->stage->trans('name', 'es'));
    }
}
