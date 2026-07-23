<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert"
import { useBrandingStore } from "@/stores/useBrandingStore"

const brandingStore = useBrandingStore()

// --- Estado de UI ---
const isLoading = ref(true)
const isSaving = ref(false)

// --- Estado reactivo de la configuración ---
const enabledSupplierViews = ref([])
const enabledSupplierTypes = ref([])
const supplierFormFields = ref([])
const expenseSupplierFormFields = ref([])

// --- Opciones estáticas (no reactivas, son constantes) ---
const supplierViewOptions = [
  { label: "Lista de Proveedores", value: "list", icon: "tabler-list", description: "Habilita el catálogo principal y panel comercial de proveedores." },
  { label: "Órdenes de Compra", value: "purchase_orders", icon: "tabler-shopping-cart", description: "Habilita la gestión y trazabilidad de órdenes de compra." },
]

const supplierTypeOptions = [
  { label: "Proveedores de Inventario", value: "inventory", icon: "tabler-building-warehouse", description: "Proveedores de mercancía y productos para stock." },
  { label: "Proveedores de Gastos", value: "expenses", icon: "tabler-receipt", description: "Proveedores de servicios y gastos operativos con formulario rápido." },
]

const supplierFormFieldOptions = [
  { label: "Nombre Comercial", value: "name", icon: "tabler-building-store" },
  { label: "Razón Social", value: "social_reason", icon: "tabler-building" },
  { label: "RIF / Identificación Fiscal", value: "rif", icon: "tabler-id" },
  { label: "Dirección Física", value: "address", icon: "tabler-map-pin" },
  { label: "Teléfono de Ventas", value: "sales_phone", icon: "tabler-phone" },
  { label: "Teléfono de Cobranza", value: "collections_phone", icon: "tabler-phone-call" },
  { label: "Tipo de Vencimiento de Pago", value: "payment_due_type", icon: "tabler-calendar-due" },
  { label: "Referencia de Fecha de Factura", value: "invoice_date_reference", icon: "tabler-calendar" },
  { label: "Días de Crédito Personalizado", value: "custom_due_days", icon: "tabler-clock" },
  { label: "Referencia de Vencimiento", value: "payment_due_reference", icon: "tabler-calendar-x" },
  { label: "Método de Pago Habitual", value: "payment_method", icon: "tabler-credit-card" },
  { label: "Indexado en Dólares (USD)", value: "is_indexed", icon: "tabler-currency-dollar" },
  { label: "Logística y Despacho", value: "logistics_dispatch", icon: "tabler-truck-delivery" },
]

const expenseSupplierFormFieldOptions = [
  { label: "Nombre Comercial", value: "name", icon: "tabler-building-store" },
  { label: "RIF / Identificación Fiscal", value: "rif", icon: "tabler-id" },
  { label: "Dirección Física", value: "address", icon: "tabler-map-pin" },
  { label: "Teléfono de Ventas", value: "sales_phone", icon: "tabler-phone" },
  { label: "Teléfono de Cobranza", value: "collections_phone", icon: "tabler-phone-call" },
  { label: "Método de Pago Habitual", value: "payment_method", icon: "tabler-credit-card" },
  { label: "Indexado en Dólares (USD)", value: "is_indexed", icon: "tabler-currency-dollar" },
]

// --- Computed: contadores habilitados para badges informativos ---
const activeViewsCount = computed(() => enabledSupplierViews.value.length)
const activeTypesCount = computed(() => enabledSupplierTypes.value.length)
const activeSupplierFieldsCount = computed(() => supplierFormFields.value.length)
const activeExpenseFieldsCount = computed(() => expenseSupplierFormFields.value.length)

