<script setup lang="js">
import ExpenseFormDialoge from '@/components/dialogs/ExpenseFormDialoge.vue';
import ExpenseTable from '@/components/ExpenseTable.vue';
import FiltrosGastos from '@/components/FiltrosGastos.vue';
import LoaderComponent from '@/components/LoaderComponent.vue';

import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import pdfGastos from '@/utils/pdfGastos';
import { onMounted, reactive, ref, watch } from 'vue';

const isDeductible = ref(false);
const hasInvoice = ref(false);

const modal= reactive({
  statu:false,
  titulo:"Nuevo",
})

const statuModule= reactive({
  items:[],
  total:0,
  categorias:[],
  loadingApp:false
})

const formulario= reactive({
  id:null,
  name:"",
  category_id:"",
  amount:"",
  amount_usd:"",
  amount_bs:"",
  currency:"USD",
  has_invoice:false,
  invoice_number:null,
  invoice_date:null,
  control_number:null,
  is_deductible:false,
  iva:false,
  expense_date:"",
  user_id:"",
  count:"",
  account:null,
  file_factura:null,
  conversion_rate_to_bs:0,
  exempt_amount:0,
  taxable_base:0,
  tax_amount:0,
  exchange_rate:0,
  total_amount:0,
  total_usd:0,
  // recurrence:"Mensual",
})

const formularioError= reactive({
  id:"",
  name:"",
  category_id:"",
  amount:"",
  amount_usd:"",
  amount_bs:"",
  currency:"",
  has_invoice:"",
  invoice_number:"",
  invoice_date:"",
  control_number:"",
  is_deductible:"",
  iva:"",
  expense_date:null,
  count:"",
  account:"",
  conversion_rate_to_bs:"",
  file_factura:null,
  exempt_amount:"",
  taxable_base:"",
  tax_amount:"",
  exchange_rate:"",
  total_amount:"",
  total_usd:"",
  // recurrence:"",
})

const buscardor_filtro = ref(""); // nombre, id
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

function insertarDatosAlFormulario(datos){
  formulario.id=datos.id
  formulario.id=datos.name
  formulario.category_id=datos.category_id
  formulario.amount=datos.amount
  formulario.amount_usd=datos.amount_usd
  formulario.amount_bs=datos.amount_bs
  formulario.currency=datos.currency
  formulario.has_invoice=datos.has_invoice
  formulario.is_deductible=datos.is_deductible
  formulario.iva=datos.iva
  formulario.expense_date=datos.expense_date
  formulario.count=datos.count
}

function limpiarDatosFormulario(){
  formulario.id=null
  formulario.name=""
  formulario.category_id=""
  formulario.amount=""
  formulario.amount_usd=""
  formulario.amount_bs=""
  formulario.currency="USD"
  formulario.has_invoice=false
  formulario.invoice_number=null
  formulario.invoice_date=null
  formulario.control_number=null
  formulario.is_deductible=false
  formulario.iva=false
  formulario.expense_date=""
  formulario.count=""
  formulario.account=null
  formulario.file_factura=null
  formulario.conversion_rate_to_bs=0
  formulario.exempt_amount=0
  formulario.taxable_base=0
  formulario.tax_amount=0
  formulario.exchange_rate=0
  formulario.total_amount=0
  formulario.total_usd=0
}

function limpiarErroresFormulario(){
  formularioError.id=""
  formularioError.name=""
  formularioError.category_id=""
  formularioError.amount=""
  formularioError.amount_usd=""
  formularioError.amount_bs=""
  formularioError.currency=""
  formularioError.has_invoice=""
  formularioError.invoice_number=""
  formularioError.invoice_date=""
  formularioError.control_number=""
  formularioError.is_deductible=""
  formularioError.iva=""
  formularioError.expense_date=""
  formularioError.count=""
  formularioError.account=""
  formularioError.conversion_rate_to_bs=""
  formularioError.exempt_amount=""
  formularioError.taxable_base=""
  formularioError.tax_amount=""
  formularioError.exchange_rate=""
  formularioError.total_amount=""
  formularioError.total_usd=""
  // formularioError.recurrence=""
  formularioError.file_factura=""
}

