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
  { id: 'amount', name: 'Por dólares' },
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
  editablePaymentRules.value.push({ id: tempIdCounter.value--, days: 0, discount_percentage: 0, _markedForDeletion: false })
}

const removePaymentRule = (index) => {
  editablePaymentRules.value.splice(index, 1)
}

// --- LÓGICA DE ESCALAS (Integrada en la misma pestaña de marcas) ---
const addScaleRule = () => {
  editableScaleRules.value.push({
    id: tempIdCounter.value--,
    laboratory: { id: null, name: '' },
    scale_type: { id: 'units', name: 'Por unidades' },
    min: 1,
    max: 1,
    discount_percentage: 0,
    _markedNew: true
  })
}

const removeScaleRule = (index) => {
  editableScaleRules.value.splice(index, 1)
}

// --- LÓGICA DE DESCUENTOS ---
const addDiscount = () => {
  editableDiscounts.value.push({ id: tempIdCounter.value--, name: '', discount_percentage: 0, _markedNew: true })
}

const removeDiscount = (index) => {
  editableDiscounts.value.splice(index, 1)
}

// --- GUARDADO POR SECCIÓN ---
const saveFinances = () => {
  const data = editablePaymentRules.value.map(r => ({
    id: r.id > 0 ? r.id : undefined,
    days: r.days,
    discount_percentage: r.discount_percentage
  }))
  emit('save-payment-rules', data)
}

const saveBrands = async () => {
  // Guardamos las escalas (solo las nuevas/editadas)
  const scalesData = editableScaleRules.value.map(s => ({
    id: s.id > 0 ? s.id : undefined,
    laboratory: s.laboratory,
    scale_type: s.scale_type,
    min: s.min,
    max: s.max,
    discount_percentage: s.discount_percentage
  }))
  emit('save-discount-rules', scalesData)
}

const saveDiscounts = () => {
  const data = editableDiscounts.value.map(d => ({
    id: d.id > 0 ? d.id : undefined,
    name: d.name,
    discount_percentage: d.discount_percentage
  }))
  emit('save-discounts', data)
}

