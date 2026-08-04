<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import AppEmptyState from "@/components/AppEmptyState.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, computed, onMounted } from "vue";
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
  { title: "Producto", key: "product.name", sortable: true },
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
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
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
</script>

<template>
  <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
    <!-- Desktop Table -->
    <div class="d-none d-md-block">
      <VDataTableServer
        :headers="headers"
        :items="lots"
        :items-length="totalLots"
        :items-per-page="itemsPerPage"
        :page="page"
        :loading="loading"
        class="text-no-wrap premium-table"
        @update:options="(opts) => emit('update:options', opts)"
      >
        <template #no-data>
          <AppEmptyState
            title="¡Todo Ubicado!"
            message="No se encontraron lotes pendientes de asignación de ubicación en almacén."
            icon="tabler-map-pin-check"
          />
        </template>

        <template #item.id="{ item }">
          <a
            :href="'/inventory/traceability?q=' + item.product?.id"
            target="_blank"
            class="text-decoration-none font-weight-black text-primary"
          >
            #{{ item.id }}
          </a>
        </template>

        <template #item.product.name="{ item }">
          <div class="d-flex align-center gap-x-3 py-1">
            <VAvatar
              v-if="item.product?.photo_url"
              size="38"
              variant="tonal"
              rounded
              :image="item.product.photo_url"
            />
            <div class="d-flex flex-column">
              <span
                class="text-sm font-weight-bold text-high-emphasis truncate"
                style="max-inline-size: 320px;"
                :class="{ 'text-warning': item.product?.psychotropic == 1 || item.product?.psychotropic === true }"
              >
                {{ item.product?.name?.toUpperCase() || "—" }}
                <span v-if="item.product?.iva == 1 || item.product?.iva === true" class="text-xs text-info ms-1">(G)</span>
                <span v-if="item.product?.is_colombian_origin == 1 || item.product?.is_colombian_origin === true" class="text-xs text-success ms-1">(COL)</span>
              </span>
              <span class="text-xs text-disabled truncate" style="max-inline-size: 280px;">
                {{ item.product?.active_ingredient || item.product?.presentation || 'Sin Especificación' }}
                <template v-if="item.product?.laboratory?.name">
                  • <strong class="text-primary">{{ item.product.laboratory.name }}</strong>
                </template>
              </span>
            </div>
          </div>
        </template>

        <template #item.lot_number="{ item }">
          <span class="text-xs font-weight-black text-high-emphasis">{{ item.lot_number || "—" }}</span>
        </template>

        <template #item.quantity="{ item }">
          <span class="text-xs font-weight-black" :class="(item.quantity ?? 0) > 0 ? 'text-success' : 'text-error'">
            {{ item.quantity || 0 }} <small>UNDS</small>
          </span>
        </template>

        <template #item.expiration_date="{ item }">
          <span class="text-xs font-weight-medium">{{ formatDateSimple(item.expiration_date) }}</span>
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
              hide-details
              style="max-inline-size: 280px; min-inline-size: 180px;"
              placeholder="Seleccionar ubicación..."
              :loading="loadingLocations || isSavingLocation"
              :disabled="isSavingLocation"
              clearable
              autofocus
              :error="props.lotWithError === item.id"
              @keydown.enter.prevent="saveInlineEdit(item)"
              @update:search="handleLocationSearch"
            />
          </template>
          <template v-else>
            <VChip size="x-small" color="error" variant="tonal" class="font-weight-bold">
              Sin ubicación
            </VChip>
          </template>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <template v-if="editingLotId === item.id">
              <VBtn
                icon
                size="30"
                color="success"
                variant="flat"
                class="rounded-lg shadow-sm me-1"
                :loading="isSavingLocation"
                @click="saveInlineEdit(item)"
              >
                <VIcon icon="tabler-check" size="18" />
              </VBtn>
              <VBtn
                icon
                size="30"
                color="secondary"
                variant="tonal"
                class="rounded-lg shadow-sm"
                :disabled="isSavingLocation"
                @click="cancelEdit"
              >
                <VIcon icon="tabler-x" size="18" />
              </VBtn>
            </template>
            <template v-else>
              <VBtn
                icon
                size="32"
                color="warning"
                variant="tonal"
                class="rounded-lg shadow-sm"
                @click="startEdit(item)"
              >
                <VIcon icon="tabler-map-pin" size="18" />
                <VTooltip activator="parent" location="top">Asignar Ubicación</VTooltip>
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

      <div v-else-if="lots.length > 0" class="pa-3 d-flex flex-column gap-3">
        <VCard
          v-for="item in lots"
          :key="item.id"
          class="border shadow-sm rounded-lg overflow-hidden"
        >
          <div class="pa-4">
            <div class="d-flex gap-3 align-start mb-2">
              <VAvatar
                v-if="item.product?.photo_url"
                size="42"
                variant="tonal"
                rounded
                :image="item.product.photo_url"
                class="flex-shrink-0"
              />
              <div class="flex-grow-1 min-width-0">
                <h3 class="text-xs font-weight-black text-high-emphasis text-uppercase leading-tight">
                  <a
                    :href="'/inventory/traceability?q=' + item.product?.id"
                    target="_blank"
                    class="text-decoration-none text-primary me-1"
                  >
                    #{{ item.id }}
                  </a>
                  <span class="text-disabled me-1">|</span>
                  {{ item.product?.name }}
                </h3>
                <span class="text-super-xs text-disabled truncate d-block mt-1">
                  {{ item.product?.active_ingredient || 'Sin Especificación' }} • <strong class="text-primary">{{ item.product?.laboratory?.name || 'S/L' }}</strong>
                </span>
              </div>
            </div>

            <VDivider class="my-3 opacity-10" />

            <div class="d-flex justify-space-between align-center mb-3">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold">No. Lote</span>
                <span class="text-xs font-weight-black text-high-emphasis">{{ item.lot_number || 'S/L' }}</span>
              </div>
              <div class="d-flex flex-column text-center">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold">Stock</span>
                <span class="text-xs font-weight-black" :class="(item.quantity ?? 0) > 0 ? 'text-success' : 'text-error'">
                  {{ item.quantity || 0 }} <small>UNDS</small>
                </span>
              </div>
              <div class="d-flex flex-column text-right">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold">Vencimiento</span>
                <span class="text-xs font-weight-bold">{{ formatDateSimple(item.expiration_date) }}</span>
              </div>
            </div>

            <!-- Edición Móvil -->
            <div v-if="editingLotId === item.id" class="pa-3 bg-surface-variant-opacity rounded-lg border">
              <VAutocomplete
                v-model="editingLocation"
                :items="locationsList"
                item-title="name"
                item-value="name"
                label="Asignar Ubicación"
                variant="outlined"
                density="compact"
                hide-details
                class="mb-3"
                placeholder="Seleccionar..."
                :loading="loadingLocations || isSavingLocation"
                :disabled="isSavingLocation"
                @update:search="handleLocationSearch"
                @keydown.enter.prevent="saveInlineEdit(item)"
              />
              <div class="d-flex gap-2 justify-center">
                <VBtn size="small" variant="tonal" color="secondary" class="flex-grow-1 font-weight-bold" :disabled="isSavingLocation" @click="cancelEdit">Cancelar</VBtn>
                <VBtn size="small" color="primary" class="flex-grow-1 font-weight-bold" :loading="isSavingLocation" @click="saveInlineEdit(item)">Guardar</VBtn>
              </div>
            </div>

            <div v-else class="d-flex justify-space-between align-center pa-2 rounded-lg bg-surface-variant-opacity border">
              <span class="text-super-xs text-disabled text-uppercase font-weight-bold">Ubicación:</span>
              <VChip size="x-small" color="error" variant="flat" class="font-weight-bold">
                Sin Ubicación Asignada
              </VChip>
            </div>
          </div>

          <div v-if="editingLotId !== item.id" class="border-t">
            <VBtn
              block
              color="warning"
              variant="text"
              class="rounded-0 text-xs font-weight-black"
              height="40"
              prepend-icon="tabler-map-pin"
              @click="startEdit(item)"
            >
              ASIGNAR UBICACIÓN
            </VBtn>
          </div>
        </VCard>

        <!-- Paginación Móvil -->
        <div class="d-flex justify-center mt-2">
           <AppMobilePagination
            :page="props.page"
            :items-per-page="props.itemsPerPage"
            :total-items="props.totalLots"
            :loading="props.loading"
            @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
          />
        </div>
      </div>

      <div v-else class="pa-4">
        <AppEmptyState
          title="¡Todo Ubicado!"
          message="No se encontraron lotes pendientes de asignación de ubicación en almacén."
          icon="tabler-map-pin-check"
        />
      </div>
    </div>
  </VCard>
</template>

<style scoped>
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
  padding-block: 10px !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.03) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
}

.bg-surface-variant-opacity {
  background-color: rgba(var(--v-theme-on-surface), 0.03) !important;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
