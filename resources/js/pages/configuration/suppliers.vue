<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAbility } from '@casl/vue'
import axios from '@/plugins/axios'
import { toast, confirmDialog } from '@/plugins/sweetalert'
import { useBrandingStore } from '@/stores/useBrandingStore'

const ability = useAbility()
const brandingStore = useBrandingStore()

// --- Verificación de Permisos CASL ---
const canManageSettings = computed(() => {
  return ability.can('manage', 'Configuration') || ability.can('update', 'GeneralSettings') || ability.can('manage', 'all')
})

// --- Estado de UI y Persistencia ---
const isLoading = ref(true)
const isSaving = ref(false)
let debounceTimeout = null

// --- Estado Reactivo de Configuración ---
const enabledSupplierViews = ref([])
const enabledSupplierTypes = ref([])
const supplierFormFields = ref([])
const expenseSupplierFormFields = ref([])

// --- Definición de Parámetros y Constantes ---
const MANDATORY_SUPPLIER_FIELDS = ['name', 'rif']

const supplierViewOptions = [
  {
    label: 'Lista de Proveedores',
    value: 'list',
    icon: 'tabler-list',
    description: 'Habilita el catálogo principal, ficha comercial y saldos de proveedores.',
  },
  {
    label: 'Órdenes de Compra',
    value: 'purchase_orders',
    icon: 'tabler-shopping-cart',
    description: 'Habilita la emisión, recepción y auditoría de órdenes de compra.',
  },
]

const supplierTypeOptions = [
  {
    label: 'Proveedores de Inventario',
    value: 'inventory',
    icon: 'tabler-building-warehouse',
    description: 'Catálogo de medicamentos, insumos y mercancía para stock.',
  },
  {
    label: 'Proveedores de Gastos',
    value: 'expenses',
    icon: 'tabler-receipt',
    description: 'Servicios, insumos operativos y gastos administrativos.',
  },
]

const supplierFormFieldOptions = [
  { label: 'Nombre Comercial', value: 'name', icon: 'tabler-building-store', required: true, tooltip: 'Identificador comercial visible en búsquedas.' },
  { label: 'RIF / Identificación Fiscal', value: 'rif', icon: 'tabler-id', required: true, tooltip: 'Documento de identidad fiscal requerido para facturación.' },
  { label: 'Razón Social', value: 'social_reason', icon: 'tabler-building', required: false, tooltip: 'Nombre legal registrado ante las autoridades tributarias.' },
  { label: 'Dirección Física', value: 'address', icon: 'tabler-map-pin', required: false, tooltip: 'Ubicación física de despacho o sede principal.' },
  { label: 'Teléfono de Ventas', value: 'sales_phone', icon: 'tabler-phone', required: false, tooltip: 'Línea de contacto para colocación de pedidos.' },
  { label: 'Teléfono de Cobranza', value: 'collections_phone', icon: 'tabler-phone-call', required: false, tooltip: 'Contacto de administración para gestión de pagos.' },
  { label: 'Tipo de Vencimiento de Pago', value: 'payment_due_type', icon: 'tabler-calendar-due', required: false, tooltip: 'Define si el crédito se calcula desde recepción o factura.' },
  { label: 'Referencia de Fecha de Factura', value: 'invoice_date_reference', icon: 'tabler-calendar', required: false, tooltip: 'Permite registrar la fecha de emisión del proveedor.' },
  { label: 'Días de Crédito Personalizado', value: 'custom_due_days', icon: 'tabler-clock', required: false, tooltip: 'Días de plazo pactados contractualmente.' },
  { label: 'Referencia de Vencimiento', value: 'payment_due_reference', icon: 'tabler-calendar-x', required: false, tooltip: 'Cálculo dinámico de fecha límite de pago.' },
  { label: 'Método de Pago Habitual', value: 'payment_method', icon: 'tabler-credit-card', required: false, tooltip: 'Canal de egreso predeterminado (Transferencia, etc.).' },
  { label: 'Indexado en Dólares (USD)', value: 'is_indexed', icon: 'tabler-currency-dollar', required: false, tooltip: 'Habilita cálculo de diferencial cambiario a tasa oficial.' },
  { label: 'Logística y Despacho', value: 'logistics_dispatch', icon: 'tabler-truck-delivery', required: false, tooltip: 'Condiciones de transporte y tiempos de entrega.' },
]

