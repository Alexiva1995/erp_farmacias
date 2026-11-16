<script setup>
import { computed, reactive, ref, watch } from "vue"
import { useBrandingStore } from '@/stores/useBrandingStore'

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
  "save-all",
  "clear-errors"
])

const brandingStore = useBrandingStore()
const isRestaurant = computed(() => brandingStore.isRestaurant)
const activeTab = ref(0)
const internalErrors = ref({})
const dirtySections = reactive({
  payment: false,
  scales: false,
  discounts: false,
  settings: false,
})
let isHydrating = false

// --- ESTADOS LOCALES PARA EDICIÓN ---
const editablePaymentRules = ref([])
const editableDiscounts = ref([])
const editableScaleRules = ref([])
const editableSettings = reactive({
  payment_due_type: 'invoice_date',
  invoice_date_reference: 'issue_date',
  custom_due_days: null,
  payment_due_reference: 'issue_date',
  credit_days: null,
  min_order_amount: null,
  payment_method: 'Bs',
  is_indexed: false,
  dispatch_days: [],
  order_days: {},
})
const tempIdCounter = ref(-1)

const dias = [
  { label: 'Lun', value: 'monday' },
  { label: 'Mar', value: 'tuesday' },
  { label: 'Mié', value: 'wednesday' },
  { label: 'Jue', value: 'thursday' },
  { label: 'Vie', value: 'friday' },
  { label: 'Sáb', value: 'saturday' },
]

const diasFull = {
  monday: 'Lunes',
  tuesday: 'Martes',
  wednesday: 'Miércoles',
  thursday: 'Jueves',
  friday: 'Viernes',
  saturday: 'Sábado',
}

const scaleTypes = [
  { id: 'units', name: 'Por unidades' },
  { id: 'amount', name: 'Por dólares ($)' },
]

// --- WATCHERS PARA SINCRONIZAR PROPS ---
watch(() => props.modelValue, (val) => {
  if (val) {
    syncSettingsData()
    syncRulesData()
    activeTab.value = 0
  }
})

watch(() => props.supplier, () => {
  if (props.modelValue) {
    syncSettingsData()
  }
}, { deep: true })

watch([
  () => props.paymentRules,
  () => props.supplierDiscount,
  () => props.discountRules
], () => {
  if (props.modelValue) {
    syncRulesData()
  }
}, { deep: true })

watch(() => props.errors, (newErrors) => {
  internalErrors.value = { ...newErrors }
}, { deep: true })

watch(editablePaymentRules, () => {
  if (!isHydrating && !props.loading && props.modelValue) dirtySections.payment = true
}, { deep: true })

watch(editableScaleRules, () => {
  if (!isHydrating && !props.loading && props.modelValue) dirtySections.scales = true
}, { deep: true })

watch(editableDiscounts, () => {
  if (!isHydrating && !props.loading && props.modelValue) dirtySections.discounts = true
}, { deep: true })

watch(editableSettings, () => {
  if (!isHydrating && !props.loading && props.modelValue) dirtySections.settings = true
}, { deep: true })

const syncSettingsData = () => {
  isHydrating = true
  const s = props.supplier || {}
  const dispatchDays = Array.isArray(s.dispatch_days) ? [...s.dispatch_days] : []
  const normalizedOrderDays = {}
  dispatchDays.forEach((day) => {
    const old = s.order_days
    normalizedOrderDays[day] = old && !Array.isArray(old) && Array.isArray(old[day]) ? [...old[day]] : []
  })

  editableSettings.payment_due_type = s.payment_due_type || 'invoice_date'
  editableSettings.invoice_date_reference = s.invoice_date_reference || 'issue_date'
  editableSettings.custom_due_days = s.custom_due_days ?? null
  editableSettings.payment_due_reference = s.payment_due_reference || 'issue_date'
  editableSettings.credit_days = s.credit_days ?? null
  editableSettings.min_order_amount = s.min_order_amount ?? null
  editableSettings.payment_method = s.payment_method || 'Bs'
  editableSettings.is_indexed = Boolean(s.is_indexed)
  editableSettings.dispatch_days = dispatchDays
  editableSettings.order_days = normalizedOrderDays

  dirtySections.settings = false
  setTimeout(() => {
    isHydrating = false
  }, 50)
}

