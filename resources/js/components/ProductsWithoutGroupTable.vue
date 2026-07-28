<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import AppEmptyState from "@/components/AppEmptyState.vue";
import { ref, computed } from "vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { formatDateSimple } from "@/utils/formatters";

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
    cellClass: "d-none d-sm-table-cell",
    headerClass: "d-none d-sm-table-cell"
  },
  { title: "Producto", key: "name", sortable: true },
  { 
    title: "Laboratorio", 
    key: "laboratory.name", 
    sortable: true,
    cellClass: "d-none d-md-table-cell",
    headerClass: "d-none d-md-table-cell"
  },
  { title: "Grupo de Producto", key: "group", sortable: true },
  {
    title: "Stock",
    key: "valid_stock",
    visible: true,
    sortable: true,
    value: (item) => {
      return item.stock_calculado !== null && item.stock_calculado !== undefined ? Math.round(item.stock_calculado) : 0;
    },
    cellClass: "d-none d-lg-table-cell",
    headerClass: "d-none d-lg-table-cell"
  },
  { title: "Exp.", key: "next_expiration", sortable: true },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
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

const handleCreateGroupOnEnter = async (event) => {
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
</script>

<template>
  <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
    <!-- Desktop Table -->
    <div class="d-none d-md-block">
      <VDataTableServer
        :headers="headers"
        :items="products"
        :items-length="totalProduct"
        :items-per-page="itemsPerPage"
        :page="page"
        :loading="loading"
        class="text-no-wrap premium-table"
        @update:options="(opts) => emit('update:options', opts)"
      >
        <template #no-data>
          <AppEmptyState
            title="¡Todo Organizado!"
            message="No se encontraron productos pendientes de asignación de grupo."
            icon="tabler-packages"
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
                class="text-sm font-weight-bold text-high-emphasis"
                :class="{ 'text-warning': item.psychotropic == 1 || item.psychotropic === true }"
              >
                {{ item.name.toUpperCase() }}
                <span v-if="item.iva == 1 || item.iva === true" class="text-xs text-info ms-1">(G)</span>
                <span v-if="item.is_colombian_origin == 1 || item.is_colombian_origin === true" class="text-xs text-success ms-1">(COL)</span>
              </span>
              <span class="text-xs text-disabled truncate" style="max-width: 280px;">
                {{ item.active_ingredient || item.presentation || 'Sin Principio Activo' }}
              </span>
            </div>
          </div>
        </template>

        <template #item.laboratory.name="{ item }">
          <span class="text-xs font-weight-medium text-capitalize">{{ item.laboratory?.name || "—" }}</span>
        </template>

        <template #item.group="{ item }">
          <template v-if="editingProductId === item.id">
            <VAutocomplete
              v-model="editingValue"
              :items="props.groups"
              item-title="name"
              item-value="id"
              variant="outlined"
              density="compact"
              hide-details
              style="max-inline-size: 320px; min-inline-size: 220px;"
              placeholder="Buscar o crear grupo..."
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
          </template>
          <template v-else>
            <VChip
              v-if="item.group?.name"
              size="small"
              color="primary"
              variant="tonal"
              class="font-weight-bold"
            >
              {{ item.group.name }}
            </VChip>
            <span v-else class="text-xs text-disabled font-weight-medium">Sin Grupo</span>
          </template>
        </template>

        <template #item.valid_stock="{ item }">
          <span class="text-xs font-weight-black" :class="item.stock_calculado > 0 ? 'text-success' : 'text-error'">
            {{ item.stock_calculado !== null && item.stock_calculado !== undefined ? Math.round(item.stock_calculado) : 0 }}
          </span>
        </template>

        <template #item.next_expiration="{ item }">
          <span class="text-xs font-weight-medium">{{ nextExpirationDate(item) }}</span>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <template v-if="editingProductId === item.id">
              <VBtn
                icon
                size="30"
                color="success"
                variant="flat"
                class="rounded-lg shadow-sm me-1"
                :loading="isSavingProductGroup"
                @click="saveInlineEdit(item)"
              >
                <VIcon icon="tabler-check" size="18" />
              </VBtn>
              <VBtn
                icon
                size="30"
                color="secondary"
                variant="tonal"
                class="rounded-lg shadow-sm"
                :disabled="isSavingProductGroup"
                @click="cancelEdit"
              >
                <VIcon icon="tabler-x" size="18" />
              </VBtn>
            </template>
            <template v-else>
              <VBtn
                icon
                size="32"
                color="warning"
                variant="tonal"
                class="rounded-lg shadow-sm"
                @click="startEdit(item)"
              >
                <VIcon icon="tabler-edit" size="18" />
                <VTooltip activator="parent" location="top">Asignar Grupo</VTooltip>
              </VBtn>
            </template>
          </div>
        </template>
      </VDataTableServer>
    </div>

    <!-- Mobile Cards -->
    <div class="d-block d-md-none">
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
                  {{ item.active_ingredient || item.presentation || 'Sin Especificación' }}
                </span>
              </div>
            </div>

            <VDivider class="my-3 opacity-10" />

            <div class="d-flex justify-space-between align-center mb-3">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold">Stock Disponible</span>
                <span class="text-xs font-weight-black" :class="item.stock_calculado > 0 ? 'text-success' : 'text-error'">
                  {{ item.stock_calculado !== null && item.stock_calculado !== undefined ? Math.round(item.stock_calculado) : 0 }} <small>UNDS</small>
                </span>
              </div>
              <div class="d-flex flex-column text-right">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold">Vencimiento Próximo</span>
                <span class="text-xs font-weight-bold">{{ nextExpirationDate(item) }}</span>
              </div>
            </div>

            <!-- Edición Móvil -->
            <div v-if="editingProductId === item.id" class="pa-3 bg-surface-variant-opacity rounded-lg border">
              <VAutocomplete
                v-model="editingValue"
                :items="props.groups"
                item-title="name"
                item-value="id"
                label="Asignar Grupo"
                variant="outlined"
                density="compact"
                hide-details
                class="mb-3"
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

            <div v-else class="d-flex justify-space-between align-center pa-2 rounded-lg bg-surface-variant-opacity border">
              <span class="text-super-xs text-disabled text-uppercase font-weight-bold">Grupo:</span>
              <VChip v-if="item.group?.name" size="x-small" color="primary" variant="flat" class="font-weight-bold">
                {{ item.group.name }}
              </VChip>
              <span v-else class="text-xs font-weight-bold text-error">Sin Grupo Asignado</span>
            </div>
          </div>

          <div v-if="editingProductId !== item.id" class="border-t">
            <VBtn
              block
              color="warning"
              variant="text"
              class="rounded-0 text-xs font-weight-black"
              height="40"
              prepend-icon="tabler-edit"
              @click="startEdit(item)"
            >
              ASIGNAR GRUPO
            </VBtn>
          </div>
        </VCard>

        <!-- Paginación Móvil -->
        <div class="d-flex justify-center mt-2">
           <AppMobilePagination
            :page="props.page"
            :items-per-page="props.itemsPerPage"
            :total-items="props.totalProduct"
            :loading="props.loading"
            @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
          />
        </div>
      </div>

      <div v-else class="pa-4">
        <AppEmptyState
          title="¡Todo Organizado!"
          message="No se encontraron productos pendientes de asignación de grupo."
          icon="tabler-packages"
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
