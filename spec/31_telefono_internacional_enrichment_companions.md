---
tipo: spec
numero: 31
modulo: Clientes y Acompañantes
dominio: CLNT
estado: DONE
tags: [spec, clientes, done]
relacionados:
  - "[[17_iuc_campo_companions|Spec 17]]"
---
# [[31_telefono_internacional_enrichment_companions|Spec 31]]: Internacionalizacion Telefonica y Enriquecimiento de Acompanantes

## Informacion del Documento

- **Fecha:** 2026-03-24
- **Tipo:** Analisis de Impacto Tecnico
- **Scope:** Codigo de pais en telefonos, enriquecimiento de Companions, logica "Otro" en estatus migratorio
- **Dependencias:** [[08_permisos_clientes_acompanantes_roles|Spec 08]] (Permisos Clientes/Acompanantes), [[17_iuc_campo_companions|Spec 17]] (IUC Companions)
- **Estado:** Completo - 6 fases implementadas (43 tests, 106 assertions)

---

## 1. Resumen Ejecutivo

Este documento analiza el impacto tecnico de tres modificaciones interrelacionadas:

1. **Internacionalizacion Telefonica:** Agregar campo de codigo de pais a todos los inputs de telefono (Clientes y Acompanantes).
2. **Enriquecimiento de Acompanantes:** Nivelar los datos de Companions con los de Clients agregando email, telefono y estatus migratorio.
3. **Logica "Otro" en Estatus Migratorio:** Implementar campo de texto condicional cuando se selecciona "Otro" en el dropdown de `canada_status`.

### Contexto

El sistema actualmente captura telefonos en formato fijo norteamericano `(###) ###-####` sin codigo de pais. Los Companions tienen datos limitados (nombre, relacion, pasaporte, nacionalidad) comparados con los Clients que incluyen email, telefono y estatus migratorio completo. El enum `canada_status` ya contiene la opcion `other` pero no existe un campo de texto asociado para especificar el valor personalizado en Companions.

---

## 2. Reglas de Negocio

| # | Regla | Descripcion |
|---|---|---|
| RN1 | Codigo de Pais | Todo input de telefono debe incluir un selector de codigo de pais |
| RN2 | Default +1 | El codigo de pais por defecto es `+1` (Canada/USA) para registros nuevos y existentes |
| RN3 | Paridad de Datos | Los Companions deben capturar email, telefono y estatus migratorio igual que los Clients |
| RN4 | Campo "Otro" Condicional | Si `canada_status = 'other'`, el campo de texto `canada_status_other` es obligatorio |
| RN5 | Limpieza Automatica | Si `canada_status` cambia de `'other'` a otro valor, `canada_status_other` se limpia automaticamente |
| RN6 | Mascara Condicional | La mascara `(###) ###-####` solo aplica para codigo `+1`; otros codigos usan input libre |
| RN7 | Normalizacion | El texto de "Otro" se normaliza con `trim()` + `ucfirst()` antes de guardar |

---

## 3. Estado Actual del Codebase (Verificado)

### 3.1 Modelo Client

**Archivo:** `app/Models/Client.php`

| Campo | Tipo | Notas |
|---|---|---|
| `phone` | varchar(30) | Unico por tenant, formato `(555) 123-4567` |
| `secondary_phone` | varchar(30) | Nullable |
| `canada_status` | enum | `asylum_seeker, refugee, temporary_resident, permanent_resident, citizen, visitor, student, worker, other` |
| `other_status_1` | varchar | Texto libre, semantica ambigua |
| `other_status_2` | varchar | Texto libre, semantica ambigua |
| **NO tiene** | `phone_country_code` | |

### 3.2 Modelo Companion

**Archivo:** `app/Models/Companion.php`

**Campos actuales (13):** `tenant_id`, `client_id`, `first_name`, `last_name`, `relationship`, `relationship_other`, `date_of_birth`, `gender`, `passport_number`, `passport_country`, `passport_expiry_date`, `nationality`, `notes`, `iuc`

**Campos faltantes vs Client:**
- `email` — no existe
- `phone` — no existe
- `phone_country_code` — no existe
- `canada_status` — no existe
- `canada_status_other` — no existe

### 3.3 Frontend: Inputs de Telefono

**Patron actual** (identico en todos los formularios):
```vue
<input v-model="form.phone" v-maska="'(###) ###-####'" placeholder="(___) ___-____" />
```

