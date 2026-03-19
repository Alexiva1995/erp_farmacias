<script setup>
import { ref } from 'vue';

const props = defineProps({
  startDate: { type: String, default: null },
  endDate:   { type: String, default: null },
});

const emit = defineEmits(['update:startDate', 'update:endDate', 'clear']);

const isFiltersVisible = ref(false);

function setQuickFilter(days) {
  const today = new Date();
  const start = new Date(today);

  if (days === 'all') {
    emit('update:startDate', null);
    emit('update:endDate', null);
  } else if (days === 'current_month') {
    start.setDate(1);
    emit('update:startDate', start.toISOString().split('T')[0]);
    emit('update:endDate', today.toISOString().split('T')[0]);
  } else if (days === 'last_month') {
    const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth(), 0);
    emit('update:startDate', lastMonth.toISOString().split('T')[0]);
    emit('update:endDate', lastDay.toISOString().split('T')[0]);
  } else {
    start.setDate(today.getDate() - days);
    emit('update:startDate', start.toISOString().split('T')[0]);
    emit('update:endDate', today.toISOString().split('T')[0]);
  }
}
</script>

<template>
  <div class="income-statement-filters mb-6">
    <VCard class="rounded-xl border shadow-sm overflow-hidden bg-surface">
      <!-- Header / Barra de Búsqueda Persistente -->
      <div class="pa-4 d-flex align-center flex-wrap gap-4">
        <div class="d-flex align-center gap-2 flex-grow-1">
          <VAvatar color="primary" variant="tonal" size="40" class="rounded-lg">
            <VIcon icon="tabler-filter" size="22" />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-h6 font-weight-black leading-none">Filtros</span>
            <span class="text-super-xs text-disabled font-weight-bold uppercase mt-1">Refina los resultados del periodo</span>
          </div>
        </div>

        <div class="d-flex align-center gap-2">
          <VBtn
            variant="tonal"
            :color="isFiltersVisible ? 'primary' : 'secondary'"
            size="small"
            class="rounded-lg font-weight-black"
            @click="isFiltersVisible = !isFiltersVisible"
          >
            <VIcon :icon="isFiltersVisible ? 'tabler-chevron-up' : 'tabler-adjustments-horizontal'" start size="18" />
            {{ isFiltersVisible ? 'Ocultar Filtros' : 'Filtros Avanzados' }}
          </VBtn>
          
          <VBtn 
            icon="tabler-refresh" 
            variant="text" 
            color="secondary" 
            size="small" 
            class="rounded-lg"
            @click="$emit('clear')"
          />
        </div>
      </div>

      <!-- Panel Expandible de Filtros Avanzados -->
      <VExpandTransition>
        <div v-show="isFiltersVisible">
          <VDivider />
          <div class="pa-5 bg-surface-variant-light">
            <VRow>
              <VCol cols="12" md="4">
                <div class="d-flex flex-column gap-1 mb-4">
                  <span class="text-super-xs font-weight-black text-primary uppercase">Rango de Fecha Personalizado</span>
                  <div class="d-flex gap-2 align-center mt-1">
                    <AppDateTimePicker
                      :model-value="props.startDate"
                      placeholder="Desde"
                      clearable
                      class="premium-input-compact"
                      :config="{ altInput: true, altFormat: 'd/m/Y', dateFormat: 'Y-m-d' }"
                      @update:model-value="$emit('update:startDate', $event)"
                    />
                    <span class="text-disabled">—</span>
                    <AppDateTimePicker
                      :model-value="props.endDate"
                      placeholder="Hasta"
                      clearable
                      class="premium-input-compact"
                      :config="{ altInput: true, altFormat: 'd/m/Y', dateFormat: 'Y-m-d' }"
                      @update:model-value="$emit('update:endDate', $event)"
                    />
                  </div>
                </div>
              </VCol>

              <VCol cols="12" md="8">
                <span class="text-super-xs font-weight-black text-primary uppercase d-block mb-2">Accesos Rápidos</span>
                <div class="d-flex flex-wrap gap-2">
                  <VBtn
                    v-for="opt in [
                      { label: 'Todo', value: 'all' },
                      { label: '15 días', value: 15 },
                      { label: '30 días', value: 30 },
                      { label: '60 días', value: 60 },
                      { label: 'Mes Actual', value: 'current_month' },
                      { label: 'Mes Pasado', value: 'last_month' }
                    ]"
                    :key="opt.value"
                    variant="tonal"
                    size="small"
                    color="primary"
                    class="rounded-lg font-weight-bold"
                    @click="setQuickFilter(opt.value)"
                  >
                    {{ opt.label }}
                  </VBtn>
                </div>
              </VCol>
            </VRow>
          </div>
        </div>
      </VExpandTransition>
    </VCard>
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}

.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 0.05);
}

:deep(.premium-input-compact) {
  .v-field__input {
    font-size: 0.85rem !important;
    min-block-size: 38px !important;
    padding-block: 0 !important;
  }
}
</style>
