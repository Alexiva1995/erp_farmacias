<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import AppEmptyState from "@/components/AppEmptyState.vue";
import { formatDateSimple } from "@/utils/formatters";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { computed } from "vue";

const props = defineProps({
  products: { type: Array, required: true },
  totalProducts: { type: Number, required: true },
  loading: { type: Boolean, default: false },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "adjust-lots"]);
const brandingStore = useBrandingStore();

const headers = computed(() => [
  { 
    title: "ID", 
    key: "id", 
    sortable: true,
    cellClass: "font-weight-black text-primary",
  },
  { title: "Producto", key: "name", sortable: true },
  { 
    title: "Stock Calculado", 
    key: "stock_calculado", 
    sortable: true, 
    align: "center" 
  },
  { 
    title: "Laboratorio", 
    key: "laboratory.name", 
    sortable: true,
    cellClass: "d-none d-md-table-cell",
    headerClass: "d-none d-md-table-cell"
  },
  { 
    title: "Lotes Activos", 
    key: "lots_count", 
    sortable: false, 
    align: "center",
    cellClass: "d-none d-lg-table-cell",
    headerClass: "d-none d-lg-table-cell"
  },
  { 
    title: "Próx. Exp.", 
    key: "next_expiration", 
    sortable: false,
    cellClass: "d-none d-sm-table-cell",
    headerClass: "d-none d-sm-table-cell"
  },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
]);

const getNextExpiration = (lots) => {
  if (!lots || lots.length === 0) return null;
  const expirations = lots
    .map(l => l.expiration_date)
    .filter(d => d)
    .sort();
  return expirations[0] || null;
};
</script>

