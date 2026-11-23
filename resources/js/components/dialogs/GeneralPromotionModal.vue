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

const weekDays = [
  { title: "Lun", value: 1 },
  { title: "Mar", value: 2 },
  { title: "Mié", value: 3 },
  { title: "Jue", value: 4 },
  { title: "Vie", value: 5 },
  { title: "Sáb", value: 6 },
  { title: "Dom", value: 7 },
];

const salesChannels = [
  { title: "Todos los canales", value: "all" },
  { title: "Solo Salón (Mesa)", value: "dine_in" },
  { title: "Para Llevar / Delivery", value: "takeaway_delivery" },
];

const localFormData = ref({
  id: null,
  name: "",
  type: "2x1",
  fixed_price: null,
  discount_percentage: null,
  is_active: true,
  categories: [],
  start_date: null,
  end_date: null,
  days_of_week: [],
  start_time: null,
  end_time: null,
  min_order_amount: null,
  coupon_code: "",
  sales_channel: "all",
  stackable: false,
});

watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible) {
      localFormData.value = {
        id: props.formData.id || null,
        name: props.formData.name || "",
        type: props.formData.type || "2x1",
        fixed_price: props.formData.fixed_price || null,
        discount_percentage:
          props.formData.discount_percentage ??
          (props.formData.type === "general" ? props.formData.fixed_price : null),
        is_active: props.formData.is_active !== undefined ? props.formData.is_active : true,
        categories: Array.isArray(props.formData.categories) ? props.formData.categories : [],
        start_date: props.formData.start_date || null,
        end_date: props.formData.end_date || null,
        days_of_week: Array.isArray(props.formData.days_of_week) ? props.formData.days_of_week : [],
        start_time: props.formData.start_time || null,
        end_time: props.formData.end_time || null,
        min_order_amount: props.formData.min_order_amount || null,
        coupon_code: props.formData.coupon_code || "",
        sales_channel: props.formData.sales_channel || "all",
        stackable: Boolean(props.formData.stackable),
      };
    }
  },
  { immediate: true }
);