**Archivos que usan este patron:**
- `resources/js/src/views/clients/create.vue`
- `resources/js/src/views/clients/edit.vue`
- `resources/js/src/views/cases/wizard/components/CreateClientModal.vue`

**No existe:**
- Componente `PhoneInput` reutilizable
- Selector de codigo de pais
- Data de codigos telefonicos por pais
- Campos de telefono en formularios de Companion

### 3.4 Logica "Otro" Existente

El patron `v-if` para campos condicionales ya existe para `relationship_other` en `StepCompanions.vue`:
```vue
<input v-if="form.relationship === 'other'" v-model="form.relationship_other" />
```

Se reutilizara este mismo patron para `canada_status_other`.

---

## 4. Diseno Tecnico

### 4.1 Migraciones

#### Migracion 1: `add_phone_country_code_to_clients`

```php
Schema::table('clients', function (Blueprint $table) {
    $table->string('phone_country_code', 6)->nullable()->default('+1')->after('phone');
    $table->string('secondary_phone_country_code', 6)->nullable()->default('+1')->after('secondary_phone');
});
```

**Nota sobre datos historicos:** El default `+1` es correcto para el 95%+ de registros existentes (sistema de inmigracion canadiense). Usuarios pueden corregir caso por caso.

#### Migracion 2: `add_contact_and_status_to_companions`

```php
Schema::table('companions', function (Blueprint $table) {
    $table->string('email', 255)->nullable()->after('iuc');
    $table->string('phone', 30)->nullable()->after('email');
    $table->string('phone_country_code', 6)->nullable()->default('+1')->after('phone');
    $table->string('canada_status')->nullable()->after('phone_country_code');
    $table->string('canada_status_other', 255)->nullable()->after('canada_status');
});
```

**Decision de diseno:** `canada_status` como `string` en lugar de `enum` para Companions. Permite flexibilidad sin alterar la migracion del Client existente. La validacion en el Request garantiza los valores permitidos.

### 4.2 Modelos

#### Client — cambios

```php
// Agregar a $fillable:
'phone_country_code',
'secondary_phone_country_code',
```

#### Companion — cambios

```php
// Agregar a $fillable:
'email',
'phone',
'phone_country_code',
'canada_status',
'canada_status_other',

// Agregar accessor:
public function getCanadaStatusLabelAttribute(): ?string
{
    // Reutilizar misma logica de labels que Client
    return match($this->canada_status) {
        'asylum_seeker'        => 'Solicitante de Asilo',
        'refugee'              => 'Refugiado',
        'temporary_resident'   => 'Residente Temporal',
        'permanent_resident'   => 'Residente Permanente',
        'citizen'              => 'Ciudadano',
        'visitor'              => 'Visitante',
        'student'              => 'Estudiante',
        'worker'               => 'Trabajador',
        'other'                => $this->canada_status_other ?? 'Otro',
        default                => null,
    };
}

// Agregar a $logAttributes (activity log):
'email', 'phone', 'canada_status',
```

### 4.3 Validaciones (Form Requests)

#### StoreCompanionRequest — nuevas reglas

```php
'email'                => ['nullable', 'email', 'max:255'],
'phone'                => ['nullable', 'string', 'max:30'],
'phone_country_code'   => ['nullable', 'string', 'max:6'],
'canada_status'        => ['nullable', Rule::in([
    'asylum_seeker', 'refugee', 'temporary_resident',
    'permanent_resident', 'citizen', 'visitor',
    'student', 'worker', 'other',
])],
'canada_status_other'  => ['nullable', 'string', 'max:255', 'required_if:canada_status,other'],
```

#### StoreClientRequest — nuevas reglas

```php
'phone_country_code'           => ['nullable', 'string', 'max:6'],
'secondary_phone_country_code' => ['nullable', 'string', 'max:6'],
```

#### Limpieza en prepareForValidation (ambos Requests)

```php
if ($this->canada_status !== 'other') {
    $this->merge(['canada_status_other' => null]);
}
if ($this->canada_status_other) {
    $this->merge(['canada_status_other' => ucfirst(trim($this->canada_status_other))]);
}
```

### 4.4 API Resources

#### CompanionResource — agregar campos

```php
'email'                => $this->email,
'phone'                => $this->phone,
'phone_country_code'   => $this->phone_country_code,
'canada_status'        => $this->canada_status,
'canada_status_other'  => $this->canada_status_other,
'canada_status_label'  => $this->canada_status_label,
```