<template>
  <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
    <!-- Desktop Table -->
    <div class="d-none d-sm-block">
      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.products"
        :items-length="props.totalProducts"
        :loading="props.loading"
        class="text-no-wrap premium-table"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #no-data>
          <AppEmptyState
            title="¡Todo Lotificado!"
            message="No hay productos pendientes de asignación de lotes en el catálogo."
            icon="tabler-package-off"
          />
        </template>

        <template #item.id="{ item }">
          <a
            :href="'/inventory/traceability?q=' + item.id"
            target="_blank"
            class="text-decoration-none font-weight-black text-primary"
          >
            #{{ item.id }}
          </a>
        </template>

        <template #item.name="{ item }">
          <div class="d-flex align-center gap-x-3 py-1">
            <VAvatar
              v-if="item.photo_url"
              size="38"
              variant="tonal"
              rounded
              :image="item.photo_url"
            />
            <div class="d-flex flex-column">
              <span
                class="text-sm font-weight-bold text-high-emphasis truncate"
                style="max-inline-size: 320px;"
                :class="{ 'text-warning': item.psychotropic == 1 || item.psychotropic === true }"
              >
                {{ item.name?.toUpperCase() || "—" }}
                <span v-if="item.iva == 1 || item.iva === true" class="text-xs text-info ms-1">(G)</span>
                <span v-if="item.is_colombian_origin == 1 || item.is_colombian_origin === true" class="text-xs text-success ms-1">(COL)</span>
              </span>
              <span class="text-xs text-disabled truncate" style="max-inline-size: 280px;">
                {{ item.active_ingredient || item.presentation || 'Sin Especificación' }}
                <template v-if="item.laboratory?.name">
                  • <strong class="text-primary">{{ item.laboratory.name }}</strong>
                </template>
              </span>
            </div>
          </div>
        </template>

        <template #item.laboratory.name="{ item }">
          <span class="text-xs font-weight-medium text-capitalize">{{ item.laboratory?.name || "—" }}</span>
        </template>

        <template #item.stock_calculado="{ item }">
          <span class="text-xs font-weight-black" :class="(item.stock_calculado ?? 0) > 0 ? 'text-success' : 'text-error'">
            {{ Math.round(item.stock_calculado ?? 0) }} <small>UNDS</small>
          </span>
        </template>

        <template #item.lots_count="{ item }">
          <span class="text-xs font-weight-bold">{{ item.lots?.length || 0 }}</span>
        </template>

        <template #item.next_expiration="{ item }">
          <span class="text-xs font-weight-medium">{{ formatDateSimple(getNextExpiration(item.lots)) || 'N/A' }}</span>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-1">
            <VBtn
              icon
              size="32"
              color="primary"
              variant="tonal"
              class="rounded-lg shadow-sm"
              @click.stop="emit('adjust-lots', item)"
            >
              <VIcon icon="tabler-package" size="18" />
              <VTooltip activator="parent" location="top">Crear / Ajustar Lotes</VTooltip>
            </VBtn>
          </div>
        </template>
      </VDataTableServer>
    </div>

    <!-- Mobile Cards -->
    <div class="d-block d-sm-none">
      <div v-if="loading && products.length === 0" class="pa-5 text-center">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <div v-else-if="products.length > 0" class="pa-3 d-flex flex-column gap-3">
        <VCard
          v-for="item in products"
          :key="item.id"
          class="border shadow-sm rounded-lg overflow-hidden"
        >
          <div class="pa-4">
            <div class="d-flex gap-3 align-start mb-2">
              <VAvatar
                v-if="item.photo_url"
                size="42"
                variant="tonal"
                rounded
                :image="item.photo_url"
                class="flex-shrink-0"
              />
              <div class="flex-grow-1 min-width-0">
                <h3 class="text-xs font-weight-black text-high-emphasis text-uppercase leading-tight">
                  <a
                    :href="'/inventory/traceability?q=' + item.id"
                    target="_blank"
                    class="text-decoration-none text-primary me-1"
                  >
                    #{{ item.id }}
                  </a>
                  <span class="text-disabled me-1">|</span>
                  {{ item.name }}
                </h3>
                <span class="text-super-xs text-disabled truncate d-block mt-1">
                  {{ item.active_ingredient || 'Sin Especificación' }} • <strong class="text-primary">{{ item.laboratory?.name || 'S/L' }}</strong>
                </span>
              </div>
            </div>

            <VDivider class="my-3 opacity-10" />

            <div class="d-flex justify-space-between align-center mb-3">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold">Stock Registrado</span>
                <span class="text-xs font-weight-black" :class="(item.stock_calculado ?? 0) > 0 ? 'text-success' : 'text-error'">
                  {{ Math.round(item.stock_calculado ?? 0) }} <small>UNDS</small>
                </span>
              </div>
              <div class="d-flex flex-column text-right">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold">Próx. Venc.</span>
                <span class="text-xs font-weight-bold">{{ formatDateSimple(getNextExpiration(item.lots)) || 'N/A' }}</span>
              </div>
            </div>

            <div class="pa-2 rounded-lg bg-surface-variant-opacity border d-flex justify-space-between align-center">
              <span class="text-super-xs text-disabled text-uppercase font-weight-bold">Lotes Físicos:</span>
              <span class="text-xs font-weight-black text-primary">{{ item.lots?.length || 0 }} Registrados</span>
            </div>
          </div>

          <div class="border-t">
            <VBtn
              block
              color="primary"
              variant="text"
              class="rounded-0 text-xs font-weight-black"
              height="40"
              prepend-icon="tabler-package"
              @click="emit('adjust-lots', item)"
            >
              ASIGNAR LOTES
            </VBtn>
          </div>
        </VCard>

        <!-- Paginación Móvil -->
        <div class="d-flex justify-center mt-2">
           <AppMobilePagination
            :page="props.page"
            :items-per-page="props.itemsPerPage"
            :total-items="props.totalProducts"
            :loading="props.loading"
            @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
          />
        </div>
      </div>

      <div v-else class="pa-4">
        <AppEmptyState
          title="¡Todo Lotificado!"
          message="No hay productos pendientes de asignación de lotes en el catálogo."
          icon="tabler-package-off"
        />
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.premium-table :deep(.v-data-table-header th) {
  background: white !important;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.05rem !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
}

.premium-table :deep(.v-data-table__td) {
  padding-block: 10px !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.03) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
}

.bg-surface-variant-opacity {
  background-color: rgba(var(--v-theme-on-surface), 0.03) !important;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
