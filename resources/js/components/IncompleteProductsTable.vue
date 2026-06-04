<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, computed, watch } from "vue";
import BarcodeScannerDialog from "@/components/dialogs/BarcodeScannerDialog.vue";
import { formatDateSimple } from "@/utils/formatters";
import { useBrandingStore } from "@/stores/useBrandingStore";

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => brandingStore.settings.business_type === 'restaurant');

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  sortBy: { type: String, default: undefined },
  orderBy: { type: String, default: 'asc' },
  productWithError: { type: [Number, null], default: null },
  errorMessage: { type: String, default: "" },
  laboratories: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
});

const sortByModel = computed(() => {
  if (!props.sortBy) return [];
  return [{ key: props.sortBy, order: props.orderBy || 'asc' }];
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
const activeScannerTarget = ref(null); // 'barcode' | 'etc'

const headers = computed(() => {
  const list = [
    { 
      title: "ID", 
      key: "id", 
      sortable: true,
      cellClass: "font-weight-black text-primary d-none d-sm-table-cell",
      headerClass: "d-none d-sm-table-cell"
    },
    { title: "Producto", key: "name", sortable: true, width: "450px" },
    { title: "Barcode", key: "barcode", sortable: true },
    { title: isRestaurant.value ? "Marca" : "Laboratorio", key: "laboratory", sortable: true },
    {
      title: "Stock",
      key: "valid_stock",
      visible: true,
      align: 'center',
      cellClass: "d-none d-md-table-cell",
      headerClass: "d-none d-md-table-cell"
    },
    { 
      title: "Exp.", 
      key: "next_expiration", 
      sortable: true,
      align: 'center',
      cellClass: "d-none d-md-table-cell",
      headerClass: "d-none d-md-table-cell"
    }
  ];
  if (!isRestaurant.value) {
    list.push({ title: "Origen", key: "origin", sortable: true });
  }
  list.push({ title: "Acciones", key: "actions", sortable: false });
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
      const errorMessage =
        err.response.data?.errors?.name?.[0] ||
        err.response.data?.message ||
        "Error al crear el laboratorio";
      toast.error(errorMessage);
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
      const errorMessage =
        err.response.data?.errors?.name?.[0] ||
        err.response.data?.message ||
        "Error al crear el origen";
      toast.error(errorMessage);
    } else {
      toast.error("Error al crear el origen");
    }
    throw err;
  }
};

const saveInlineEdit = async (product) => {
  const payload = { id: product.id };
  if (editingBarcode.value) payload.barcode = editingBarcode.value;
  if (editingLaboratoryId.value)
    payload.laboratory_id = editingLaboratoryId.value;
  if (editingOriginId.value) payload.origin_id = editingOriginId.value;
  emit("update-product", payload);
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
};

const openScanner = (product) => {
  currentEditingProduct.value = product;
  isScannerVisible.value = true;
};

const handleScan = (code) => {
  editingBarcode.value = code;
  isScannerVisible.value = false;
  
  // Si estamos en modo edición, podemos intentar guardar automáticamente o dejar que el usuario revise
  if (editingProductId.value === currentEditingProduct.value?.id) {
    saveInlineEdit(currentEditingProduct.value);
  }
};

const handleLaboratorySearch = (search) => {
  searchInput.value = search;
};

const handleOriginSearch = (search) => {
  searchInput.value = search;
};

const handleCreateLaboratoryOnEnter = async (event) => {
  if (!searchInput.value || !searchInput.value.trim()) return;

  const labName = searchInput.value.trim();

  const exists = props.laboratories.some(
    (lab) => lab.name.toLowerCase() === labName.toLowerCase(),
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

const handleCreateOriginOnEnter = async (event) => {
  if (!searchInput.value || !searchInput.value.trim()) return;

  const originName = searchInput.value.trim();

  const exists = props.origins.some(
    (origin) => origin.name.toLowerCase() === originName.toLowerCase(),
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
    (a, b) => new Date(a.expiration_date) - new Date(b.expiration_date),
  );
  const closestDate = new Date(validLots[0].expiration_date);
  return formatDateSimple(closestDate);
};
</script>

<template>
  <VCard>
    <!-- Desktop Table -->
    <div class="d-none d-md-block">
      <VDataTableServer
        :headers="headers"
        :items="products"
        :items-length="totalProduct"
        :items-per-page="itemsPerPage"
        :page="page"
        :loading="loading"
        :sort-by="sortByModel"
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
          <div class="d-flex align-center gap-x-4 py-2">
            <VAvatar
              v-if="item.photo_url"
              size="38"
              variant="tonal"
              rounded
              :image="item.photo_url"
              class="border flex-shrink-0"
            />
            <div class="d-flex flex-column truncate" style="max-inline-size: 400px;">
              <div class="d-flex align-center gap-x-1 truncate">
                <span
                  class="text-sm font-weight-black text-high-emphasis leading-tight text-uppercase truncate"
                  :class="{
                    'text-warning':
                      item.psychotropic == 1 || item.psychotropic === true,
                  }"
                >
                  {{ item.name || 'N/A' }}
                  <span v-if="item.iva == 1 || item.iva === true"> (G)</span>
                  <span v-if="item.is_colombian_origin == 1 || item.is_colombian_origin === true"> (COL)</span>
                </span>
                <div class="d-flex gap-1 flex-shrink-0">
                  <VChip v-if="isMissing(item, 'laboratory')" size="x-small" color="error" variant="flat" class="font-weight-black text-uppercase" style="font-size: 10px !important;">{{ isRestaurant ? 'Falta Marca' : 'Falta Lab' }}</VChip>
                  <VChip v-if="isMissing(item, 'barcode')" size="x-small" color="error" variant="flat" class="font-weight-black text-uppercase" style="font-size: 10px !important;">Falta Barcode</VChip>
                </div>
              </div>
              <div class="d-flex align-center gap-x-1 text-super-xs mt-1">
                <span v-if="!isRestaurant" class="text-disabled truncate" style="max-inline-size: 150px;">{{ item.active_ingredient || "" }}</span>
                <span v-if="!isRestaurant" class="text-disabled">|</span>
                <span v-if="isRestaurant && item.presentation" class="text-disabled truncate" style="max-inline-size: 150px;">
                  {{ item.presentation }} {{ item.unit_of_measure ? `(${item.unit_of_measure})` : '' }}
                </span>
                <span v-if="isRestaurant && item.presentation" class="text-disabled">|</span>
                <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 100px;">
                  {{ item.laboratory?.name || 'S/L' }}
                </span>
              </div>
            </div>
          </div>
        </template>

        <template #item.laboratory="{ item }">
          <template
            v-if="editingProductId === item.id && isMissing(item, 'laboratory')"
          >
            <VAutocomplete
              v-model="editingLaboratoryId"
              :items="props.laboratories"
              item-title="name"
              item-value="id"
              density="compact"
              variant="outlined"
              class="responsive-autocomplete"
              style=" flex-grow: 1;min-inline-size: 150px;"
              :placeholder="isRestaurant ? 'Buscar o crear marca' : 'Buscar o crear laboratorio'"
              clearable
              @keydown.enter.prevent="
                searchInput && searchInput.trim() && !editingLaboratoryId
                  ? handleCreateLaboratoryOnEnter()
                  : saveInlineEdit(item)
              "
              autofocus
              :error="props.productWithError === item.id"
              :error-messages="
                props.productWithError === item.id
                  ? 'Error al asignar laboratorio'
                  : ''
              "
              @update:search="handleLaboratorySearch"
              :no-data-text="
                searchInput && searchInput.trim()
                  ? 'No se encontró. Presiona Enter para crear uno nuevo.'
                  : (isRestaurant ? 'No hay marcas disponibles.' : 'No hay laboratorios disponibles.')
              "
            />
          </template>
          <template v-else>
            <div class="d-flex align-center gap-2">
              <span :class="{ 'text-error font-weight-black': !item.laboratory }">{{ item.laboratory?.name || "FALTA" }}</span>
            </div>
          </template>
        </template>

        <template #item.barcode="{ item }">
          <template
            v-if="editingProductId === item.id && isMissing(item, 'barcode')"
          >
            <VTextField
              v-model="editingBarcode"
              density="compact"
              variant="outlined"
              class="responsive-input"
              style=" flex-grow: 1;min-inline-size: 120px;"
              @keyup.enter="saveInlineEdit(item)"
              autofocus
              placeholder="Escribir barcode"
              append-inner-icon="tabler-camera"
              @click:append-inner="openScanner(item)"
              :error="props.productWithError === item.id"
              :error-messages="
                props.productWithError === item.id
                  ? props.errorMessage || 'Ya se encuentra registrado'
                  : ''
              "
            />
          </template>
          <template v-else>
            <div class="d-flex align-center gap-2">
              <span>{{ item.barcode || "—" }}</span>
            </div>
          </template>
        </template>

        <template #item.valid_stock="{ item }">
          <VChip
            :color="(item.stock_calculado || 0) > 0 ? 'success' : 'error'"
            size="x-small"
            label
            variant="flat"
            class="font-weight-black"
          >
            {{ item.stock_calculado || 0 }} UNDS
          </VChip>
        </template>

        <template #item.next_expiration="{ item }">
          <span class="text-caption font-weight-black text-high-emphasis">
            <VIcon icon="tabler-calendar" size="14" class="me-1 text-warning" />
            {{ nextExpirationDate(item) }}
          </span>
        </template>

        <template #item.origin="{ item }">
          <template
            v-if="editingProductId === item.id && isMissing(item, 'origin')"
          >
            <VAutocomplete
              v-model="editingOriginId"
              :items="props.origins"
              item-title="name"
              item-value="id"
              density="compact"
              variant="outlined"
              class="responsive-autocomplete"
              style=" flex-grow: 1;min-inline-size: 150px;"
              placeholder="Buscar o crear origen"
              clearable
              @keydown.enter.prevent="
                searchInput && searchInput.trim() && !editingOriginId
                  ? handleCreateOriginOnEnter()
                  : saveInlineEdit(item)
              "
              autofocus
              :error="props.productWithError === item.id"
              :error-messages="
                props.productWithError === item.id
                  ? 'Error al asignar origen'
                  : ''
              "
              @update:search="handleOriginSearch"
              :no-data-text="
                searchInput && searchInput.trim()
                  ? 'No se encontró. Presiona Enter para crear uno nuevo.'
                  : 'No hay orígenes disponibles.'
              "
            />
          </template>
          <template v-else>
            <div class="d-flex align-center gap-2">
              <span>{{ item.origin?.name || "—" }}</span>
            </div>
          </template>
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
          variant="flat"
          class="product-mobile-card border mb-2 overflow-hidden"
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
                <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight truncate">
                  <a
                    :href="'/inventory/traceability?q=' + item.id"
                    target="_blank"
                    class="text-decoration-none text-primary mr-1"
                  >
                    #{{ item.id }}
                  </a>
                  <span class="mx-1 text-disabled">|</span>
                  {{ item.name }}
                </h3>
                <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs mt-1">
                  <span class="text-medium-emphasis font-weight-medium truncate" style="max-inline-size: 150px;">{{ item.active_ingredient }}</span>
                  <span class="text-disabled">|</span>
                  <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 100px;">
                    {{ item.laboratory?.name || 'S/L' }}
                  </span>
                </div>
              </div>
            </div>

            <VDivider class="my-2 border-opacity-10" />

            <div class="bg-var-theme-background-light px-3 py-2 rounded mb-3 border-dashed-thin d-flex justify-space-between align-center">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Stock</span>
                <VChip
                  :color="(item.stock_calculado || 0) > 0 ? 'success' : 'error'"
                  size="x-small"
                  label
                  variant="flat"
                  class="font-weight-black mt-1"
                >
                  {{ item.stock_calculado || 0 }} UNDS
                </VChip>
              </div>
              <div class="d-flex flex-column text-right">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Próx. Exp.</span>
                <span class="text-xs font-weight-black text-high-emphasis mt-1">
                  <VIcon icon="tabler-calendar" size="12" class="me-1 text-warning" />
                  {{ nextExpirationDate(item) }}
                </span>
              </div>
            </div>

            <div class="bg-var-theme-background pa-2 rounded mt-2">
              <!-- Edit Mode inside Card -->
              <div v-if="editingProductId === item.id">
                <VRow dense>
                  <VCol v-if="isMissing(item, 'barcode')" cols="12">
                    <VTextField
                      v-model="editingBarcode"
                      label="Código de Barras"
                      density="compact"
                      variant="outlined"
                      class="mb-2"
                      hide-details="auto"
                      append-inner-icon="tabler-camera"
                      @click:append-inner="openScanner(item)"
                      :error="props.productWithError === item.id"
                      :error-messages="props.productWithError === item.id ? props.errorMessage : ''"
                    />
                  </VCol>
                  <VCol v-if="isMissing(item, 'laboratory')" cols="12">
                    <VAutocomplete
                      v-model="editingLaboratoryId"
                      :items="props.laboratories"
                      item-title="name"
                      item-value="id"
                      :label="isRestaurant ? 'Marca' : 'Laboratorio'"
                      density="compact"
                      variant="outlined"
                      class="mb-2"
                      hide-details="auto"
                      placeholder="Buscar o crear..."
                      @update:search="handleLaboratorySearch"
                      @keydown.enter.prevent="handleCreateLaboratoryOnEnter"
                    />
                  </VCol>
                  <VCol v-if="!isRestaurant && isMissing(item, 'origin')" cols="12">
                    <VAutocomplete
                      v-model="editingOriginId"
                      :items="props.origins"
                      item-title="name"
                      item-value="id"
                      label="Origen"
                      density="compact"
                      variant="outlined"
                      class="mb-2"
                      hide-details="auto"
                      placeholder="Buscar o crear..."
                      @update:search="handleOriginSearch"
                      @keydown.enter.prevent="handleCreateOriginOnEnter"
                    />
                  </VCol>
                </VRow>
                <div class="d-flex gap-2 justify-center mt-2">
                  <VBtn variant="tonal" color="secondary" size="small" class="flex-grow-1" @click="cancelEdit">Cancelar</VBtn>
                  <VBtn color="primary" size="small" class="flex-grow-1" @click="saveInlineEdit(item)">Guardar</VBtn>
                </div>
              </div>

              <!-- Display Mode -->
              <div v-else class="d-flex flex-column gap-y-1">
                <div class="d-flex justify-space-between text-super-xs">
                  <span class="text-disabled font-weight-bold">BARCODE:</span>
                  <span :class="item.barcode ? 'text-high-emphasis' : 'text-error font-weight-black'">
                    {{ item.barcode || 'FALTA' }}
                  </span>
                </div>
                <div v-if="!isRestaurant" class="d-flex justify-space-between text-super-xs">
                  <span class="text-disabled font-weight-bold">ORIGEN:</span>
                  <span :class="item.origin ? 'text-high-emphasis' : 'text-error font-weight-black'">
                    {{ item.origin?.name || 'FALTA' }}
                  </span>
                </div>
                <div v-if="!item.laboratory_id" class="d-flex justify-space-between text-super-xs">
                  <span class="text-disabled font-weight-bold">{{ isRestaurant ? 'MARCA:' : 'LABORATORIO:' }}</span>
                  <span class="text-error font-weight-black">FALTA</span>
                </div>
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
              COMPLETAR DATOS
            </VBtn>
          </div>
        </VCard>

        <!-- Paginación Móvil -->
        <div class="d-flex justify-center mt-4 pb-2">
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
    </div>

    <BarcodeScannerDialog
      v-model="isScannerVisible"
      @scan="handleScan"
    />
  </VCard>
</template>

<style scoped>
.product-mobile-card {
  border-radius: 8px !important;
  background: rgb(var(--v-theme-surface));
}

.text-super-xs {
  font-size: 0.65rem !important;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-theme-primary), 0.05);
}

.bg-var-theme-background-light {
  background-color: rgba(var(--v-border-color), 0.05);
}

.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}
</style>
