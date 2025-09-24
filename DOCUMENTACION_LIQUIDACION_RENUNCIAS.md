# 📋 Documentación para Liquidación de Renuncias

## 🎯 Propósito
Este documento describe cómo acceder y utilizar la información de empleados con renuncias registradas en el sistema ERP de Farmacias, específicamente para el proceso de liquidación de prestaciones sociales.

## 🔥 **INFORMACIÓN CRÍTICA PARA LIQUIDACIÓN**

### **Campo Más Importante: `employee_id`**
- ✅ **SÍ, el JSON contiene el `employee_id`** que es exactamente el ID del empleado en la base de datos
- ✅ Este es el campo **MÁS IMPORTANTE** para el personal de liquidación
- ✅ Se puede extraer fácilmente para identificar empleados a liquidar
- ✅ Ejemplo: `"employee_id": 2` = Empleado con ID 2 en la base de datos

### **Uso Principal del Personal de Liquidación:**
1. **Extraer solo los IDs**: `[2, 5, 8, 12]` - Lista de empleados a liquidar
2. **Verificar estado**: Solo empleados con `employee_status: "Inactivo"`
3. **Información adicional**: Fechas, nombres, cédulas (opcional pero útil)

---

## 📁 Ubicación del Archivo de Datos

### Archivo Principal
```
Ruta: storage/app/resignations.json
Formato: JSON (JavaScript Object Notation)
Codificación: UTF-8
```

### Acceso al Archivo
- **Servidor Local**: `C:\ruta\del\proyecto\storage\app\resignations.json`
- **Servidor de Producción**: `/var/www/html/storage/app/resignations.json`
- **Acceso Programático**: A través de la API REST del sistema

---

## 🔧 Estructura de Datos

### Formato del Archivo JSON
El archivo contiene un array de objetos, donde cada objeto representa una renuncia registrada:

```json
[
    {
        "id": 1,
        "employee_id": 2,
        "employee_name": "María González",
        "employee_identification": "87654321",
        "employee_email": "maria.gonzalez@farmacia.com",
        "employee_status": "Activo",
        "employee_position": "empleado",
        "start_date": "2025-09-23",
        "resignation_type": "voluntary",
        "request_date": "2025-09-23",
        "effective_date": "2025-09-30",
        "created_at": "2025-09-23T16:00:00Z",
        "updated_at": "2025-09-23T16:00:00Z"
    }
]
```

### Descripción de Campos

| Campo | Tipo | Descripción | Ejemplo | **Importancia** |
|-------|------|-------------|---------|-----------------|
| `id` | Integer | Identificador único de la renuncia | `1` | Secundario |
| **`employee_id`** | **Integer** | **ID del empleado en la base de datos** | **`2`** | **🔥 CRÍTICO** |
| `employee_name` | String | Nombre completo del empleado | `"María González"` | Útil |
| `employee_identification` | String | Cédula de identidad | `"87654321"` | Útil |
| `employee_email` | String | Correo electrónico del empleado | `"maria.gonzalez@farmacia.com"` | Útil |
| `employee_status` | String | Estado actual del empleado | `"Activo"` o `"Inactivo"` | **🔥 CRÍTICO** |
| `employee_position` | String | Cargo del empleado | `"empleado"`, `"vendedora"`, `"cajero"` | Útil |
| `start_date` | String | Fecha de inicio de labores (YYYY-MM-DD) | `"2025-09-23"` | **🔥 CRÍTICO** |
| `resignation_type` | String | Tipo de renuncia | `"voluntary"` | Secundario |
| `request_date` | String | Fecha de solicitud de renuncia (YYYY-MM-DD) | `"2025-09-23"` | Secundario |
| `effective_date` | String | Fecha efectiva de renuncia (YYYY-MM-DD) | `"2025-09-30"` | **🔥 CRÍTICO** |
| `created_at` | String | Fecha de creación del registro (ISO 8601) | `"2025-09-23T16:00:00Z"` | Secundario |
| `updated_at` | String | Fecha de última actualización (ISO 8601) | `"2025-09-23T16:00:00Z"` | Secundario |

### 🎯 **Campos Críticos para Liquidación**
- **`employee_id`**: ID único del empleado en la base de datos (MÁS IMPORTANTE)
- **`employee_status`**: Debe ser "Inactivo" para empleados a liquidar
- **`start_date`**: Para calcular tiempo de servicio
- **`effective_date`**: Fecha de salida del empleado

