# Prompt Completo para Análisis del Módulo "Por Pagar" - ERP Farmacias

## 🎯 Contexto General del Proyecto

Estás trabajando con un **ERP de Farmacias** desarrollado en **Laravel 12** (backend) y **Vue.js 3 + Vuetify 3.8.5** (frontend). El sistema maneja múltiples módulos incluyendo inventario, facturación, proveedores, finanzas, y un nuevo módulo "Por Pagar" que permite gestionar facturas pendientes de pago.

**Arquitectura del Sistema:**

- **Backend:** Laravel con patrón Repository + Service Layer
- **Frontend:** Vue.js 3 con Composition API + Vuetify para UI
- **Base de Datos:** MySQL con tablas existentes (sin modificaciones de estructura)
- **Autenticación:** Laravel Sanctum con autenticación por sesión
- **Monedas:** Sistema multi-moneda (VES, USD, COP) con tasas de cambio dinámicas

## 📋 Contexto del Módulo "Por Pagar"

### Objetivo del Módulo

El módulo "Por Pagar" permite visualizar facturas pendientes de pago agrupadas por proveedor y fecha, procesar pagos en múltiples monedas con conversión automática a USD, y mantener un historial completo de pagos realizados.

### Funcionalidades Implementadas

- ✅ Dashboard con estadísticas de pagos pendientes
- ✅ Tabla de facturas agrupadas por proveedor y fecha
- ✅ Filtros avanzados (proveedor, fechas, estado de vencimiento)
- ✅ Modal de procesamiento de pagos con conversión de monedas
- ✅ Upload de comprobantes de pago
- ✅ Historial de pagos con filtros
- ✅ Integración completa con el módulo de expenses

## 🗄️ Estructura de Base de Datos

### Tablas Principales del Módulo

**1. Tabla `invoices` (Facturas)**

```sql
- id (bigint, primary key)
- supplier_id (bigint, foreign key)
- invoice_number (varchar, unique)
- total_amount (decimal) -- Monto en moneda original
- currency (enum: 'Bs', 'USD', 'COP') -- Moneda de la factura
- payment_date (date) -- Fecha límite de pago
- status (enum: 'ordered', 'to_order', 'unpaid') -- Estado de la factura
- total_usd (decimal) -- Monto convertido a USD
- exchange_rate (decimal) -- Tasa de cambio utilizada
- status_payment (enum: 'paid', 'partial', 'unpaid') -- Estado del pago
- created_at, updated_at
```

**2. Tabla `suppliers` (Proveedores)**

```sql
- id (bigint, primary key)
- name (varchar) -- Nombre del proveedor
- social_reason (varchar) -- Razón social
- created_at, updated_at
```

**3. Tabla `exchange_rates` (Tasas de Cambio)**

```sql
- id (bigint, primary key)
- currency_code (varchar) -- Código de moneda (BS, USD, COP)
- rate (decimal) -- Tasa de cambio respecto al USD
- created_at, updated_at
```

**4. Tabla `invoice_payments` (Pagos de Facturas)**

```sql
- id (bigint, primary key)
- payment_method (varchar) -- Moneda del pago (VES, USD, COP)
- amount (decimal) -- Monto pagado en moneda original
- reference (varchar) -- JSON compacto con datos adicionales
- photo_url (varchar) -- URL del comprobante
- status (enum: 'paid', 'unpaid') -- Estado del pago
- user_id (bigint) -- Usuario que procesó el pago
- created_at, updated_at
```

**5. Tabla `invoice_payment_invoice` (Relación Pivot)**

```sql
- invoice_payment_id (bigint, foreign key)
- invoice_id (bigint, foreign key)
- created_at, updated_at
```

**6. Tabla `expenses` (Gastos)**

```sql
- id (bigint, primary key)
- name (varchar) -- Descripción del gasto
- amount (decimal) -- Monto en moneda original
- amount_usd (decimal) -- Monto convertido a USD
- currency (varchar) -- Moneda del gasto
- user_id (bigint) -- Usuario que creó el gasto
- created_at, updated_at
```

## 🏗️ Arquitectura del Módulo

### Backend (Laravel)

**1. Controlador Principal:**

- **Archivo:** `app/Http/Controllers/Api/PendingPaymentsController.php`
- **Funciones principales:**
  - `index()` - Listar pagos pendientes agrupados
  - `statistics()` - Obtener estadísticas del dashboard
  - `processPayment()` - Procesar pagos con conversión de monedas
  - `uploadReceipt()` - Subir comprobantes de pago
  - `paymentHistory()` - Historial de pagos con filtros

