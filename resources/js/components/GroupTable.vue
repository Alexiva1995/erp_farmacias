<script setup>
import { ref, computed } from "vue";
import { useAbility } from "@casl/vue";
import { useBrandingStore } from "@/stores/useBrandingStore";

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => false);

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
      emit("refresh"); // refrescar el listado general
    } catch (e) {
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
            <!-- Checkbox de Selección Masiva (Evita expandir al hacer clic) -->
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
                <span class="text-super-xs text-primary font-weight-bold">#{{ group.id }}</span>
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
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
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
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
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
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
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
          
          <!-- Desktop Table -->
          <div class="d-none d-sm-block pa-2">
            <VTable density="compact" class="bg-transparent border rounded-lg overflow-hidden premium-inner-table">
              <thead>
                <tr>
                  <th class="text-super-xs font-weight-black uppercase text-center" style="width: 60px;">ID</th>
                  <th class="text-super-xs font-weight-black uppercase">Nombre del Producto</th>
                  <th class="text-super-xs font-weight-black uppercase">{{ isRestaurant ? 'Marca' : 'Laboratorio' }}</th>
                  <th class="text-super-xs font-weight-black uppercase text-center" style="width: 100px;">S. Actual</th>
                  <th class="text-super-xs font-weight-black uppercase text-center" style="width: 80px;">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="product in group.products" :key="product.id" class="product-row-hover">
                  <td class="text-center">
                    <a :href="'/inventory/traceability?q=' + product.id" target="_blank" class="text-xs font-weight-bold text-primary text-decoration-none">
                      #{{ product.id }}
                    </a>
                  </td>
                  <td>
                    <div class="d-flex flex-column py-1">
                      <span class="text-xs font-weight-black uppercase text-high-emphasis">
                        {{ product.name }}
                        <VChip v-if="product.iva == 1" size="x-super-small" color="success" variant="flat" class="ms-1 font-weight-black">G</VChip>
                      </span>
                      <span v-if="!isRestaurant" class="text-super-xs text-disabled uppercase truncate" style="max-inline-size: 300px;">{{ product.active_ingredient || 'Sin componente' }}</span>
                      <span v-if="isRestaurant && product.presentation" class="text-super-xs text-disabled uppercase truncate" style="max-inline-size: 300px;">
                        {{ product.presentation }} {{ product.unit_of_measure ? `(${product.unit_of_measure})` : '' }}
                      </span>
                    </div>
                  </td>
                  <td>
                    <span class="text-xs font-weight-bold text-disabled uppercase">{{ product.laboratory?.name || 'S/L' }}</span>
                  </td>
                  <td class="text-center">
                    <VChip
                      :color="(product.stock_calculado || 0) > 0 ? 'info' : 'error'"
                      variant="tonal"
                      size="x-small"
                      class="font-weight-black"
                    >
                      {{ Math.round(product.stock_calculado || 0) }} UNID
                    </VChip>
                  </td>
                  <td class="text-center">
                    <VTooltip text="Desvincular del Grupo" location="top">
                      <template #activator="{ props }">
                        <VBtn
                          v-bind="props"
                          icon="tabler-link-off"
                          variant="text"
                          color="error"
                          density="compact"
                          @click="handleUnassign(product, group)"
                        />
                      </template>
                    </VTooltip>
                  </td>
                </tr>
                <tr v-if="group.products?.length === 0">
                  <td colspan="5" class="text-center py-4 text-disabled font-weight-bold uppercase text-xs">
                    No hay productos vinculados a este grupo
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>

          <!-- Mobile Cards -->
          <div class="d-block d-sm-none pa-2 d-flex flex-column gap-2">
             <div v-for="product in group.products" :key="product.id" class="pa-2 bg-white rounded border d-flex align-center gap-3">
                <VAvatar color="primary" variant="tonal" size="32" rounded>
                   <VIcon icon="tabler-package" size="16" />
                </VAvatar>
                <div class="flex-grow-1 overflow-hidden">
                   <div class="d-flex justify-space-between align-center">
                      <span class="text-super-xs font-weight-bold text-primary">#{{ product.id }}</span>
                      <span class="text-super-xs font-weight-black" :class="(product.stock_calculado || 0) > 0 ? 'text-info' : 'text-error'">
                        {{ Math.round(product.stock_calculado || 0) }} UNID
                      </span>
                   </div>
                   <h4 class="text-xs font-weight-black uppercase truncate leading-none mt-1">{{ product.name }}</h4>
                </div>
                <!-- Botón desvincular móvil -->
                <VBtn
                  icon="tabler-link-off"
                  variant="text"
                  color="error"
                  density="compact"
                  class="flex-shrink-0"
                  @click="handleUnassign(product, group)"
                />
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
  font-size: 0.62rem !important;
  line-height: normal;
}

.x-super-small {
  height: 14px !important;
  font-size: 0.6rem !important;
  padding: 0 4px !important;
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
</style>
