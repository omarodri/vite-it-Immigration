# AGENTS.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

**VITE-IT Immigration** is a full-stack sales admin dashboard combining Laravel 12 backend with Vue 3.5 + TypeScript frontend. It follows a **Single Page Application (SPA)** architecture where Laravel serves the initial HTML and acts as an API server, while Vue handles all client-side routing and UI rendering.

## Common Commands

```bash
# Development
npm run dev              # Start Vite dev server (hot reload)
npm run build            # Production build
php artisan serve        # Start Laravel dev server (if not using Herd)

# Database
php artisan migrate                    # Run migrations
php artisan migrate:fresh --seed       # Reset and seed database

# PHP Testing (PHPUnit)
./vendor/bin/phpunit                   # Run all PHP tests
./vendor/bin/phpunit tests/Unit        # Unit tests only
./vendor/bin/phpunit tests/Feature     # Feature tests only
./vendor/bin/phpunit --filter=TestName # Run specific test

# Frontend Testing (Vitest)
npm run test             # Run all frontend tests
npm run test:watch       # Watch mode
npm run test:coverage    # With coverage

# Cache & Optimization
php artisan config:cache    # Cache configuration
php artisan route:cache     # Cache routes
php artisan optimize        # Optimize for production
```

## Architecture Overview

### Request Flow
```
Browser Request (any URL)
    │
    ▼
routes/web.php
    Route::get('/{any}', [AppController::class, 'index'])->where('any', '.*')
    │
    ▼
AppController::index() → return view('app')
    │
    ▼
resources/views/app.blade.php → @vite(['resources/js/src/main.ts'])
    │
    ▼
Vue 3 SPA mounts on #app, Vue Router handles all navigation
```

### Key Directories
- **Backend (Laravel):** `app/`, `routes/`, `config/`, `database/`
- **Frontend (Vue SPA):** `resources/js/src/`
  - Entry point: `main.ts`
  - Routes: `router/index.ts`
  - State: `stores/` (Pinia - auth, profile, role, user, index)
  - Views: `views/`
  - Components: `components/`
  - Composables: `composables/`
  - i18n: `locales/` (16 languages)
- **Frontend Tests:** `resources/js/tests/` (Vitest with happy-dom)
- **Specs/Docs:** `spec/` (architectural and implementation specifications)

## Important Patterns

### Import Alias
Use `@/` for imports from `resources/js/src/`:
```typescript
import { useAppStore } from '@/stores/index';
import Header from '@/components/layout/Header.vue';
```

### State Management (Pinia)
Global state in `stores/index.ts`, domain stores in separate files:
```typescript
const store = useAppStore();
store.theme        // 'light' | 'dark' | 'system'
store.menu         // 'vertical' | 'collapsible-vertical' | 'horizontal'
store.layout       // 'full' | 'boxed-layout'
store.locale       // 'en' | 'es' | 'fr' | ... (16 languages)
store.mainLayout   // 'app' | 'auth'
```

### Layout System
`App.vue` dynamically switches layouts based on route meta:
```typescript
// In router/index.ts
{
    path: '/auth/login',
    component: () => import('../views/auth/cover-login.vue'),
    meta: { layout: 'auth' }  // Uses auth-layout.vue
}
// Routes without meta.layout use app-layout.vue by default
```

### Vue Component Style
Use Composition API with `<script setup>`:
```vue
<script lang="ts" setup>
import { ref, computed } from 'vue';
import { useAppStore } from '@/stores/index';
import { useMeta } from '@/composables/use-meta';

useMeta({ title: 'Page Title' });
const store = useAppStore();
const items = ref([]);
</script>
```

### Adding New Pages
1. Create view in `resources/js/src/views/`
2. Add route in `resources/js/src/router/index.ts`
3. Add menu item in `components/layout/Sidebar.vue` if needed

### Adding API Endpoints
1. Create controller: `php artisan make:controller Api/MyController`
2. Add route in `routes/api.php`
3. Call from Vue using fetch/axios with `/api/` prefix

## Code Style Guidelines

### Backend (Laravel/PHP)
- Use PHP 8.2+ features (readonly properties, match expressions)
- Apply strict typing: `declare(strict_types=1)`
- Follow PSR-12 coding standards
- Use Laravel's built-in helpers (`Str::`, `Arr::`)
- Implement Form Requests for validation
- Use Eloquent ORM and migrations for database operations

### Frontend (TypeScript/Vue)
- Use functional and declarative programming; avoid classes
- Use descriptive variable names with auxiliary verbs (`isLoading`, `hasError`)
- Prefer interfaces over types for extendability
- Avoid enums; use maps instead
- Always use Vue Composition API with `<script setup>`
- Use Tailwind CSS with mobile-first responsive design
- Dynamic loading for non-critical components

## Known Configurations

- FullCalendar requires alias: `@fullcalendar/core/vdom` → `@fullcalendar/core`
- Swiper modules import from `swiper/modules` (not `swiper`)
- Perfect Scrollbar uses named export: `{ PerfectScrollbarPlugin }`
- @unhead/vue client import: `@unhead/vue/client`

## Tailwind CSS Colors

Custom palette in `tailwind.config.cjs`:
```
primary: '#4361ee'    secondary: '#805dca'    success: '#00ab55'
danger: '#e7515a'     warning: '#e2a03f'      info: '#2196f3'
```
Dark mode: Use `dark:` prefix (e.g., `dark:bg-gray-800`)

## File Naming
- Vue components: PascalCase (`MyComponent.vue`)
- Composables: camelCase with `use` prefix (`use-meta.ts`)
- CSS files: kebab-case (`quill-editor.css`)
- Directories: lowercase with dashes (`auth-wizard`)
