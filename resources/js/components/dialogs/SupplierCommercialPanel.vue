<script setup>
import { ref, watch } from "vue"
import { useTheme } from 'vuetify'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  supplier: { type: Object, default: () => ({}) },
  laboratories: { type: Array, default: () => [] },
  paymentRules: { type: Array, default: () => [] },
  supplierDiscount: { type: Array, default: () => [] },
  discountRules: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits([
  "update:modelValue", 
  "save-payment-rules", 
  "save-discounts", 
  "save-discount-rules",
  "clear-errors"
])

const theme = useTheme()
const activeTab = ref(0)
const internalErrors = ref({})

// --- ESTADOS LOCALES PARA EDICIÓN ---
const editablePaymentRules = ref([])
const editableDiscounts = ref([])
const editableScaleRules = ref([])
const tempIdCounter = ref(-1)

const scaleTypes = [
  { id: 'units', name: 'Por unidades' },
  { id: 'amount', name: 'Por dólares ($)' },
]

// --- WATCHERS PARA SINCRONIZAR PROPS ---
watch(() => props.modelValue, (val) => {
  if (val) {
    syncLocalData()
    activeTab.value = 0
  }
})

watch([
  () => props.paymentRules,
  () => props.supplierDiscount,
  () => props.discountRules
], () => {
  if (props.modelValue) {
    syncLocalData()
  }
}, { deep: true })

watch(() => props.errors, (newErrors) => {
  internalErrors.value = { ...newErrors }
}, { deep: true })

const syncLocalData = () => {
  // Pronto Pago
  editablePaymentRules.value = props.paymentRules.map(rule => ({ ...rule, _markedForDeletion: false }))
  
  // Descuentos Planos
  editableDiscounts.value = props.supplierDiscount.map(d => ({ ...d, _markedNew: false }))

  // Reglas de Escala
  editableScaleRules.value = props.discountRules.map(r => ({ ...r, _markedNew: false }))
}

// --- LÓGICA DE PRONTO PAGO ---
const addPaymentRule = () => {
  editablePaymentRules.value.push({ id: tempIdCounter.value--, days: null, discount_percentage: null, _markedForDeletion: false })
}

const removePaymentRule = (index) => {
  editablePaymentRules.value.splice(index, 1)
}

// --- LÓGICA DE ESCALAS ---
const addScaleRule = () => {
  editableScaleRules.value.push({
    id: tempIdCounter.value--,
    laboratory: null,
    scale_type: { id: 'units', name: 'Por unidades' },
    min: 1,
    max: 100,
    discount_percentage: null,
    _markedNew: true
  })
}

const removeScaleRule = (index) => {
  editableScaleRules.value.splice(index, 1)
}

// --- LÓGICA DE DESCUENTOS ---
const addDiscount = () => {
  editableDiscounts.value.push({ id: tempIdCounter.value--, name: '', discount_percentage: null, _markedNew: true })
}

const removeDiscount = (index) => {
  editableDiscounts.value.splice(index, 1)
}

// --- GUARDADO UNIFICADO POR SECCIÓN ---
const saveFinances = () => {
  // Filtrar reglas vacías sin días ni porcentaje
  const validRules = editablePaymentRules.value.filter(r => (Number(r.days) > 0 || Number(r.discount_percentage) > 0))
  const data = validRules.map(r => ({
    id: r.id > 0 ? r.id : undefined,
    days: Number(r.days) || 0,
    discount_percentage: Number(r.discount_percentage) || 0
  }))
  emit('save-payment-rules', data)
}

const saveBrands = () => {
  const scalesData = editableScaleRules.value.map(s => ({
    id: s.id > 0 ? s.id : undefined,
    laboratory: s.laboratory,
    scale_type: s.scale_type,
    min: Number(s.min) || 1,
    max: Number(s.max) || 1,
    discount_percentage: Number(s.discount_percentage) || 0
  }))
  emit('save-discount-rules', scalesData)
}

const saveDiscounts = () => {
  const validDiscounts = editableDiscounts.value.filter(d => (d.name && d.name.trim() !== '') || Number(d.discount_percentage) > 0)
  const data = validDiscounts.map(d => ({
    id: d.id > 0 ? d.id : undefined,
    name: d.name,
    discount_percentage: Number(d.discount_percentage) || 0
  }))
  emit('save-discounts', data)
}

const saveCurrentTab = () => {
  if (activeTab.value === 0) {
    saveFinances()
  } else if (activeTab.value === 1) {
    saveBrands()
  } else if (activeTab.value === 2) {
    saveDiscounts()
  }
}

