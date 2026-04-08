<script setup lang="js">
import SupplierIaOrderAssistantFilter from "@/components/SupplierIaOrderAssistantFilter.vue";
import SupplierIaOrderAssistantGrupoTable from "@/components/SupplierIaOrderAssistantGrupoTable.vue";
import SupplierIaOrderAssistantIndividualTable from "@/components/SupplierIaOrderAssistantIndividualTable.vue";
import ProductComparisionProductsTable from "@/components/ProductComparisionProductsTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, reactive, ref, watch } from "vue";
import { useRouter } from "vue-router";

const router = useRouter();

const statuModule = reactive({ total: 0, items: [] });
// Para vista grupal: grupos con sus productos anidados
const gruposData = reactive({ grupos: [], total_grupos: 0, per_page: 25, current_page: 1, last_page: 1 });

const groups = ref([]);
const laboratories = ref([]);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(25);
const sortBy = ref();
const orderBy = ref();

const selectedLaboratory = ref([]);
const selectedGroup = ref([]);

const tipo_de_vista = ref(false);
const tipo_de_filtracion = ref("combinado");
const lapso_de_tiempo = ref("1 month");
const stock = ref("all");
const con_descuento = ref(true);
const isColombian = ref(false);
const searchQuery = ref("");
const withSuppliers = ref(false);

const handleClearFilters = () => {
  withSuppliers.value = false;
  con_descuento.value = true;
  tipo_de_vista.value = false;
  tipo_de_filtracion.value = "combinado";
  lapso_de_tiempo.value = "1 month";
  stock.value = "all";
  isColombian.value = false;
  selectedLaboratory.value = [];
  selectedGroup.value = [];
  searchQuery.value = "";
};

async function consultarLaboratorios() {
  const respuesta = await axios.get("/laboratories");
  laboratories.value = respuesta.data;
}

async function consultarGruposProductos() {
  const respuestaApi = await axios.get("/groups/consult-all");
  if (respuestaApi.status !== 200) {
    toast.error("Error al cargar grupos");
    return;
  }
  groups.value = [...respuestaApi.data.data];
}

async function consultarProductosConPaginacion() {
  const data = {
    laboratoryId: selectedLaboratory.value,
    groups: selectedGroup.value,
    tipo_vista: tipo_de_vista.value,
    tipo_filtracion: tipo_de_filtracion.value,
    lapso_de_tiempo: lapso_de_tiempo.value,
    stock: stock.value,
    isColombian: isColombian.value,
    q: searchQuery.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    with_suppliers: withSuppliers.value,
    con_descuento: con_descuento.value, // Asegurar que el flag de descuento se pase siempre
  };
  const resp = await axios.post(
    `/suppliers-ia-order-assistant/filtrar-paginate?page=${page.value}`,
    data,
  );
  if (resp.status !== 200) toast.error("Error al filtrar los datos");
  return { ...resp.data };
}

async function actualizarTabla() {
  loading.value = true;
  try {
    const respuesta = await consultarProductosConPaginacion();
    const paginacion = respuesta.data.paginate;

    if (tipo_de_vista.value) {
      // Vista grupal: el servidor devuelve { grupos, total_grupos, per_page, current_page, last_page }
      gruposData.grupos = paginacion.grupos ?? [];
      gruposData.total_grupos = paginacion.total_grupos ?? 0;
      gruposData.per_page = paginacion.per_page ?? 25;
      gruposData.current_page = paginacion.current_page ?? 1;
      gruposData.last_page = paginacion.last_page ?? 1;
      // Limpiar vista individual
      statuModule.items = [];
      statuModule.total = 0;
    } else {
      // Vista individual: paginator estándar de Laravel
      statuModule.items = paginacion.data ?? [];
      statuModule.total = paginacion.total ?? 0;
      // Limpiar vista grupal
      gruposData.grupos = [];
      gruposData.total_grupos = 0;
    }
  } catch (e) {
    toast.error("Error al cargar los productos.");
  } finally {
    loading.value = false;
  }
}

async function handleFetchSuppliers() {
  withSuppliers.value = true;
  await actualizarTabla();
}

const updateTableOptionsTable = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const onGrupalPageChange = (newPage) => {
  page.value = newPage;
  actualizarTabla();
};

const handleProductScarceToggled = (productId) => {
  if (tipo_de_vista.value) {
    // 1. Encontrar el grupo que contiene el producto
    gruposData.grupos = gruposData.grupos.map(g => {
      if (!g.productos.some(p => p.id === productId)) return g;

      // 2. Remover el producto
      const nuevosProductos = g.productos.filter(p => p.id !== productId);

      // 3. Recalcular suma de promedios para la "preferencia"
      const nuevaSumaPromedio = nuevosProductos.reduce((acc, p) => acc + (parseFloat(p.sales_average) || 0), 0);

      // 4. Actualizar preferencia_product para los productos restantes
      const productosRecalculados = nuevosProductos.map(p => {
        const avg = parseFloat(p.sales_average) || 0;
        return {
          ...p,
          preferencia_product: nuevaSumaPromedio > 0 ? (avg / nuevaSumaPromedio) * 100 : 0
        };
      });

      return {
        ...g,
        productos: productosRecalculados,
      };
    }).filter(g => g.productos.length > 0);
  } else {
    statuModule.items = statuModule.items.filter(item => item.id !== productId);
    statuModule.total -= 1;
  }
};

