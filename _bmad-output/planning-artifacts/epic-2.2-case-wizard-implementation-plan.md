# Epic 2.2: Case Wizard - Implementation Plan

## 1. Executive Summary

This document details the implementation plan for the Case Wizard feature, a 5-step guided workflow for creating immigration cases. The wizard replaces the simple form at `/cases/create` with a more intuitive, step-by-step experience.

**Total Story Points:** 21 pts
**Estimated Development Time:** 5-7 days

---

## 2. Architectural Analysis

### 2.1 Current State

**Backend (Laravel)**
- `ImmigrationCase` model with full CRUD support
- `CaseService` handles creation with auto-generated case numbers
- `StoreCaseRequest` validates client_id, case_type_id, priority, dates
- No `assigned_to` field in creation - only via separate assignment endpoint
- No case-companion relationship (pivot table) exists yet

**Frontend (Vue)**
- Simple `create.vue` form with dropdowns for client/type selection
- `useCaseStore()` manages state and CRUD operations
- `caseService.ts` for API calls
- Existing wizard library: `vue3-form-wizard`
- Pattern: `<script setup>` with Composition API

### 2.2 Impact Assessment

| Area | Impact Level | Changes Required |
|------|-------------|------------------|
| Database | Medium | New `case_companions` pivot table |
| Backend API | Low-Medium | Extend `StoreCaseRequest`, new endpoints for wizard data |
| Frontend Components | High | New wizard page + 5 step components |
| Routing | Low | Modify `/cases/create` route |
| State Management | Medium | Extend case store with wizard state |

---

## 3. Database Design

### 3.1 New Migration: case_companions Pivot Table

The wizard allows selecting which companions to include in the case (Step 3). This requires a many-to-many relationship.

```php
// Migration: create_case_companions_table.php
Schema::create('case_companions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('case_id')->constrained()->cascadeOnDelete();
    $table->foreignId('companion_id')->constrained()->cascadeOnDelete();
    $table->timestamps();

    $table->unique(['case_id', 'companion_id']);
});
```

**Model Changes:**
- `ImmigrationCase.php`: Add `companions()` relationship (belongsToMany)
- `Companion.php`: Add `cases()` relationship (belongsToMany)

---

## 4. Backend Implementation

### 4.1 Modified Files

#### 4.1.1 `app/Models/ImmigrationCase.php`
Add companions relationship:
```php
public function companions(): BelongsToMany
{
    return $this->belongsToMany(Companion::class, 'case_companions')
        ->withTimestamps();
}
```

#### 4.1.2 `app/Models/Companion.php`
Add cases relationship:
```php
public function cases(): BelongsToMany
{
    return $this->belongsToMany(ImmigrationCase::class, 'case_companions', 'companion_id', 'case_id')
        ->withTimestamps();
}
```

#### 4.1.3 `app/Http/Requests/Case/StoreCaseRequest.php`
Extend validation rules:
```php
public function rules(): array
{
    return [
        // Existing rules...
        'client_id' => ['required', 'integer', 'exists:clients,id'],
        'case_type_id' => ['required', 'integer', 'exists:case_types,id'],

        // New wizard fields
        'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        'companion_ids' => ['nullable', 'array'],
        'companion_ids.*' => ['integer', 'exists:companions,id'],
    ];
}

// Add validation in withValidator()
$validator->after(function ($validator) {
    // Validate companions belong to the selected client
    if ($this->companion_ids && $this->client_id) {
        $clientCompanionIds = Companion::where('client_id', $this->client_id)
            ->pluck('id')
            ->toArray();

        foreach ($this->companion_ids as $companionId) {
            if (!in_array($companionId, $clientCompanionIds)) {
                $validator->errors()->add('companion_ids',
                    'One or more companions do not belong to the selected client.');
                break;
            }
        }
    }
});
```

