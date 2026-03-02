<script setup>
import { formatCurrency } from '@/utils/currencyFormatter';
import { computed } from 'vue';

const props = defineProps({
  transactions:    { type: [Array, Object], default: () => [] },
  loading:         { type: Boolean, default: false },
  dataDetailed:    { type: Boolean, default: false },
  selectedCurrency:{ type: String,  default: '' },
  selectedTab:     { type: String,  default: '' },
  totalTransactions:{ type: Number, required: true },
  itemsPerPage:    { type: Number,  required: true },
  page:            { type: Number,  required: true },
});

const emit = defineEmits(['update:options', 'update:selectedTab']);

const headers = [
  { title: 'Id',          key: 'id',               sortable: false },
  { title: 'Usuario',     key: 'user_name',         sortable: false },
  { title: 'Descripción', key: 'description',       sortable: false },
  { title: 'Tipo',        key: 'type',              sortable: false },
  { title: 'Monto',       key: 'amount',            sortable: false },
  { title: 'Balance',     key: 'balance',           sortable: false },
  { title: 'Categoría',   key: 'category_name',     sortable: false },
  { title: 'Fecha',       key: 'transaction_date',  sortable: false },
];

// Siempre agrupa las transacciones por fecha
const groupedByDay = computed(() => {
  if (!Array.isArray(props.transactions)) return null;

  const map = {};
  for (const t of props.transactions) {
    const day = t.transaction_date?.slice(0, 10) ?? 'Sin fecha';
    if (!map[day]) map[day] = { date: day, items: [], totalInUsd: 0, totalOutUsd: 0 };
    
    map[day].items.push(t);
    
    // Cálculo centralizado en USD para el resumen del día
    // Si la moneda es USD el rate no importa, sino usamos exchange_rate
    const rate = parseFloat(t.exchange_rate) || 1;
    const amountUsd = t.currency === 'USD' ? (parseFloat(t.amount) || 0) : ((parseFloat(t.amount) || 0) / rate);
    
    if (t.movement_type === 'IN') {
      map[day].totalInUsd += amountUsd;
    } else {
      map[day].totalOutUsd += amountUsd;
    }
  }

  return Object.values(map).sort((a, b) => b.date.localeCompare(a.date));
});
</script>

<template>
  <VCard>
    <div v-if="props.loading" class="pa-6 text-center">
      <VProgressCircular indeterminate color="primary" />
    </div>
    
    <div v-else-if="groupedByDay && groupedByDay.length > 0">
      <div
        v-for="group in groupedByDay"
        :key="group.date"
        class="mb-2"
      >
        <!-- Encabezado del día con totales centralizados en USD -->
        <div class="d-flex align-center justify-space-between px-4 py-2 bg-grey-lighten-4">
          <span class="font-weight-bold text-body-2">{{ group.date }}</span>
          <div class="d-flex gap-3 text-caption">
            <span class="font-weight-medium">{{ group.items.length }} mov.</span>
            <span class="text-success font-weight-bold">
              ↑ {{ formatCurrency(group.totalInUsd, 'USD') }}
            </span>
            <span class="text-error font-weight-bold">
              ↓ {{ formatCurrency(group.totalOutUsd, 'USD') }}
            </span>
            <span class="font-weight-black text-primary">
              Neto: {{ formatCurrency(group.totalInUsd - group.totalOutUsd, 'USD') }}
            </span>
          </div>
        </div>

        <!-- Tabla del día -->
        <VTable density="compact" class="text-no-wrap border-b">
          <thead>
            <tr>
              <th v-for="h in headers" :key="h.key" class="text-uppercase text-caption font-weight-bold">
                {{ h.title }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in group.items" :key="item.id">
              <td class="text-caption">#{{ item.id }}</td>
              <td class="text-caption">{{ item.user_name }}</td>
              <td class="text-truncate" style="max-inline-size: 250px;">{{ item.description }}</td>
              <td>
                <VChip size="x-small" label variant="tonal" color="secondary">
                  {{ item.type }}
                </VChip>
              </td>
              <td class="font-weight-bold">
                <span :class="item.movement_type === 'IN' ? 'text-success' : 'text-error'">
                  {{ formatCurrency(parseFloat(item.amount), item.currency) }}
                </span>
              </td>
              <td class="text-medium-emphasis">
                {{ formatCurrency(parseFloat(item.balance), item.currency) }}
              </td>
              <td class="text-caption">{{ item.category_name }}</td>
              <td class="text-caption">{{ item.transaction_date }}</td>
            </tr>
          </tbody>
        </VTable>
      </div>

      <!-- Paginación -->
      <div class="d-flex justify-end pa-4">
        <VPagination
          :model-value="props.page"
          :length="Math.ceil(props.totalTransactions / props.itemsPerPage)"
          density="compact"
          total-visible="7"
          @update:model-value="(p) => emit('update:options', { page: p, itemsPerPage: props.itemsPerPage })"
        />
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="pa-12 text-center text-medium-emphasis">
      <VIcon icon="tabler-database-x" size="48" class="mb-2" />
      <p>No se encontraron movimientos para este periodo.</p>
    </div>
  </VCard>
</template>

<style scoped>
.v-table th {
  block-size: 40px !important;
}
</style>
