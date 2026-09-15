<script setup lang="js">
import day from 'dayjs';
import { computed } from 'vue';
import { useDisplay } from 'vuetify';
import AppEmptyState from "@/components/AppEmptyState.vue";

const props= defineProps({
  items: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  total: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  sortBy: { type: String, default: undefined },
  orderBy: { type: String, default: undefined },
})

const { mobile } = useDisplay();

const sortByModel = computed(() => {
  if (props.sortBy) {
    return [{ key: props.sortBy, order: props.orderBy || 'asc' }]
  }
  return []
})

const toTitleCase = (str) => {
  if (!str) return '—';
  return str.toLowerCase().replace(/(?:^|\s|-)\S/g, (char) => char.toUpperCase());
};

const headers = [
  { title: 'ID',                        key: 'id', sortable: true},
  { title: 'IDENTIFICACIÓN',           key: 'identification', sortable: false },
  { title: 'CLIENTE',                  key: 'client_name', sortable: false },
  { title: 'VENDEDOR',                 key: 'seller_username', sortable: false },
  { title: 'MONTO USD',               key: 'total_amount_usd', sortable: true, align: 'end'},
  { title: 'MONEDA',                   key: 'currency', sortable: true, align: 'center'},
  { title: 'FECHA',                    key: 'created_at', sortable: true },
];

const handleMobilePageChange = (newPage) => {
  emit('update:options', {
    page: newPage,
    itemsPerPage: props.itemsPerPage,
    sortBy: [],
  });
};
</script>

<template>
  <div class="lottery-table-container">
    <!-- Desktop View -->
    <div class="d-none d-md-block">
      <VCard border variant="flat">
        <VDataTableServer
          :headers="headers"
          :items-per-page="props.itemsPerPage"
          :items="props.items"
          :items-length="props.total"
          :loading="loading"
          :page="props.page"
          :sort-by="sortByModel"
          density="comfortable"
          @update:options="(options) => emit('update:options', options)"
        >
          <template #no-data>
            <AppEmptyState
              title="No hay órdenes disponibles"
              message="No se encontraron órdenes registradas que cumplan con los filtros seleccionados."
              icon="tabler-ticket-off"
            />
          </template>
          <template #item.id="{ item }">
            <span class="font-weight-bold text-primary">{{ item.id }}</span>
          </template>

          <template #item.identification="{ item }">
            <span class="font-weight-semibold text-high-emphasis">
              {{ item.client?.identification_type || '' }}{{ item.client?.identification || 'N/A' }}
            </span>
          </template>

          <template #item.client_name="{ item }">
            <span class="font-weight-medium text-high-emphasis">
              {{ toTitleCase((item.client?.name || '') + ' ' + (item.client?.last_name || '')) }}
            </span>
          </template>

          <template #item.seller_username="{ item }">
            <span class="text-xs text-medium-emphasis">
              {{ item.seller?.username || 'S/V' }}
            </span>
          </template>

          <template #item.total_amount_usd="{ item }">
            <span class="font-weight-semibold text-success">
              ${{ item.total_amount_usd ? Number(item.total_amount_usd).toFixed(2) : '0.00' }}
            </span>
          </template>

          <template #item.currency="{ item }">
            <VChip size="x-small" variant="tonal" :color="item.currency === 'USD' ? 'success' : item.currency === 'BS' ? 'info' : 'warning'" class="font-weight-bold rounded">
              {{ item.currency }}
            </VChip>
          </template>

          <template #item.created_at="{ item }">
            <span class="text-xs text-medium-emphasis">
              {{ item.created_at ? day(item.created_at.replace('Z', '')).format('DD/MM/YYYY') : '—' }}
            </span>
          </template>
        </VDataTableServer>
      </VCard>
    </div>

    <!-- Mobile View (Cards) -->
    <div class="d-block d-md-none pa-2 bg-light">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />

      <AppEmptyState
        v-if="props.items.length === 0 && !props.loading"
        title="No hay órdenes disponibles"
        message="No se encontraron órdenes registradas que cumplan con los filtros seleccionados."
        icon="tabler-ticket-off"
      />

      <div class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.items"
          :key="item.id"
          variant="flat"
          border
          class="mb-1 overflow-hidden premium-card bg-white"
        >
          <div class="pa-4">
            <div class="d-flex justify-space-between align-start mb-3">
              <div class="d-flex flex-column min-width-0">
                <span class="text-primary font-weight-black text-xs uppercase mb-1">Orden #{{ item.id }}</span>
                <h3 class="text-sm font-weight-semibold text-high-emphasis leading-tight truncate">
                  {{ toTitleCase((item.client?.name || '') + " " + (item.client?.last_name || '')) }}
                </h3>
                <div class="d-flex align-center gap-1 mt-1">
                  <VIcon icon="tabler-id" size="14" class="text-primary me-1" />
                  <span class="text-xs font-weight-medium text-medium-emphasis">
                    {{ item.client?.identification_type || '' }}{{ item.client?.identification || 'N/A' }}
                  </span>
                </div>
              </div>
              <div>
                <VChip size="x-small" variant="tonal" :color="item.currency === 'USD' ? 'success' : 'info'" class="font-weight-bold rounded">
                  {{ item.currency }}
                </VChip>
              </div>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <div class="d-grid mobile-info-grid gap-3">
              <div class="stat-box">
                <span class="label">Monto USD</span>
                <span class="value text-success font-weight-bold">${{ Number(item.total_amount_usd || 0).toFixed(2) }}</span>
              </div>
              <div class="stat-box text-center">
                <span class="label">Vendedor</span>
                <span class="value text-medium-emphasis text-xs">{{ item.seller?.username || 'S/V' }}</span>
              </div>
              <div class="stat-box text-right">
                <span class="label">Fecha</span>
                <span class="value text-disabled text-xs">{{ item.created_at ? day(item.created_at.replace('Z', '')).format('DD/MM/YYYY') : '—' }}</span>
              </div>
            </div>
          </div>
        </VCard>
      </div>

      <!-- Mobile Pagination -->
      <div class="d-flex justify-center mt-4 pb-2">
        <AppMobilePagination
          :page="props.page"
          :items-per-page="props.itemsPerPage"
          :total-items="props.total"
          :loading="props.loading"
          @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.bg-light {
  background-color: #f8fafc !important;
}

.premium-card {
  border-radius: 12px !important;
  transition: transform 0.2s ease;
}

.premium-card:active {
  transform: scale(0.98);
}

.mobile-info-grid {
  display: grid;
  align-items: center;
  grid-template-columns: 1fr 1fr 1fr;
}

.stat-box .label {
  display: block;
  color: rgba(var(--v-theme-on-surface), 0.45);
  font-size: 0.6rem;
  font-weight: 900;
  margin-block-end: 2px;
  text-transform: uppercase;
}

.stat-box .value {
  font-size: 0.75rem;
  font-weight: 800;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.leading-tight {
  line-height: 1.25 !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }
</style>
