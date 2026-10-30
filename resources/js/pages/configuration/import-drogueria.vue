<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import Swal from 'sweetalert2'
import { toast } from '@/plugins/sweetalert'

const isLoadingSuppliers = ref(false)
const isLoadingPreview = ref(false)
const isImporting = ref(false)

const suppliers = ref([])
const selectedSupplierId = ref(null)
const catalogPreview = ref(null)
const importResult = ref(null)

const form = reactive({
  margin_percentage: 18.0,
  default_credit_days: 7,
  default_stock_if_zero: 100,
  only_with_stock: false,
})

const selectedSupplier = computed(() => {
  return suppliers.value.find(s => s.id === selectedSupplierId.value) || null
})

const fetchSuppliers = async () => {
  isLoadingSuppliers.value = true
  try {
    const { data } = await axios.get('/import/drogueria/master-suppliers')
    suppliers.value = data.data || []
  } catch (error) {
    toast.error('No se pudieron consultar los proveedores de la base central.')
  } finally {
    isLoadingSuppliers.value = false
  }
}

const onSupplierSelected = async (supplierId) => {
  if (!supplierId) {
    catalogPreview.value = null
    return
  }

  // Preconfigurar días de crédito según el proveedor si los tiene definidos
  const supplier = suppliers.value.find(s => s.id === supplierId)
  if (supplier && supplier.credit_days) {
    form.default_credit_days = Number(supplier.credit_days)
  }

  isLoadingPreview.value = true
  catalogPreview.value = null
  importResult.value = null

  try {
    const { data } = await axios.get(`/import/drogueria/master-suppliers/${supplierId}/preview`)
    if (data.success) {
      catalogPreview.value = data
    } else {
      toast.warning(data.message || 'No se pudo cargar la vista previa.')
    }
  } catch (error) {
    toast.error('Error al previsualizar el catálogo del proveedor.')
  } finally {
    isLoadingPreview.value = false
  }
}

