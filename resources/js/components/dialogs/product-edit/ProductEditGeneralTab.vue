<script setup>
import { computed, watch } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";

const props = defineProps({
  formData: { type: Object, required: true },
  formErrors: { type: Object, default: () => ({}) },
  laboratories: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
  imageFile: { type: [Object, File, null], default: null },
  imagePreviewUrl: { type: String, default: null },
  isMasterSearching: { type: Boolean, default: false },
  masterFound: { type: Boolean, default: false },
  masterFoundProduct: { type: Object, default: null },
  xs: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:formData",
  "update:imageFile",
  "open-lab-dialog",
  "open-img-preview",
  "submit",
]);

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => brandingStore.settings.business_type === "restaurant");
const isMiniMarket = computed(() => brandingStore.settings.business_type === "minimarket");
const isSportsRental = computed(() => brandingStore.settings.business_type === "sports_rental");

const isFieldEnabled = (fieldKey) => {
  const fields = brandingStore.settings?.product_form_fields;
  if (!fields || !Array.isArray(fields) || fields.length === 0) return true;
  return fields.includes(fieldKey);
};

// Opciones concisas para unidad de receta (solo el símbolo o abreviatura)
const recipeUnits = [
  { title: "und", value: "und" },
  { title: "g", value: "g" },
  { title: "ml", value: "ml" },
  { title: "oz", value: "oz" },
  { title: "porción", value: "porción" },
];

// Limpiar formato si contiene paréntesis heredados
watch(
  () => props.formData.unit_of_measure,
  (val) => {
    if (val && /\(([^)]+)\)/.test(val)) {
      const match = val.match(/\(([^)]+)\)/);
      if (match) props.formData.unit_of_measure = match[1];
    }
  },
  { immediate: true }
);
</script>

