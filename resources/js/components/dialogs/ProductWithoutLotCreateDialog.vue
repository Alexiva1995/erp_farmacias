<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  loading: { type: Boolean, default: false },
  products: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
});

const emit = defineEmits(["update:modelValue", "save"]);

const defaultLot = {
  product_id: null,
  supplier_id: null,
  lot_number: "",
  quantity: null,
  unit_cost: null,
  expiration_date: "",
  location: "",
};
const newLot = ref({ ...defaultLot });

watch(
  () => props.modelValue,
  (isVisible) => {
    if (!isVisible) {
      newLot.value = { ...defaultLot };
    }
  }
);

const onSave = () => emit("save", newLot.value);
const onCancel = () => emit("update:modelValue", false);
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="600px"
    @update:model-value="onCancel"
    persistent
  >
    <VCard :loading="props.loading" title="Crear Nuevo Lote">
      <VCardText>
        <VContainer>
          <VRow>
            <VCol cols="12"
              ><VAutocomplete
                v-model="newLot.product_id"
                label="Seleccionar Producto"
                :items="props.products"
                item-title="name"
                item-value="id"
                placeholder="Busca un producto"
            /></VCol>
            <VCol cols="12" sm="6"
              ><VTextField v-model="newLot.lot_number" label="Número de Lote"
            /></VCol>
            <VCol cols="12" sm="6"
              ><VTextField
                v-model="newLot.expiration_date"
                label="Fecha de Vencimiento"
                type="date"
                placeholder="YYYY-MM-DD"
            /></VCol>
            <VCol cols="12" sm="6"
              ><VTextField
                v-model="newLot.quantity"
                label="Cantidad"
                type="number"
            /></VCol>
            <VCol cols="12" sm="6"
              ><VTextField
                v-model="newLot.unit_cost"
                label="Precio de Costo"
                type="number"
                prefix="$"
            /></VCol>
            <VCol cols="12" sm="6"
              ><VTextField
                v-model="newLot.location"
                label="Ubicación (Opcional)"
            /></VCol>
            <VCol cols="12" sm="6"
              ><VAutocomplete
                v-model="newLot.supplier_id"
                label="Proveedor (Opcional)"
                :items="props.suppliers"
                item-title="name"
                item-value="id"
                placeholder="Busca un proveedor"
            /></VCol>
          </VRow>
        </VContainer>
      </VCardText>
      <VCardActions>
        <VSpacer /><VBtn color="secondary" variant="outlined" @click="onCancel"
          >Cancelar</VBtn
        ><VBtn color="primary" variant="flat" @click="onSave"
          >Guardar Lote</VBtn
        >
      </VCardActions>
    </VCard>
  </VDialog>
</template>