#### 4.1.4 `app/Services/Case/CaseService.php`
Modify `createCase()` to handle companions:
```php
public function createCase(array $data): ImmigrationCase
{
    return DB::transaction(function () use ($data) {
        // Extract companion_ids before creating case
        $companionIds = $data['companion_ids'] ?? [];
        unset($data['companion_ids']);

        // Existing case creation logic...
        $case = $this->caseRepository->create($data);

        // Attach companions if provided
        if (!empty($companionIds)) {
            $case->companions()->attach($companionIds);
        }

        // Activity logging...
        return $case->load(['client', 'caseType', 'assignedTo', 'companions']);
    });
}
```

#### 4.1.5 `app/Http/Resources/CaseResource.php`
Add companions to response:
```php
'companions' => $this->whenLoaded('companions', fn () =>
    $this->companions->map(fn ($companion) => [
        'id' => $companion->id,
        'first_name' => $companion->first_name,
        'last_name' => $companion->last_name,
        'full_name' => $companion->full_name,
        'relationship' => $companion->relationship,
        'relationship_label' => $companion->relationship_label,
    ])
),
```

### 4.2 New Endpoints

#### 4.2.1 GET `/api/users/staff` - Get assignable staff
Returns users who can be assigned to cases (for Step 4 dropdowns).

```php
// app/Http/Controllers/Api/UserController.php
public function staff(Request $request): JsonResponse
{
    // Return users in current tenant with case assignment capability
    $users = User::where('tenant_id', Auth::user()->tenant_id)
        ->whereHas('roles', function ($query) {
            $query->whereHas('permissions', function ($q) {
                $q->where('name', 'cases.view');
            });
        })
        ->select('id', 'name', 'email')
        ->orderBy('name')
        ->get();

    return response()->json(['data' => $users]);
}
```

Add route in `routes/api.php`:
```php
Route::get('/users/staff', [UserController::class, 'staff'])->name('users.staff');
```

---

## 5. Frontend Implementation

### 5.1 Component Architecture

```
resources/js/src/
├── views/
│   └── cases/
│       └── wizard/
│           ├── CaseWizard.vue           # Main wizard container
│           ├── steps/
│           │   ├── StepCaseType.vue     # Step 1: Case type selection
│           │   ├── StepClient.vue       # Step 2: Client selection
│           │   ├── StepCompanions.vue   # Step 3: Companions selection
│           │   ├── StepDetails.vue      # Step 4: Case details
│           │   └── StepSummary.vue      # Step 5: Summary & create
│           └── components/
│               ├── CaseTypeCard.vue     # Visual case type card
│               ├── ClientSearchInput.vue # Autocomplete search
│               ├── ClientCard.vue       # Client summary card
│               ├── CompanionCheckbox.vue # Companion selection item
│               └── CreateClientModal.vue # Inline client creation
├── composables/
│   └── useCaseWizard.ts                 # Wizard state management
├── types/
│   └── case.ts                          # Extended with wizard types
└── services/
    └── caseService.ts                   # Extended with staff endpoint
```

### 5.2 Types Extensions

```typescript
// resources/js/src/types/case.ts

// Wizard state interface
export interface CaseWizardState {
    currentStep: number;
    caseType: CaseType | null;
    client: Client | null;
    selectedCompanionIds: number[];
    details: {
        assigned_to: number | null;
        priority: CasePriority;
        language: string;
        description: string;
        hearing_date: string;
        fda_deadline: string;
        brown_sheet_date: string;
        evidence_deadline: string;
    };
    isSubmitting: boolean;
}

// Extended create data
export interface CreateCaseDataWizard extends CreateCaseData {
    companion_ids?: number[];
    assigned_to?: number;
}
```

### 5.3 Composable: useCaseWizard

