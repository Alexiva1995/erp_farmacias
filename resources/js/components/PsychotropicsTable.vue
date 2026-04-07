<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import { calculateValidStock, formatDateSimple, nextExpirationDate } from "@/utils/formatters";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "actionVer"]);

const headers = [
  { title: "ID", key: "id", sortable: true, cellClass: "font-weight-black text-primary" },
  { title: "Producto", key: "name", sortable: true, width: "450px" },
  { title: "Stock Válido", key: "valid_stock", sortable: false, align: 'center' },
  { title: "Próx. Vencimiento", key: "next_expiration", sortable: false, align: 'center' },
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
  <VCard variant="flat" border>
    <!-- Vista de Escritorio (Tabla) -->
    <div class="d-none d-md-block">
      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.products"
        :items-length="props.totalProduct"
        :loading="props.loading"
        class="text-no-wrap"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #item.id="{ item }">
          <a
            :href="'/inventory/traceability?q=' + item.id"
            target="_blank"
            class="text-decoration-none font-weight-black text-primary"
          >
            {{ item.id }}
          </a>
        </template>

        <template #item.name="{ item }">
          <div class="d-flex align-center gap-x-4 py-2">
            <VAvatar
              v-if="item.photo_url"
              size="44"
              variant="tonal"
              rounded
              :image="item.photo_url"
              class="border elevation-1 flex-shrink-0"
            />
            <div class="d-flex flex-column truncate" style="max-inline-size: 400px;">
              <span class="text-sm font-weight-black text-high-emphasis leading-tight text-uppercase truncate">
                {{ item.name || 'N/A' }}
                <span v-if="item.iva == 1" class="text-primary font-weight-black"> (G)</span>
                <span v-if="item.is_colombian_origin == 1" class="text-info font-weight-black"> (COL)</span>
              </span>
              <div class="d-flex align-center gap-1 text-super-xs mt-1">
                <span class="text-disabled truncate" style="max-inline-size: 200px;">{{ item.active_ingredient || "" }}</span>
                <span class="text-disabled">|</span>
                <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 150px;">
                  {{ item.laboratory?.name || 'S/L' }}
                </span>
              </div>
            </div>
          </div>
        </template>


        <template #item.valid_stock="{ item }">
          <VChip
            :color="calculateValidStock(item) > 10 ? 'success' : 'error'"
            size="small"
            label
            variant="flat"
            class="font-weight-black elevation-1"
          >
            {{ calculateValidStock(item) }} UNDS
          </VChip>
        </template>

        <template #item.next_expiration="{ item }">
          <VChip
            v-if="nextExpirationDate(item) !== 'N/A' && nextExpirationDate(item) !== 'Todos expiraron'"
            color="warning"
            size="small"
            variant="tonal"
            class="font-weight-black"
            prepend-icon="tabler-calendar-time"
          >
            {{ formatDateSimple(nextExpirationDate(item)) }}
          </VChip>
          <span v-else class="text-caption text-disabled font-weight-bold">{{ nextExpirationDate(item) }}</span>
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista de Móvil (Tarjetas) -->
    <div class="d-block d-md-none pa-2 bg-light">
      <VLinearProgress v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.products.length === 0 && !props.loading" class="text-center py-8 text-disabled">
        No se encontraron productos psicotrópicos.
      </div>

      <div class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.products"
          :key="item.id"
          variant="flat"
          class="border mb-1 overflow-hidden"
          style="border-radius: 12px !important;"
        >
          <div class="pa-4">
            <div class="d-flex gap-3 align-start mb-3">
              <VAvatar
                v-if="item.photo_url"
                size="48"
                variant="tonal"
                rounded
                :image="item.photo_url"
                class="flex-shrink-0 border"
              />
              <div class="flex-grow-1 min-width-0">
                <div class="d-flex align-center gap-1 mb-1">
                  <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight truncate">
                    <a
                      :href="'/inventory/traceability?q=' + item.id"
                      target="_blank"
                      class="text-decoration-none text-primary"
                    >
                      {{ item.id }}
                    </a>
                    <span class="mx-1 text-disabled">|</span>
                    {{ item.name }}
                  </h3>
                </div>
                <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs">
                  <span class="text-medium-emphasis font-weight-bold">{{ item.active_ingredient }}</span>
                  <span class="text-disabled">|</span>
                  <span class="text-primary font-weight-black text-uppercase">{{ item.laboratory?.name || 'S/L' }}</span>
                </div>
              </div>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <div class="d-flex justify-space-between align-center bg-var-theme-background-light px-3 py-2 rounded border-dashed-thin">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Stock Válido</span>
                <VChip
                  :color="calculateValidStock(item) > 10 ? 'success' : 'error'"
                  size="x-small"
                  label
                  variant="flat"
                  class="font-weight-black mt-1"
                >
                  {{ calculateValidStock(item) }} UNDS
                </VChip>
              </div>
              <div class="d-flex flex-column text-right">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Próx. Vencimiento</span>
                <span class="text-caption font-weight-black text-high-emphasis mt-1">
                  <VIcon icon="tabler-calendar" size="14" class="me-1 text-warning" />
                  {{ nextExpirationDate(item) !== 'N/A' && nextExpirationDate(item) !== 'Todos expiraron' ? formatDateSimple(nextExpirationDate(item)) : nextExpirationDate(item) }}
                </span>
              </div>
            </div>
          </div>
        </VCard>
      </div>

      <!-- Paginación Móvil -->
      <div class="d-flex justify-center mt-4 pb-2">
         <AppMobilePagination
            :page="props.page"
            :items-per-page="props.itemsPerPage"
            :total-items="props.totalProduct"
            :loading="props.loading"
            @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
          />
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.bg-light {
  background-color: #f8fafc !important;
}

.bg-var-theme-background-light {
  background-color: rgba(var(--v-border-color), 0.05);
}

.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }

.leading-tight { line-height: 1.2 !important; }

:deep(.v-data-table th) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}
</style>