// --- Fetch de configuración ---
const fetchSettings = async () => {
  isLoading.value = true
  try {
    const response = await axios.get('/general-settings')
    const settings = response.data.data

    enabledSupplierViews.value = settings.enabled_supplier_views ?? supplierViewOptions.map(o => o.value)
    enabledSupplierTypes.value = settings.enabled_supplier_types ?? supplierTypeOptions.map(o => o.value)
    supplierFormFields.value = settings.supplier_form_fields ?? supplierFormFieldOptions.map(o => o.value)
    expenseSupplierFormFields.value = settings.expense_supplier_form_fields ?? expenseSupplierFormFieldOptions.map(o => o.value)
  } catch (error) {
    console.error("Error cargando configuración de proveedores:", error)
    toast.error("Error al cargar la configuración de proveedores")
  } finally {
    isLoading.value = false
  }
}

// --- Guardado con debounce implícito (isSaving previene doble envío) ---
const updateSettings = async () => {
  if (isSaving.value) return
  isSaving.value = true
  try {
    await axios.post('/general-settings', {
      enabled_supplier_views: enabledSupplierViews.value,
      enabled_supplier_types: enabledSupplierTypes.value,
      supplier_form_fields: supplierFormFields.value,
      expense_supplier_form_fields: expenseSupplierFormFields.value,
    })
    await brandingStore.fetchSettings()
    toast.success("Configuración de proveedores actualizada")
  } catch (error) {
    console.error("Error al guardar configuración de proveedores:", error)
    toast.error("Error al actualizar la configuración")
  } finally {
    isSaving.value = false
  }
}

onMounted(fetchSettings)
</script>

