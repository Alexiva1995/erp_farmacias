<script setup lang="js">
import SupplierIaOrderAssistantFilter from "@/components/SupplierIaOrderAssistantFilter.vue";
import SupplierIaOrderAssistantGrupoTable from "@/components/SupplierIaOrderAssistantGrupoTable.vue";
import SupplierIaOrderAssistantIndividualTable from "@/components/SupplierIaOrderAssistantIndividualTable.vue";
import SupplierIaOrderAssistantComparatorModal from "@/components/SupplierIaOrderAssistantComparatorModal.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { computed, onMounted, reactive, ref, watch } from "vue";
import { roundIaAnalysis } from "@/utils/iaAnalysisRounding";

const statuModule = reactive({ total: 0, items: [] });
const gruposData = reactive({ grupos: [], total_grupos: 0, per_page: 25, current_page: 1, last_page: 1 });

const groups = ref([]);
const laboratories = ref([]);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(25);
const sortBy = ref("solicitar");
const orderBy = ref("desc");

const selectedLaboratory = ref([]);
const selectedGroup = ref([]);

const tipo_de_vista = ref(false);
const tipo_de_filtracion = ref("combinado");
const lapso_de_tiempo = ref("1 month");
const stock = ref("fallas");
const hasStock = ref("all");
const con_descuento = ref(false);
const isColombian = ref(false);
const isNovaventa = ref(false);
const tipoExclusion = ref([]);
const ordenarAhorro = ref(false);
const searchQuery = ref("");
const withSuppliers = ref(false);
const soloConCoincidencias = ref(false);
const showIgnored = ref(false);
const showGraphs = ref(false);
const selectedSupplier = ref(null);
const suppliers = ref([]);

const isOrderingAhorro = ref(false);
const isExporting = ref(false);

const displayedItems = computed(() => {
  if (soloConCoincidencias.value) {
    return statuModule.items.filter(p => p.best_supplier != null);
  }
  return statuModule.items;
});

const displayedTotal = computed(() => {
  if (soloConCoincidencias.value) {
    return displayedItems.value.length;
  }
  return statuModule.total;
});

const handleClearFilters = () => {
  withSuppliers.value = false;
  soloConCoincidencias.value = false;
  con_descuento.value = false;
  tipo_de_vista.value = false;
  tipo_de_filtracion.value = "combinado";
  lapso_de_tiempo.value = "1 month";
  stock.value = "fallas";
  hasStock.value = "all";
  isColombian.value = false;
  isNovaventa.value = false;
  tipoExclusion.value = [];
  ordenarAhorro.value = false;
  selectedLaboratory.value = [];
  selectedGroup.value = [];
  searchQuery.value = "";
  showIgnored.value = false;
  showGraphs.value = false;
  selectedSupplier.value = null;
  sortBy.value = "solicitar";
  orderBy.value = "desc";
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

async function consultarProveedores() {
  try {
    const respuesta = await axios.get("/suppliers", { params: { itemsPerPage: -1 } });
    suppliers.value = respuesta.data.data ?? respuesta.data;
  } catch (error) {
    console.error("Error al cargar proveedores", error);
  }
}

async function consultarProductosConPaginacion() {
  const data = {
    laboratoryId: selectedLaboratory.value,
    groups: selectedGroup.value,
    tipo_vista: tipo_de_vista.value,
    tipo_filtracion: tipo_de_filtracion.value,
    lapso_de_tiempo: lapso_de_tiempo.value,
    stock: stock.value,
    hasStock: hasStock.value,
    tipo_exclusion: tipoExclusion.value,
    q: searchQuery.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
    with_suppliers: withSuppliers.value,
    skip_ai_match: skipAiMatch.value,
    con_descuento: con_descuento.value,
    show_ignored: showIgnored.value,
    with_trend: showGraphs.value,
    supplier_id: selectedSupplier.value,
  };

  if (isColombian.value === true) data.isColombian = true;
  if (isNovaventa.value === true) data.isNovaventa = true;
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
      gruposData.grupos = paginacion.grupos ?? [];
      gruposData.total_grupos = paginacion.total_grupos ?? 0;
      gruposData.per_page = paginacion.per_page ?? 25;
      gruposData.current_page = paginacion.current_page ?? 1;
      gruposData.last_page = paginacion.last_page ?? 1;
      statuModule.items = [];
      statuModule.total = 0;
    } else {
      statuModule.items = paginacion.data ?? [];
      statuModule.total = paginacion.total ?? 0;
      gruposData.grupos = [];
      gruposData.total_grupos = 0;
    }
  } catch (e) {
    toast.error("Error al cargar los productos.");
  } finally {
    loading.value = false;
  }
}

const skipAiMatch = ref(true);

async function handleFetchSuppliers() {
  withSuppliers.value = true;
  skipAiMatch.value = true;
  await actualizarTabla();
}

