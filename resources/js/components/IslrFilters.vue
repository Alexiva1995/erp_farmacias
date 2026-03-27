<script setup>
// Filtros para ISLR
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  selectedYear: { type: Number, required: true },
  availableYears: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:selectedYear", "refresh", "clear"]);

const yearModel = computed({
  get: () => props.selectedYear,
  set: (val) => emit("update:selectedYear", val),
});
</script>

<template>
  <AppFilterBase
    :show-search="false"
    :show-advanced="false"
    @clear="emit('clear')"
  >
    <!-- Selector de Año siempre visible -->
    <template #search>
      <div class="d-flex align-center gap-2 w-100">
        <VSelect
          v-model="yearModel"
          :items="props.availableYears"
          placeholder="Año"
          variant="outlined"
          density="compact"
          hide-details
          color="primary"
          class="flex-grow-1"
          style="max-width: 200px;"
        >
          <template #prepend-inner>
            <VIcon icon="tabler-calendar" size="18" color="disabled" class="me-2" />
          </template>
        </VSelect>
      </div>
    </template>

    <template #search-append>
      <!-- Actualizar -->
      <VBtn
        icon
        color="primary"
        variant="flat"
        size="38"
        class="rounded-circle shadow-sm"
        :loading="props.loading"
        @click="emit('refresh')"
      >
        <VIcon icon="tabler-refresh" size="20" />
        <VTooltip activator="parent" location="top">Actualizar Datos</VTooltip>
      </VBtn>
    </template>
  </AppFilterBase>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.leading-none {
  line-height: 1;
}

.shadow-sm {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
}
</style>