const expenseSupplierFormFieldOptions = [
  { label: 'Nombre Comercial', value: 'name', icon: 'tabler-building-store', required: true, tooltip: 'Nombre identificativo del prestador del servicio.' },
  { label: 'RIF / Identificación Fiscal', value: 'rif', icon: 'tabler-id', required: true, tooltip: 'Identificación fiscal obligatoria para egresos deducibles.' },
  { label: 'Dirección Física', value: 'address', icon: 'tabler-map-pin', required: false, tooltip: 'Dirección del prestador del servicio.' },
  { label: 'Teléfono de Ventas', value: 'sales_phone', icon: 'tabler-phone', required: false, tooltip: 'Teléfono comercial de atención.' },
  { label: 'Teléfono de Cobranza', value: 'collections_phone', icon: 'tabler-phone-call', required: false, tooltip: 'Contacto para envío de comprobantes de pago.' },
  { label: 'Método de Pago Habitual', value: 'payment_method', icon: 'tabler-credit-card', required: false, tooltip: 'Vía bancaria habitual de pago de gastos.' },
  { label: 'Indexado en Dólares (USD)', value: 'is_indexed', icon: 'tabler-currency-dollar', required: false, tooltip: 'Indica si el servicio cotiza en divisa extranjera.' },
]

// --- Computed: Métricas Informativas ---
const activeViewsCount = computed(() => enabledSupplierViews.value.length)
const activeTypesCount = computed(() => enabledSupplierTypes.value.length)
const activeSupplierFieldsCount = computed(() => supplierFormFields.value.length)
const activeExpenseFieldsCount = computed(() => expenseSupplierFormFields.value.length)

// --- Carga de Configuración ---
const fetchSettings = async () => {
  isLoading.value = true
  try {
    const response = await axios.get('/general-settings')
    const settings = response.data.data

    enabledSupplierViews.value = settings.enabled_supplier_views ?? supplierViewOptions.map(o => o.value)
    enabledSupplierTypes.value = settings.enabled_supplier_types ?? supplierTypeOptions.map(o => o.value)
    
    // Asegurar siempre campos mandatorios
    const loadedSupplierFields = settings.supplier_form_fields ?? supplierFormFieldOptions.map(o => o.value)
    supplierFormFields.value = Array.from(new Set([...loadedSupplierFields, ...MANDATORY_SUPPLIER_FIELDS]))

    const loadedExpenseFields = settings.expense_supplier_form_fields ?? expenseSupplierFormFieldOptions.map(o => o.value)
    expenseSupplierFormFields.value = Array.from(new Set([...loadedExpenseFields, ...MANDATORY_SUPPLIER_FIELDS]))
  } catch (error) {
    console.error('Error cargando configuración de proveedores:', error)
    toast.error('Error al cargar la configuración de proveedores')
  } finally {
    isLoading.value = false
  }
}

// --- Persistencia con Debounce ---
const saveSettings = async () => {
  if (isSaving.value || !canManageSettings.value) return
  isSaving.value = true

  const finalSupplierFields = Array.from(new Set([...supplierFormFields.value, ...MANDATORY_SUPPLIER_FIELDS]))
  const finalExpenseFields = Array.from(new Set([...expenseSupplierFormFields.value, ...MANDATORY_SUPPLIER_FIELDS]))

  try {
    await axios.post('/general-settings', {
      enabled_supplier_views: enabledSupplierViews.value,
      enabled_supplier_types: enabledSupplierTypes.value,
      supplier_form_fields: finalSupplierFields,
      expense_supplier_form_fields: finalExpenseFields,
    })

    await brandingStore.fetchSettings()
    toast.success('Configuración de proveedores actualizada')
  } catch (error) {
    console.error('Error al guardar configuración de proveedores:', error)
    toast.error('Error al actualizar la configuración')
  } finally {
    isSaving.value = false
  }
}

const handleSettingChange = () => {
  if (debounceTimeout) clearTimeout(debounceTimeout)
  debounceTimeout = setTimeout(() => {
    saveSettings()
  }, 600)
}

// --- Acciones Masivas y Restablecimiento ---
const selectAllSupplierFields = () => {
  supplierFormFields.value = supplierFormFieldOptions.map(f => f.value)
  handleSettingChange()
}

