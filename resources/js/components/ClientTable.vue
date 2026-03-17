<script setup lang="js">
import day from "dayjs";
import { computed } from "vue";

const props = defineProps({
  clients: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalClients: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  sortBy: { type: String, default: undefined },
  orderBy: { type: String, default: undefined },
})

const sortByModel = computed(() => {
  if (props.sortBy) {
    return [{ key: props.sortBy, order: props.orderBy || 'asc' }]
  }
  return []
})

const emit = defineEmits(["edit", 'delete', 'update:options', 'view-stats'])

const clientTypeColor = (type) => {
  const map = {
    'VIP': 'warning',
    'Frecuente': 'success',
    'En Riesgo': 'error',
    'Ocasional': 'info',
    'Nuevo': 'secondary',
  }
  return map[type] || 'secondary'
}

const headers = [
  { title: 'ID', key: 'id', sortable: true },
  { title: 'Nombre', key: 'name', value: item => `${item.name} ${(item.last_name == null) ? "" : item.last_name}`, sortable: true },
  { title: 'Identidad', key: 'identification', value: item => `${item.identification_type}${item.identification}`, sortable: true },
  { title: 'Empresa', key: 'company.name', sortable: false },
  { title: 'Tipo', key: 'client_type', sortable: true },
  { title: 'Dirección', key: 'address', sortable: true },
  { 
    title: 'Fecha', 
    key: 'created_at', 
    sortable: true, 
    value: item => {
      const fechaStr = item.created_at.replace('Z', '');
      return day(fechaStr).format('DD/MM/YYYY');
    }
  },
  { title: 'Acciones', key: 'acciones', sortable: false, align: 'center' },
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
  <div class="client-table-container">
    <!-- Desktop View -->
    <div class="d-none d-md-block">
      <VCard border variant="flat">
        <VDataTableServer
          :headers="headers"
          :items-per-page="props.itemsPerPage"
          :items="props.clients"
          :items-length="props.totalClients"
          :loading="loading"
          :page="props.page"
          :sort-by="sortByModel"
          @update:options="(options) => emit('update:options', options)"
        >
          <template #item.id="{ item }">
            <span class="font-weight-black text-primary">{{ item.id }}</span>
          </template>

          <template #item.client_type="{ item }">
            <VChip
              v-if="item.client_type"
              :color="clientTypeColor(item.client_type)"
              size="x-small"
              variant="flat"
              class="font-weight-black"
            >
              {{ item.client_type }}
            </VChip>
            <span v-else class="text-disabled">—</span>
          </template>

          <template #item.acciones="{ item }">
            <div class="d-flex justify-center gap-1">
              <IconBtn @click="emit('view-stats', item.id)" color="info" variant="tonal" size="small">
                <VIcon icon="tabler-eye" size="18" />
              </IconBtn>
              <IconBtn @click="emit('edit', item.id)" color="warning" variant="tonal" size="small">
                <VIcon icon="tabler-edit" size="18" />
              </IconBtn>
              <IconBtn @click="emit('delete', item.id)" color="error" variant="tonal" size="small">
                <VIcon icon="tabler-trash" size="18" />
              </IconBtn>
            </div>
          </template>
        </VDataTableServer>
      </VCard>
    </div>

    <!-- Mobile View (Premium Cards) -->
    <div class="d-block d-md-none pa-2 bg-light">
      <VLinearProgress v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.clients.length === 0 && !props.loading" class="text-center py-8 text-disabled">
        No se encontraron clientes registrados.
      </div>

      <div class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.clients"
          :key="item.id"
          variant="flat"
          class="border mb-1 overflow-hidden premium-card"
        >
          <div class="pa-4">
            <div class="d-flex justify-space-between align-start mb-2">
              <div class="d-flex flex-column">
                <span class="text-primary font-weight-black text-xs">#{{ item.id }}</span>
                <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight mt-1">
                  {{ item.name }} {{ item.last_name || '' }}
                </h3>
              </div>
              <div class="d-flex gap-1">
                <VBtn
                  icon="tabler-eye"
                  variant="tonal"
                  color="info"
                  size="x-small"
                  @click="emit('view-stats', item.id)"
                />
                <VBtn
                  icon="tabler-edit"
                  variant="tonal"
                  color="warning"
                  size="x-small"
                  @click="emit('edit', item.id)"
                />
                <VBtn
                  icon="tabler-trash"
                  variant="tonal"
                  color="error"
                  size="x-small"
                  @click="emit('delete', item.id)"
                />
              </div>
            </div>

            <VDivider class="my-2 border-opacity-10" />

            <div class="d-grid mobile-grid gap-2">
              <div class="stat-box">
                <span class="label">Identidad</span>
                <span class="value">{{ item.identification_type }}{{ item.identification }}</span>
              </div>
              <div class="stat-box">
                <span class="label">Empresa</span>
                <span class="value text-primary truncate">{{ item.company?.name || 'S/E' }}</span>
              </div>
              <div class="stat-box text-right">
                <span class="label">Tipo</span>
                <VChip
                  v-if="item.client_type"
                  :color="clientTypeColor(item.client_type)"
                  size="x-small"
                  variant="flat"
                  class="font-weight-black"
                >
                  {{ item.client_type }}
                </VChip>
              </div>
            </div>

            <div class="mt-3 bg-var-theme-background-light rounded pa-2 d-flex align-center gap-2">
              <VIcon icon="tabler-map-pin" size="14" class="text-disabled" />
              <span class="text-super-xs text-medium-emphasis truncate">{{ item.address || 'Sin dirección' }}</span>
            </div>
          </div>
        </VCard>
      </div>

      <!-- Mobile Pagination -->
      <div class="d-flex justify-center mt-4 pb-2">
        <VPagination
          :model-value="props.page"
          :length="Math.ceil(props.totalClients / props.itemsPerPage)"
          :total-visible="3"
          density="compact"
          size="small"
          @update:model-value="handleMobilePageChange"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.7rem !important;
}

.bg-light {
  background-color: rgba(var(--v-border-color), 0.02) !important;
}

.bg-var-theme-background-light {
  background-color: rgba(var(--v-border-color), 0.05);
}

.premium-card {
  border-radius: 12px !important;
}

.mobile-grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
}

.stat-box .label {
  display: block;
  font-size: 0.6rem;
  font-weight: 900;
  color: rgba(var(--v-theme-on-surface), 0.45);
  text-transform: uppercase;
}

.stat-box .value {
  font-size: 0.75rem;
  font-weight: 800;
  text-transform: uppercase;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.leading-tight {
  line-height: 1.2 !important;
}

.gap-1 { gap: 4px; }
.gap-2 { gap: 8px; }
.gap-3 { gap: 12px; }
</style>
