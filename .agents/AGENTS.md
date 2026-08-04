# NORMAS ESTRICTAS DE DESARROLLO — ERP FARMACIAS

## 1. IDIOMA Y COMUNICACIÓN
- **Respuestas:** SIEMPRE en ESPAÑOL.
- **Código Fuente:** SIEMPRE en INGLÉS.
- **Comentarios de Código:** SIEMPRE en ESPAÑOL.
- **Sin preámbulos:** Cero saludos, cero despedidas, cero comentarios introductorios o finales irrelevantes. Ir directo al grano.
- **Pensamiento Interno:** Debe ser estrictamente analítico, técnico y directo. Evitar exclamaciones, narraciones coloquiales, suposiciones dramáticas o tono conversacional humano para optimizar el consumo de tokens.
- **Resúmenes concisos:** Los resúmenes finales de trabajo deben ser lo más breves y directos posible (preferiblemente viñetas con archivos modificados y el cambio clave, sin explicaciones redundantes).

## 2. PATRONES DE LARAVEL 12
- **Arquitectura:** Usar siempre Repository Pattern (ej. `app/Repository/`, `app/Contracts/`) y Service Layer (`app/Services/`).
- **Controladores delgados (Thin Controllers):** Solo deben manejar entradas y salidas HTTP. TODA la lógica de negocio debe residir en los Servicios.
- **Validación:** Usar SIEMPRE Form Requests (`app/Http/Requests/`). NUNCA validar directamente dentro de los Controladores. Queda estrictamente prohibido el uso de `$request->validate()` o `Validator::make()` de forma inline/manual dentro de controladores o servicios HTTP.
- **Respuestas API:** Usar SIEMPRE API Resources (`app/Http/Resources/`).
- **Enums:** Usar PHP 8.1+ Enums para campos de estado y valores fijos.
- **Estilo:** Seguir PSR-12. Usar siempre sintaxis de arreglo corto `[]`.

## 3. VUE 3 — SOLO COMPOSITION API
- **Versión:** Usar siempre Vue 3. NUNCA Options API.
- **Sintaxis:** Usar siempre `<script setup>`.
- **Reactividad:** Usar `ref()` para tipos primitivos, `reactive()` para objetos/arreglos, y `computed()` para valores derivados.
- **Estado Global:** Usar únicamente Pinia.
- **Ciclo de Vida:** Usar `onMounted`, `onUnmounted`, etc. NUNCA métodos de Options API.

## 4. ACCESO CONTROLADO A GIT
- **Comandos permitidos:** `git add`, `git commit`, `git push`, `git status`, `git log`, `git diff`.
- **Comandos PROHIBIDOS:** `git merge`, `git rebase`, `git reset`, `git restore`, `git checkout`, `git stash drop`, `git clean`.
- **Ejecución de Commit:** Solo cuando se mencione de forma explícita la palabra "git" en el mensaje del usuario, ejecutar automáticamente `git add` seguido de `git commit`.
- **Formato del Commit:** `tipo(ámbito): descripción en español` (Ejemplo: `feat(nomina): agregar lógica de detección de quincena`).
- **Ramas:** Nunca crear ni borrar ramas. Nunca realizar force push.
- **Pull Requests:** Solo crear Pull Requests cuando el usuario lo solicite explícitamente.

## 5. CONTROL DE COMPILACIÓN Y ASSETS
- **ESTRICTO:** No ejecutar comandos de compilación o instalación de paquetes como `npm run build`, `npm run dev`, `npm install`, `pnpm build`, `pnpm dev` o `pnpm install` bajo ninguna circunstancia, a menos que el usuario lo solicite de manera explícita y directa en su mensaje.

## 6. REESCRITURA DE PROMPTS Y EJECUCIÓN DIRECTA
- **Reescritura del Prompt:** Optimiza la petición del usuario redactándola de forma precisa, clara y estructurada como un parámetro de comportamiento de IA.
- **Ejecución Directa:** Responde inmediatamente a la solicitud del usuario aplicando la versión optimizada que acabas de generar.

## 7. ESTÁNDARES DE CALIDAD OBLIGATORIOS EN VISTAS LARAVEL + VUE

Esta sección define los criterios mínimos que el agente debe cumplir y verificar **proactivamente** cada vez que genere, modifique o revise cualquier vista del sistema (componente `.vue`, controlador, servicio o recurso asociado). No es necesario que el usuario los solicite; son requisitos implícitos de toda entrega de código.

