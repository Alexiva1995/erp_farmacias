<script setup>
import { ref, watch } from "vue"
import { useTheme } from 'vuetify'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  supplier: { type: Object, default: () => ({}) },
  laboratories: { type: Array, default: () => [] },
  laboratoryLinks: { type: Array, default: () => [] },
  paymentRules: { type: Array, default: () => [] },
  supplierDiscount: { type: Array, default: () => [] },
  discountRules: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits([
  "update:modelValue", 
  "save-payment-rules", 
  "save-laboratories", 
  "save-discounts", 
  "save-discount-rules",
  "clear-errors"
])

const theme = useTheme()
const activeTab = ref(0)
const internalErrors = ref({})

// --- ESTADOS LOCALES PARA EDICIÓN ---
const editablePaymentRules = ref([])
const editableLaboratories = ref([])
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
  () => props.laboratoryLinks,
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
  
  // Laboratorios
  editableLaboratories.value = props.laboratoryLinks.map(link => ({
    ...link,
    laboratory: link.laboratory ? { ...link.laboratory } : { id: null, name: null },
    _markedForEdit: false
  }))

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

// --- LÓGICA DE LABORATORIOS ---
const addLaboratory = () => {
  editableLaboratories.value.push({
    id: tempIdCounter.value--,
    phone: '',
    laboratory: { id: null, name: '' },
    _markedForEdit: true
  })
}