const confirmAndExecuteImport = async () => {
  if (!selectedSupplierId.value) {
    toast.warning('Por favor selecciona una droguería o proveedor.')
    return
  }

  const result = await Swal.fire({
    title: '¿Confirmar Carga Masiva a Inventario?',
    text: `Se importarán los productos del proveedor "${selectedSupplier.value?.name}" hacia el inventario local aplicando ${form.margin_percentage}% de margen y ${form.default_credit_days} días de crédito base.`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#0052cc',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Sí, Cargar Inventario',
    cancelButtonText: 'Cancelar',
  })

  if (!result.isConfirmed) return

  isImporting.value = true
  importResult.value = null

  try {
    const payload = {
      master_supplier_id: selectedSupplierId.value,
      margin_percentage: form.margin_percentage,
      default_credit_days: form.default_credit_days,
      default_stock_if_zero: form.default_stock_if_zero,
      only_with_stock: form.only_with_stock,
    }

    const { data } = await axios.post('/import/drogueria/execute', payload)

    if (data.success) {
      importResult.value = data.data
      toast.success(data.message || 'Inventario cargado exitosamente.')
      // Refrescar conteo de proveedores
      fetchSuppliers()
    } else {
      toast.error(data.message || 'Error en la importación.')
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error al procesar la importación masiva.')
  } finally {
    isImporting.value = false
  }
}

onMounted(() => {
  fetchSuppliers()
})
</script>

<template>
  <VContainer fluid>
    <!-- Encabezado -->
    <VCard class="mb-4" elevation="1">
      <VCardItem class="pb-3 pt-4">
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div>
            <VCardTitle class="text-h5 font-weight-bold d-flex align-center gap-2">
              <VIcon icon="tabler-truck-loading" color="primary" size="28" />
              Carga Directa de Inventario desde Droguerías del Master
            </VCardTitle>
            <VCardSubtitle class="text-body-2 text-medium-emphasis mt-1">
              Selecciona una droguería proveedora del catálogo central para volcar automáticamente sus productos, lotes y costos a tu inventario.
            </VCardSubtitle>
          </div>
          <VBtn
            color="primary"
            variant="tonal"
            prepend-icon="tabler-reload"
            :loading="isLoadingSuppliers"
            @click="fetchSuppliers"
          >
            Actualizar Proveedores
          </VBtn>
        </div>
      </VCardItem>
    </VCard>

    <!-- Formulario de Selección y Configuración Comercial -->
    <VCard class="mb-4" elevation="1">
      <VCardText class="pa-5">
        <VRow>
          <!-- Selector de Droguería / Proveedor Master -->
          <VCol cols="12" md="6">
            <VAutocomplete
              v-model="selectedSupplierId"
              :items="suppliers"
              :loading="isLoadingSuppliers"
              item-title="name"
              item-value="id"
              label="Seleccionar Droguería / Proveedor del Master"
              placeholder="Buscar por nombre o RIF..."
              prepend-inner-icon="tabler-building-store"
              variant="outlined"
              density="comfortable"
              clearable
              @update:model-value="onSupplierSelected"
            >
              <template #item="{ props, item }">
                <VListItem v-bind="props" :title="item.raw.name">
                  <template #subtitle>
                    <div class="d-flex align-center gap-2 mt-1">
                      <span v-if="item.raw.rif">{{ item.raw.rif }}</span>
                      <VChip size="x-small" color="primary" variant="tonal">
                        {{ item.raw.catalog_items_count }} productos en catálogo
                      </VChip>
                      <VChip v-if="item.raw.credit_days" size="x-small" color="info" variant="outlined">
                        {{ item.raw.credit_days }} días crédito
                      </VChip>
                    </div>
                  </template>
                </VListItem>
              </template>
            </VAutocomplete>
          </VCol>

          <!-- Margen Comercial Mayorista -->
          <VCol cols="12" sm="4" md="2">
            <VTextField
              v-model.number="form.margin_percentage"
              type="number"
              step="0.5"
              min="0"
              label="% Margen Mayorista"
              suffix="%"
              variant="outlined"
              density="comfortable"
            />
          </VCol>

          <!-- Días de Crédito por Defecto -->
          <VCol cols="12" sm="4" md="2">
            <VTextField
              v-model.number="form.default_credit_days"
              type="number"
              min="0"
              max="180"
              label="Días Crédito Base"
              suffix="días"
              variant="outlined"
              density="comfortable"
            />
          </VCol>

          <!-- Stock por Defecto si viene en 0 -->
          <VCol cols="12" sm="4" md="2">
            <VTextField
              v-model.number="form.default_stock_if_zero"
              type="number"
              min="0"
              label="Stock Si Viene 0"
              suffix="und"
              variant="outlined"
              density="comfortable"
            />
          </VCol>

          <!-- Switch y Botón de Ejecución -->
          <VCol cols="12" md="6" class="d-flex align-center">
            <VSwitch
              v-model="form.only_with_stock"
              color="primary"
              label="Importar únicamente productos con stock > 0 en el catálogo"
              hide-details
            />
          </VCol>

          <VCol cols="12" md="6" class="text-right">
            <VBtn
              color="primary"
              size="large"
              prepend-icon="tabler-database-import"
              :disabled="!selectedSupplierId || isImporting"
              :loading="isImporting"
              @click="confirmAndExecuteImport"
            >
              Cargar Inventario a la Droguería
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Resumen de Resultados de la Carga -->
    <VAlert
      v-if="importResult"
      type="success"
      variant="tonal"
      class="mb-4"
    >
      <div class="font-weight-bold text-subtitle-1 mb-2">
        Resultado de la Carga de Inventario: {{ importResult.supplier_name }}
      </div>
      <VRow>
        <VCol cols="6" sm="3">
          <div class="text-caption">Productos Creados</div>
          <div class="text-h6 font-weight-bold">{{ importResult.created_products }}</div>
        </VCol>
        <VCol cols="6" sm="3">
          <div class="text-caption">Productos Actualizados</div>
          <div class="text-h6 font-weight-bold">{{ importResult.updated_products }}</div>
        </VCol>
        <VCol cols="6" sm="3">
          <div class="text-caption">Lotes FEFO Generados</div>
          <div class="text-h6 font-weight-bold">{{ importResult.created_lots }}</div>
        </VCol>
        <VCol cols="6" sm="3">
          <div class="text-caption">Unidades Totales Cargadas</div>
          <div class="text-h6 font-weight-bold">{{ Number(importResult.total_units_loaded).toLocaleString() }}</div>
        </VCol>
      </VRow>
    </VAlert>

    <!-- Vista Previa del Catálogo -->
    <VCard v-if="catalogPreview" elevation="1">
      <VCardTitle class="pa-4 d-flex align-center justify-space-between flex-wrap gap-2">
        <span class="font-weight-bold">
          Vista Previa del Catálogo: {{ catalogPreview.supplier?.name }}
        </span>
        <div class="d-flex align-center gap-2">
          <VChip size="small" color="info" variant="tonal">
            Total en Catálogo: {{ catalogPreview.metrics?.total_items }} SKUs
          </VChip>
          <VChip size="small" color="success" variant="tonal">
            Con Stock: {{ catalogPreview.metrics?.with_stock_count }} SKUs
          </VChip>
          <VChip size="small" color="secondary" variant="tonal">
            Costo Promedio: ${{ catalogPreview.metrics?.avg_cost_usd }}
          </VChip>
        </div>
      </VCardTitle>
      <VDivider />

      <VProgressLinear v-if="isLoadingPreview" indeterminate color="primary" />

      <VTable density="comfortable">
        <thead>
          <tr>
            <th>Cód. Proveedor</th>
            <th>Producto</th>
            <th>Laboratorio</th>
            <th>Código de Barras</th>
            <th class="text-center">Stock Catálogo</th>
            <th class="text-right">Costo Proveedor (USD)</th>
            <th class="text-right">Precio Mayorista Calc.</th>
            <th class="text-center">Días Crédito</th>
            <th class="text-center">Vencimiento</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in catalogPreview.preview_items" :key="item.id">
            <td class="font-weight-medium">{{ item.cod_supplier || 'N/A' }}</td>
            <td class="font-weight-bold">{{ item.name }}</td>
            <td>{{ item.laboratory || 'General' }}</td>
            <td>
              <code>{{ item.barcode || 'Sin código' }}</code>
            </td>
            <td class="text-center">
              <VChip size="small" :color="item.quantity > 0 ? 'success' : 'warning'" variant="tonal">
                {{ item.quantity }}
              </VChip>
            </td>
            <td class="text-right font-weight-bold">
              ${{ Number(item.unit_cost_usd).toFixed(2) }}
            </td>
            <td class="text-right font-weight-bold text-primary">
              ${{ (Number(item.unit_cost_usd) * (1 + form.margin_percentage / 100)).toFixed(2) }}
            </td>
            <td class="text-center">
              <VChip size="small" color="primary" variant="outlined">
                {{ item.credit_days ?? form.default_credit_days }} días
              </VChip>
            </td>
            <td class="text-center text-caption">
              {{ item.expiration || 'FEFO 12m' }}
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>
  </VContainer>
</template>
