<script setup>
import { ref, computed } from "vue";
import { useAbility } from "@casl/vue";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { formatDateSimple } from "@/utils/formatters";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => brandingStore.settings?.business_type === "restaurant");
const isMiniMarket = computed(() => brandingStore.settings?.business_type === "minimarket");
const isSportsRental = computed(() => brandingStore.settings?.business_type === "sports_rental");

const { can } = useAbility();

const props = defineProps({
  groups: { type: Array, required: true },
  loading: Boolean,
  totalGroups: { type: Number, default: 0 },
  itemsPerPage: { type: Number, default: 10 },
  page: { type: Number, default: 1 },
});

const emit = defineEmits([
  "update:options",
  "edit-group",
  "delete-group",
  "show-group",
  "add-products",
  "refresh"
]);

const selectedGroups = ref([]);

const handleUnassign = async (product, group) => {
  const result = await Swal.fire({
    title: "¿Desvincular producto?",
    text: `El producto "${product.name}" será desvinculado del grupo "${group.name}".`,
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Desvincular",
    cancelButtonText: "Cancelar",
    reverseButtons: true
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/products/${product.id}/unassign-group`);
      toast.success("Producto desvinculado con éxito.");
      if (group && Array.isArray(group.products)) {
        const idx = group.products.findIndex((p) => p.id === product.id);
        if (idx !== -1) group.products.splice(idx, 1);
      }
      emit("refresh");
    } catch (e) {
      console.error("Error al desvincular el producto:", e);
      toast.error("Ocurrió un error al desvincular el producto.");
    }
  }
};

const handleBulkDelete = async () => {
  const ids = selectedGroups.value;
  if (!ids.length) return;

  const result = await Swal.fire({
    title: "¿Eliminar grupos seleccionados?",
    text: `Se eliminarán permanentemente ${ids.length} grupos seleccionados.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Eliminar",
    cancelButtonText: "Cancelar",
    reverseButtons: true
  });

  if (result.isConfirmed) {
    try {
      for (const id of ids) {
        await axios.delete(`/groups/${id}`);
      }
      toast.success("Grupos eliminados correctamente.");
      selectedGroups.value = [];
      emit("refresh");
    } catch (e) {
      toast.error("Ocurrió un error al eliminar los grupos.");
    }
  }
};

const expandedGroupId = ref(null);

const toggleGroup = (groupId) => {
  if (expandedGroupId.value === groupId) {
    expandedGroupId.value = null;
  } else {
    expandedGroupId.value = groupId;
  }
};

const isExpanded = (groupId) => expandedGroupId.value === groupId;

const totalPages = computed(() => Math.ceil(props.totalGroups / props.itemsPerPage));

const handlePageChange = (newPage) => {
  emit("update:options", {
    page: newPage,
    itemsPerPage: props.itemsPerPage,
    sortBy: [],
    groupBy: [],
  });
};

const handleItemsPerPageChange = (val) => {
  emit("update:options", {
    page: 1,
    itemsPerPage: val,
    sortBy: [],
    groupBy: [],
  });
};

const nextExpirationDate = (product) => {
  if (!product.lots || !Array.isArray(product.lots) || product.lots.length === 0) return "N/A";
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const validLots = product.lots.filter((lot) => {
    if (!lot.expiration_date) return false;
    const expirationDate = new Date(lot.expiration_date);
    return !isNaN(expirationDate.getTime()) && expirationDate >= today;
  });
  if (validLots.length === 0) return product.ultima_fecha_vencimiento ? formatDateSimple(product.ultima_fecha_vencimiento) : "EXPIRADO";
  validLots.sort((a, b) => new Date(a.expiration_date) - new Date(b.expiration_date));
  const closestDate = new Date(validLots[0].expiration_date);
  return formatDateSimple(closestDate);
};

const formatStock = (item) => {
  const stock = Number(item.stock_calculado ?? 0);
  if (isSportsRental.value || isMiniMarket.value) {
    return Math.round(stock).toString();
  }
  if (!isRestaurant.value) {
    return stock % 1 === 0 ? stock.toString() : stock.toFixed(2).replace(".", ",");
  }
  if (!item.unit_of_measure) {
    const formatted = stock.toString().replace(".", ",");
    return `${formatted} UNDS`;
  }
  const presentation = Number(item.presentation) || 0;
  if (presentation > 0 && (item.unit_of_measure === "g" || item.unit_of_measure === "ml")) {
    const unit = item.unit_of_measure;
    const totalUnits = Math.round(stock * 1000);
    if (totalUnits < 0) {
      return `${totalUnits} ${unit}`;
    }
    const fullPackages = Math.floor(totalUnits / presentation);
    const remainder = totalUnits % presentation;
    if (fullPackages > 0 && remainder > 0) {
      return `${fullPackages} paq + ${remainder} ${unit}`;
    } else if (fullPackages > 0) {
      return `${fullPackages} paq`;
    } else {
      return `${remainder} ${unit}`;
    }
  }
  return `${stock} UNDS`;
};