function cargarErrores(errores){
  formularioError.id=(errores.id)?errores.id.join(", "):""
  formularioError.name=(errores.name)?errores.name.join(", "):""
  formularioError.category_id=(errores.category_id)?errores.category_id.join(", "):""
  formularioError.amount=(errores.amount)?errores.amount.join(", "):""
  formularioError.amount_usd=(errores.amount_usd)?errores.amount_usd.join(", "):""
  formularioError.amount_bs=(errores.amount_bs)?errores.amount_bs.join(", "):""
  formularioError.currency=(errores.currency)?errores.currency.join(", "):""
  formularioError.has_invoice=(errores.has_invoice)?errores.has_invoice.join(", "):""
  formularioError.invoice_number=(errores.invoice_number)?errores.invoice_number.join(", "):""
  formularioError.invoice_date=(errores.invoice_date)?errores.invoice_date.join(", "):""
  formularioError.control_number=(errores.control_number)?errores.control_number.join(", "):""
  formularioError.is_deductible=(errores.is_deductible)?errores.is_deductible.join(", "):""
  formularioError.iva=(errores.iva)?errores.iva.join(", "):""
  formularioError.expense_date=(errores.expense_date)?errores.expense_date.join(", "):""
  formularioError.count=(errores.count)?errores.count.join(", "):""
  formularioError.account=(errores.account)?errores.account.join(", "):""
  formularioError.conversion_rate_to_bs=(errores.conversion_rate_to_bs)?errores.conversion_rate_to_bs.join(", "):""
  formularioError.exempt_amount=(errores.exempt_amount)?errores.exempt_amount.join(", "):""
  formularioError.taxable_base=(errores.taxable_base)?errores.taxable_base.join(", "):""
  formularioError.tax_amount=(errores.tax_amount)?errores.tax_amount.join(", "):""
  formularioError.exchange_rate=(errores.exchange_rate)?errores.exchange_rate.join(", "):""
  formularioError.total_amount=(errores.total_amount)?errores.total_amount.join(", "):""
  formularioError.total_usd=(errores.total_usd)?errores.total_usd.join(", "):""
  // formularioError.recurrence=(errores.recurrence)?errores.recurrence.join(", "):""
  formularioError.file_factura=(errores.file_factura)?errores.file_factura.join(", "):""
}

function mostarModal(){
  modal.statu=true
  modal.titulo="Añadir Nuevo Gasto"
}

function cerrarModal(payload){
  modal.statu=payload
  limpiarDatosFormulario()
  limpiarErroresFormulario()
}

// Debounce para búsqueda
let debounceTimer;

watch(
  [
    page,
    itemsPerPage,
    sortBy,
    orderBy,
  ],
  () => {
    actualizarTabla();
  }
);

watch(
  [
    buscardor_filtro,
    category_id_filtro,
    currency,
    fechaDesde_filtro,
    fechaHasta_filtro,
    isDeductible,
    hasInvoice,
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
    hasInvoice,
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
    return [...respuestaApi.data.data];
  } catch (error) {
    console.error("Error al cargar opciones de los filtros:", error);
    toast.error("No se pudieron cargar los filtros.");
    return [];
  } finally {
    isLoadingFilters.value = false;
  }
}

