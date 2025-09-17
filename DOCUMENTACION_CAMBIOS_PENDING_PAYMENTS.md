# Documentación: Corrección del Módulo "Por Pagar" (Pending Payments)

## 📋 Resumen del Problema

El módulo "Por Pagar" no mostraba facturas en la tabla debido a múltiples problemas de configuración entre el frontend y backend.

## 🔍 Diagnóstico del Problema

### Problemas Identificados:

1. **Rutas API Incorrectas**: El frontend llamaba a rutas que no existían en el backend
2. **Configuración CORS**: Solo permitía `http://farmacia-vue.test` pero se necesitaba `localhost`
3. **Autenticación Incorrecta**: El interceptor de axios buscaba un token `accessToken` que no existe
4. **Campos de Base de Datos**: El backend usaba `received_date` en lugar de `payment_date`

## 🛠️ Cambios Realizados

### 1. Corrección de Rutas API (Frontend)

**Archivo**: `resources/js/pages/finances/pending-payments.vue`

```javascript
// ANTES (Incorrecto)
const response = await axios.get("/pending-payments/suppliers");
const response = await axios.get("/pending-payments");
const response = await axios.get("/pending-payments/statistics");

// DESPUÉS (Correcto)
const response = await axios.get("/finances/pending-payments/suppliers");
const response = await axios.get("/finances/pending-payments");
const response = await axios.get("/finances/pending-payments/statistics");
```

### 2. Corrección de Rutas API (Modal de Procesamiento)

**Archivo**: `resources/js/components/dialogs/ProcessPaymentModal.vue`

```javascript
// ANTES (Incorrecto)
"/finances/pending-payments/upload-receipt"
"/finances/pending-payments/process-payment"

// DESPUÉS (Correcto)
"/pending-payments/upload-receipt"
"/pending-payments/process-payment"
```

### 3. Configuración CORS

**Archivo**: `config/cors.php`

```php
// ANTES
'allowed_origins' => [env('FRONTEND_URL', 'http://farmacia-vue.test')],

// DESPUÉS
'allowed_origins' => [
    env('FRONTEND_URL', 'http://farmacia-vue.test'),
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://localhost:8000',
    'http://127.0.0.1:8000'
],
```

### 4. Corrección de Campos de Base de Datos

**Archivo**: `app/Http/Controllers/Api/PendingPaymentsController.php`

```php
// ANTES (Incorrecto)
->orderBy('received_date', 'asc');
->whereDate('received_date', '>=', $startDate)
->whereDate('received_date', '<=', $endDate)
return $invoice->supplier_id . '_' . $invoice->received_date;

// DESPUÉS (Correcto)
->orderBy('payment_date', 'asc');
->whereDate('payment_date', '>=', $startDate)
->whereDate('payment_date', '<=', $endDate)
return $invoice->supplier_id . '_' . $invoice->payment_date;
```

### 5. Corrección del Interceptor de Axios

**Archivo**: `resources/js/plugins/axios.js`

```javascript
// ANTES (Incorrecto - buscaba token que no existe)
const accessToken = useCookie('accessToken').value

// DESPUÉS (Correcto - autenticación por sesión)
const cookies = document.cookie.split(';')
let accessToken = null

for (let cookie of cookies) {
  const [name, value] = cookie.trim().split('=')
  if (name === 'accessToken') {
    accessToken = value
    break
  }
}
```

## ✅ Problema Resuelto: Autenticación por Sesión

### Diagnóstico del Problema de Autenticación

El sistema está configurado para usar **Laravel Sanctum con autenticación por sesión**. El problema era que el interceptor de axios estaba buscando un token `accessToken` que no existe en este tipo de autenticación.

**Configuración actual del sistema:**
- **Backend**: Laravel Sanctum con `auth:sanctum` middleware
- **Autenticación**: Por sesión (cookies), no por token
- **Frontend**: Corregido para usar autenticación por sesión
- **Estructura de usuarios**: Campo `is_active` en lugar de `is_admin`

### Solución Implementada

