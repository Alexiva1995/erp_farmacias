<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  loading: { type: Boolean, default: false },
  products: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
  isEditing: { type: Boolean, default: false },
  lotToEdit: { type: Object, default: null },
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

const lotData = ref({ ...defaultLot });

const dialogTitle = computed(() => {
  return props.isEditing ? "Editar Lote" : "Crear Nuevo Lote";
});

const productName = computed(() => {
  if (props.isEditing && props.lotToEdit?.product) {
    return props.lotToEdit.product.name;
  }
  return "";
});

watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible) {
      if (props.isEditing && props.lotToEdit) {
        lotData.value = {
          id: props.lotToEdit.id,
          product_id: props.lotToEdit.product_id,
          supplier_id: props.lotToEdit.supplier_id || null,
          lot_number: props.lotToEdit.lot_number || "",
          quantity: props.lotToEdit.quantity,
          unit_cost: props.lotToEdit.unit_cost || null,
          expiration_date: formatDateForInput(props.lotToEdit.expiration_date),
          location: props.lotToEdit.location || "",
        };
      } else {
        lotData.value = { ...defaultLot };
      }
    }
  },
  { immediate: true }
);

const formatDateForInput = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

const onSave = () => emit("save", lotData.value);
const onCancel = () => emit("update:modelValue", false);
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="600px"
    @update:model-value="onCancel"
    persistent
  >
    <VCard :loading="props.loading" :title="dialogTitle">
      <VCardText>
        <VContainer>
          <VRow>
            <VCol cols="12">
              <VAutocomplete
                v-if="!props.isEditing"
                v-model="lotData.product_id"
                label="Seleccionar Producto"
                :items="props.products"
                item-title="name"
                item-value="id"
                placeholder="Busca un producto"
              />
              <VTextField
                v-else
                :model-value="productName"
                label="Producto"
                readonly
                variant="outlined"
                bg-color="grey-lighten-5"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField v-model="lotData.lot_number" label="Número de Lote" />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField
                v-model="lotData.expiration_date"
                label="Fecha de Vencimiento"
                type="date"
                placeholder="YYYY-MM-DD"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField
                v-model="lotData.quantity"
                label="Cantidad"
                type="number"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField
                v-model="lotData.unit_cost"
                label="Precio de Costo"
                type="number"
                prefix="$"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField
                v-model="lotData.location"
                label="Ubicación (Opcional)"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VAutocomplete
                v-model="lotData.supplier_id"
                label="Proveedor (Opcional)"
                :items="props.suppliers"
                item-title="name"
                item-value="id"
                placeholder="Busca un proveedor"
                clearable
              />
            </VCol>
          </VRow>
        </VContainer>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn color="secondary" variant="outlined" @click="onCancel">
          Cancelar
        </VBtn>
        <VBtn color="primary" variant="flat" @click="onSave">
          {{ props.isEditing ? "Actualizar Lote" : "Guardar Lote" }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
