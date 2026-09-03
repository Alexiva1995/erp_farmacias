<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import AppEmptyState from "@/components/AppEmptyState.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, computed } from "vue";
import BarcodeScannerDialog from "@/components/dialogs/BarcodeScannerDialog.vue";
import { formatDateSimple } from "@/utils/formatters";
import { useBrandingStore } from "@/stores/useBrandingStore";

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => false);

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  sortBy: { type: String, default: undefined },
  orderBy: { type: String, default: "asc" },
  productWithError: { type: [Number, null], default: null },
  errorMessage: { type: String, default: "" },
  laboratories: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
});

const sortByModel = computed(() => {
  if (!props.sortBy) return [];
  return [{ key: props.sortBy, order: props.orderBy || "asc" }];
});

const emit = defineEmits([
  "update:options",
  "update-product",
  "laboratory-created",
  "origin-created",
]);

const editingProductId = ref(null);
const editingBarcode = ref("");
const editingLaboratoryId = ref(null);
const editingOriginId = ref(null);
const searchInput = ref("");
const currentEditingProduct = ref(null);
const isScannerVisible = ref(false);
const isSaving = ref(false);

const headers = computed(() => {
  const list = [
    { 
      title: "ID", 
      key: "id", 
      sortable: true,
      cellClass: "font-weight-black text-primary",
    },
    { title: "PRODUCTO", key: "name", sortable: true, width: "40%" },
    { title: "BARCODE", key: "barcode", sortable: true },
    { title: isRestaurant.value ? "MARCA" : "LABORATORIO", key: "laboratory", sortable: true },
  ];
  if (!isRestaurant.value) {
    list.push({ title: "ORIGEN", key: "origin", sortable: true });
  }
  list.push(
    { title: "Accion", key: "actions", sortable: false, align: "center" }
  );
  return list;
});

const isMissing = (product, field) => {
  if (field === "barcode") return !product.barcode;
  if (field === "laboratory") return !product.laboratory_id;
  if (field === "origin") return !isRestaurant.value && !product.origin_id;
  return false;
};

const createLaboratory = async (name) => {
  try {
    const response = await axios.post("/laboratories", { name });
    toast.success("Laboratorio creado con éxito");
    emit("laboratory-created", response.data.laboratory);
    return response.data.laboratory;
  } catch (err) {
    if (err.response?.status === 422) {
      const errorMsg =
        err.response.data?.errors?.name?.[0] ||
        err.response.data?.message ||
        "Error al crear el laboratorio";
      toast.error(errorMsg);
    } else {
      toast.error("Error al crear el laboratorio");
    }
    throw err;
  }
};

const createOrigin = async (name) => {
  try {
    const response = await axios.post("/origins", { name });
    toast.success("Origen creado con éxito");
    emit("origin-created", response.data.origin);
    return response.data.origin;
  } catch (err) {
    if (err.response?.status === 422) {
      const errorMsg =
        err.response.data?.errors?.name?.[0] ||
        err.response.data?.message ||
        "Error al crear el origen";
      toast.error(errorMsg);
    } else {
      toast.error("Error al crear el origen");
    }
    throw err;
  }
};

const saveInlineEdit = async (product) => {
  const payload = {
    id: product.id,
  };

  if (isMissing(product, "barcode")) {
    payload.barcode = editingBarcode.value;
  }
  if (isMissing(product, "laboratory")) {
    payload.laboratory_id = editingLaboratoryId.value;
  }
  if (isMissing(product, "origin")) {
    payload.origin_id = editingOriginId.value;
  }

  isSaving.value = true;
  try {
    await emit("update-product", payload);
    cancelEdit();
  } catch (error) {
    console.error(error);
  } finally {
    isSaving.value = false;
  }
};

const startEdit = (product) => {
  editingProductId.value = product.id;
  editingBarcode.value = product.barcode || "";
  editingLaboratoryId.value = product.laboratory_id || null;
  editingOriginId.value = product.origin_id || null;
  currentEditingProduct.value = product;
  searchInput.value = "";
};

const cancelEdit = () => {
  editingProductId.value = null;
  editingBarcode.value = "";
  editingLaboratoryId.value = null;
  editingOriginId.value = null;
  currentEditingProduct.value = null;
  searchInput.value = "";
  isSaving.value = false;
};

const handleLaboratorySearch = (search) => {
  searchInput.value = search;
};

const handleOriginSearch = (search) => {
  searchInput.value = search;
};

