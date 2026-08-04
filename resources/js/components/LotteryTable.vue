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

const headers = [
  { title: 'ID',                        key: 'id', sortable: true},
  { title: 'IDENTIFICACIÓN',           key: 'identification', sortable: false },
  { title: 'CLIENTE',                  key: 'client_name', sortable: false },
  { title: 'VENDEDOR',                 key: 'seller_username', sortable: false },
  { title: 'MONTO USD',               key: 'total_amount_usd', sortable: true, align: 'end'},
  { title: 'MONEDA',                   key: 'currency', sortable: true, align: 'center'},
  { title: 'FECHA',                    key: 'created_at', sortable: true },
];

const emit= defineEmits(["update:options"])
</script>

<template>
  <div class="lottery-table-container">
    <!-- Desktop View -->
    <VCard class="d-none d-md-block rounded-lg border-0 shadow-sm overflow-hidden">
      <VDataTableServer
        :headers="headers"
        :items-per-page="props.itemsPerPage"
        :items="props.items"
        :items-length="props.total"
        :loading="loading"
        :page="props.page"
        :sort-by="sortByModel"
        class="premium-table text-no-wrap"
        density="compact"
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
          <span class="font-weight-black text-primary">{{ item.id }}</span>
        </template>

        <template #item.identification="{ item }">
          <span class="text-sm font-weight-black text-high-emphasis">
            {{ item.client?.identification_type || '' }}{{ item.client?.identification || 'N/A' }}
          </span>
        </template>

        <template #item.client_name="{ item }">
          <span class="text-sm font-weight-black text-high-emphasis uppercase">
            {{ (item.client?.name || '') }} {{ (item.client?.last_name || '') }}
          </span>
        </template>

        <template #item.seller_username="{ item }">
          <span class="text-sm font-weight-black text-disabled uppercase">
            {{ item.seller?.username || 'S/V' }}
          </span>
        </template>

        <template #item.total_amount_usd="{ item }">
          <span class="text-sm font-weight-black text-success">
            ${{ item.total_amount_usd ? Number(item.total_amount_usd).toFixed(2) : '0.00' }}
          </span>
        </template>

        <template #item.currency="{ item }">
          <VChip size="x-small" variant="tonal" :color="item.currency === 'USD' ? 'success' : item.currency === 'BS' ? 'info' : 'warning'" class="font-weight-black rounded">
            {{ item.currency }}
          </VChip>
        </template>

        <template #item.created_at="{ item }">
          <span class="text-sm font-weight-black text-disabled uppercase">
            {{ day(item.created_at.replace('Z', '')).format('DD/MM/YYYY') }}
          </span>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Mobile View -->
    <div class="d-md-none">
      <VDataIterator
        :items="props.items"
        :items-length="props.total"
        :loading="props.loading"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #no-data>
          <AppEmptyState
            title="No hay órdenes disponibles"
            message="No se encontraron órdenes registradas que cumplan con los filtros seleccionados."
            icon="tabler-ticket-off"
          />
        </template>
        <template #default="{ items }">
          <VRow dense>
            <VCol v-for="item in items" :key="item.id" cols="12" class="mb-4">
              <VCard class="premium-card rounded-lg border shadow-sm overflow-hidden h-100 pb-2">
                <div class="pa-4">
                  <div class="d-flex justify-space-between align-center mb-3">
                    <div class="d-flex align-center gap-1">
                      <span class="text-primary font-weight-black text-xs">{{ item.raw.id }}</span>
                      <span class="text-disabled mx-1">|</span>
                      <h3 class="text-sm font-weight-black text-high-emphasis uppercase mb-0">
                        {{ (item.raw.client?.name || '') + " " + (item.raw.client?.last_name || '') }}
                      </h3>
                    </div>
                    <VChip size="x-small" variant="tonal" :color="item.raw.currency === 'USD' ? 'success' : 'info'" class="font-weight-black rounded">
                      {{ item.raw.currency }}
                    </VChip>
                  </div>

                  <div class="d-flex flex-column gap-2 mb-3">
                    <div class="d-flex justify-space-between align-center">
                      <span class="text-super-xs font-weight-bold text-disabled uppercase">IDENTIFICACIÓN:</span>
                      <span class="text-xs font-weight-black text-high-emphasis">{{ item.raw.client?.identification_type }}{{ item.raw.client?.identification }}</span>
                    </div>
                    <div class="d-flex justify-space-between align-center">
                      <span class="text-super-xs font-weight-bold text-disabled uppercase">MONTO:</span>
                      <span class="text-xs font-weight-black text-success">${{ Number(item.raw.total_amount_usd).toFixed(2) }}</span>
                    </div>
                  </div>

                  <VDivider class="border-dashed my-3" />

                  <div class="d-flex justify-space-between align-center">
                    <div class="d-flex flex-column gap-1">
                      <span class="text-super-xs font-weight-black text-disabled d-flex align-center gap-1">
                        <VIcon icon="tabler-calendar" size="12" /> {{ day(item.raw.created_at.replace('Z', '')).format('DD/MM/YYYY') }}
                      </span>
                      <span class="text-super-xs font-weight-black text-disabled d-flex align-center gap-1">
                        <VIcon icon="tabler-user" size="12" /> {{ item.raw.seller?.username || 'N/A' }}
                      </span>
                    </div>
                  </div>
                </div>
              </VCard>
            </VCol>
          </VRow>
        </template>
      </VDataIterator>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(thead th) {
  background-color: white !important;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.05rem !important;
  text-transform: uppercase !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.1) !important;
}

.premium-table :deep(td) {
  padding-block: 12px !important;
  color: #334155 !important;
}

.premium-card {
  transition: all 0.3s ease;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.shadow-sm {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
}

.border-dashed {
  border-style: dashed !important;
  opacity: 0.4;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
</style>
