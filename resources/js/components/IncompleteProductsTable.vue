<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref } from "vue";
import { formatDate } from "@/utils/formatters";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  productWithError: { type: [Number, null], default: null },
  errorMessage: { type: String, default: "" },
  laboratories: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
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

const headers = [
  { 
    title: "ID", 
    key: "id", 
    sortable: true,
    cellClass: "d-none d-sm-table-cell",
    headerClass: "d-none d-sm-table-cell"
  },
  { title: "Producto", key: "name", sortable: true },
  { title: "Laboratorio", key: "laboratory", sortable: true },
  { title: "Barcode", key: "barcode", sortable: true },
  {
    title: "Stock",
    key: "valid_stock",
    visible: true,
    sortable: true,
    value: (item) => {
      return item.stock_calculado;
    },
    cellClass: "d-none d-md-table-cell",
    headerClass: "d-none d-md-table-cell"
  },
  { 
    title: "Exp.", 
    key: "next_expiration", 
    sortable: true,
    cellClass: "d-none d-md-table-cell",
    headerClass: "d-none d-md-table-cell"
  },
  { title: "Origen", key: "origin", sortable: true },
  { title: "Acciones", key: "actions", sortable: false },
];

const isMissing = (product, field) => {
  if (field === "barcode") return !product.barcode;
  if (field === "laboratory") return !product.laboratory_id;
  if (field === "origin") return !product.origin_id;
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
  return formatDate(closestDate);
};
</script>

<template>
  <VCard>
    <VDataTableServer
      :headers="headers"
      :items="products"
      :items-length="totalProduct"
      :items-per-page="itemsPerPage"
      :page="page"
      :loading="loading"
      @update:options="(opts) => emit('update:options', opts)"
    >
      <template #item.id="{ item }">
        {{ item.id }}
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
                'text-warning font-weight-bold':
                  item.psychotropic == 1 || item.psychotropic === true,
              }"
            >
              <!-- En móvil añadimos ID y Laboratorio al nombre si el lab existe -->
              <span class="d-inline d-sm-none text-primary font-weight-bold">[{{ item.id }}] </span>
              {{ item.name.toUpperCase() }}
              <span v-if="item.iva == 1 || item.iva === true"> (G)</span>
              <span v-if="item.is_colombian_origin == 1 || item.is_colombian_origin === true"> (COL)</span>
              <div v-if="item.laboratory" class="d-block d-md-none text-xs text-secondary italic">
                {{ item.laboratory.name }}
              </div>
            </span>
            <span class="text-sm text-disabled">
              {{ item.active_ingredient }}
              <VChip v-if="isMissing(item, 'laboratory')" size="x-small" color="error" class="ms-1">Falta Lab</VChip>
              <VChip v-if="isMissing(item, 'barcode')" size="x-small" color="error" class="ms-1">Falta Barcode</VChip>
            </span>
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
            placeholder="Buscar o crear laboratorio"
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
                : 'No hay laboratorios disponibles.'
            "
          />
        </template>
        <template v-else>
          <div class="d-flex align-center gap-2">
            <span>{{ item.laboratory?.name || "—" }}</span>
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
        <span class="font-weight-medium">{{ item.stock_calculado || 0 }}</span>
      </template>

      <template #item.next_expiration="{ item }">
        <span>{{ nextExpirationDate(item) }}</span>
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
  </VCard>
</template>
