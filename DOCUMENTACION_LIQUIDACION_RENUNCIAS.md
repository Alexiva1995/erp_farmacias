# 📋 Documentación para Equipo de Liquidación - Sistema de Renuncias

## 🎯 **Propósito**
Esta documentación está dirigida al equipo de liquidación para explicar cómo acceder y consumir la información de renuncias de empleados desde el sistema ERP.

---

## 🗄️ **Fuente de Datos**

### **Base de Datos: Tabla `resignations`**
Todas las renuncias se almacenan en la tabla `resignations` de la base de datos MySQL/MariaDB.

### **Estructura de la Tabla**
```sql
CREATE TABLE resignations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    employee_name VARCHAR(255) NOT NULL,
    employee_identification VARCHAR(255) NOT NULL,
    employee_email VARCHAR(255) NULL,
    employee_position VARCHAR(255) NULL,
    start_date DATE NOT NULL,
    resignation_type ENUM('voluntary', 'unjustified_dismissal') NOT NULL,
    request_date DATE NOT NULL,
    effective_date DATE NOT NULL,
    employee_status VARCHAR(255) DEFAULT 'Activo',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    UNIQUE KEY unique_employee_id (employee_id),
    INDEX idx_employee_type (employee_id, resignation_type),
    INDEX idx_effective_date (effective_date),
    INDEX idx_created_at (created_at)
);
```

---

## 🔑 **Campo Clave: `employee_id`**

**⚠️ IMPORTANTE**: El campo `employee_id` es la referencia principal para identificar al empleado en el sistema de liquidación.

### **Relación con otras tablas:**
- `resignations.employee_id` → `employees.id`
- `employees.user_id` → `users.id` (para datos de usuario)

---

## 📊 **Datos Disponibles para Liquidación**

### **Información del Empleado:**
| Campo | Descripción | Ejemplo |
|-------|-------------|---------|
| `employee_id` | **ID único del empleado** | `3` |
| `employee_name` | Nombre completo | `"Carlos Rodríguez"` |
| `employee_identification` | Cédula/ID | `"11223344"` |
| `employee_email` | Email del empleado | `"carlos.rodriguez@farmacia.com"` |
| `employee_position` | Cargo (opcional) | `"Vendedor"` |

### **Información de la Renuncia:**
| Campo | Descripción | Valores Posibles |
|-------|-------------|------------------|
| `resignation_type` | Tipo de renuncia | `"voluntary"` (Justificada)<br>`"unjustified_dismissal"` (Injustificada) |
| `start_date` | Fecha de inicio laboral | `"2024-01-15"` |
| `request_date` | Fecha de solicitud | `"2025-09-24"` |
| `effective_date` | Fecha efectiva de renuncia | `"2025-10-30"` |
| `employee_status` | Estado actual | `"Activo"` o `"Inactivo"` |

### **Metadatos:**
| Campo | Descripción |
|-------|-------------|
| `created_at` | Fecha de registro de la renuncia |
| `updated_at` | Última actualización |

---

## 🔍 **Consultas SQL para Liquidación**

### **1. Obtener todas las renuncias:**
```sql
SELECT 
    employee_id,
    employee_name,
    employee_identification,
    employee_email,
    employee_position,
    start_date,
    resignation_type,
    request_date,
    effective_date,
    employee_status,
    created_at
FROM resignations
ORDER BY effective_date DESC;
```

### **2. Renuncias por período:**
```sql
SELECT *
FROM resignations
WHERE effective_date BETWEEN '2025-01-01' AND '2025-12-31'
ORDER BY effective_date DESC;
```

### **3. Renuncias por tipo:**
```sql
-- Solo renuncias justificadas
SELECT * FROM resignations WHERE resignation_type = 'voluntary';

-- Solo renuncias injustificadas
SELECT * FROM resignations WHERE resignation_type = 'unjustified_dismissal';
```

### **4. Renuncias de un empleado específico:**
```sql
SELECT *
FROM resignations
WHERE employee_id = 3;
```

### **5. Renuncias con datos del empleado:**
```sql
SELECT 
    r.*,
    e.name,
    e.last_name,
    e.identification,
    e.is_active,
    u.email
FROM resignations r
JOIN employees e ON r.employee_id = e.id
LEFT JOIN users u ON e.user_id = u.id
ORDER BY r.effective_date DESC;
```

