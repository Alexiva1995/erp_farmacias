<script setup lang="js">
import day from "dayjs";

const props = defineProps({
  items: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  total: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
})

const emit = defineEmits(["edit", 'delete', 'verClientes', "update:options"])

const headers = [
  { title: 'ID', key: 'id', sortable: true },
  { title: 'Nombre', key: 'name', sortable: true },
  { title: 'Tipo', key: 'type_company', sortable: true },
  { title: 'Identificación', key: 'identification', sortable: true },
  { title: 'Dirección', key: 'address', sortable: false },
  { title: 'Total Clientes', key: 'clients', sortable: false, value: items => items.clients.length },
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
  <div class="company-table-container">
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
          @update:options="(options) => emit('update:options', options)"
        >
          <template #item.id="{ item }">
            <span class="font-weight-black text-primary">{{ item.id }}</span>
          </template>

          <template #item.type_company="{ item }">
            <VChip size="x-small" :color="item.type_company === 'Clinica' ? 'info' : 'success'" variant="flat" class="font-weight-black">
              {{ item.type_company }}
            </VChip>
          </template>

          <template #item.acciones="{ item }">
            <div class="d-flex justify-center gap-1">
              <IconBtn @click="emit('verClientes', item.id)" color="secondary" variant="tonal" size="small">
                <VIcon icon="tabler-users" size="18" />
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
      
      <div v-if="props.items.length === 0 && !props.loading" class="text-center py-8 text-disabled">
        No se encontraron empresas registradas.
      </div>

      <div class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.items"
          :key="item.id"
          variant="flat"
          class="border mb-1 overflow-hidden premium-card"
        >
          <div class="pa-4">
            <div class="d-flex justify-space-between align-start mb-2">
              <div class="d-flex flex-column">
                <span class="text-primary font-weight-black text-xs">#{{ item.id }}</span>
                <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight mt-1">
                  {{ item.name }}
                </h3>
              </div>
              <div class="d-flex gap-1">
                <VBtn
                  icon="tabler-users"
                  variant="tonal"
                  color="secondary"
                  size="x-small"
                  @click="emit('verClientes', item.id)"
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
                <span class="label">Identificación</span>
                <span class="value">{{ item.identification }}</span>
              </div>
              <div class="stat-box">
                <span class="label">Tipo</span>
                <VChip size="x-small" :color="item.type_company === 'Clinica' ? 'info' : 'success'" variant="flat" class="font-weight-black">
                  {{ item.type_company }}
                </VChip>
              </div>
              <div class="stat-box text-right">
                <span class="label">Clientes</span>
                <span class="value text-primary">{{ item.clients?.length || 0 }}</span>
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
          :length="Math.ceil(props.total / props.itemsPerPage)"
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
