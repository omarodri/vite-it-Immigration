# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Vristo POC** is a full-stack sales admin dashboard combining Laravel 12 backend with Vue 3.5 + TypeScript frontend. It follows a **Single Page Application (SPA)** architecture where Laravel serves the initial HTML and acts as an API server, while Vue handles all client-side routing and UI rendering.

## Common Commands

```bash
# Development
npm run dev              # Start Vite dev server (hot reload)
npm run build            # Production build
php artisan serve        # Start Laravel dev server (if not using Herd)

# Database
php artisan migrate      # Run database migrations
php artisan migrate:fresh --seed  # Reset and seed database

# Testing
./vendor/bin/phpunit                    # Run all PHP tests
./vendor/bin/phpunit tests/Unit         # Run unit tests only
./vendor/bin/phpunit tests/Feature      # Run feature tests only
./vendor/bin/phpunit --filter=TestName  # Run specific test

# Cache & Optimization
php artisan config:cache    # Cache configuration
php artisan route:cache     # Cache routes
php artisan view:cache      # Cache views
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
AppController::index()
    return view('app')
    │
    ▼
resources/views/app.blade.php
    @vite(['resources/js/src/main.ts'])
    <div id="app"></div>
    │
    ▼
Vue 3 SPA mounts on #app
    Vue Router handles all navigation (no page reloads)
```

### Architectural Pattern
- **Backend (Laravel):** Serves static files, provides API endpoints, handles authentication
- **Frontend (Vue SPA):** Manages all UI, routing, and state
- **Communication:** REST API (future), Laravel Sanctum for auth tokens

## Project Structure

```
vristo-poc/
├── app/                          # Laravel backend
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Controller.php    # Base controller
│   │   │   └── AppController.php # Serves Vue SPA (single controller)
│   │   └── Middleware/           # 11 standard middlewares
│   ├── Models/
│   │   └── User.php              # User model with Sanctum
│   └── Providers/                # 5 service providers
│
├── routes/
│   ├── web.php                   # Catch-all route → Vue SPA
│   └── api.php                   # API routes (expandable)
│
├── resources/
│   ├── views/
│   │   └── app.blade.php         # Single blade template (SPA mount point)
│   │
│   └── js/src/                   # Vue 3 frontend
│       ├── main.ts               # Entry point
│       ├── App.vue               # Root component (layout switching)
│       ├── router/index.ts       # 60+ routes defined
│       ├── stores/index.ts       # Pinia store (global state)
│       ├── i18n.ts               # Internationalization config
│       ├── app-setting.ts        # Theme initialization
│       │
│       ├── layouts/
│       │   ├── app-layout.vue    # Main layout (header, sidebar, footer)
│       │   └── auth-layout.vue   # Auth pages layout (minimal)
│       │
│       ├── views/                # 99+ page components
│       │   ├── index.vue         # Main dashboard
│       │   ├── analytics.vue, finance.vue, crypto.vue
│       │   ├── apps/             # Chat, mailbox, calendar, notes, etc.
│       │   ├── forms/            # 15 form types
│       │   ├── datatables/       # 14 datatable variants
│       │   ├── components/       # UI component demos
│       │   ├── elements/         # UI element demos
│       │   ├── auth/             # Login, register, etc.
│       │   ├── pages/            # FAQ, errors, knowledge base
│       │   └── users/            # Profile, settings
│       │
│       ├── components/
│       │   ├── layout/           # Header.vue, Sidebar.vue, Footer.vue
│       │   ├── icon/             # 150+ SVG icon components
│       │   ├── ThemeCustomizer.vue
│       │   └── plugins/highlight.vue
│       │
│       ├── composables/
│       │   ├── use-meta.ts       # Page meta tags
│       │   └── codePreview.ts    # Code highlighting
│       │
│       ├── locales/              # 16 language JSON files
│       │   └── en.json, es.json, fr.json, de.json, etc.
│       │
│       └── assets/css/           # 19 CSS files
│           ├── app.css           # Main imports
│           ├── tailwind.css      # Tailwind directives
│           └── [component].css   # Component-specific styles
│
├── public/assets/                # Static assets
│   └── images/                   # Logos, flags, products, etc.
│
├── config/                       # Laravel configuration (16 files)
├── database/
│   ├── migrations/               # 4 base migrations
│   ├── seeders/
│   └── factories/
│
├── vite.config.ts                # Vite + Laravel plugin config
├── tailwind.config.cjs           # Tailwind CSS config
├── tsconfig.json                 # TypeScript config
└── package.json                  # NPM dependencies
```

## Key Technologies

### Backend
| Technology | Version | Purpose |
|------------|---------|---------|
| Laravel | 12 | PHP Framework |
| PHP | 8.2+ | Server language |
| MySQL | 8.0 | Database |
| Laravel Sanctum | built-in | API authentication |

### Frontend
| Technology | Version | Purpose |
|------------|---------|---------|
| Vue | 3.5 | UI Framework (Composition API) |
| TypeScript | 5.7 | Type safety |
| Vue Router | 4.5 | Client-side routing |
| Pinia | 2.3 | State management |
| Tailwind CSS | 3.4 | Utility-first CSS |
| Vue I18n | 11 | Internationalization (16 languages) |
| Vite | 6 | Build tool + dev server |