const handleRemoveItem = (productId) => {
  if (tipo_de_vista.value) {
     handleProductScarceToggled(productId);
  } else {
    statuModule.items = statuModule.items.filter(item => item.id !== productId);
    statuModule.total -= 1;
  }
};

let filterTimeout = null;
watch(
  [
    selectedLaboratory,
    selectedGroup,
    tipo_de_vista,
    tipo_de_filtracion,
    lapso_de_tiempo,
    stock,
    isColombian,
    searchQuery,
    con_descuento,
  ],
  () => {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(async () => {
      page.value = 1;
      await actualizarTabla();
    }, 400); // 400ms de retraso para evitar peticiones masivas
  },
);

let paginationTimeout = null;
watch([page, itemsPerPage, orderBy, sortBy], () => {
  clearTimeout(paginationTimeout);
  paginationTimeout = setTimeout(async () => {
    await actualizarTabla();
  }, 200);
});

function generarPedido() {
  toast.info("Navegando a generar pedido...");
  console.log("[DEBUG] Iniciando generarPedido desde el asistente");
  router.push({
    path: "/suppliers/generar-pedido",
    query: {
      con_descuento: con_descuento.value,
      tipo_filtracion: tipo_de_filtracion.value,
      lapso_de_tiempo: lapso_de_tiempo.value,
      stock: stock.value,
      isColombian: isColombian.value,
      laboratoryId: JSON.stringify(selectedLaboratory.value),
      groups: JSON.stringify(selectedGroup.value),
      q: searchQuery.value,
    },
  });
}

// Modal de Comparación Manual (Productos sin proveedor)
const isComparatorModalVisible = ref(false);
const comparatorProduct = ref(null);
const comparatorQuantity = ref(0);
const comparatorSearchQuery = ref("");
const comparatorProducts = ref([]);
const comparatorLoading = ref(false);
const comparatorTotal = ref(0);
const comparatorPage = ref(1);
const comparatorItemsPerPage = ref(10);
const comparatorSortBy = ref([{ key: 'unit_cost_usd', order: 'asc' }]);

const handleOpenComparator = ({ item, quantity }) => {
  comparatorProduct.value = item;
  comparatorQuantity.value = quantity;
  
  // Regla de búsqueda: Nombre (5) + Lab (3)
  const namePart = item.name ? item.name.substring(0, 5) : "";
  const labPart = item.laboratory?.name ? item.laboratory.name.substring(0, 3) : "";
  comparatorSearchQuery.value = `${namePart} ${labPart}`.trim();
  
  comparatorPage.value = 1;
  isComparatorModalVisible.value = true;
};

const fetchComparatorProducts = async () => {
  if (!isComparatorModalVisible.value) return;
  
  comparatorLoading.value = true;
  try {
    const { data } = await axios.get("/suppliers/available-products", {
      params: {
        page: comparatorPage.value,
        perPage: comparatorItemsPerPage.value,
        q: comparatorSearchQuery.value,
        sortBy: comparatorSortBy.value[0]?.key,
        order: comparatorSortBy.value[0]?.order,
      }
    });
    comparatorProducts.value = data.data;
    comparatorTotal.value = data.total;
  } catch (error) {
    console.error("[Comparator] Error:", error);
    toast.error("Error al buscar productos de proveedores");
  } finally {
    comparatorLoading.value = false;
  }
};

watch([isComparatorModalVisible, comparatorSearchQuery, comparatorPage, comparatorItemsPerPage, comparatorSortBy], () => {
  if (isComparatorModalVisible.value) {
    fetchComparatorProducts();
  }
});

const handleSendToAutoOrder = async ({ id, quantity }) => {
  try {
    const form = new FormData();
    form.append("productId", id);
    form.append("main_product_id", comparatorProduct.value.id);
    form.append("quantity", quantity);
    
    await axios.post("/suppliers/add-product-to-order", form);
    
    toast.success("Producto añadido a la orden de compra.");
    // Cerramos el modal
    isComparatorModalVisible.value = false;
    // Removemos de la página actual para que el usuario pueda seguir trabajando
    handleRemoveItem(comparatorProduct.value.id);
    // No refrescamos toda la tabla para no ralentizar la UX
  } catch (error) {
    console.error("[Comparator] Error sending to order:", error);
    toast.error("Error al añadir producto a la orden.");
  }
};

onMounted(async () => {
  await Promise.all([consultarGruposProductos(), consultarLaboratorios()]);
  await actualizarTabla();
});
</script>

