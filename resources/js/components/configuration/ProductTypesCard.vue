<script setup>
const props = defineProps({
  enabledProductTypes: {
    type: Array,
    required: true
  },
  isSaving: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:enabledProductTypes', 'change'])

const productTypeOptions = [
  { label: "Redundantes",       value: "redundantes" },
  { label: "Origen Colombiano", value: "col"         },
  { label: "Con IVA (G)",       value: "iva"         },
  { label: "Exento",            value: "exento"      },
  { label: "Novaventa",         value: "novaventa"   },
  { label: "Eliminados",        value: "eliminados"  },
  { label: "PVP",               value: "pvp"         },
  { label: "Ingredientes",      value: "ingredients" },
  { label: "Mixto",             value: "mixed"       },
]

const handleChange = (val) => {
  if (props.isSaving) return
  emit('update:enabledProductTypes', val)
  emit('change')
}
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm">
    <VCardItem class="py-5">
      <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2 mb-2">
        <VIcon icon="tabler-tags" color="primary" size="28" />
        Tipos de Productos Habilitados
      </VCardTitle>
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
          <VCard variant="outlined" class="rounded-lg pa-3 transition-all border-color-light">
            <VCheckbox
              :model-value="enabledProductTypes"
              :value="item.value"
              :label="item.label"
              color="primary"
              density="compact"
              hide-details
              :disabled="isSaving"
              @update:model-value="handleChange"
            />
          </VCard>
        </VCol>
      </VRow>
    </VCardItem>
  </VCard>
</template>

<style scoped>
.border-color-light {
  border-color: rgba(var(--v-border-color), var(--v-border-opacity)) !important;
}
</style>
