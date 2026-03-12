<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, onMounted } from "vue";
import { formatDate } from "@/utils/formatters";

const props = defineProps({
  lots: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalLots: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  lotWithError: { type: [Number, null], default: null },
  errorMessage: { type: String, default: "" },
});

const emit = defineEmits(["update:options", "update-lot"]);

const editingLotId = ref(null);
const editingLocation = ref("");
const searchInput = ref("");
const currentEditingLot = ref(null);

const locationsList = ref([]);
const loadingLocations = ref(false);

const fetchLocations = async () => {
  loadingLocations.ref = true;
  try {
    const response = await axios.get("/locations");
    locationsList.value = response.data;
  } catch (error) {
    console.error("Error al cargar ubicaciones:", error);
    toast.error("No se pudieron cargar las ubicaciones.");
  } finally {
    loadingLocations.value = false;
  }
};

onMounted(() => {
  fetchLocations();
});

const headers = [
  { 
    title: "ID", 
    key: "id", 
    sortable: true,
    cellClass: "d-none d-sm-table-cell",
    headerClass: "d-none d-sm-table-cell"
  },
  { title: "Producto", key: "product.name", sortable: true },
  { 
    title: "Laboratorio", 
    key: "product.laboratory.name", 
    sortable: true,
    cellClass: "d-none d-md-table-cell",
    headerClass: "d-none d-md-table-cell"
  },
  { title: "# Lote", key: "lot_number", sortable: true },
  { 
    title: "Cantidad", 
    key: "quantity", 
    sortable: true,
    cellClass: "d-none d-sm-table-cell",
    headerClass: "d-none d-sm-table-cell"
  },
  { 
    title: "Expiración", 
    key: "expiration_date", 
    sortable: true,
    cellClass: "d-none d-sm-table-cell",
    headerClass: "d-none d-sm-table-cell"
  },
  { title: "Ubicación", key: "location", sortable: false },
  { title: "Acciones", key: "actions", sortable: false },
];

const saveInlineEdit = async (lot) => {
  if (!editingLocation.value || !editingLocation.value.trim()) {
    toast.warning("Por favor ingrese una ubicación");
    return;
  }

  try {
    emit("update-lot", {
      lot_id: lot.lot_id,
      location: editingLocation.value.trim(),
    });
  } catch (err) {
    console.error(err);
  }
};

const startEdit = (lot) => {
  editingLotId.value = lot.lot_id;
  editingLocation.value = lot.location || "";
  currentEditingLot.value = lot;
  searchInput.value = "";
};

const cancelEdit = () => {
  editingLotId.value = null;
  editingLocation.value = "";
  currentEditingLot.value = null;
  searchInput.value = "";
};

const handleLocationSearch = (search) => {
  searchInput.value = search;
};
</script>

<template>
  <VCard>
    <VDataTableServer
      :headers="headers"
      :items="lots"
      :items-length="totalLots"
      :items-per-page="itemsPerPage"
      :page="page"
      :loading="loading"
      @update:options="(opts) => emit('update:options', opts)"
    >
      <template #item.id="{ item }">
        {{ item.id }}
      </template>

      <template #item.product.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <VAvatar
            v-if="item.product?.photo_url"
            size="38"
            variant="tonal"
            rounded
            :image="item.product.photo_url"
          />
          <div class="d-flex flex-column">
            <span
              class="text-body-1 font-weight-medium text-high-emphasis"
              :class="{
                'text-warning font-weight-bold':
                  item.product?.psychotropic == 1 || item.product?.psychotropic === true,
              }"
            >
              <span class="d-inline d-sm-none text-primary font-weight-bold">[{{ item.id }}] </span>
              {{ item.product?.name?.toUpperCase() || "—" }}
              <span v-if="item.product?.iva == 1 || item.product?.iva === true"> (G)</span>
              <span v-if="item.product?.is_colombian_origin == 1 || item.product?.is_colombian_origin === true"> (COL)</span>
              <div v-if="item.product?.laboratory" class="d-block d-md-none text-xs text-secondary italic">
                {{ item.product.laboratory.name }}
              </div>
            </span>
            <span class="text-sm text-disabled">{{
              item.product?.active_ingredient || ""
            }}</span>
          </div>
        </div>
      </template>

      <template #item["product.laboratory.name"]="{ item }">
        <span>{{ item.product?.laboratory?.name || "—" }}</span>
      </template>

      <template #item.lot_number="{ item }">
        <span class="font-weight-medium">{{ item.lot_number || "—" }}</span>
      </template>

      <template #item.quantity="{ item }">
        <span class="font-weight-medium">{{ item.quantity || 0 }}</span>
      </template>

      <template #item.expiration_date="{ item }">
        <span>{{ formatDate(item.expiration_date) }}</span>
      </template>

      <template #item.location="{ item }">
        <template v-if="editingLotId === item.lot_id">
          <VAutocomplete
            v-model="editingLocation"
            :items="locationsList"
            item-title="name"
            item-value="name"
            density="compact"
            variant="outlined"
            class="responsive-autocomplete"
            style="min-width: 150px; flex-grow: 1;"
            placeholder="Seleccionar ubicación"
            :loading="loadingLocations"
            clearable
            @keydown.enter.prevent="saveInlineEdit(item)"
            autofocus
            :error="props.lotWithError === item.lot_id"
            :error-messages="
              props.lotWithError === item.lot_id
                ? props.errorMessage || 'Error al asignar ubicación'
                : ''
            "
            @update:search="handleLocationSearch"
          />
        </template>
        <template v-else>
          <span class="text-error font-weight-medium">Sin ubicación</span>
        </template>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex gap-2">
          <template v-if="editingLotId === item.lot_id">
            <VBtn
              icon="tabler-check"
              size="small"
              color="success"
              @click="saveInlineEdit(item)"
            />
            <VBtn icon="tabler-x" size="small" color="error" @click="cancelEdit" />
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