const handleCreateLaboratoryOnEnter = async () => {
  if (!searchInput.value || !searchInput.value.trim()) return;

  const labName = searchInput.value.trim();
  const exists = props.laboratories.some(
    (lab) => lab.name.toLowerCase() === labName.toLowerCase()
  );

  if (exists) {
    toast.error("Ya existe un laboratorio con ese nombre");
    return;
  }

  try {
    const newLab = await createLaboratory(labName);
    editingLaboratoryId.value = newLab.id;
    if (currentEditingProduct.value) {
      await saveInlineEdit(currentEditingProduct.value);
    }
  } catch (err) {
    console.error(err);
  }
};

const handleCreateOriginOnEnter = async () => {
  if (!searchInput.value || !searchInput.value.trim()) return;

  const originName = searchInput.value.trim();
  const exists = props.origins.some(
    (origin) => origin.name.toLowerCase() === originName.toLowerCase()
  );

  if (exists) {
    toast.error("Ya existe un origen con ese nombre");
    return;
  }

  try {
    const newOrigin = await createOrigin(originName);
    editingOriginId.value = newOrigin.id;
    if (currentEditingProduct.value) {
      await saveInlineEdit(currentEditingProduct.value);
    }
  } catch (err) {
    console.error(err);
  }
};

const openScanner = (product) => {
  currentEditingProduct.value = product;
  isScannerVisible.value = true;
};

