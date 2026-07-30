<script setup lang="js">
import SupplierIaOrderAssistantFilter from "@/components/SupplierIaOrderAssistantFilter.vue";
import SupplierIaOrderAssistantGrupoTable from "@/components/SupplierIaOrderAssistantGrupoTable.vue";
import SupplierIaOrderAssistantIndividualTable from "@/components/SupplierIaOrderAssistantIndividualTable.vue";
import ProductComparisionProductsTable from "@/components/ProductComparisionProductsTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { computed, onMounted, reactive, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { roundIaAnalysis } from "@/utils/iaAnalysisRounding";

const router = useRouter();

const statuModule = reactive({ total: 0, items: [] });
// Para vista grupal: grupos con sus productos anidados
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

// Computed reactivo: filtra en tiempo real sin necesidad de recargar la tabla
const displayedItems = computed(() => {
  if (soloConCoincidencias.value) {
    return statuModule.items.filter(p => p.best_supplier != null);
  }
  return statuModule.items;
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

  // Solo enviar isColombian/isNovaventa si están activos (switch encendido) para no confundir al backend
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
      // Guardar items crudos, displayedItems los filtra reactivamente
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

const skipAiMatch = ref(true);

async function handleFetchSuppliers() {
  withSuppliers.value = true;
  skipAiMatch.value = true; // Solo comparar el costo mas bajo de proveedor (sin IA)
  await actualizarTabla();
}

async function handleFetchAiMatches() {
  withSuppliers.value = true;
  skipAiMatch.value = false; // Disparar búsqueda de coincidencia por IA
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
    }, 400); // 400ms de retraso para evitar peticiones masivas
  },
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
      tipo_vista: false, // Forzar vista individual para obtener el listado plano
      tipo_filtracion: tipo_de_filtracion.value,
      lapso_de_tiempo: lapso_de_tiempo.value,
      stock: stock.value,
      hasStock: hasStock.value,
      isColombian: isColombian.value,
      isNovaventa: isNovaventa.value,
      tipo_exclusion: tipoExclusion.value,
      q: searchQuery.value,
      page: 1,
      itemsPerPage: 999999, // Límite alto para obtener todo
      sortBy: sortBy.value,
      orderBy: orderBy.value,
      with_suppliers: true, // Forzar comparación de proveedores
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
  // Construir los params con los filtros actuales
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
    // Descargar el archivo
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

const handleSendToAutoOrder = async ({ id, quantity, item }) => {
  try {
    // Validar código de barras diferente y preguntar por reemplazo si el listado tiene uno
    const nuestroBarcode = comparatorProduct.value?.barcode ? String(comparatorProduct.value.barcode).trim() : '';
    const listadoBarcode = item?.barcode_match ? String(item.barcode_match).trim() : '';

    if (listadoBarcode && nuestroBarcode !== listadoBarcode) {
      const { isConfirmed } = await Swal.fire({
        title: "¿Reemplazar código de barras?",
        html: `Nuestro producto actual tiene el código: <strong>${nuestroBarcode || 'vacío'}</strong>.<br>El del listado del proveedor es: <strong>${listadoBarcode}</strong>.<br><br>¿Desea actualizar nuestro código de barras por el del listado?`,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Sí, reemplazar",
        cancelButtonText: "No, mantener actual",
      });

      if (isConfirmed) {
        try {
          await axios.post(`/suppliers-ia-order-assistant/products/${comparatorProduct.value.id}/update-barcode`, {
            barcode: listadoBarcode
          });
          toast.success("Código de barras actualizado correctamente.");
          // Actualizamos la propiedad barcode del producto local en memoria para que coincida
          comparatorProduct.value.barcode = listadoBarcode;
        } catch (updateError) {
          console.error("Error updating barcode:", updateError);
          if (updateError.response?.status === 409 && updateError.response?.data?.conflict) {
            const { isConfirmed: confirmForce } = await Swal.fire({
              title: "Código duplicado",
              text: updateError.response.data.message,
              icon: "warning",
              showCancelButton: true,
              confirmButtonText: "Sí, desvincular y asignar",
              cancelButtonText: "Cancelar",
            });
            if (confirmForce) {
              try {
                await axios.post(`/suppliers-ia-order-assistant/products/${comparatorProduct.value.id}/update-barcode`, {
                  barcode: listadoBarcode,
                  force: true
                });
                toast.success("Código de barras actualizado correctamente.");
                comparatorProduct.value.barcode = listadoBarcode;
              } catch (forceError) {
                console.error("Error forcing barcode update:", forceError);
                toast.error("No se pudo forzar el reajuste del código de barras.");
              }
            }
          } else {
            toast.error("No se pudo actualizar el código de barras, pero se procederá con el pedido.");
          }
        }
      }
    }

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
          :show-graphs="showGraphs"
          :selected-supplier-id="selectedSupplier"
          @page-change="onGrupalPageChange"
          @product-scarce-toggled="handleProductScarceToggled"
          @open-comparator="handleOpenComparator"
          @remove-item="handleRemoveItem"
          @reject-ai-match="handleRejectAiMatch"
        />
        <!-- Vista Individual: tabla estándar paginada -->
        <SupplierIaOrderAssistantIndividualTable
          v-else
          :products="displayedItems"
          :total-product="statuModule.total"
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
                  Buscando para: <VChip color="surface" size="x-small" class="ml-2 font-weight-black text-truncate text-primary" max-width="600">{{ comparatorProduct?.name }}</VChip>
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
              :enable-discounts="con_descuento"
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
  padding: 0;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.03);
}

.hover-rotate:hover {
  transform: rotate(90deg);
  transition: transform 0.3s ease;
}

:deep(.v-card) {
  transition: all 0.3s ease;
}

:deep(.v-dialog .v-card) {
  animation: slide-up 0.4s ease-out;
}

@keyframes slide-up {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