### UI Libraries
| Library | Purpose |
|---------|---------|
| ApexCharts | Interactive charts |
| FullCalendar | Calendar component |
| SweetAlert2 | Alerts and modals |
| Swiper | Carousels/sliders |
| Quill/EasyMDE | Rich text editors |
| Flatpickr | Date picker |
| vue3-datatable | Data tables |
| vue-draggable-plus | Drag and drop |

## Important Patterns

### Import Alias
Use `@/` for imports from `resources/js/src/`:
```typescript
import { useAppStore } from '@/stores/index';
import Header from '@/components/layout/Header.vue';
```

### State Management (Pinia)
All global state flows through `stores/index.ts`:
```typescript
const store = useAppStore();

// State
store.theme        // 'light' | 'dark' | 'system'
store.menu         // 'vertical' | 'collapsible-vertical' | 'horizontal'
store.layout       // 'full' | 'boxed-layout'
store.locale       // 'en' | 'es' | 'fr' | ... (16 languages)
store.mainLayout   // 'app' | 'auth'
store.sidebar      // boolean (mobile sidebar visibility)
store.isDarkMode   // boolean
store.rtlClass     // 'ltr' | 'rtl'

// Actions
store.toggleTheme('dark')
store.toggleMenu('horizontal')
store.toggleLocale('es')
store.setMainLayout('auth')
```
State persists to localStorage automatically.

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

## Routes Available

### Dashboards
- `/` - Main dashboard
- `/analytics` - Analytics dashboard
- `/finance` - Finance dashboard
- `/crypto` - Crypto dashboard

### Apps
- `/apps/chat` - Chat application
- `/apps/mailbox` - Email client
- `/apps/calendar` - Calendar
- `/apps/todolist` - To-do list
- `/apps/notes` - Notes
- `/apps/scrumboard` - Kanban board
- `/apps/contacts` - Contacts manager
- `/apps/invoice/list|add|preview|edit` - Invoice management

### Forms (`/forms/*`)
basic, input-group, layouts, validation, input-mask, select2, touchspin, checkbox-radio, switches, wizards, file-upload, quill-editor, markdown-editor, date-picker, clipboard

### DataTables (`/datatables/*`)
basic, advanced, skin, order-sorting, columns-filter, multi-column, multiple-tables, alt-pagination, checkbox, range-search, export, sticky-header, clone-header, column-chooser

### Components (`/components/*`)
tabs, accordions, modals, cards, carousel, countdown, counter, sweetalert, timeline, notifications, media-object, list-group, pricing-table, lightbox

### Elements (`/elements/*`)
alerts, avatar, badges, breadcrumbs, buttons, buttons-group, color-library, dropdown, infobox, jumbotron, loader, pagination, popovers, progress-bar, search, tooltips, treeview, typography

### Auth (`/auth/*`)
boxed-signin, boxed-signup, boxed-lockscreen, boxed-password-reset, cover-login, cover-register, cover-lockscreen, cover-password-reset

### Pages (`/pages/*`)
knowledge-base, faq, contact-us-boxed, contact-us-cover, coming-soon-boxed, coming-soon-cover, error404, error500, error503, maintenence

### Users
- `/users/profile` - User profile
- `/users/user-account-settings` - Account settings

### Other
- `/charts` - Chart examples
- `/widgets` - Widget examples
- `/font-icons` - Icon library
- `/dragndrop` - Drag & drop demo
- `/tables` - Basic tables

## Database

- **Connection:** MySQL on port 3306
- **Database:** vristo-poc
- **Session Storage:** Database-driven
- **Migrations:** 4 base tables (users, password_resets, failed_jobs, personal_access_tokens)

## Tailwind CSS Colors

Custom color palette defined in `tailwind.config.cjs`:
```javascript
primary: '#4361ee'      // Indigo blue
secondary: '#805dca'    // Purple
success: '#00ab55'      // Green
danger: '#e7515a'       // Red
warning: '#e2a03f'      // Orange
info: '#2196f3'         // Blue
dark: '#3b3f5c'
```

Dark mode: Use `dark:` prefix (e.g., `dark:bg-gray-800`)

## Internationalization

16 languages supported via Vue I18n:
- English (en), Spanish (es), French (fr), German (de)
- Italian (it), Portuguese (pt), Russian (ru), Polish (pl)
- Turkish (tr), Japanese (ja), Chinese (zh), Greek (el)
- Hungarian (hu), Danish (da), Swedish (sv), Arabic (ae)

Arabic (ae) automatically enables RTL layout.

Translation files: `resources/js/src/locales/*.json`

## Development Notes

### Vite Configuration
- Entry point: `resources/js/src/main.ts`
- Alias `@` → `resources/js/src/`
- Laravel Vite plugin handles asset versioning
- Hot reload enabled by default

### Known Configurations
- FullCalendar requires alias: `@fullcalendar/core/vdom` → `@fullcalendar/core`
- Swiper modules import from `swiper/modules` (not `swiper`)
- Perfect Scrollbar uses named export: `{ PerfectScrollbarPlugin }`
- @unhead/vue client import: `@unhead/vue/client`

### File Naming
- Vue components: PascalCase (`MyComponent.vue`)
- Composables: camelCase with `use` prefix (`use-meta.ts`)
- CSS files: kebab-case (`quill-editor.css`)
