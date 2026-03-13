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
  loadingLocations.value = true;
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
  <VCard class="rounded-xl border-0 elevation-12 overflow-hidden bg-surface">
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
          <span class="text-xs font-weight-black text-disabled">#{{ item.id }}</span>
        </template>

        <template #item.product.name="{ item }">
          <div class="d-flex flex-column py-2">
            <span
              class="text-subtitle-2 font-weight-black text-high-emphasis leading-tight uppercase"
              :class="{
                'text-warning':
                  item.product?.psychotropic == 1 || item.product?.psychotropic === true,
              }"
            >
              {{ item.product?.name?.toUpperCase() || "—" }}
              <span v-if="item.product?.is_colombian_origin == 1 || item.product?.is_colombian_origin === true" class="text-info"> (COL)</span>
            </span>
            <span class="text-super-xs font-weight-bold text-disabled uppercase">{{
              item.product?.active_ingredient || ""
            }}</span>
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
          <template v-if="editingLotId === item.id">
            <VAutocomplete
              v-model="editingLocation"
              :items="locationsList"
              item-title="name"
              item-value="name"
              density="comfortable"
              variant="outlined"
              class="premium-textfield shadow-sm"
              style="min-inline-size: 180px;"
              placeholder="Ubicación..."
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
            <VChip color="error" variant="tonal" size="x-small" class="font-weight-black uppercase">
              Sin ubicación
            </VChip>
          </template>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-2">
            <template v-if="editingLotId === item.id">
              <VBtn
                icon="tabler-check"
                size="x-small"
                color="success"
                variant="elevated"
                class="shadow-sm"
                @click="saveInlineEdit(item)"
              />
              <VBtn 
                icon="tabler-x" 
                size="x-small" 
                color="error" 
                variant="tonal"
                @click="cancelEdit" 
              />
            </template>
            <template v-else>
              <VBtn
                icon
                size="small"
                variant="tonal"
                color="warning"
                class="rounded-lg"
                @click="startEdit(item)"
              >
                <VIcon icon="tabler-edit" size="18" />
              </VBtn>
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
          class="lot-mobile-card border mb-3 overflow-hidden rounded-xl shadow-sm bg-white"
        >
          <div class="pa-4">
            <div class="d-flex justify-space-between align-start mb-3">
              <div class="flex-grow-1 min-width-0">
                <div class="d-flex align-center gap-2 mb-1">
                  <VChip size="x-small" color="primary" variant="flat" class="font-weight-black uppercase px-2 shadow-sm">
                    ID: {{ item.id }}
                  </VChip>
                  <div class="header-indicator primary"></div>
                </div>
                <h3 class="text-subtitle-1 font-weight-black text-high-emphasis text-uppercase leading-tight mb-1">
                  {{ item.product?.name || 'SIN NOMBRE' }}
                </h3>
                <div class="d-flex align-center gap-x-2 text-super-xs">
                  <span class="text-primary font-weight-bold uppercase">{{ item.product?.laboratory?.name || 'S/L' }}</span>
                </div>
              </div>
            </div>

            <VDivider class="my-3 border-opacity-10" />

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

            <div class="bg-light pa-3 rounded-lg mt-3 border-dashed-1 shadow-inner">
              <!-- Edit Mode inside Card -->
              <div v-if="editingLotId === item.id">
                <VAutocomplete
                  v-model="editingLocation"
                  :items="locationsList"
                  item-title="name"
                  item-value="name"
                  label="Ubicación"
                  density="comfortable"
                  variant="outlined"
                  class="mb-3 premium-textfield shadow-sm"
                  hide-details="auto"
                  placeholder="Seleccionar..."
                  :loading="loadingLocations"
                  @update:search="handleLocationSearch"
                  @keydown.enter.prevent="saveInlineEdit(item)"
                />
                <div class="d-flex gap-3 justify-center mt-2">
                  <VBtn variant="tonal" color="secondary" size="small" class="flex-grow-1 font-weight-black" @click="cancelEdit">CANCELAR</VBtn>
                  <VBtn color="primary" size="small" class="flex-grow-1 font-weight-black shadow-sm" @click="saveInlineEdit(item)">GUARDAR</VBtn>
                </div>
              </div>

              <!-- Display Mode -->
              <div v-else class="d-flex justify-center align-center py-2">
                <VIcon icon="tabler-map-pin-off" size="16" color="error" class="me-2" />
                <span class="text-xs font-weight-black text-error text-uppercase letter-spacing-05">Ubicación Pendiente</span>
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
  border-radius: 16px !important;
  background: rgb(var(--v-theme-surface));
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-05 { letter-spacing: 0.5px !important; }

.bg-light {
  background-color: #f8fafc !important;
}

.shadow-inner {
  box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 6%) !important;
}

.premium-textfield :deep(.v-field__outline) {
  --v-field-border-opacity: 0.1 !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 4px;
}

.header-indicator.primary { background-color: rgb(var(--v-theme-primary)); }

.border-dashed-1 {
  border: 1px dashed rgba(var(--v-border-color), 20%) !important;
}
</style>
