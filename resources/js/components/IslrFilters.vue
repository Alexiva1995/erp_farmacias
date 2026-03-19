<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  selectedYear: { type: Number, required: true },
  availableYears: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:selectedYear", "refresh", "clear"]);

const isFilterVisible = ref(false);

const yearModel = computed({
  get: () => props.selectedYear,
  set: (val) => emit("update:selectedYear", val),
});
</script>

<template>
  <VCard class="mb-6 rounded-xl border-0 shadow-sm overflow-hidden bg-surface">
    <!-- Barra de Acciones Principal -->
    <VCardActions class="pa-4 px-6 d-flex align-center bg-surface">
      <div class="d-flex align-center gap-2">
        <VAvatar color="primary" variant="tonal" size="38" class="rounded-lg">
          <VIcon icon="tabler-calendar-stats" size="20" />
        </VAvatar>
        <div class="d-flex flex-column">
          <span class="text-sm font-weight-black uppercase leading-none mb-1">Año Fiscal</span>
          <span class="text-super-xs text-disabled font-weight-medium">Selección de período para ISLR</span>
        </div>
      </div>

      <VSpacer />

      <div class="d-flex align-center gap-2">
        <!-- Toggle Filtros -->
        <VBtn
          icon
          variant="tonal"
          :color="isFilterVisible ? 'primary' : 'secondary'"
          size="38"
          @click="isFilterVisible = !isFilterVisible"
          class="rounded-lg"
        >
          <VBadge
            :model-value="props.selectedYear !== new Date().getFullYear()"
            color="error"
            dot
            offset-x="3"
            offset-y="3"
          >
            <VIcon :icon="isFilterVisible ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
          </VBadge>
          <VTooltip activator="parent" location="top">{{ isFilterVisible ? 'Ocultar Filtros' : 'Mostrar Filtros' }}</VTooltip>
        </VBtn>

        <VDivider vertical class="mx-1 my-2" />

        <!-- Actualizar -->
        <VBtn
          icon
          color="primary"
          variant="flat"
          size="38"
          class="rounded-lg shadow-sm"
          :loading="props.loading"
          @click="emit('refresh')"
        >
          <VIcon icon="tabler-refresh" size="20" />
          <VTooltip activator="parent" location="top">Actualizar Datos</VTooltip>
        </VBtn>

        <!-- Limpiar -->
        <VBtn
          icon
          variant="tonal"
          color="error"
          size="38"
          class="rounded-lg"
          @click="emit('clear')"
          :disabled="props.loading"
        >
          <VIcon icon="tabler-filter-x" size="20" />
          <VTooltip activator="parent" location="top">Restablecer Año</VTooltip>
        </VBtn>
      </div>
    </VCardActions>

    <!-- Panel de Filtros Colapsable -->
    <VExpandTransition>
      <div v-show="isFilterVisible">
        <VDivider class="opacity-10" />
        <VCardText class="pa-6 pt-4 bg-surface-variant-opacity-2">
          <VRow>
            <!-- Selector de Año -->
            <VCol cols="12" md="6">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Seleccionar Año Fiscal</span>
              <VSelect
                v-model="yearModel"
                :items="props.availableYears"
                placeholder="Año..."
                variant="outlined"
                density="compact"
                hide-details
                color="primary"
                class="premium-input"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-calendar" size="18" color="disabled" class="me-2" />
                </template>
              </VSelect>
            </VCol>

            <!-- Información -->
            <VCol cols="12" md="6" class="d-flex align-center">
              <VAlert type="info" variant="tonal" class="rounded-lg text-xs" density="compact">
                Seleccione el año fiscal para visualizar el desglose de renta bruta e impuestos de dicho período.
              </VAlert>
            </VCol>
          </VRow>
        </VCardText>
      </div>
    </VExpandTransition>
  </VCard>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}

.leading-none {
  line-height: 1;
}

.bg-surface-variant-opacity-2 {
  background-color: rgba(var(--v-theme-on-surface), 0.02) !important;
}

:deep(.premium-input) {
  .v-field__outline {
    --v-field-border-opacity: 0.1;
  }
}
</style>
