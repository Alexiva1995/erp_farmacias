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
  { title: 'ID',          key: 'id',               sortable: false, align: 'start' },
  { title: 'USUARIO',     key: 'user_name',         sortable: false },
  { title: 'DESCRIPCIÓN', key: 'description',       sortable: false },
  { title: 'TIPO',        key: 'type',              sortable: false, align: 'center' },
  { title: 'MONTO',       key: 'amount',            sortable: false, align: 'end' },
  { title: 'BALANCE',     key: 'balance',           sortable: false, align: 'end' },
  { title: 'CATEGORÍA',   key: 'category_name',     sortable: false },
  { title: 'FECHA',       key: 'transaction_date',  sortable: false },
];

const groupedByDay = computed(() => {
  if (!Array.isArray(props.transactions)) return [];

  const map = {};
  for (const t of props.transactions) {
    const day = t.transaction_date?.slice(0, 10) ?? 'Sin fecha';
    if (!map[day]) map[day] = { date: day, items: [], totalInUsd: 0, totalOutUsd: 0 };
    
    map[day].items.push(t);
    
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
  <div class="transactions-table-container">
    <div v-if="props.loading" class="pa-12 text-center">
      <VProgressCircular indeterminate color="primary" size="48" />
      <p class="text-caption text-disabled mt-4 font-weight-black uppercase">Cargando movimientos...</p>
    </div>
    
    <div v-else-if="groupedByDay.length > 0">
      <div
        v-for="group in groupedByDay"
        :key="group.date"
        class="mb-8"
      >
        <!-- Encabezado del día Premium -->
        <VCard class="rounded-xl border shadow-sm mb-4 overflow-hidden">
          <div class="d-flex flex-wrap align-center justify-space-between px-5 py-3 bg-surface-variant-light gap-3">
            <div class="d-flex align-center gap-2">
              <VAvatar color="primary" variant="tonal" size="32" class="rounded-lg">
                <VIcon icon="tabler-calendar" size="18" />
              </VAvatar>
              <span class="font-weight-black text-h6">{{ group.date }}</span>
            </div>
            
            <div class="d-flex flex-wrap gap-x-6 gap-y-2">
              <div class="d-flex flex-column align-end">
                <span class="text-super-xs font-weight-black text-disabled uppercase">Entradas</span>
                <span class="text-sm font-weight-black text-success">↑ {{ formatCurrency(group.totalInUsd, 'USD') }}</span>
              </div>
              <div class="d-flex flex-column align-end">
                <span class="text-super-xs font-weight-black text-disabled uppercase">Salidas</span>
                <span class="text-sm font-weight-black text-error">↓ {{ formatCurrency(group.totalOutUsd, 'USD') }}</span>
              </div>
              <div class="d-flex flex-column align-end bg-white px-4 py-1 rounded-lg shadow-inner border border-dashed">
                <span class="text-super-xs font-weight-black text-primary uppercase">Neto Diario</span>
                <span class="text-base font-weight-black text-primary">
                  {{ formatCurrency(group.totalInUsd - group.totalOutUsd, 'USD') }}
                </span>
              </div>
            </div>
          </div>

          <!-- Vista Escritorio: Tabla -->
          <VTable v-if="!$vuetify.display.smAndDown" density="compact" class="premium-table text-no-wrap">
            <thead>
              <tr>
                <th v-for="h in headers" :key="h.key" :class="`text-${h.align || 'start'}`" class="text-uppercase text-super-xs font-weight-black text-disabled bg-surface">
                  {{ h.title }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in group.items" :key="item.id">
                <td class="text-caption font-weight-black text-primary">#{{ item.id }}</td>
                <td>
                  <div class="d-flex align-center gap-2">
                    <VAvatar size="24" color="secondary" variant="tonal" class="rounded-lg">
                      <span class="text-super-xs font-weight-black">{{ item.user_name?.charAt(0) }}</span>
                    </VAvatar>
                    <span class="text-xs font-weight-medium">{{ item.user_name }}</span>
                  </div>
                </td>
                <td class="text-truncate text-xs" style="max-inline-size: 200px;">{{ item.description }}</td>
                <td class="text-center">
                  <VChip size="x-small" variant="elevated" color="secondary" class="font-weight-black rounded-lg">
                    {{ item.type }}
                  </VChip>
                </td>
                <td class="font-weight-black text-right">
                  <span :class="item.movement_type === 'IN' ? 'text-success' : 'text-error'">
                    {{ formatCurrency(parseFloat(item.amount), item.currency) }}
                  </span>
                </td>
                <td class="text-medium-emphasis text-right font-weight-bold">
                  {{ formatCurrency(parseFloat(item.balance), item.currency) }}
                </td>
                <td class="text-caption font-weight-bold text-disabled">{{ item.category_name }}</td>
                <td class="text-caption">{{ item.transaction_date?.slice(11, 19) }}</td>
              </tr>
            </tbody>
          </VTable>

          <!-- Vista Móvil: Cards dentro del grupo -->
          <div v-else class="pa-2 d-flex flex-column gap-2">
            <VCard
              v-for="item in group.items"
              :key="item.id"
              variant="flat"
              class="border rounded-xl pa-3"
            >
              <div class="d-flex justify-space-between align-start mb-2">
                <div class="d-flex align-center gap-2">
                  <VAvatar :color="item.movement_type === 'IN' ? 'success' : 'error'" variant="tonal" size="32" class="rounded-lg">
                    <VIcon :icon="item.movement_type === 'IN' ? 'tabler-arrow-up-right' : 'tabler-arrow-down-left'" size="18" />
                  </VAvatar>
                  <div class="d-flex flex-column">
                    <span class="text-xs font-weight-black">#{{ item.id }}</span>
                    <span class="text-super-xs text-disabled font-weight-bold uppercase">{{ item.type }}</span>
                  </div>
                </div>
                <div class="text-right">
                  <div :class="['text-base font-weight-black', item.movement_type === 'IN' ? 'text-success' : 'text-error']">
                    {{ formatCurrency(parseFloat(item.amount), item.currency) }}
                  </div>
                  <div class="text-super-xs font-weight-bold text-disabled">Balance: {{ formatCurrency(parseFloat(item.balance), item.currency) }}</div>
                </div>
              </div>
              <div class="text-xs text-medium-emphasis mb-2 line-clamp-2">
                {{ item.description }}
              </div>
              <div class="d-flex justify-space-between align-center mt-1 pt-2 border-t border-dashed">
                <span class="text-super-xs font-weight-black text-disabled uppercase">{{ item.user_name }}</span>
                <span class="text-super-xs font-weight-bold text-disabled">{{ item.category_name }}</span>
              </div>
            </VCard>
          </div>
        </VCard>
      </div>

      <!-- Paginación Premium -->
      <VCard class="rounded-xl border shadow-sm pa-3 d-flex justify-center mt-6">
        <VPagination
          :model-value="props.page"
          :length="Math.ceil(props.totalTransactions / props.itemsPerPage)"
          density="comfortable"
          total-visible="5"
          active-color="primary"
          @update:model-value="(p) => emit('update:options', { page: p, itemsPerPage: props.itemsPerPage })"
        />
      </VCard>
    </div>

    <!-- Empty state -->
    <div v-else class="pa-16 text-center rounded-xl border-2 border-dashed">
      <VAvatar size="80" color="surface-variant" variant="tonal" class="mb-4">
        <VIcon icon="tabler-database-x" size="40" color="disabled" />
      </VAvatar>
      <h3 class="text-h6 font-weight-black text-disabled mb-1">Sin movimientos</h3>
      <p class="text-body-2 text-disabled">No se encontraron registros para los filtros seleccionados.</p>
    </div>
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 0.05);
}

.premium-table {
  :deep(th) {
    letter-spacing: 0.5px !important;
  }
}

.border-dashed {
  border-style: dashed !important;
}

.shadow-inner {
  box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.05) !important;
}
</style>