---

## 🚀 Métodos de Acceso

### 1. Acceso Directo al Archivo
```bash
# Linux/Mac
cat /ruta/del/proyecto/storage/app/resignations.json

# Windows
type C:\ruta\del\proyecto\storage\app\resignations.json
```

### 2. Acceso a través de API REST

#### Obtener Todas las Renuncias
```http
GET /api/rrhh/resignations
Content-Type: application/json
Authorization: Bearer {token}
```

**Respuesta:**
```json
{
    "data": [
        {
            "id": 1,
            "employee_id": 2,
            "employee_name": "María González",
            "employee_identification": "87654321",
            "employee_email": "maria.gonzalez@farmacia.com",
            "employee_status": "Activo",
            "employee_position": "empleado",
            "start_date": "2025-09-23",
            "resignation_type": "voluntary",
            "request_date": "2025-09-23",
            "effective_date": "2025-09-30",
            "created_at": "2025-09-23T16:00:00Z",
            "updated_at": "2025-09-23T16:00:00Z"
        }
    ],
    "pagination": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1
    }
}
```

#### Obtener Estadísticas
```http
GET /api/rrhh/resignations/stats
Content-Type: application/json
Authorization: Bearer {token}
```

**Respuesta:**
```json
{
    "total_resignations": 1,
    "active_employees": 0,
    "inactive_employees": 1,
    "this_month": 1,
    "this_year": 1
}
```

### 3. Acceso Programático (PHP)

#### **Ejemplo Simple: Solo IDs de Empleados a Liquidar**
```php
<?php
// Leer el archivo JSON
$jsonData = file_get_contents(storage_path('app/resignations.json'));
$resignations = json_decode($jsonData, true);

// Extraer SOLO los IDs de empleados inactivos (para liquidación)
$employeeIds = [];
foreach ($resignations as $resignation) {
    if ($resignation['employee_status'] === 'Inactivo') {
        $employeeIds[] = $resignation['employee_id'];
    }
}

// Resultado: [2, 5, 8, 12] - IDs de empleados a liquidar
echo "IDs de empleados a liquidar: " . implode(', ', $employeeIds);
?>
```

#### **Ejemplo Completo: Con Información Adicional**
```php
<?php
// Leer el archivo JSON
$jsonData = file_get_contents(storage_path('app/resignations.json'));
$resignations = json_decode($jsonData, true);

// Procesar los datos con información completa
foreach ($resignations as $resignation) {
    if ($resignation['employee_status'] === 'Inactivo') {
        echo "ID: " . $resignation['employee_id'] . "\n";
        echo "Empleado: " . $resignation['employee_name'] . "\n";
        echo "Cédula: " . $resignation['employee_identification'] . "\n";
        echo "Fecha efectiva: " . $resignation['effective_date'] . "\n";
        echo "---\n";
    }
}
?>
```

---

## 💼 Casos de Uso para Liquidación

### 1. **Extraer Solo los IDs de Empleados (MÁS IMPORTANTE)**
```php
// Obtener solo los IDs de empleados inactivos para liquidación
$employeeIds = [];
foreach ($resignations as $resignation) {
    if ($resignation['employee_status'] === 'Inactivo') {
        $employeeIds[] = $resignation['employee_id'];
    }
}

// Resultado: [2, 5, 8, 12] - IDs de empleados a liquidar
print_r($employeeIds);
```

### 2. **Generar Lista Completa de Empleados a Liquidar**
```php
// Filtrar empleados inactivos (renuncias efectivas)
$employeesToLiquidate = array_filter($resignations, function($resignation) {
    return $resignation['employee_status'] === 'Inactivo';
});
```

### 3. **Extraer IDs con Información Adicional**
```php
// Obtener IDs con datos básicos para liquidación
$liquidationData = [];
foreach ($resignations as $resignation) {
    if ($resignation['employee_status'] === 'Inactivo') {
        $liquidationData[] = [
            'employee_id' => $resignation['employee_id'],        // ID de la BD
            'name' => $resignation['employee_name'],             // Nombre
            'identification' => $resignation['employee_identification'], // Cédula
            'start_date' => $resignation['start_date'],          // Fecha inicio
            'effective_date' => $resignation['effective_date']   // Fecha salida
        ];
    }
}
```

