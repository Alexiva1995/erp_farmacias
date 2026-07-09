<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
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
const isRestaurant = computed(() => (brandingStore.settings.business_type === 'restaurant' || brandingStore.settings.business_type === 'minimarket'));

const editingProductId = ref(null);
const editingValue = ref(null);
const searchInput = ref("");
const currentEditingProduct = ref(null);

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
    title: isRestaurant.value ? "Marca" : "Laboratorio", 
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
  { title: "Acciones", key: "actions", sortable: false },
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
  try {
    emit("update-product", {
      id: product.id,
      group_id: editingValue.value,
    });
  } catch (err) {
    console.error(err);
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
};

const handleGroupSearch = (search) => {
  searchInput.value = search;
};

const handleCreateGroupOnEnter = async (event) => {
  if (!searchInput.value || !searchInput.value.trim()) return;
  
  const groupName = searchInput.value.trim();
  
  // Verificar si ya existe un grupo con ese nombre (case-insensitive)
  const exists = props.groups.some(
    (group) => group.name.toLowerCase() === groupName.toLowerCase()
  );
  
  if (exists) {
    toast.error("Ya existe un grupo con ese nombre");
    return;
  }
  
  try {
    const newGroup = await createGroup(groupName);
    // Asignar el nuevo grupo al producto
    editingValue.value = newGroup.id;
    // Guardar automáticamente
    if (currentEditingProduct.value) {
      await saveInlineEdit(currentEditingProduct.value);
    }
  } catch (err) {
    // El error ya se maneja en createGroup
    console.error(err);
  }
};