const handleScan = (code) => {
  editingBarcode.value = code;
  isScannerVisible.value = false;
  if (currentEditingProduct.value) {
    saveInlineEdit(currentEditingProduct.value);
  }
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
        :sort-by="sortByModel"
        class="text-no-wrap"
        density="compact"
        item-value="id"
        @update:options="(opts) => emit('update:options', opts)"
      >
        <template #no-data>
          <AppEmptyState
            title="¡Todo Completo!"
            message="No se encontraron productos con datos incompletos."
            icon="tabler-circle-check"
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

        <!-- PRODUCTO con ingrediente y laboratorio subtitulado -->
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
                <span class="text-disabled truncate" style="max-inline-size: 180px;">
                  {{ item.active_ingredient || item.presentation || 'Sin Especificación' }}
                </span>
                <span class="text-disabled mx-1">|</span>
                <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 140px;">
                  {{ item.laboratory?.name || 'S/L' }}
                </span>
              </div>
            </div>
          </div>
        </template>

        <!-- BARCODE -->
        <template #item.barcode="{ item }">
          <template v-if="editingProductId === item.id && isMissing(item, 'barcode')">
            <div class="d-flex align-center gap-1">
              <VTextField
                v-model="editingBarcode"
                density="compact"
                variant="outlined"
                style="max-inline-size: 180px; min-inline-size: 130px;"
                @keyup.enter="saveInlineEdit(item)"
                autofocus
                placeholder="Barcode..."
                append-inner-icon="tabler-camera"
                hide-details
                @click:append-inner="openScanner(item)"
                :error="props.productWithError === item.id"
              />
            </div>
          </template>
          <template v-else>
            <span v-if="item.barcode" class="text-xs font-weight-medium">{{ item.barcode }}</span>
            <span v-else class="text-xs text-disabled font-weight-medium cursor-pointer" @click="startEdit(item)">—</span>
          </template>
        </template>

        <!-- LABORATORIO -->
        <template #item.laboratory="{ item }">
          <template v-if="editingProductId === item.id && isMissing(item, 'laboratory')">
            <VAutocomplete
              v-model="editingLaboratoryId"
              :items="props.laboratories"
              item-title="name"
              item-value="id"
              density="compact"
              variant="outlined"
              style="max-inline-size: 220px; min-inline-size: 150px;"
              :placeholder="isRestaurant ? 'Buscar marca...' : 'Buscar lab...'"
              clearable
              hide-details
              autofocus
              @keydown.enter.prevent="
                searchInput && searchInput.trim() && !editingLaboratoryId
                  ? handleCreateLaboratoryOnEnter()
                  : saveInlineEdit(item)
              "
              @update:search="handleLaboratorySearch"
              :no-data-text="
                searchInput && searchInput.trim()
                  ? 'Presiona Enter para crear ' + searchInput
                  : 'No hay opciones disponibles.'
              "
            />
          </template>
          <template v-else>
            <span v-if="item.laboratory?.name" class="text-xs font-weight-medium">{{ item.laboratory.name }}</span>
            <span v-else class="text-xs text-disabled font-weight-medium cursor-pointer" @click="startEdit(item)">—</span>
          </template>
        </template>

        <!-- ORIGEN -->
        <template v-if="!isRestaurant" #item.origin="{ item }">
          <template v-if="editingProductId === item.id && isMissing(item, 'origin')">
            <VAutocomplete
              v-model="editingOriginId"
              :items="props.origins"
              item-title="name"
              item-value="id"
              density="compact"
              variant="outlined"
              style="max-inline-size: 200px; min-inline-size: 140px;"
              placeholder="Buscar origen..."
              clearable
              hide-details
              autofocus
              @keydown.enter.prevent="
                searchInput && searchInput.trim() && !editingOriginId
                  ? handleCreateOriginOnEnter()
                  : saveInlineEdit(item)
              "
              @update:search="handleOriginSearch"
              :no-data-text="
                searchInput && searchInput.trim()
                  ? 'Presiona Enter para crear ' + searchInput
                  : 'No hay opciones disponibles.'
              "
            />
          </template>
          <template v-else>
            <span v-if="item.origin?.name" class="text-xs font-weight-medium">{{ item.origin.name }}</span>
            <span v-else class="text-xs text-disabled font-weight-medium cursor-pointer" @click="startEdit(item)">—</span>
          </template>
        </template>

        <!-- Columna Accion con IconBtn limpio -->
        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <template v-if="editingProductId === item.id">
              <IconBtn
                color="success"
                size="small"
                :loading="isSaving"
                @click="saveInlineEdit(item)"
              >
                <VIcon icon="tabler-check" size="18" />
                <VTooltip activator="parent">Guardar</VTooltip>
              </IconBtn>
              <IconBtn
                color="secondary"
                size="small"
                :disabled="isSaving"
                @click="cancelEdit"
              >
                <VIcon icon="tabler-x" size="18" />
                <VTooltip activator="parent">Cancelar</VTooltip>
              </IconBtn>
            </template>
            <template v-else>
              <IconBtn
                color="primary"
                size="small"
                @click="startEdit(item)"
              >
                <VIcon icon="tabler-edit" size="18" />
                <VTooltip activator="parent" location="top">Completar Datos</VTooltip>
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
          title="¡Todo Completo!"
          message="No se encontraron productos con datos incompletos."
          icon="tabler-circle-check"
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
              <span class="text-xs font-weight-black text-primary uppercase truncate" style="max-inline-size: 200px;">
                {{ item.laboratory?.name || 'S/L' }}
              </span>
            </div>
          </div>

          <h4 class="text-xs font-weight-black text-high-emphasis uppercase leading-tight mb-1 text-truncate">
            {{ item.name }}
          </h4>

          <!-- Edición Móvil In-line -->
          <div v-if="editingProductId === item.id" class="mt-3 pt-2 border-t d-flex flex-column gap-2">
            <VTextField
              v-if="isMissing(item, 'barcode')"
              v-model="editingBarcode"
              density="compact"
              variant="outlined"
              label="Barcode"
              placeholder="Escribir barcode..."
              hide-details
              append-inner-icon="tabler-camera"
              @click:append-inner="openScanner(item)"
            />
            <VAutocomplete
              v-if="isMissing(item, 'laboratory')"
              v-model="editingLaboratoryId"
              :items="props.laboratories"
              item-title="name"
              item-value="id"
              label="Laboratorio / Marca"
              density="compact"
              variant="outlined"
              hide-details
              placeholder="Buscar o crear..."
              @update:search="handleLaboratorySearch"
              @keydown.enter.prevent="handleCreateLaboratoryOnEnter"
            />
            <VAutocomplete
              v-if="isMissing(item, 'origin') && !isRestaurant"
              v-model="editingOriginId"
              :items="props.origins"
              item-title="name"
              item-value="id"
              label="Origen"
              density="compact"
              variant="outlined"
              hide-details
              placeholder="Buscar o crear..."
              @update:search="handleOriginSearch"
              @keydown.enter.prevent="handleCreateOriginOnEnter"
            />
            <div class="d-flex gap-2 justify-center mt-1">
              <VBtn size="small" variant="tonal" color="secondary" class="flex-grow-1 font-weight-bold" :disabled="isSaving" @click="cancelEdit">Cancelar</VBtn>
              <VBtn size="small" color="primary" class="flex-grow-1 font-weight-bold" :loading="isSaving" @click="saveInlineEdit(item)">Guardar</VBtn>
            </div>
          </div>

          <div v-else class="d-flex align-center justify-end text-super-xs text-medium-emphasis mt-2 pt-2 border-t">
            <IconBtn
              color="primary"
              size="small"
              @click="startEdit(item)"
            >
              <VIcon icon="tabler-edit" size="18" />
              <VTooltip activator="parent">Completar Datos</VTooltip>
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
          :sort-by="typeof props.sortBy === 'string' ? props.sortBy : (props.sortBy?.[0]?.key || undefined)"
          :order-by="props.orderBy"
          @change="(options) => emit('update:options', options)"
        />
      </div>
    </div>

    <BarcodeScannerDialog
      v-model="isScannerVisible"
      @scan="handleScan"
    />
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
