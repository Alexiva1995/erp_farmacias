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
    persistent
    @update:model-value="close"
  >
    <VCard class="overflow-hidden">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div 
          class="d-flex align-center pa-4 text-white"
          style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: #fff !important;"
        >
          <VIcon icon="tabler-settings-dollar" class="mr-3" />
          <div>
            <div class="text-h5 font-weight-bold">Panel Comercial</div>
            <div class="text-caption opacity-80">{{ supplier.name }} • RIF: {{ supplier.rif }}</div>
          </div>
          <VSpacer />
          <VBtn icon variant="text" color="white" @click="close">
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VTabs
        v-model="activeTab"
        color="primary"
        grow
        bg-color="transparent"
      >
        <VTab :value="0">
          <VIcon start>tabler-receipt-2</VIcon>
          Finanzas
        </VTab>
        <VTab :value="1">
          <VIcon start>tabler-building-factory-2</VIcon>
          Marcas y Escalas
        </VTab>
        <VTab :value="2">
          <VIcon start>tabler-percentage</VIcon>
          Otros Descuentos
        </VTab>
      </VTabs>

      <VDivider />

      <VCardText class="pa-0" style=" background-color: #f8fafc;block-size: 65vh; overflow-y: auto;">
        <VWindow v-model="activeTab" class="pa-6">
          <!-- TAB 1: FINANZAS (PRONTO PAGO) -->
          <VWindowItem :value="0">
            <VCard variant="flat" class="border rounded-lg mb-6">
              <VCardTitle class="d-flex align-center py-3 px-4 bg-grey-lighten-4">
                <VIcon icon="tabler-coin" size="20" class="me-2 text-primary" />
                <span class="text-subtitle-1 font-weight-bold">Reglas de Pronto Pago</span>
                <VSpacer />
                <VBtn 
                  prepend-icon="tabler-plus" 
                  variant="elevated" 
                  color="primary" 
                  size="small"
                  elevation="1"
                  @click="addPaymentRule"
                >
                  Nueva Regla
                </VBtn>
              </VCardTitle>
              <VDivider />
              
              <VTable class="premium-table">
                <thead>
                  <tr>
                    <th class="text-overline">Días de Anticipación</th>
                    <th class="text-overline">% Descuento</th>
                    <th class="text-center text-overline">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(rule, index) in editablePaymentRules" :key="index" class="hover-row">
                    <td>
                      <AppTextField
                        v-model="rule.days"
                        type="number"
                        density="compact"
                        placeholder="0"
                        hide-details
                        class="centered-input-field"
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
                      />
                    </td>
                    <td class="text-center">
                      <VBtn icon="tabler-trash" variant="text" color="error" size="small" @click="removePaymentRule(index)" />
                    </td>
                  </tr>
                  <tr v-if="editablePaymentRules.length === 0">
                    <td colspan="3" class="text-center py-10">
                      <VIcon icon="tabler-receipt-off" size="40" color="grey-lighten-1" class="mb-2" />
                      <p class="text-body-2 text-grey-darken-1">No hay reglas de pronto pago configuradas</p>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </VCard>

            <VAlert
              color="primary"
              variant="tonal"
              border="start"
              class="rounded-lg"
            >
              <template #prepend>
                <VIcon icon="tabler-info-circle" size="24" />
              </template>
              <div class="text-subtitle-2 font-weight-bold">Información Financiera</div>
              <div class="text-caption">
                Estas reglas definen el porcentaje de descuento que el proveedor otorga si la factura se paga antes de los días indicados. Se calcula sobre el monto neto.
              </div>
            </VAlert>
          </VWindowItem>

          <!-- TAB 2: MARCAS Y ESCALAS -->
          <VWindowItem :value="1">
            <!-- Sección Escalas -->
            <VCard variant="flat" class="border rounded-lg">
              <VCardTitle class="d-flex align-center py-3 px-4 bg-green-lighten-5">
                <VIcon icon="tabler-chart-arrows-vertical" size="20" class="me-2 text-success" />
                <span class="text-subtitle-1 font-weight-bold text-success">Escalas de Negociación</span>
                <VSpacer />
                <VBtn 
                  prepend-icon="tabler-plus" 
                  variant="elevated" 
                  color="success" 
                  size="small"
                  elevation="1"
                  @click="addScaleRule"
                >
                  Nueva Escala
                </VBtn>
              </VCardTitle>
              <VDivider />
              <VTable class="premium-table">
                <thead>
                  <tr>
                    <th class="text-overline">Lab. Objetivo</th>
                    <th class="text-overline">Tipo</th>
                    <th class="text-overline">Rango (Min - Max)</th>
                    <th class="text-overline">% Dscto</th>
                    <th class="text-center text-overline">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(scale, index) in editableScaleRules" :key="index" class="hover-row">
                    <td style="inline-size: 30%;">
                      <AppAutocomplete
                        v-model="scale.laboratory"
                        :items="laboratories"
                        item-title="name"
                        return-object
                        density="compact"
                        hide-details
                      />
                    </td>
                    <td style="inline-size: 20%;">
                      <VChip
                        :color="scale.scale_type?.id === 'units' ? 'success' : 'info'"
                        size="x-small"
                        class="mb-1 d-block text-center font-weight-bold"
                        variant="flat"
                      >
                        {{ scale.scale_type?.id === 'units' ? 'UNIDADES' : 'DÓLARES' }}
                      </VChip>
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
                      <div class="d-flex align-center gap-2">
                        <AppTextField v-model="scale.min" type="number" density="compact" hide-details />
                        <span class="text-grey">-</span>
                        <AppTextField v-model="scale.max" type="number" density="compact" hide-details />
                      </div>
                    </td>
                    <td style="inline-size: 15%;">
                      <AppTextField 
                        v-model="scale.discount_percentage" 
                        type="number" 
                        density="compact" 
                        suffix="%" 
                        hide-details 
                        class="centered-input-field"
                      />
                    </td>
                    <td class="text-center">
                      <VBtn icon="tabler-trash" variant="text" color="error" size="small" @click="removeScaleRule(index)" />
                    </td>
                  </tr>
                  <tr v-if="editableScaleRules.length === 0">
                    <td colspan="5" class="text-center py-8 text-grey">Configura escalas para bonificaciones por volumen</td>
                  </tr>
                </tbody>
              </VTable>
            </VCard>
          </VWindowItem>

          <!-- TAB 3: OTROS DESCUENTOS -->
          <VWindowItem :value="2">
            <VCard variant="flat" class="border rounded-lg mb-6">
              <VCardTitle class="d-flex align-center py-3 px-4 bg-grey-lighten-4">
                <VIcon icon="tabler-brightness-down" size="20" class="me-2 text-primary" />
                <span class="text-subtitle-1 font-weight-bold">Descuentos Indirectos / Campañas</span>
                <VSpacer />
                <VBtn 
                  prepend-icon="tabler-plus" 
                  variant="elevated" 
                  color="primary" 
                  size="small"
                  elevation="1"
                  @click="addDiscount"
                >
                  Añadir
                </VBtn>
              </VCardTitle>
              <VDivider />
            <VTable class="premium-table">
              <thead>
                <tr>
                  <th class="text-overline">Identificación del Descuento</th>
                  <th class="text-overline">% Aplicado</th>
                  <th class="text-center text-overline">Acciones</th>
                </tr>
              </thead>
               <tbody>
                  <tr v-for="(disc, index) in editableDiscounts" :key="index" class="hover-row">
                    <td style="inline-size: 75%;">
                      <AppTextField
                        v-model="disc.name"
                        density="compact"
                        placeholder="Ej: Descuento Comercial 2%"
                        hide-details
                      />
                    </td>
                    <td>
                      <AppTextField
                        v-model="disc.discount_percentage"
                        type="number"
                        density="compact"
                        suffix="%"
                        hide-details
                        class="centered-input-field"
                      />
                    </td>
                    <td class="text-center">
                      <VBtn icon="tabler-trash" variant="text" color="error" size="small" @click="removeDiscount(index)" />
                    </td>
                  </tr>
                  <tr v-if="editableDiscounts.length === 0">
                    <td colspan="3" class="text-center py-8 text-grey">No hay descuentos comerciales adicionales</td>
                  </tr>
                </tbody>
              </VTable>
            </VCard>
            
            <VAlert
              color="info"
              variant="tonal"
              icon="tabler-bulb"
              class="rounded-lg mt-6"
            >
              Estos descuentos son fijos y se aplican a todo el catálogo del proveedor sin condiciones de marca.
            </VAlert>
          </VWindowItem>
        </VWindow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-grey-lighten-4">
        <VBtn variant="text" color="secondary" @click="close">Cerrar</VBtn>
        <VSpacer />
        <VBtn 
          v-if="activeTab === 0" 
          color="primary" 
          variant="flat" 
          prepend-icon="tabler-device-floppy"
          class="px-6"
          :loading="loading"
          @click="saveFinances"
        >
          Guardar Finanzas
        </VBtn>
        <VBtn 
          v-if="activeTab === 1" 
          color="success" 
          variant="flat" 
          prepend-icon="tabler-device-floppy"
          class="px-6"
          :loading="loading"
          @click="saveBrands"
        >
          Guardar Marcas y Escalas
        </VBtn>
        <VBtn 
          v-if="activeTab === 2" 
          color="primary" 
          variant="flat" 
          prepend-icon="tabler-device-floppy"
          class="px-6"
          :loading="loading"
          @click="saveDiscounts"
        >
          Guardar Descuentos
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.premium-table {
  background: transparent !important;
}

.premium-table :deep(th) {
  background-color: #f1f5f9 !important;
  block-size: 48px !important;
  color: #64748b !important;
  font-size: 0.7rem !important;
  letter-spacing: 0.05em !important;
}

.premium-table :deep(td) {
  block-size: 56px !important;
  border-block-end: 1px solid #f1f5f9 !important;
}

.hover-row:hover {
  background-color: #f8fafc;
}

:deep(.centered-input-field input) {
  text-align: center;
}

.bg-grey-lighten-4 {
  background-color: #f8fafc !important;
}

.bg-green-lighten-5 {
  background-color: #f0fdf4 !important;
}
</style>
