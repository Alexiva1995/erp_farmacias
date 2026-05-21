<script setup lang="js">
import dayjs from 'dayjs';
import { computed } from 'vue';
import { useAuthStore } from "@/stores/auth";

const authStore = useAuthStore();

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

const headers = computed(() => {
  const h = [
    { title: 'ID',             key: 'id',            sortable: true,  width: '70px' },
    { title: 'Descripción',     key: 'name',          sortable: true,  width: '250px' },
    { title: 'Categoría',       key: 'category.name', sortable: false, width: '150px' },
    { title: 'Moneda',          key: 'currency',      sortable: false, width: '100px' },
    { title: 'Monto Total',     key: 'total_usd',     sortable: true,  align: 'end', width: '150px' },
    { title: 'Estado',          key: 'status',        sortable: false, align: 'center', width: '120px' },
    { title: 'Fecha',           key: 'created_at',    sortable: true,  width: '100px' },
  ];
  if (authStore.isAdmin) {
    h.push({ title: 'Acciones',        key: 'acciones',      sortable: false, align: 'center', width: '100px' });
  }
  return h;
});
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
      <template #[`item.id`]="{ item }">
        <span class="font-weight-black text-primary text-sm">{{ item.id }}</span>
      </template>

      <!-- Nombre / Descripción -->
      <template #[`item.name`]="{ item }">
        <div class="d-flex flex-column py-2">
          <span class="text-sm font-weight-black text-high-emphasis leading-tight mb-1">
            {{ item.name }}
          </span>
          <span v-if="item.description" class="text-xs font-weight-medium text-disabled leading-normal">
            {{ item.description }}
          </span>
          <div class="d-flex align-center gap-1 mt-1">
            <VIcon icon="tabler-user" size="12" class="text-disabled" />
            <span class="text-super-xs font-weight-black text-disabled uppercase">{{ item.user?.username || 'S/U' }}</span>
          </div>
        </div>
      </template>

      <!-- Categoría -->
      <template #[`item.category.name`]="{ item }">
        <VChip size="x-small" color="primary" variant="tonal" class="rounded-lg font-weight-black px-2">
          <VIcon icon="tabler-tag" size="12" start />
          {{ item.category?.name || 'S/C' }}
        </VChip>
      </template>

      <!-- Moneda -->
      <template #[`item.currency`]="{ item }">
        <VChip size="x-small" variant="tonal" class="font-weight-black">
          {{ item.currency }}
        </VChip>
      </template>

      <!-- Monto -->
      <template #[`item.total_usd`]="{ item }">
        <div class="d-flex flex-column align-end py-2">
          <span class="text-sm font-weight-black text-error">
            ${{ Number(item.total_usd || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
          </span>
          <span v-if="item.currency !== 'USD'" class="text-super-xs font-weight-black text-disabled mt-1">
            Orig: {{ Number(item.amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }} {{ item.currency }}
          </span>
        </div>
      </template>

      <!-- Estado -->
      <template #[`item.status`]="{ item }">
        <VChip
          size="small"
          :color="item.status === 'Approved' ? 'success' : item.status === 'Cancelled' ? 'error' : 'warning'"
          variant="tonal"
          class="font-weight-black uppercase px-2"
        >
          {{ item.status === 'Pending' ? 'Pendiente' : item.status === 'Approved' ? 'Aprobado' : 'Cancelado' }}
        </VChip>
      </template>

      <!-- Fecha -->
      <template #[`item.created_at`]="{ item }">
        <span class="text-xs font-weight-bold text-disabled">{{ dayjs(item.created_at.replace('Z', '')).format('DD/MM/YYYY') }}</span>
      </template>

      <!-- Acciones -->
      <template #[`item.acciones`]="{ item }">
        <div class="d-flex justify-center gap-2">
          <VBtn
            v-if="item.status === 'Pending' || item.status === 'Pendiente'"
            variant="text"
            color="success"
            size="small"
            class="rounded-lg"
            :loading="statuModule.loadingItems.has(item.id)"
            @click="() => emit('approve', item.id)"
          >
            <VIcon icon="tabler-circle-check" size="22" />
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
        <div class="d-flex flex-column gap-3">
          <VCard
            v-for="item in props.items"
            :key="item.id"
            variant="flat"
            class="rounded-lg border shadow-soft pa-4 bg-white position-relative overflow-hidden"
          >
            <!-- Línea de Estado lateral -->
            <div 
              class="position-absolute left-0 top-0 bottom-0 w-1"
              :class="item.status === 'Approved' ? 'bg-success' : item.status === 'Cancelled' ? 'bg-error' : 'bg-warning'"
            ></div>

            <div class="d-flex justify-space-between align-center mb-2 ml-2">
              <div class="d-flex align-center gap-2">
                <VChip
                  size="x-small"
                  variant="flat"
                  class="rounded-lg font-weight-black bg-primary-opacity-1 text-primary"
                >
                  #{{ item.id }}
                </VChip>
                <VChip
                  size="x-small"
                  :color="item.status === 'Approved' ? 'success' : item.status === 'Cancelled' ? 'error' : 'warning'"
                  variant="tonal"
                  class="font-weight-black uppercase px-2"
                >
                  {{ item.status === 'Pending' ? 'Pendiente' : item.status === 'Approved' ? 'Aprobado' : 'Cancelado' }}
                </VChip>
              </div>
              <div class="text-right">
                <span class="text-super-xs font-weight-black text-disabled uppercase d-block">Monto en USD</span>
                <span class="text-sm font-weight-black text-error">
                  ${{ Number(item.total_usd || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                </span>
              </div>
            </div>

            <div class="ml-2 mb-1">
              <div class="text-sm font-weight-black text-high-emphasis">{{ item.name }}</div>
              <div v-if="item.description" class="text-xs text-disabled mt-1">{{ item.description }}</div>
            </div>

            <!-- Datos adicionales móvil -->
            <div class="ml-2 mb-3 mt-1 d-flex flex-wrap gap-2">
               <span class="text-super-xs font-weight-black text-disabled uppercase">
                 {{ item.count }}
               </span>
               <span class="text-disabled text-super-xs">•</span>
               <span class="text-super-xs font-weight-black text-disabled uppercase">
                 {{ item.currency }}
               </span>
               <span v-if="item.currency !== 'USD'" class="text-super-xs font-weight-medium text-disabled">
                 ({{ Number(item.amount).toLocaleString('es-VE') }} original)
               </span>
            </div>

            <div class="d-flex justify-space-between align-center mt-3 pt-3 border-t ml-2">
              <div class="d-flex align-center gap-1">
                <VIcon icon="tabler-tag" size="14" class="text-disabled" />
                <span class="text-super-xs font-weight-black uppercase text-disabled">{{ item.category?.name || 'S/C' }}</span>
              </div>
              <div class="d-flex align-center gap-1">
                <VIcon icon="tabler-calendar" size="14" class="text-disabled" />
                <span class="text-super-xs font-weight-black text-disabled">{{ dayjs(item.created_at.replace('Z', '')).format('DD/MM/YYYY') }}</span>
              </div>
            </div>

            <div v-if="authStore.isAdmin" class="d-flex justify-end gap-2 mt-4 ml-2">
              <VBtn
                v-if="item.status === 'Pending' || item.status === 'Pendiente'"
                color="success"
                size="small"
                class="rounded-lg font-weight-black flex-grow-1"
                :loading="statuModule.loadingItems.has(item.id)"
                @click="() => emit('approve', item.id)"
              >
                Aprobar Gasto
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

      <div v-else class="text-center py-12 text-disabled uppercase font-weight-bold border rounded-lg">
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
  border-block-end: 1px solid rgba(var(--v-border-color), 0.05) !important;
}

.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 5%);
}

.bg-primary-opacity-1 {
  background: rgba(var(--v-theme-primary), 0.08);
}

.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 5%);
}

.text-super-xs { font-size: 0.65rem !important; }

.w-1 { width: 4px !important; }
</style>
