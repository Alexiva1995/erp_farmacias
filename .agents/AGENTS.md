# NORMAS ESTRICTAS DE DESARROLLO — ERP FARMACIAS

## 1. IDIOMA Y COMUNICACIÓN
- **Respuestas:** SIEMPRE en ESPAÑOL.
- **Código fuente:** SIEMPRE en INGLÉS.
- **Comentarios de código:** SIEMPRE en ESPAÑOL.
- **Sin preámbulos:** Cero saludos, despedidas, emojis, ofertas finales ("¿quieres que...?") ni comentarios introductorios. Ir directo al grano.
- **Pensamiento interno:** Estrictamente analítico, técnico y directo. Sin exclamaciones, narración coloquial ni suposiciones dramáticas.
- **Resumen final:** Viñetas breves con `archivo → cambio clave`. Sin explicaciones redundantes ni recapitulación de pasos.

## 2. PATRONES DE LARAVEL 12
- **Arquitectura:** Repository Pattern (`app/Repository/`, `app/Contracts/`) y Service Layer (`app/Services/`).
- **Controladores delgados:** Solo entradas/salidas HTTP. Toda la lógica de negocio vive en Servicios.
- **Validación:** SIEMPRE Form Requests (`app/Http/Requests/`). Prohibido `$request->validate()` y `Validator::make()` inline en controladores o servicios HTTP.
- **Respuestas API:** SIEMPRE API Resources (`app/Http/Resources/`).
- **Enums:** PHP 8.1+ Enums para estados y valores fijos.
- **Estilo:** PSR-12. Sintaxis de arreglo corto `[]`.

## 3. VUE 3 — SOLO COMPOSITION API
- Vue 3 con `<script setup>`. NUNCA Options API.
- `ref()` para primitivos, `reactive()` para objetos/arreglos, `computed()` para valores derivados.
- Estado global: únicamente Pinia.
- Ciclo de vida: `onMounted`, `onUnmounted`, etc.

## 4. ACCESO CONTROLADO A GIT
- **Permitidos:** `git add`, `git commit`, `git push`, `git status`, `git log`, `git diff`.
- **PROHIBIDOS:** `git merge`, `git rebase`, `git reset`, `git restore`, `git checkout`, `git stash drop`, `git clean`, `git branch -d/-D`, `git push --force`.
- **Commit:** Solo cuando el mensaje del usuario contenga explícitamente la palabra "git": ejecutar `git add` seguido de `git commit`. Sin esa palabra, NO commitear.
- **Formato:** `tipo(ámbito): descripción en español` (ej. `feat(nomina): agregar lógica de detección de quincena`).
- **Ramas:** Nunca crear ni borrar ramas. Nunca force push.
- **Pull Requests:** Solo cuando el usuario lo solicite explícitamente.

## 5. CONTROL DE COMPILACIÓN Y ASSETS
- Prohibido ejecutar `npm run build`, `npm run dev`, `npm install`, `pnpm build`, `pnpm dev`, `pnpm install` (y equivalentes con yarn/composer install de frontend) salvo solicitud explícita y directa del usuario en su mensaje.

## 6. REESCRITURA INTERNA DE PROMPTS Y EJECUCIÓN DIRECTA

### 6.1 Reescritura (interna, sin mostrarla)
Toda solicitud ambigua, incompleta o poco estructurada se reescribe INTERNAMENTE antes de ejecutar, usando esta plantilla:

| Campo | Contenido |
|---|---|
| **Acción** | Crear / Modificar / Corregir / Refactorizar / Revisar |
| **Objetivo** | Resultado funcional concreto y verificable |
| **Archivos/componentes** | Controlador, Servicio, Repositorio, Request, Resource, `.vue`, migración |
| **Entrada → Salida** | Datos que recibe y qué retorna/renderiza |
| **Alcance de datos** | Tabla central, tabla de tenant o ambas (aplica Sección 8) |
| **Restricciones** | Secciones de estas normas aplicables (2, 3, 7, 8) |
| **Criterio de aceptación** | Cómo se comprueba que quedó bien |

- La versión optimizada NO se imprime en la respuesta salvo que el usuario la pida.
- Se ejecuta de inmediato, sin esperar confirmación.

### 6.2 Completar contexto sin preguntar
- Los datos faltantes (archivo, modelo, ruta, campos, patrón existente) se infieren explorando el código (Glob/Grep/Read) y replicando las convenciones ya presentes en el proyecto.
- Prohibido rechazar o devolver una solicitud por falta de detalle si el contexto puede deducirse del repositorio.

### 6.3 Cuándo SÍ preguntar (única excepción)
Preguntar de forma breve (máximo 1–3 preguntas cerradas) solo si:
- Existen dos o más interpretaciones con resultados materialmente distintos y el código no permite decidir.
- La acción es destructiva o irreversible (borrado de datos, migración que elimina columnas).

### 6.4 Supuestos
Si se tomó una decisión por inferencia que afecte el resultado, declararla en UNA línea al final: `Supuesto: ...`.

## 7. ESTÁNDARES DE CALIDAD OBLIGATORIOS EN VISTAS LARAVEL + VUE
Requisitos implícitos de toda entrega (`.vue`, controlador, servicio o recurso). El agente los cumple y verifica sin que el usuario los solicite.