<template>
  <div :class="[xs ? 'gap-4' : 'gap-6', 'd-flex flex-column']">
    <div class="d-flex flex-column gap-3">
      <div class="d-flex align-center gap-2">
        <div class="header-indicator primary shadow-sm" />
        <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Información General del Producto</span>
      </div>

      <VCard
        variant="flat"
        :class="[xs ? 'pa-3' : 'pa-5', 'bg-surface rounded-xl border shadow-sm']"
      >
        <VForm @submit.prevent="emit('submit')">
          <VRow dense>
            <!-- Imagen del Producto -->
            <VCol v-if="isFieldEnabled('photo_url')" cols="12" md="4">
              <div class="d-flex align-center gap-2">
                <VFileInput
                  :model-value="imageFile"
                  @update:model-value="emit('update:imageFile', $event)"
                  label="Imagen del Producto"
                  accept="image/*"
                  variant="outlined"
                  placeholder="Imagen del Producto"
                  :prepend-icon="null"
                  prepend-inner-icon="tabler-camera"
                  clearable
                  :error-messages="formErrors.photo_url"
                  density="comfortable"
                  class="rounded-lg flex-1-1"
                  hide-details="auto"
                />
                <VBtn
                  v-if="imagePreviewUrl"
                  icon="tabler-eye"
                  variant="tonal"
                  color="primary"
                  size="small"
                  density="comfortable"
                  class="rounded-lg flex-shrink-0"
                  title="Ver preview ecommerce"
                  @click="emit('open-img-preview')"
                />
              </div>
            </VCol>

            <!-- Nombre del Producto -->
            <VCol v-if="isFieldEnabled('name')" cols="12" :md="isFieldEnabled('photo_url') ? 8 : 12">
              <VTextField v-model="formData.name" label="Nombre del Producto"
                variant="outlined"
                density="comfortable"
                :error-messages="formErrors.name"
                class="rounded-lg font-weight-black"
                hide-details="auto"
              />
            </VCol>

            <!-- Código de Barras -->
            <VCol v-if="isFieldEnabled('barcode')" cols="12" md="4">
              <VTextField v-model="formData.barcode" label="Código de Barras (SCAN O MANUAL)"
                variant="outlined"
                density="comfortable"
                :loading="isMasterSearching"
                :error-messages="formErrors.barcode"
                prepend-inner-icon="tabler-barcode"
                class="rounded-lg font-weight-black"
                hide-details="auto"
              >
                <template #append-inner v-if="masterFound">
                  <VChip
                    size="x-small"
                    color="success"
                    variant="elevated"
                    class="font-weight-bold cursor-pointer"
                    title="Producto homologado en el Catálogo Maestro"
                  >
                    <VIcon start size="13" icon="tabler-cloud-check" />
                    ID #{{ masterFoundProduct?.id }}
                  </VChip>
                </template>
              </VTextField>
            </VCol>

            <!-- Principio Activo -->
            <VCol v-if="!isRestaurant && !isMiniMarket && !isSportsRental && isFieldEnabled('active_ingredient')" cols="12" md="4">
              <VTextField v-model="formData.active_ingredient" label="Principio Activo"
                variant="outlined"
                density="comfortable"
                :error-messages="formErrors.active_ingredient"
                class="rounded-lg font-weight-black"
                hide-details="auto"
              />
            </VCol>

            <!-- Laboratorio / Marca -->
            <VCol v-if="isFieldEnabled('laboratory_id')" cols="12" :md="!isRestaurant && !isMiniMarket && !isSportsRental ? 4 : 4">
              <VAutocomplete v-model="formData.laboratory_id" :label="isRestaurant || isMiniMarket || isSportsRental ? 'Marca' : 'Laboratorio'"
                :items="laboratories"
                item-title="name"
                item-value="id"
                variant="outlined"
                density="comfortable"
                clearable
                :error-messages="formErrors.laboratory_id"
                class="rounded-lg font-weight-black"
                hide-details="auto"
              >
                <template #append-inner>
                  <VBtn
                    icon="tabler-plus"
                    variant="tonal"
                    color="primary"
                    size="x-small"
                    class="rounded-lg"
                    @click.stop="emit('open-lab-dialog')"
                  />
                </template>
              </VAutocomplete>
            </VCol>

            <!-- Categoría -->
            <VCol v-if="isFieldEnabled('category_id')" cols="12" :md="!isRestaurant && !isMiniMarket && !isSportsRental ? 4 : 4">
              <VAutocomplete v-model="formData.category_id" label="Categoría"
                :items="categories"
                item-title="name"
                item-value="id"
                variant="outlined"
                density="comfortable"
                clearable
                :error-messages="formErrors.category_id"
                class="rounded-lg font-weight-black"
                hide-details="auto"
              />
            </VCol>

            <!-- Origen -->
            <VCol v-if="!isRestaurant && !isMiniMarket && !isSportsRental && isFieldEnabled('origin_id')" cols="12" md="4">
              <VAutocomplete v-model="formData.origin_id" label="Origen"
                :items="origins"
                item-title="name"
                item-value="id"
                variant="outlined"
                density="comfortable"
                clearable
                :error-messages="formErrors.origin_id"
                class="rounded-lg font-weight-black"
                hide-details="auto"
              />
            </VCol>

            <!-- Proveedor -->
            <VCol v-if="(isRestaurant || isSportsRental) && isFieldEnabled('supplier_id')" cols="12" md="4">
              <VAutocomplete v-model="formData.supplier_id" label="Proveedor"
                :items="suppliers"
                item-title="name"
                item-value="id"
                variant="outlined"
                density="comfortable"
                clearable
                :error-messages="formErrors.supplier_id"
                class="rounded-lg font-weight-black"
                hide-details="auto"
              />
            </VCol>

            <!-- Configuración de Unidades de 3 Niveles (Compra -> Almacén -> Receta) para Restaurante -->
            <template v-if="isRestaurant">
              <!-- Nivel 1: Unidad de Compra (Empaque mayorista del proveedor) -->
              <VCol cols="12" sm="6" md="3">
                <VCombobox
                  v-model="formData.purchase_unit_name"
                  label="Unidad de Compra"
                  placeholder="Ej: Display, Caja"
                  :items="['Display', 'Caja', 'Fardo', 'Bulto', 'Saco', 'Paquete', 'Galón', 'Cubeta']"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg font-weight-black"
                  hide-details="auto"
                  :error-messages="formErrors.purchase_unit_name"
                />
              </VCol>

              <VCol cols="12" sm="6" md="2">
                <VTextField
                  v-model.number="formData.units_per_inner_pack"
                  label="Paquetes por Compra"
                  placeholder="12"
                  type="number"
                  step="any"
                  min="0.0001"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg font-weight-black"
                  hide-details="auto"
                  :error-messages="formErrors.units_per_inner_pack"
                />
              </VCol>

              <!-- Nivel 2: Unidad de Almacén (Paquete / Envase individual) -->
              <VCol cols="12" sm="6" md="3">
                <VCombobox
                  v-model="formData.pack_unit_name"
                  label="Unidad de Almacén"
                  placeholder="Paquete"
                  :items="['Paquete', 'Envase', 'Bolsa', 'Botella', 'Lata', 'Porción']"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg font-weight-black"
                  hide-details="auto"
                  :error-messages="formErrors.pack_unit_name"
                />
              </VCol>

              <!-- Nivel 3: Contenido por paquete y Unidad en Receta -->
              <VCol cols="12" sm="6" md="2">
                <VTextField
                  v-model.number="formData.presentation"
                  label="Contenido / Paq."
                  placeholder="4"
                  type="number"
                  step="any"
                  min="0.0001"
                  variant="outlined"
                  density="comfortable"
                  class="rounded-lg font-weight-black"
                  hide-details="auto"
                  :error-messages="formErrors.presentation"
                />
              </VCol>

              <VCol cols="12" sm="6" md="2">
                <VSelect
                  v-model="formData.unit_of_measure"
                  label="Unidad en Receta"
                  :items="recipeUnits"
                  item-title="title"
                  item-value="value"
                  variant="outlined"
                  density="comfortable"
                  clearable
                  class="rounded-lg font-weight-black"
                  hide-details="auto"
                  :error-messages="formErrors.unit_of_measure"
                />
              </VCol>

              <!-- Cintilla Informativa de Equivalencia en Vivo -->
              <VCol cols="12" class="pt-0">
                <div class="pa-2.5 bg-light rounded-lg border text-xs d-flex align-center gap-2 flex-wrap text-medium-emphasis">
                  <VIcon icon="tabler-scale" size="18" color="primary" />
                  <span class="font-weight-bold text-high-emphasis">Equivalencia logística:</span>
                  <VChip size="x-small" color="primary" variant="flat" class="font-weight-black">
                    1 {{ formData.purchase_unit_name || 'Compra' }}
                  </VChip>
                  <span>=</span>
                  <VChip size="x-small" color="secondary" variant="tonal" class="font-weight-bold">
                    {{ formData.units_per_inner_pack || 1 }} {{ formData.pack_unit_name || 'paquete(s)' }}
                  </VChip>
                  <span>=</span>
                  <VChip size="x-small" color="success" variant="tonal" class="font-weight-black">
                    {{ ((Number(formData.units_per_inner_pack) || 1) * (Number(formData.presentation) || 1)).toLocaleString() }} {{ formData.unit_of_measure || 'und' }} en cocina
                  </VChip>
                </div>
              </VCol>
            </template>

            <!-- Descripción -->
            <VCol v-if="isFieldEnabled('description')" cols="12">
              <VTextarea v-model="formData.description" label="Descripción del Producto"
                variant="outlined"
                density="comfortable"
                rows="1"
                auto-grow
                persistent-placeholder
                :error-messages="formErrors.description"
                class="rounded-lg font-weight-black"
                hide-details="auto"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCard>
    </div>
  </div>
</template>

<style scoped>
.header-indicator {
  border-radius: 8px !important;
  block-size: 16px;
  inline-size: 4px;
}
.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}
.letter-spacing-1 {
  letter-spacing: 1px !important;
}
.uppercase {
  text-transform: uppercase;
}
</style>
