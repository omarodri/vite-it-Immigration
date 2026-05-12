<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CaseFolderTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $defaults = collect(config('case_folders.defaults', []))
            ->map(fn ($d) => [
                'key'                => $d['key'],
                'i18n_key'           => $d['i18n_key'],
                'category'           => $d['category'] ?? null,
                'enabled_by_default' => (bool) ($d['enabled_by_default'] ?? true),
            ])
            ->values();

        return response()->json([
            'data'       => $defaults,
            'validation' => [
                'name_max_length'     => (int) config('case_folders.validation.name_max_length', 100),
                'forbidden_chars'     => (array) config('case_folders.validation.forbidden_chars', []),
                'reserved_names'      => (array) config('case_folders.validation.reserved_names', []),
                'max_custom_per_case' => (int) config('case_folders.validation.max_custom_per_case', 10),
                'max_total_per_case'  => (int) config('case_folders.validation.max_total_per_case', 30),
            ],
        ]);
    }
}