### 7.1 Diseño y UI
- **Framework de UI: Vuetify 3 exclusivamente.** Usar siempre componentes nativos de Vuetify (`v-btn`, `v-card`, `v-data-table`, `v-dialog`, `v-text-field`, etc.). Prohibido mezclar con Tailwind CSS o estilos en línea arbitrarios.
- Toda vista debe ser **responsive**: usar el sistema de grid de Vuetify (`v-row`, `v-col` con props `cols`, `sm`, `md`, `lg`) para que el layout no se rompa en móvil.
- Mantener jerarquía visual clara: un único encabezado principal por vista, acciones primarias con variante `color="primary"`, acciones secundarias con variante `variant="outlined"` o `variant="text"`.
- Los colores, tipografías y espaciados deben respetar el tema de Vuetify definido en `plugins/vuetify.js`. No definir colores hexadecimales a mano fuera del tema.

### 7.2 UX y Flujo de Interacción
- **Estados de carga:** Toda petición asíncrona debe mostrar un indicador visual (skeleton, spinner o `opacity-50 pointer-events-none`). Nunca dejar la UI sin respuesta mientras se espera datos.
- **Estados vacíos:** Si una lista o tabla puede estar vacía, debe existir un componente o bloque de "empty state" con mensaje descriptivo y, cuando aplique, una acción para crear el primer registro.
- **Manejo de errores:** Los errores de validación deben mostrarse campo por campo usando `form.errors` de Inertia o el equivalente en el store. Los errores de servidor (500, 422) deben capturarse y mostrar un toast o mensaje visible al usuario.
- **Feedback de envío:** Los botones de submit deben deshabilitarse (`disabled`) y cambiar su texto o mostrar un spinner mientras `form.processing` (Inertia) o el estado de carga interno sea `true`, para prevenir envíos duplicados.
- **Toasts/Notificaciones:** Usar el sistema de notificaciones del proyecto (toast) para confirmar acciones exitosas (crear, editar, eliminar) con mensajes concisos y en español.

### 7.3 Rendimiento y Comunicación Laravel → Vue
- **Sin N+1:** Todo controlador que retorne colecciones con relaciones DEBE usar `with()` (eager loading). Está prohibido acceder a relaciones dentro de un bucle sin haberlas cargado previamente.
- **Selects explícitos:** Usar `select(['col1', 'col2', ...])` en las consultas Eloquent para evitar traer columnas innecesarias, especialmente en listados con paginación.
- **API Resources:** Toda respuesta que salga hacia el frontend (Inertia o API pura) debe pasar por un `JsonResource` o `ResourceCollection`. Nunca exponer modelos Eloquent crudos (`->toArray()` o `->all()` directamente en el controlador).
- **Paginación:** Las colecciones grandes deben paginarse. No usar `->get()` sin límite en listados; usar `->paginate(n)` o `->cursorPaginate(n)`.
- **Consultas pesadas y Subconsultas:** Prohibido usar subconsultas correlacionadas por fila (`EXISTS`, `TIMESTAMPDIFF`, etc.) en consultas o `UNION` globales si no son estrictamente necesarias para el resultado paginado. Para datos secundarios o trazabilidad, deben ser calculados bajo demanda o mediante campos computados eficientes.
- **Props mínimas:** Los props que Inertia pasa a Vue deben contener solo los datos que la vista necesita. Evitar serializar objetos completos cuando solo se necesitan 2 o 3 campos.

### 7.4 Calidad de Código Vue
- **Composition API exclusiva:** Todo componente usa `<script setup>`. Prohibido Options API.
- **Reactividad semántica:** `ref()` para primitivos, `reactive()` para objetos de formulario o estado compuesto, `computed()` para valores derivados. Nunca calcular valores derivados dentro del `<template>`.
- **Sin lógica en el template:** El `<template>` no debe contener lógica de negocio. Extraer a `computed` o funciones del `<script setup>`.
- **Componentes reutilizables:** Si un bloque de UI se repite en más de un lugar (inputs, modales, tablas, badges de estado), debe extraerse a un componente independiente en `resources/js/Components/`.
- **Ciclo de vida limpio:** Usar `onMounted` para inicializar datos. Si se registran event listeners o intervalos en `onMounted`, deben limpiarse en `onUnmounted`.
- **Manejo de props tipado:** Declarar props con `defineProps<{...}>()` usando TypeScript o con validación de tipo explícita. Nunca recibir props sin tipo.