### 7.1 Diseño y UI
- **Vuetify 3 exclusivamente** (`v-btn`, `v-card`, `v-data-table`, `v-dialog`, `v-text-field`, etc.). Prohibido Tailwind CSS y estilos en línea arbitrarios.
- **Responsive:** grid de Vuetify (`v-row`, `v-col` con `cols`, `sm`, `md`, `lg`).
- **Jerarquía visual:** un único encabezado principal por vista; acción primaria `color="primary"`; secundarias `variant="outlined"` o `variant="text"`.
- **Tema:** colores, tipografías y espaciados desde `plugins/vuetify.js`. Prohibido hexadecimales a mano fuera del tema.

### 7.2 UX y flujo de interacción
- **Carga:** toda petición asíncrona muestra indicador con componentes Vuetify (`v-skeleton-loader`, `v-progress-circular`, prop `:loading`). Nunca UI sin respuesta.
- **Estados vacíos:** listas/tablas posiblemente vacías incluyen "empty state" con mensaje descriptivo y, si aplica, acción para crear el primer registro.
- **Errores:** validación campo por campo (`form.errors` de Inertia o equivalente en el store). Errores 422/500 capturados con toast visible.
- **Envío:** botón submit con `disabled` y `:loading` mientras `form.processing` (o estado interno) sea `true`.
- **Toasts:** usar el sistema de notificaciones del proyecto para confirmar crear/editar/eliminar, con mensajes concisos en español.

### 7.3 Rendimiento y comunicación Laravel → Vue
- **Sin N+1:** `with()` obligatorio al retornar colecciones con relaciones. Prohibido acceder a relaciones en bucles sin carga previa.
- **Selects explícitos:** `select([...])` en consultas Eloquent, especialmente en listados paginados.
- **API Resources:** toda respuesta hacia el frontend (Inertia o API) pasa por `JsonResource`/`ResourceCollection`. Prohibido exponer modelos crudos (`->toArray()`, `->all()`).
- **Paginación:** prohibido `->get()` sin límite en listados; usar `->paginate(n)` o `->cursorPaginate(n)`.
- **Consultas pesadas:** prohibidas subconsultas correlacionadas por fila (`EXISTS`, `TIMESTAMPDIFF`, etc.) en consultas o `UNION` globales salvo estricta necesidad para el resultado paginado. Datos secundarios/trazabilidad: calcular bajo demanda o con campos computados eficientes.
- **Props mínimas:** Inertia solo pasa a Vue los campos que la vista necesita.

### 7.4 Calidad de código Vue
- `<script setup>` exclusivo; prohibido Options API.
- **Reactividad semántica:** `ref()` primitivos, `reactive()` formularios/estado compuesto, `computed()` derivados. Nunca calcular derivados en el `<template>`.
- **Sin lógica en el template:** extraer a `computed` o funciones del `<script setup>`.
- **Componentes reutilizables:** bloque de UI repetido en más de un lugar (inputs, modales, tablas, badges) se extrae a `resources/js/Components/`.
- **Ciclo de vida limpio:** inicializar en `onMounted`; listeners/intervalos registrados se limpian en `onUnmounted`.
- **Props tipados:** `defineProps` siempre tipado (TypeScript si el componente usa `lang="ts"`; en caso contrario, validación de tipo explícita). Nunca props sin tipo.

## 8. MIGRACIONES EN MULTI-TENANCY
- **Sincronización obligatoria:** toda migración que cree o modifique tablas de inquilinos (invoices, products, settings, roles, retentions, purchase_books, sales_books, etc.) se crea en AMBOS directorios con contenido idéntico:
  1. `database/migrations/` (base central/global)
  2. `database/migrations/tenant/` (bases por tenant, ejecutadas con `tenants:migrate`)
- **Prohibido:** migración de tablas de tenant solo en `database/migrations/`. Previene "Unknown column" / "Table doesn't exist" en producción (ej. ensalud.tovaerp.com).
- **Verificación:** antes de finalizar, confirmar que ambos archivos existen y son idénticos.

## 9. PROTOCOLO DE EJECUCIÓN Y VERIFICACIÓN
1. **Leer antes de editar:** revisar el archivo objetivo y al menos un archivo análogo del proyecto para replicar convenciones.
2. **Alcance mínimo:** modificar solo lo necesario para el objetivo. Sin refactors, renombrados ni mejoras no solicitadas.
3. **Reutilizar:** usar Servicios, Repositorios, Requests, Resources y Componentes existentes antes de crear nuevos.
4. **Checklist previo a finalizar** (aplicar solo lo pertinente):
   - Lógica en Servicio, no en Controlador (S2).
   - Form Request y API Resource presentes (S2, S7.3).
   - Sin N+1, `select()` y paginación (S7.3).
   - Vue con `<script setup>`, Vuetify, estados de carga/vacío/error (S7).
   - Migración replicada en `tenant/` (S8).
   - Sin comandos prohibidos de compilación ni git (S4, S5).
5. **Verificación honesta:** si algo no se pudo comprobar (no se ejecutan builds ni se prueba en navegador), indicarlo en una línea; nunca afirmar que "funciona" sin evidencia.
6. **Cierre:** resumen según Sección 1, más `Supuesto:` si aplica (S6.4).