#### ClientResource (si existe) o Controller response — agregar campos

```php
'phone_country_code'           => $this->phone_country_code,
'secondary_phone_country_code' => $this->secondary_phone_country_code,
```

### 4.5 Componente Frontend: PhoneInput.vue

**Ubicacion:** `resources/js/src/components/PhoneInput.vue`

**Props:**
- `modelValue: string` — numero de telefono
- `countryCode: string` — codigo de pais (default '+1')
- `placeholder: string`
- `required: boolean`
- `error: boolean`
- `disabled: boolean`

**Emits:**
- `update:modelValue` — cuando cambia el telefono
- `update:countryCode` — cuando cambia el codigo de pais

**Comportamiento:**
- Selector de codigo de pais (top 20 paises de origen de inmigrantes a Canada)
- Si `countryCode === '+1'`: aplica mascara `(###) ###-####`
- Si otro codigo: input libre, validacion regex `^\d{7,15}$`

**Data:** `resources/js/src/data/phoneCodes.ts`

```typescript
export const PHONE_CODES = [
    { code: '+1',   country: 'CA', label: 'Canada / USA (+1)' },
    { code: '+52',  country: 'MX', label: 'Mexico (+52)' },
    { code: '+57',  country: 'CO', label: 'Colombia (+57)' },
    { code: '+55',  country: 'BR', label: 'Brasil (+55)' },
    { code: '+58',  country: 'VE', label: 'Venezuela (+58)' },
    { code: '+51',  country: 'PE', label: 'Peru (+51)' },
    { code: '+56',  country: 'CL', label: 'Chile (+56)' },
    { code: '+54',  country: 'AR', label: 'Argentina (+54)' },
    { code: '+593', country: 'EC', label: 'Ecuador (+593)' },
    { code: '+53',  country: 'CU', label: 'Cuba (+53)' },
    { code: '+509', country: 'HT', label: 'Haiti (+509)' },
    { code: '+1',   country: 'JM', label: 'Jamaica (+1)' },
    { code: '+33',  country: 'FR', label: 'France (+33)' },
    { code: '+44',  country: 'GB', label: 'United Kingdom (+44)' },
    { code: '+91',  country: 'IN', label: 'India (+91)' },
    { code: '+86',  country: 'CN', label: 'China (+86)' },
    { code: '+63',  country: 'PH', label: 'Philippines (+63)' },
    { code: '+234', country: 'NG', label: 'Nigeria (+234)' },
    { code: '+212', country: 'MA', label: 'Morocco (+212)' },
    { code: '+213', country: 'DZ', label: 'Algeria (+213)' },
];
```

### 4.6 Tipos TypeScript

#### companion.ts — agregar

```typescript
export interface Companion {
    // ... campos existentes ...
    email: string | null;
    phone: string | null;
    phone_country_code: string | null;
    canada_status: CanadaStatus | null;
    canada_status_other: string | null;
    canada_status_label?: string | null;
}
```

#### client.ts — agregar

```typescript
export interface Client {
    // ... campos existentes ...
    phone_country_code: string | null;
    secondary_phone_country_code: string | null;
}
```

---

## 5. Componentes Vue a Modificar

| Componente | Archivo | Cambios |
|---|---|---|
| Client Create | `views/clients/create.vue` | Reemplazar input telefono por `PhoneInput`, agregar logica "Otro" para `canada_status` |
| Client Edit | `views/clients/edit.vue` | Mismo que create |
| CreateClientModal | `views/cases/wizard/components/CreateClientModal.vue` | Agregar `PhoneInput` |
| StepCompanions | `views/cases/wizard/steps/StepCompanions.vue` | Agregar `email`, `PhoneInput`, selector `canada_status` con logica "Otro" |
| CompanionCheckbox | `views/cases/wizard/components/CompanionCheckbox.vue` | Mostrar email y estatus en el chip |
| Client Show | `views/clients/show.vue` | Mostrar codigo de pais junto al telefono |
| Traducciones | `locales/es.json`, `en.json`, `fr.json` | Labels para nuevos campos |

---

## 6. Riesgos y Mitigaciones

### 6.1 Normalizacion del Campo "Otro"

**Riesgo:** Texto libre genera variantes semanticas duplicadas ("turista" vs "Visitante" vs "Tourist").

