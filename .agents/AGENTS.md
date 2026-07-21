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
- **Validación:** Usar SIEMPRE Form Requests (`app/Http/Requests/`). NUNCA validar directamente dentro de los Controladores.
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
