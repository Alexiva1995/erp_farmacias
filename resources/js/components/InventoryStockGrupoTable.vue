<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import { formatPrice } from "@/utils/formatters";
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
          class="grupo-header d-flex align-center justify-space-between pa-4 cursor-pointer"
          :class="isExpanded(grupo.group_id || grupo.id) ? 'grupo-header--expanded' : ''"
          @click="toggleGroup(grupo.group_id || grupo.id)"
        >
          <div class="d-flex align-center gap-3">
            <VIcon
              :icon="isExpanded(grupo.group_id || grupo.id) ? 'tabler-chevron-down' : 'tabler-chevron-right'"
              size="20"
              color="primary"
            />
            <div class="d-flex flex-column">
              <span class="text-sm font-weight-black text-uppercase text-high-emphasis leading-tight">
                {{ grupo.name }}
              </span>
              <span class="text-super-xs text-disabled font-weight-bold">
                ID: {{ grupo.group_id ? 'G-' + grupo.group_id : 'ID-' + grupo.id }} | 
                {{ grupo.productos?.length || 0 }} productos integrados
              </span>
            </div>
          </div>

          <!-- Totales rápidos en la cabecera -->
          <div class="d-flex align-center gap-4 d-none d-sm-flex">
            <div class="text-center px-2">
              <span class="text-super-xs text-disabled d-block font-weight-black uppercase">Stock Total</span>
              <span class="text-xs font-weight-black" :class="grupo.lote_quantity > 0 ? 'text-success' : 'text-error'">
                {{ grupo.lote_quantity }}
              </span>
            </div>
            <VDivider vertical class="mx-1" />
            <div class="text-center px-2">
              <span class="text-super-xs text-disabled d-block font-weight-black uppercase">Ventas</span>
              <span class="text-xs font-weight-black">{{ grupo.total_sold_completed }}</span>
            </div>
            <VDivider vertical class="mx-1" />
            <div class="text-center px-2">
              <span class="text-super-xs text-disabled d-block font-weight-black uppercase">Diferencia</span>
              <VChip
                :color="getDiffColor(grupo.diferencia_product)"
                size="x-small"
                variant="flat"
                class="font-weight-black"
              >
                {{ parseFloat(grupo.diferencia_product) > 0 ? '+' : '' }}{{ Math.ceil(parseFloat(grupo.diferencia_product)) }}
              </VChip>
            </div>
          </div>
        </div>

        <!-- Cuerpo el grupo (Tabla de productos hijos) -->
        <VExpandTransition>
          <div v-if="isExpanded(grupo.group_id || grupo.id)" class="grupo-body border-t">
            <div class="table-container overflow-x-auto">
              <VTable density="compact" class="child-products-table">
                <thead>
                  <tr>
                    <th class="text-xs font-weight-black text-uppercase shadow-sm">ID</th>
                    <th class="text-xs font-weight-black text-uppercase shadow-sm">Producto Individual</th>
                    <th class="text-xs font-weight-black text-uppercase text-right shadow-sm">Costo</th>
                    <th class="text-xs font-weight-black text-uppercase text-center shadow-sm">Ventas</th>
                    <th class="text-xs font-weight-black text-uppercase text-center shadow-sm">Stock</th>
                    <th class="text-xs font-weight-black text-uppercase text-center shadow-sm">Pref %</th>
                    <th class="text-xs font-weight-black text-uppercase text-center shadow-sm">Prom.</th>
                    <th class="text-xs font-weight-black text-uppercase text-center shadow-sm">AO</th>
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
                          <span class="text-primary font-weight-black text-uppercase">{{ item.laboratory?.name || 'S/L' }}</span>
                        </div>
                      </div>
                    </td>
                    <td class="text-right text-xs font-weight-medium">{{ formatPrice(item.unit_cost) }}</td>
                    <td class="text-center text-xs">{{ item.total_sold_completed }}</td>
                    <td class="text-center text-xs">
                      <VChip :color="item.lote_quantity > 0 ? 'success' : 'error'" size="x-small" variant="tonal" class="font-weight-black">
                        {{ item.lote_quantity }}
                      </VChip>
                    </td>
                    <td class="text-center text-xs font-weight-bold text-primary">
                      {{ parseFloat(item.preferencia_product || 0).toFixed(1) }}%
                    </td>
                    <td class="text-center text-xs text-disabled">{{ parseFloat(item.promedio_calculado || 0).toFixed(1) }}</td>
                    <td class="text-center">
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
