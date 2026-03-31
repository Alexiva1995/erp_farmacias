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
    <VCard :loading="props.loading" class="detail-dialog-card overflow-hidden border-0 elevation-12">
      <!-- Cabecera Compacta Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-3 d-flex align-center shadow-sm">
          <div class="d-flex align-center">
            <VAvatar
              color="white"
              variant="flat"
              size="32"
              class="me-3 elevation-1"
            >
              <VIcon
                :icon="props.isEditing ? 'tabler-edit' : 'tabler-circle-plus'"
                color="primary"
                size="18"
              />
            </VAvatar>
            <div>
              <h2 class="text-subtitle-2 font-weight-black text-white leading-tight mb-0">
                {{ dialogTitle }}
              </h2>
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.6rem">
                {{ props.isEditing ? "Modificación" : "Registro" }} de lote
              </span>
            </div>
          </div>

          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            color="white"
            size="x-small"
            @click="onCancel"
          >
            <VIcon size="18">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-4 pa-sm-6 bg-light d-flex flex-column gap-6 overflow-y-auto" style="max-height: 80vh;">
        <VContainer class="pa-0">
          <VRow>
            <VCol cols="12">
              <VCard variant="flat" class="border pa-4 bg-white elevation-1 rounded-lg mb-4">
                <div class="d-flex align-center gap-2 mb-4">
                  <div class="header-indicator primary"></div>
                  <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Producto Vinculado</span>
                </div>
                
                <AppAutocomplete
                  v-if="!props.isEditing"
                  v-model="lotData.product_id"
                  label="Seleccionar Producto"
                  :items="props.products"
                  :item-title="(item) => `[${item.id}] ${item.name.toUpperCase()} ${item.laboratory ? ' - ' + item.laboratory.name : ''}`"
                  item-value="id"
                  placeholder="Busca un producto"
                  variant="outlined"
                  density="comfortable"
                  class="shadow-sm"
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
                </AppAutocomplete>
                <AppTextField
                  v-else
                  :model-value="productName"
                  label="Producto"
                  readonly
                  variant="outlined"
                  density="comfortable"
                  class="shadow-sm bg-grey-lighten-4"
                />
              </VCard>
            </VCol>

            <VCol cols="12">
              <VCard variant="flat" class="border pa-4 bg-white elevation-1 rounded-lg">
                <div class="d-flex align-center gap-2 mb-4">
                  <div class="header-indicator secondary"></div>
                  <span class="text-xs font-weight-black text-secondary uppercase letter-spacing-1">Detalles del Lote</span>
                </div>

                <VRow dense>
                  <VCol cols="12" sm="6">
                    <AppTextField 
                      v-model="lotData.lot_number" 
                      label="Número de Lote" 
                      variant="outlined"
                      density="comfortable"
                      placeholder="Ej: L12345"
                      class="shadow-sm"
                    />
                  </VCol>

                  <VCol cols="12" sm="6">
                    <AppTextField
                      v-model="lotData.expiration_date"
                      label="Fecha de Vencimiento"
                      type="date"
                      placeholder="YYYY-MM-DD"
                      variant="outlined"
                      density="comfortable"
                      class="shadow-sm"
                    />
                  </VCol>

                  <VCol cols="12" sm="6">
                    <AppTextField
                      v-model="lotData.quantity"
                      label="Cantidad"
                      type="number"
                      variant="outlined"
                      density="comfortable"
                      placeholder="0"
                      :disabled="props.isEditing"
                      class="shadow-sm"
                    />
                  </VCol>

                  <VCol cols="12" sm="6">
                    <AppTextField
                      v-model="lotData.unit_cost"
                      label="Precio de Costo"
                      type="number"
                      prefix="$"
                      variant="outlined"
                      density="comfortable"
                      placeholder="0.00"
                      :disabled="props.isEditing"
                      class="shadow-sm"
                    />
                  </VCol>

                  <VCol cols="12" sm="6">
                    <AppAutocomplete
                      v-model="lotData.location"
                      label="Ubicación"
                      :items="props.locations"
                      item-title="name"
                      item-value="name"
                      placeholder="Busca una ubicación"
                      variant="outlined"
                      density="comfortable"
                      clearable
                      class="shadow-sm"
                    />
                  </VCol>

                  <VCol cols="12" sm="6">
                    <AppAutocomplete
                      v-model="lotData.supplier_id"
                      label="Proveedor"
                      :items="props.suppliers"
                      item-title="name"
                      item-value="id"
                      placeholder="Busca un proveedor"
                      variant="outlined"
                      density="comfortable"
                      clearable
                      class="shadow-sm"
                    />
                  </VCol>
                </VRow>
              </VCard>
            </VCol>
          </VRow>
        </VContainer>
      </VCardText>

      <VDivider />
      
      <VCardActions class="pa-4 bg-light border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn 
              color="secondary" 
              variant="tonal" 
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg text-button uppercase"
              @click="onCancel" 
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn 
              color="primary" 
              variant="flat" 
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              @click="onSave" 
            >
              {{ props.isEditing ? "Actualizar Lote" : "Guardar Lote" }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e5128 100%);
}

.bg-light {
  background-color: #f8fafc !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.header-indicator.secondary {
  background-color: rgb(var(--v-theme-secondary));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