<template>
  <div class="d-flex flex-column gap-6 pb-12">

    <!-- ===================== SKELETON LOADER ===================== -->
    <template v-if="isLoading">
      <VCard v-for="n in 3" :key="n" class="rounded-xl">
        <VCardItem class="py-4">
          <VSkeletonLoader type="list-item-two-line" />
        </VCardItem>
        <VDivider />
        <VCardText class="py-6">
          <VRow>
            <VCol v-for="i in 4" :key="i" cols="12" sm="6" md="3">
              <VSkeletonLoader type="card" class="rounded-lg" />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </template>

    <!-- ===================== CONTENIDO PRINCIPAL ===================== -->
    <template v-else>

      <!-- CARD 1: Vistas y Tipos de Proveedores -->
      <VCard class="rounded-xl config-card">
        <VCardItem class="py-5 px-6">
          <template #prepend>
            <VAvatar color="primary" variant="tonal" size="42" class="rounded-lg">
              <VIcon icon="tabler-truck" size="22" />
            </VAvatar>
          </template>
          <VCardTitle class="text-h6 font-weight-semibold">
            Vistas y Tipos de Proveedores
          </VCardTitle>
          <VCardSubtitle class="text-body-2 mt-1">
            Si deshabilitas ambas vistas, la opción "Proveedores" desaparecerá del menú lateral.
          </VCardSubtitle>
          <template #append>
            <VChip
              :color="activeViewsCount === 0 && activeTypesCount === 0 ? 'error' : 'success'"
              size="small"
              variant="tonal"
              class="font-weight-semibold"
            >
              {{ activeViewsCount + activeTypesCount }} activos
            </VChip>
          </template>
        </VCardItem>

        <VDivider />

        <VCardText class="py-5 px-6">
          <!-- Subtítulo de sección -->
          <div class="text-overline text-disabled mb-3">Módulos de Vista</div>
          <VRow class="mb-2">
            <VCol
              v-for="viewItem in supplierViewOptions"
              :key="viewItem.value"
              cols="12" sm="6" md="3"
            >
              <VCard
                variant="outlined"
                :class="['pa-4 h-full option-card', enabledSupplierViews.includes(viewItem.value) ? 'option-card--active' : '']"
              >
                <div class="d-flex align-center justify-space-between mb-3">
                  <VAvatar size="32" color="primary" variant="tonal" class="rounded-md">
                    <VIcon :icon="viewItem.icon" size="16" />
                  </VAvatar>
                  <VSwitch
                    v-model="enabledSupplierViews"
                    :value="viewItem.value"
                    color="primary"
                    density="compact"
                    hide-details
                    :disabled="isSaving"
                    @update:model-value="updateSettings"
                  />
                </div>
                <div class="font-weight-semibold text-body-2 text-high-emphasis mb-1">{{ viewItem.label }}</div>
                <span class="text-caption text-medium-emphasis">{{ viewItem.description }}</span>
              </VCard>
            </VCol>
          </VRow>

          <!-- Estado vacío: sin vistas activas -->
          <VAlert
            v-if="activeViewsCount === 0"
            type="warning"
            variant="tonal"
            density="compact"
            class="mt-2 mb-4"
            icon="tabler-alert-triangle"
          >
            No hay vistas habilitadas. El módulo de proveedores estará oculto en el menú.
          </VAlert>

          <VDivider class="my-4 border-dashed" />

          <div class="text-overline text-disabled mb-3">Tipos de Proveedor</div>
          <VRow>
            <VCol
              v-for="typeItem in supplierTypeOptions"
              :key="typeItem.value"
              cols="12" sm="6" md="3"
            >
              <VCard
                variant="outlined"
                :class="['pa-4 h-full option-card', enabledSupplierTypes.includes(typeItem.value) ? 'option-card--active' : '']"
              >
                <div class="d-flex align-center justify-space-between mb-3">
                  <VAvatar size="32" color="secondary" variant="tonal" class="rounded-md">
                    <VIcon :icon="typeItem.icon" size="16" />
                  </VAvatar>
                  <VSwitch
                    v-model="enabledSupplierTypes"
                    :value="typeItem.value"
                    color="secondary"
                    density="compact"
                    hide-details
                    :disabled="isSaving"
                    @update:model-value="updateSettings"
                  />
                </div>
                <div class="font-weight-semibold text-body-2 text-high-emphasis mb-1">{{ typeItem.label }}</div>
                <span class="text-caption text-medium-emphasis">{{ typeItem.description }}</span>
              </VCard>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- CARD 2: Campos del Formulario de Proveedores -->
      <VCard class="rounded-xl config-card">
        <VCardItem class="py-5 px-6">
          <template #prepend>
            <VAvatar color="primary" variant="tonal" size="42" class="rounded-lg">
              <VIcon icon="tabler-forms" size="22" />
            </VAvatar>
          </template>
          <VCardTitle class="text-h6 font-weight-semibold">
            Campos del Formulario de Proveedor
          </VCardTitle>
          <VCardSubtitle class="text-body-2 mt-1">
            Selecciona qué campos estarán visibles al crear o editar un proveedor de inventario.
          </VCardSubtitle>
          <template #append>
            <VChip color="primary" size="small" variant="tonal" class="font-weight-semibold">
              {{ activeSupplierFieldsCount }}/{{ supplierFormFieldOptions.length }}
            </VChip>
          </template>
        </VCardItem>

        <VDivider />

        <VCardText class="py-6 px-6">
          <!-- Estado vacío: sin campos -->
          <VAlert
            v-if="activeSupplierFieldsCount === 0"
            type="error"
            variant="tonal"
            density="compact"
            class="mb-5"
            icon="tabler-forms-off"
          >
            No hay campos habilitados. El formulario de proveedor estará vacío.
          </VAlert>

          <VRow>
            <VCol
              v-for="field in supplierFormFieldOptions"
              :key="field.value"
              cols="12" sm="6" md="4" lg="3"
            >
              <div :class="['field-item d-flex align-center gap-3 pa-3 rounded-lg', supplierFormFields.includes(field.value) ? 'field-item--active' : '']">
                <VIcon :icon="field.icon" size="16" class="text-medium-emphasis flex-shrink-0" />
                <VSwitch
                  v-model="supplierFormFields"
                  :value="field.value"
                  :label="field.label"
                  color="primary"
                  density="compact"
                  hide-details
                  :disabled="isSaving"
                  class="flex-grow-1"
                  @update:model-value="updateSettings"
                />
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- CARD 3: Campos del Formulario de Proveedor de Gastos -->
      <VCard class="rounded-xl config-card">
        <VCardItem class="py-5 px-6">
          <template #prepend>
            <VAvatar color="warning" variant="tonal" size="42" class="rounded-lg">
              <VIcon icon="tabler-receipt-tax" size="22" />
            </VAvatar>
          </template>
          <VCardTitle class="text-h6 font-weight-semibold">
            Campos del Formulario de Proveedor de Gastos
          </VCardTitle>
          <VCardSubtitle class="text-body-2 mt-1">
            Selecciona qué campos estarán visibles al crear o editar un proveedor de tipo <strong>Gasto</strong>.
          </VCardSubtitle>
          <template #append>
            <VChip color="warning" size="small" variant="tonal" class="font-weight-semibold">
              {{ activeExpenseFieldsCount }}/{{ expenseSupplierFormFieldOptions.length }}
            </VChip>
          </template>
        </VCardItem>

        <VDivider />

        <VCardText class="py-6 px-6">
          <!-- Estado vacío -->
          <VAlert
            v-if="activeExpenseFieldsCount === 0"
            type="warning"
            variant="tonal"
            density="compact"
            class="mb-5"
            icon="tabler-forms-off"
          >
            No hay campos habilitados para el formulario de gastos.
          </VAlert>

          <VRow>
            <VCol
              v-for="field in expenseSupplierFormFieldOptions"
              :key="field.value"
              cols="12" sm="6" md="4" lg="3"
            >
              <div :class="['field-item d-flex align-center gap-3 pa-3 rounded-lg', expenseSupplierFormFields.includes(field.value) ? 'field-item--active field-item--warning' : '']">
                <VIcon :icon="field.icon" size="16" class="text-medium-emphasis flex-shrink-0" />
                <VSwitch
                  v-model="expenseSupplierFormFields"
                  :value="field.value"
                  :label="field.label"
                  color="warning"
                  density="compact"
                  hide-details
                  :disabled="isSaving"
                  class="flex-grow-1"
                  @update:model-value="updateSettings"
                />
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- Indicador de guardado flotante -->
      <VFadeTransition>
        <div v-if="isSaving" class="saving-indicator d-flex align-center gap-2">
          <VProgressCircular indeterminate size="16" width="2" color="primary" />
          <span class="text-caption text-medium-emphasis">Guardando...</span>
        </div>
      </VFadeTransition>

    </template>
  </div>
