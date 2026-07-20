<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import { formatPrice } from "@/utils/formatters";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { ref, computed } from 'vue';

const props = defineProps({
  products: { type: Array, required: true }, // Aquí vienen los grupos
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  sortBy: { type: [String, Array], default: () => [] },
  orderBy: { type: String, default: "asc" },
});

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => (brandingStore.settings.business_type === 'restaurant' || brandingStore.settings.business_type === 'minimarket'));
const isMiniMarket = computed(() => brandingStore.settings?.business_type === 'minimarket');

const emit = defineEmits(["update:options"]);

// Estado de expansión
const expandedGroupId = ref(null);
const toggleGroup = (groupId) => {
  if (expandedGroupId.value === groupId) {
    expandedGroupId.value = null;
  } else {
    expandedGroupId.value = groupId;
  }
};

const isExpanded = (groupId) => expandedGroupId.value === groupId;

const formatPriceWithDecimals = (price) => {
  const numPrice = Number(price);
  if (isNaN(numPrice)) return "$ 0.00";
  return new Intl.NumberFormat("es-CO", {
    style: "currency",
    currency: "COP",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(numPrice);
};

const formatInteger = (val) => {
  const num = Number(val || 0);
  return Math.round(num).toString();
};

const getDiffColor = (val) => {
  const num = parseFloat(val);
  if (isNaN(num) || num === 0) return 'secondary';
  return num > 0 ? 'success' : 'error';
};
</script>

<template>
  <div class="inventory-stock-grouped">
    <!-- Estado vacío -->
    <div v-if="!loading && products.length === 0" class="d-flex flex-column align-center py-16 text-disabled bg-surface border rounded-lg">
      <VIcon icon="tabler-package-off" size="48" class="mb-3" />
      <span class="text-body-1 font-weight-medium">No se encontraron productos o grupos filtrados</span>
    </div>

    <!-- Skeleton de carga -->
    <div v-else-if="loading" class="pa-4 d-flex flex-column gap-3">
      <div v-for="n in 5" :key="n" class="rounded-lg border pa-4 skeleton-group">
        <div class="skeleton-bar short mb-2" />
        <div class="skeleton-bar medium" />
      </div>
    </div>

    <!-- Acordeón de grupos -->
    <div v-else class="groups-container">
      <div
        v-for="grupo in props.products"
        :key="grupo.group_id || grupo.id"
        class="grupo-card mb-3 rounded-lg border overflow-hidden bg-surface"
      >
        <!-- Cabecera del grupo -->
        <div
          class="grupo-header d-flex flex-column flex-sm-row align-start align-sm-center justify-space-between pa-4 pa-sm-5 cursor-pointer"
          :class="isExpanded(grupo.group_id || grupo.id) ? 'grupo-header--expanded' : ''"
          @click="toggleGroup(grupo.group_id || grupo.id)"
        >
          <!-- Información del Grupo (Izquierda) -->
          <div class="d-flex align-center gap-3 min-width-0 mb-3 mb-sm-0 flex-grow-1">
            <VIcon
              :icon="isExpanded(grupo.group_id || grupo.id) ? 'tabler-chevron-down' : 'tabler-chevron-right'"
              size="20"
              color="primary"
              class="flex-shrink-0"
            />
            <div class="d-flex flex-column min-width-0">
              <span class="text-sm font-weight-black text-uppercase text-high-emphasis leading-tight mb-1">
                {{ grupo.name }}
              </span>
              <div class="d-flex align-center gap-2 text-super-xs text-disabled font-weight-bold">
                <span class="bg-light px-1 rounded">ID: {{ grupo.group_id ? 'G-' + grupo.group_id : 'ID-' + grupo.id }}</span>
                <span>•</span>
                <span>{{ grupo.productos?.length || 0 }} productos integrados</span>
              </div>
            </div>
          </div>

          <!-- Totales en la cabecera (Derecha / Abajo en móvil) -->
          <div class="d-flex align-center gap-4 gap-sm-6 w-100 w-sm-auto justify-space-between justify-sm-end">
            <!-- Stock -->
            <div class="text-center min-width-indicator">
              <span class="text-super-xs text-disabled d-block font-weight-black uppercase leading-none mb-1">Stock</span>
              <div class="d-flex align-center justify-center gap-x-1">
                <span class="text-sm font-weight-black" :class="parseFloat(grupo.lote_quantity) === 0 ? 'text-error' : (parseFloat(grupo.diferencia_product) < 0 ? 'text-warning' : 'text-success')">
                  {{ isMiniMarket ? formatInteger(grupo.lote_quantity) : grupo.lote_quantity }}
                </span>
                <VIcon
                  v-if="parseFloat(grupo.diferencia_product) < 0"
                  :icon="parseFloat(grupo.lote_quantity) === 0 ? 'tabler-alert-octagon' : 'tabler-alert-triangle'"
                  :color="parseFloat(grupo.lote_quantity) === 0 ? 'error' : 'warning'"
                  size="14"
                />
              </div>
            </div>
            
            <VDivider vertical class="mx-0" />
            
            <!-- Ventas/Consumido -->
            <div class="text-center min-width-indicator">
              <span class="text-super-xs text-disabled d-block font-weight-black uppercase leading-none mb-1">{{ isRestaurant ? 'Consumido' : 'Ventas' }}</span>
              <span class="text-sm font-weight-black text-high-emphasis">
                {{ isMiniMarket ? formatInteger(grupo.total_sold_completed) : grupo.total_sold_completed }}
              </span>
            </div>

            <VDivider vertical class="mx-0" />

            <!-- Diferencia -->
            <div class="text-center min-width-indicator">
              <span class="text-super-xs text-disabled d-block font-weight-black uppercase leading-none mb-1">Dif.</span>
              <VChip
                :color="getDiffColor(grupo.diferencia_product)"
                size="x-small"
                variant="flat"
                class="font-weight-black text-super-xs px-2"
              >
                {{ parseFloat(grupo.diferencia_product) > 0 ? '+' : '' }}{{ Math.ceil(parseFloat(grupo.diferencia_product)) }}
              </VChip>
            </div>
          </div>
        </div>

        <!-- Cuerpo del grupo (Responsivo) -->
        <VExpandTransition>
          <div v-if="isExpanded(grupo.group_id || grupo.id)" class="grupo-body border-t bg-var-theme-background-light">
            <!-- Vista Desktop (Tabla) -->
            <div class="table-container overflow-x-auto d-none d-md-block">
              <VTable density="compact" class="child-products-table bg-transparent">
                <thead>
                  <tr>
                    <th class="text-xs font-weight-black text-uppercase shadow-sm">ID</th>
                    <th class="text-xs font-weight-black text-uppercase shadow-sm">Producto Individual</th>
                    <th class="text-xs font-weight-black text-uppercase text-right shadow-sm">Costo</th>
                    <th class="text-xs font-weight-black text-uppercase text-center shadow-sm">{{ isRestaurant ? 'Consumido' : 'Ventas' }}</th>
                    <th class="text-xs font-weight-black text-uppercase text-center shadow-sm">Stock</th>
                    <th class="text-xs font-weight-black text-uppercase text-center shadow-sm">Pref %</th>
                    <th class="text-xs font-weight-black text-uppercase text-center shadow-sm">Prom.</th>
                    <th v-if="!isRestaurant" class="text-xs font-weight-black text-uppercase text-center shadow-sm">AO</th>
                    <th class="text-xs font-weight-black text-uppercase text-center shadow-sm">Dif.</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in grupo.productos" :key="item.id" class="child-row">
                    <td>
                      <a :href="'/inventory/traceability?q=' + item.id" target="_blank" class="text-decoration-none font-weight-black text-primary text-xs">
                        {{ item.id }}
                      </a>
                    </td>
                    <td>
                      <div class="d-flex flex-column py-1 truncate" style="max-inline-size: 300px;">
                        <span class="text-xs font-weight-black text-uppercase text-high-emphasis truncate">
                          {{ item.name }}
                          <span v-if="item.is_colombian_origin == 1" class="text-info font-weight-black text-super-xs ml-1">(COL)</span>
                        </span>
                        <div class="d-flex align-center gap-1 text-super-xs text-disabled">
                          <span class="truncate" style="max-inline-size: 150px;">{{ item.active_ingredient || '' }}</span>
                          <span>|</span>
                          <span class="text-primary font-weight-black text-uppercase">{{ item.laboratory?.name || (isRestaurant ? 'S/M' : 'S/L') }}</span>
                        </div>
                      </div>
                    </td>
                    <td class="text-right text-xs font-weight-medium">
                      {{ isMiniMarket ? formatPriceWithDecimals(item.unit_cost) : formatPrice(item.unit_cost) }}
                    </td>
                    <td class="text-center text-xs">
                      {{ isMiniMarket ? formatInteger(item.total_sold_completed) : item.total_sold_completed }}
                    </td>
                    <td class="text-center text-xs">
                      <div class="d-flex align-center justify-center gap-x-1">
                        <VChip
                          :color="parseFloat(item.lote_quantity) === 0 ? 'error' : (parseFloat(item.diferencia_product) < 0 ? 'warning' : 'success')"
                          size="x-small"
                          variant="tonal"
                          class="font-weight-black"
                        >
                          {{ isMiniMarket ? formatInteger(item.lote_quantity) : item.lote_quantity }}
                        </VChip>
                        <VIcon
                          v-if="parseFloat(item.diferencia_product) < 0"
                          :icon="parseFloat(item.lote_quantity) === 0 ? 'tabler-alert-octagon' : 'tabler-alert-triangle'"
                          :color="parseFloat(item.lote_quantity) === 0 ? 'error' : 'warning'"
                          size="13"
                        >
                          <VTooltip activator="parent" location="top">
                            {{ parseFloat(item.lote_quantity) === 0 ? 'Quiebre de Stock Crítico' : 'Stock insuficiente para la demanda' }}
                          </VTooltip>
                        </VIcon>
                      </div>
                    </td>
                    <td class="text-center text-xs font-weight-bold text-primary">
                      {{ parseFloat(item.preferencia_product || 0).toFixed(1) }}%
                    </td>
                    <td class="text-center text-xs text-disabled">{{ parseFloat(item.promedio_calculado || 0).toFixed(1) }}</td>
                    <td v-if="!isRestaurant" class="text-center">
                      <VChip :color="item.totalQuantityInAutoOrder > 0 ? 'info' : 'default'" variant="tonal" size="x-small">
                        {{ item.totalQuantityInAutoOrder || 0 }}
                      </VChip>
                    </td>
                    <td class="text-center">
                      <span class="text-xs font-weight-black" :class="'text-' + getDiffColor(item.diferencia_product)">
                        {{ parseFloat(item.diferencia_product) > 0 ? '+' : '' }}{{ Math.ceil(parseFloat(item.diferencia_product)) }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>

            <!-- Vista Móvil (Tarjetas Compactas) -->
            <div class="d-block d-md-none pa-2">
              <div 
                v-for="item in grupo.productos" 
                :key="item.id" 
                class="mb-2 bg-surface rounded-lg border pa-2 shadow-xs"
              >
                <div class="d-flex justify-space-between align-start mb-1">
                  <div class="d-flex align-center gap-2 truncate">
                    <a :href="'/inventory/traceability?q=' + item.id" target="_blank" class="text-decoration-none font-weight-black text-primary text-xs flex-shrink-0">
                      #{{ item.id }}
                    </a>
                    <span class="text-xs font-weight-black text-uppercase text-high-emphasis truncate">
                      {{ item.name }}
                    </span>
                  </div>
                  <VChip v-if="item.is_colombian_origin == 1" color="info" size="x-super-xs" variant="flat" class="text-super-xs font-weight-black">COL</VChip>
                </div>
                
                <div class="d-flex align-center justify-space-between text-super-xs mb-2 text-disabled font-weight-bold">
                    <span class="truncate">{{ item.laboratory?.name || (isRestaurant ? 'SIN MARCA' : 'SIN LABORATORIO') }}</span>
                   <span>Costo: {{ isMiniMarket ? formatPriceWithDecimals(item.unit_cost) : formatPrice(item.unit_cost) }}</span>
                </div>

                <div class="d-flex align-center justify-space-between gap-1">
                  <div class="bg-var-theme-background-light rounded px-2 py-1 flex-1 text-center border-dashed-thin">
                    <span class="text-super-xs text-disabled d-block uppercase font-weight-black leading-none mb-1">Stock</span>
                    <div class="d-flex align-center justify-center gap-x-1">
                      <span class="text-xs font-weight-black" :class="parseFloat(item.lote_quantity) === 0 ? 'text-error' : (parseFloat(item.diferencia_product) < 0 ? 'text-warning' : 'text-success')">
                        {{ isMiniMarket ? formatInteger(item.lote_quantity) : item.lote_quantity }}
                      </span>
                      <VIcon
                        v-if="parseFloat(item.diferencia_product) < 0"
                        :icon="parseFloat(item.lote_quantity) === 0 ? 'tabler-alert-octagon' : 'tabler-alert-triangle'"
                        :color="parseFloat(item.lote_quantity) === 0 ? 'error' : 'warning'"
                        size="12"
                      />
                    </div>
                  </div>
                  <div class="bg-var-theme-background-light rounded px-2 py-1 flex-1 text-center border-dashed-thin">
                    <span class="text-super-xs text-disabled d-block uppercase font-weight-black leading-none mb-1">Pref.</span>
                    <span class="text-xs font-weight-black text-primary">{{ parseFloat(item.preferencia_product || 0).toFixed(1) }}%</span>
                  </div>
                  <div class="bg-var-theme-background-light rounded px-2 py-1 flex-1 text-center border-dashed-thin">
                    <span class="text-super-xs text-disabled d-block uppercase font-weight-black leading-none mb-1">Dif.</span>
                    <span class="text-xs font-weight-black" :class="'text-' + getDiffColor(item.diferencia_product)">
                        {{ parseFloat(item.diferencia_product) > 0 ? '+' : '' }}{{ Math.ceil(parseFloat(item.diferencia_product)) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </VExpandTransition>
      </div>
    </div>

    <!-- Paginación Movida al Componente -->
    <div class="d-flex justify-center mt-6 pb-4">
       <AppMobilePagination
          :page="props.page"
          :items-per-page="props.itemsPerPage"
          :total-items="props.totalProduct"
          :loading="props.loading"
          @change="(options) => emit('update:options', options)"
        />
    </div>
  </div>
</template>

<style scoped>
.min-width-indicator {
  min-width: 65px;
}

@media (max-width: 600px) {
  .min-width-indicator {
    min-width: 55px;
  }
}

.bg-light {
  background-color: rgba(var(--v-border-color), 0.05);
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.bg-surface {
  background-color: #fff !important;
}

.grupo-card {
  border: 1px solid rgba(var(--v-border-color), 0.12) !important;
  transition: all 0.2s ease;
}

.grupo-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
  border-color: rgba(var(--v-theme-primary), 0.3) !important;
}

.grupo-header {
  background: rgba(var(--v-border-color), 0.02);
  transition: background 0.2s ease;
  user-select: none;
}

.grupo-header:hover {
  background: rgba(var(--v-theme-primary), 0.04);
}

.grupo-header--expanded {
  background: rgba(var(--v-theme-primary), 0.06);
}

.child-products-table {
  background: transparent !important;
}

:deep(.child-products-table thead tr th) {
  background: rgba(var(--v-border-color), 0.04) !important;
  font-size: 0.65rem !important;
  height: 32px !important;
}

.child-row {
  transition: background 0.2s;
}

.child-row:hover {
  background-color: rgba(var(--v-theme-primary), 0.04) !important;
}

.skeleton-group {
  background: rgba(var(--v-border-color), 0.05);
}

.skeleton-bar {
  height: 12px;
  border-radius: 6px;
  background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}

.skeleton-bar.short { width: 40%; }
.skeleton-bar.medium { width: 75%; }

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.leading-tight { line-height: 1.2 !important; }
.truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