```typescript
// resources/js/src/composables/useCaseWizard.ts
import { ref, computed, reactive } from 'vue';
import type { CaseType, CaseWizardState, CreateCaseDataWizard } from '@/types/case';
import type { Client } from '@/types/client';
import type { Companion } from '@/types/companion';

export function useCaseWizard() {
    const state = reactive<CaseWizardState>({
        currentStep: 1,
        caseType: null,
        client: null,
        selectedCompanionIds: [],
        details: {
            assigned_to: null,
            priority: 'medium',
            language: 'es',
            description: '',
            hearing_date: '',
            fda_deadline: '',
            brown_sheet_date: '',
            evidence_deadline: '',
        },
        isSubmitting: false,
    });

    const totalSteps = 5;

    const canProceed = computed(() => {
        switch (state.currentStep) {
            case 1: return state.caseType !== null;
            case 2: return state.client !== null;
            case 3: return true; // Companions are optional
            case 4: return true; // Details have defaults
            case 5: return true;
            default: return false;
        }
    });

    const stepLabels = [
        'Case Type',
        'Client',
        'Companions',
        'Details',
        'Summary',
    ];

    function goToStep(step: number) {
        if (step >= 1 && step <= totalSteps) {
            state.currentStep = step;
        }
    }

    function nextStep() {
        if (state.currentStep < totalSteps && canProceed.value) {
            state.currentStep++;
        }
    }

    function prevStep() {
        if (state.currentStep > 1) {
            state.currentStep--;
        }
    }

    function selectCaseType(caseType: CaseType) {
        state.caseType = caseType;
    }

    function selectClient(client: Client) {
        state.client = client;
        // Reset companions when client changes
        state.selectedCompanionIds = [];
    }

    function toggleCompanion(companionId: number) {
        const index = state.selectedCompanionIds.indexOf(companionId);
        if (index === -1) {
            state.selectedCompanionIds.push(companionId);
        } else {
            state.selectedCompanionIds.splice(index, 1);
        }
    }

    function updateDetails(details: Partial<CaseWizardState['details']>) {
        Object.assign(state.details, details);
    }

    function getCreateData(): CreateCaseDataWizard {
        return {
            client_id: state.client!.id,
            case_type_id: state.caseType!.id,
            companion_ids: state.selectedCompanionIds,
            ...state.details,
        };
    }

    function reset() {
        state.currentStep = 1;
        state.caseType = null;
        state.client = null;
        state.selectedCompanionIds = [];
        state.details = {
            assigned_to: null,
            priority: 'medium',
            language: 'es',
            description: '',
            hearing_date: '',
            fda_deadline: '',
            brown_sheet_date: '',
            evidence_deadline: '',
        };
        state.isSubmitting = false;
    }

    return {
        state,
        totalSteps,
        canProceed,
        stepLabels,
        goToStep,
        nextStep,
        prevStep,
        selectCaseType,
        selectClient,
        toggleCompanion,
        updateDetails,
        getCreateData,
        reset,
    };
}
```

### 5.4 Main Components