<template>
  <div class="assistant-ia-view pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <!-- Filtros -->
      <SupplierIaOrderAssistantFilter
        class="mb-6"
        v-model:selectConDescuento="con_descuento"
        v-model:selectedLaboratory="selectedLaboratory"
        v-model:selectedGroup="selectedGroup"
        v-model:tipo_de_vista="tipo_de_vista"
        v-model:tipo_de_filtracion="tipo_de_filtracion"
        v-model:lapso_de_tiempo="lapso_de_tiempo"
        v-model:stock="stock"
        v-model:isColombian="isColombian"
        v-model:searchQuery="searchQuery"
        :groups="groups"
        :laboratories="laboratories"
        :tipo_de_filtracion="tipo_de_filtracion"
        :tipo_de_vista="tipo_de_vista"
        :lapso_de_tiempo="lapso_de_tiempo"
        :stock="stock"
        :isColombian="isColombian"
        @clear="handleClearFilters"
        @generarPedido="generarPedido"
        @fetchSuppliers="handleFetchSuppliers"
      />

      <!-- Tabla -->
      <div class="assistant-content">
        <!-- Vista Grupal: acordeón con grupos paginados por el servidor -->
        <SupplierIaOrderAssistantGrupoTable
          v-if="tipo_de_vista == true"
          :grupos="gruposData.grupos"
          :total-grupos="gruposData.total_grupos"
          :per-page="gruposData.per_page"
          :current-page="gruposData.current_page"
          :last-page="gruposData.last_page"
          :loading="loading"
          :with-suppliers="withSuppliers"
          @page-change="onGrupalPageChange"
          @product-scarce-toggled="handleProductScarceToggled"
        />
        <!-- Vista Individual: tabla estándar paginada -->
        <SupplierIaOrderAssistantIndividualTable
          v-else
          :products="statuModule.items"
          :total-product="statuModule.total"
          :loading="loading"
          :items-per-page="itemsPerPage"
          :page="page"
          :with-suppliers="withSuppliers"
          @update:options="updateTableOptionsTable"
          @refresh="actualizarTabla"
          @product-scarce-toggled="handleProductScarceToggled"
          @open-comparator="handleOpenComparator"
          @remove-item="handleRemoveItem"
        />
      </div>
    </div>

    <!-- Dialogo de Comparación Manual (Buscador de Proveedores) -->
    <VDialog v-model="isComparatorModalVisible" max-width="1200" scrollable persistent transition="dialog-bottom-transition">
      <VCard class="rounded-xl shadow-2xl overflow-hidden border-0 elevation-24">
        <VCardTitle class="pa-0">
          <div class="bg-primary px-6 py-4 d-flex align-center justify-space-between w-100 border-b border-primary-darken-1">
            <div class="d-flex align-center">
              <div class="bg-white bg-opacity-10 pa-2 rounded-lg mr-4 border border-white border-opacity-10">
                <VIcon icon="tabler-arrows-exchange" color="white" size="24" />
              </div>
              <div class="d-flex flex-column overflow-hidden">
                <span class="text-h6 font-weight-black text-white leading-tight mb-0">Comparador de Proveedores</span>
                <span class="text-caption text-white text-opacity-80 d-flex align-center">
                  Buscando para: <span class="bg-white bg-opacity-10 px-2 py-0.5 rounded ml-1 text-truncate font-weight-bold" style="max-inline-size: 500px; font-size: 0.7rem;">{{ comparatorProduct?.name }}</span>
                </span>
              </div>
            </div>
            <VBtn icon="tabler-x" variant="tonal" color="white" size="small" @click="isComparatorModalVisible = false" class="rounded-lg hover-rotate" />
          </div>
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-0 bg-var-theme-background">
          <div class="pa-6">
            <ProductComparisionProductsTable
              :products="comparatorProducts"
              :loading="comparatorLoading"
              :total-products="comparatorTotal"
              :items-per-page="comparatorItemsPerPage"
              :page="comparatorPage"
              :search-query="comparatorSearchQuery"
              :selected-product="comparatorProduct"
              enable-usd-amount-col
              enable-discount-col
              v-model:sort-by="comparatorSortBy"
              @update:searchQuery="comparatorSearchQuery = $event"
              @update:options="(options) => { 
                  comparatorPage = options.page; 
                  comparatorItemsPerPage = options.itemsPerPage; 
              }"
              @send-product="handleSendToAutoOrder"
            />
          </div>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.assistant-ia-view {
  min-block-size: 100vh;
}

.assistant-content {
  padding: 16px;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.03);
}

.hover-rotate:hover { transform: rotate(90deg); transition: transform 0.3s ease; }

:deep(.v-card) {
  transition: all 0.3s ease;
}

:deep(.v-dialog .v-card) {
  animation: slide-up 0.4s ease-out;
}

@keyframes slide-up {
  from { transform: translateY(30px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}
</style>
鼓