**2. Servicio de Lógica de Negocio:**

- **Archivo:** `app/Services/PendingPayments/PendingPaymentsService.php`
- **Responsabilidades:**
  - Agrupación de facturas por proveedor y fecha
  - Conversión de monedas usando tasas del sistema
  - Cálculo de totales en USD
  - Validación de datos de pago

**3. Rutas API:**

- **Archivo:** `routes/api.php`
- **Endpoints principales:**
  - `GET /api/finances/pending-payments` - Listar pagos pendientes
  - `GET /api/finances/pending-payments/statistics` - Estadísticas
  - `POST /api/finances/pending-payments/process-payment` - Procesar pago
  - `POST /api/finances/pending-payments/upload-receipt` - Subir comprobante
  - `GET /api/finances/pending-payments/history` - Historial de pagos
  - `GET /api/public/exchange-rates` - Tasas de cambio públicas

### Frontend (Vue.js)

**1. Vista Principal:**

- **Archivo:** `resources/js/pages/finances/pending-payments.vue`
- **Funcionalidades:**
  - Dashboard con estadísticas
  - Tabla de pagos pendientes
  - Filtros avanzados
  - Modales de visualización y procesamiento

**2. Modal de Procesamiento de Pagos:**

- **Archivo:** `resources/js/components/dialogs/ProcessPaymentModal.vue`
- **Funcionalidades:**
  - Selección de moneda de pago
  - Conversión automática a USD
  - Upload de comprobantes
  - Validaciones en tiempo real

**3. Componente de Filtros:**

- **Archivo:** `resources/js/components/PendingPaymentsFilters.vue`
- **Funcionalidades:**
  - Búsqueda por proveedor
  - Filtros de fecha
  - Filtro de vencidos
  - Limpieza de filtros

**4. Historial de Pagos:**

- **Archivo:** `resources/js/pages/finances/payment-history.vue`
- **Funcionalidades:**
  - Lista de pagos realizados
  - Filtros avanzados
  - Visualización de comprobantes

## 🔄 Flujo de Datos

### 1. Carga de Pagos Pendientes

```
Frontend → GET /api/finances/pending-payments → Backend
Backend → Consulta BD (invoices + suppliers + exchange_rates)
Backend → Agrupa por proveedor y fecha
Backend → Convierte montos a USD
Backend → Retorna JSON con datos agrupados
Frontend → Renderiza tabla y estadísticas
```

### 2. Procesamiento de Pagos

```
Frontend → POST /api/finances/pending-payments/process-payment → Backend
Backend → Valida datos del pago
Backend → Convierte monto a USD usando tasas actuales
Backend → Inicia transacción de BD
Backend → Crea registro en invoice_payments
Backend → Actualiza estado de facturas
Backend → Crea registro en expenses
Backend → Confirma transacción
Backend → Retorna confirmación
Frontend → Actualiza vista y cierra modal
```

### 3. Conversión de Monedas

```
Frontend → GET /api/public/exchange-rates → Backend
Backend → Consulta tabla exchange_rates
Backend → Retorna tasas actuales
Frontend → Calcula montos sugeridos
Frontend → Muestra conversiones en tiempo real
```

## 📊 Estrategia de Almacenamiento

### Sin Modificaciones de BD

El módulo utiliza una estrategia inteligente para almacenar información adicional sin modificar la estructura de la base de datos:

**Campo `reference` en `invoice_payments`:**

```json
{
  "a_usd": 150.00,           // amount_usd
  "rate": 36.50,             // exchange_rate
  "total": 1000.00,          // total_invoice_amount
  "notes": "Pago parcial"    // notas del pago
}
```

**Mapeo de Estados:**

- `status_payment`: 'paid' → 'ordered', 'partial' → 'to_order'
- `currency`: Normalización de códigos (BS/Bs → VES)

## 🚨 Problema Actual

### Síntomas Observados

1. **Modal de pago no muestra montos sugeridos:** Los campos "Monto sugerido en Bs", "Monto ingresado en Bs" y "Equivalente en USD" muestran "N/A"
2. **Funciones computadas no se ejecutan:** Los logs de debug no aparecen en la consola
3. **Conversión de monedas falla:** Al intentar pagar una factura USD con Bolívares, no se calculan los montos correctos

