<script setup>
import { formatCurrency } from '@/utils/currencyFormatter';

const props = defineProps({
  snapshots: {
    type: Array,
    default: () => [],
  },
  totalSnapshots: {
    type: Number,
    default: 0,
  },
  page: {
    type: Number,
    default: 1,
  },
  itemsPerPage: {
    type: Number,
    default: 10,
  },
  sortBy: {
    type: Array,
    default: () => [{ key: 'cutoff_date', order: 'desc' }],
  },
  search: {
    type: String,
    default: '',
  },
  loading: {
    type: Boolean,
    default: false,
  },
  headers: {
    type: Array,
    required: true,
  },
});

const emit = defineEmits([
  'update:page',
  'update:itemsPerPage',
  'update:sortBy',
  'update:search',
  'refresh',
  'open-create',
  'open-detail',
  'export-excel',
  'delete-snapshot',
]);
</script>

<template>
  <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface mb-6">
    <VCardText class="pa-4">
      <!-- Barra de Búsqueda y Botón Refrescar -->
      <VRow align="center" dense class="mb-3">
        <VCol cols="12" sm="6" md="4">
          <AppTextField
            :model-value="search"
            placeholder="Buscar por nombre o fecha (YYYY-MM-DD)..."
            prepend-inner-icon="tabler-search"
            clearable
            density="compact"
            hide-details
            variant="outlined"
            :disabled="loading"
            @update:model-value="val => emit('update:search', val)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="auto" class="ms-auto d-flex align-center justify-end gap-2">
          <VBtn
            variant="tonal"
            color="primary"
            size="small"
            class="font-weight-bold"
            @click="emit('open-create')"
          >
            <VIcon icon="tabler-camera-plus" size="16" class="me-1" />
            Nueva Foto
          </VBtn>

          <VBtn
            icon
            variant="text"
            color="secondary"
            size="36"
            class="rounded-circle"
            :disabled="loading"
            @click="emit('refresh')"
          >
            <VIcon icon="tabler-refresh" size="18" />
            <VTooltip activator="parent" location="top">Refrescar listado</VTooltip>
          </VBtn>
        </VCol>
      </VRow>

      <!-- VISTA DESKTOP: TABLA SERVIDOR -->
      <div class="d-none d-md-block">
        <VDataTableServer
          :items-per-page="itemsPerPage"
          :page="page"
          :sort-by="sortBy"
          :items-length="totalSnapshots"
          :headers="headers"
          :items="snapshots"
          :loading="loading"
          class="premium-table"
          hover
          density="comfortable"
          @update:items-per-page="val => emit('update:itemsPerPage', val)"
          @update:page="val => emit('update:page', val)"
          @update:sort-by="val => emit('update:sortBy', val)"
        >
          <!-- Empty state -->
          <template #no-data>
            <div class="py-10 text-center text-medium-emphasis">
              <VIcon icon="tabler-camera-off" size="52" class="mb-3 opacity-40" />
              <p class="text-body-1 font-weight-medium mb-1">Aún no se han generado Fotos Finish</p>
              <p class="text-caption text-disabled mb-4">
                Las fotos se generan automáticamente el día 1 de cada mes a las 02:00 AM o manualmente con cualquier fecha de corte.
              </p>
              <VBtn size="small" color="primary" variant="flat" @click="emit('open-create')">
                <VIcon icon="tabler-camera-plus" size="16" class="me-1" />
                Tomar Primera Foto Finish
              </VBtn>
            </div>
          </template>

          <!-- ID -->
          <template #item.id="{ item }">
            <span class="font-weight-black text-primary">#{{ item.id }}</span>
          </template>

          <!-- Nombre -->
          <template #item.name="{ item }">
            <div class="d-flex flex-column py-2">
              <span class="font-weight-bold text-high-emphasis text-base">{{ item.name }}</span>
              <span class="text-caption text-medium-emphasis">
                Creado: {{ item.created_at ? new Date(item.created_at).toLocaleDateString('es-ES') : 'N/D' }}
                <span v-if="item.creator_name"> por {{ item.creator_name }}</span>
              </span>
            </div>
          </template>

          <!-- Fecha de Corte -->
          <template #item.cutoff_date="{ item }">
            <VChip size="small" color="primary" variant="tonal" class="font-weight-bold">
              <VIcon icon="tabler-calendar" size="14" class="me-1" />
              {{ item.cutoff_date }}
            </VChip>
          </template>

          <!-- SKUs -->
          <template #item.total_products="{ item }">
            <span class="font-weight-black">{{ item.total_products }}</span>
          </template>

          <!-- Stock Total -->
          <template #item.total_inventory_units="{ item }">
            <span class="font-weight-bold">{{ Number(item.total_inventory_units).toLocaleString() }}</span>
          </template>

          <!-- Valor Inventario -->
          <template #item.total_inventory_value="{ item }">
            <span class="font-weight-black text-primary text-base">{{ formatCurrency(item.total_inventory_value) }}</span>
          </template>

          <!-- Ventas 30d -->
          <template #item.total_sales_value="{ item }">
            <span class="font-weight-bold text-success">{{ formatCurrency(item.total_sales_value) }}</span>
          </template>

          <!-- Sobrestock -->
          <template #item.overstock_products_count="{ item }">
            <VTooltip location="top">
              <template #activator="{ props: tipProps }">
                <VChip
                  v-bind="tipProps"
                  :color="item.overstock_products_count > 0 ? 'error' : 'success'"
                  size="small"
                  variant="tonal"
                  class="font-weight-bold"
                >
                  <VIcon :icon="item.overstock_products_count > 0 ? 'tabler-alert-triangle' : 'tabler-circle-check'" size="14" class="me-1" />
                  {{ item.overstock_products_count }} SKUs
                </VChip>
              </template>
              <span>Capital en Sobrestock: {{ formatCurrency(item.overstock_inventory_value) }}</span>
            </VTooltip>
          </template>

          <!-- Tipo -->
          <template #item.is_automatic="{ item }">
            <VChip
              size="small"
              :color="item.is_automatic ? 'info' : 'secondary'"
              variant="tonal"
              class="font-weight-bold"
            >
              <VIcon :icon="item.is_automatic ? 'tabler-cpu' : 'tabler-user'" size="14" class="me-1" />
              {{ item.is_automatic ? 'Automático' : 'Manual' }}
            </VChip>
          </template>

          <!-- Acciones -->
          <template #item.actions="{ item }">
            <div class="d-flex align-center justify-center gap-1">
              <VBtn
                icon
                variant="text"
                size="30"
                color="primary"
                @click="emit('open-detail', item)"
              >
                <VIcon icon="tabler-eye" size="18" />
                <VTooltip activator="parent" location="top">Ver 4 Módulos de Control</VTooltip>
              </VBtn>

              <VBtn
                icon
                variant="text"
                size="30"
                color="success"
                @click="emit('export-excel', item.id)"
              >
                <VIcon icon="tabler-download" size="18" />
                <VTooltip activator="parent" location="top">Descargar Excel</VTooltip>
              </VBtn>

              <VBtn
                icon
                variant="text"
                size="30"
                color="error"
                @click="emit('delete-snapshot', item)"
              >
                <VIcon icon="tabler-trash" size="18" />
                <VTooltip activator="parent" location="top">Eliminar Foto Finish</VTooltip>
              </VBtn>
            </div>
          </template>
        </VDataTableServer>
      </div>

      <!-- VISTA MÓVIL: TARJETAS RESPONSIVAS -->
      <div class="d-md-none">
        <template v-if="loading">
          <VRow dense>
            <VCol v-for="n in 3" :key="n" cols="12">
              <VSkeletonLoader type="article" class="rounded-lg border mb-3" />
            </VCol>
          </VRow>
        </template>

        <template v-else-if="!snapshots.length">
          <div class="py-8 text-center text-medium-emphasis">
            <VIcon icon="tabler-camera-off" size="40" class="mb-2 opacity-40" />
            <p class="text-body-2 mb-2">No hay Fotos Finish disponibles</p>
            <VBtn size="small" color="primary" variant="flat" @click="emit('open-create')">
              Tomar Foto Finish
            </VBtn>
          </div>
        </template>

        <template v-else>
          <div class="d-flex flex-column gap-3">
            <VCard
              v-for="item in snapshots"
              :key="item.id"
              class="border rounded-lg pa-3"
              elevation="0"
            >
              <div class="d-flex align-center justify-space-between mb-2">
                <div class="d-flex align-center gap-1">
                  <span class="font-weight-black text-primary">#{{ item.id }}</span>
                  <VChip
                    size="x-small"
                    :color="item.is_automatic ? 'info' : 'secondary'"
                    variant="tonal"
                    class="font-weight-bold"
                  >
                    <VIcon :icon="item.is_automatic ? 'tabler-cpu' : 'tabler-user'" size="12" class="me-1" />
                    {{ item.is_automatic ? 'Auto' : 'Manual' }}
                  </VChip>
                </div>
                <VChip size="x-small" color="primary" variant="tonal" class="font-weight-bold">
                  {{ item.cutoff_date }}
                </VChip>
              </div>

              <h4 class="text-subtitle-1 font-weight-bold mb-2">{{ item.name }}</h4>

              <div class="d-flex justify-space-between py-1 border-b text-caption">
                <span class="text-medium-emphasis">Valor Inventario:</span>
                <span class="font-weight-black text-primary">{{ formatCurrency(item.total_inventory_value) }}</span>
              </div>
              <div class="d-flex justify-space-between py-1 border-b text-caption">
                <span class="text-medium-emphasis">Ventas 30D:</span>
                <span class="font-weight-bold text-success">{{ formatCurrency(item.total_sales_value) }}</span>
              </div>
              <div class="d-flex justify-space-between py-1 border-b text-caption">
                <span class="text-medium-emphasis">SKUs / Stock Total:</span>
                <span>{{ item.total_products }} SKUs ({{ Number(item.total_inventory_units).toLocaleString() }} u)</span>
              </div>
              <div class="d-flex justify-space-between py-1 text-caption mb-3">
                <span class="text-medium-emphasis">Sobrestock (>90d):</span>
                <span :class="item.overstock_products_count > 0 ? 'text-error font-weight-bold' : 'text-success'">
                  {{ item.overstock_products_count }} SKUs
                </span>
              </div>

              <div class="d-flex align-center gap-2">
                <VBtn
                  block
                  variant="flat"
                  color="primary"
                  size="small"
                  @click="emit('open-detail', item)"
                >
                  <VIcon icon="tabler-eye" size="16" class="me-1" />
                  Ver 4 Módulos
                </VBtn>
                <VBtn
                  icon
                  variant="outlined"
                  color="success"
                  size="small"
                  @click="emit('export-excel', item.id)"
                >
                  <VIcon icon="tabler-download" size="16" />
                </VBtn>
                <VBtn
                  icon
                  variant="outlined"
                  color="error"
                  size="small"
                  @click="emit('delete-snapshot', item)"
                >
                  <VIcon icon="tabler-trash" size="16" />
                </VBtn>
              </div>
            </VCard>
          </div>
        </template>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }
</style>
