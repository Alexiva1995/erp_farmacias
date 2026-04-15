<script setup lang="js">
import day from "dayjs";
import { computed } from "vue";

const props= defineProps({
  companyId: { type: [String, Number], required: true },
  clients: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalClients: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  sortBy: { type: String, default: undefined },
  orderBy: { type: String, default: undefined },
})

const emit= defineEmits(["edit",'delete','update:options','remove-from-company'])

const sortByModel = computed(() => {
  if (props.sortBy) {
    return [{ key: props.sortBy, order: props.orderBy || 'asc' }]
  }
  return []
})

const headers = [
  { title: 'ID', key: 'id', sortable: true },
  { title: 'Nombre', key: 'name', value: item => `${item.name} ${(item.last_name==null)?"":item.last_name}`, sortable: true },
  { title: 'Identidad', key: 'identification', value: item => `${item.identification_type}${item.identification}`, sortable: true },
  { title: 'Dirección', key: 'address', sortable: true },
  { title: 'Fecha', key: 'created_at', sortable: true, value: item => {
    const fechaStr = item.created_at.replace('Z', '');
    return day(fechaStr).format('DD/MM/YYYY');
  }},
  { title: 'Acciones', key: 'acciones', sortable: false },
];
</script>

<template>
  <div class="clients-company-table-container">
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

          <template #item.acciones="{ item }">
            <div class="d-flex justify-start gap-1">
              <IconBtn @click="emit('edit', item.id)" color="warning" variant="tonal" size="small">
                <VIcon icon="tabler-edit" size="18" />
              </IconBtn>
              <IconBtn @click="emit('delete', item.id)" color="error" variant="tonal" size="small">
                <VIcon icon="tabler-trash" size="18" />
              </IconBtn>
              <VTooltip text="Quitar de la empresa" location="top">
                <template #activator="{ props: tooltipProps }">
                  <IconBtn
                    v-bind="tooltipProps"
                    @click="emit('remove-from-company', item.id)"
                    color="secondary"
                    variant="tonal"
                    size="small"
                  >
                    <VIcon icon="tabler-unlink" size="18" />
                  </IconBtn>
                </template>
              </VTooltip>
            </div>
          </template>
        </VDataTableServer>
      </VCard>
    </div>

    <!-- Mobile View (Premium Cards) -->
    <div class="d-block d-md-none pa-2 bg-light">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.clients.length === 0 && !props.loading" class="text-center py-8 text-disabled uppercase font-weight-bold">
        No hay clientes asociados.
      </div>

      <div class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.clients"
          :key="item.id"
          variant="flat"
          border
          class="mb-1 overflow-hidden premium-card bg-white"
        >
          <div class="pa-4">
            <div class="d-flex justify-space-between align-start mb-3">
              <div class="d-flex flex-column min-width-0">
                <span class="text-primary font-weight-black text-xs uppercase mb-1">CLIENTE ASOCIADO</span>
                <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight truncate">
                  {{ item.name }} {{ item.last_name || '' }}
                </h3>
              </div>
              <div class="d-flex gap-1">
                <IconBtn
                  color="warning"
                  variant="tonal"
                  size="x-small"
                  @click="emit('edit', item.id)"
                >
                  <VIcon icon="tabler-edit" size="16" />
                </IconBtn>
                <IconBtn
                  color="error"
                  variant="tonal"
                  size="x-small"
                  @click="emit('delete', item.id)"
                >
                  <VIcon icon="tabler-trash" size="16" />
                </IconBtn>
                <IconBtn
                  color="secondary"
                  variant="tonal"
                  size="x-small"
                  @click="emit('remove-from-company', item.id)"
                >
                  <VIcon icon="tabler-unlink" size="16" />
                </IconBtn>
              </div>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <div class="d-grid mobile-grid gap-3">
              <div class="stat-box">
                <span class="label">IDENTIDAD</span>
                <span class="value font-weight-black text-xs">{{ item.identification_type }}{{ item.identification }}</span>
              </div>
              <div class="stat-box text-right">
                <span class="label">ID SISTEMA</span>
                <span class="value text-primary font-weight-black text-xs">#{{ item.id }}</span>
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
  
  .mobile-grid {
    display: grid;
    align-items: center;
    grid-template-columns: 1fr 1fr;
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