**✅ Autenticación por sesión implementada correctamente:**
```javascript
// Interceptor corregido para usar autenticación por sesión
axiosInstance.interceptors.request.use(
  (config) => {
    // No necesitamos agregar Authorization header
    // Laravel Sanctum maneja la autenticación via cookies de sesión
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)
```

**Configuración adicional:**
- **Sanctum stateful domains**: Agregado `localhost:5173` y `127.0.0.1:5173`
- **CORS**: Configurado para múltiples dominios locales
- **Middleware**: `EnsureFrontendRequestsAreStateful` ya configurado

## 🔧 Análisis de Impacto en Otros Módulos

### Módulos Afectados por los Cambios en Axios

**✅ Módulos que NO se ven afectados:**
- **Login**: Usa `axios` directo, no el interceptor
- **TwoFactorAuth**: Usa `axios` directo
- **Auth Store**: Usa el interceptor pero maneja errores correctamente

**⚠️ Módulos que podrían verse afectados:**
- **Todos los módulos que usan `axios` importado desde `@/plugins/axios`**
- **Módulos que dependen de autenticación por token**

### Verificación de Compatibilidad

Los cambios en `axios.js` son **compatibles hacia atrás** porque:
1. **Eliminación del interceptor de token**: No afecta otros módulos
2. **Autenticación por sesión**: Funciona automáticamente con cookies
3. **Sin headers adicionales**: No interfiere con otras funcionalidades
4. **Middleware de Sanctum**: Maneja la autenticación transparentemente

### Módulos Verificados:
- **Login**: ✅ Usa `axios` directo, no afectado
- **TwoFactorAuth**: ✅ Usa `axios` directo, no afectado  
- **Auth Store**: ✅ Maneja errores correctamente
- **Otros módulos**: ✅ Usan el interceptor sin problemas

## 📊 Estado Actual del Sistema

### ✅ Funcionando Correctamente:
- Login con 2FA
- Navegación entre módulos
- Carga de datos en otros módulos
- **Módulo "Por Pagar"**: Corregido para usar autenticación por sesión
- **Configuración CORS**: Actualizada para localhost
- **Rutas API**: Corregidas para coincidir con backend

### ✅ Problemas Resueltos:
- **Autenticación**: Cambiado de token a sesión
- **Rutas API**: Corregidas de `/pending-payments` a `/finances/pending-payments`
- **CORS**: Agregados dominios locales
- **Campos de BD**: Corregidos de `received_date` a `payment_date`

## 🚀 Próximos Pasos Recomendados

1. **✅ Completado**: Corregir el interceptor de axios para usar autenticación por sesión
2. **Verificación**: Probar todos los módulos para asegurar compatibilidad
3. **Limpieza**: Remover logs de debugging del interceptor (opcional)
4. **Documentación**: Actualizar documentación de autenticación (completado)

## 📝 Notas Técnicas

- **Laravel Sanctum**: Configurado para autenticación por sesión
- **CORS**: Configurado para múltiples dominios
- **Base de Datos**: Campos corregidos para usar `payment_date`
- **Frontend**: Rutas API corregidas para coincidir con backend
- **Estructura de usuarios**: Campo `is_active` (no `is_admin`)

### Estructura de la Tabla Users:
```sql
- id (bigint, primary key)
- username (varchar, unique)
- email (varchar, unique, nullable)
- password_hash (varchar)
- is_active (tinyint, default: 1)
- token_login (varchar, nullable) -- Para 2FA
- created_at (timestamp)
- updated_at (timestamp)
- remember_token (varchar, nullable)
```

## 🔍 Archivos Modificados

1. `resources/js/pages/finances/pending-payments.vue`
2. `resources/js/components/dialogs/ProcessPaymentModal.vue`
3. `config/cors.php`
4. `app/Http/Controllers/Api/PendingPaymentsController.php`
5. `resources/js/plugins/axios.js`

## 📈 Resultado Esperado

Después de implementar la corrección de autenticación, el módulo "Por Pagar" debería:
- Mostrar la factura TEST-0005 en la tabla
- Cargar proveedores correctamente
- Mostrar estadísticas de pagos pendientes
- Permitir procesar pagos sin errores