const close = () => {
  emit("update:modelValue", false)
  emit("clear-errors")
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="1000px"
    :fullscreen="$vuetify.display.mobile"
    persistent
    @update:model-value="close"
  >
    <VCard class="detail-dialog-card overflow-hidden">
      <!-- Header Premium Institucional -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
            <VIcon icon="tabler-settings-dollar" color="primary" size="22" />
          </VAvatar>
          <div class="d-flex flex-column leading-none text-white">
            <h2 class="text-h6 font-weight-black leading-tight mb-0 uppercase text-white">
              Panel Comercial
            </h2>
            <span class="text-super-xs opacity-75 font-weight-bold uppercase letter-spacing-1">
              {{ supplier.name }} • RIF: {{ supplier.rif }}
            </span>
          </div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="white" size="small" class="rounded-lg" @click="close" />
        </div>
      </VCardTitle>

      <!-- Tabs -->
      <VTabs v-model="activeTab" color="primary" grow bg-color="white">
        <VTab :value="0" class="font-weight-black text-xs uppercase letter-spacing-1">
          <VIcon start>tabler-receipt-2</VIcon>
          Finanzas
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

      <VDivider />

      <VCardText class="pa-0 dialog-content-scroll">
        <VWindow v-model="activeTab" class="pa-4 pa-sm-6 bg-light">

          <!-- TAB 1: FINANZAS (PRONTO PAGO) -->
          <VWindowItem :value="0">
            <div class="d-flex align-center gap-2 mb-3">
              <div class="header-indicator primary shadow-sm" />
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Reglas de Pronto Pago</span>
              <VSpacer />
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

            <VCard variant="flat" class="bg-white rounded-xl border shadow-sm mb-4">
              <VTable class="premium-table">
                <thead>
                  <tr>
                    <th>Días de Anticipación</th>
                    <th>% Descuento</th>
                    <th class="text-center">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(rule, index) in editablePaymentRules" :key="index" class="hover-row">
                    <td>
                      <AppTextField v-model="rule.days" type="number" density="compact" placeholder="0" hide-details class="centered-input-field" />
                    </td>
                    <td>
                      <AppTextField v-model="rule.discount_percentage" type="number" density="compact" suffix="%" placeholder="0.00" hide-details class="centered-input-field" />
                    </td>
                    <td class="text-center">
                      <VBtn icon="tabler-trash" variant="tonal" color="error" size="small" class="rounded-lg" @click="removePaymentRule(index)" />
                    </td>
                  </tr>
                  <tr v-if="editablePaymentRules.length === 0">
                    <td colspan="3" class="text-center py-10">
                      <VIcon icon="tabler-receipt-off" size="40" color="disabled" class="mb-2 opacity-25" />
                      <p class="text-super-xs font-weight-black text-disabled uppercase letter-spacing-1">No hay reglas de pronto pago configuradas</p>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </VCard>

            <VAlert color="primary" variant="tonal" border="start" class="rounded-xl">
              <template #prepend>
                <VIcon icon="tabler-info-circle" size="22" />
              </template>
              <div class="text-xs font-weight-black uppercase letter-spacing-1 mb-1">Información Financiera</div>
              <div class="text-super-xs text-medium-emphasis">
                Estas reglas definen el porcentaje de descuento que el proveedor otorga si la factura se paga antes de los días indicados. Se calcula sobre el monto neto.
              </div>
            </VAlert>
          </VWindowItem>

          <!-- TAB 2: MARCAS Y ESCALAS -->
          <VWindowItem :value="1">
            <div class="d-flex align-center gap-2 mb-3">
              <div class="header-indicator secondary shadow-sm" />
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Escalas de Negociación</span>
              <VSpacer />
              <VBtn
                prepend-icon="tabler-plus"
                variant="tonal"
                color="success"
                size="small"
                class="rounded-lg font-weight-black"
                @click="addScaleRule"
              >
                Nueva Escala
              </VBtn>
            </div>

            <VCard variant="flat" class="bg-white rounded-xl border shadow-sm">
              <VTable class="premium-table">
                <thead>
                  <tr>
                    <th>Lab. Objetivo</th>
                    <th>Tipo</th>
                    <th>Rango (Min - Max)</th>
                    <th>% Dscto</th>
                    <th class="text-center">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(scale, index) in editableScaleRules" :key="index" class="hover-row">
                    <td style="inline-size: 30%;">
                      <AppAutocomplete v-model="scale.laboratory" :items="laboratories" item-title="name" return-object density="compact" hide-details />
                    </td>
                    <td style="inline-size: 20%;">
                      <VChip :color="scale.scale_type?.id === 'units' ? 'success' : 'info'" size="x-small" class="mb-1 d-block text-center font-weight-black rounded-lg" variant="flat">
                        {{ scale.scale_type?.id === 'units' ? 'UNIDADES' : 'DÓLARES' }}
                      </VChip>
                      <AppSelect v-model="scale.scale_type" :items="scaleTypes" item-title="name" return-object density="compact" hide-details />
                    </td>
                    <td>
                      <div class="d-flex align-center gap-2">
                        <AppTextField v-model="scale.min" type="number" density="compact" hide-details />
                        <span class="text-disabled font-weight-black">–</span>
                        <AppTextField v-model="scale.max" type="number" density="compact" hide-details />
                      </div>
                    </td>
                    <td style="inline-size: 15%;">
                      <AppTextField v-model="scale.discount_percentage" type="number" density="compact" suffix="%" hide-details class="centered-input-field" />
                    </td>
                    <td class="text-center">
                      <VBtn icon="tabler-trash" variant="tonal" color="error" size="small" class="rounded-lg" @click="removeScaleRule(index)" />
                    </td>
                  </tr>
                  <tr v-if="editableScaleRules.length === 0">
                    <td colspan="5" class="text-center py-10">
                      <VIcon icon="tabler-chart-arrows-vertical" size="40" color="disabled" class="mb-2 opacity-25" />
                      <p class="text-super-xs font-weight-black text-disabled uppercase letter-spacing-1">Configura escalas para bonificaciones por volumen</p>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </VCard>
          </VWindowItem>

          <!-- TAB 3: OTROS DESCUENTOS -->
          <VWindowItem :value="2">
            <div class="d-flex align-center gap-2 mb-3">
              <div class="header-indicator primary shadow-sm" />
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Descuentos Indirectos / Campañas</span>
              <VSpacer />
              <VBtn
                prepend-icon="tabler-plus"
                variant="tonal"
                color="primary"
                size="small"
                class="rounded-lg font-weight-black"
                @click="addDiscount"
              >
                Añadir
              </VBtn>
            </div>

            <VCard variant="flat" class="bg-white rounded-xl border shadow-sm mb-4">
              <VTable class="premium-table">
                <thead>
                  <tr>
                    <th>Identificación del Descuento</th>
                    <th>% Aplicado</th>
                    <th class="text-center">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(disc, index) in editableDiscounts" :key="index" class="hover-row">
                    <td style="inline-size: 75%;">
                      <AppTextField v-model="disc.name" density="compact" placeholder="Ej: Descuento Comercial 2%" hide-details />
                    </td>
                    <td>
                      <AppTextField v-model="disc.discount_percentage" type="number" density="compact" suffix="%" hide-details class="centered-input-field" />
                    </td>
                    <td class="text-center">
                      <VBtn icon="tabler-trash" variant="tonal" color="error" size="small" class="rounded-lg" @click="removeDiscount(index)" />
                    </td>
                  </tr>
                  <tr v-if="editableDiscounts.length === 0">
                    <td colspan="3" class="text-center py-10">
                      <VIcon icon="tabler-percentage" size="40" color="disabled" class="mb-2 opacity-25" />
                      <p class="text-super-xs font-weight-black text-disabled uppercase letter-spacing-1">No hay descuentos comerciales adicionales</p>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </VCard>

            <VAlert color="info" variant="tonal" icon="tabler-bulb" class="rounded-xl">
              <div class="text-super-xs">Estos descuentos son fijos y se aplican a todo el catálogo del proveedor sin condiciones de marca.</div>
            </VAlert>
          </VWindowItem>

        </VWindow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 pa-sm-6 bg-white border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
              class="font-weight-black rounded-lg uppercase"
              @click="close"
            >
              Cerrar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              v-if="activeTab === 0"
              color="primary"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary uppercase"
              :loading="loading"
              @click="saveFinances"
            >
              <VIcon start icon="tabler-device-floppy" size="18" />
              Guardar Finanzas
            </VBtn>
            <VBtn
              v-if="activeTab === 1"
              color="success"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-success uppercase"
              :loading="loading"
              @click="saveBrands"
            >
              <VIcon start icon="tabler-device-floppy" size="18" />
              Guardar Escalas
            </VBtn>
            <VBtn
              v-if="activeTab === 2"
              color="primary"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary uppercase"
              :loading="loading"
              @click="saveDiscounts"
            >
              <VIcon start icon="tabler-device-floppy" size="18" />
              Guardar Descuentos
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient) !important;
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
.header-indicator.secondary { background-color: rgb(var(--v-theme-secondary)); }

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.shadow-success {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-success), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
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
  background-color: #f1f5f9 !important;
  block-size: 44px !important;
  color: #64748b !important;
  font-size: 0.65rem !important;
  font-weight: 800 !important;
  letter-spacing: 0.07em !important;
  text-transform: uppercase;
  border-block-end: 2px solid #e2e8f0 !important;
}

.premium-table :deep(td) {
  block-size: 54px !important;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.06) !important;
  padding-block: 8px !important;
}

.hover-row:hover {
  background-color: rgba(var(--v-theme-primary), 0.02) !important;
}

:deep(.centered-input-field input) {
  text-align: center;
}
</style>
