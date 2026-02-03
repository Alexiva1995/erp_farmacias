<script setup>
import AppTextField from "@/@core/components/app-form-elements/AppTextField.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useRoute, useRouter } from "vue-router";
import { computed, onMounted, ref, watch } from "vue";

const route = useRoute();
const router = useRouter();
const cycleId = computed(() => route.params.cycleId);

const cycleInfo = ref(null);
const products = ref([]);
const totalProducts = ref(0);
const laboratories = ref([]);
const userOptions = ref([]);
const supervisorOptions = ref([]);
const loading = ref(false);
const isLoadingFilters = ref(false);

const page = ref(1);
const itemsPerPage = ref(15);
const searchQuery = ref("");
const selectedLaboratory = ref(null);
const discrepancyFilter = ref(null);
const selectedUserId = ref(null);
const selectedSupervisorId = ref(null);
const sortBy = ref("product.name");
const orderBy = ref("asc");

const headers = [
  { title: "#", key: "product_id", value: "product_id", sortable: true, align: "center", width: 60 },
  { title: "Producto", key: "product.name", value: "product.name", sortable: true, width: "320px" },
  { title: "Sistema", key: "system_quantity", value: "system_quantity", sortable: true, align: "center" },
  { title: "Físico", key: "final_quantity", sortable: true, align: "center" },
  { title: "Discrepancia", key: "discrepancy", sortable: true, align: "center" },
  { title: "Usuario", key: "user.email", value: "user.email", sortable: true, align: "center" },
  { title: "Supervisor", key: "supervisor.email", value: "supervisor.email", sortable: true, align: "center" },
];

const fetchLaboratories = async () => {
  isLoadingFilters.value = true;
  try {
    const [labsResponse, usersResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/inventory/cycle/users-with-counts"),
    ]);
    laboratories.value = labsResponse.data || [];
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
    toast.error("No se pudieron cargar los filtros.");
  } finally {
    isLoadingFilters.value = false;
  }
};

const fetchCycleInfo = async () => {
  try {
    const response = await axios.get(`/inventory/cycle/${cycleId.value}`);
    cycleInfo.value = response.data.data;
  } catch (error) {
    console.error("Error al obtener info del ciclo:", error);
  }
};

const fetchProducts = async () => {
  if (!cycleId.value)
    return;

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
    console.error("Error al obtener productos del ciclo:", error);
    toast.error("No se pudieron cargar los productos del ciclo.");
    products.value = [];
    totalProducts.value = 0;
  } finally {
    loading.value = false;
  }
};