**Mitigacion (este sprint):**
- `trim()` + `ucfirst()` en backend antes de guardar
- Placeholder con ejemplos: "Ej: Visitante temporal, Proteccion temporal..."

**Mitigacion (futuro):**
- Tabla `immigration_statuses` con autocomplete en frontend
- Query periodica para promover valores frecuentes de `canada_status_other` al enum

### 6.2 Datos Historicos

**Riesgo:** Telefonos existentes sin `phone_country_code`.

**Mitigacion:** Default `+1` en migracion. Correcto para 95%+ de registros (contexto de inmigracion canadiense).

### 6.3 Mascara de Telefono

**Riesgo:** La mascara `(###) ###-####` es invalida para telefonos internacionales.

**Mitigacion:** Mascara condicional — solo para `+1`, input libre para otros codigos con validacion regex.

### 6.4 Densidad de Formularios

**Riesgo:** StepCompanions.vue ya tiene 10+ campos. Agregar 3 mas puede saturar el modal.

**Mitigacion:** Organizar en secciones colapsables: "Datos Personales", "Contacto", "Estatus Migratorio", "Pasaporte".

---

## 7. Checklist de Implementacion

### Fase 1: Migraciones y Backend

- [x] Crear migracion `add_phone_country_code_to_clients`
- [x] Crear migracion `add_contact_and_status_to_companions`
- [x] Actualizar `Client` model — agregar campos a `$fillable`
- [x] Actualizar `Companion` model — agregar campos a `$fillable`, accessor, `$logAttributes`
- [x] Actualizar `StoreCompanionRequest` / `UpdateCompanionRequest` — nuevas reglas
- [x] Actualizar `StoreClientRequest` / `UpdateClientRequest` — nuevas reglas
- [x] Agregar limpieza de `canada_status_other` en `prepareForValidation`
- [x] Actualizar `CompanionResource` — exponer nuevos campos
- [x] Actualizar response de Client en Controller (campos llegan via $fillable + toArray)

### Fase 2: Componente PhoneInput

- [x] Crear `data/phoneCodes.ts`
- [x] Crear `components/PhoneInput.vue`

### Fase 3: Formularios de Clientes

- [x] Actualizar `types/client.ts`
- [x] Actualizar `clients/create.vue` — PhoneInput + logica "Otro"
- [x] Actualizar `clients/edit.vue` — PhoneInput + logica "Otro"
- [x] Actualizar `wizard/components/CreateClientModal.vue` — PhoneInput

### Fase 4: Formularios de Companions

- [x] Actualizar `types/companion.ts`
- [x] Actualizar `wizard/steps/StepCompanions.vue` — email, PhoneInput, canada_status + "Otro"
- [x] Actualizar `wizard/components/CompanionCheckbox.vue` — mostrar email y estatus

### Fase 5: Presentacion y Traducciones

- [x] Actualizar `clients/show.vue` — mostrar codigo de pais junto al telefono
- [x] Actualizar `locales/es.json` — labels nuevos
- [x] Actualizar `locales/en.json` — labels nuevos
- [x] Actualizar `locales/fr.json` — labels nuevos

### Fase 6: Tests

- [x] Test: crear companion con email, phone, canada_status
- [x] Test: `canada_status_other` requerido cuando `canada_status = 'other'`
- [x] Test: `canada_status_other` se limpia cuando status cambia de 'other' a otro valor
- [x] Test: `phone_country_code` default '+1' en registros nuevos
- [x] Test: CompanionResource incluye nuevos campos
- [x] Test: `canada_status_other` se guarda con ucfirst normalizado

---

## 8. Estimacion de Archivos Impactados

| Capa | Archivos Nuevos | Archivos Modificados |
|---|---|---|
| Migraciones | 2 | 0 |
| Modelos | 0 | 2 (Client, Companion) |
| Requests | 0 | 4 (Store/Update x Client/Companion) |
| Resources | 0 | 1 (CompanionResource) |
| Controllers | 0 | 1 (ClientController — response) |
| Componentes Vue | 2 (PhoneInput, phoneCodes) | 6 (create, edit, modal, step, checkbox, show) |
| Tipos TS | 0 | 2 (client.ts, companion.ts) |
| Traducciones | 0 | 3 (es, en, fr) |
| Tests | 1 | 0 |
| **Total** | **5** | **19** |
