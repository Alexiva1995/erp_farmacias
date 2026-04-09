<script setup>
import AppTextField from "@/@core/components/app-form-elements/AppTextField.vue";
import LotDistributionModal from "@/components/dialogs/LotDistributionModal.vue";
import VerifyCountModal from "@/components/dialogs/VerifyCountModal.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { formatDateSimple, formatPrice } from "@/utils/formatters";
import { computed, nextTick, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router/auto";

const route = useRoute();
const router = useRouter();

const cycleId = computed(() => route.query.id);

const cycleInfo = ref(null);
const products = ref([]);
const totalProducts = ref(0);
const laboratories = ref([]);
const locations = ref([]);
const userOptions = ref([]);
const supervisorOptions = ref([]);
const loading = ref(false);
const isLoadingFilters = ref(false);
const isAdvancedFiltersVisible = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const searchQuery = ref("");
const selectedLaboratory = ref(null);
const discrepancyFilter = ref(null);
const selectedUserId = ref(null);
const selectedSupervisorId = ref(null);
const sortBy = ref("product.name");
const orderBy = ref("asc");

// Modales de acciones
const showVerifyModal = ref(false);
const itemForVerification = ref(null);
const showLotDistributionModal = ref(false);
const itemForLotDistribution = ref(null);
const targetQuantityForDistribution = ref(0);
const pendingVerifyData = ref(null);

const headers = [
  { title: "#", key: "product_id", value: "product_id", sortable: true, align: "center", width: 60 },
  { title: "Producto", key: "product.name", value: "product.name", sortable: true, width: "280px" },
  { title: "Sistema", key: "system_quantity", value: "system_quantity", sortable: true, align: "center" },
  { title: "Físico", key: "final_quantity", sortable: true, align: "center" },
  { title: "Discrepancia", key: "discrepancy", sortable: true, align: "center" },
  { title: "Costo", key: "product.unit_cost", value: "product.unit_cost", sortable: true, align: "right" },
  { title: "Monto", key: "amount", sortable: true, align: "right" },
  { title: "Usuario / Supervisor", key: "user.email", value: "user.email", sortable: true },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

const fetchLaboratories = async () => {
  isLoadingFilters.value = true;
  try {
    const [labsResponse, usersResponse, locsResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/inventory/cycle/users-with-counts"),
      axios.get("/locations")
    ]);
    laboratories.value = labsResponse.data || [];
    locations.value = locsResponse.data.data || locsResponse.data || [];
    userOptions.value = (usersResponse.data || []).map(user => ({
      id: user.id,
      label:
        user.employee_name && user.employee_last_name
          ? `${user.employee_name.split(" ")[0]} ${user.employee_last_name.split(" ")[0]}`
          : user.employee_name
            ? user.employee_name.split(" ")[0]
            : user.username || user.email || `Usuario ${user.id}`,
    }));
    supervisorOptions.value = userOptions.value;
  } catch (error) {
    console.error("Error al cargar filtros:", error);
  } finally {
    isLoadingFilters.value = false;
  }
};

const fetchCycleInfo = async () => {
  if (!cycleId.value) return;
  try {
    const response = await axios.get(`/inventory/cycle/${cycleId.value}`);
    cycleInfo.value = response.data.data;
  } catch (error) {
    console.error("Error al obtener info del ciclo:", error);
  }
};

const fetchProducts = async () => {
  if (!cycleId.value) return;

  loading.value = true;
  const params = {
    cycleId: cycleId.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    q: searchQuery.value,
    laboratoryId: selectedLaboratory.value,
    discrepancyFilter: discrepancyFilter.value,
    userId: selectedUserId.value,
    supervisorId: selectedSupervisorId.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };

  Object.keys(params).forEach(
    key => (params[key] === null || params[key] === "" || params[key] === undefined) && delete params[key],
  );

  try {
    const response = await axios.get("/products/count", { params });
    products.value = response.data.data || [];
    totalProducts.value = response.data.total || 0;
  } catch (error) {
    console.error("Error al obtener productos:", error);
    toast.error("Error al cargar productos.");
  } finally {
    loading.value = false;
  }
};

// Acciones de Verificación
const handleVerifyItem = async (item) => {
  itemForVerification.value = item;
  await nextTick();
  showVerifyModal.value = true;
};

const callProcessApi = async (itemId, payload) => {
  try {
    const response = await axios.post(`/products/count/${itemId}/process`, payload);
    if (response.data.success) {
      toast.success(response.data.message);
      await fetchProducts();
    } else {
      toast.error(response.data.message);
    }
  } catch (error) {
    toast.error(error.response?.data?.message || "Error al procesar acción.");
  } finally {
    showLotDistributionModal.value = false;
    itemForLotDistribution.value = null;
    pendingVerifyData.value = null;
  }
};

const onVerifyNoDiscrepancy = async ({ countRecord }) => {
  await callProcessApi(countRecord.id, { action: 'approve' });
};

const onVerifyWithDiscrepancy = async ({ countRecord, newCountedQuantity }) => {
  pendingVerifyData.value = { countRecord, newCountedQuantity };
  itemForLotDistribution.value = countRecord.product;
  targetQuantityForDistribution.value = newCountedQuantity;
  await nextTick();
  showLotDistributionModal.value = true;
};

const handleLotsDistributed = async (distributionData) => {
  if (!pendingVerifyData.value) return;
  const { countRecord, newCountedQuantity } = pendingVerifyData.value;
  const payload = {
    action: 'approve',
    corrected_quantity: newCountedQuantity,
    updated_lots: distributionData.updatedLots,
    new_lots: distributionData.newLots,
  };
  await callProcessApi(countRecord.id, payload);
};

const handleUpdateOptions = options => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key;
    orderBy.value = options.sortBy[0].order;
  }
};