#### 5.4.1 CaseWizard.vue (Main Container)
```vue
<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/cases" class="text-primary hover:underline">
                    {{ $t('sidebar.cases') }}
                </router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('cases.create_case') }}</span>
            </li>
        </ul>

        <div class="panel">
            <!-- Wizard Header with Steps -->
            <div class="mb-5">
                <h5 class="font-semibold text-lg dark:text-white-light mb-4">
                    {{ $t('cases.create_case') }}
                </h5>

                <!-- Step Indicators -->
                <div class="flex items-center justify-center mb-6">
                    <template v-for="(label, index) in wizard.stepLabels" :key="index">
                        <div
                            class="flex items-center cursor-pointer"
                            @click="wizard.goToStep(index + 1)"
                        >
                            <div
                                :class="[
                                    'w-10 h-10 rounded-full flex items-center justify-center font-semibold',
                                    wizard.state.currentStep === index + 1
                                        ? 'bg-primary text-white'
                                        : wizard.state.currentStep > index + 1
                                            ? 'bg-success text-white'
                                            : 'bg-gray-200 dark:bg-gray-700 text-gray-500'
                                ]"
                            >
                                <template v-if="wizard.state.currentStep > index + 1">
                                    <icon-circle-check class="w-5 h-5" />
                                </template>
                                <template v-else>
                                    {{ index + 1 }}
                                </template>
                            </div>
                            <span
                                class="ml-2 hidden sm:inline"
                                :class="{ 'text-primary font-semibold': wizard.state.currentStep === index + 1 }"
                            >
                                {{ label }}
                            </span>
                        </div>
                        <div
                            v-if="index < wizard.stepLabels.length - 1"
                            class="w-8 sm:w-16 h-0.5 mx-2"
                            :class="wizard.state.currentStep > index + 1 ? 'bg-success' : 'bg-gray-200 dark:bg-gray-700'"
                        />
                    </template>
                </div>
            </div>

            <!-- Step Content -->
            <div class="min-h-[400px]">
                <StepCaseType v-if="wizard.state.currentStep === 1" :wizard="wizard" />
                <StepClient v-else-if="wizard.state.currentStep === 2" :wizard="wizard" />
                <StepCompanions v-else-if="wizard.state.currentStep === 3" :wizard="wizard" />
                <StepDetails v-else-if="wizard.state.currentStep === 4" :wizard="wizard" />
                <StepSummary v-else-if="wizard.state.currentStep === 5" :wizard="wizard" @submit="handleSubmit" />
            </div>

            <!-- Navigation Buttons -->
            <div class="flex justify-between mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <button
                    v-if="wizard.state.currentStep > 1"
                    type="button"
                    class="btn btn-outline-secondary"
                    @click="wizard.prevStep()"
                >
                    <icon-arrow-left class="w-4 h-4 mr-1" />
                    {{ $t('cases.previous') }}
                </button>
                <div v-else></div>

                <div class="flex gap-2">
                    <router-link to="/cases" class="btn btn-outline-secondary">
                        {{ $t('cases.cancel') }}
                    </router-link>
                    <button
                        v-if="wizard.state.currentStep < wizard.totalSteps"
                        type="button"
                        class="btn btn-primary"
                        :disabled="!wizard.canProceed"
                        @click="wizard.nextStep()"
                    >
                        {{ $t('cases.next') }}
                        <icon-arrow-forward class="w-4 h-4 ml-1" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useMeta } from '@/composables/use-meta';
import { useCaseWizard } from '@/composables/useCaseWizard';
import { useCaseStore } from '@/stores/case';
import { useNotification } from '@/composables/useNotification';

// Components
import StepCaseType from './steps/StepCaseType.vue';
import StepClient from './steps/StepClient.vue';
import StepCompanions from './steps/StepCompanions.vue';
import StepDetails from './steps/StepDetails.vue';
import StepSummary from './steps/StepSummary.vue';
import IconCircleCheck from '@/components/icon/icon-circle-check.vue';
import IconArrowLeft from '@/components/icon/icon-arrow-left.vue';
import IconArrowForward from '@/components/icon/icon-arrow-forward.vue';

useMeta({ title: 'Create Case' });

const router = useRouter();
const { t } = useI18n();
const caseStore = useCaseStore();
const { success, error } = useNotification();
const wizard = useCaseWizard();

async function handleSubmit() {
    wizard.state.isSubmitting = true;

    try {
        const data = wizard.getCreateData();
        const response = await caseStore.createCase(data);
        success(t('cases.created_successfully'));
        router.push(`/cases/${response.data.id}`);
    } catch (err: any) {
        error(err.response?.data?.message || t('cases.create_failed'));
    } finally {
        wizard.state.isSubmitting = false;
    }
}
</script>
```

