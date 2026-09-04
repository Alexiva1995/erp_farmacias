<script setup>
import AppEmptyState from "@/components/AppEmptyState.vue";
import IncompleteProductsMobileCards from "@/components/IncompleteProductsMobileCards.vue";
import BarcodeScannerDialog from "@/components/dialogs/BarcodeScannerDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { computed, ref } from "vue";

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => brandingStore.settings.business_type === 'restaurant');

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
    { title: "ACCIONES", key: "actions", sortable: false, align: "center" }
  );
  return list;
});

const isMissing = (product, field) => {
  if (!product) return false;
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
    const errorMsg =
      err.response?.data?.errors?.name?.[0] ||
      err.response?.data?.message ||
      "Error al crear el laboratorio";
    toast.error(errorMsg);
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
    const errorMsg =
      err.response?.data?.errors?.name?.[0] ||
      err.response?.data?.message ||
      "Error al crear el origen";
    toast.error(errorMsg);
    throw err;
  }
};

const saveInlineEdit = async (product) => {
  if (!product) return;

  const payload = {
    id: product.id,
  };

  if (editingBarcode.value !== undefined && editingBarcode.value !== "") {
    payload.barcode = editingBarcode.value;
  }
  if (editingLaboratoryId.value !== undefined && editingLaboratoryId.value !== null) {
    payload.laboratory_id = editingLaboratoryId.value;
  }
  if (editingOriginId.value !== undefined && editingOriginId.value !== null) {
    payload.origin_id = editingOriginId.value;
  }

  isSaving.value = true;
  try {
    await emit("update-product", payload);
    cancelEdit();
  } catch (error) {
    console.error("Error saving product:", error);
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
  if (!searchInput.value?.trim()) return;
  const labName = searchInput.value.trim();
  if (props.laboratories.some((lab) => lab.name.toLowerCase() === labName.toLowerCase())) {
    toast.error("Ya existe un laboratorio con ese nombre");
    return;
  }
  try {
    const newLab = await createLaboratory(labName);
    editingLaboratoryId.value = newLab.id;
    if (currentEditingProduct.value) await saveInlineEdit(currentEditingProduct.value);
  } catch (err) {
    console.error(err);
  }
};

const handleCreateOriginOnEnter = async () => {
  if (!searchInput.value?.trim()) return;
  const originName = searchInput.value.trim();
  if (props.origins.some((origin) => origin.name.toLowerCase() === originName.toLowerCase())) {
    toast.error("Ya existe un origen con ese nombre");
    return;
  }
  try {
    const newOrigin = await createOrigin(originName);
    editingOriginId.value = newOrigin.id;
    if (currentEditingProduct.value) await saveInlineEdit(currentEditingProduct.value);
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
</script>

<template>
  <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
    <!-- Vista de Tabla para Escritorio -->
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

        <!-- ID con enlace directo a trazabilidad -->
        <template #item.id="{ item }">
          <a
            :href="'/inventory/traceability?q=' + item.id"
            target="_blank"
            class="text-decoration-none font-weight-black text-primary"
          >
            {{ item.id }}
          </a>
        </template>

        <!-- PRODUCTO: Nombre, principio activo y laboratorio -->
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-x-3 py-2">
            <div class="d-flex flex-column min-width-0">
              <span
                class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate product-title-max"
                :class="{ 
                  'text-warning': item.psychotropic == 1 || item.psychotropic === true
                }"
                :title="item.name"
              >
                {{ item.name?.toUpperCase() || "—" }}
                <span v-if="item.iva == 1 || item.iva === true" class="text-xs text-disabled"> (G)</span>
                <span v-if="item.is_colombian_origin == 1 || item.is_colombian_origin === true" class="text-xs text-disabled"> (COL)</span>
              </span>
              <div class="d-flex align-center gap-1 text-super-xs">
                <span class="text-disabled truncate active-ingredient-max">
                  {{ item.active_ingredient || item.presentation || 'Sin Especificación' }}
                </span>
                <span class="text-disabled mx-1">|</span>
                <span class="text-primary font-weight-black text-uppercase truncate lab-name-max">
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
                class="field-barcode-input"
                autofocus
                placeholder="Barcode..."
                append-inner-icon="tabler-camera"
                hide-details
                :error="props.productWithError === item.id"
                @keyup.enter="saveInlineEdit(item)"
                @click:append-inner="openScanner(item)"
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
              class="field-autocomplete-input"
              :placeholder="isRestaurant ? 'Buscar marca...' : 'Buscar lab...'"
              clearable
              hide-details
              autofocus
              :no-data-text="
                searchInput && searchInput.trim()
                  ? 'Presiona Enter para crear ' + searchInput
                  : 'No hay opciones disponibles.'
              "
              @keydown.enter.prevent="
                searchInput && searchInput.trim() && !editingLaboratoryId
                  ? handleCreateLaboratoryOnEnter()
                  : saveInlineEdit(item)
              "
              @update:search="handleLaboratorySearch"
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
              class="field-autocomplete-input"
              placeholder="Buscar origen..."
              clearable
              hide-details
              autofocus
              :no-data-text="
                searchInput && searchInput.trim()
                  ? 'Presiona Enter para crear ' + searchInput
                  : 'No hay opciones disponibles.'
              "
              @keydown.enter.prevent="
                searchInput && searchInput.trim() && !editingOriginId
                  ? handleCreateOriginOnEnter()
                  : saveInlineEdit(item)
              "
              @update:search="handleOriginSearch"
            />
          </template>
          <template v-else>
            <span v-if="item.origin?.name" class="text-xs font-weight-medium">{{ item.origin.name }}</span>
            <span v-else class="text-xs text-disabled font-weight-medium cursor-pointer" @click="startEdit(item)">—</span>
          </template>
        </template>

        <!-- ACCIONES -->
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

    <!-- Vista de Tarjetas para Móviles (Componente Desacoplado) -->
    <IncompleteProductsMobileCards
      :products="props.products"
      :loading="props.loading"
      :total-product="props.totalProduct"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :sort-by="props.sortBy"
      :order-by="props.orderBy"
      :product-with-error="props.productWithError"
      :laboratories="props.laboratories"
      :origins="props.origins"
      :is-restaurant="isRestaurant"
      :editing-product-id="editingProductId"
      v-model:editing-barcode="editingBarcode"
      v-model:editing-laboratory-id="editingLaboratoryId"
      v-model:editing-origin-id="editingOriginId"
      :is-saving="isSaving"
      :search-input="searchInput"
      @update:options="(opts) => emit('update:options', opts)"
      @start-edit="startEdit"
      @cancel-edit="cancelEdit"
      @save-inline-edit="saveInlineEdit"
      @open-scanner="openScanner"
      @search-laboratory="handleLaboratorySearch"
      @search-origin="handleOriginSearch"
      @create-laboratory="handleCreateLaboratoryOnEnter"
      @create-origin="handleCreateOriginOnEnter"
    />

    <!-- Diálogo Escáner de Código de Barras -->
    <BarcodeScannerDialog
      v-model="isScannerVisible"
      @scan="handleScan"
    />
  </VCard>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.text-xs {
  font-size: 0.75rem !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }

.product-title-max {
  max-inline-size: 320px;
}

.active-ingredient-max {
  max-inline-size: 180px;
}

.lab-name-max {
  max-inline-size: 140px;
}

.field-barcode-input {
  max-inline-size: 180px;
  min-inline-size: 130px;
}

.field-autocomplete-input {
  max-inline-size: 220px;
  min-inline-size: 140px;
}

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