### Contexto del Problema

- El usuario intenta pagar una factura de **USD $150.00** usando **Bolívares (VES)** como moneda de pago
- El sistema debería mostrar un monto sugerido en Bolívares basado en la tasa de cambio actual
- Las funciones computadas de Vue.js no se están ejecutando, indicando un problema de reactividad

### Archivos Afectados

- **Principal:** `resources/js/components/dialogs/ProcessPaymentModal.vue`
- **Relacionados:** `resources/js/pages/finances/pending-payments.vue`
- **Backend:** `app/Http/Controllers/Api/PendingPaymentsController.php`

## 🔍 Hipótesis Inicial

### Problema de Reactividad en Vue.js

**Hipótesis:** Las funciones computadas (`suggestedAmountInLocalCurrency`, `amountInUSD`, `totalInUSD`) no se están ejecutando debido a:

1. **Datos no reactivos:** Las props o refs no están siendo observadas correctamente
2. **Inicialización incorrecta:** El modal se abre antes de que los datos estén disponibles
3. **Problema de dependencias:** Las funciones computadas dependen de datos que no se están actualizando
4. **Error en watchers:** Los watchers no están disparando las funciones computadas

### Posibles Causas Técnicas

- **Props no definidas correctamente:** El modal recibe datos incorrectos o undefined
- **Refs no inicializadas:** Las variables reactivas no se están inicializando
- **Problema de timing:** Las tasas de cambio no se cargan antes de los cálculos
- **Error en la estructura de datos:** Los datos del backend no coinciden con lo esperado por el frontend

## 📚 Documentación de Referencia

### Documentos Incluidos

1. **`DOCUMENTACION_CAMBIOS_PENDING_PAYMENTS.html`** - Documenta todos los cambios técnicos realizados, problemas resueltos y soluciones implementadas
2. **`documentacion_proyecto.html`** - Documentación completa del módulo, arquitectura, flujos de datos y guía de usuario

### Instrucciones para Análisis

1. **Revisar la documentación completa** para entender el contexto histórico
2. **Analizar la estructura de la base de datos** y verificar los datos actuales
3. **Examinar el flujo de datos** desde el backend hasta el frontend
4. **Verificar la reactividad de Vue.js** en el modal de procesamiento
5. **Probar las funciones computadas** con datos de prueba
6. **Identificar la causa raíz** del problema de "N/A"

## 🎯 Instrucciones Específicas para el Análisis

### 1. Análisis de Base de Datos

```sql
-- Verificar facturas pendientes
SELECT * FROM invoices WHERE status_payment = 'unpaid';

-- Verificar tasas de cambio
SELECT * FROM exchange_rates ORDER BY created_at DESC;

-- Verificar proveedores
SELECT * FROM suppliers LIMIT 10;

-- Verificar pagos existentes
SELECT * FROM invoice_payments ORDER BY created_at DESC LIMIT 5;
```

### 2. Análisis del Backend

- **Verificar endpoints API** con Postman o curl
- **Revisar logs de Laravel** para errores
- **Validar estructura de respuesta** de los endpoints
- **Verificar autenticación** y middleware

### 3. Análisis del Frontend

- **Abrir DevTools** y revisar la consola
- **Verificar Network tab** para peticiones API
- **Examinar Vue DevTools** para reactividad
- **Probar funciones computadas** con datos de prueba

### 4. Análisis de Reactividad

- **Verificar props** del modal
- **Revisar refs y computed** properties
- **Examinar watchers** y su funcionamiento
- **Probar con datos estáticos** para aislar el problema

## 🚀 Próximos Pasos

### Después del Análisis

1. **Generar una nueva hipótesis** basada en los hallazgos
2. **Proponer una solución específica** para el problema identificado
3. **Implementar la corrección** paso a paso
4. **Verificar que no se afecten** otros módulos del sistema

### Criterios de Éxito

- ✅ Modal muestra montos sugeridos correctamente
- ✅ Conversión de monedas funciona en todas las combinaciones
- ✅ Funciones computadas se ejecutan y muestran logs
- ✅ No se afectan otras funcionalidades del sistema

---

**Nota:** Este prompt contiene todo el contexto necesario para entender el módulo completo y el problema específico. Utiliza la documentación adjunta como referencia y sigue las instrucciones de análisis para identificar la causa raíz del problema de reactividad en el modal de procesamiento de pagos.