const syncRulesData = () => {
  isHydrating = true
  // Pronto Pago
  editablePaymentRules.value = props.paymentRules.map(rule => ({ ...rule, _markedForDeletion: false }))

  // Descuentos Planos
  editableDiscounts.value = props.supplierDiscount.map(d => ({ ...d, _markedNew: false }))

  // Reglas de Escala
  editableScaleRules.value = props.discountRules.map(r => ({ ...r, _markedNew: false }))

  dirtySections.payment = false
  dirtySections.scales = false
  dirtySections.discounts = false

  setTimeout(() => {
    isHydrating = false
  }, 50)
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
const buildFinancesPayload = () => {
  const validRules = editablePaymentRules.value.filter(r => ((r.days !== null && r.days !== '') || Number(r.discount_percentage) > 0))
  return validRules.map(r => ({
    id: r.id > 0 ? r.id : undefined,
    days: Number(r.days) || 0,
    discount_percentage: Number(r.discount_percentage) || 0
  }))
}

const buildBrandsPayload = () => {
  return editableScaleRules.value
    .filter(s => s.laboratory && (s.laboratory.id || typeof s.laboratory === 'number'))
    .map(s => ({
      id: s.id > 0 ? s.id : undefined,
      laboratory: s.laboratory,
      scale_type: s.scale_type,
      min: Number(s.min) || 1,
      max: Number(s.max) || 1,
      discount_percentage: Number(s.discount_percentage) || 0
    }))
}

const buildDiscountsPayload = () => {
  const validDiscounts = editableDiscounts.value.filter(d => (d.name && d.name.trim() !== '') || Number(d.discount_percentage) > 0)
  return validDiscounts.map(d => ({
    id: d.id > 0 ? d.id : undefined,
    name: d.name,
    discount_percentage: Number(d.discount_percentage) || 0
  }))
}

const buildSettingsPayload = () => {
  const payload = {
    payment_due_type: editableSettings.payment_due_type,
    credit_days: editableSettings.credit_days !== '' && editableSettings.credit_days !== null ? Number(editableSettings.credit_days) : null,
    min_order_amount: editableSettings.min_order_amount !== '' && editableSettings.min_order_amount !== null ? Number(editableSettings.min_order_amount) : null,
    payment_method: editableSettings.payment_method,
    is_indexed: Boolean(editableSettings.is_indexed),
    dispatch_days: [...editableSettings.dispatch_days],
    order_days: { ...editableSettings.order_days },
  }

  if (editableSettings.payment_due_type === 'invoice_date') {
    payload.invoice_date_reference = editableSettings.invoice_date_reference
    payload.custom_due_days = null
    payload.payment_due_reference = null
  } else if (editableSettings.payment_due_type === 'early_payment') {
    payload.payment_due_reference = editableSettings.payment_due_reference
    payload.custom_due_days = null
    payload.invoice_date_reference = null
  } else if (editableSettings.payment_due_type === 'custom') {
    payload.custom_due_days = editableSettings.custom_due_days ? Number(editableSettings.custom_due_days) : null
    payload.payment_due_reference = null
    payload.invoice_date_reference = null
  }

  return payload
}

const saveAllChanges = () => {
  const payload = {}

  if (dirtySections.payment || activeTab.value === 0) {
    payload.paymentRules = buildFinancesPayload()
  }
  if (dirtySections.scales || activeTab.value === 1) {
    const scales = buildBrandsPayload()
    if (scales.length > 0) {
      payload.discountRules = scales
    }
  }
  if (dirtySections.discounts || activeTab.value === 2) {
    payload.discounts = buildDiscountsPayload()
  }
  if (dirtySections.settings || activeTab.value === 0 || activeTab.value === 3) {
    payload.supplierSettings = buildSettingsPayload()
  }

  emit('save-all', payload)
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
            <VImg v-if="supplier?.logo_url" :src="supplier.logo_url" cover />
            <VIcon v-else icon="tabler-settings-dollar" color="primary" size="20" />
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
      <VTabs v-model="activeTab" color="primary" grow bg-color="surface" class="border-b">
        <VTab :value="0" class="font-weight-black text-xs uppercase letter-spacing-1">
          <VIcon start>tabler-receipt-2</VIcon>
          Crédito y Pronto Pago
          <VBadge v-if="dirtySections.payment || dirtySections.settings" dot color="warning" inline class="ms-1" />
        </VTab>
        <VTab :value="1" class="font-weight-black text-xs uppercase letter-spacing-1">
          <VIcon start>tabler-building-factory-2</VIcon>
          {{ isRestaurant ? 'Marcas / Líneas y Escalas' : 'Marcas y Escalas' }}
          <VBadge v-if="dirtySections.scales" dot color="warning" inline class="ms-1" />
        </VTab>
        <VTab :value="2" class="font-weight-black text-xs uppercase letter-spacing-1">
          <VIcon start>tabler-percentage</VIcon>
          Otros Descuentos
          <VBadge v-if="dirtySections.discounts" dot color="warning" inline class="ms-1" />
        </VTab>
        <VTab :value="3" class="font-weight-black text-xs uppercase letter-spacing-1">
          <VIcon start>tabler-truck-delivery</VIcon>
          Logística y Despacho
          <VBadge v-if="dirtySections.settings" dot color="warning" inline class="ms-1" />
        </VTab>
      </VTabs>

      <!-- Contenido del Diálogo -->
      <VCardText class="pa-0 dialog-content-scroll">
        <VProgressLinear v-if="loading" indeterminate color="primary" height="3" />
        <VWindow v-model="activeTab" class="pa-4 pa-sm-5 bg-light">

          <!-- TAB 1: CRÉDITO, CONDICIONES FINANCIERAS Y PRONTO PAGO -->
          <VWindowItem :value="0">
            <!-- Condiciones Financieras del Proveedor -->
            <div class="d-flex align-center gap-2 mb-3">
              <div class="header-indicator primary shadow-sm" />
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">
                Condiciones Financieras y Crédito
              </span>
            </div>

            <VCard variant="flat" class="bg-white rounded-xl border shadow-sm pa-4 mb-5">
              <VRow dense class="gy-2">
                <VCol cols="12" sm="6" md="3">
                  <VSelect
                    v-model="editableSettings.payment_due_type"
                    :items="[
                      { title: 'Aprovechar Pronto Pago', value: 'early_payment' },
                      { title: 'Fecha de la Factura', value: 'invoice_date' },
                      { title: 'Plazo Fijo (Días)', value: 'custom' },
                    ]"
                    label="Meta de Pago (Vencimiento)"
                    variant="outlined"
                    density="compact"
                    hide-details="auto"
                  />
                </VCol>

                <VCol cols="12" sm="6" md="3">
                  <VSelect
                    v-if="editableSettings.payment_due_type === 'invoice_date'"
                    v-model="editableSettings.invoice_date_reference"
                    :items="[
                      { title: 'Fecha Emisión', value: 'issue_date' },
                      { title: 'Fecha Recibo', value: 'receipt_date' },
                      { title: 'Fecha Vencimiento', value: 'expiration_date' },
                    ]"
                    label="Referencia de Fecha"
                    variant="outlined"
                    density="compact"
                    hide-details="auto"
                  />
                  <VTextField
                    v-else-if="editableSettings.payment_due_type === 'custom'"
                    v-model.number="editableSettings.custom_due_days"
                    label="Días de Plazo"
                    type="number"
                    variant="outlined"
                    density="compact"
                    hide-details="auto"
                  />
                  <VSelect
                    v-else
                    v-model="editableSettings.payment_due_reference"
                    :items="[
                      { title: 'Fecha Recibo (Llegada)', value: 'receipt_date' },
                      { title: 'Fecha Emisión', value: 'issue_date' },
                    ]"
                    label="Contar pronto pago desde"
                    variant="outlined"
                    density="compact"
                    hide-details="auto"
                  />
                </VCol>

                <VCol cols="12" sm="6" md="3">
                  <VTextField
                    v-model.number="editableSettings.credit_days"
                    label="Días Crédito Habitual"
                    type="number"
                    variant="outlined"
                    density="compact"
                    hide-details="auto"
                  />
                </VCol>

                <VCol cols="12" sm="6" md="3">
                  <VTextField
                    v-model.number="editableSettings.min_order_amount"
                    label="Pedido Mínimo ($)"
                    type="number"
                    step="0.01"
                    min="0"
                    variant="outlined"
                    density="compact"
                    hide-details="auto"
                  />
                </VCol>

                <VCol cols="12" class="d-flex align-center flex-wrap gap-4 pt-2">
                  <div class="d-flex align-center gap-2">
                    <span class="text-xs font-weight-bold text-high-emphasis">Moneda:</span>
                    <VRadioGroup
                      v-model="editableSettings.payment_method"
                      density="compact"
                      hide-details
                      inline
                      @update:model-value="(val) => { if (val === 'Divisas') editableSettings.is_indexed = false }"
                    >
                      <VRadio label="Bs" value="Bs" color="primary" class="me-2 text-xs" />
                      <VRadio label="Divisas ($)" value="Divisas" color="primary" class="text-xs" />
                    </VRadioGroup>
                  </div>
                  <VCheckbox
                    v-if="editableSettings.payment_method !== 'Divisas'"
                    v-model="editableSettings.is_indexed"
                    label="Indexar (USD)"
                    color="primary"
                    hide-details
                    density="compact"
                    class="font-weight-bold text-xs"
                  />
                </VCol>
              </VRow>
            </VCard>

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
                Nueva Regla
              </VBtn>
            </div>

            <VCard variant="flat" class="bg-white rounded-xl border shadow-sm overflow-hidden">
              <VTable class="premium-table">
                <thead>
                  <tr>
                    <th style="inline-size: 48%;">PLAZO DE PAGO (DÍAS — 0 = DÍA DE LLEGADA)</th>
                    <th style="inline-size: 37%;">% DESCUENTO</th>
                    <th class="text-center" style="inline-size: 15%;">ACCIONES</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(rule, index) in editablePaymentRules" :key="index" class="hover-row">
                    <td>
                      <div class="d-flex align-center gap-2">
                        <VTextField
                          v-model.number="rule.days"
                          type="number"
                          min="0"
                          density="compact"
                          variant="outlined"
                          placeholder="0 = Día de llegada"
                          hide-details
                          class="centered-input-field"
                          @focus="$event.target?.select()"
                        />
                        <VChip
                          v-if="rule.days !== null && rule.days !== '' && Number(rule.days) === 0"
                          size="x-small"
                          color="success"
                          variant="tonal"
                          class="font-weight-bold flex-shrink-0"
                        >
                          Día de llegada
                        </VChip>
                      </div>
                    </td>
                    <td>
                      <VTextField
                        v-model.number="rule.discount_percentage"
                        type="number"
                        min="0"
                        max="100"
                        density="compact"
                        variant="outlined"
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
          </VWindowItem>

          <!-- TAB 2: MARCAS Y ESCALAS -->
          <VWindowItem :value="1">
            <div class="d-flex align-center justify-space-between mb-3">
              <div class="d-flex align-center gap-2">
                <div class="header-indicator primary shadow-sm" />
                <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">
                  {{ isRestaurant ? 'Escalas de Negociación por Marca / Línea' : 'Escalas de Negociación por Laboratorio' }}
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
                    <th style="inline-size: 32%;">{{ isRestaurant ? 'MARCA / LÍNEA' : 'LABORATORIO / MARCA' }}</th>
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
                        :placeholder="isRestaurant ? 'Seleccionar marca / línea...' : 'Seleccionar laboratorio...'"
                        :no-data-text="isRestaurant ? 'No hay marcas disponibles' : 'No hay laboratorios disponibles'"
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
                {{ isRestaurant ? 'Información de Escalas por Marca / Volumen' : 'Información de Escalas por Volumen' }}
              </div>
              <div class="text-caption text-medium-emphasis">
                Establece bonificaciones o descuentos comerciales escalonados por rangos de unidades compradas o montos en dólares {{ isRestaurant ? 'por marca o línea de insumos' : 'por laboratorio' }}.
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

          <!-- TAB 4: LOGÍSTICA Y DESPACHO -->
          <VWindowItem :value="3">
            <div class="d-flex flex-column gap-4">
              <!-- Días de Despacho -->
              <VCard variant="flat" class="bg-white rounded-xl border shadow-sm pa-4">
                <div class="d-flex align-center gap-2 mb-1">
                  <VIcon icon="tabler-truck-delivery" color="primary" size="20" />
                  <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">
                    {{ isRestaurant ? 'Días de Despacho (Entrega en Restaurante)' : 'Días de Despacho (Entrega en Farmacia)' }}
                  </span>
                </div>
                <p class="text-xs text-medium-emphasis mb-3">
                  Selecciona los días de la semana en que el proveedor realiza entregas de mercancía en la sucursal.
                </p>

                <div class="d-flex flex-wrap gap-2">
                  <VChip
                    v-for="dia in dias"
                    :key="dia.value"
                    :color="editableSettings.dispatch_days?.includes(dia.value) ? 'primary' : 'default'"
                    :variant="editableSettings.dispatch_days?.includes(dia.value) ? 'flat' : 'outlined'"
                    class="cursor-pointer font-weight-bold px-4 py-2 text-sm"
                    rounded="lg"
                    @click="
                      editableSettings.dispatch_days.includes(dia.value)
                        ? editableSettings.dispatch_days.splice(editableSettings.dispatch_days.indexOf(dia.value), 1)
                        : (editableSettings.dispatch_days.push(dia.value), editableSettings.order_days[dia.value] = editableSettings.order_days[dia.value] || [])
                    "
                  >
                    <VIcon v-if="editableSettings.dispatch_days?.includes(dia.value)" icon="tabler-check" size="16" class="me-1" />
                    {{ dia.label.toUpperCase() }}
                  </VChip>
                </div>
              </VCard>

              <!-- Programación de Pedidos -->
              <VCard v-if="editableSettings.dispatch_days?.length" variant="flat" class="bg-white rounded-xl border shadow-sm pa-4">
                <div class="d-flex align-center gap-2 mb-1">
                  <VIcon icon="tabler-calendar-event" color="primary" size="20" />
                  <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">
                    Días Límite para Transmitir la Orden de Compra
                  </span>
                </div>
                <p class="text-xs text-medium-emphasis mb-3">
                  Para cada día de entrega seleccionado, indica qué días se debe generar y enviar la orden de compra.
                </p>

                <VRow dense class="gy-3">
                  <VCol v-for="diaD in editableSettings.dispatch_days" :key="diaD" cols="12" md="6">
                    <VCard variant="outlined" class="pa-3 rounded-lg">
                      <div class="d-flex align-center justify-space-between mb-2">
                        <div class="d-flex align-center gap-2">
                          <VIcon icon="tabler-truck" size="18" color="primary" />
                          <span class="text-xs font-weight-black text-high-emphasis text-uppercase">
                            Entrega del {{ diasFull[diaD] }}
                          </span>
                        </div>
                        <span class="text-xxs text-medium-emphasis">Hacer pedido el:</span>
                      </div>

                      <div class="d-flex flex-wrap gap-1 mt-2">
                        <VChip
                          v-for="dia in dias"
                          :key="dia.value"
                          :color="editableSettings.order_days[diaD]?.includes(dia.value) ? 'primary' : 'default'"
                          :variant="editableSettings.order_days[diaD]?.includes(dia.value) ? 'flat' : 'outlined'"
                          class="cursor-pointer font-weight-medium"
                          size="small"
                          rounded="md"
                          @click="
                            editableSettings.order_days[diaD]?.includes(dia.value)
                              ? editableSettings.order_days[diaD].splice(editableSettings.order_days[diaD].indexOf(dia.value), 1)
                              : (editableSettings.order_days[diaD] = [...(editableSettings.order_days[diaD] || []), dia.value])
                          "
                        >
                          <VIcon v-if="editableSettings.order_days[diaD]?.includes(dia.value)" icon="tabler-check" size="12" class="me-1" />
                          {{ dia.label }}
                        </VChip>
                      </div>
                    </VCard>
                  </VCol>
                </VRow>
              </VCard>

              <VCard v-else variant="flat" class="bg-white rounded-xl border shadow-sm pa-6 text-center opacity-75">
                <VIcon icon="tabler-calendar-off" size="40" color="disabled" class="mb-2" />
                <div class="text-sm font-weight-bold text-medium-emphasis">Sin días de despacho asignados</div>
                <div class="text-xs text-disabled mt-1">Selecciona arriba los días de entrega del proveedor para programar las órdenes de compra.</div>
              </VCard>
            </div>
          </VWindowItem>

        </VWindow>
      </VCardText>

      <!-- Pie de Diálogo Unificado -->
      <VCardActions class="pa-3 bg-white border-t flex-shrink-0">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="outlined"
              size="large"
              block
              height="44"
              class="font-weight-bold rounded-lg text-button"
              @click="close"
            >
              CERRAR
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              size="large"
              block
              height="44"
              class="font-weight-black rounded-lg shadow-primary text-button"
              :loading="loading"
              :disabled="loading"
              @click="saveAllChanges"
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
  background: var(--brand-gradient, linear-gradient(135deg, rgb(var(--v-theme-secondary)), rgb(var(--v-theme-primary)))) !important;
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
  background-color: rgba(var(--v-theme-on-surface), 0.04) !important;
  block-size: 40px !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.65rem !important;
  font-weight: 800 !important;
  letter-spacing: 0.07em !important;
  text-transform: uppercase;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.12) !important;
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
