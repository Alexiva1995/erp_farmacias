<script setup>
import { computed, ref, watch } from "vue";
import { formatDate, formatPrice } from "@/utils/formatters";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  loading: { type: Boolean, default: false },
  products: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
  locations: { type: Array, default: () => [] },
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
    max-width="800px"
    @update:model-value="onCancel"
    persistent
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard :loading="props.loading" class="d-flex flex-column">
      <VCardTitle class="d-flex align-center pa-4 pb-3 bg-primary">
        <VIcon 
          :icon="props.isEditing ? 'tabler-edit' : 'tabler-plus'" 
<script setup>
import { computed, ref, watch } from "vue";
import { formatDate, formatPrice } from "@/utils/formatters";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  loading: { type: Boolean, default: false },
  products: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
  locations: { type: Array, default: () => [] },
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
    max-width="800px"
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
                :item-title="(item) => `[${item.id}] ${item.name.toUpperCase()} ${item.laboratory ? ' - ' + item.laboratory.name : ''}`"
                item-value="id"
                placeholder="Busca un producto"
                variant="outlined"
                density="compact"
              >
                <template #item="{ props: itemProps, item }">
                  <VListItem v-bind="itemProps">
                    <template #title>
                      <div class="d-flex flex-column">
                        <span class="font-weight-medium">[{{ item.raw.id }}] {{ item.raw.name.toUpperCase() }}</span>
                        <span class="text-xs text-secondary italic" v-if="item.raw.laboratory">
                          {{ item.raw.laboratory.name }}
                        </span>
                      </div>
                    </template>
                  </VListItem>
                </template>
              </VAutocomplete>
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
                :items="props.locations"
                item-title="name"
                item-value="name"
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