const handleUpdateOptions = options => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;

  if (options.sortBy && options.sortBy.length > 0) {
    const { key, order } = options.sortBy[0];
    const map = {
      product: "product.name",
      "product.name": "product.name",
      system_quantity: "system_quantity",
      final_quantity: "final_quantity",
      fisico: "final_quantity",
      discrepancy: "discrepancy",
      "user.email": "user.email",
      "supervisor.email": "supervisor.email",
    };
    sortBy.value = map[key] || key;
    orderBy.value = order;
  } else {
    sortBy.value = undefined;
    orderBy.value = undefined;
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

const goBack = () => {
  router.back();
};

onMounted(() => {
  fetchLaboratories();
  fetchCycleInfo();
  fetchProducts();
});

let debounceTimer;
watch(
  [
    page,
    itemsPerPage,
    searchQuery,
    selectedLaboratory,
    discrepancyFilter,
    selectedUserId,
    selectedSupervisorId,
    sortBy,
    orderBy,
  ],
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
    <VBtn
      class="mb-4"
      color="primary"
      variant="tonal"
      prepend-icon="tabler-arrow-left"
      @click="goBack"
    >
      Volver
    </VBtn>

    <VCard class="mb-4">
      <VCardTitle>Filtros</VCardTitle>
      <VCardText>
        <VRow>
          <VCol cols="12" md="4">
            <AppTextField
              v-model="searchQuery"
              placeholder="Buscar producto o usuario..."
              clearable
              prepend-inner-icon="tabler-search"
            />
          </VCol>
          <VCol cols="12" md="4">
            <VAutocomplete
              v-model="selectedLaboratory"
              :items="laboratories"
              :loading="isLoadingFilters"
              label="Laboratorio"
              placeholder="Selecciona laboratorio"
              item-title="name"
              item-value="id"
              clearable
              prepend-inner-icon="tabler-building"
            />
          </VCol>
          <VCol cols="12" md="3">
            <VSelect
              v-model="discrepancyFilter"
              :items="[
                { title: 'Con discrepancia', value: 'with_discrepancy' },
                { title: 'Sobrantes', value: 'surplus' },
                { title: 'Faltantes', value: 'shortage' },
                { title: 'Sin discrepancia', value: 'exact' },
              ]"
              label="Discrepancia"
              placeholder="Todas"
              clearable
              prepend-inner-icon="tabler-filter"
            />
          </VCol>
          <VCol cols="12" md="3">
            <VAutocomplete
              v-model="selectedUserId"
              :items="userOptions"
              label="Usuario que contó"
              placeholder="Selecciona usuario"
              item-title="label"
              item-value="id"
              clearable
              prepend-inner-icon="tabler-user"
            />
          </VCol>
          <VCol cols="12" md="3">
            <VAutocomplete
              v-model="selectedSupervisorId"
              :items="supervisorOptions"
              label="Supervisor"
              placeholder="Selecciona supervisor"
              item-title="label"
              item-value="id"
              clearable
              prepend-inner-icon="tabler-user-check"
            />
          </VCol>
          <VCol cols="12" md="1" class="d-flex align-end">
            <VBtn
              color="secondary"
              variant="outlined"
              block
              prepend-icon="tabler-x"
              @click="handleClearFilters"
            >
              Limpiar
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardText>
        <VDataTableServer
          :headers="headers"
          :items="products"
          :items-length="totalProducts"
          :loading="loading"
          :page="page"
          :items-per-page="itemsPerPage"
          class="text-no-wrap"
          hover
          density="comfortable"
          @update:options="handleUpdateOptions"
          :items-per-page-options="[
            { value: 10, title: '10' },
            { value: 15, title: '15' },
            { value: 25, title: '25' },
            { value: 50, title: '50' },
            { value: -1, title: 'Todos' },
          ]"
        >
          <template #item.product.name="{ item }">
            <div class="d-flex align-start gap-x-3" style="max-width: 320px;">
              <VAvatar
                v-if="item.product?.photo_url"
                size="32"
                variant="tonal"
                rounded
                :image="item.product.photo_url"
              />
              <div class="d-flex flex-column">
                <span class="text-sm font-weight-medium text-high-emphasis">
                  {{ item.product?.name?.toUpperCase() || 'N/A' }}
                </span>
                <span class="text-xs text-disabled">
                  {{ item.product?.laboratory?.name || '—' }}
                </span>
              </div>
            </div>
          </template>

          <template #item.system_quantity="{ item }">
            {{ item.system_quantity ?? 0 }}
          </template>

          <template #item.final_quantity="{ item }">
            {{ item.final_quantity ?? item.counted_quantity ?? 0 }}
          </template>

          <template #item.discrepancy="{ item }">
            <span
              :class="{
                'text-success': item.discrepancy > 0,
                'text-error': item.discrepancy < 0,
                'text-medium-emphasis': item.discrepancy === 0,
              }"
              class="font-weight-medium"
            >
              {{ item.discrepancy > 0 ? `+${item.discrepancy}` : item.discrepancy }}
            </span>
          </template>

          <template #item.user.email="{ item }">
            {{
              (item.user?.employee_name ? `${item.user.employee_name.split(' ')[0]}` : '') +
                (item.user?.employee_last_name ? ` ${item.user.employee_last_name.split(' ')[0]}` : '') ||
                item.user?.email ||
                item.user?.username ||
                '—'
            }}
          </template>

          <template #item.supervisor.email="{ item }">
            {{
              (item.supervisor?.employee_name ? `${item.supervisor.employee_name.split(' ')[0]}` : '') +
                (item.supervisor?.employee_last_name ? ` ${item.supervisor.employee_last_name.split(' ')[0]}` : '') ||
                item.supervisor?.email ||
                item.supervisor?.username ||
                '—'
            }}
          </template>

          <template #item.source_type="{ item }">
            <VChip size="small" color="primary" variant="tonal" label>
              {{ item.source_type }}
            </VChip>
          </template>

          <template #bottom>
            <VDivider />
            <div class="pa-2 text-xs text-disabled">
              Mostrando {{ products.length }} de {{ totalProducts }} registros
            </div>
          </template>
        </VDataTableServer>
      </VCardText>
    </VCard>
  </div>
</template>
