<?php

namespace App\Services\Document;

use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\ImmigrationCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class FolderService
{
    /**
     * Get the folder tree for a case (root folders with nested children).
     */
    public function getFolderTree(ImmigrationCase $case): Collection
    {
        return DocumentFolder::byCase($case->id)
            ->roots()
            ->with(['children' => function ($query) {
                $query->withCount('documents')->orderBy('sort_order');
            }])
            ->withCount('documents')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Create a new folder for a case.
     */
    public function createFolder(ImmigrationCase $case, array $data): DocumentFolder
    {
        $folder = DocumentFolder::create([
            'tenant_id' => $case->tenant_id,
            'case_id' => $case->id,
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_default' => false,
            'category' => $data['category'] ?? null,
        ]);

        activity()
            ->causedBy(Auth::user())
            ->performedOn($folder)
            ->withProperties([
                'case_id' => $case->id,
                'folder_name' => $folder->name,
            ])
            ->log('Created folder: ' . $folder->name);

        return $folder->loadCount('documents');
    }

    /**
     * Rename a folder.
     */
    public function renameFolder(DocumentFolder $folder, string $name): DocumentFolder
    {
        $oldName = $folder->name;

        $folder->update(['name' => $name]);

        activity()
            ->causedBy(Auth::user())
            ->performedOn($folder)
            ->withProperties([
                'old_name' => $oldName,
                'new_name' => $name,
            ])
            ->log('Renamed folder from "' . $oldName . '" to "' . $name . '"');

        return $folder;
    }

    /**
     * Delete a folder. Throws exception if not empty.
     */
    public function deleteFolder(DocumentFolder $folder): bool
    {
        // Check for documents (including soft-deleted)
        $documentCount = Document::withTrashed()
            ->where('folder_id', $folder->id)
            ->count();

        if ($documentCount > 0) {
            throw new \RuntimeException('Cannot delete folder that contains documents.');
        }

        // Check for child folders
        if ($folder->children()->count() > 0) {
            throw new \RuntimeException('Cannot delete folder that contains subfolders.');
        }

        activity()
            ->causedBy(Auth::user())
            ->performedOn($folder)
            ->withProperties([
                'case_id' => $folder->case_id,
                'folder_name' => $folder->name,
            ])
            ->log('Deleted folder: ' . $folder->name);

        return $folder->delete();
    }

    /**
     * Create default folder structure for a case.
     */
    public function createDefaultStructure(ImmigrationCase $case): void
    {
        $defaultFolders = [
            ['name' => 'Archivo', 'category' => Document::CATEGORY_ARCHIVE, 'sort_order' => 0],
            ['name' => 'Cartas', 'category' => Document::CATEGORY_LETTERS, 'sort_order' => 1],
            ['name' => 'Comunicaciones', 'category' => Document::CATEGORY_COMMUNICATION, 'sort_order' => 2],
            ['name' => 'Contrato', 'category' => Document::CATEGORY_CONTRACT, 'sort_order' => 3],
            ['name' => 'Contabilidad', 'category' => Document::CATEGORY_ACCOUNTING, 'sort_order' => 4],
            ['name' => 'Documentos', 'category' => Document::CATEGORY_DOCUMENTS, 'sort_order' => 5],
            ['name' => 'Enlaces', 'category' => Document::CATEGORY_LINKS, 'sort_order' => 6],
            ['name' => 'Questionarios', 'category' => Document::CATEGORY_QUESTIONARY, 'sort_order' => 7],
            ['name' => 'Formularios', 'category' => Document::CATEGORY_FORMS, 'sort_order' => 8],
            ['name' => 'Admision', 'category' => Document::CATEGORY_ADMISSION, 'sort_order' => 9],
            ['name' => 'Historial', 'category' => Document::CATEGORY_HISTORY, 'sort_order' => 10],
            ['name' => 'Evidencia', 'category' => Document::CATEGORY_EVIDENCE, 'sort_order' => 11],
            ['name' => 'Audiencias', 'category' => Document::CATEGORY_HEARING, 'sort_order' => 12],
            ['name' => 'Otros', 'category' => Document::CATEGORY_OTHER, 'sort_order' => 13],
        ];

        foreach ($defaultFolders as $folderData) {
            DocumentFolder::create([
                'tenant_id' => $case->tenant_id,
                'case_id' => $case->id,
                'parent_id' => null,
                'name' => $folderData['name'],
                'sort_order' => $folderData['sort_order'],
                'is_default' => true,
                'category' => $folderData['category'],
            ]);
        }
    }
}