</template>

<style scoped>
/* Tarjeta de configuración con transición sutil */
.config-card {
  transition: box-shadow 0.2s ease;
}

.config-card:hover {
  box-shadow: 0 4px 24px rgba(var(--v-theme-primary), 0.08) !important;
}

/* Tarjeta de opción (switch card) */
.option-card {
  transition: border-color 0.2s ease, background-color 0.2s ease;
  cursor: default;
}

.option-card--active {
  border-color: rgba(var(--v-theme-primary), 0.5) !important;
  background-color: rgba(var(--v-theme-primary), 0.04) !important;
}

/* Ítem de campo del formulario */
.field-item {
  border: 1px solid transparent;
  transition: background-color 0.2s ease, border-color 0.2s ease;
}

.field-item--active {
  background-color: rgba(var(--v-theme-primary), 0.06);
  border-color: rgba(var(--v-theme-primary), 0.2) !important;
}

.field-item--warning.field-item--active {
  background-color: rgba(var(--v-theme-warning), 0.06);
  border-color: rgba(var(--v-theme-warning), 0.2) !important;
}

/* Indicador flotante de guardado */
.saving-indicator {
  position: fixed;
  inset-block-end: 24px;
  inset-inline-end: 24px;
  z-index: 1000;
  padding: 8px 16px;
  border-radius: 999px;
  backdrop-filter: blur(10px);
  background-color: rgba(var(--v-theme-surface), 0.9);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
  border: 1px solid rgba(var(--v-border-color), 0.12);
}
</style>