const getProductLocations = (item) => {
  if (item.lot_locations && Array.isArray(item.lot_locations)) {
    return item.lot_locations.filter(Boolean);
  }
  if (item.lots && Array.isArray(item.lots)) {
    const locs = item.lots.map(l => l.location).filter(l => l && String(l).trim() !== '');
    return [...new Set(locs)];
  }
  if (item.location) {
    return [item.location];
  }
  return [];
};
</script>

<template>
  <VCard class="rounded-lg border shadow-sm bg-surface overflow-hidden">
    <!-- Estado: Cargando -->
    <div v-if="loading" class="pa-10 text-center">
      <VProgressCircular indeterminate color="primary" size="48" thickness="4" />
      <p class="text-disabled mt-3 font-weight-bold uppercase">Cargando grupos...</p>
    </div>

    <!-- Estado: Vacío -->
    <div v-else-if="groups.length === 0" class="pa-16 text-center text-disabled">
      <VIcon icon="tabler-category-2" size="64" class="mb-4 opacity-20" />
      <h3 class="text-h6 font-weight-black opacity-50 uppercase">No se encontraron grupos</h3>
      <p>Intenta ajustar los filtros de búsqueda</p>
    </div>

    <!-- Lista de Acordeones -->
    <div v-else class="pa-2 pa-sm-3">
      <div
        v-for="group in groups"
        :key="group.id"
        class="group-accordion mb-3 rounded-lg border overflow-hidden transition-all"
        :class="{ 'group-accordion--expanded shadow-md': isExpanded(group.id) }"
      >
        <!-- Cabecera del Acordeón -->
        <div
          class="group-header d-flex flex-wrap align-center justify-space-between pa-3 cursor-pointer select-none"
          @click="toggleGroup(group.id)"
        >
          <div class="d-flex align-center gap-3">
            <!-- Checkbox de Selección Masiva -->
            <VCheckboxBtn
              v-model="selectedGroups"
              :value="group.id"
              class="flex-shrink-0"
              @click.stop
            />
            <VIcon
              :icon="isExpanded(group.id) ? 'tabler-chevron-down' : 'tabler-chevron-right'"
              size="20"
              color="primary"
              class="transition-all"
            />
            <div class="d-flex flex-column">
              <span class="text-sm font-weight-black text-uppercase text-high-emphasis leading-tight">
                {{ group.name }}
              </span>
              <div class="d-flex align-center gap-1 mt-1">
                <span class="text-super-xs text-primary font-weight-bold">{{ group.id }}</span>
                <span class="text-super-xs text-disabled">|</span>
                <!-- Chip de Conteo Estilizado -->
                <VChip
                  size="x-small"
                  color="primary"
                  variant="tonal"
                  class="font-weight-black text-super-xs rounded px-1.5"
                >
                  {{ group.products?.length || 0 }} PRODUCTOS
                </VChip>
              </div>
            </div>
          </div>

          <!-- Acciones de Cabecera -->
          <div class="d-flex align-center gap-1 mt-2 mt-sm-0" @click.stop>
            <VTooltip text="Añadir Productos" location="top">
              <template #activator="{ props: tooltipProps }">
                <VBtn
                  v-bind="tooltipProps"
                  icon="tabler-plus"
                  variant="tonal"
                  color="success"
                  size="32"
                  class="rounded-lg shadow-sm"
                  @click="emit('add-products', group)"
                />
              </template>
            </VTooltip>

            <VTooltip text="Editar Grupo" location="top">
              <template #activator="{ props: tooltipProps }">
                <VBtn
                  v-bind="tooltipProps"
                  icon="tabler-edit"
                  variant="tonal"
                  color="warning"
                  size="32"
                  class="rounded-lg shadow-sm"
                  @click="emit('edit-group', group)"
                />
              </template>
            </VTooltip>

            <VTooltip v-if="can('manage', 'admin')" text="Eliminar Grupo" location="top">
              <template #activator="{ props: tooltipProps }">
                <VBtn
                  v-bind="tooltipProps"
                  icon="tabler-trash"
                  variant="tonal"
                  color="error"
                  size="32"
                  class="rounded-lg shadow-sm"
                  @click="emit('delete-group', group.id)"
                />
              </template>
            </VTooltip>
          </div>
        </div>

        <!-- Cuerpo del Acordeón (Productos) -->
        <div v-if="isExpanded(group.id)" class="group-body bg-light pa-0 animate-fade-in">
          <VDivider />
          
          <!-- Vista Desktop (Tabla Idéntica a ProductTable) -->
          <div class="d-none d-sm-block pa-2">
            <VTable density="compact" class="bg-surface border rounded-lg overflow-hidden premium-inner-table">
              <thead>
                <tr>
                  <th class="text-super-xs font-weight-black uppercase" style="inline-size: 70px;">ID</th>
                  <th class="text-super-xs font-weight-black uppercase" style="inline-size: 50%;">PRODUCTO</th>
                  <th
                    v-if="brandingStore.settings?.enable_lots !== false"
                    class="text-super-xs font-weight-black uppercase"
                    style="inline-size: 110px;"
                  >
                    EXP.
                  </th>
                  <th class="text-super-xs font-weight-black uppercase text-end" style="inline-size: 110px;">STOCK</th>
                  <th class="text-super-xs font-weight-black uppercase text-center" style="inline-size: 80px;">ACCIONES</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="product in group.products" :key="product.id" class="product-row-hover">
                  <!-- ID -->
                  <td>
                    <a
                      :href="'/inventory/traceability?q=' + product.id"
                      target="_blank"
                      class="text-decoration-none font-weight-black text-primary text-xs"
                    >
                      {{ product.id }}
                    </a>
                  </td>

                  <!-- PRODUCTO -->
                  <td>
                    <div class="d-flex align-center gap-x-3 py-2">
                      <div class="d-flex flex-column min-width-0">
                        <span
                          class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate"
                          :class="{ 
                            'text-warning': product.psychotropic == 1 || product.psychotropic === true
                          }"
                          style="max-inline-size: 340px;"
                          :title="product.name"
                        >
                          {{ product.name?.toUpperCase() || "—" }}
                          <span v-if="product.iva == 1 || product.iva === true" class="text-xs text-disabled"> (G)</span>
                          <span v-if="product.is_colombian_origin == 1 || product.is_colombian_origin === true" class="text-xs text-disabled"> (COL)</span>
                        </span>
                        <div class="d-flex align-center flex-wrap gap-1 text-super-xs">
                          <span v-if="!isRestaurant" class="text-disabled truncate" style="max-inline-size: 200px;">
                            {{ product.active_ingredient || product.presentation || 'Sin Especificación' }}
                          </span>
                          <span v-if="isRestaurant && product.presentation" class="text-disabled truncate" style="max-inline-size: 200px;">
                            {{ product.presentation }} {{ product.unit_of_measure ? `(${product.unit_of_measure})` : '' }}
                          </span>
                          <span class="text-disabled mx-1">|</span>
                          <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 150px;">
                            {{ product.laboratory?.name || 'S/L' }}
                          </span>
                          <template v-if="getProductLocations(product).length > 0">
                            <span class="text-disabled mx-1">|</span>
                            <span class="text-success font-weight-black text-uppercase">
                              📍 {{ getProductLocations(product).join(', ') }}
                            </span>
                          </template>
                        </div>
                      </div>
                    </div>
                  </td>

                  <!-- EXP. / VENCIMIENTO -->
                  <td v-if="brandingStore.settings?.enable_lots !== false">
                    <span class="text-xs font-weight-medium">
                      {{ nextExpirationDate(product) }}
                    </span>
                  </td>

                  <!-- STOCK -->
                  <td class="text-end">
                    <!-- Menú flotante interactivo para ver desglose de lotes -->
                    <VMenu
                      v-if="product.lots && product.lots.length > 0 && brandingStore.settings?.enable_lots !== false"
                      open-on-hover
                      location="bottom end"
                      offset="8px"
                    >
                      <template #activator="{ props: menuProps }">
                        <VChip
                          v-bind="menuProps"
                          :color="(product.stock_calculado || 0) > 0 ? 'success' : 'error'"
                          label
                          size="x-small"
                          variant="tonal"
                          class="font-weight-black cursor-pointer hover-chip"
                        >
                          {{ formatStock(product) }}
                          <VIcon icon="tabler-info-circle" size="12" class="ms-1" />
                        </VChip>
                      </template>
                      <VCard min-width="280" class="rounded-xl border shadow-lg pa-3">
                        <div class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-2 d-flex align-center gap-1">
                          <VIcon icon="tabler-clipboard-list" size="14" />
                          Desglose de Lotes
                        </div>
                        <VDivider class="mb-2" />
                        <div style="max-height: 180px; overflow-y: auto;">
                          <div 
                            v-for="lot in product.lots" 
                            :key="lot.id"
                            class="d-flex align-center justify-space-between py-1 border-bottom-light"
                          >
                            <div class="d-flex flex-column text-left">
                              <span class="text-xs font-weight-bold text-high-emphasis">Lote: {{ lot.lot_number }}</span>
                              <span class="text-super-xs text-disabled">Exp: {{ formatDateSimple(lot.expiration_date) }}</span>
                            </div>
                            <VChip size="x-small" label color="secondary" variant="flat" class="font-weight-black">
                              {{ lot.quantity }}
                            </VChip>
                          </div>
                        </div>
                      </VCard>
                    </VMenu>
                    <VChip
                      v-else
                      :color="(product.stock_calculado || 0) > 0 ? 'success' : 'error'"
                      label
                      size="x-small"
                      variant="tonal"
                      class="font-weight-black"
                    >
                      {{ formatStock(product) }}
                    </VChip>
                  </td>

                  <!-- ACCIONES -->
                  <td class="text-center">
                    <IconBtn
                      color="error"
                      size="small"
                      @click="handleUnassign(product, group)"
                    >
                      <VIcon icon="tabler-link-off" size="18" />
                      <VTooltip activator="parent" location="top">Desvincular del Grupo</VTooltip>
                    </IconBtn>
                  </td>
                </tr>
                <tr v-if="group.products?.length === 0">
                  <td :colspan="brandingStore.settings?.enable_lots !== false ? 5 : 4" class="text-center py-6 text-disabled font-weight-bold uppercase text-xs">
                    No hay productos vinculados a este grupo
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>

          <!-- Vista Móvil (Tarjetas de Productos) -->
          <div class="d-block d-sm-none pa-2 d-flex flex-column gap-2">
            <div
              v-for="product in group.products"
              :key="product.id"
              class="pa-3 bg-surface rounded-lg border d-flex flex-column gap-2 shadow-none position-relative"
            >
              <div class="d-flex align-center justify-space-between">
                <div class="d-flex align-center gap-1 min-width-0">
                  <a
                    :href="'/inventory/traceability?q=' + product.id"
                    target="_blank"
                    class="text-xs font-weight-black text-primary text-decoration-none"
                  >
                    {{ product.id }}
                  </a>
                  <span class="text-disabled">|</span>
                  <span class="text-xs font-weight-black text-primary uppercase truncate" style="max-inline-size: 150px;">
                    {{ product.laboratory?.name || 'S/L' }}
                  </span>
                </div>
                <VChip
                  :color="(product.stock_calculado || 0) > 0 ? 'success' : 'error'"
                  label
                  size="x-small"
                  variant="tonal"
                  class="font-weight-black"
                >
                  {{ formatStock(product) }} UNDS
                </VChip>
              </div>

              <h4 class="text-xs font-weight-black text-high-emphasis uppercase leading-tight mb-0 text-truncate">
                {{ product.name }}
                <span v-if="product.iva == 1 || product.iva === true" class="text-super-xs text-disabled"> (G)</span>
                <span v-if="product.is_colombian_origin == 1 || product.is_colombian_origin === true" class="text-super-xs text-disabled"> (COL)</span>
              </h4>

              <div class="d-flex align-center justify-space-between text-super-xs text-disabled pt-1 border-t">
                <span v-if="brandingStore.settings?.enable_lots !== false">
                  Vence: <strong class="text-high-emphasis font-weight-bold">{{ nextExpirationDate(product) }}</strong>
                </span>
                <IconBtn
                  color="error"
                  size="small"
                  class="ms-auto"
                  @click="handleUnassign(product, group)"
                >
                  <VIcon icon="tabler-link-off" size="16" />
                  <VTooltip activator="parent">Desvincular</VTooltip>
                </IconBtn>
              </div>
            </div>
            <div v-if="group.products?.length === 0" class="text-center py-4 text-disabled text-xs font-weight-bold">
              SIN PRODUCTOS
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Paginación Footer Desktop Style -->
    <div v-if="!loading && totalGroups > 0" class="d-flex flex-column flex-sm-row align-center justify-space-between pa-4 border-t bg-light-gray gap-4">
      <div class="d-flex align-center gap-4">
        <span class="text-xs text-disabled font-weight-bold uppercase">
          Mostrando {{ (page - 1) * itemsPerPage + 1 }}–{{ Math.min(page * itemsPerPage, totalGroups) }} de {{ totalGroups }} grupos
        </span>
        <VSelect
          :model-value="props.itemsPerPage"
          :items="[10, 25, 50, 100]"
          density="compact"
          variant="outlined"
          hide-details
          class="items-per-page-select"
          @update:model-value="handleItemsPerPageChange"
        />
      </div>

      <VPagination
        v-model="props.page"
        :length="totalPages"
        :total-visible="$vuetify.display.xs ? 3 : 5"
        density="compact"
        @update:model-value="handlePageChange"
      />
    </div>

    <!-- Barra de Acciones Masivas Flotante para Grupos -->
    <Transition name="fade-slide">
      <div v-if="selectedGroups.length > 0" class="bulk-actions-wrapper">
        <VCard class="bulk-actions-bar px-6 py-3 d-flex align-center justify-space-between rounded-pill elevation-10">
          <div class="d-flex align-center gap-3">
            <VChip color="primary" class="font-weight-black">{{ selectedGroups.length }}</VChip>
            <span class="text-subtitle-2 font-weight-black text-high-emphasis">Grupos seleccionados</span>
          </div>

          <div class="d-flex align-center gap-2">
            <!-- Eliminar Masivo -->
            <VBtn
              color="error"
              class="rounded-pill font-weight-black"
              size="small"
              prepend-icon="tabler-trash"
              @click="handleBulkDelete"
            >
              Eliminar en Bloque
            </VBtn>

            <VDivider vertical class="mx-2 border-opacity-20" />

            <!-- Deseleccionar Todo -->
            <VBtn
              icon="tabler-x"
              variant="text"
              density="compact"
              color="secondary"
              @click="selectedGroups = []"
            />
          </div>
        </VCard>
      </div>
    </Transition>
  </VCard>