function onSave() {
  const payload = { ...localFormData.value };
  if (payload.type === "general") {
    payload.fixed_price = payload.discount_percentage;
  }
  emit("save", payload);
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
    width="680px"
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
        <div class="mb-4">
          <div class="d-flex align-center justify-space-between mb-2">
            <div class="d-flex align-center gap-1-5">
              <div class="header-indicator primary" />
              <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Regla de Promoción</span>
            </div>
            <div class="d-flex align-center gap-2">
              <span class="text-super-xs font-weight-bold text-high-emphasis uppercase">Activa</span>
              <VSwitch
                v-model="localFormData.is_active"
                color="primary"
                hide-details
                density="compact"
                inset
              />
            </div>
          </div>

          <div class="pa-3 rounded border bg-var-theme-background">
            <VRow dense class="align-center">
              <!-- Nombre amigable -->
              <VCol cols="12" class="mb-2">
                <VTextField
                  v-model="localFormData.name"
                  label="Nombre de la promoción (Opcional)"
                  placeholder="Ej. Happy Hour Viernes, Miércoles Saludable..."
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  :disabled="props.loading"
                />
              </VCol>

              <!-- Tipo de Promoción -->
              <VCol cols="12" :sm="localFormData.type === 'general' || localFormData.type === 'fixed_price' ? 7 : 12">
                <VSelect
                  v-model="localFormData.type"
                  :items="promoTypes"
                  item-title="title"
                  item-value="value"
                  placeholder="Seleccionar tipo de oferta..."
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  class="rounded font-weight-bold"
                  :disabled="props.loading"
                />
              </VCol>

              <!-- Porcentaje de Descuento (Si es Oferta General) -->
              <VCol v-if="localFormData.type === 'general'" cols="12" sm="5">
                <VTextField
                  v-model.number="localFormData.discount_percentage"
                  type="number"
                  min="0"
                  max="100"
                  step="0.1"
                  suffix="%"
                  placeholder="% Descuento (Ej. 10.00)"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  prepend-inner-icon="tabler-percentage"
                  class="rounded font-weight-black"
                  :disabled="props.loading"
                  :error="!!props.formErrors.discount_percentage"
                  :error-messages="props.formErrors.discount_percentage"
                />
              </VCol>

              <!-- Precio Fijo (Solo si aplica) -->
              <VCol v-if="localFormData.type === 'fixed_price'" cols="12" sm="5">
                <VTextField
                  v-model.number="localFormData.fixed_price"
                  type="number"
                  min="0"
                  step="0.01"
                  prefix="$"
                  placeholder="Precio fijo (Ej. 0.00)"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  class="rounded font-weight-black"
                  :disabled="props.loading"
                  :error="!!props.formErrors.fixed_price"
                  :error-messages="props.formErrors.fixed_price"
                />
              </VCol>
            </VRow>
          </div>
        </div>

        <!-- Bloque 2: Alcance y Categorías -->
        <div class="mb-2">
          <div class="d-flex align-center gap-1-5 mb-2">
            <div class="header-indicator primary" />
            <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Categorías Aplicables</span>
          </div>

          <div class="pa-3 rounded border bg-var-theme-background">
            <VAutocomplete
              v-model="localFormData.categories"
              :items="props.categories"
              :item-title="(item) => `${item.id} - ${item.name}`"
              item-value="id"
              multiple
              chips
              closable-chips
              placeholder="Seleccionar una o más categorías (Opcional)..."
              variant="outlined"
              density="compact"
              hide-details="auto"
              class="rounded font-weight-medium"
              :disabled="props.loading"
              :error="!!props.formErrors.categories"
              :error-messages="props.formErrors.categories"
            />
            <span v-if="localFormData.type === 'general' && (!localFormData.categories || localFormData.categories.length === 0)" class="text-super-xs text-primary font-weight-medium mt-1-5 d-block">
              ℹ️ Si no seleccionas ninguna categoría, el descuento se aplicará de forma global a todos los productos.
            </span>
          </div>
        </div>

        <!-- Bloque 3: Vigencia, Días, Horario y Condiciones -->
        <div class="mt-4">
          <div class="d-flex align-center gap-1-5 mb-2">
            <div class="header-indicator primary" />
            <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Vigencia, Horario y Condiciones (Opcional)</span>
          </div>

          <div class="pa-3 rounded border bg-var-theme-background">
            <VRow dense>
              <VCol cols="12" sm="6">
                <VTextField
                  v-model="localFormData.start_date"
                  type="date"
                  label="Fecha inicio"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  :disabled="props.loading"
                />
              </VCol>
              <VCol cols="12" sm="6">
                <VTextField
                  v-model="localFormData.end_date"
                  type="date"
                  label="Fecha fin"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  :disabled="props.loading"
                />
              </VCol>
              <VCol cols="12" sm="6">
                <VTextField
                  v-model="localFormData.start_time"
                  type="time"
                  label="Hora inicio (Happy Hour)"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  :disabled="props.loading"
                />
              </VCol>
              <VCol cols="12" sm="6">
                <VTextField
                  v-model="localFormData.end_time"
                  type="time"
                  label="Hora fin"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  :disabled="props.loading"
                />
              </VCol>
              <VCol cols="12">
                <VSelect
                  v-model="localFormData.days_of_week"
                  :items="weekDays"
                  item-title="title"
                  item-value="value"
                  label="Días activos (vacío = todos los días)"
                  multiple
                  chips
                  closable-chips
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  :disabled="props.loading"
                />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField
                  v-model.number="localFormData.min_order_amount"
                  type="number"
                  min="0"
                  step="0.01"
                  prefix="$"
                  label="Compra mínima ($)"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  :disabled="props.loading"
                />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField
                  v-model="localFormData.coupon_code"
                  label="Código cupón"
                  placeholder="Ej. PROMO10"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  :disabled="props.loading"
                />
              </VCol>
              <VCol cols="12" sm="4">
                <VSelect
                  v-model="localFormData.sales_channel"
                  :items="salesChannels"
                  item-title="title"
                  item-value="value"
                  label="Canal de venta"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  :disabled="props.loading"
                />
              </VCol>
              <VCol cols="12" class="pt-2">
                <VSwitch
                  v-model="localFormData.stackable"
                  label="Permitir acumular con otras promociones generales"
                  color="primary"
                  density="compact"
                  hide-details="auto"
                  :disabled="props.loading"
                />
              </VCol>
            </VRow>
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

.bg-var-theme-background {
  background-color: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), 0.12) !important;
  border-radius: 5px !important;
}
</style>
