<script setup>
import AppEmptyState from "@/components/AppEmptyState.vue";
import LotsWithoutLocationMobileCards from "@/components/LotsWithoutLocationMobileCards.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { formatDateSimple } from "@/utils/formatters";
import { computed, onMounted, ref } from "vue";

const props = defineProps({
  lots: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalLots: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  sortBy: { type: String, default: undefined },
  orderBy: { type: String, default: "asc" },
  lotWithError: { type: [Number, null], default: null },
  errorMessage: { type: String, default: "" },
});

const sortByModel = computed(() => {
  if (!props.sortBy) return [];
  return [{ key: props.sortBy, order: props.orderBy || "asc" }];
});

const emit = defineEmits(["update:options", "update-lot"]);

const editingLotId = ref(null);
const editingLocation = ref("");
const searchInput = ref("");
const currentEditingLot = ref(null);
const isSavingLocation = ref(false);

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

const headers = computed(() => [
  { 
    title: "ID", 
    key: "id", 
    sortable: true,
    cellClass: "font-weight-black text-primary",
  },
  { title: "PRODUCTO", key: "product.name", sortable: true, width: "35%" },
  { title: "# LOTE", key: "lot_number", sortable: true },
  { title: "STOCK", key: "quantity", sortable: true },
  { title: "VENCIMIENTO", key: "expiration_date", sortable: true },
  { title: "UBICACIÓN", key: "location", sortable: false },
  { title: "ACCIONES", key: "actions", sortable: false, align: "center" },
]);

const saveInlineEdit = async (lot) => {
  if (!editingLocation.value || !editingLocation.value.trim()) {
    toast.warning("Por favor ingrese una ubicación");
    return;
  }

  isSavingLocation.value = true;
  try {
    await emit("update-lot", {
      lot_id: lot.id,
      location: editingLocation.value.trim(),
    });
    cancelEdit();
  } catch (err) {
    console.error(err);
  } finally {
    isSavingLocation.value = false;
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
  isSavingLocation.value = false;
};

const handleLocationSearch = (search) => {
  searchInput.value = search;
};

const formatStock = (quantity) => {
  const stock = Number(quantity ?? 0);
  return stock % 1 === 0 ? stock.toString() : stock.toFixed(2).replace(".", ",");
};
</script>

<template>
  <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
    <!-- Vista de Tabla para Escritorio -->
    <div class="d-none d-sm-block">
      <VDataTableServer
        :headers="headers"
        :items="props.lots"
        :items-length="props.totalLots"
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
            title="¡Todo Ubicado!"
            message="No se encontraron lotes pendientes de asignación de ubicación en almacén."
            icon="tabler-map-pin-check"
          />
        </template>

        <!-- ID con enlace directo a trazabilidad -->
        <template #item.id="{ item }">
          <a
            :href="'/inventory/traceability?q=' + item.product?.id"
            target="_blank"
            class="text-decoration-none font-weight-black text-primary"
          >
            #{{ item.id }}
          </a>
        </template>

        <!-- PRODUCTO: Nombre, principio activo y laboratorio -->
        <template #item.product.name="{ item }">
          <div class="d-flex align-center gap-x-3 py-2">
            <div class="d-flex flex-column min-width-0">
              <span
                class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate product-title-max"
                :class="{ 
                  'text-warning': item.product?.psychotropic == 1 || item.product?.psychotropic === true
                }"
                :title="item.product?.name"
              >
                {{ item.product?.name?.toUpperCase() || "—" }}
                <span v-if="item.product?.iva == 1 || item.product?.iva === true" class="text-xs text-disabled"> (G)</span>
                <span v-if="item.product?.is_colombian_origin == 1 || item.product?.is_colombian_origin === true" class="text-xs text-disabled"> (COL)</span>
              </span>
              <div class="d-flex align-center gap-1 text-super-xs">
                <span class="text-disabled truncate active-ingredient-max">
                  {{ item.product?.active_ingredient || item.product?.presentation || 'Sin Especificación' }}
                </span>
                <span class="text-disabled mx-1">|</span>
                <span class="text-primary font-weight-black text-uppercase truncate lab-name-max">
                  {{ item.product?.laboratory?.name || 'S/L' }}
                </span>
              </div>
            </div>
          </div>
        </template>

        <!-- # LOTE -->
        <template #item.lot_number="{ item }">
          <span class="text-xs font-weight-black text-high-emphasis">{{ item.lot_number || "—" }}</span>
        </template>

        <!-- STOCK -->
        <template #item.quantity="{ item }">
          <VChip
            :color="(item.quantity ?? 0) > 0 ? 'success' : 'error'"
            label
            size="x-small"
            variant="tonal"
            class="font-weight-black"
          >
            {{ formatStock(item.quantity) }}
          </VChip>
        </template>

        <!-- VENCIMIENTO -->
        <template #item.expiration_date="{ item }">
          <span class="text-xs font-weight-medium">{{ formatDateSimple(item.expiration_date) }}</span>
        </template>

        <!-- UBICACIÓN -->
        <template #item.location="{ item }">
          <template v-if="editingLotId === item.id">
            <VAutocomplete
              v-model="editingLocation"
              :items="locationsList"
              item-title="name"
              item-value="name"
              density="compact"
              variant="outlined"
              class="field-autocomplete-input"
              placeholder="Buscar ubicación..."
              clearable
              hide-details
              autofocus
              :loading="loadingLocations || isSavingLocation"
              :disabled="isSavingLocation"
              :error="props.lotWithError === item.id"
              @keydown.enter.prevent="saveInlineEdit(item)"
              @update:search="handleLocationSearch"
            />
          </template>
          <template v-else>
            <span v-if="item.location" class="text-xs font-weight-medium">{{ item.location }}</span>
            <span v-else class="text-xs text-disabled font-weight-medium cursor-pointer" @click="startEdit(item)">—</span>
          </template>
        </template>

        <!-- ACCIONES -->
        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <template v-if="editingLotId === item.id">
              <IconBtn
                color="success"
                size="small"
                :loading="isSavingLocation"
                @click="saveInlineEdit(item)"
              >
                <VIcon icon="tabler-check" size="18" />
                <VTooltip activator="parent">Guardar</VTooltip>
              </IconBtn>
              <IconBtn
                color="secondary"
                size="small"
                :disabled="isSavingLocation"
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
                <VIcon icon="tabler-map-pin" size="18" />
                <VTooltip activator="parent" location="top">Asignar Ubicación</VTooltip>
              </IconBtn>
            </template>
          </div>
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista de Tarjetas para Móviles -->
    <LotsWithoutLocationMobileCards
      :lots="props.lots"
      :loading="props.loading"
      :total-lots="props.totalLots"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :sort-by="props.sortBy"
      :order-by="props.orderBy"
      :lot-with-error="props.lotWithError"
      :locations="locationsList"
      :loading-locations="loadingLocations"
      :editing-lot-id="editingLotId"
      v-model:editing-location="editingLocation"
      :is-saving="isSavingLocation"
      :search-input="searchInput"
      @update:options="(opts) => emit('update:options', opts)"
      @start-edit="startEdit"
      @cancel-edit="cancelEdit"
      @save-inline-edit="saveInlineEdit"
      @search-location="handleLocationSearch"
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

.field-autocomplete-input {
  max-inline-size: 240px;
  min-inline-size: 160px;
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
