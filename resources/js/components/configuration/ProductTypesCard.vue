<script setup>
import { computed } from 'vue'

const props = defineProps({
  enabledProductTypes: {
    type: Array,
    required: true,
  },
  isSaving: {
    type: Boolean,
    default: false,
  },
  canEdit: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['update:enabledProductTypes', 'change'])

const productTypeOptions = [
  { label: 'Redundantes',       value: 'redundantes' },
  { label: 'Origen Colombiano', value: 'col'         },
  { label: 'Con IVA (G)',       value: 'iva'         },
  { label: 'Exento',            value: 'exento'      },
  { label: 'Novaventa',         value: 'novaventa'   },
  { label: 'Eliminados',        value: 'eliminados'  },
  { label: 'PVP',               value: 'pvp'         },
  { label: 'Ingredientes',      value: 'ingredients' },
  { label: 'Mixto',             value: 'mixed'       },
]

const allValues = productTypeOptions.map(item => item.value)
const allSelected = computed(() => allValues.every(val => props.enabledProductTypes.includes(val)))
const noneSelected = computed(() => props.enabledProductTypes.length === 0)

const handleChange = (val) => {
  if (props.isSaving || !props.canEdit) return
  emit('update:enabledProductTypes', val)
  emit('change')
}

const selectAll = () => {
  if (props.isSaving || !props.canEdit) return
  emit('update:enabledProductTypes', [...allValues])
  emit('change')
}

const deselectAll = () => {
  if (props.isSaving || !props.canEdit) return
  emit('update:enabledProductTypes', [])
  emit('change')
}
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm">
    <VCardItem class="py-5">
      <div class="d-flex flex-wrap align-center justify-space-between gap-4 mb-2">
        <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2">
          <VIcon icon="tabler-tags" color="primary" size="28" />
          Tipos de Productos Habilitados
        </VCardTitle>
        
        <!-- Acciones en Lote -->
        <div class="d-flex align-center gap-2">
          <VBtn
            variant="outlined"
            color="primary"
            size="small"
            :disabled="allSelected || isSaving || !canEdit"
            @click="selectAll"
          >
            Habilitar Todos
          </VBtn>
          <VBtn
            variant="outlined"
            color="secondary"
            size="small"
            :disabled="noneSelected || isSaving || !canEdit"
            @click="deselectAll"
          >
            Desmarcar Todos
          </VBtn>
        </div>
      </div>

      <p class="text-caption text-medium-emphasis mb-6">
        Selecciona cuáles clasificaciones estarán visibles en los filtros de búsqueda y catálogo de productos.
      </p>

      <VDivider class="mb-6" />

      <VRow>
        <VCol
          v-for="item in productTypeOptions"
          :key="item.value"
          cols="12"
          sm="6"
          md="4"
        >
          <VCard variant="outlined" class="rounded-lg pa-3 transition-all">
            <VCheckbox
              :model-value="enabledProductTypes"
              :value="item.value"
              :label="item.label"
              color="primary"
              density="comfortable"
              hide-details="auto"
              :disabled="isSaving || !canEdit"
              @update:model-value="handleChange"
            />
          </VCard>
        </VCol>
      </VRow>
    </VCardItem>
  </VCard>
</template>
