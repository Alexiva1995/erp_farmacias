<script setup>
import { computed } from "vue";
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
              <AppTextField
                v-model="formData.name"
                placeholder="Nombre del Producto"
                variant="outlined"
                density="comfortable"
                :error-messages="formErrors.name"
                class="rounded-lg font-weight-black"
                hide-details="auto"
              />
            </VCol>

            <!-- Código de Barras -->
            <VCol v-if="isFieldEnabled('barcode')" cols="12" md="4">
              <AppTextField
                v-model="formData.barcode"
                placeholder="Código de Barras (SCAN O MANUAL)"
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
              </AppTextField>
            </VCol>

            <!-- Principio Activo -->
            <VCol v-if="!isRestaurant && !isMiniMarket && !isSportsRental && isFieldEnabled('active_ingredient')" cols="12" md="4">
              <AppTextField
                v-model="formData.active_ingredient"
                placeholder="Principio Activo"
                variant="outlined"
                density="comfortable"
                :error-messages="formErrors.active_ingredient"
                class="rounded-lg font-weight-black"
                hide-details="auto"
              />
            </VCol>

            <!-- Laboratorio / Marca -->
            <VCol v-if="isFieldEnabled('laboratory_id')" cols="12" :md="!isRestaurant && !isMiniMarket && !isSportsRental ? 4 : 4">
              <AppSelect
                v-model="formData.laboratory_id"
                :placeholder="isRestaurant || isMiniMarket || isSportsRental ? 'Marca' : 'Laboratorio'"
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
              </AppSelect>
            </VCol>

            <!-- Categoría -->
            <VCol v-if="isFieldEnabled('category_id')" cols="12" :md="!isRestaurant && !isMiniMarket && !isSportsRental ? 4 : 4">
              <AppSelect
                v-model="formData.category_id"
                placeholder="Categoría"
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
              <AppSelect
                v-model="formData.origin_id"
                placeholder="Origen"
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
              <AppSelect
                v-model="formData.supplier_id"
                placeholder="Proveedor"
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

            <!-- Presentación -->
            <VCol v-if="isRestaurant && isFieldEnabled('presentation')" cols="12" md="4">
              <AppTextField
                v-model="formData.presentation"
                placeholder="Presentación"
                type="number"
                step="any"
                variant="outlined"
                density="comfortable"
                :error-messages="formErrors.presentation"
                class="rounded-lg font-weight-black"
                hide-details="auto"
              />
            </VCol>

            <!-- Unidad de Medida -->
            <VCol v-if="isRestaurant && isFieldEnabled('unit_of_measure')" cols="12" md="4">
              <AppSelect
                v-model="formData.unit_of_measure"
                placeholder="Unidad de Medida"
                :items="[
                  { title: 'Gramos (g)', value: 'g' },
                  { title: 'Mililitros (ml)', value: 'ml' },
                  { title: 'Unidades (und)', value: 'und' }
                ]"
                item-title="title"
                item-value="value"
                variant="outlined"
                density="comfortable"
                clearable
                :error-messages="formErrors.unit_of_measure"
                class="rounded-lg font-weight-black"
                hide-details="auto"
              />
            </VCol>

            <!-- Descripción -->
            <VCol v-if="isFieldEnabled('description')" cols="12">
              <AppTextarea
                v-model="formData.description"
                placeholder="Descripción del Producto"
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
