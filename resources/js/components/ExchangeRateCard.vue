<script setup>
import { computed } from "vue";

const props = defineProps({
  title: { type: String, required: true },
  badgeText: { type: String, required: true },
  rateValue: { type: [Number, String], required: true },
  prefix: { type: String, default: "" },
  suffix: { type: String, default: "" },
  decimals: { type: Number, default: 4 },
  subtext: { type: String, default: "" },
  color: { type: String, default: "primary" },
  icon: { type: String, default: "tabler-currency-dollar" },
  dateUpdate: { type: String, default: "Cargando..." },
  dateColor: { type: String, default: "success" },
  isManual: { type: Boolean, default: false },
  isRestaurantSelected: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  btnText: { type: String, default: "ACTUALIZAR" },
  modelValue: { type: [Number, String], default: "" },
});

const emit = defineEmits(["update:modelValue", "submit-manual", "update-auto"]);

const inputValue = computed({
  get: () => props.modelValue,
  set: (val) => emit("update:modelValue", val),
});

const formattedRate = computed(() => {
  const num = Number(props.rateValue) || 0;
  return props.decimals > 0 ? num.toFixed(props.decimals) : num;
});
</script>

<template>
  <VCard
    class="stats-card h-100 border-0 overflow-hidden shadow-sm d-flex flex-column"
    :class="{ 'restaurant-selected-card': isRestaurantSelected }"
  >
    <div
      class="card-bg-decoration"
      :style="`background: linear-gradient(45deg, rgba(var(--v-theme-${color}), 0.12), transparent)`"
    ></div>

    <VCardText class="pa-5 relative-content d-flex flex-column flex-grow-1">
      <!-- Encabezado de la Tarjeta -->
      <div class="d-flex align-center justify-space-between mb-4">
        <VAvatar :color="color" variant="tonal" rounded="lg" size="44" class="elevation-1">
          <VIcon size="24" :icon="icon" />
        </VAvatar>
        <div class="text-right">
          <span class="text-overline font-weight-bold text-disabled leading-none mb-1 d-block" style="letter-spacing: 1px !important">
            {{ title }}
          </span>
          <span :class="`text-super-xs font-weight-black opacity-80 uppercase text-${color}`">
            {{ badgeText }}
          </span>
        </div>
      </div>

      <!-- Valor de la Tasa -->
      <div class="mb-4">
        <div :class="`text-h4 font-weight-black text-${color}`">
          {{ prefix }} {{ formattedRate }} {{ suffix }}
        </div>
        <div v-if="subtext" class="text-super-xs text-medium-emphasis uppercase font-weight-bold mt-1">
          {{ subtext }}
        </div>
      </div>

      <VDivider class="mb-4 opacity-20" />

      <!-- Pie de Tarjeta con Controles -->
      <div class="d-flex flex-column gap-3 mt-auto">
        <div class="d-flex align-center justify-space-between mb-2">
          <VChip :color="dateColor" size="x-small" class="font-weight-black rounded uppercase">
            <VIcon start icon="tabler-calendar-stats" size="14"></VIcon>
            {{ dateUpdate }}
          </VChip>
        </div>

        <!-- Modo Manual -->
        <div v-if="isManual" class="d-flex align-center gap-2">
          <VTextField
            v-model="inputValue"
            placeholder="0.00"
            prefix="$"
            density="compact"
            variant="outlined"
            hide-details
            type="number"
            :disabled="loading"
            class="flex-grow-1 rounded-lg custom-input-rate"
            @keyup.enter="emit('submit-manual')"
          />
          <VBtn
            :color="color"
            icon="tabler-check"
            variant="flat"
            :loading="loading"
            :disabled="loading"
            size="small"
            class="rounded-lg shadow-sm"
            @click="emit('submit-manual')"
          />
        </div>

        <!-- Modo Automático -->
        <VBtn
          v-else
          :color="color"
          variant="flat"
          block
          :loading="loading"
          :disabled="loading"
          prepend-icon="tabler-refresh"
          class="rounded-lg font-weight-black text-xs shadow-sm"
          size="small"
          @click="emit('update-auto')"
        >
          {{ btnText }}
        </VBtn>
      </div>
    </VCardText>
    <div :class="`accent-border bg-${color}`"></div>
  </VCard>
</template>

<style scoped>
.stats-card {
  border-radius: 12px !important;
  backdrop-filter: blur(8px);
  background: rgba(var(--v-theme-surface), 90%) !important;
  transition: all 0.3s ease;
  position: relative;
}

.stats-card:hover {
  transform: translateY(-4px);
  background: rgba(var(--v-theme-surface), 100%) !important;
  box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.12) !important;
}

.restaurant-selected-card {
  border: 2px solid rgb(var(--v-theme-error)) !important;
}

.card-bg-decoration {
  position: absolute;
  z-index: 0;
  border-radius: 50%;
  block-size: 120px;
  filter: blur(50px);
  inline-size: 120px;
  inset-block-start: -30px;
  inset-inline-end: -30px;
  pointer-events: none;
}

.relative-content {
  position: relative;
  z-index: 1;
}

.accent-border {
  position: absolute;
  block-size: 100%;
  inline-size: 4px;
  inset-block-start: 0;
  inset-inline-start: 0;
  opacity: 0.8;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1.2;
}

.custom-input-rate :deep(.v-field__input) {
  font-weight: 800;
  font-size: 0.875rem;
  padding-block: 4px;
}

.custom-input-rate :deep(.v-field__outline) {
  --v-field-border-opacity: 0.15;
}
</style>
