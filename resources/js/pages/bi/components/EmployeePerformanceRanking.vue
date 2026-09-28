<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  employees: { type: Array, default: () => [] },
  selectedEmployee: { type: [Number, String], default: null }
});

const emit = defineEmits(['select']);

const searchQuery = ref('');

const formatCurrency = (value) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0);
const formatNumber = (value) => new Intl.NumberFormat('en-US').format(value || 0);

const filteredEmployees = computed(() => {
  if (!searchQuery.value.trim()) return props.employees;
  const q = searchQuery.value.toLowerCase();
  return props.employees.filter(emp => 
    `${emp.name} ${emp.last_name || ''}`.toLowerCase().includes(q)
  );
});
</script>

<template>
  <VCard class="rounded-lg border h-100 d-flex flex-column">
    <VCardItem class="py-3 border-b">
      <div class="d-flex align-center justify-space-between flex-wrap ga-2">
        <VCardTitle class="text-subtitle-2 font-weight-bold text-uppercase">Ranking Integral</VCardTitle>
        <VChip size="small" color="primary" variant="tonal" class="font-weight-bold">
          {{ employees.length }} Vendedores
        </VChip>
      </div>
    </VCardItem>
    
    <div class="pa-3 border-b">
      <VTextField
        v-model="searchQuery"
        placeholder="Buscar vendedor..."
        prepend-inner-icon="tabler-search"
        variant="outlined"
        density="compact"
        hide-details
        clearable
      />
    </div>

    <div class="flex-grow-1 overflow-y-auto" style="max-height: 520px;">
      <VTable density="comfortable" hover class="ranking-table">
        <thead>
          <tr>
            <th class="text-center" style="width: 48px;">#</th>
            <th>Vendedor</th>
            <th class="text-end">Venta USD</th>
            <th class="text-center" style="width: 90px;">Puntos</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(emp, idx) in filteredEmployees"
            :key="emp.id"
            @click="emit('select', emp.id)"
            class="cursor-pointer"
            :class="{ 'selected-row': selectedEmployee === emp.id }"
          >
            <td class="text-center">
              <VAvatar
                v-if="idx === 0"
                size="24"
                color="warning"
                variant="tonal"
                class="font-weight-bold"
              >
                <VIcon icon="tabler-trophy" size="14" />
              </VAvatar>
              <VAvatar
                v-else-if="idx === 1"
                size="24"
                color="secondary"
                variant="tonal"
                class="font-weight-bold"
              >
                <VIcon icon="tabler-medal" size="14" />
              </VAvatar>
              <VAvatar
                v-else-if="idx === 2"
                size="24"
                color="info"
                variant="tonal"
                class="font-weight-bold"
              >
                <VIcon icon="tabler-award" size="14" />
              </VAvatar>
              <span v-else class="font-weight-bold text-medium-emphasis text-caption">{{ idx + 1 }}</span>
            </td>
            <td>
              <div class="d-flex align-center py-1">
                <VAvatar size="32" class="me-2 border" :color="selectedEmployee === emp.id ? 'primary' : undefined">
                  <VImg :src="emp.photo || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(emp.name)" />
                </VAvatar>
                <div class="d-flex flex-column overflow-hidden">
                  <span class="text-body-2 font-weight-bold text-truncate">{{ emp.name }} {{ emp.last_name || '' }}</span>
                  <span class="text-caption text-medium-emphasis">{{ emp.tickets }} tickets</span>
                </div>
              </div>
            </td>
            <td class="text-end font-weight-bold text-body-2">{{ formatCurrency(emp.sales) }}</td>
            <td class="text-center">
              <VChip size="x-small" color="primary" variant="tonal" class="font-weight-bold">
                {{ formatNumber(emp.points) }}
              </VChip>
            </td>
          </tr>
          <tr v-if="!filteredEmployees.length">
            <td colspan="4" class="text-center py-6 text-medium-emphasis">
              <VIcon icon="tabler-user-off" size="32" class="mb-1 opacity-50 d-block mx-auto" />
              <span class="text-caption">No se encontraron empleados</span>
            </td>
          </tr>
        </tbody>
      </VTable>
    </div>
  </VCard>
</template>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
.selected-row {
  background-color: rgba(var(--v-theme-primary), 0.12) !important;
}
</style>
