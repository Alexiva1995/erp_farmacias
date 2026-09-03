<script setup>
const props = defineProps({
  isDialogVisible: Boolean,
  
  // Filtros del Catálogo
  selectedLaboratory: { type: Array, default: () => [] },
  selectedGroup: { type: Array, default: () => [] },
  selectedOrigin: [Number, String, null],
  selectedSupplier: [Number, String, null],
  
  // Configuraciones Visuales
  enableDiscounts: Boolean,
  enableUsdAmountCol: Boolean,
  enableDiscountCol: Boolean,
  isStrictSearch: Boolean,
  isUpdatingAll: Boolean,

  // Opciones
  laboratories: { type: Array, default: () => [] },
  groups: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'update:selectedLaboratory',
  'update:selectedGroup',
  'update:selectedOrigin',
  'update:selectedSupplier',
  'update:enableDiscounts',
  'update:enableUsdAmountCol',
  'update:enableDiscountCol',
  'update:isStrictSearch',
  'clear',
  'update-all-api'
])

const closeDialog = () => {
  emit('update:isDialogVisible', false)
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="600"
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard title="Filtros del Catálogo de Proveedores">
      <DialogCloseBtn @click="closeDialog" />

      <VCardText>
        <VRow>
          <VCol cols="12">
            <VAutocomplete
              :model-value="props.selectedLaboratory"
              :items="props.laboratories"
              label="Laboratorios"
              item-title="name"
              item-value="id"
              clearable
              chips
              multiple
              closable-chips
              @update:model-value="emit('update:selectedLaboratory', $event)"
            />
          </VCol>

          <VCol cols="12">
            <VAutocomplete
              :model-value="props.selectedGroup"
              :items="props.groups"
              label="Grupos"
              item-title="name"
              item-value="id"
              clearable
              chips
              multiple
              closable-chips
              @update:model-value="emit('update:selectedGroup', $event)"
            />
          </VCol>

          <VCol cols="12" md="6">
            <VSelect
              :model-value="props.selectedOrigin"
              :items="props.origins"
              label="Origen"
              item-title="name"
              item-value="id"
              clearable
              @update:model-value="emit('update:selectedOrigin', $event)"
            />
          </VCol>

          <VCol cols="12" md="6">
            <VAutocomplete
              :model-value="props.selectedSupplier"
              :items="props.suppliers"
              label="Proveedor Específico"
              item-title="name"
              item-value="id"
              clearable
              @update:model-value="emit('update:selectedSupplier', $event)"
            />
          </VCol>

          <VDivider class="my-2 w-100" />

          <VCol cols="12" class="d-flex flex-wrap gap-x-6 gap-y-2">
            <VSwitch
              :model-value="props.enableDiscounts"
              label="Aplicar Descuento"
              color="primary"
              hide-details
              @update:model-value="emit('update:enableDiscounts', $event)"
            />
            <VSwitch
              :model-value="props.enableUsdAmountCol"
              label="Ver Divisas ($)"
              color="success"
              hide-details
              @update:model-value="emit('update:enableUsdAmountCol', $event)"
            />
            <VSwitch
              :model-value="props.enableDiscountCol"
              label="Ver % Desc."
              color="info"
              hide-details
              @update:model-value="emit('update:enableDiscountCol', $event)"
            />
            <VSwitch
              :model-value="props.isStrictSearch"
              label="Búsqueda Estricta"
              color="warning"
              hide-details
              @update:model-value="emit('update:isStrictSearch', $event)"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="pa-4">
        <VBtn
          color="info"
          variant="tonal"
          :prepend-icon="props.isUpdatingAll ? 'tabler-loader-2' : 'tabler-cloud-download'"
          :loading="props.isUpdatingAll"
          :disabled="props.isUpdatingAll"
          @click="emit('update-all-api')"
        >
          {{ props.isUpdatingAll ? 'Sincronizando...' : 'Sincronizar APIs' }}
        </VBtn>

        <VSpacer />
        
        <VBtn
          color="secondary"
          variant="outlined"
          @click="emit('clear')"
        >
          Limpiar
        </VBtn>

        <VBtn
          color="primary"
          @click="closeDialog"
        >
          Aplicar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