const close = () => {
  emit("update:modelValue", false)
  emit("clear-errors")
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="980px"
    :fullscreen="$vuetify.display.mobile"
    persistent
    @update:model-value="close"
  >
    <VCard class="detail-dialog-card overflow-hidden">
      <!-- Header Institucional con Gradiente de Marca -->
      <VCardTitle class="pa-0 flex-shrink-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="38" class="me-3 elevation-1">
            <VIcon icon="tabler-settings-dollar" color="primary" size="20" />
          </VAvatar>
          <div class="d-flex flex-column leading-none text-white">
            <h2 class="text-subtitle-1 font-weight-black leading-tight mb-0 uppercase text-white">
              Panel Comercial — {{ supplier.name }}
            </h2>
            <span class="text-caption opacity-85 font-weight-medium">
              RIF: {{ supplier.rif || 'N/A' }} • Configuración de políticas y bonificaciones
            </span>
          </div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="white" size="small" class="rounded-lg" @click="close" />
        </div>
      </VCardTitle>

      <!-- Pestañas Institucionales -->
      <VTabs v-model="activeTab" color="primary" grow bg-color="white" class="border-b">
        <VTab :value="0" class="font-weight-black text-xs uppercase letter-spacing-1">
          <VIcon start>tabler-receipt-2</VIcon>
          Finanzas (Pronto Pago)
        </VTab>
        <VTab :value="1" class="font-weight-black text-xs uppercase letter-spacing-1">
          <VIcon start>tabler-building-factory-2</VIcon>
          Marcas y Escalas
        </VTab>
        <VTab :value="2" class="font-weight-black text-xs uppercase letter-spacing-1">
          <VIcon start>tabler-percentage</VIcon>
          Otros Descuentos
        </VTab>
      </VTabs>

      <!-- Contenido del Diálogo -->
      <VCardText class="pa-0 dialog-content-scroll">
        <VWindow v-model="activeTab" class="pa-4 pa-sm-5 bg-light">

          <!-- TAB 1: FINANZAS (PRONTO PAGO) -->
          <VWindowItem :value="0">
            <div class="d-flex align-center justify-space-between mb-3">
              <div class="d-flex align-center gap-2">
                <div class="header-indicator primary shadow-sm" />
                <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">
                  Reglas de Pronto Pago
                </span>
              </div>
              <VBtn
                prepend-icon="tabler-plus"
                variant="tonal"
                color="primary"
                size="small"
                class="rounded-lg font-weight-black"
                @click="addPaymentRule"
              >
                + Nueva Regla
              </VBtn>
            </div>

            <VCard variant="flat" class="bg-white rounded-xl border shadow-sm mb-4 overflow-hidden">
              <VTable class="premium-table">
                <thead>
                  <tr>
                    <th style="inline-size: 45%;">DÍAS DE ANTICIPACIÓN</th>
                    <th style="inline-size: 40%;">% DESCUENTO</th>
                    <th class="text-center" style="inline-size: 15%;">ACCIONES</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(rule, index) in editablePaymentRules" :key="index" class="hover-row">
                    <td>
                      <AppTextField
                        v-model="rule.days"
                        type="number"
                        density="compact"
                        placeholder="Ej: 7"
                        hide-details
                        class="centered-input-field"
                        @focus="$event.target?.select()"
                      />
                    </td>
                    <td>
                      <AppTextField
                        v-model="rule.discount_percentage"
                        type="number"
                        density="compact"
                        suffix="%"
                        placeholder="0.00"
                        hide-details
                        class="centered-input-field"
                        @focus="$event.target?.select()"
                      />
                    </td>
                    <td class="text-center">
                      <VBtn
                        icon="tabler-trash"
                        variant="tonal"
                        color="error"
                        size="small"
                        class="rounded-lg"
                        @click="removePaymentRule(index)"
                      />
                    </td>
                  </tr>
                  <tr v-if="editablePaymentRules.length === 0">
                    <td colspan="3" class="text-center py-8">
                      <VIcon icon="tabler-receipt-off" size="36" color="disabled" class="mb-2 opacity-30" />
                      <p class="text-caption font-weight-bold text-disabled uppercase mb-0">No hay reglas de pronto pago configuradas</p>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </VCard>

            <!-- Callout Estandarizado -->
            <VAlert
              color="primary"
              variant="tonal"
              border="start"
              class="rounded-xl border shadow-none"
              density="comfortable"
            >
              <template #prepend>
                <VIcon icon="tabler-info-circle" size="22" color="primary" />
              </template>
              <div class="text-xs font-weight-black uppercase letter-spacing-1 mb-0.5 text-primary">
                Información de Pronto Pago
              </div>
              <div class="text-caption text-medium-emphasis">
                Define los porcentajes de descuento otorgados por el proveedor según los días de anticipación en el pago. Se calcula sobre el monto neto de la factura.
              </div>
            </VAlert>
          </VWindowItem>

          <!-- TAB 2: MARCAS Y ESCALAS -->
          <VWindowItem :value="1">
            <div class="d-flex align-center justify-space-between mb-3">
              <div class="d-flex align-center gap-2">
                <div class="header-indicator primary shadow-sm" />
                <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">
                  Escalas de Negociación por Laboratorio
                </span>
              </div>
              <VBtn
                prepend-icon="tabler-plus"
                variant="tonal"
                color="primary"
                size="small"
                class="rounded-lg font-weight-black"
                @click="addScaleRule"
              >
                + Nueva Escala
              </VBtn>
            </div>

            <VCard variant="flat" class="bg-white rounded-xl border shadow-sm mb-4 overflow-hidden">
              <VTable class="premium-table">
                <thead>
                  <tr>
                    <th style="inline-size: 32%;">LABORATORIO / MARCA</th>
                    <th style="inline-size: 22%;">TIPO DE ESCALA</th>
                    <th style="inline-size: 24%;">RANGO (MÍN - MÁX)</th>
                    <th style="inline-size: 12%;">% DSCTO</th>
                    <th class="text-center" style="inline-size: 10%;">ACCIONES</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(scale, index) in editableScaleRules" :key="index" class="hover-row">
                    <td>
                      <AppAutocomplete
                        v-model="scale.laboratory"
                        :items="laboratories"
                        item-title="name"
                        return-object
                        density="compact"
                        placeholder="Seleccionar laboratorio..."
                        no-data-text="No hay laboratorios disponibles"
                        hide-details
                      />
                    </td>
                    <td>
                      <AppSelect
                        v-model="scale.scale_type"
                        :items="scaleTypes"
                        item-title="name"
                        return-object
                        density="compact"
                        hide-details
                      />
                    </td>
                    <td>
                      <div class="d-flex align-center gap-1.5">
                        <AppTextField
                          v-model="scale.min"
                          type="number"
                          density="compact"
                          placeholder="Mín"
                          hide-details
                          class="centered-input-field"
                          @focus="$event.target?.select()"
                        />
                        <span class="text-disabled font-weight-black">–</span>
                        <AppTextField
                          v-model="scale.max"
                          type="number"
                          density="compact"
                          placeholder="Máx"
                          hide-details
                          class="centered-input-field"
                          @focus="$event.target?.select()"
                        />
                      </div>
                    </td>
                    <td>
                      <AppTextField
                        v-model="scale.discount_percentage"
                        type="number"
                        density="compact"
                        suffix="%"
                        placeholder="0.00"
                        hide-details
                        class="centered-input-field"
                        @focus="$event.target?.select()"
                      />
                    </td>
                    <td class="text-center">
                      <VBtn
                        icon="tabler-trash"
                        variant="tonal"
                        color="error"
                        size="small"
                        class="rounded-lg"
                        @click="removeScaleRule(index)"
                      />
                    </td>
                  </tr>
                  <tr v-if="editableScaleRules.length === 0">
                    <td colspan="5" class="text-center py-8">
                      <VIcon icon="tabler-chart-arrows-vertical" size="36" color="disabled" class="mb-2 opacity-30" />
                      <p class="text-caption font-weight-bold text-disabled uppercase mb-0">Configura escalas para bonificaciones y descuentos por volumen</p>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </VCard>

            <!-- Callout Estandarizado -->
            <VAlert
              color="primary"
              variant="tonal"
              border="start"
              class="rounded-xl border shadow-none"
              density="comfortable"
            >
              <template #prepend>
                <VIcon icon="tabler-info-circle" size="22" color="primary" />
              </template>
              <div class="text-xs font-weight-black uppercase letter-spacing-1 mb-0.5 text-primary">
                Información de Escalas por Volumen
              </div>
              <div class="text-caption text-medium-emphasis">
                Establece bonificaciones o descuentos comerciales escalonados por rangos de unidades compradas o montos en dólares por laboratorio.
              </div>
            </VAlert>
          </VWindowItem>

          <!-- TAB 3: OTROS DESCUENTOS -->
          <VWindowItem :value="2">
            <div class="d-flex align-center justify-space-between mb-3">
              <div class="d-flex align-center gap-2">
                <div class="header-indicator primary shadow-sm" />
                <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">
                  Descuentos Comerciales Directos
                </span>
              </div>
              <VBtn
                prepend-icon="tabler-plus"
                variant="tonal"
                color="primary"
                size="small"
                class="rounded-lg font-weight-black"
                @click="addDiscount"
              >
                + Nuevo Descuento
              </VBtn>
            </div>

            <VCard variant="flat" class="bg-white rounded-xl border shadow-sm mb-4 overflow-hidden">
              <VTable class="premium-table">
                <thead>
                  <tr>
                    <th style="inline-size: 65%;">IDENTIFICACIÓN DEL DESCUENTO</th>
                    <th style="inline-size: 20%;">% APLICADO</th>
                    <th class="text-center" style="inline-size: 15%;">ACCIONES</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(disc, index) in editableDiscounts" :key="index" class="hover-row">
                    <td>
                      <AppTextField
                        v-model="disc.name"
                        density="compact"
                        placeholder="Ej: Descuento Comercial 2%"
                        hide-details
                        @focus="$event.target?.select()"
                      />
                    </td>
                    <td>
                      <AppTextField
                        v-model="disc.discount_percentage"
                        type="number"
                        density="compact"
                        suffix="%"
                        placeholder="0.00"
                        hide-details
                        class="centered-input-field"
                        @focus="$event.target?.select()"
                      />
                    </td>
                    <td class="text-center">
                      <VBtn
                        icon="tabler-trash"
                        variant="tonal"
                        color="error"
                        size="small"
                        class="rounded-lg"
                        @click="removeDiscount(index)"
                      />
                    </td>
                  </tr>
                  <tr v-if="editableDiscounts.length === 0">
                    <td colspan="3" class="text-center py-8">
                      <VIcon icon="tabler-percentage" size="36" color="disabled" class="mb-2 opacity-30" />
                      <p class="text-caption font-weight-bold text-disabled uppercase mb-0">No hay descuentos comerciales adicionales</p>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </VCard>

            <!-- Callout Estandarizado -->
            <VAlert
              color="primary"
              variant="tonal"
              border="start"
              class="rounded-xl border shadow-none"
              density="comfortable"
            >
              <template #prepend>
                <VIcon icon="tabler-info-circle" size="22" color="primary" />
              </template>
              <div class="text-xs font-weight-black uppercase letter-spacing-1 mb-0.5 text-primary">
                Información de Descuentos Comerciales
              </div>
              <div class="text-caption text-medium-emphasis">
                Estos descuentos son fijos y se aplican a todo el catálogo del proveedor sin condiciones de volumen ni laboratorio.
              </div>
            </VAlert>
          </VWindowItem>

        </VWindow>
      </VCardText>

      <!-- Pie de Diálogo Unificado -->
      <VCardActions class="pa-3 pa-sm-4 bg-white border-t flex-shrink-0">
        <VRow dense class="w-100 ma-0 justify-end" align="center">
          <VCol cols="12" sm="auto" class="d-flex justify-end gap-2">
            <VBtn
              color="secondary"
              variant="outlined"
              height="40"
              class="font-weight-bold rounded-lg px-5"
              @click="close"
            >
              CERRAR
            </VBtn>
            <VBtn
              color="primary"
              variant="flat"
              height="40"
              class="font-weight-black rounded-lg px-6 shadow-primary"
              :loading="loading"
              @click="saveCurrentTab"
            >
              <VIcon start icon="tabler-device-floppy" size="18" />
              GUARDAR CAMBIOS
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient, linear-gradient(135deg, #7A0099, #E20074)) !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary { background-color: rgb(var(--v-theme-primary)); }

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.letter-spacing-1 { letter-spacing: 1px !important; }
.leading-none { line-height: 1 !important; }
.leading-tight { line-height: 1.25 !important; }

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.dialog-content-scroll {
  max-block-size: 60vh;
  overflow-y: auto;
}

.premium-table {
  background: transparent !important;
}

.premium-table :deep(th) {
  background-color: #f8f9fa !important;
  block-size: 40px !important;
  color: #64748b !important;
  font-size: 0.65rem !important;
  font-weight: 800 !important;
  letter-spacing: 0.07em !important;
  text-transform: uppercase;
  border-block-end: 1px solid #e2e8f0 !important;
}

.premium-table :deep(td) {
  block-size: 50px !important;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.06) !important;
  padding-block: 6px !important;
}

.hover-row:hover {
  background-color: rgba(var(--v-theme-primary), 0.02) !important;
}

:deep(.centered-input-field input) {
  text-align: center;
}
</style>