</template>

<style scoped>
.hover-chip:hover {
  filter: brightness(0.95);
  box-shadow: 0 2px 4px rgba(0,0,0,0.08);
}

.border-bottom-light {
  border-bottom: 1px solid rgba(var(--v-border-color), 0.08);
}
.border-bottom-light:last-child {
  border-bottom: none;
}

.bulk-actions-wrapper {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 1000;
  width: 100%;
  max-width: 600px;
  padding: 0 16px;
}

.bulk-actions-bar {
  background: rgba(var(--v-theme-surface), 0.85) !important;
  backdrop-filter: blur(12px) saturate(190%);
  border: 1px solid rgba(var(--v-border-color), 0.24) !important;
}

.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.3s ease;
}

.fade-slide-enter-from {
  opacity: 0;
  transform: translate(-50%, 30px);
}

.fade-slide-leave-to {
  opacity: 0;
  transform: translate(-50%, 30px);
}

.group-accordion {
  background-color: rgb(var(--v-theme-surface));
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  border-color: rgba(var(--v-border-color), 0.08) !important;
}

.group-accordion:hover {
  border-color: rgba(var(--v-theme-primary), 0.2) !important;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.group-accordion--expanded {
  border-color: rgb(var(--v-theme-primary)) !important;
  background-color: #fff;
}

.group-header {
  transition: background-color 0.2s;
}

.group-header:hover {
  background-color: rgba(var(--v-theme-primary), 0.03);
}

.group-accordion--expanded .group-header {
  background-color: rgba(var(--v-theme-primary), 0.05);
}

.bg-light {
  background-color: #f8fafc;
}

.bg-light-gray {
  background-color: rgba(var(--v-border-color), 0.02);
}

.premium-inner-table :deep(th) {
  background-color: rgba(var(--v-border-color), 0.04) !important;
  color: rgba(var(--v-theme-on-surface), 0.6) !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.product-row-hover:hover td {
  background-color: rgba(var(--v-theme-primary), 0.02) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.text-xs {
  font-size: 0.75rem !important;
}

.leading-tight {
  line-height: 1.25 !important;
}

.leading-none {
  line-height: 1 !important;
}

.animate-fade-in {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-5px); }
  to { opacity: 1; transform: translateY(0); }
}

.transition-all {
  transition: all 0.3s ease;
}

.shadow-md {
  box-shadow: 0 8px 30px rgba(0,0,0,0.12) !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.items-per-page-select {
  inline-size: 80px;
}

:deep(.v-pagination__list) {
  justify-content: flex-end;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
