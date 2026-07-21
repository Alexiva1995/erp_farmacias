<script setup>
import { ref, onMounted } from 'vue'
import axios from '@axios'
import { toast } from '@/plugins/sweetalert'

const activeTab = ref('clientes')
const selectedFile = ref(null)
const uploading = ref(false)
const progress = ref(0)
const businessType = ref('pharmacy')

const tabs = [
  { title: 'Clientes', value: 'clientes', icon: 'tabler-users', filePattern: 'clientes.csv' },
  { title: 'Proveedores', value: 'proveedores', icon: 'tabler-truck', filePattern: 'proveedores.csv' },
  { title: 'Productos', value: 'productos', icon: 'tabler-package', filePattern: 'productos.csv' },
  { title: 'Inventario / Lotes', value: 'inventariolot', icon: 'tabler-clipboard-list', filePattern: 'inventariolot.csv' },
  { title: 'Gastos', value: 'gastos', icon: 'tabler-receipt-2', filePattern: 'gastos.csv' },
  { title: 'Cierres Diarios', value: 'cierres', icon: 'tabler-report-money', filePattern: 'cierres.csv' },
]

const fileSchemas = {
  clientes: [
    { field: 'identification', required: true, desc: 'Identificación del cliente (cédula o RIF)' },
    { field: 'identification_type', required: true, desc: 'Tipo de ID: V-, J-, G- o E-' },
    { field: 'name', required: true, desc: 'Nombres / Razón social' },
    { field: 'last_name', required: false, desc: 'Apellidos (opcional)' },
    { field: 'email', required: false, desc: 'Correo electrónico' },
    { field: 'phone', required: false, desc: 'Teléfono' },
    { field: 'address', required: false, desc: 'Dirección' },
    { field: 'birthdate', required: false, desc: 'Fecha de nacimiento (YYYY-MM-DD)' },
  ],
  proveedores: [
    { field: 'supplier_name', required: true, desc: 'Nombre comercial del proveedor' },
    { field: 'social_reason', required: false, desc: 'Razón social' },
    { field: 'sales_phone', required: false, desc: 'Teléfono de contacto de ventas' },
    { field: 'collections_phone', required: false, desc: 'Teléfono de cobranzas' },
    { field: 'credit_days', required: false, desc: 'Días de crédito otorgados (numérico)' },
    { field: 'payment_method', required: true, desc: 'Moneda habitual de pago (Bs / Divisas)' },
    { field: 'cash_payment', required: true, desc: '¿Acepta efectivo?: 1 (Sí) / 0 (No)' },
    { field: 'charges_igtf', required: true, desc: '¿Cobra IGTF?: 1 (Sí) / 0 (No)' },
  ],
  productos: [
    { field: 'barcode', required: true, desc: 'Código de barra del producto' },
    { field: 'name', required: true, desc: 'Nombre o descripción comercial' },
    { field: 'active_ingredient', required: false, desc: 'Principio activo' },
    { field: 'category_name', required: true, desc: 'Nombre de la Categoría' },
    { field: 'laboratory_name', required: false, desc: 'Nombre del Laboratorio' },
    { field: 'origin_name', required: false, desc: 'Nombre del Origen (Nacional / Importado)' },
    { field: 'cost_price', required: true, desc: 'Precio de costo (Ej: 1.20)' },
    { field: 'sale_price', required: true, desc: 'Precio de venta al público (Ej: 2.50)' },
    { field: 'iva', required: true, desc: '¿Aplica IVA?: 1 (Sí) / 0 (No)' },
    { field: 'psychotropic', required: true, desc: '¿Es psicotrópico?: 1 (Sí) / 0 (No)' },
    { field: 'from_colombia', required: true, desc: '¿Es traído de Colombia?: 1 (Sí) / 0 (No)' },
  ],
  inventariolot: [
    { field: 'barcode', required: true, desc: 'Código de barra de producto para enlazar catálogo' },
    { field: 'lot_number', required: false, desc: 'Número del lote físico' },
    { field: 'expiration_date', required: true, desc: 'Fecha de vencimiento (YYYY-MM-DD)' },
    { field: 'quantity', required: true, desc: 'Cantidad física en stock' },
    { field: 'cost_price', required: true, desc: 'Precio de costo del lote' },
    { field: 'location', required: false, desc: 'Ubicación física en tienda (Ej: Estante A-2)' },
    { field: 'supplier_name', required: false, desc: 'Nombre comercial del proveedor de origen' },
  ],
  gastos: [
    { field: 'name', required: true, desc: 'Descripción del gasto realizado' },
    { field: 'category_name', required: true, desc: 'Categoría del gasto' },
    { field: 'amount', required: true, desc: 'Monto total en Bolívares (Bs)' },
    { field: 'amount_usd', required: true, desc: 'Monto total en Dólares (USD)' },
    { field: 'currency', required: true, desc: 'Moneda del pago (Bs / USD)' },
    { field: 'expense_date', required: true, desc: 'Fecha del egreso (YYYY-MM-DD)' },
    { field: 'has_invoice', required: true, desc: '¿Posee factura formal?: 1 (Sí) / 0 (No)' },
    { field: 'is_deductible', required: true, desc: '¿Es gasto deducible?: 1 (Sí) / 0 (No)' },
  ],
  cierres: [
    { field: 'date', required: true, desc: 'Fecha del día del cuadre de caja (YYYY-MM-DD)' },
    { field: 'total_usd', required: true, desc: 'Total ventas Dólares (USD)' },
    { field: 'total_cop', required: true, desc: 'Total ventas Pesos (COP)' },
    { field: 'total_bs', required: true, desc: 'Total ventas Bolívares (Bs)' },
    { field: 'bs_card', required: true, desc: 'Monto cobrado con Tarjeta de Débito (Bs)' },
    { field: 'bs_mobile', required: true, desc: 'Monto cobrado con Pago Móvil (Bs)' },
    { field: 'usd_delivered', required: true, desc: 'Monto físico USD entregado a administración' },
    { field: 'cop_delivered', required: true, desc: 'Monto físico COP entregado a administración' },
    { field: 'bs_delivered', required: true, desc: 'Monto físico Bs entregado a administración' },
  ],
}