const selectOnlyMandatorySupplierFields = () => {
  supplierFormFields.value = [...MANDATORY_SUPPLIER_FIELDS]
  handleSettingChange()
}

const selectAllExpenseFields = () => {
  expenseSupplierFormFields.value = expenseSupplierFormFieldOptions.map(f => f.value)
  handleSettingChange()
}

const selectOnlyMandatoryExpenseFields = () => {
  expenseSupplierFormFields.value = [...MANDATORY_SUPPLIER_FIELDS]
  handleSettingChange()
}

const resetToDefaults = async () => {
  const confirmed = await confirmDialog({
    title: '¿Restablecer configuración predeterminada?',
    text: 'Se habilitarán todas las vistas y campos de formulario para proveedores.',
    icon: 'warning',
    confirmButtonText: 'Sí, restablecer',
    cancelButtonText: 'Cancelar',
  })

  if (!confirmed) return

  enabledSupplierViews.value = supplierViewOptions.map(o => o.value)
  enabledSupplierTypes.value = supplierTypeOptions.map(o => o.value)
  supplierFormFields.value = supplierFormFieldOptions.map(o => o.value)
  expenseSupplierFormFields.value = expenseSupplierFormFieldOptions.map(o => o.value)

  saveSettings()
}

onMounted(fetchSettings)
</script>

