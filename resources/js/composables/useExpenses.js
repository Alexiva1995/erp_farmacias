import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import pdfGastos from "@/utils/pdfGastos";
import { reactive, ref, watch } from "vue";

export function useExpenses() {
  const isDeductible = ref(false);
  // Pestaña activa: null = Todos, o 'Pending' | 'Approved' | 'Cancelled'
  const activeTab = ref(null);

  const stats = reactive({
    totalApproved: 0,
    totalPending: 0,
    totalCancelled: 0,
    topCategory: null,
    loading: false,
  });

  const modal = reactive({
    statu: false,
    titulo: "Nuevo",
  });

  const statuModule = reactive({
    items: [],
    total: 0,
    categorias: [],
    loadingApp: false,
    loadingItems: new Set(),
  });

  const formulario = reactive({
    id: null,
    name: "",
    category_id: "",
    amount_bs: "",
    currency: "USD",
    is_deductible: false,
    iva: false,
    expense_date: "",
    user_id: "",
    count: "",
    conversion_rate: 0,
    exempt_amount: 0,
    taxable_base: 0,
    tax_amount: 0,
    exchange_rate: 0,
    total_amount: 0,
    total_usd: 0,
  });

  const formularioError = reactive({
    id: "",
    name: "",
    category_id: "",
    amount_bs: "",
    currency: "",
    is_deductible: "",
    iva: "",
    expense_date: null,
    count: "",
    conversion_rate: "",
    exempt_amount: "",
    taxable_base: "",
    tax_amount: "",
    exchange_rate: "",
    total_amount: "",
    total_usd: "",
  });

  const buscardor_filtro = ref("");
  const category_id_filtro = ref(null);
  const currency = ref(null);
  const fechaDesde_filtro = ref(null);
  const fechaHasta_filtro = ref(null);
  const status = ["Approved", "Cancelled", "Pending"];
  const type_of_expense = ["Normal"];

  const loading = ref(false);
  const isLoadingFilters = ref(false);

  const page = ref(1);
  const itemsPerPage = ref(10);
  const sortBy = ref();
  const orderBy = ref();

  function limpiarDatosFormulario() {
    formulario.id = null;
    formulario.name = "";
    formulario.category_id = "";
    formulario.amount_bs = "";
    formulario.currency = "USD";
    formulario.is_deductible = false;
    formulario.iva = false;
    formulario.expense_date = "";
    formulario.count = "";
    formulario.conversion_rate = 0;
    formulario.exempt_amount = 0;
    formulario.taxable_base = 0;
    formulario.tax_amount = 0;
    formulario.exchange_rate = 0;
    formulario.total_amount = 0;
    formulario.total_usd = 0;
  }

  function limpiarErroresFormulario() {
    formularioError.id = "";
    formularioError.name = "";
    formularioError.category_id = "";
    formularioError.amount_bs = "";
    formularioError.currency = "";
    formularioError.is_deductible = "";
    formularioError.iva = "";
    formularioError.expense_date = "";
    formularioError.count = "";
    formularioError.conversion_rate = "";
    formularioError.exempt_amount = "";
    formularioError.taxable_base = "";
    formularioError.tax_amount = "";
    formularioError.exchange_rate = "";
    formularioError.total_amount = "";
    formularioError.total_usd = "";
  }

  function cargarErrores(errores) {
    formularioError.id = errores.id ? errores.id.join(", ") : "";
    formularioError.name = errores.name ? errores.name.join(", ") : "";
    formularioError.category_id = errores.category_id
      ? errores.category_id.join(", ")
      : "";
    formularioError.amount_bs = errores.amount_bs
      ? errores.amount_bs.join(", ")
      : "";
    formularioError.currency = errores.currency
      ? errores.currency.join(", ")
      : "";
    formularioError.is_deductible = errores.is_deductible
      ? errores.is_deductible.join(", ")
      : "";
    formularioError.iva = errores.iva ? errores.iva.join(", ") : "";
    formularioError.expense_date = errores.expense_date
      ? errores.expense_date.join(", ")
      : "";
    formularioError.count = errores.count ? errores.count.join(", ") : "";
    formularioError.conversion_rate = errores.conversion_rate
      ? errores.conversion_rate.join(", ")
      : "";
    formularioError.exempt_amount = errores.exempt_amount
      ? errores.exempt_amount.join(", ")
      : "";
    formularioError.taxable_base = errores.taxable_base
      ? errores.taxable_base.join(", ")
      : "";
    formularioError.tax_amount = errores.tax_amount
      ? errores.tax_amount.join(", ")
      : "";
    formularioError.exchange_rate = errores.exchange_rate
      ? errores.exchange_rate.join(", ")
      : "";
    formularioError.total_amount = errores.total_amount
      ? errores.total_amount.join(", ")
      : "";
    formularioError.total_usd = errores.total_usd
      ? errores.total_usd.join(", ")
      : "";
  }

  function mostarModal() {
    modal.statu = true;
    modal.titulo = "Añadir Nuevo Gasto";
  }

  function cerrarModal(payload) {
    modal.statu = payload;
    limpiarDatosFormulario();
    limpiarErroresFormulario();
  }

  let debounceTimer;

  watch([page, itemsPerPage, sortBy, orderBy], () => {
    actualizarTabla();
  });

  watch(activeTab, () => {
    page.value = 1;
    actualizarTabla();
  });

  watch(
    [
      buscardor_filtro,
      category_id_filtro,
      currency,
      fechaDesde_filtro,
      fechaHasta_filtro,
      isDeductible,
    ],
    () => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        actualizarTabla();
      }, 300);
    },
    { deep: true }
  );

  watch(
    [
      buscardor_filtro,
      category_id_filtro,
      currency,
      fechaDesde_filtro,
      fechaHasta_filtro,
      isDeductible,
    ],
    () => {
      page.value = 1;
    }
  );

  async function consultarCategorias() {
    isLoadingFilters.value = true;
    try {
      let respuestaApi = await axios.get("/finances/expenses/category");
      if (respuestaApi.status != 200) {
        toast.error("Error al cargar las categorias de los gastos");
        return [];
      }
      statuModule.categorias = [...respuestaApi.data.data];
      return statuModule.categorias;
    } catch (error) {
      console.error("Error al cargar opciones de los filtros:", error);
      toast.error("No se pudieron cargar los filtros.");
      return [];
    } finally {
      isLoadingFilters.value = false;
    }
  }

  async function consultarGastos() {
    // Si hay pestaña activa => filtrar por ese status; si no => todos
    const statusFiltro = activeTab.value ? [activeTab.value] : ["Approved", "Cancelled", "Pending"];
    const DATA = {
      status: statusFiltro,
      buscardor_filtro: buscardor_filtro.value,
      currency: currency.value,
      category_id_filtro: category_id_filtro.value,
      fechaDesde_filtro: fechaDesde_filtro.value,
      fechaHasta_filtro: fechaHasta_filtro.value,
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      sortBy: sortBy.value,
      orderBy: orderBy.value,
      type_of_expense: type_of_expense,
      isDeductible: isDeductible.value,
    };

    Object.keys(DATA).forEach((key) => {
      if (DATA[key] === null || DATA[key] === "" || DATA[key] === false) {
        if (key !== "status" && key !== "type_of_expense") {
          delete DATA[key];
        }
      }
    });

    try {
      let respuestaApi = await axios.post(
        `/finances/expenses/filter-paginate?page=${page.value}`,
        DATA
      );
      if (respuestaApi.status !== 200) {
        toast.error("Error al cargar los gastos");
        return { data: [], total: 0 };
      }
      return { ...respuestaApi.data.data };
    } catch (error) {
      toast.error("Error al cargar los gastos");
      console.error("Error al consultar gastos:", error);
      return { data: [], total: 0 };
    }
  }

  async function actualizarTabla() {
    loading.value = true;
    try {
      let gastosPaginate = await consultarGastos();
      statuModule.items = gastosPaginate.data;
      statuModule.total = gastosPaginate.total;
    } catch (error) {
      console.error("Error al actualizar tabla:", error);
      toast.error("Error al actualizar la tabla de gastos");
    } finally {
      loading.value = false;
    }
  }

  async function consultarStats() {
    stats.loading = true;
    try {
      const [approved, pending, cancelled] = await Promise.all([
        axios.post(`/finances/expenses/filter-paginate?page=1`, {
          status: ["Approved"], type_of_expense: ["Normal"], itemsPerPage: 1,
        }),
        axios.post(`/finances/expenses/filter-paginate?page=1`, {
          status: ["Pending"], type_of_expense: ["Normal"], itemsPerPage: 1,
        }),
        axios.post(`/finances/expenses/filter-paginate?page=1`, {
          status: ["Cancelled"], type_of_expense: ["Normal"], itemsPerPage: 1,
        }),
      ]);
      stats.totalApproved = approved.data?.data?.total ?? 0;
      stats.totalPending = pending.data?.data?.total ?? 0;
      stats.totalCancelled = cancelled.data?.data?.total ?? 0;
    } catch (e) {
      console.error("Error cargando stats:", e);
    } finally {
      stats.loading = false;
    }
  }

  const updateTableOptions = (options) => {
    page.value = options.page;
    itemsPerPage.value = options.itemsPerPage;
    sortBy.value = options.sortBy[0]?.key;
    orderBy.value = options.sortBy[0]?.order;
  };

  function limpliarFiltros() {
    buscardor_filtro.value = "";
    currency.value = null;
    category_id_filtro.value = null;
    fechaDesde_filtro.value = null;
    fechaHasta_filtro.value = null;
    isDeductible.value = false;
  }

  async function generaPdf() {
    statuModule.loadingApp = true;
    const DATA = {
      status,
      buscardor_filtro: buscardor_filtro.value,
      currency: currency.value,
      category_id_filtro: category_id_filtro.value,
      fechaDesde_filtro: fechaDesde_filtro.value,
      fechaHasta_filtro: fechaHasta_filtro.value,
      isDeductible: isDeductible.value,
    };

    Object.keys(DATA).forEach((key) => {
      if (DATA[key] === null || DATA[key] === "" || DATA[key] === false) {
        if (key !== "isDeductible" && key !== "status") {
          delete DATA[key];
        }
      }
    });

    try {
      let respuestaApi = await axios.post(`/finances/expenses`, DATA);
      if (respuestaApi.status !== 200) {
        toast.error("Error al cargar los gastos");
        return;
      }
      pdfGastos([...respuestaApi.data.data], "Gastos");
    } catch (error) {
      toast.error("Error al generar el PDF");
      console.error("Error al generar PDF:", error);
    } finally {
      statuModule.loadingApp = false;
    }
  }

  async function exportarExcel(formato) {
    try {
      statuModule.loadingApp = true;
      let params = {
        formato,
        status,
        buscardor_filtro: buscardor_filtro.value,
        currency: currency.value,
        category_id_filtro: category_id_filtro.value,
        fechaDesde_filtro: fechaDesde_filtro.value,
        fechaHasta_filtro: fechaHasta_filtro.value,
        isDeductible: isDeductible.value,
      };

      Object.keys(params).forEach((key) => {
        if (
          params[key] === null ||
          params[key] === "" ||
          params[key] === false
        ) {
          if (key !== "isDeductible" && key !== "status" && key !== "formato") {
            delete params[key];
          }
        }
      });

      let respuestaApi = await axios.post(
        "/finances/expenses/exportar/excel",
        params,
        {
          responseType: "blob",
          headers: {
            "Content-Type": "application/json",
          },
        }
      );

      if (respuestaApi.status !== 200) {
        toast.error("Error al filtrar los datos");
        return;
      }

      const url = window.URL.createObjectURL(new Blob([respuestaApi.data]));
      const link = document.createElement("a");
      link.href = url;

      const contentDisposition = respuestaApi.headers["content-disposition"];
      let fileName = `gastos.${formato}`;
      if (contentDisposition) {
        const fileNameMatch = contentDisposition.match(/filename="(.+)"/);
        if (fileNameMatch && fileNameMatch.length === 2)
          fileName = fileNameMatch[1];
      }

      link.setAttribute("download", fileName);
      document.body.appendChild(link);
      link.click();

      link.remove();
      window.URL.revokeObjectURL(url);
    } catch (error) {
      console.error("Error al exportar los datos:", error);
      toast.error("Error al exportar los datos");
    } finally {
      statuModule.loadingApp = false;
    }
  }

  async function enviar(payload) {
    try {
      statuModule.loadingApp = true;

      const invoiceFile = payload.invoice_file;
      delete payload.invoice_file;

      const payloadToSend = {
        ...payload,
        total_amount: payload.total_amount,
        total_usd: payload.total_usd,
      };

      let respuesApi = await axios.post(
        "/finances/expenses/create-normal",
        payloadToSend
      );

      if (respuesApi.status === 200) {
        const newExpenseId = respuesApi.data.data.id;

        if (invoiceFile) {
          await uploadInvoiceFile(newExpenseId, invoiceFile);
        }

        toast.success("El gasto se guardó correctamente");
        cerrarModal(false);
        await actualizarTabla();
      }
    } catch (error) {
      toast.error("Error al crear el gasto");
      console.error("error en el servidor => ", error);

      if (error.response?.data?.data?.errors) {
        let errores = { ...error.response.data.data.errors };
        cargarErrores(errores);
      }
    } finally {
      statuModule.loadingApp = false;
    }
  }

  async function uploadInvoiceFile(expenseId, file) {
    try {
      const formData = new FormData();
      formData.append("id", expenseId);
      formData.append("file_invoice", file);

      const response = await axios.post(
        "/finances/expenses/upload-file-invoice",
        formData,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          },
        }
      );

      if (response.data.success) {
        toast.success("Factura subida correctamente");
        return true;
      } else {
        throw new Error(response.data.message || "Error al subir la factura");
      }
    } catch (error) {
      console.error("Error uploading file:", error);
      const errorMessage =
        error.response?.data?.message ||
        error.message ||
        "Error al subir el archivo. Intente nuevamente.";
      toast.error(errorMessage);
      return false;
    }
  }

  async function cambiarEstadoGasto(id, status) {
    try {
      statuModule.loadingItems.add(id);
      const response = await axios.post("/finances/expenses/change-status", {
        id,
        status
      });
      if (response.status === 200) {
        toast.success("Estado actualizado con éxito");
        await actualizarTabla();
        await consultarStats();
      }
    } catch (error) {
      toast.error("Error al actualizar el estado");
      console.error(error);
    } finally {
      statuModule.loadingItems.delete(id);
    }
  }

  async function initialize() {
    formulario.user_id = 1;
    await consultarCategorias();
    await Promise.all([actualizarTabla(), consultarStats()]);
  }

  return {
    // States
    isDeductible,
    activeTab,
    stats,
    modal,
    statuModule,
    formulario,
    formularioError,
    buscardor_filtro,
    category_id_filtro,
    currency,
    fechaDesde_filtro,
    fechaHasta_filtro,
    status,
    type_of_expense,
    loading,
    isLoadingFilters,
    page,
    itemsPerPage,
    sortBy,
    orderBy,

    // Methods
    limpiarDatosFormulario,
    limpiarErroresFormulario,
    cargarErrores,
    mostarModal,
    cerrarModal,
    actualizarTabla,
    updateTableOptions,
    limpliarFiltros,
    generaPdf,
    exportarExcel,
    enviar,
    cambiarEstadoGasto,
    initialize,
  };
}