const nextExpirationDate = (product) => {
  if (
    !product.lots ||
    !Array.isArray(product.lots) ||
    product.lots.length === 0
  )
    return "N/A";
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const validLots = product.lots.filter((lot) => {
    if (!lot.expiration_date) return false;
    const expirationDate = new Date(lot.expiration_date);
    return !isNaN(expirationDate.getTime()) && expirationDate >= today;
  });
  if (validLots.length === 0) return product.ultima_fecha_vencimiento || "N/A";
  validLots.sort(
    (a, b) => new Date(a.expiration_date) - new Date(b.expiration_date)
  );
  const closestDate = new Date(validLots[0].expiration_date);
  return formatDate(closestDate);
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
          <div class="d-flex align-center gap-x-4">
            <VAvatar
              v-if="item.photo_url"
              size="38"
              variant="tonal"
              rounded
              :image="item.photo_url"
            />
            <div class="d-flex flex-column">
              <span
                class="text-body-1 font-weight-medium text-high-emphasis"
                :class="{ 
                  'text-warning font-weight-bold': item.psychotropic == 1 || item.psychotropic === true
                }"
              >
                {{ item.name.toUpperCase() }}
                <span v-if="item.iva == 1 || item.iva === true"> (G)</span>
                <span v-if="item.is_colombian_origin == 1 || item.is_colombian_origin === true"> (COL)</span>
              </span>
              <span v-if="!isRestaurant" class="text-sm text-disabled">{{
                item.active_ingredient
              }}</span>
              <span v-if="isRestaurant && item.presentation" class="text-sm text-disabled">
                {{ item.presentation }} {{ item.unit_of_measure ? `(${item.unit_of_measure})` : '' }}
              </span>
            </div>
          </div>
        </template>

        <template #item.laboratory.name="{ item }">
          <span>{{ item.laboratory?.name || "—" }}</span>
        </template>

        <template #item.group="{ item }">
          <template v-if="editingProductId === item.id">
            <VAutocomplete
              v-model="editingValue"
              :items="props.groups"
              item-title="name"
              item-value="id"
              variant="outlined"
              class="responsive-autocomplete"
              style="max-inline-size: 350px; min-inline-size: 200px;"
              placeholder="Buscar o crear grupo"
              clearable
              @keydown.enter.prevent="
                searchInput && searchInput.trim() && !editingValue
                  ? handleCreateGroupOnEnter()
                  : saveInlineEdit(item)
              "
              autofocus
              :error="props.productWithError === item.id"
              :error-messages="
                props.productWithError === item.id
                  ? 'Error al asignar grupo'
                  : ''
              "
              @update:search="handleGroupSearch"
              :no-data-text="
                searchInput && searchInput.trim()
                  ? 'No se encontró. Presiona Enter para crear uno nuevo.'
                  : 'No hay grupos disponibles.'
              "
            />
          </template>
          <template v-else>
            {{ item.group?.name || "—" }}
          </template>
        </template>

        <template #item.valid_stock="{ item }">
          <span class="font-weight-medium">{{ item.stock_calculado !== null && item.stock_calculado !== undefined ? Math.round(item.stock_calculado) : 0 }}</span>
        </template>

        <template #item.next_expiration="{ item }">
          <span>{{ nextExpirationDate(item) }}</span>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-2">
            <template v-if="editingProductId === item.id">
              <VBtn
                icon="tabler-check"
                size="small"
                @click="saveInlineEdit(item)"
              />
              <VBtn icon="tabler-x" size="small" @click="cancelEdit" />
            </template>
            <template v-else>
              <IconBtn @click="startEdit(item)" color="warning">
                <VIcon icon="tabler-edit" />
              </IconBtn>
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

      <div class="pa-2">
        <VCard
          v-for="item in products"
          :key="item.id"
          class="product-mobile-card border shadow-sm mb-2 overflow-hidden"
        >
          <div class="pa-3">
            <div class="d-flex gap-3 align-start mb-2">
              <VAvatar
                v-if="item.photo_url"
                size="44"
                variant="tonal"
                rounded
                :image="item.photo_url"
                class="flex-shrink-0 mt-1"
              />
              <div class="flex-grow-1 min-width-0">
                <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight">
                  <a
                    :href="'/inventory/traceability?q=' + item.id"
                    target="_blank"
                    class="text-decoration-none text-primary mr-1"
                  >
                    {{ item.id }}
                  </a>
                  <span class="mx-1 text-disabled">|</span>
                  {{ item.name }}
                </h3>
                <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs mt-1">
                  <span v-if="!isRestaurant" class="text-medium-emphasis font-weight-medium">{{ item.active_ingredient }}</span>
                  <span v-if="!isRestaurant" class="text-disabled">|</span>
                  <span v-if="isRestaurant && item.presentation" class="text-medium-emphasis font-weight-medium">
                    {{ item.presentation }} {{ item.unit_of_measure ? `(${item.unit_of_measure})` : '' }}
                  </span>
                  <span v-if="isRestaurant && item.presentation" class="text-disabled">|</span>
                  <span class="text-primary font-weight-bold">{{ item.laboratory?.name || 'S/L' }}</span>
                </div>
              </div>
            </div>

            <VDivider class="my-2 border-opacity-10" />

            <div class="d-flex justify-space-between align-center px-1 mb-2">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold">Stock</span>
                <span class="text-xs font-weight-black" :class="item.stock_calculado > 0 ? 'text-success' : 'text-error'">
                  {{ item.stock_calculado !== null && item.stock_calculado !== undefined ? Math.round(item.stock_calculado) : 0 }} <small>UNDS</small>
                </span>
              </div>
              <div class="d-flex flex-column text-right">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold">Expl.</span>
                <span class="text-xs font-weight-medium">{{ nextExpirationDate(item) }}</span>
              </div>
            </div>

            <!-- Inline Editing Area in Card -->
            <div v-if="editingProductId === item.id" class="mt-2 pa-2 bg-var-theme-background rounded">
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
                @update:search="handleGroupSearch"
                @keydown.enter.prevent="handleCreateGroupOnEnter"
              />
              <div class="d-flex gap-2 justify-center mt-2">
                <VBtn size="small" variant="tonal" color="secondary" class="flex-grow-1" @click="cancelEdit">Cancelar</VBtn>
                <VBtn size="small" color="primary" class="flex-grow-1" @click="saveInlineEdit(item)">Guardar</VBtn>
              </div>
            </div>
            <div v-else class="mt-1">
              <div class="d-flex justify-space-between align-center py-1 px-2 bg-var-theme-background rounded">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold">Grupo:</span>
                <span class="text-xs font-weight-bold text-primary">{{ item.group?.name || '---' }}</span>
              </div>
            </div>
          </div>

          <!-- Acciones Rectangulares Movil -->
          <div v-if="editingProductId !== item.id" class="d-flex border-t border-opacity-10">
            <VBtn
              block
              color="warning"
              variant="text"
              class="rounded-0"
              height="40"
              prepend-icon="tabler-edit"
              @click="startEdit(item)"
            >
              ASIGNAR GRUPO
            </VBtn>
          </div>
        </VCard>

        <!-- Paginación Móvil -->
        <div class="d-flex justify-center mt-4">
           <AppMobilePagination
            :page="props.page"
            :items-per-page="props.itemsPerPage"
            :total-items="props.totalProduct"
            :loading="props.loading"
            @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
          />
        </div>
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.product-mobile-card {
  border-radius: 8px !important;
  background: rgb(var(--v-theme-surface));
}

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
  padding-block: 12px !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.03) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-theme-primary), 0.05);
}
</style>