const removeLaboratory = (index) => {
  editableLaboratories.value.splice(index, 1)
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
  // Primero guardamos laboratorios vinculados
  const labsData = editableLaboratories.value.map(l => ({
    id: l.id > 0 ? l.id : undefined,
    phone: l.phone,
    laboratory: l.laboratory
  }))
  emit('save-laboratories', labsData)

  // Luego guardamos las escalas (solo las nuevas/editadas)
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

      <VCardText class="pa-4" style="block-size: 60vh; overflow-y: auto;">
        <VWindow v-model="activeTab">
          <!-- TAV 1: FINANZAS (PRONTO PAGO) -->
          <VWindowItem :value="0">
            <div class="d-flex align-center mb-4">
              <h3 class="text-h6 font-weight-bold">Reglas de Pronto Pago</h3>
              <VSpacer />
              <VBtn 
                prepend-icon="tabler-plus" 
                variant="tonal" 
                color="primary" 
                size="small"
                @click="addPaymentRule"
              >
                Agregar Regla
              </VBtn>
            </div>
            
            <VTable density="compact" class="border rounded-lg">
              <thead>
                <tr>
                  <th>Días de Anticipación</th>
                  <th>% Descuento</th>
                  <th class="text-center">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(rule, index) in editablePaymentRules" :key="index">
                  <td>
                    <VTextField
                      v-model="rule.days"
                      type="number"
                      variant="plain"
                      density="compact"
                      hide-details
                      class="centered-input"
                    />
                  </td>
                  <td>
                    <VTextField
                      v-model="rule.discount_percentage"
                      type="number"
                      variant="plain"
                      density="compact"
                      suffix="%"
                      hide-details
                      class="centered-input"
                    />
                  </td>
                  <td class="text-center">
                    <VBtn icon="tabler-trash" variant="text" color="error" size="small" @click="removePaymentRule(index)" />
                  </td>
                </tr>
                <tr v-if="editablePaymentRules.length === 0">
                  <td colspan="3" class="text-center py-4 text-grey">No hay reglas de pronto pago configuradas</td>
                </tr>
              </tbody>
            </VTable>

            <div class="mt-6 pa-4 bg-blue-lighten-5 rounded-lg border-s-lg border-primary">
              <div class="d-flex align-center">
                <VIcon icon="tabler-info-circle" color="primary" class="mr-2" />
                <span class="text-subtitle-2 text-primary font-weight-bold">Nota Financiera</span>
              </div>
              <p class="text-caption mt-1">
                El pronto pago es un descuento adicional aplicado sobre el total facturado si se liquida antes del plazo establecido.
              </p>
            </div>
          </VWindowItem>

          <!-- TAB 2: MARCAS Y ESCALAS -->
          <VWindowItem :value="1">
            <div class="mb-8">
              <div class="d-flex align-center mb-4">
                <h3 class="text-h6 font-weight-bold">Vínculos de Laboratorio</h3>
                <VSpacer />
                <VBtn 
                  prepend-icon="tabler-link" 
                  variant="tonal" 
                  color="primary" 
                  size="small"
                  @click="addLaboratory"
                >
                  Vincular Lab
                </VBtn>
              </div>
              <VTable density="compact" class="border rounded-lg">
                <thead>
                  <tr>
                    <th>Laboratorio</th>
                    <th>Tel. Representante</th>
                    <th class="text-center">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(lab, index) in editableLaboratories" :key="index">
                    <td style="inline-size: 50%;">
                      <VAutocomplete
                        v-model="lab.laboratory"
                        :items="laboratories"
                        item-title="name"
                        return-object
                        variant="plain"
                        density="compact"
                        hide-details
                        placeholder="Buscar laboratorio..."
                      />
                    </td>
                    <td>
                      <VTextField
                        v-model="lab.phone"
                        variant="plain"
                        density="compact"
                        hide-details
                        placeholder="Ej: 0412..."
                      />
                    </td>
                    <td class="text-center">
                      <VBtn icon="tabler-trash" variant="text" color="error" size="small" @click="removeLaboratory(index)" />
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>

            <VDivider class="mb-6" />

            <div>
              <div class="d-flex align-center mb-4">
                <h3 class="text-h6 font-weight-bold">Escalas de Descuento (Volumen)</h3>
                <VSpacer />
                <VBtn 
                  prepend-icon="tabler-chart-arrows" 
                  variant="tonal" 
                  color="success" 
                  size="small"
                  @click="addScaleRule"
                >
                  Añadir Escala
                </VBtn>
              </div>
              <VTable density="compact" class="border rounded-lg">
                <thead>
                  <tr>
                    <th>Laboratorio</th>
                    <th>Tipo</th>
                    <th>Min</th>
                    <th>Max</th>
                    <th>Dscto %</th>
                    <th class="text-center">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(scale, index) in editableScaleRules" :key="index">
                    <td style="inline-size: 30%;">
                      <VAutocomplete
                        v-model="scale.laboratory"
                        :items="laboratories"
                        item-title="name"
                        return-object
                        variant="plain"
                        density="compact"
                        hide-details
                      />
                    </td>
                    <td>
                      <VSelect
                        v-model="scale.scale_type"
                        :items="scaleTypes"
                        item-title="name"
                        return-object
                        variant="plain"
                        density="compact"
                        hide-details
                      />
                    </td>
                    <td>
                      <VTextField v-model="scale.min" type="number" variant="plain" density="compact" hide-details />
                    </td>
                    <td>
                      <VTextField v-model="scale.max" type="number" variant="plain" density="compact" hide-details />
                    </td>
                    <td>
                      <VTextField v-model="scale.discount_percentage" type="number" variant="plain" density="compact" suffix="%" hide-details />
                    </td>
                    <td class="text-center">
                      <VBtn icon="tabler-trash" variant="text" color="error" size="small" @click="removeScaleRule(index)" />
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>
          </VWindowItem>

          <!-- TAB 3: OTROS DESCUENTOS -->
          <VWindowItem :value="2">
            <div class="d-flex align-center mb-4">
              <h3 class="text-h6 font-weight-bold">Descuentos Comerciales del Proveedor</h3>
              <VSpacer />
              <VBtn 
                prepend-icon="tabler-plus" 
                variant="tonal" 
                color="primary" 
                size="small"
                @click="addDiscount"
              >
                Agregar Descuento
              </VBtn>
            </div>
            <p class="text-caption mb-4 text-grey-darken-1">
              Estos descuentos aplican de forma plana a todos los productos del proveedor, independientemente de la marca o volumen.
            </p>
            <VTable density="compact" class="border rounded-lg">
              <thead>
                <tr>
                  <th>Nombre / Campaña</th>
                  <th>% Descuento</th>
                  <th class="text-center">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(disc, index) in editableDiscounts" :key="index">
                  <td style="inline-size: 70%;">
                    <VTextField
                      v-model="disc.name"
                      variant="plain"
                      density="compact"
                      hide-details
                      placeholder="Ej: Descuento de Temporada"
                    />
                  </td>
                  <td>
                    <VTextField
                      v-model="disc.discount_percentage"
                      type="number"
                      variant="plain"
                      density="compact"
                      suffix="%"
                      hide-details
                    />
                  </td>
                  <td class="text-center">
                    <VBtn icon="tabler-trash" variant="text" color="error" size="small" @click="removeDiscount(index)" />
                  </td>
                </tr>
              </tbody>
            </VTable>
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
.v-table {
  background: transparent !important;
}

.centered-input :deep(input) {
  text-align: center;
}

.bg-grey-lighten-4 {
  background-color: #f8fafc !important;
}
</style>