#### 5.4.2 StepCaseType.vue (Step 1)
```vue
<template>
    <div>
        <h6 class="text-lg font-semibold mb-4">{{ $t('cases.select_case_type') }}</h6>
        <p class="text-gray-500 mb-6">{{ $t('cases.select_case_type_description') }}</p>

        <!-- Category Tabs -->
        <div class="flex flex-wrap gap-2 mb-6">
            <button
                v-for="category in categories"
                :key="category.value"
                type="button"
                class="btn"
                :class="activeCategory === category.value ? 'btn-primary' : 'btn-outline-primary'"
                @click="activeCategory = category.value"
            >
                {{ category.label }}
            </button>
        </div>

        <!-- Case Types Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <CaseTypeCard
                v-for="caseType in filteredCaseTypes"
                :key="caseType.id"
                :case-type="caseType"
                :selected="wizard.state.caseType?.id === caseType.id"
                @select="handleSelect(caseType)"
            />
        </div>

        <!-- Selected Case Type Description -->
        <div v-if="wizard.state.caseType" class="mt-6 p-4 bg-primary/10 rounded-lg">
            <h6 class="font-semibold text-primary mb-2">
                {{ wizard.state.caseType.name }}
            </h6>
            <p class="text-gray-600 dark:text-gray-400">
                {{ wizard.state.caseType.description || $t('cases.no_description') }}
            </p>
        </div>
    </div>
</template>
```

#### 5.4.3 StepClient.vue (Step 2)
Key features:
- Autocomplete search for existing clients
- "Create New Client" button opens modal
- Selected client card with summary

#### 5.4.4 StepCompanions.vue (Step 3)
Key features:
- Load companions for selected client
- Checkbox list with companion details
- "Add Companion" inline option
- Optional step - can proceed without selection

#### 5.4.5 StepDetails.vue (Step 4)
Key features:
- Assigned to dropdown (staff users)
- Priority selection
- Language selection
- Date pickers for hearing_date, fda_deadline, etc.
- Description textarea

#### 5.4.6 StepSummary.vue (Step 5)
Key features:
- Summary cards for each section
- Edit buttons to jump to specific steps
- "Create Case" button
- Placeholder sections for auto-tasks and folder structure (future)

### 5.5 Service Extensions

```typescript
// resources/js/src/services/caseService.ts

// Add to caseService object:
async getStaffUsers(): Promise<Array<{ id: number; name: string; email: string }>> {
    const response = await api.get<{ data: Array<{ id: number; name: string; email: string }> }>('/users/staff');
    return response.data.data;
},
```

### 5.6 Router Changes

```typescript
// resources/js/src/router/index.ts

// Replace existing cases-create route:
{
    path: '/cases/create',
    name: 'cases-create',
    component: () => import('../views/cases/wizard/CaseWizard.vue'),
    meta: { permission: 'cases.create' },
},
```

---

## 6. Implementation Phases

### Phase 1: Backend Foundation (Day 1)
**Priority: High | Effort: Medium**

1. Create `case_companions` migration and run it
2. Update `ImmigrationCase` model with `companions()` relationship
3. Update `Companion` model with `cases()` relationship
4. Extend `StoreCaseRequest` with new validation rules
5. Update `CaseService.createCase()` to handle companions
6. Add companions to `CaseResource`
7. Create `GET /api/users/staff` endpoint
8. Write tests for new functionality

**Files to create/modify:**
- `database/migrations/xxxx_create_case_companions_table.php` (new)
- `app/Models/ImmigrationCase.php` (modify)
- `app/Models/Companion.php` (modify)
- `app/Http/Requests/Case/StoreCaseRequest.php` (modify)
- `app/Services/Case/CaseService.php` (modify)
- `app/Http/Resources/CaseResource.php` (modify)
- `app/Http/Controllers/Api/UserController.php` (modify)
- `routes/api.php` (modify)

### Phase 2: Frontend Types & Services (Day 1-2)
**Priority: High | Effort: Low**

1. Extend `case.ts` types with wizard interfaces
2. Add `getStaffUsers()` to caseService
3. Create `useCaseWizard` composable
4. Add i18n translations

**Files to create/modify:**
- `resources/js/src/types/case.ts` (modify)
- `resources/js/src/services/caseService.ts` (modify)
- `resources/js/src/composables/useCaseWizard.ts` (new)
- `resources/js/src/locales/en.json` (modify)
- `resources/js/src/locales/es.json` (modify)

