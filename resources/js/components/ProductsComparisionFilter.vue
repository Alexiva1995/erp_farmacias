<script setup>
const props = defineProps({
  selectedSupplier: [Number, String, null],
  suppliers: { type: Array, default: () => [] },
  enableDiscounts: { type: Boolean, default: false },
  enablePaymentRules: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:selectedSupplier", "update:enableDiscounts", "update:enablePaymentRules", "clear"]);
</script>

<template>
  <VCard title="Filtros" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="4">
          <VAutocomplete
            :model-value="props.selectedSupplier"
            :items="props.suppliers"
            :loading="props.loading"
            label="Proveedor"
            placeholder="Escribe para buscar un proveedor"
            item-title="name"
            item-value="id"
            clearable
            @update:model-value="emit('update:selectedSupplier', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="4">
          <VSwitch
            :model-value="props.enableDiscounts"
            label="Activar Descuentos"
            :inset="true"
            @update:model-value="emit('update:enableDiscounts', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="4">
          <VSwitch
            :model-value="props.enablePaymentRules"
            label="Activar Pronto Pago"
            :inset="true"
            @update:model-value="emit('update:enablePaymentRules', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')"> Limpiar Filtros </VBtn>
    </VCardActions>
  </VCard>
</template>