async function handleFetchAiMatches() {
  withSuppliers.value = true;
  skipAiMatch.value = false;
  toast.info("Iniciando búsqueda de coincidencias con IA...");
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
    gruposData.grupos = gruposData.grupos.map(g => {
      if (!g.productos.some(p => p.id === productId)) return g;

      const nuevosProductos = g.productos.filter(p => p.id !== productId);
      const nuevaSumaPromedio = nuevosProductos.reduce((acc, p) => acc + (parseFloat(p.sales_average) || 0), 0);

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

const handleClearIgnore = async () => {
  try {
    const { isConfirmed } = await Swal.fire({
      title: "¿Restaurar productos ocultos?",
      text: "Todos los productos que fueron ignorados volverán a aparecer en los reportes.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Sí, restaurar",
      cancelButtonText: "Cancelar",
    });

    if (isConfirmed) {
      await axios.post("/suppliers-ia-order-assistant/clear-ignored");
      toast.success("Productos restaurados correctamente.");
      await actualizarTabla();
    }
  } catch (error) {
    toast.error("Error al restaurar productos.");
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

const handleRejectAiMatch = (productId) => {
  if (tipo_de_vista.value) {
    gruposData.grupos = gruposData.grupos.map(g => {
      const nuevosProductos = g.productos.map(p => {
        if (p.id === productId) {
          return { ...p, best_supplier: null, best_supplier_price: null };
        }
        return p;
      });
      return { ...g, productos: nuevosProductos };
    });
  } else {
    statuModule.items = statuModule.items.map(p => {
      if (p.id === productId) {
        return { ...p, best_supplier: null, best_supplier_price: null };
      }
      return p;
    });
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
    hasStock,
    isColombian,
    isNovaventa,
    tipoExclusion,
    searchQuery,
    con_descuento,
    showIgnored,
    showGraphs,
    selectedSupplier,
  ],
  () => {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(async () => {
      page.value = 1;
      await actualizarTabla();
    }, 400);
  },
  { deep: true }
);

watch(ordenarAhorro, (nuevoValor) => {
  if (nuevoValor) {
    sortBy.value = "best_supplier_percentage";
    orderBy.value = "desc";
  } else {
    if (sortBy.value === "best_supplier_percentage") {
      sortBy.value = "solicitar";
      orderBy.value = "desc";
    }
  }
});

watch(sortBy, (nuevaColumna) => {
  if (nuevaColumna !== "best_supplier_percentage") {
    ordenarAhorro.value = false;
  } else {
    ordenarAhorro.value = true;
  }
});

let paginationTimeout = null;
watch([page, itemsPerPage, orderBy, sortBy], () => {
  clearTimeout(paginationTimeout);
  paginationTimeout = setTimeout(async () => {
    await actualizarTabla();
  }, 200);
});

async function pedirTodoAhorro() {
  if (isOrderingAhorro.value) return;
  isOrderingAhorro.value = true;
  toast.info("Analizando oportunidades de ahorro...");
  try {
    const data = {
      laboratoryId: selectedLaboratory.value,
      groups: selectedGroup.value,
      tipo_vista: false,
      tipo_filtracion: tipo_de_filtracion.value,
      lapso_de_tiempo: lapso_de_tiempo.value,
      stock: stock.value,
      hasStock: hasStock.value,
      isColombian: isColombian.value,
      isNovaventa: isNovaventa.value,
      tipo_exclusion: tipoExclusion.value,
      q: searchQuery.value,
      page: 1,
      itemsPerPage: 999999,
      sortBy: sortBy.value,
      orderBy: orderBy.value,
      with_suppliers: true,
      con_descuento: con_descuento.value,
      show_ignored: showIgnored.value,
      supplier_id: selectedSupplier.value,
    };

    const resp = await axios.post("/suppliers-ia-order-assistant/filtrar-paginate?page=1", data);
    
    if (resp.status !== 200) {
      toast.error("Error al obtener productos");
      return;
    }

    const todosLosProductos = resp.data.data?.paginate?.data ?? [];

    const ahorroFiltrados = todosLosProductos.filter(item => {
      return item.best_supplier && item.best_supplier.id && item.best_supplier_percentage < 0 && item.best_supplier_price > 0;
    });

    if (ahorroFiltrados.length === 0) {
      toast.info("No hay productos con ofertas de ahorro bajo los filtros actuales.");
      return;
    }

    const { isConfirmed } = await Swal.fire({
      title: "¿Solicitar productos en ahorro?",
      text: `Se van a solicitar ${ahorroFiltrados.length} productos en ahorro. ¿Desea proceder?`,
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Sí, solicitar",
      cancelButtonText: "Cancelar",
    });

    if (!isConfirmed) return;

    const itemsPayload = ahorroFiltrados.map(item => {
      const qty = item.manual_solicitar !== null 
        ? item.manual_solicitar 
        : (item.solicitar ? roundIaAnalysis(item.solicitar) : 1);

      return {
        product_id: item.id,
        quantity: qty > 0 ? qty : 1,
        supplier_id: selectedSupplier.value || item.best_supplier.id,
        product_supplier_id: item.best_supplier.product_suppliers_id,
        unit_cost: item.best_supplier_price
      };
    });

    toast.info("Procesando solicitudes...");
    
    const sendResp = await axios.post("/suppliers-ia-order-assistant/add-multiple-to-order", {
      items: itemsPayload
    });

    if (sendResp.status === 200) {
      toast.success("Productos añadidos a la orden correctamente.");
      await actualizarTabla();
    } else {
      toast.error("No se pudieron procesar las solicitudes");
    }

  } catch (error) {
    console.error("Error en pedirTodoAhorro:", error);
    toast.error("Ocurrió un error al procesar el pedido masivo.");
  } finally {
    isOrderingAhorro.value = false;
  }
}

async function handleExportarColombianos() {
  if (isExporting.value) return;
  isExporting.value = true;
  const params = new URLSearchParams({
    tipo_filtracion: tipo_de_filtracion.value,
    lapso_de_tiempo: lapso_de_tiempo.value,
    stock: stock.value === 'fallas' ? 'fallas' : 'all',
    isColombian: true,
    show_ignored: showIgnored.value,
  });

  if (selectedLaboratory.value.length) {
    selectedLaboratory.value.forEach(id => params.append('laboratoryId[]', id));
  }

  try {
    toast.info('Generando Excel de Colombia...');
    const resp = await axios.get(
      `/suppliers-ia-order-assistant/exportar-colombianos?${params.toString()}`,
      { responseType: 'blob' }
    );
    const url  = window.URL.createObjectURL(new Blob([resp.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `colombia-pedido-${new Date().toISOString().slice(0,10)}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    toast.success('Excel descargado correctamente.');
  } catch (e) {
    toast.error('Error al exportar el archivo Excel.');
  } finally {
    isExporting.value = false;
  }
}

// Modal de Comparación Manual (Productos sin proveedor)
const isComparatorModalVisible = ref(false);
const comparatorProduct = ref(null);
const comparatorQuantity = ref(0);

const handleOpenComparator = ({ item, quantity }) => {
  comparatorProduct.value = item;
  comparatorQuantity.value = quantity;
  isComparatorModalVisible.value = true;
};

onMounted(async () => {
  await Promise.all([consultarGruposProductos(), consultarLaboratorios(), consultarProveedores()]);
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
        v-model:hasStock="hasStock"
        v-model:searchQuery="searchQuery"
        v-model:showIgnored="showIgnored"
        v-model:showGraphs="showGraphs"
        v-model:isColombian="isColombian"
        v-model:isNovaventa="isNovaventa"
        v-model:tipoExclusion="tipoExclusion"
        v-model:ordenarAhorro="ordenarAhorro"
        v-model:selectedSupplier="selectedSupplier"
        v-model:soloConCoincidencias="soloConCoincidencias"
        :groups="groups"
        :laboratories="laboratories"
        :suppliers="suppliers"
        :is-ordering-ahorro="isOrderingAhorro"
        :is-exporting="isExporting"
        @clear="handleClearFilters"
        @clear-ignore="handleClearIgnore"
        @pedirAhorro="pedirTodoAhorro"
        @fetchSuppliers="handleFetchSuppliers"
        @fetchAiMatches="handleFetchAiMatches"
        @exportarColombianos="handleExportarColombianos"
      />

      <!-- Tabla -->
      <div class="assistant-content">
        <!-- Vista Grupal -->
        <SupplierIaOrderAssistantGrupoTable
          v-if="tipo_de_vista == true"
          :grupos="gruposData.grupos"
          :total-grupos="gruposData.total_grupos"
          :per-page="gruposData.per_page"
          :current-page="gruposData.current_page"
          :last-page="gruposData.last_page"
          :loading="loading"
          :with-suppliers="withSuppliers"
          :show-graphs="showGraphs"
          :selected-supplier-id="selectedSupplier"
          @page-change="onGrupalPageChange"
          @product-scarce-toggled="handleProductScarceToggled"
          @open-comparator="handleOpenComparator"
          @remove-item="handleRemoveItem"
          @reject-ai-match="handleRejectAiMatch"
        />
        <!-- Vista Individual -->
        <SupplierIaOrderAssistantIndividualTable
          v-else
          :products="displayedItems"
          :total-product="displayedTotal"
          :loading="loading"
          :items-per-page="itemsPerPage"
          :page="page"
          :with-suppliers="withSuppliers"
          :show-graphs="showGraphs"
          :sort-by="sortBy"
          :order-by="orderBy"
          :selected-supplier-id="selectedSupplier"
          @update:options="updateTableOptionsTable"
          @refresh="actualizarTabla"
          @product-scarce-toggled="handleProductScarceToggled"
          @open-comparator="handleOpenComparator"
          @remove-item="handleRemoveItem"
          @reject-ai-match="handleRejectAiMatch"
        />
      </div>
    </div>

    <!-- Dialogo de Comparación Manual Desacoplado -->
    <SupplierIaOrderAssistantComparatorModal
      v-model="isComparatorModalVisible"
      :product="comparatorProduct"
      :quantity="comparatorQuantity"
      :con-descuento="con_descuento"
      @product-added="handleRemoveItem"
    />
  </div>
</template>

<style scoped>
.assistant-ia-view {
  min-block-size: 100vh;
}

.assistant-content {
  padding: 0;
}
</style>