async function consultarGastos() {
  const DATA = {
    status,
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
    hasInvoice: hasInvoice.value,
  };

  // Limpiar parámetros vacíos
  Object.keys(DATA).forEach((key) => {
    if (DATA[key] === null || DATA[key] === "" || DATA[key] === false) {
      if (
        key !== "status" &&
        key !== "type_of_expense"
      ) {
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

async function actualizarTabla(){
  loading.value=true
  let gastosPaginate=await consultarGastos()
  statuModule.items=gastosPaginate.data
  statuModule.total=gastosPaginate.total
  loading.value=false
}

const updateTableOptions = options => {
  // console.log(options)
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

function limpliarFiltros() {
  buscardor_filtro.value = "";
  currency.value = null;
  category_id_filtro.value = null;
  fechaDesde_filtro.value = null;
  fechaHasta_filtro.value = null;
  isDeductible.value = false;
  hasInvoice.value = false;
}


async function generaPdf(){
  statuModule.loadingApp = true;
  const DATA = {
    status,
    buscardor_filtro: buscardor_filtro.value,
    currency: currency.value,
    category_id_filtro: category_id_filtro.value,
    fechaDesde_filtro: fechaDesde_filtro.value,
    fechaHasta_filtro: fechaHasta_filtro.value,
    isDeductible: isDeductible.value,
    hasInvoice: hasInvoice.value,
  };

  // Limpiar parámetros vacíos
  Object.keys(DATA).forEach((key) => {
    if (DATA[key] === null || DATA[key] === "" || DATA[key] === false) {
      if (key !== 'isDeductible' && key !== 'hasInvoice' && key !== 'status') {
        delete DATA[key];
      }
    }
  });

  try {
    let respuestaApi = await axios.post(`/finances/expenses`, DATA);
    if (respuestaApi.status !== 200) {
      statuModule.loadingApp = false;
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

async function exportarExcel(formato){
  try{
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
      hasInvoice: hasInvoice.value,
    };

    // Limpiar parámetros vacíos
    Object.keys(params).forEach((key) => {
      if (params[key] === null || params[key] === "" || params[key] === false) {
        if (key !== 'isDeductible' && key !== 'hasInvoice' && key !== 'status' && key !== 'formato') {
          delete params[key];
        }
      }
    });

    let respuestaApi = await axios.post(
      '/finances/expenses/exportar/excel',
      params,  // Tus parámetros como objeto
      {
        responseType: 'blob',
        headers: {
          'Content-Type': 'application/json',  // Asegura el envío correcto de los parámetros
        }
      }
    );

    console.log("res => ",respuestaApi)

    if(respuestaApi.status!=200){
      toast.success("Error al filtrar los datos")
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
    statuModule.loadingApp=false
  } catch (error) {
    console.error("Error al exportar los datos:", error);
    statuModule.loadingApp=false
  }

}

async function enviar(payload){
  try {
    statuModule.loadingApp=true
    let respuesApi=await axios.post("/finances/expenses/create-normal",payload)
    if(respuesApi.status==200 && payload.has_invoice==false){
      toast.success("El gasto se a guardado correctamente")
      cerrarModal(false)
      await actualizarTabla()
      statuModule.loadingApp=false
    }
    console.log("respuesta api gasto => ",respuesApi.data.data)
    let gasto=respuesApi.data.data

    if(payload.has_invoice==true){
      let data=new FormData()
      data.append("id",gasto.id)
      data.append("file_invoice",payload.file_factura)

      let config= {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      }

      let respuesApiFileUploaa=await axios.post("/finances/expenses/upload-file-invoice",data,config)
      if(respuesApiFileUploaa.status==200){
        toast.success("El archivo de la factura a sido guardado correctamente")
        cerrarModal(false)
        await actualizarTabla()
        statuModule.loadingApp=false
      }
    }

  } catch (error) {
    statuModule.loadingApp=false
    toast.error("Error al crear el gasto")
    console.log("error en el servidor => ",error)
    let errores={...error.response.data.data.errors}
    cargarErrores(errores)
  }
}

onMounted(async () => {
  formulario.user_id = 1;
  statuModule.categorias = await consultarCategorias();
  await actualizarTabla();
});
</script>
<template>
  <LoaderComponent :loadingApp="statuModule.loadingApp" />
  <div>
    <FiltrosGastos
      v-model:currency="currency"
      v-model:buscardor_filtro="buscardor_filtro"
      v-model:category_id_filtro="category_id_filtro"
      v-model:fechaDesde_filtro="fechaDesde_filtro"
      v-model:fechaHasta_filtro="fechaHasta_filtro"
      v-model:isDeductible="isDeductible"
      v-model:hasInvoice="hasInvoice"
      :categorias="statuModule.categorias"
      :loading="isLoadingFilters"
      :show-add-button="true"
      @export-excel="exportarExcel"
      @export-pdf="generaPdf"
      @clear="limpliarFiltros"
      @add="mostarModal"
    />
    <ExpenseFormDialoge
      type_of_expense="normal"
      :modal-formulario="modal.statu"
      :titulo="modal.titulo"
      :form-data="formulario"
      :form-error="formularioError"
      :categorias="statuModule.categorias"
      @modal-close="cerrarModal"
      @clear-error-form="limpiarErroresFormulario"
      @save="enviar"
    />
    <VCard title="Gastos">
      <VDivider />
      <ExpenseTable
        :items="statuModule.items"
        :total="statuModule.total"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :page="page"
        @update:options="updateTableOptions"
      />
    </VCard>
  </div>
</template>
