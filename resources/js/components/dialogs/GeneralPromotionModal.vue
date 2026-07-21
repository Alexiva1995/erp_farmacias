<script setup>
import { computed, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  formData: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
  categories: { type: Array, default: () => [] },
  formErrors: { type: Object, default: () => ({}) },
  isEditing: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "save", "modal-closed"]);

const { mobile } = useDisplay();

const promoTypes = [
  { title: "Oferta General (% Descuento a Todos)", value: "general" },
  { title: "Oferta 2X1 (Pagas el de mayor valor, menor gratis de la misma categoría)", value: "2x1" },
  { title: "Oferta 3X2 (Pagas los 2 más caros, menor gratis de la misma categoría)", value: "3x2" },
  { title: "50% en el segundo (50% en el de menor valor)", value: "50_second" },
  { title: "Precio Fijo por Categoría", value: "fixed_price" },
];

const localFormData = ref({
  id: null,
  type: "2x1",
  fixed_price: null,
  is_active: true,
  categories: [],
});

watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible) {
      localFormData.value = {
        id: props.formData.id || null,
        type: props.formData.type || "2x1",
        fixed_price: props.formData.fixed_price || null,
        is_active: props.formData.is_active !== undefined ? props.formData.is_active : true,
        categories: Array.isArray(props.formData.categories) ? props.formData.categories : [],
      };
    }
  },
  { immediate: true }
);

function onSave() {
  emit("save", { ...localFormData.value });
}

const onCancel = () => {
  emit("update:modelValue", false);
  emit("modal-closed");
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="700px"
    persistent
    scrollable
    transition="dialog-bottom-transition"
    class="premium-dialog"
    :fullscreen="mobile"
    @click:outside.prevent
    @keydown.esc.prevent="onCancel"
  >
    <VCard :class="mobile ? 'rounded-0' : 'detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface'">
      <!-- Header Premium con Gradiente -->
      <VCardTitle class="pa-0">
        <div class="premium-header pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
            <VIcon icon="tabler-tag" size="24" color="primary" />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ props.isEditing ? 'Editar Promoción General' : 'Nueva Promoción General' }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.6rem; letter-spacing: 0.05em;">
                Promociones Generales de Categorías
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="onCancel"
            :disabled="props.loading"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light">
        <div class="d-flex align-center gap-2 mb-4">
          <div class="header-indicator primary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Configuración de la Promoción</span>
        </div>

        <VCard variant="flat" class="pa-5 bg-white rounded-xl border shadow-sm mb-0">
          <VRow dense>
            <!-- Tipo de Promoción -->
            <VCol cols="12">
              <div class="mb-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Tipo de Oferta</span>
                <VSelect
                  v-model="localFormData.type"
                  :items="promoTypes"
                  item-title="title"
                  item-value="value"
                  variant="outlined"
                  density="comfortable"
                  hide-details
                  class="rounded-lg font-weight-bold"
                  :disabled="props.loading"
                />
              </div>
            </VCol>

            <!-- Porcentaje de Descuento (Si es Oferta General) -->
            <VCol v-if="localFormData.type === 'general'" cols="12">
              <div class="mb-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Porcentaje de Descuento (%)</span>
                <VTextField
                  v-model.number="localFormData.fixed_price"
                  type="number"
                  min="0"
                  max="100"
                  step="0.1"
                  suffix="%"
                  placeholder="Ej: 10"
                  variant="outlined"
                  density="comfortable"
                  hide-details
                  class="rounded-lg font-weight-bold"
                  :disabled="props.loading"
                  :error="!!props.formErrors.fixed_price"
                  :error-messages="props.formErrors.fixed_price"
                />
                <span class="text-caption text-medium-emphasis mt-1 d-block">Si no seleccionas categorías, este porcentaje se aplicará a TODOS los productos.</span>
              </div>
            </VCol>

            <!-- Precio Fijo (Solo si aplica) -->
            <VCol v-if="localFormData.type === 'fixed_price'" cols="12">
              <div class="mb-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Precio Fijo para Categoría</span>
                <VTextField
                  v-model.number="localFormData.fixed_price"
                  type="number"
                  min="0"
                  step="0.01"
                  prefix="$"
                  variant="outlined"
                  density="comfortable"
                  hide-details
                  class="rounded-lg font-weight-bold"
                  :disabled="props.loading"
                  :error="!!props.formErrors.fixed_price"
                  :error-messages="props.formErrors.fixed_price"
                />
              </div>
            </VCol>

            <!-- Categorías Aplicables (Multiselect) -->
            <VCol cols="12">
              <div class="mb-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Categorías Aplicables</span>
                <VSelect
                  v-model="localFormData.categories"
                  :items="props.categories"
                  item-title="name"
                  item-value="id"
                  multiple
                  chips
                  closable-chips
                  placeholder="Selecciona una o más categorías"
                  variant="outlined"
                  density="comfortable"
                  hide-details
                  class="rounded-lg"
                  :disabled="props.loading"
                  :error="!!props.formErrors.categories"
                  :error-messages="props.formErrors.categories"
                />
              </div>
            </VCol>

            <!-- Switch Activo -->
            <VCol cols="12">
              <VSwitch
                v-model="localFormData.is_active"
                label="¿Activar promoción?"
                color="primary"
                hide-details
              />
            </VCol>
          </VRow>
        </VCard>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-white border-t px-6">
        <VRow dense class="w-100 ma-0">
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              @click="onCancel"
              :disabled="props.loading"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :loading="props.loading"
              @click="onSave"
            >
              <VIcon start icon="tabler-device-floppy" size="18" />
              {{ props.isEditing ? "Guardar Cambios" : "Crear Promoción" }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.premium-header {
  background: var(--brand-gradient, linear-gradient(135deg, #7A0099, #E20074)) !important;
}

.premium-header h2,
.premium-header span {
  color: #ffffff !important;
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
  background-color: #3b82f6;
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

.leading-none {
  line-height: 1 !important;
}
</style>
