<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import AppEmptyState from "@/components/AppEmptyState.vue";
import { ref, computed } from "vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { formatDateSimple } from "@/utils/formatters";
import { useBrandingStore } from "@/stores/useBrandingStore";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  productWithError: { type: [Number, null], default: null },
  groups: { type: Array, default: () => [] },
});

const emit = defineEmits(["update:options", "update-product", "group-created"]);
const brandingStore = useBrandingStore();

const editingProductId = ref(null);
const editingValue = ref(null);
const searchInput = ref("");
const currentEditingProduct = ref(null);
const isSavingProductGroup = ref(false);

const headers = computed(() => [
  { 
    title: "ID", 
    key: "id", 
    sortable: true,
    cellClass: "font-weight-black text-primary",
  },
  { 
    title: "PRODUCTO", 
    key: "name", 
    sortable: true,
    width: "45%",
  },
  { 
    title: "GRUPO", 
    key: "group", 
    sortable: true,
    width: "30%",
  },
  {
    title: "STOCK",
    key: "stock_calculado",
    sortable: true,
    align: "end",
  },
  { 
    title: "Accion", 
    key: "actions", 
    sortable: false, 
    align: "center" 
  },
]);

const createGroup = async (name) => {
  try {
    const response = await axios.post("/groups", { name });
    toast.success("Grupo creado con éxito");
    const newGroup = response.data.group || response.data.data || response.data;
    emit("group-created", newGroup);
    return newGroup;
  } catch (err) {
    if (err.response?.status === 422) {
      const errorMessage = err.response.data?.errors?.name?.[0] || err.response.data?.message || "Error al crear el grupo";
      toast.error(errorMessage);
    } else {
      toast.error("Error al crear el grupo");
    }
    throw err;
  }
};

const saveInlineEdit = async (product) => {
  isSavingProductGroup.value = true;
  try {
    await emit("update-product", {
      id: product.id,
      group_id: editingValue.value,
    });
    cancelEdit();
  } catch (err) {
    console.error(err);
  } finally {
    isSavingProductGroup.value = false;
  }
};

const startEdit = (product) => {
  editingProductId.value = product.id;
  editingValue.value = product.group_id || null;
  currentEditingProduct.value = product;
  searchInput.value = "";
};

const cancelEdit = () => {
  editingProductId.value = null;
  editingValue.value = null;
  currentEditingProduct.value = null;
  searchInput.value = "";
  isSavingProductGroup.value = false;
};

const handleGroupSearch = (search) => {
  searchInput.value = search;
};

const handleCreateGroupOnEnter = async () => {
  if (!searchInput.value || !searchInput.value.trim()) return;
  
  const groupName = searchInput.value.trim();
  const exists = props.groups.some(
    (group) => group.name.toLowerCase() === groupName.toLowerCase()
  );
  
  if (exists) {
    toast.error("Ya existe un grupo con ese nombre");
    return;
  }
  
  try {
    const newGroup = await createGroup(groupName);
    editingValue.value = newGroup.id;
    if (currentEditingProduct.value) {
      await saveInlineEdit(currentEditingProduct.value);
    }
  } catch (err) {
    console.error(err);
  }
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
  
  if (validLots.length === 0) return product.ultima_fecha_vencimiento ? formatDateSimple(product.ultima_fecha_vencimiento) : "N/A";
  
  validLots.sort((a, b) => new Date(a.expiration_date) - new Date(b.expiration_date));
  return formatDateSimple(validLots[0].expiration_date);
};

const formatStock = (item) => {
  const stock = Number(item.stock_calculado ?? 0);
  return stock % 1 === 0 ? stock.toString() : stock.toFixed(2).replace(".", ",");
};
</script>

