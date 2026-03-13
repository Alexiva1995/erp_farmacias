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
  <VCard padding="0">
    <!-- Desktop Table -->
    <div class="d-none d-md-block">
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
                {{ item.product?.name?.toUpperCase() || "—" }}
                <span v-if="item.product?.iva == 1 || item.product?.iva === true"> (G)</span>
                <span v-if="item.product?.is_colombian_origin == 1 || item.product?.is_colombian_origin === true"> (COL)</span>
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
              style=" flex-grow: 1;min-inline-size: 150px;"
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
    </div>

    <!-- Mobile Cards -->
    <div class="d-block d-md-none">
      <div v-if="loading && lots.length === 0" class="pa-5 text-center">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <div class="pa-2">
        <VCard
          v-for="item in lots"
          :key="item.lot_id"
          variant="flat"
          class="lot-mobile-card border mb-2 overflow-hidden"
        >
          <div class="pa-3">
            <div class="d-flex gap-3 align-start mb-2">
              <VAvatar
                v-if="item.product?.photo_url"
                size="44"
                variant="tonal"
                rounded
                :image="item.product.photo_url"
                class="flex-shrink-0 mt-1"
              />
              <div class="flex-grow-1 min-width-0">
                <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight">
                  <span class="text-primary mr-1">#{{ item.id }}</span>
                  <span class="mx-1 text-disabled">|</span>
                  {{ item.product?.name || 'S/N' }}
                </h3>
                <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs mt-1">
                  <span class="text-primary font-weight-bold">{{ item.product?.laboratory?.name || 'S/L' }}</span>
                </div>
              </div>
            </div>

            <VDivider class="my-2 border-opacity-10" />

            <div class="d-flex justify-space-between align-center px-1 mb-2">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold">Lote</span>
                <span class="text-xs font-weight-black text-primary">{{ item.lot_number || 'S/L' }}</span>
              </div>
              <div class="d-flex flex-column text-center">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold">Stock</span>
                <span class="text-xs font-weight-black text-success">{{ item.quantity || 0 }} <small>UNDS</small></span>
              </div>
              <div class="d-flex flex-column text-right">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold">Expira</span>
                <span class="text-xs font-weight-medium">{{ formatDate(item.expiration_date) }}</span>
              </div>
            </div>

            <div class="bg-var-theme-background pa-2 rounded mt-2 text-center">
              <!-- Edit Mode inside Card -->
              <div v-if="editingLotId === item.lot_id">
                <VAutocomplete
                  v-model="editingLocation"
                  :items="locationsList"
                  item-title="name"
                  item-value="name"
                  label="Asignar Ubicación"
                  density="compact"
                  variant="outlined"
                  class="mb-2"
                  hide-details="auto"
                  placeholder="Seleccionar..."
                  :loading="loadingLocations"
                  @update:search="handleLocationSearch"
                  @keydown.enter.prevent="saveInlineEdit(item)"
                />
                <div class="d-flex gap-2 justify-center mt-2">
                  <VBtn variant="tonal" color="secondary" size="small" class="flex-grow-1" @click="cancelEdit">Cancelar</VBtn>
                  <VBtn color="primary" size="small" class="flex-grow-1" @click="saveInlineEdit(item)">Guardar</VBtn>
                </div>
              </div>

              <!-- Display Mode -->
              <div v-else class="d-flex justify-center align-center py-1">
                <VIcon icon="tabler-map-pin-off" size="14" color="error" class="me-2" />
                <span class="text-xs font-weight-black text-error text-uppercase">Sin ubicación asignada</span>
              </div>
            </div>
          </div>

          <!-- Acciones Rectangulares Movil -->
          <div v-if="editingLotId !== item.lot_id" class="d-flex border-t border-opacity-10">
            <VBtn
              block
              color="warning"
              variant="text"
              class="rounded-0"
              height="40"
              prepend-icon="tabler-map-pin"
              @click="startEdit(item)"
            >
              ASIGNAR UBICACIÓN
            </VBtn>
          </div>
        </VCard>

        <!-- Paginación Móvil -->
        <div class="d-flex justify-center mt-4 pb-2">
          <VPagination
            :model-value="page"
            :length="Math.ceil(totalLots / itemsPerPage)"
            :total-visible="3"
            density="compact"
            size="small"
            @update:model-value="emit('update:options', { page: $event, itemsPerPage })"
          />
        </div>
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.lot-mobile-card {
  border-radius: 8px !important;
  background: rgb(var(--v-theme-surface));
}

.text-super-xs {
  font-size: 0.65rem !important;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-theme-primary), 0.05);
}
</style>