### Phase 3: Wizard Container & Navigation (Day 2)
**Priority: High | Effort: Medium**

1. Create wizard directory structure
2. Implement `CaseWizard.vue` main container
3. Implement step indicators and navigation
4. Create placeholder step components
5. Update router

**Files to create:**
- `resources/js/src/views/cases/wizard/CaseWizard.vue`
- `resources/js/src/views/cases/wizard/steps/StepCaseType.vue` (placeholder)
- `resources/js/src/views/cases/wizard/steps/StepClient.vue` (placeholder)
- `resources/js/src/views/cases/wizard/steps/StepCompanions.vue` (placeholder)
- `resources/js/src/views/cases/wizard/steps/StepDetails.vue` (placeholder)
- `resources/js/src/views/cases/wizard/steps/StepSummary.vue` (placeholder)

### Phase 4: Step 1 - Case Type Selection (Day 3)
**Priority: High | Effort: Medium**
**US-2.2.1 (3 pts)**

1. Implement `CaseTypeCard.vue` component
2. Implement `StepCaseType.vue` with category tabs
3. Visual grid layout
4. Selection and description display

**Files to create/modify:**
- `resources/js/src/views/cases/wizard/components/CaseTypeCard.vue` (new)
- `resources/js/src/views/cases/wizard/steps/StepCaseType.vue` (implement)

### Phase 5: Step 2 - Client Selection (Day 3-4)
**Priority: High | Effort: High**
**US-2.2.2 (5 pts)**

1. Implement `ClientSearchInput.vue` with autocomplete
2. Implement `ClientCard.vue` summary component
3. Implement `CreateClientModal.vue` for inline creation
4. Implement `StepClient.vue`

**Files to create/modify:**
- `resources/js/src/views/cases/wizard/components/ClientSearchInput.vue` (new)
- `resources/js/src/views/cases/wizard/components/ClientCard.vue` (new)
- `resources/js/src/views/cases/wizard/components/CreateClientModal.vue` (new)
- `resources/js/src/views/cases/wizard/steps/StepClient.vue` (implement)

### Phase 6: Step 3 - Companions Selection (Day 4)
**Priority: Medium | Effort: Medium**
**US-2.2.3 (3 pts)**

1. Implement `CompanionCheckbox.vue` component
2. Load companions for selected client
3. Implement `StepCompanions.vue`
4. Optional: inline companion creation

**Files to create/modify:**
- `resources/js/src/views/cases/wizard/components/CompanionCheckbox.vue` (new)
- `resources/js/src/views/cases/wizard/steps/StepCompanions.vue` (implement)

### Phase 7: Step 4 - Case Details (Day 5)
**Priority: Medium | Effort: Medium**
**US-2.2.4 (3 pts)**

1. Staff user dropdown (from `/api/users/staff`)
2. Priority and language selects
3. Date pickers with Flatpickr
4. Description textarea
5. Implement `StepDetails.vue`

**Files to modify:**
- `resources/js/src/views/cases/wizard/steps/StepDetails.vue` (implement)

### Phase 8: Step 5 - Summary & Submit (Day 5-6)
**Priority: High | Effort: High**
**US-2.2.5 (7 pts)**

1. Summary section with all selections
2. Edit links for each step
3. Submit button with loading state
4. Success redirect
5. Placeholder for future features (auto-tasks, folders)

**Files to modify:**
- `resources/js/src/views/cases/wizard/steps/StepSummary.vue` (implement)

### Phase 9: Testing & Polish (Day 6-7)
**Priority: High | Effort: Medium**

1. Backend feature tests for new functionality
2. Manual E2E testing of wizard flow
3. Error handling and edge cases
4. Responsive design review
5. Accessibility review

---

## 7. Testing Strategy

### 7.1 Backend Tests