### 2. Calcular Tiempo de Servicio
```php
function calculateServiceTime($startDate, $effectiveDate) {
    $start = new DateTime($startDate);
    $end = new DateTime($effectiveDate);
    $interval = $start->diff($end);
    
    return [
        'years' => $interval->y,
        'months' => $interval->m,
        'days' => $interval->d,
        'total_days' => $interval->days
    ];
}
```

### 3. Exportar Datos para Liquidación
```php
function exportForLiquidation($resignations) {
    $exportData = [];
    
    foreach ($resignations as $resignation) {
        if ($resignation['employee_status'] === 'Inactivo') {
            $exportData[] = [
                'nombre' => $resignation['employee_name'],
                'cedula' => $resignation['employee_identification'],
                'email' => $resignation['employee_email'],
                'cargo' => $resignation['employee_position'],
                'fecha_inicio' => $resignation['start_date'],
                'fecha_renuncia' => $resignation['effective_date'],
                'tipo_renuncia' => $resignation['resignation_type']
            ];
        }
    }
    
    return $exportData;
}
```

---

## ⚠️ Consideraciones Importantes

### 1. Estados de Empleados
- **"Activo"**: El empleado aún está en la empresa (renuncia no efectiva)
- **"Inactivo"**: El empleado ya no está en la empresa (renuncia efectiva)

### 2. Fechas
- Todas las fechas están en formato ISO 8601 (YYYY-MM-DD)
- `effective_date` es la fecha real de salida del empleado
- `request_date` es cuando se solicitó la renuncia

### 3. Validaciones Recomendadas
```php
// Verificar que el archivo existe
if (!file_exists(storage_path('app/resignations.json'))) {
    throw new Exception('Archivo de renuncias no encontrado');
}

// Validar formato JSON
$resignations = json_decode($jsonData, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    throw new Exception('Error al decodificar JSON: ' . json_last_error_msg());
}
```

### 4. Seguridad
- El archivo contiene información sensible de empleados
- Acceso restringido solo al personal autorizado
- No compartir datos sin autorización

---

## 🔄 Actualizaciones del Archivo

### Frecuencia de Actualización
- **Tiempo Real**: Cada vez que se genera una nueva renuncia
- **Sincronización**: Los cambios se reflejan inmediatamente

### Campos que Pueden Cambiar
- `employee_status`: Cuando se cambia el estado del empleado
- `updated_at`: Se actualiza con cada modificación

---

## 📞 Soporte Técnico

### Contacto
- **Desarrollador**: Equipo de Desarrollo ERP
- **Email**: desarrollo@farmacia.com
- **Teléfono**: +58-XXX-XXX-XXXX

### Problemas Comunes
1. **Archivo no encontrado**: Verificar ruta y permisos
2. **JSON inválido**: Verificar formato del archivo
3. **Datos faltantes**: Contactar al administrador del sistema

---

## 📝 Ejemplo de Implementación Completa

```php
<?php
class ResignationLiquidationService 
{
    private $resignationsFile;
    
    public function __construct() 
    {
        $this->resignationsFile = storage_path('app/resignations.json');
    }
    
    public function getEmployeesToLiquidate() 
    {
        $resignations = $this->loadResignations();
        
        return array_filter($resignations, function($resignation) {
            return $resignation['employee_status'] === 'Inactivo';
        });
    }
    
    public function generateLiquidationReport() 
    {
        $employees = $this->getEmployeesToLiquidate();
        $report = [];
        
        foreach ($employees as $employee) {
            $serviceTime = $this->calculateServiceTime(
                $employee['start_date'], 
                $employee['effective_date']
            );
            
            $report[] = [
                'employee_data' => $employee,
                'service_time' => $serviceTime,
                'liquidation_date' => date('Y-m-d')
            ];
        }
        
        return $report;
    }
    
    private function loadResignations() 
    {
        if (!file_exists($this->resignationsFile)) {
            return [];
        }
        
        $jsonData = file_get_contents($this->resignationsFile);
        return json_decode($jsonData, true) ?: [];
    }
    
    private function calculateServiceTime($startDate, $effectiveDate) 
    {
        $start = new DateTime($startDate);
        $end = new DateTime($effectiveDate);
        $interval = $start->diff($end);
        
        return [
            'years' => $interval->y,
            'months' => $interval->m,
            'days' => $interval->d,
            'total_days' => $interval->days
        ];
    }
}
?>
```

---

**📅 Última actualización**: 23 de Septiembre de 2025  
**📋 Versión**: 1.0  
**👥 Autor**: Equipo de Desarrollo ERP Farmacias
