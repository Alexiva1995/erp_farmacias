<script setup lang="js">
import day from 'dayjs';

const props = defineProps({
  items: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  total: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
})

const emit = defineEmits(["edit", 'delete', "update:options"])

const headers = [
  { title: 'ID', key: 'id', sortable: true },
  { title: 'Nombre', key: 'name', sortable: true },
  { title: 'Identificación', key: 'identification', sortable: true },
  { title: 'Dirección', key: 'address', sortable: false },
  { 
    title: 'Fecha', 
    key: 'created_at', 
    sortable: true, 
    value: item => {
      if (!item.created_at) return 'N/A';
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
  <div class="doctor-table-container">
    <!-- Desktop View -->
    <div class="d-none d-md-block">
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

        <template #item.name="{ item }">
          <span class="font-weight-bold uppercase text-high-emphasis">{{ item.name }}</span>
        </template>

        <template #item.acciones="{ item }">
          <div class="d-flex justify-center gap-1">
            <IconBtn @click="emit('edit', item.id)" color="warning" variant="tonal" size="small">
              <VIcon icon="tabler-edit" size="18" />
            </IconBtn>
            <IconBtn @click="emit('delete', item.id)" color="error" variant="tonal" size="small">
              <VIcon icon="tabler-trash" size="18" />
            </IconBtn>
          </div>
        </template>
      </VDataTableServer>
    </div>

    <!-- Mobile View (Premium Cards) -->
    <div class="d-block d-md-none pa-2 bg-light">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.items.length === 0 && !props.loading" class="text-center py-8 text-disabled font-weight-bold uppercase">
        No se encontraron especialistas registrados.
      </div>

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
                <span class="text-primary font-weight-black text-xs uppercase mb-1">Especialista</span>
                <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight truncate">
                  {{ item.name }}
                </h3>
              </div>
              <div class="d-flex gap-1">
                <IconBtn
                  color="warning"
                  variant="tonal"
                  size="x-small"
                  class="rounded"
                  @click="emit('edit', item.id)"
                >
                  <VIcon icon="tabler-edit" size="16" />
                </IconBtn>
                <IconBtn
                  color="error"
                  variant="tonal"
                  size="x-small"
                  class="rounded"
                  @click="emit('delete', item.id)"
                >
                  <VIcon icon="tabler-trash" size="16" />
                </IconBtn>
              </div>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <div class="d-grid mobile-info-grid gap-3">
              <div class="stat-box">
                <span class="label">ID Sistema</span>
                <span class="value text-primary font-weight-black">#{{ item.id }}</span>
              </div>
              <div class="stat-box text-center">
                <span class="label">Identificación</span>
                <span class="value text-medium-emphasis">{{ item.identification }}</span>
              </div>
              <div class="stat-box text-right">
                <span class="label">Fecha Reg.</span>
                <span class="value text-disabled">{{ day(item.created_at).format('DD/MM/YYYY') }}</span>
              </div>
            </div>

            <div class="mt-3 pa-2 bg-light rounded-lg border-dashed">
              <div class="d-flex align-start gap-2">
                <VIcon icon="tabler-map-pin" size="14" class="text-primary mt-1" />
                <span class="text-super-xs text-medium-emphasis leading-tight truncate-2-lines uppercase font-weight-bold">
                  {{ item.address || 'SIN DIRECCIÓN REGISTRADA' }}
                </span>
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

.truncate-2-lines {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.leading-tight {
  line-height: 1.25 !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }

.uppercase {
  text-transform: uppercase;
}

.border-dashed {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}
</style>