```php
// tests/Feature/CaseWizardTest.php

public function test_can_create_case_with_companions(): void
{
    // Given a client with companions
    // When creating a case with companion_ids
    // Then case is created and companions are attached
}

public function test_cannot_attach_companions_from_different_client(): void
{
    // Given companions from different client
    // When trying to create case with those companions
    // Then validation fails
}

public function test_staff_endpoint_returns_assignable_users(): void
{
    // Given users with cases.view permission
    // When calling /api/users/staff
    // Then only qualified users are returned
}
```

### 7.2 Frontend Testing Checklist

- [ ] Step 1: Case type selection updates state
- [ ] Step 1: Category filtering works
- [ ] Step 2: Client search returns results
- [ ] Step 2: Client selection updates state
- [ ] Step 2: Create client modal works
- [ ] Step 3: Companions load for selected client
- [ ] Step 3: Toggle companion selection works
- [ ] Step 3: Can proceed without selection
- [ ] Step 4: All form fields save to state
- [ ] Step 4: Staff dropdown populated
- [ ] Step 5: Summary displays all selections
- [ ] Step 5: Edit buttons navigate correctly
- [ ] Step 5: Submit creates case
- [ ] Step 5: Success redirects to case detail
- [ ] Navigation: Previous/Next work correctly
- [ ] Navigation: Step indicators clickable (completed steps)
- [ ] Error handling: API errors displayed

---

## 8. Risks & Mitigations

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|------------|
| Client search performance | Medium | Low | Debounce search input, limit results |
| Wizard state lost on refresh | Medium | Medium | Consider localStorage persistence |
| Mobile layout issues | Medium | Medium | Test responsive design early |
| Complex validation failures | Low | Medium | Clear error messages per step |

---

## 9. Future Enhancements (Out of Scope)

The following features are mentioned in the requirements but marked as placeholders for future implementation:

1. **Auto-generated tasks** - Will be implemented in Epic 2.3
2. **Folder structure creation** - Will be implemented in Epic 3.x
3. **Document templates** - Future enhancement
4. **Workflow automation** - Future enhancement

---

## 10. Definition of Done

- [ ] All backend endpoints implemented and tested
- [ ] All frontend components implemented
- [ ] Wizard flow works end-to-end
- [ ] Case created with companions attached
- [ ] Redirect to case detail on success
- [ ] Error handling implemented
- [ ] i18n translations added (en, es)
- [ ] Code reviewed and approved
- [ ] No console errors or warnings
- [ ] Responsive design verified

---

## 11. Dependencies

- **Existing:** Epic 2.1 (Cases CRUD) - Completed
- **Existing:** Epic 1.2 (Clients) - Completed
- **Existing:** Epic 1.3 (Companions) - Completed
- **Library:** vue3-form-wizard (already installed, but we'll use custom implementation)
- **Library:** Flatpickr (already installed for date pickers)

---

## 12. Appendix: API Contract

### POST /api/cases (Extended)

**Request Body:**
```json
{
    "client_id": 123,
    "case_type_id": 1,
    "assigned_to": 5,
    "companion_ids": [1, 2, 3],
    "priority": "medium",
    "language": "es",
    "description": "Optional case description",
    "hearing_date": "2026-06-15",
    "fda_deadline": "2026-05-01",
    "brown_sheet_date": null,
    "evidence_deadline": null
}
```

**Response:**
```json
{
    "message": "Case created successfully.",
    "data": {
        "id": 42,
        "case_number": "2026-ASYLUM-00042",
        "client_id": 123,
        "case_type_id": 1,
        "assigned_to": 5,
        "status": "active",
        "priority": "medium",
        "companions": [
            { "id": 1, "first_name": "Maria", "last_name": "Garcia", "relationship": "spouse" },
            { "id": 2, "first_name": "Carlos", "last_name": "Garcia", "relationship": "child" }
        ],
        // ... other fields
    }
}
```

### GET /api/users/staff

**Response:**
```json
{
    "data": [
        { "id": 1, "name": "Admin User", "email": "admin@example.com" },
        { "id": 5, "name": "Consultant One", "email": "consultant1@example.com" }
    ]
}
```
