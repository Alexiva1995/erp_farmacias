<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, onMounted } from "vue";
import { formatDateSimple } from "@/utils/formatters";

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
  loadingLocations.value = true;
  try {
    const response = await axios.get("/locations");
    locationsList.value = response.data.data || response.data || [];
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
    cellClass: "font-weight-black text-primary",
  },
  { title: "Producto", key: "product.name", sortable: true },
  { 
    title: "Laboratorio", 
    key: "product.laboratory.name", 
    sortable: true,
    visible: false,
    cellClass: "d-none d-md-table-cell",
    headerClass: "d-none d-md-table-cell"
  },
  { title: "# Lote", key: "lot_number", sortable: true },
  { 
    title: "Stock", 
    key: "quantity", 
    sortable: true,
    cellClass: "d-none d-sm-table-cell",
    headerClass: "d-none d-sm-table-cell"
  },
  { 
    title: "Exp.", 
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
      lot_id: lot.id,
      location: editingLocation.value.trim(),
    });
  } catch (err) {
    console.error(err);
  }
};

const startEdit = (lot) => {
  editingLotId.value = lot.id;
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
  <VCard class="pa-0">
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
          <a
            :href="'/inventory/traceability?q=' + item.product?.id"
            target="_blank"
            class="text-decoration-none font-weight-black text-primary"
          >
            {{ item.id }}
          </a>
        </template>

        <template #item.product.name="{ item }">
          <div class="d-flex align-center gap-x-4 py-2">
            <VAvatar
              v-if="item.product?.photo_url"
              size="44"
              variant="tonal"
              rounded
              :image="item.product.photo_url"
              class="border elevation-1"
            />
            <div class="d-flex flex-column">
              <span
                class="text-sm font-weight-black text-high-emphasis text-uppercase truncate"
                style="max-inline-size: 300px;"
                :class="{
                  'text-warning': item.product?.psychotropic == 1 || item.product?.psychotropic === true,
                }"
              >
                {{ item.product?.name || "—" }}
                <span v-if="item.product?.iva == 1 || item.product?.iva === true"> (G)</span>
                <span v-if="item.product?.is_colombian_origin == 1 || item.product?.is_colombian_origin === true"> (COL)</span>
              </span>
              <div class="d-flex align-center gap-1 text-super-xs">
                <span class="text-disabled truncate" style="max-inline-size: 180px;">{{ item.product?.active_ingredient || "" }}</span>
                <span class="text-disabled mx-1">|</span>
                <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 120px;">
                  {{ item.product?.laboratory?.name || 'S/L' }}
                </span>
              </div>
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
          <VChip
            :color="(item.quantity ?? 0) > 0 ? 'success' : 'error'"
            label
            size="x-small"
            variant="tonal"
            class="font-weight-black"
          >
            {{ item.quantity || 0 }}
          </VChip>
        </template>

        <template #item.expiration_date="{ item }">
          <span>{{ formatDate(item.expiration_date) }}</span>
        </template>

        <template #item.location="{ item }">
          <template v-if="editingLotId === item.id">
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
              :error="props.lotWithError === item.id"
              :error-messages="
                props.lotWithError === item.id
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
            <template v-if="editingLotId === item.id">
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
          :key="item.id"
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
                class="flex-shrink-0 mt-1 border"
              />
              <div class="flex-grow-1 min-width-0">
                <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight truncate-2-lines">
                  <a
                    :href="'/inventory/traceability?q=' + item.product?.id"
                    target="_blank"
                    class="text-decoration-none text-primary text-xs font-weight-black"
                  >
                    #{{ item.id }}
                  </a>
                  <span class="mx-1 text-disabled">|</span>
                  {{ item.product?.name || 'S/N' }}
                </h3>
                <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs mt-1">
                  <span class="text-medium-emphasis font-weight-medium text-truncate" style="max-inline-size: 150px;">{{ item.product?.active_ingredient || '' }}</span>
                  <span class="text-disabled">|</span>
                  <span class="text-primary font-weight-black text-uppercase text-truncate" style="max-inline-size: 120px;">{{ item.product?.laboratory?.name || 'S/L' }}</span>
                </div>
              </div>
            </div>
 
            <VDivider class="my-2 border-opacity-10" />
 
            <div class="d-flex justify-space-between align-center bg-var-theme-background-light px-3 py-2 rounded border-dashed-thin mb-2">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black text-xs">Vencimiento</span>
                <span class="text-base font-weight-black">{{ formatDate(item.expiration_date) }}</span>
              </div>
              <div class="d-flex flex-column text-center">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black text-xs">Stock</span>
                <span class="text-base font-weight-black text-success">{{ item.quantity || 0 }} <small class="text-super-xs">UNDS</small></span>
              </div>
            </div>

            <div class="mt-2 bg-var-theme-background rounded pa-2 d-flex justify-space-between align-center border-s-4 border-warning">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-black">Lote No.</span>
                <span class="text-sm font-weight-black">{{ item.lot_number || 'S/L' }}</span>
              </div>
            </div>
 
            <div class="mt-3">
              <!-- Edit Mode inside Card -->
              <div v-if="editingLotId === item.id">
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
          <div v-if="editingLotId !== item.id" class="d-flex border-t border-opacity-10">
            <VBtn
              block
              color="warning"
              variant="flat"
              class="rounded-0 font-weight-black"
              height="44"
              prepend-icon="tabler-map-pin"
              @click="startEdit(item)"
            >
              ASIGNAR UBICACIÓN
            </VBtn>
          </div>
        </VCard>
 
        <!-- Paginación Móvil -->
        <div class="d-flex justify-center mt-4 pb-2">
           <AppMobilePagination
            :page="props.page"
            :items-per-page="props.itemsPerPage"
            :total-items="props.totalLots"
            :loading="props.loading"
            @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
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

.truncate-2-lines {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
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

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }

:deep(.v-data-table th) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}
</style>