<template>
  <div class="d-flex flex-column gap-6 pb-12">
    <!-- Header de Módulo y Acciones Globales -->
    <div class="d-flex flex-wrap align-center justify-space-between gap-4">
      <div>
        <h1 class="text-h5 font-weight-bold mb-1">
          Configuración de Proveedores
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Personalice la visibilidad de módulos, submódulos y campos de captura del catálogo de proveedores y egresos.
        </p>
      </div>

      <div class="d-flex align-center gap-3">
        <VBtn
          v-if="canManageSettings"
          variant="outlined"
          color="secondary"
          density="comfortable"
          prepend-icon="tabler-rotate"
          :disabled="isLoading || isSaving"
          @click="resetToDefaults"
        >
          Valores por Defecto
        </VBtn>
      </div>
    </div>

    <!-- ===================== SKELETON LOADER ===================== -->
    <template v-if="isLoading">
      <VCard v-for="n in 3" :key="n" variant="outlined" class="rounded-lg">
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
      <VCard variant="outlined" class="rounded-lg">
        <VCardItem class="py-4 px-6">
          <template #prepend>
            <VAvatar color="primary" variant="tonal" size="42" class="rounded-lg">
              <VIcon icon="tabler-truck" size="22" />
            </VAvatar>
          </template>
          <VCardTitle class="text-subtitle-1 font-weight-semibold">
            Vistas y Tipos de Proveedores
          </VCardTitle>
          <VCardSubtitle class="text-caption text-medium-emphasis">
            Control de visibilidad de submódulos en la barra de navegación lateral.
          </VCardSubtitle>
          <template #append>
            <VChip
              :color="activeViewsCount === 0 && activeTypesCount === 0 ? 'error' : 'success'"
              size="small"
              variant="tonal"
              class="font-weight-semibold"
            >
              {{ activeViewsCount + activeTypesCount }} Activos
            </VChip>
          </template>
        </VCardItem>

        <VDivider />

        <VCardText class="py-5 px-6">
          <div class="text-overline text-disabled mb-3">Módulos de Vista en Menú</div>
          <VRow class="mb-4">
            <VCol
              v-for="viewItem in supplierViewOptions"
              :key="viewItem.value"
              cols="12"
              sm="6"
              md="6"
              lg="3"
            >
              <VCard
                variant="outlined"
                class="pa-4 h-100 rounded-lg"
                :color="enabledSupplierViews.includes(viewItem.value) ? 'primary' : undefined"
              >
                <div class="d-flex align-center justify-space-between mb-3">
                  <VAvatar size="32" color="primary" variant="tonal" class="rounded-md">
                    <VIcon :icon="viewItem.icon" size="18" />
                  </VAvatar>
                  <VSwitch
                    v-model="enabledSupplierViews"
                    :value="viewItem.value"
                    color="primary"
                    density="comfortable"
                    hide-details="auto"
                    :disabled="isSaving || !canManageSettings"
                    @update:model-value="handleSettingChange"
                  />
                </div>
                <div class="font-weight-semibold text-body-2 mb-1">{{ viewItem.label }}</div>
                <div class="text-caption text-medium-emphasis">{{ viewItem.description }}</div>
              </VCard>
            </VCol>
          </VRow>

          <VAlert
            v-if="activeViewsCount === 0"
            type="warning"
            variant="tonal"
            density="comfortable"
            class="mb-4"
            icon="tabler-alert-triangle"
          >
            No hay vistas habilitadas. La sección de Proveedores no se mostrará en el menú de navegación.
          </VAlert>

          <VDivider class="my-4 border-dashed" />

          <div class="text-overline text-disabled mb-3">Tipos de Proveedor Soportados</div>
          <VRow>
            <VCol
              v-for="typeItem in supplierTypeOptions"
              :key="typeItem.value"
              cols="12"
              sm="6"
              md="6"
              lg="3"
            >
              <VCard
                variant="outlined"
                class="pa-4 h-100 rounded-lg"
                :color="enabledSupplierTypes.includes(typeItem.value) ? 'secondary' : undefined"
              >
                <div class="d-flex align-center justify-space-between mb-3">
                  <VAvatar size="32" color="secondary" variant="tonal" class="rounded-md">
                    <VIcon :icon="typeItem.icon" size="18" />
                  </VAvatar>
                  <VSwitch
                    v-model="enabledSupplierTypes"
                    :value="typeItem.value"
                    color="secondary"
                    density="comfortable"
                    hide-details="auto"
                    :disabled="isSaving || !canManageSettings"
                    @update:model-value="handleSettingChange"
                  />
                </div>
                <div class="font-weight-semibold text-body-2 mb-1">{{ typeItem.label }}</div>
                <div class="text-caption text-medium-emphasis">{{ typeItem.description }}</div>
              </VCard>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- CARD 2: Campos del Formulario de Proveedores de Inventario -->
      <VCard variant="outlined" class="rounded-lg">
        <VCardItem class="py-4 px-6">
          <template #prepend>
            <VAvatar color="primary" variant="tonal" size="42" class="rounded-lg">
              <VIcon icon="tabler-forms" size="22" />
            </VAvatar>
          </template>
          <VCardTitle class="text-subtitle-1 font-weight-semibold">
            Campos del Formulario: Proveedor de Inventario
          </VCardTitle>
          <VCardSubtitle class="text-caption text-medium-emphasis">
            Active o desactive los campos visibles durante el registro y edición de proveedores comerciales.
          </VCardSubtitle>
          <template #append>
            <div class="d-flex align-center gap-2">
              <VBtn
                v-if="canManageSettings"
                size="small"
                variant="text"
                color="primary"
                @click="selectAllSupplierFields"
              >
                Todos
              </VBtn>
              <VBtn
                v-if="canManageSettings"
                size="small"
                variant="text"
                color="secondary"
                @click="selectOnlyMandatorySupplierFields"
              >
                Solo Obligatorios
              </VBtn>
              <VChip color="primary" size="small" variant="tonal" class="font-weight-semibold ml-2">
                {{ activeSupplierFieldsCount }}/{{ supplierFormFieldOptions.length }}
              </VChip>
            </div>
          </template>
        </VCardItem>

        <VDivider />

        <VCardText class="py-5 px-6">
          <VRow>
            <VCol
              v-for="field in supplierFormFieldOptions"
              :key="field.value"
              cols="12"
              sm="6"
              md="4"
              lg="3"
            >
              <div
                class="d-flex align-center justify-space-between pa-3 rounded-lg border"
                :class="{ 'bg-var-theme-background': !supplierFormFields.includes(field.value) }"
              >
                <div class="d-flex align-center gap-2 overflow-hidden mr-2">
                  <VIcon :icon="field.icon" size="18" class="text-medium-emphasis flex-shrink-0" />
                  <span class="text-body-2 text-truncate font-weight-medium">
                    {{ field.label }}
                  </span>
                  <VTooltip :text="field.tooltip" location="top">
                    <template #activator="{ props: tooltipProps }">
                      <VIcon
                        v-bind="tooltipProps"
                        icon="tabler-info-circle"
                        size="14"
                        class="text-disabled cursor-pointer flex-shrink-0"
                      />
                    </template>
                  </VTooltip>
                </div>

                <div class="d-flex align-center flex-shrink-0">
                  <VChip
                    v-if="field.required"
                    size="x-small"
                    color="primary"
                    variant="tonal"
                    class="mr-2 font-weight-semibold"
                  >
                    Req.
                  </VChip>
                  <VSwitch
                    v-model="supplierFormFields"
                    :value="field.value"
                    color="primary"
                    density="comfortable"
                    hide-details="auto"
                    :disabled="field.required || isSaving || !canManageSettings"
                    @update:model-value="handleSettingChange"
                  />
                </div>
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- CARD 3: Campos del Formulario de Proveedores de Gastos -->
      <VCard variant="outlined" class="rounded-lg">
        <VCardItem class="py-4 px-6">
          <template #prepend>
            <VAvatar color="warning" variant="tonal" size="42" class="rounded-lg">
              <VIcon icon="tabler-receipt-tax" size="22" />
            </VAvatar>
          </template>
          <VCardTitle class="text-subtitle-1 font-weight-semibold">
            Campos del Formulario: Proveedor de Gastos
          </VCardTitle>
          <VCardSubtitle class="text-caption text-medium-emphasis">
            Campos visualizados al registrar proveedores de servicios, suministros y gastos operativos.
          </VCardSubtitle>
          <template #append>
            <div class="d-flex align-center gap-2">
              <VBtn
                v-if="canManageSettings"
                size="small"
                variant="text"
                color="warning"
                @click="selectAllExpenseFields"
              >
                Todos
              </VBtn>
              <VBtn
                v-if="canManageSettings"
                size="small"
                variant="text"
                color="secondary"
                @click="selectOnlyMandatoryExpenseFields"
              >
                Solo Obligatorios
              </VBtn>
              <VChip color="warning" size="small" variant="tonal" class="font-weight-semibold ml-2">
                {{ activeExpenseFieldsCount }}/{{ expenseSupplierFormFieldOptions.length }}
              </VChip>
            </div>
          </template>
        </VCardItem>

        <VDivider />

        <VCardText class="py-5 px-6">
          <VRow>
            <VCol
              v-for="field in expenseSupplierFormFieldOptions"
              :key="field.value"
              cols="12"
              sm="6"
              md="4"
              lg="3"
            >
              <div
                class="d-flex align-center justify-space-between pa-3 rounded-lg border"
                :class="{ 'bg-var-theme-background': !expenseSupplierFormFields.includes(field.value) }"
              >
                <div class="d-flex align-center gap-2 overflow-hidden mr-2">
                  <VIcon :icon="field.icon" size="18" class="text-medium-emphasis flex-shrink-0" />
                  <span class="text-body-2 text-truncate font-weight-medium">
                    {{ field.label }}
                  </span>
                  <VTooltip :text="field.tooltip" location="top">
                    <template #activator="{ props: tooltipProps }">
                      <VIcon
                        v-bind="tooltipProps"
                        icon="tabler-info-circle"
                        size="14"
                        class="text-disabled cursor-pointer flex-shrink-0"
                      />
                    </template>
                  </VTooltip>
                </div>

                <div class="d-flex align-center flex-shrink-0">
                  <VChip
                    v-if="field.required"
                    size="x-small"
                    color="warning"
                    variant="tonal"
                    class="mr-2 font-weight-semibold"
                  >
                    Req.
                  </VChip>
                  <VSwitch
                    v-model="expenseSupplierFormFields"
                    :value="field.value"
                    color="warning"
                    density="comfortable"
                    hide-details="auto"
                    :disabled="field.required || isSaving || !canManageSettings"
                    @update:model-value="handleSettingChange"
                  />
                </div>
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- Indicador Flotante de Persistencia -->
      <VFadeTransition>
        <div
          v-if="isSaving"
          class="position-fixed d-flex align-center gap-2 py-2 px-4 bg-surface rounded-pill elevation-4 border"
          style="inset-block-end: 24px; inset-inline-end: 24px; z-index: 1050;"
        >
          <VProgressCircular indeterminate size="16" width="2" color="primary" />
          <span class="text-caption font-weight-medium">Guardando configuración...</span>
        </div>
      </VFadeTransition>
    </template>
  </div>
</template>
