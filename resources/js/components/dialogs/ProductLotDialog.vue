<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  loading: { type: Boolean, default: false },
  products: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
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
const locations = [
  "E-001",
  "E-002",
  "E-003",
  "E-004",
  "E-005",
  "E-006",
  "E-007",
  "E-008",
  "E-009",
  "E-010",
  "G-001",
  "G-002",
  "G-003",
  "G-004",
  "G-005",
  "G-006",
  "G-007",
  "G-008",
  "G-009",
  "G-010",
  "I-001",
  "I-002",
  "I-003",
  "I-004",
  "I-005",
  "I-006",
  "I-007",
  "I-008",
  "I-009",
  "I-010",
  "N-001",
  "N-002",
  "P-001",
  "P-002",
  "P-003",
  "P-004",
  "P-005",
  "P-006",
  "P-007",
  "P-008",
  "P-009",
  "P-010",
  "D-001",
  "D-002",
  "D-003",
  "D-004",
  "D-005",
  "D-006",
  "D-007",
  "D-008",
  "D-009",
  "D-010",
].sort();
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="600px"
    @update:model-value="onCancel"
    persistent
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard :loading="props.loading" class="d-flex flex-column">
      <VCardTitle class="d-flex align-center pa-4 pb-3 bg-primary">
        <VIcon 
          :icon="props.isEditing ? 'tabler-edit' : 'tabler-plus'" 
          size="24" 
          color="white" 
          class="me-2" 
        />
        <span class="text-h5 font-weight-bold text-white">{{ dialogTitle }}</span>
        <VSpacer />
        <VBtn icon variant="text" color="white" size="small" @click="onCancel">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VCardText class="flex-grow-1 pa-4" style="overflow-y: auto;">
        <VContainer class="pa-0">
          <VRow>
            <VCol cols="12">
              <VAutocomplete
                v-if="!props.isEditing"
                v-model="lotData.product_id"
                label="Seleccionar Producto"
                :items="props.products"
                :item-title="(item) => item.id + ' - ' + item.name"
                item-value="id"
                placeholder="Busca un producto"
                variant="outlined"
                density="compact"
              />
              <VTextField
                v-else
                :model-value="productName"
                label="Producto"
                readonly
                variant="outlined"
                density="compact"
                bg-color="grey-lighten-5"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField 
                v-model="lotData.lot_number" 
                label="Número de Lote" 
                variant="outlined"
                density="compact"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField
                v-model="lotData.expiration_date"
                label="Fecha de Vencimiento"
                type="date"
                placeholder="YYYY-MM-DD"
                variant="outlined"
                density="compact"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField
                v-model="lotData.quantity"
                label="Cantidad"
                type="number"
                variant="outlined"
                density="compact"
                :disabled="props.isEditing"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField
                v-model="lotData.unit_cost"
                label="Precio de Costo"
                type="number"
                prefix="$"
                variant="outlined"
                density="compact"
                :disabled="props.isEditing"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VAutocomplete
                v-model="lotData.location"
                label="Ubicación (Opcional)"
                :items="locations"
                placeholder="Busca un ubicación"
                variant="outlined"
                density="compact"
                clearable
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
                variant="outlined"
                density="compact"
                clearable
              />
            </VCol>
          </VRow>
        </VContainer>
      </VCardText>

      <VDivider />
      <VCardActions class="pa-4 d-flex gap-2">
        <VBtn 
          color="secondary" 
          variant="outlined" 
          @click="onCancel" 
          class="flex-grow-1"
          style="flex: 1 1 50%; max-inline-size: 50%;"
        >
          Cancelar
        </VBtn>
        <VBtn 
          color="primary" 
          variant="flat" 
          @click="onSave" 
          class="flex-grow-1"
          style="flex: 1 1 50%; max-inline-size: 50%;"
        >
          {{ props.isEditing ? "Actualizar Lote" : "Guardar Lote" }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
