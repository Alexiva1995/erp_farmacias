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
  { title: 'Día / Mov.',  key: 'direction',         sortable: false, align: 'center' },
  { title: 'ID',          key: 'id',                sortable: false, align: 'start' },
  { title: 'USUARIO',     key: 'user_name',         sortable: false },
  { title: 'DESCRIPCIÓN', key: 'description',       sortable: false },
  { title: 'TIPO',        key: 'type',              sortable: false, align: 'center' },
  { title: 'MONTO',       key: 'amount',            sortable: false, align: 'end' },
  { title: 'BALANCE CAJA',key: 'balance',           sortable: false, align: 'end' },
  { title: 'CATEGORÍA',   key: 'category_name',     sortable: false },
];

const processedTransactions = computed(() => {
  const raw = Array.isArray(props.transactions) ? props.transactions : (props.transactions?.data || []);
  return raw.map(t => {
    const isEntry = t.movement_type === 'IN' || t.amount > 0;
    return {
      ...t,
      isEntry,
      amount: Math.abs(parseFloat(t.amount) || 0),
      balance: parseFloat(t.balance) || 0,
    };
  });
});

const groupedByDay = computed(() => {
  const map = {};
  for (const t of processedTransactions.value) {
    const day = t.transaction_date?.slice(0, 10) ?? 'Sin fecha';
    if (!map[day]) map[day] = { date: day, items: [], totalInUsd: 0, totalOutUsd: 0 };
    
    map[day].items.push(t);
    
    const rate = parseFloat(t.exchange_rate) || 1;
    const amountUsd = t.currency === 'USD' ? t.amount : (t.amount / rate);
    
    if (t.isEntry) {
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
        class="mb-10"
      >
        <!-- Encabezado del día Premium -->
        <VCard class="rounded-xl border shadow-sm mb-4 overflow-hidden">
          <div class="d-flex flex-wrap align-center justify-space-between px-5 py-4 bg-surface-variant-light gap-4">
            <div class="d-flex align-center gap-3">
              <VAvatar color="primary" variant="elevated" size="40" class="rounded-lg shadow-sm">
                <VIcon icon="tabler-calendar-event" color="white" size="22" />
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-h6 font-weight-black leading-none">{{ group.date }}</span>
                <span class="text-super-xs text-disabled font-weight-bold uppercase mt-1">{{ group.items.length }} Movimientos registrados</span>
              </div>
            </div>
            
            <div class="d-flex flex-wrap gap-x-8 gap-y-2">
              <div class="d-flex flex-column align-end">
                <span class="text-super-xs font-weight-black text-disabled uppercase">Entradas (+)</span>
                <span class="text-base font-weight-black text-success">↑ {{ formatCurrency(group.totalInUsd, 'USD') }}</span>
              </div>
              <div class="d-flex flex-column align-end">
                <span class="text-super-xs font-weight-black text-disabled uppercase">Salidas (-)</span>
                <span class="text-base font-weight-black text-error">↓ {{ formatCurrency(group.totalOutUsd, 'USD') }}</span>
              </div>
              <div class="d-flex flex-column align-end bg-white px-5 py-2 rounded-xl shadow-inner border border-dashed border-primary">
                <span class="text-super-xs font-weight-black text-primary uppercase">Neto del Día</span>
                <span class="text-h6 font-weight-black text-primary">
                  {{ formatCurrency(group.totalInUsd - group.totalOutUsd, 'USD') }}
                </span>
              </div>
            </div>
          </div>

          <!-- Vista Escritorio: Tabla -->
          <VTable v-if="!$vuetify.display.smAndDown" density="comfortable" class="premium-table text-no-wrap">
            <thead>
              <tr class="bg-surface">
                <th v-for="h in headers" :key="h.key" :class="`text-${h.align || 'start'}`" class="text-uppercase text-super-xs font-weight-black text-disabled border-b px-4 py-3">
                  {{ h.title }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in group.items" :key="item.id" :class="item.isEntry ? 'row-in' : 'row-out'">
                <td class="text-center">
                  <VIcon 
                    :icon="item.isEntry ? 'tabler-circle-arrow-up' : 'tabler-circle-arrow-down'" 
                    :color="item.isEntry ? 'success' : 'error'" 
                    size="20"
                    class="opacity-80"
                  />
                </td>
                <td class="text-caption font-weight-black text-primary px-4">#{{ item.id }}</td>
                <td>
                  <div class="d-flex align-center gap-2">
                    <VAvatar size="28" color="secondary" variant="tonal" class="rounded-lg">
                      <span class="text-xs font-weight-black">{{ item.user_name?.charAt(0) }}</span>
                    </VAvatar>
                    <span class="text-xs font-weight-bold">{{ item.user_name }}</span>
                  </div>
                </td>
                <td class="text-truncate text-xs px-4" style="max-inline-size: 250px;">{{ item.description }}</td>
                <td class="text-center">
                  <VChip size="x-small" variant="tonal" :color="item.isEntry ? 'success' : 'error'" class="font-weight-black rounded-lg">
                    {{ item.type }}
                  </VChip>
                </td>
                <td class="text-right px-4">
                  <div :class="['text-base font-weight-black', item.isEntry ? 'text-success' : 'text-error']">
                    {{ item.isEntry ? '+' : '-' }} {{ formatCurrency(item.amount, item.currency) }}
                  </div>
                </td>
                <td class="text-right px-4 bg-surface-variant-light">
                  <div class="d-flex flex-column align-end">
                    <span class="text-base font-weight-black text-high-emphasis">
                      {{ formatCurrency(item.balance, item.currency) }}
                    </span>
                    <span class="text-super-xs text-disabled font-weight-bold uppercase">Balance Final</span>
                  </div>
                </td>
                <td class="text-caption font-weight-bold text-disabled px-4">{{ item.category_name }}</td>
              </tr>
            </tbody>
          </VTable>

          <!-- Vista Móvil: Cards dentro del grupo -->
          <div v-else class="pa-3 d-flex flex-column gap-3">
            <VCard
              v-for="item in group.items"
              :key="item.id"
              variant="flat"
              class="border rounded-xl px-4 py-3 bg-white shadow-xs"
              :class="item.isEntry ? 'border-success-subtle' : 'border-error-subtle'"
            >
              <div class="d-flex justify-space-between align-start mb-3">
                <div class="d-flex align-center gap-3">
                  <VAvatar :color="item.isEntry ? 'success' : 'error'" variant="tonal" size="40" class="rounded-xl">
                    <VIcon :icon="item.isEntry ? 'tabler-arrow-up-right' : 'tabler-arrow-down-left'" size="22" />
                  </VAvatar>
                  <div class="d-flex flex-column">
                    <span class="text-xs font-weight-black text-primary">ID: #{{ item.id }}</span>
                    <span class="text-super-xs text-disabled font-weight-black uppercase">{{ item.type }}</span>
                  </div>
                </div>
                <div class="text-right">
                  <div :class="['text-lg font-weight-black', item.isEntry ? 'text-success' : 'text-error']">
                    {{ item.isEntry ? '+' : '-' }} {{ formatCurrency(item.amount, item.currency) }}
                  </div>
                  <VChip size="x-small" variant="tonal" color="secondary" class="font-weight-bold mt-1">
                    {{ item.category_name }}
                  </VChip>
                </div>
              </div>
              
              <!-- Descripción -->
              <div class="text-sm text-medium-emphasis mb-4 bg-surface-variant-light pa-2 rounded-lg italic border border-dashed">
                "{{ item.description }}"
              </div>
              
              <!-- Footer de la Card con Balance Destacado -->
              <div class="d-flex justify-space-between align-center pt-3 border-t border-dashed">
                <div class="d-flex align-center gap-2">
                  <VAvatar size="20" color="secondary" variant="tonal" class="rounded-sm">
                    <span class="text-super-xs font-weight-black">{{ item.user_name?.charAt(0) }}</span>
                  </VAvatar>
                  <span class="text-super-xs font-weight-black text-disabled uppercase">{{ item.user_name }}</span>
                </div>
                <div class="d-flex flex-column align-end">
                  <span class="text-super-xs font-weight-black text-primary uppercase">Balance tras mov.</span>
                  <span class="text-sm font-weight-black text-high-emphasis">
                    {{ formatCurrency(item.balance, item.currency) }}
                  </span>
                </div>
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
  background-color: rgba(var(--v-theme-surface-variant), 0.04);
}

.premium-table {
  background: white !important;
}

.premium-table :deep(th) {
  height: 48px !important;
  border-bottom: 2px solid rgba(var(--v-theme-surface-variant), 0.1) !important;
}

.premium-table :deep(td) {
  height: 60px !important;
  border-bottom: 1px solid rgba(var(--v-theme-surface-variant), 0.05) !important;
}

.row-in:hover {
  background-color: rgba(var(--v-theme-success), 0.02);
}

.row-out:hover {
  background-color: rgba(var(--v-theme-error), 0.02);
}

.border-dashed {
  border-style: dashed !important;
}

.shadow-inner {
  box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.04) !important;
}

.border-success-subtle {
  border-left: 4px solid rgb(var(--v-theme-success)) !important;
}

.border-error-subtle {
  border-left: 4px solid rgb(var(--v-theme-error)) !important;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
