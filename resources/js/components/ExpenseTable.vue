<script setup lang="js">
import dayjs from 'dayjs';

const props = defineProps({
  items: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  total: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  statuModule: { type: Object, required: true },
});

const emit = defineEmits(['update:options', 'approve']);

function verImagne(item) {
  window.open(item.url_file, "_blank");
}

const headers = [
  { title: 'ID',             key: 'id',            sortable: true,  width: '70px' },
  { title: 'Descripción',     key: 'name',          sortable: true,  width: '250px' },
  { title: 'Categoría',       key: 'category.name', sortable: false, width: '150px' },
  { title: 'Monto',           key: 'amount',        sortable: true,  align: 'end', width: '150px' },
  { title: 'Estado',          key: 'status',        sortable: false, align: 'center', width: '120px' },
  { title: 'Fecha',           key: 'created_at',    sortable: true,  width: '100px' },
  { title: 'Acciones',        key: 'acciones',      sortable: false, align: 'center', width: '100px' },
];
</script>

<template>
  <VCard variant="flat" class="overflow-hidden">
    <!-- Vista Pro Desktop -->
    <VDataTableServer
      v-if="!$vuetify.display.smAndDown"
      :headers="headers"
      :items-per-page="props.itemsPerPage"
      :items="props.items"
      :items-length="props.total"
      :loading="loading"
      :page="props.page"
      hover
      class="premium-table"
      @update:options="(options) => emit('update:options', options)"
    >
      <!-- ID -->
      <template #item.id="{ item }">
        <span class="font-weight-black text-primary text-sm">{{ item.id }}</span>
      </template>

      <!-- Nombre / Descripción -->
      <template #item.name="{ item }">
        <div class="d-flex flex-column py-2" style="max-inline-size: 250px;">
          <span class="text-sm font-weight-black text-high-emphasis text-truncate" :title="item.name">
            {{ item.name }} {{ item.last_name || '' }}
          </span>
          <span v-if="item.description" class="text-super-xs font-weight-bold text-disabled text-truncate" :title="item.description">
            {{ item.description }}
          </span>
          <div class="d-flex align-center gap-1 mt-1">
            <VIcon icon="tabler-user" size="12" class="text-disabled" />
            <span class="text-super-xs font-weight-black text-disabled uppercase">{{ item.user?.username || 'S/U' }}</span>
            <span class="text-disabled">•</span>
            <span class="text-super-xs font-weight-black text-disabled uppercase">{{ item.count || 'N/A' }}</span>
          </div>
        </div>
      </template>

      <!-- Categoría -->
      <template #item.category.name="{ item }">
        <VChip size="x-small" color="primary" variant="tonal" class="rounded-lg font-weight-black px-2">
          <VIcon icon="tabler-tag" size="12" start />
          {{ item.category?.name || 'S/C' }}
        </VChip>
      </template>

      <!-- Monto -->
      <template #item.amount="{ item }">
        <div class="d-flex flex-column align-end py-2">
          <span class="text-sm font-weight-black text-error">
            {{ item.currency === 'USD' ? '$' : item.currency === 'BS' ? 'Bs.' : 'COP$' }}
            {{ Number(item.amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}
          </span>
          <span v-if="item.currency !== 'USD'" class="text-super-xs font-weight-black text-disabled">
            ≈ ${{ Number(item.total_usd || 0).toFixed(2) }}
          </span>
        </div>
      </template>

      <!-- Estado -->
      <template #item.status="{ item }">
        <VChip
          size="x-small"
          :color="item.status === 'Approved' ? 'success' : item.status === 'Cancelled' ? 'error' : 'warning'"
          variant="flat"
          class="font-weight-black uppercase px-2 rounded-lg"
        >
          {{ item.status === 'Pending' ? 'Pendiente' : item.status === 'Approved' ? 'Aprobado' : 'Cancelado' }}
        </VChip>
      </template>

      <!-- Fecha -->
      <template #item.created_at="{ item }">
        <span class="text-sm font-weight-black text-medium-emphasis">
          {{ dayjs(item.created_at.replace('Z', '')).format('DD/MM/YYYY') }}
        </span>
      </template>

      <!-- Acciones -->
      <template #item.acciones="{ item }">
        <div class="d-flex align-center justify-center gap-2">
          <VBtn
            icon
            size="32"
            variant="tonal"
            color="info"
            class="rounded-circle shadow-sm"
            :disabled="!item.url_file"
            @click="() => verImagne(item)"
          >
            <VIcon icon="tabler-eye" size="18" />
            <VTooltip activator="parent" location="top">Ver Recibo</VTooltip>
          </VBtn>
          
          <VBtn
            v-if="item.status === 'Pending' || item.status === 'Pendiente'"
            icon
            size="32"
            variant="tonal"
            color="success"
            class="rounded-circle shadow-sm"
            :disabled="statuModule.loadingItems.has(item.id)"
            @click="() => emit('approve', item.id)"
          >
            <VProgressCircular v-if="statuModule.loadingItems.has(item.id)" indeterminate size="18" width="2" />
            <VIcon v-else icon="tabler-circle-check" size="18" />
            <VTooltip activator="parent" location="top">Aprobar Gasto</VTooltip>
          </VBtn>
        </div>
      </template>
    </VDataTableServer>

    <!-- Vista Móvil: Cards -->
    <div v-else class="pa-4 bg-surface-variant-light">
      <div v-if="loading" class="text-center py-12">
        <VProgressCircular indeterminate color="primary" />
      </div>
      
      <template v-else-if="props.items.length > 0">
        <div class="d-flex flex-column gap-4">
          <VCard
            v-for="item in props.items"
            :key="item.id"
            variant="flat"
            class="rounded-lg border shadow-sm pa-4 bg-white position-relative overflow-hidden"
          >
            <!-- Línea de Estado lateral -->
            <div 
              class="position-absolute left-0 top-0 bottom-0 w-1"
              :class="item.status === 'Approved' ? 'bg-success' : item.status === 'Cancelled' ? 'bg-error' : 'bg-warning'"
            ></div>

            <div class="d-flex justify-space-between align-start mb-3 ml-2">
              <div class="d-flex align-center gap-2">
                <span class="text-sm font-weight-black text-primary">{{ item.id }}</span>
                <VChip
                  size="x-small"
                  :color="item.status === 'Approved' ? 'success' : item.status === 'Cancelled' ? 'error' : 'warning'"
                  variant="tonal"
                  class="font-weight-black"
                >
                  {{ item.status === 'Pending' ? 'Pendiente' : item.status === 'Approved' ? 'Aprobado' : 'Cancelado' }}
                </VChip>
              </div>
              <div class="text-right">
                <span class="text-sm font-weight-black text-error">
                  {{ item.currency === 'USD' ? '$' : item.currency === 'BS' ? 'Bs.' : 'COP$' }}
                  {{ Number(item.amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }}
                </span>
                <span v-if="item.currency !== 'USD'" class="text-super-xs font-weight-black text-disabled d-block">
                  ≈ ${{ Number(item.total_usd || 0).toFixed(2) }}
                </span>
              </div>
            </div>

            <div class="text-sm font-weight-black mb-1 ml-2">{{ item.name }}</div>
            <div v-if="item.description" class="text-xs text-disabled mb-3 ml-2 line-clamp-2">{{ item.description }}</div>

            <div class="d-flex justify-space-between align-center pt-3 border-t border-dashed ml-2">
              <div class="d-flex align-center gap-2">
                <VIcon icon="tabler-tag" size="14" class="text-primary" />
                <span class="text-super-xs font-weight-black uppercase">{{ item.category?.name || 'S/C' }}</span>
              </div>
              <span class="text-super-xs font-weight-black text-disabled">{{ dayjs(item.created_at.replace('Z', '')).format('DD/MM/YYYY') }}</span>
            </div>

            <div class="d-flex justify-end gap-2 mt-4 ml-2">
              <VBtn
                v-if="item.url_file"
                variant="tonal"
                color="info"
                size="small"
                class="rounded-lg font-weight-bold"
                prepend-icon="tabler-eye"
                @click="verImagne(item)"
              >
                Recibo
              </VBtn>
              <VBtn
                v-if="item.status === 'Pending' || item.status === 'Pendiente'"
                color="success"
                size="small"
                class="rounded-lg font-weight-black"
                prepend-icon="tabler-circle-check"
                :loading="statuModule.loadingItems.has(item.id)"
                @click="() => emit('approve', item.id)"
              >
                Aprobar
              </VBtn>
            </div>
          </VCard>
        </div>
        
        <!-- Paginación Móvil -->
        <div class="d-flex justify-center mt-6">
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(props.total / props.itemsPerPage)"
            total-visible="3"
            density="compact"
            active-color="primary"
            @update:model-value="(val) => emit('update:options', { page: val, itemsPerPage: props.itemsPerPage })"
          />
        </div>
      </template>

      <div v-else class="text-center py-12 text-disabled uppercase font-weight-bold border-2 border-dashed rounded-lg">
        No se encontraron gastos
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.premium-table :deep(th) {
  background-color: white !important;
  block-size: 52px !important;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.05) !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.premium-table :deep(td) {
  padding-block: 12px !important;
  border-block-end: 1px dashed rgba(var(--v-border-color), 0.1) !important;
}

.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 5%);
}

.text-super-xs { font-size: 0.65rem !important; }
.line-clamp-2 {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
}

.w-1 { width: 4px !important; }
</style>