<template>
  <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
    <!-- Desktop Table -->
    <div class="d-none d-sm-block">
      <VDataTableServer
        :headers="headers"
        :items="props.products"
        :items-length="props.totalProduct"
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :loading="props.loading"
        class="text-no-wrap"
        density="compact"
        item-value="id"
        @update:options="(opts) => emit('update:options', opts)"
      >
        <template #no-data>
          <AppEmptyState
            title="¡Todo Organizado!"
            message="No se encontraron productos pendientes de asignación de grupo."
            icon="tabler-packages"
          />
        </template>

        <!-- ID sin símbolo # -->
        <template #item.id="{ item }">
          <a
            :href="'/inventory/traceability?q=' + item.id"
            target="_blank"
            class="text-decoration-none font-weight-black text-primary"
          >
            {{ item.id }}
          </a>
        </template>

        <!-- Producto con activo y laboratorio idéntico a ProductTable -->
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-x-3 py-2">
            <div class="d-flex flex-column min-width-0">
              <span
                class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate"
                :class="{ 
                  'text-warning': item.psychotropic == 1 || item.psychotropic === true
                }"
                style="max-inline-size: 320px;"
                :title="item.name"
              >
                {{ item.name?.toUpperCase() || "—" }}
                <span v-if="item.iva == 1 || item.iva === true" class="text-xs text-disabled"> (G)</span>
                <span v-if="item.is_colombian_origin == 1 || item.is_colombian_origin === true" class="text-xs text-disabled"> (COL)</span>
              </span>
              <div class="d-flex align-center gap-1 text-super-xs">
                <span class="text-disabled truncate" style="max-inline-size: 200px;">
                  {{ item.active_ingredient || item.presentation || 'Sin Especificación' }}
                </span>
                <span class="text-disabled mx-1">|</span>
                <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 150px;">
                  {{ item.laboratory?.name || 'S/L' }}
                </span>
              </div>
            </div>
          </div>
        </template>

        <!-- Columna GRUPO con edición inline interactiva -->
        <template #item.group="{ item }">
          <template v-if="editingProductId === item.id">
            <div class="d-flex align-center gap-1">
              <VAutocomplete
                v-model="editingValue"
                :items="props.groups"
                item-title="name"
                item-value="id"
                variant="outlined"
                density="compact"
                hide-details
                style="max-inline-size: 260px; min-inline-size: 180px;"
                placeholder="Buscar o crear..."
                clearable
                autofocus
                :loading="isSavingProductGroup"
                :disabled="isSavingProductGroup"
                :error="props.productWithError === item.id"
                @keydown.enter.prevent="
                  searchInput && searchInput.trim() && !editingValue
                    ? handleCreateGroupOnEnter()
                    : saveInlineEdit(item)
                "
                @update:search="handleGroupSearch"
                :no-data-text="
                  searchInput && searchInput.trim()
                    ? 'Presiona Enter para crear ' + searchInput
                    : 'No hay grupos disponibles.'
                "
              />
              <IconBtn
                color="success"
                size="small"
                :loading="isSavingProductGroup"
                @click="saveInlineEdit(item)"
              >
                <VIcon icon="tabler-check" size="18" />
                <VTooltip activator="parent">Guardar</VTooltip>
              </IconBtn>
              <IconBtn
                color="secondary"
                size="small"
                :disabled="isSavingProductGroup"
                @click="cancelEdit"
              >
                <VIcon icon="tabler-x" size="18" />
                <VTooltip activator="parent">Cancelar</VTooltip>
              </IconBtn>
            </div>
          </template>
          <template v-else>
            <VChip
              v-if="item.group?.name"
              size="small"
              color="primary"
              variant="tonal"
              class="font-weight-bold cursor-pointer"
              @click="startEdit(item)"
            >
              {{ item.group.name }}
            </VChip>
            <span 
              v-else 
              class="text-xs text-disabled font-weight-medium cursor-pointer text-decoration-underline"
              @click="startEdit(item)"
            >
              Sin Grupo (Asignar)
            </span>
          </template>
        </template>

        <!-- STOCK con chip tonal y menú flotante -->
        <template #item.stock_calculado="{ item }">
          <div class="text-end">
            <VMenu
              v-if="item.lots && item.lots.length > 0 && brandingStore.settings?.enable_lots !== false"
              open-on-hover
              location="bottom end"
              offset="8px"
            >
              <template #activator="{ props: menuProps }">
                <VChip
                  v-bind="menuProps"
                  :color="item.stock_calculado > 0 ? 'success' : 'error'"
                  label
                  size="x-small"
                  variant="tonal"
                  class="font-weight-black cursor-pointer hover-chip"
                >
                  {{ formatStock(item) }}
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
                    v-for="lot in item.lots" 
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
              :color="item.stock_calculado > 0 ? 'success' : 'error'"
              label
              size="x-small"
              variant="tonal"
              class="font-weight-black"
            >
              {{ formatStock(item) }}
            </VChip>
          </div>
        </template>

        <!-- Columna Accion con IconBtn limpio -->
        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <template v-if="editingProductId !== item.id">
              <IconBtn
                color="primary"
                size="small"
                @click="startEdit(item)"
              >
                <VIcon icon="tabler-edit" size="18" />
                <VTooltip activator="parent" location="top">Asignar Grupo</VTooltip>
              </IconBtn>
            </template>
          </div>
        </template>
      </VDataTableServer>
    </div>

    <!-- Mobile Cards -->
    <div class="d-block d-sm-none pa-2">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.products.length === 0 && !props.loading" class="text-center py-8 text-disabled">
        <AppEmptyState
          title="¡Todo Organizado!"
          message="No se encontraron productos pendientes de asignación de grupo."
          icon="tabler-packages"
        />
      </div>

      <div v-else class="d-flex flex-column gap-2">
        <VCard
          v-for="item in props.products"
          :key="item.id"
          class="product-mobile-card border rounded-lg bg-surface pa-3 shadow-none position-relative"
        >
          <div class="d-flex align-center justify-space-between mb-2">
            <div class="d-flex align-center gap-1 min-width-0">
              <span class="text-xs font-weight-black text-primary">#{{ item.id }}</span>
              <span class="text-disabled">|</span>
              <span class="text-xs font-weight-black text-primary uppercase truncate" style="max-inline-size: 150px;">
                {{ item.laboratory?.name || 'S/L' }}
              </span>
            </div>
            <VChip
              :color="item.stock_calculado > 0 ? 'success' : 'error'"
              label
              size="x-small"
              variant="tonal"
              class="font-weight-black"
            >
              {{ formatStock(item) }} UNDS
            </VChip>
          </div>

          <h4 class="text-xs font-weight-black text-high-emphasis uppercase leading-tight mb-1 text-truncate">
            {{ item.name }}
          </h4>

          <div v-if="editingProductId === item.id" class="mt-3 pt-2 border-t">
            <VAutocomplete
              v-model="editingValue"
              :items="props.groups"
              item-title="name"
              item-value="id"
              label="Asignar Grupo"
              variant="outlined"
              density="compact"
              hide-details
              class="mb-2"
              placeholder="Buscar o crear..."
              :loading="isSavingProductGroup"
              :disabled="isSavingProductGroup"
              @update:search="handleGroupSearch"
              @keydown.enter.prevent="handleCreateGroupOnEnter"
            />
            <div class="d-flex gap-2 justify-center">
              <VBtn size="small" variant="tonal" color="secondary" class="flex-grow-1 font-weight-bold" :disabled="isSavingProductGroup" @click="cancelEdit">Cancelar</VBtn>
              <VBtn size="small" color="primary" class="flex-grow-1 font-weight-bold" :loading="isSavingProductGroup" @click="saveInlineEdit(item)">Guardar</VBtn>
            </div>
          </div>

          <div v-else class="d-flex align-center justify-end text-super-xs text-medium-emphasis mt-2 pt-2 border-t">
            <IconBtn
              color="primary"
              size="small"
              @click="startEdit(item)"
            >
              <VIcon icon="tabler-edit" size="18" />
              <VTooltip activator="parent">Asignar Grupo</VTooltip>
            </IconBtn>
          </div>
        </VCard>
      </div>

      <!-- Paginación Móvil -->
      <div class="mt-4">
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

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.text-xs {
  font-size: 0.75rem !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }

:deep(.v-data-table th) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
