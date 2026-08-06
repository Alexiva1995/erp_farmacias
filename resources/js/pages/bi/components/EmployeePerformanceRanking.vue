<script setup>
const props = defineProps({
  employees: { type: Array, default: () => [] },
  selectedEmployee: { type: [Number, String], default: null }
});

const emit = defineEmits(['select']);

const formatCurrency = (value) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0);
const formatNumber = (value) => new Intl.NumberFormat('en-US').format(value || 0);
</script>

<template>
  <VCard class="rounded-lg border shadow-sm overflow-hidden h-100">
    <VCardItem class="py-3 border-b bg-light-primary">
      <VCardTitle class="text-subtitle-2 font-weight-black uppercase">Ranking Integral (Gamificación)</VCardTitle>
    </VCardItem>
    
    <VTable density="compact" hover class="analytics-table clickable-rows">
      <thead>
        <tr>
          <th class="text-center">#</th>
          <th>Vendedor</th>
          <th class="text-right">Venta USD</th>
          <th class="text-center">Pts</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(emp, idx) in employees"
          :key="emp.id"
          @click="emit('select', emp.id)"
          :class="{ 'bg-light-primary': selectedEmployee === emp.id }"
        >
          <td class="text-center font-weight-black opacity-30">{{ idx + 1 }}</td>
          <td>
            <div class="d-flex align-center py-1">
              <VAvatar size="24" class="me-2">
                <VImg :src="emp.photo || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(emp.name)" />
              </VAvatar>
              <span class="text-[11px] font-weight-bold">{{ emp.name }} {{ emp.last_name || '' }}</span>
            </div>
          </td>
          <td class="text-right font-weight-bold">{{ formatCurrency(emp.sales) }}</td>
          <td class="text-center">
            <VChip size="x-small" label color="primary" variant="tonal" class="font-weight-black">
              {{ formatNumber(emp.points) }}
            </VChip>
          </td>
        </tr>
        <tr v-if="!employees.length">
          <td colspan="4" class="text-center py-4 text-disabled">
            No hay empleados disponibles en este período.
          </td>
        </tr>
      </tbody>
    </VTable>
  </VCard>
</template>

<style scoped>
.bg-light-primary { background-color: #fff0f6; }
.font-weight-black { font-weight: 900 !important; }
.uppercase { text-transform: uppercase; letter-spacing: 0.5px; }

.clickable-rows tbody tr { cursor: pointer; transition: all 0.2s; }
.clickable-rows tbody tr:hover { background-color: #f1f5f9; }

.analytics-table :deep(th) {
  background-color: #f8fafc !important;
  color: #64748b !important;
  font-size: 10px !important;
  border-bottom: 2px solid #e2e8f0 !important;
}

.analytics-table :deep(td) {
  font-size: 0.75rem !important;
  border-bottom: 1px solid #f1f5f9 !important;
}
</style>