const handleClearFilters = () => {
  searchQuery.value = "";
  selectedLaboratory.value = null;
  discrepancyFilter.value = null;
  selectedUserId.value = null;
  selectedSupervisorId.value = null;
  sortBy.value = "product.name";
  orderBy.value = "asc";
};

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

const hasActiveAdvancedFilters = computed(() => {
  return selectedLaboratory.value || discrepancyFilter.value || selectedUserId.value || selectedSupervisorId.value;
});

const goBack = () => router.back();

onMounted(() => {
  fetchLaboratories();
  fetchCycleInfo();
  fetchProducts();
});

let debounceTimer;
watch(
  [page, itemsPerPage, searchQuery, selectedLaboratory, discrepancyFilter, selectedUserId, selectedSupervisorId, sortBy, orderBy],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(fetchProducts, 200);
  },
);

watch([searchQuery, selectedLaboratory, discrepancyFilter, selectedUserId, selectedSupervisorId], () => {
  page.value = 1;
});
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText class="pa-3">
        <VRow align="center" no-gutters class="gap-2">
          <VCol cols="12" md="4">
            <AppTextField
              v-model="searchQuery"
              placeholder="Buscar producto o contador..."
              prepend-inner-icon="tabler-search"
              clearable
              density="compact"
              hide-details
            />
          </VCol>
          <VSpacer />
          <div class="d-flex align-center gap-1">
            <VBtn
              icon variant="tonal"
              :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
              size="38"
              @click="toggleAdvancedFilters"
            >
              <VIcon :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" />
              <VTooltip activator="parent">Filtros Avanzados</VTooltip>
              <VBadge v-if="hasActiveAdvancedFilters && !isAdvancedFiltersVisible" color="error" dot offset-x="3" offset-y="-3" />
            </VBtn>
            <VBtn icon variant="tonal" color="secondary" size="38" @click="goBack">
              <VIcon icon="tabler-arrow-left" />
              <VTooltip activator="parent">Volver</VTooltip>
            </VBtn>
            <VDivider vertical class="mx-1 my-2" />
            <VBtn icon variant="text" color="secondary" size="38" @click="handleClearFilters">
              <VIcon icon="tabler-eraser" />
              <VTooltip activator="parent">Limpiar Filtros</VTooltip>
            </VBtn>
          </div>
        </VRow>
        <VExpandTransition>
          <div v-show="isAdvancedFiltersVisible">
            <VDivider class="my-3 border-opacity-10" />
            <VRow dense>
              <VCol cols="12" sm="6" md="3">
                <VAutocomplete
                  v-model="selectedLaboratory"
                  :items="laboratories"
                  :loading="isLoadingFilters"
                  placeholder="Laboratorio"
                  item-title="name"
                  item-value="id"
                  clearable density="compact" hide-details
                  prepend-inner-icon="tabler-building"
                />
              </VCol>
              <VCol cols="12" sm="6" md="3">
                <VSelect
                  v-model="discrepancyFilter"
                  :items="[
                    { title: 'Con discrepancia', value: 'with_discrepancy' },
                    { title: 'Sobrantes', value: 'surplus' },
                    { title: 'Faltantes', value: 'shortage' },
                    { title: 'Sin discrepancia', value: 'exact' },
                  ]"
                  placeholder="Discrepancia"
                  clearable density="compact" hide-details
                  prepend-inner-icon="tabler-filter"
                />
              </VCol>
              <VCol cols="12" sm="6" md="3">
                <VAutocomplete
                  v-model="selectedUserId"
                  :items="userOptions"
                  placeholder="Contador"
                  item-title="label" item-value="id"
                  clearable density="compact" hide-details
                  prepend-inner-icon="tabler-user"
                />
              </VCol>
              <VCol cols="12" sm="6" md="3">
                <VAutocomplete
                  v-model="selectedSupervisorId"
                  :items="supervisorOptions"
                  placeholder="Supervisor"
                  item-title="label" item-value="id"
                  clearable density="compact" hide-details
                  prepend-inner-icon="tabler-user-check"
                />
              </VCol>
            </VRow>
          </div>
        </VExpandTransition>
      </VCardText>
    </VCard>

    <VCard>
      <div class="d-none d-md-block">
        <VDataTableServer
          :headers="headers"
          :items="products"
          :items-length="totalProducts"
          :loading="loading"
          :page="page"
          :items-per-page="itemsPerPage"
          class="text-no-wrap"
          hover
          density="compact"
          @update:options="handleUpdateOptions"
        >
          <template #item.product.name="{ item }">
            <div class="d-flex align-start gap-x-3 py-2" style="max-inline-size: 280px;">
              <VAvatar v-if="item.product?.photo_url" size="34" variant="tonal" rounded :image="item.product.photo_url" />
              <div class="d-flex flex-column">
                <span class="text-sm font-weight-black text-high-emphasis truncate-1-line">{{ item.product?.name?.toUpperCase() || 'N/A' }}</span>
                <span class="text-xs text-primary font-weight-bold">{{ item.product?.laboratory?.name || '—' }}</span>
              </div>
            </div>
          </template>

          <template #item.system_quantity="{ item }">
            <span class="text-sm font-weight-medium">{{ item.system_quantity ?? 0 }}</span>
          </template>

          <template #item.final_quantity="{ item }">
            <span class="text-sm font-weight-black text-primary">{{ item.final_quantity ?? item.counted_quantity ?? 0 }}</span>
          </template>

          <template #item.discrepancy="{ item }">
            <VChip
              v-if="item.discrepancy !== 0"
              :color="item.discrepancy > 0 ? 'success' : 'error'"
              size="x-small" label variant="tonal" class="font-weight-black"
            >
              {{ item.discrepancy > 0 ? `+${item.discrepancy}` : item.discrepancy }}
            </VChip>
            <span v-else class="text-xs text-disabled">Sin diferencias</span>
          </template>

          <template #item.product.unit_cost="{ item }">
            <span class="text-sm font-weight-medium text-secondary">{{ formatPrice(item.product?.unit_cost || 0) }}</span>
          </template>

          <template #item.amount="{ item }">
            <span class="text-sm font-weight-black" :class="item.discrepancy >= 0 ? 'text-success' : 'text-error'">
              {{ formatPrice((item.discrepancy || 0) * (item.product?.unit_cost || 0)) }}
            </span>
          </template>

          <template #item.user.email="{ item }">
            <div class="d-flex flex-column py-1">
              <span class="text-xs font-weight-bold text-high-emphasis text-capitalize">
                {{ (item.user?.employee_name || '') + (item.user?.employee_last_name ? ` ${item.user.employee_last_name}` : '') || item.user?.email || '—' }}
              </span>
              <span v-if="item.supervisor" class="text-super-xs text-disabled text-capitalize d-flex align-center gap-1">
                <VIcon icon="tabler-user-check" size="10" />
                {{ (item.supervisor?.employee_name || '') + (item.supervisor?.employee_last_name ? ` ${item.supervisor.employee_last_name}` : '') }}
              </span>
            </div>
          </template>

          <template #item.actions="{ item }">
            <IconBtn size="small" color="primary" variant="tonal" class="rounded" @click="handleVerifyItem(item)">
              <VIcon icon="tabler-check" />
              <VTooltip activator="parent">Verificar Conteo</VTooltip>
            </IconBtn>
          </template>
        </VDataTableServer>
      </div>

      <!-- Vista de Móvil -->
      <div class="d-block d-md-none pa-2">
        <VProgressLinear v-if="loading" indeterminate color="primary" class="mb-2" />
        <div v-if="products.length === 0 && !loading" class="text-center py-8 text-disabled text-sm">No se encontraron registros.</div>
        <div class="d-flex flex-column gap-2">
          <VCard v-for="item in products" :key="item.id" variant="flat" class="count-mobile-card border mb-1">
            <div class="pa-3">
              <div class="d-flex align-start justify-space-between mb-3">
                <div class="d-flex flex-column min-width-0">
                  <span class="text-sm font-weight-black text-primary truncate-1-line">{{ item.product?.name?.toUpperCase() }}</span>
                  <div class="text-super-xs text-medium-emphasis d-flex align-center gap-x-2">
                    <span class="text-primary font-weight-bold">{{ item.product?.laboratory?.name }}</span>
                    <span class="text-disabled">|</span>
                    <span class="d-flex align-center"><VIcon icon="tabler-clock" size="10" class="me-1" /> {{ formatDateSimple(item.created_at) }}</span>
                  </div>
                </div>
                <IconBtn size="small" color="primary" variant="tonal" @click="handleVerifyItem(item)">
                  <VIcon icon="tabler-check" />
                </IconBtn>
              </div>
              <div class="d-flex align-center justify-space-between bg-var-theme-background px-3 py-2 rounded border-dashed-thin mb-3">
                <div class="d-flex flex-column">
                  <span class="text-super-xs text-disabled text-uppercase font-weight-black">FÍSICO</span>
                  <span class="text-base font-weight-black text-primary">{{ item.final_quantity ?? item.counted_quantity ?? 0 }}</span>
                </div>
                <div class="d-flex flex-column text-right">
                  <span class="text-super-xs text-disabled text-uppercase font-weight-black">MONTO</span>
                  <span class="text-sm font-weight-black" :class="item.discrepancy >= 0 ? 'text-success' : 'text-error'">
                    {{ formatPrice((item.discrepancy || 0) * (item.product?.unit_cost || 0)) }}
                  </span>
                </div>
              </div>
              <div class="d-flex align-center justify-space-between text-capitalize">
                <div class="d-flex flex-column">
                  <span class="text-super-xs font-weight-medium d-flex align-center gap-1">
                    <VIcon icon="tabler-user" size="12" class="text-disabled" />
                    {{ item.user?.employee_name }} {{ item.user?.employee_last_name }}
                  </span>
                  <span v-if="item.supervisor" class="text-super-xs text-disabled font-weight-medium d-flex align-center gap-1">
                    <VIcon icon="tabler-user-check" size="12" class="text-disabled" />
                    {{ item.supervisor?.employee_name }} {{ item.supervisor?.employee_last_name }}
                  </span>
                </div>
                <VChip v-if="item.discrepancy !== 0" :color="item.discrepancy > 0 ? 'success' : 'error'" size="x-small" label variant="flat">
                  {{ item.discrepancy > 0 ? `+${item.discrepancy}` : item.discrepancy }}
                </VChip>
              </div>
            </div>
          </VCard>
        </div>
        <div class="d-flex justify-center mt-4">
          <VPagination v-model="page" :length="Math.ceil(totalProducts / itemsPerPage)" :total-visible="3" density="compact" size="small" />
        </div>
      </div>
    </VCard>

    <!-- Modales -->
    <VerifyCountModal
      v-model="showVerifyModal"
      :count-record="itemForVerification"
      @verify-no-discrepancy="onVerifyNoDiscrepancy"
      @verify-with-discrepancy="onVerifyWithDiscrepancy"
    />
    <LotDistributionModal
      v-model="showLotDistributionModal"
      :product-name="itemForLotDistribution?.name || 'Producto'"
      :lots="itemForLotDistribution?.lots || []"
      :target-quantity="targetQuantityForDistribution || 0"
      :locations="locations"
      mode="adjustment"
      @save="handleLotsDistributed"
    />
  </div>
</template>

<style scoped>
.count-mobile-card { overflow: hidden; border-radius: 8px !important; background: rgb(var(--v-theme-surface)); }
.border-dashed-thin { border: 1px dashed rgba(var(--v-border-color), 0.3) !important; }
.bg-var-theme-background { background-color: rgba(var(--v-border-color), 0.05); }
.text-super-xs { font-size: 0.65rem !important; line-height: 1.2; }
.truncate-1-line { display: -webkit-box; overflow: hidden; -webkit-box-orient: vertical; -webkit-line-clamp: 1; }
.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-x-2 { column-gap: 8px !important; }
.gap-x-3 { column-gap: 12px !important; }
:deep(.v-data-table) { font-size: 0.8125rem; }
:deep(.v-data-table th) { color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important; font-size: 0.75rem !important; font-weight: 700 !important; text-transform: uppercase; }
</style>