const handleFileSelect = (e) => {
  const file = e.target.files[0]
  if (file) {
    selectedFile.value = file
  }
}

const fetchSettings = async () => {
  // Configuración cargada al inicio (business_type eliminado)
}

const triggerImport = async () => {
  if (!selectedFile.value) {
    toast.error('Por favor, selecciona un archivo CSV válido.')
    return
  }

  uploading.value = true
  progress.value = 10

  const formData = new FormData()
  formData.append('type', activeTab.value)
  formData.append('file', selectedFile.value)

  try {
    progress.value = 40
    const response = await axios.post('/import-csv', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
    progress.value = 100
    toast.success(response.data.message || 'Importación procesada exitosamente.')
    selectedFile.value = null
    // Reset file input
    const fileInput = document.getElementById('csv-file-input')
    if (fileInput) fileInput.value = ''
  } catch (err) {
    toast.error(err.response?.data?.message || 'Error al procesar el archivo CSV. Verifica la estructura.')
  } finally {
    uploading.value = false
    setTimeout(() => {
      progress.value = 0
    }, 1000)
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard title="Módulo de Importación de Datos (Onboarding)">
        <VCardText>
          Carga de forma masiva la información inicial de tu negocio en formato de valores separados por coma (CSV).
        </VCardText>

        <VTabs v-model="activeTab" color="primary" grow>
          <VTab v-for="tab in tabs" :key="tab.value" :value="tab.value">
            <VIcon start :icon="tab.icon" />
            {{ tab.title }}
          </VTab>
        </VTabs>

        <VCardText class="mt-4">
          <div class="mb-4">
            <h3 class="text-subtitle-1 font-weight-bold mb-2">Estructura requerida para el archivo:</h3>
            <VTable density="compact" class="border rounded">
              <thead>
                <tr>
                  <th class="text-left font-weight-bold">Columna (Cabecera)</th>
                  <th class="text-left font-weight-bold">Requerido</th>
                  <th class="text-left font-weight-bold">Descripción</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="col in fileSchemas[activeTab]" :key="col.field">
                  <td><code>{{ col.field }}</code></td>
                  <td>
                    <VChip size="x-small" :color="col.required ? 'error' : 'secondary'">
                      {{ col.required ? 'Obligatorio' : 'Opcional' }}
                    </VChip>
                  </td>
                  <td>{{ col.desc }}</td>
                </tr>
              </tbody>
            </VTable>
          </div>

          <VDivider class="my-4" />

          <div class="d-flex flex-column align-center justify-center border-dashed rounded p-6 bg-var-theme-background">
            <VIcon icon="tabler-file-type-csv" size="48" color="primary" class="mb-2" />
            <span class="text-body-2 font-weight-bold mb-1">
              {{ selectedFile ? selectedFile.name : 'Selecciona el archivo correspondiente a ' + activeTab }}
            </span>
            <span class="text-caption text-disabled mb-4" v-if="selectedFile">
              Tamaño: {{ (selectedFile.size / 1024).toFixed(2) }} KB
            </span>

            <input
              id="csv-file-input"
              type="file"
              accept=".csv, text/plain"
              class="d-none"
              @change="handleFileSelect"
            />
            
            <div class="d-flex gap-4">
              <VBtn
                color="secondary"
                variant="tonal"
                prepend-icon="tabler-upload"
                @click="() => document.getElementById('csv-file-input').click()"
                :disabled="uploading"
              >
                Buscar Archivo
              </VBtn>

              <VBtn
                color="primary"
                prepend-icon="tabler-database-import"
                :disabled="!selectedFile || uploading"
                :loading="uploading"
                @click="triggerImport"
              >
                Comenzar Importación
              </VBtn>
            </div>

            <VProgressLinear
              v-if="uploading"
              v-model="progress"
              color="primary"
              height="8"
              striped
              class="mt-4 w-50 rounded"
            />
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.border-dashed {
  border: 2px dashed rgba(var(--v-border-color), 0.3) !important;
}
.p-6 {
  padding: 1.5rem;
}
</style>
