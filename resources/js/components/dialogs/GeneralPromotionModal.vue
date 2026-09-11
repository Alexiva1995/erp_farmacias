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
    max-width="680px"
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
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="38" class="me-3 elevation-1">
            <VIcon icon="tabler-tags" size="22" color="primary" />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ props.isEditing ? 'Editar Promoción General' : 'Nueva Promoción General' }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">
                Promociones y Reglas de Descuento Masivo
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="outlined"
            color="white"
            size="small"
            class="rounded-lg"
            @click="onCancel"
            :disabled="props.loading"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-5 bg-surface">
        <!-- Bloque 1: Configuración de la Regla -->
        <div class="mb-5">
          <div class="d-flex align-center justify-space-between mb-2">
            <div class="d-flex align-center gap-1-5">
              <div class="header-indicator primary" />
              <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Regla de Promoción</span>
            </div>
            <div class="d-flex align-center gap-2">
              <span class="text-super-xs font-weight-bold text-disabled uppercase">Activa</span>
              <VSwitch
                v-model="localFormData.is_active"
                color="primary"
                hide-details
                density="compact"
                inset
              />
            </div>
          </div>

          <VRow dense>
            <!-- Tipo de Promoción -->
            <VCol cols="12" :sm="localFormData.type === 'general' || localFormData.type === 'fixed_price' ? 7 : 12">
              <div class="mb-2 mb-sm-0">
                <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">Tipo de Oferta</span>
                <VSelect
                  v-model="localFormData.type"
                  :items="promoTypes"
                  item-title="title"
                  item-value="value"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  class="rounded-lg font-weight-bold"
                  :disabled="props.loading"
                />
              </div>
            </VCol>

            <!-- Porcentaje de Descuento (Si es Oferta General) -->
            <VCol v-if="localFormData.type === 'general'" cols="12" sm="5">
              <div class="mb-2 mb-sm-0">
                <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">% Descuento</span>
                <VTextField
                  v-model.number="localFormData.fixed_price"
                  type="number"
                  min="0"
                  max="100"
                  step="0.1"
                  suffix="%"
                  placeholder="Ej: 10"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  prepend-inner-icon="tabler-percentage"
                  class="rounded-lg font-weight-black"
                  :disabled="props.loading"
                  :error="!!props.formErrors.fixed_price"
                  :error-messages="props.formErrors.fixed_price"
                />
              </div>
            </VCol>

            <!-- Precio Fijo (Solo si aplica) -->
            <VCol v-if="localFormData.type === 'fixed_price'" cols="12" sm="5">
              <div class="mb-2 mb-sm-0">
                <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">Precio Fijo</span>
                <VTextField
                  v-model.number="localFormData.fixed_price"
                  type="number"
                  min="0"
                  step="0.01"
                  prefix="$"
                  placeholder="0.00"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  class="rounded-lg font-weight-black"
                  :disabled="props.loading"
                  :error="!!props.formErrors.fixed_price"
                  :error-messages="props.formErrors.fixed_price"
                />
              </div>
            </VCol>
          </VRow>
        </div>

        <!-- Bloque 2: Alcance y Categorías -->
        <div class="mb-2">
          <div class="d-flex align-center gap-1-5 mb-2">
            <div class="header-indicator primary" />
            <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Categorías Aplicables</span>
          </div>

          <div>
            <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">Seleccionar Categorías (Opcional si aplica a todo)</span>
            <VAutocomplete
              v-model="localFormData.categories"
              :items="props.categories"
              :item-title="(item) => `${item.id} - ${item.name}`"
              item-value="id"
              multiple
              chips
              closable-chips
              placeholder="SELECCIONA UNA O MÁS CATEGORÍAS..."
              variant="outlined"
              density="compact"
              hide-details="auto"
              class="rounded-lg font-weight-medium"
              :disabled="props.loading"
              :error="!!props.formErrors.categories"
              :error-messages="props.formErrors.categories"
            />
            <span v-if="localFormData.type === 'general' && (!localFormData.categories || localFormData.categories.length === 0)" class="text-super-xs text-primary font-weight-medium mt-1 d-block">
              ℹ️ Si no seleccionas ninguna categoría, el descuento se aplicará de forma global a todos los productos.
            </span>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-3 pa-sm-4 bg-surface border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="outlined"
              height="44"
              block
              class="font-weight-bold rounded-lg text-button uppercase"
              @click="onCancel"
              :disabled="props.loading"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              height="44"
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
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end, var(--v-theme-primary))) 100%
  );
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 3px;
  block-size: 14px;
  border-radius: 4px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 0.5px !important;
}

.leading-none {
  line-height: 1 !important;
}

.gap-1-5 {
  gap: 6px !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