---

## 📈 **Estadísticas Disponibles**

### **API Endpoint:**
```
GET /api/rrhh/resignations/stats
```

### **Respuesta:**
```json
{
    "success": true,
    "data": {
        "total": 2,
        "voluntary": 2,
        "unjustified_dismissal": 0,
        "this_month": 2,
        "this_year": 2
    }
}
```

---

## 🛠️ **APIs para Consumo Programático**

### **1. Listar renuncias:**
```http
GET /api/rrhh/resignations
```

**Parámetros opcionales:**
- `page`: Número de página (default: 1)
- `perPage`: Elementos por página (default: 10)
- `search`: Búsqueda por nombre, identificación o email
- `resignation_type`: Filtrar por tipo (`voluntary` o `unjustified_dismissal`)
- `date_from`: Fecha desde (YYYY-MM-DD)
- `date_to`: Fecha hasta (YYYY-MM-DD)

**Ejemplo:**
```http
GET /api/rrhh/resignations?page=1&perPage=50&resignation_type=voluntary&date_from=2025-01-01
```

### **2. Obtener renuncia específica:**
```http
GET /api/rrhh/resignations/{id}
```

### **3. Descargar PDF de renuncia:**
```http
GET /api/rrhh/resignations/{id}/download-pdf
```

---

## 📋 **Proceso de Liquidación Recomendado**

### **1. Identificación de Renuncias:**
```sql
-- Renuncias pendientes de liquidación
SELECT 
    employee_id,
    employee_name,
    employee_identification,
    effective_date,
    resignation_type
FROM resignations
WHERE employee_status = 'Activo'
ORDER BY effective_date ASC;
```

### **2. Cálculo de Período Laboral:**
```sql
-- Días trabajados desde inicio hasta renuncia
SELECT 
    employee_id,
    employee_name,
    start_date,
    effective_date,
    DATEDIFF(effective_date, start_date) AS dias_trabajados
FROM resignations;
```

### **3. Verificación de Datos:**
- ✅ **employee_id**: Usar para consultar nóminas y prestaciones
- ✅ **effective_date**: Fecha de corte para liquidación
- ✅ **resignation_type**: Determina tipo de liquidación
- ✅ **employee_status**: Verificar si ya fue procesado

---

## ⚠️ **Consideraciones Importantes**

### **1. Campo `employee_id` es CRÍTICO:**
- Es la única referencia confiable al empleado
- Usar para consultar tablas de nóminas, prestaciones, etc.
- No confiar solo en `employee_identification` (puede cambiar)

### **2. Estados de Renuncias:**
- `"Activo"`: Renuncia pendiente de liquidación
- `"Inactivo"`: Renuncia ya procesada

### **3. Tipos de Renuncia:**
- `"voluntary"`: Renuncia justificada (liquidación completa)
- `"unjustified_dismissal"`: Renuncia injustificada (liquidación parcial)

### **4. Fechas:**
- `effective_date`: Fecha de corte para liquidación
- `start_date`: Para calcular antigüedad
- `request_date`: Fecha de solicitud (referencia)

---

## 🔄 **Flujo de Trabajo Sugerido**

1. **Consultar renuncias pendientes** (`employee_status = 'Activo'`)
2. **Obtener `employee_id`** de cada renuncia
3. **Consultar datos del empleado** usando `employee_id`
4. **Calcular liquidación** basada en `effective_date` y `resignation_type`
5. **Procesar liquidación** según políticas de la empresa
6. **Actualizar estado** a `"Inactivo"` cuando se complete

---

## 📞 **Contacto Técnico**

Para dudas técnicas sobre el sistema de renuncias:
- **Desarrollador**: Sistema ERP
- **Base de datos**: MySQL/MariaDB
- **Tabla principal**: `resignations`
- **Campo clave**: `employee_id`

---

## 📝 **Notas de Versión**

- **v1.0**: Sistema inicial con almacenamiento en JSON
- **v2.0**: Migración a base de datos MySQL
- **v2.1**: Generación dinámica de PDFs
- **v2.2**: APIs para consumo programático

---

*Documentación actualizada: Septiembre 2025*
