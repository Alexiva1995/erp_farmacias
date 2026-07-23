<script setup lang="js">
import ClientsFilters from "@/components/ClientsFilters.vue";
import ClientFormDialoge from "@/components/dialogs/ClientFormDialoge.vue";
import ClientStatsModal from "@/components/dialogs/ClientStatsModal.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import pdfClienstGenerator from "@/utils/pdfClienstGenerator";
import Swal from 'sweetalert2';
import { onMounted, reactive, ref } from 'vue';

const isStatsModalVisible = ref(false);
const statsClientId = ref(null);

function openStatsModal(clientId) {
  statsClientId.value = clientId;
  isStatsModalVisible.value = true;
}

const statuModule= reactive({
  items:[],
  itemsClientes:[],
  totalClientes:0,
  comapanies:[],
})

const modal= reactive({
  statu:false,
  titulo:"Nuevo"
})

const formulario= reactive({
  id:null,
  identification:"",
  identification_type:"",
  name:"",
  last_name:"",
  email:"",
  phone:"",
  address:"",
  birthdate:"",
  company_id:"",
  is_spe: false,
})
const formularioError= reactive({
  id:"",
  identification:"",
  identification_type:"",
  name:"",
  last_name:"",
  email:"",
  phone:"",
  address:"",
  birthdate:"",
  company_id:"",
  is_spe: "",
})

const loading = ref(false)

const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref('id')
const orderBy = ref('desc')

const buscardor_filtro= ref("");
const tipo_identificacion_filtro= ref(null);
const company_id_filtro= ref("");
const client_type_filtro= ref(null);
const fechaDesde_filtro= ref("");
const fechaHasta_filtro= ref("");
const has_phone_filtro= ref("");


function mostarModal(){
  modal.statu=true
  modal.titulo="Nuevo Cliente"
}

function mostarModoEdit(payload){
  // console.log("items => ",statuModule.items)
  // console.log("payload => ",payload)
  let cliente= statuModule.items.find(client => client.id==payload)
  // console.log("DATA => ",cliente)
  modal.statu=true
  modal.titulo=`${cliente.name} ${cliente.last_name}`



  insertarDatosAlFormulario({...cliente})
}

function cerrarModal(payload){
  modal.statu=payload
  limpiarDatosFormulario()
  limpiarErroresFormulario()
}

function insertarDatosAlFormulario(datos) {
  formulario.id = datos.id
  formulario.identification = datos.identification
  formulario.identification_type = datos.identification_type
  formulario.name = datos.name
  formulario.last_name = datos.last_name
  formulario.email = (datos.email == null) ? "" : datos.email
  formulario.phone = datos.phone
  formulario.address = datos.address
  formulario.birthdate = datos.birthdate
  formulario.company_id = datos.company_id
  formulario.is_spe = Boolean(datos.is_spe) || datos.is_spe === 1 || datos.is_spe === '1'
}

function limpiarDatosFormulario() {
  formulario.id = null
  formulario.identification = ""
  formulario.identification_type = ""
  formulario.name = ""
  formulario.last_name = ""
  formulario.email = ""
  formulario.phone = ""
  formulario.address = ""
  formulario.birthdate = null
  formulario.company_id = ""
  formulario.is_spe = false
}

function limpiarErroresFormulario() {
  formularioError.id = ""
  formularioError.identification = ""
  formularioError.identification_type = ""
  formularioError.name = ""
  formularioError.last_name = ""
  formularioError.email = ""
  formularioError.phone = ""
  formularioError.address = ""
  formularioError.birthdate = ""
  formularioError.company_id = ""
  formularioError.is_spe = ""
}

async function consultAll(){
  let res = await axios.get("/crm/clients")
  if(res.status!=200){
    console.error("error => ",res)
    return []
  }
  return [...res.data.data]

}

async function consultAllcomapanies(){
  let res = await axios.get("/crm/companies")
  if(res.status!=200){
    console.error("error => ",res)
    return []
  }

  return [...res.data.data]

}

function enviar(payload){
  // console.log("data id => ",payload.get("id"))
  if(formulario.id==null){
    crear(payload)
  }
  else{
    actualizar(payload)
  }
}

const isSubmitting = ref(false)

async function crear(data){
  isSubmitting.value = true
  try {
    let respuesApi = await axios.post("/crm/clients", data)
    if (respuesApi.status === 200 || respuesApi.status === 201) {
      toast.success("El cliente se ha guardado correctamente")
      cerrarModal(false)
      await actualizarTabla()
    }
  } catch (error) {
    let msg = error?.response?.data?.message || "Error al crear el cliente"
    toast.error(msg)
    console.error("Error en el servidor =>", error)
    let errores = error?.response?.data?.data?.errors || error?.response?.data?.errors || {}
    cargarErrores(errores)
  } finally {
    isSubmitting.value = false
  }
}

async function actualizar(data){
  isSubmitting.value = true
  try {
    let config = {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    }
    let respuesApi = await axios.post(`/crm/clients/edit/${data.get("id")}`, data, config)
    if (respuesApi.status === 200) {
      toast.success("Se guardaron los cambios correctamente")
      cerrarModal(false)
      await actualizarTabla()
    }
  } catch (error) {
    let msg = error?.response?.data?.message || "Error al guardar los cambios del cliente"
    toast.error(msg)
    console.error("Error en el servidor =>", error)
    let errores = error?.response?.data?.data?.errors || error?.response?.data?.errors || {}
    cargarErrores(errores)
  } finally {
    isSubmitting.value = false
  }
}

function cargarErrores(errores) {
  formularioError.id = (errores.id) ? (Array.isArray(errores.id) ? errores.id.join(", ") : errores.id) : ""
  formularioError.identification = (errores.identification) ? (Array.isArray(errores.identification) ? errores.identification.join(", ") : errores.identification) : ""
  formularioError.identification_type = (errores.identification_type) ? (Array.isArray(errores.identification_type) ? errores.identification_type.join(", ") : errores.identification_type) : ""
  formularioError.name = (errores.name) ? (Array.isArray(errores.name) ? errores.name.join(", ") : errores.name) : ""
  formularioError.last_name = (errores.last_name) ? (Array.isArray(errores.last_name) ? errores.last_name.join(", ") : errores.last_name) : ""
  formularioError.email = (errores.email) ? (Array.isArray(errores.email) ? errores.email.join(", ") : errores.email) : ""
  formularioError.phone = (errores.phone) ? (Array.isArray(errores.phone) ? errores.phone.join(", ") : errores.phone) : ""
  formularioError.address = (errores.address) ? (Array.isArray(errores.address) ? errores.address.join(", ") : errores.address) : ""
  formularioError.birthdate = (errores.birthdate) ? (Array.isArray(errores.birthdate) ? errores.birthdate.join(", ") : errores.birthdate) : ""
  formularioError.company_id = (errores.company_id) ? (Array.isArray(errores.company_id) ? errores.company_id.join(", ") : errores.company_id) : ""
  formularioError.is_spe = (errores.is_spe) ? (Array.isArray(errores.is_spe) ? errores.is_spe.join(", ") : errores.is_spe) : ""
}

async function actualizarTabla(){
  loading.value = true;
  try {
    let filtroNaturales = {
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      orderBy: orderBy.value,
      sortBy: sortBy.value,
      buscardor_filtro: buscardor_filtro.value,
      tipo_identificacion_filtro: tipo_identificacion_filtro.value,
      company_id: company_id_filtro.value,
      client_type: client_type_filtro.value,
      fechaDesde_filtro: fechaDesde_filtro.value,
      fechaHasta_filtro: fechaHasta_filtro.value,
      has_phone: has_phone_filtro.value,
    }
    let respuestaApiNaturles = await filtrar(filtroNaturales)
    statuModule.itemsClientes = respuestaApiNaturles.data || []
    statuModule.totalClientes = respuestaApiNaturles.total || 0
    statuModule.items = [...(respuestaApiNaturles.data || [])]
  } catch (err) {
    console.error("Error cargando tabla de clientes:", err)
  } finally {
    loading.value = false;
  }
}

async function actualizarTablaTablaClientes(){
  await actualizarTabla()
}

async function confirmarEliminarCliente(payload){
  // alert(payload)

  const result = await Swal.fire({
    title: '¿Estás seguro?',
    text: '¡No podrás revertir la eliminación de este cliente!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, ¡Eliminar!',
    cancelButtonText: 'No, ¡Cancelar!',
    buttonsStyling: false,
    customClass: {
      confirmButton: 'v-btn v-btn--elevated v-theme--light bg-error v-btn--density-default v-btn--size-default v-btn--variant-elevated',
      cancelButton: 'v-btn v-theme--light text-secondary v-btn--density-default v-btn--size-default v-btn--variant-outlined mx-2'
    },
    reverseButtons: true,
    // title: '¿Estás seguro?',
    // text: "¡No podrás revertir la eliminación de este cliente!",
    // icon: 'warning',
    // showCancelButton: true,
    // confirmButtonText: '<span style="color: white;">Sí, ¡eliminar!</span>',
    // cancelButtonText: '<span style="color: white;">Cancelar</span>',
    // customClass: {
    //   confirmButton: 'red-accent-3',  // Clase para el botón de confirmar
    //   cancelButton: 'btn-cancel',   // Clase para el botón de cancelar
    // },
    // color: '#111',
    // confirmButtonColor: '#7367f0',
    // cancelButtonColor: '#d33',
    // background: '#2f3349',
  });

  if (result.isConfirmed) {
    await eliminarCliente(payload)
  }
}


async function eliminarCliente(id){
  try {
    let respuesApi=await axios.delete(`/crm/clients/${id}`)
    if(respuesApi.status==200){
        toast.success("El cliente se ha eliminado correctamente")
        cerrarModal(false)
        await actualizarTabla()
    }
  } catch (error) {
    toast.error("Error al eliminar el cliente")
    console.log("error en el servidor => ",error)
    let errores={...error.response.data.data.errors}
    cargarErrores(errores)
  }
}

async function handleBulkCleanup() {
  const result = await Swal.fire({
    title: '¿Corregir teléfonos inválidos?',
    html: `
      <div class="text-start">
        <p>Esta acción buscará y <b>reseteará</b> los números de teléfono basura en toda la base de datos.</p>
        <ul class="mt-2">
          <li><b>Ejemplos:</b> "04", "0424", "00000000", "12345678".</li>
          <li><b>Resultado:</b> Los clientes permanecerán en el sistema, pero su teléfono quedará como <i>"Sin Registrar"</i>.</li>
        </ul>
        <p class="text-info mt-4 font-weight-bold">Nota: No se eliminará ningún cliente.</p>
      </div>
    `,
    icon: 'info',
    showCancelButton: true,
    confirmButtonText: 'Sí, corregir base de datos',
    cancelButtonText: 'Cancelar',
    buttonsStyling: false,
    customClass: {
      confirmButton: 'v-btn v-btn--elevated v-theme--light bg-info v-btn--density-default v-btn--size-default v-btn--variant-elevated',
      cancelButton: 'v-btn v-theme--light text-secondary v-btn--density-default v-btn--size-default v-btn--variant-outlined mx-2'
    },
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    loading.value = true;
    try {
      const response = await axios.post('/crm/clients/bulk-cleanup');
      if (response.status === 200) {
        const count = response.data.data.count;
        toast.success(`Limpieza completada: ${count} teléfonos corregidos.`);
        await actualizarTabla();
      }
    } catch (error) {
      console.error("Error en limpieza masiva:", error);
      toast.error(error.response?.data?.message || "Hubo un error al intentar corregir los teléfonos.");
    } finally {
      loading.value = false;
    }
  }
}

async function handleBulkCneVerification() {
  const result = await Swal.fire({
    title: '¿Verificar identidades vía CNE?',
    html: `
      <div class="text-start">
        <p>Esta acción consultará el Registro Electoral de Venezuela para <b>actualizar nombres y apellidos</b> de clientes con cédula (V-).</p>
        <ul class="mt-2">
          <li><b>Proceso:</b> Se procesarán los 100 registros más antiguos para evitar saturar el servicio externo.</li>
          <li><b>Automatización:</b> El sistema también procesará un lote automáticamente cada 2 horas (100 registros).</li>
        </ul>
        <p class="text-success mt-4 font-weight-bold">Nota: Esto garantiza que los nombres en el CRM coincidan con los datos oficiales.</p>
      </div>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, verificar y corregir',
    cancelButtonText: 'Cancelar',
    buttonsStyling: false,
    customClass: {
      confirmButton: 'v-btn v-btn--elevated v-theme--light bg-success v-btn--density-default v-btn--size-default v-btn--variant-elevated',
      cancelButton: 'v-btn v-theme--light text-secondary v-btn--density-default v-btn--size-default v-btn--variant-outlined mx-2'
    },
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    loading.value = true;
    try {
      toast.info("Iniciando verificación masiva... por favor espera.");
      const response = await axios.post('/crm/clients/bulk-cne-verify');
      if (response.status === 200) {
        const { updated, not_found } = response.data.data;
        toast.success(`Proceso completado: ${updated} registros actualizados. (${not_found} no encontrados)`);
        await actualizarTabla();
      }
    } catch (error) {
      console.error("Error en verificación masiva CNE:", error);
      toast.error(error.response?.data?.message || "Hubo un error al intentar consultar el CNE.");
    } finally {
      loading.value = false;
    }
  }
}

async function handleIndividualCneVerification(client) {
  if (client.identification_type !== 'V-') {
    toast.warning("La verificación CNE solo está disponible para cédulas venezolanas (V-).");
    return;
  }

  loading.value = true;
  try {
    toast.info(`Consultando CNE para CI: ${client.identification}...`);
    const response = await axios.post('/crm/clients/cne-verify', {
      identification: client.identification,
      client_id: client.id
    });

    if (response.status === 200) {
      toast.success("Datos actualizados correctamente.");
      await actualizarTabla();
    }
  } catch (error) {
    console.error("Error en verificación individual CNE:", error);
    const mensaje = error.response?.data?.message || "No se pudo verificar la identidad.";
    toast.error(mensaje);
  } finally {
    loading.value = false;
  }
}

const updateTableOptions = options => {
  // console.log(options)
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

watch(
    [
      buscardor_filtro,
      tipo_identificacion_filtro,
      fechaDesde_filtro,
      fechaHasta_filtro,
      has_phone_filtro,
      company_id_filtro,
      client_type_filtro,
      page,
      itemsPerPage,
      orderBy,
      sortBy
  ],
  async () =>{
    await actualizarTablaTablaClientes()
  }
)

watch(
  () => formulario.identification_type,
  (value) => {
    if(value=="J-"){
      formulario.last_name=""
      formulario.company_id=""
    }
  }
)


function limpiarFiltros(){
  buscardor_filtro.value=""
  tipo_identificacion_filtro.value=""
  company_id_filtro.value=""
  client_type_filtro.value=null
  fechaDesde_filtro.value=""
  fechaHasta_filtro.value=""
  has_phone_filtro.value=""
}

async function filtrar(dataFiltro){
  // let datosFiltros={
  //   page:dataFiltro.page,
  //   itemsPerPage:dataFiltro.itemsPerPage,
  //   orderBy:dataFiltro.orderBy,
  //   sortBy:dataFiltro.sortBy,
  //   tipo:dataFiltro.tipo,
  // }
  let respuestaApi = await axios.post(`/crm/clients/filtrar?page=${dataFiltro.page}`,dataFiltro)
  if(respuestaApi.status!=200){
    toast.success("Error al filtrar los datos")
  }
  // console.log("respues api => ",respuestaApi)

  return {...respuestaApi.data.data}
}

async function filtrarSinPaginar(dataFiltro){
  let respuestaApi = await axios.post(`/crm/clients/filtrar-sin-paginar`,dataFiltro)
  if(respuestaApi.status!=200){
    toast.success("Error al filtrar los datos")
  }
  // console.log("respues api => ",respuestaApi)

  return [...respuestaApi.data.data]
}


async function exportarPdf(){
    let filtros={
      buscardor_filtro:buscardor_filtro.value,
      tipo_identificacion_filtro:tipo_identificacion_filtro.value,
      company_id:company_id_filtro.value,
      client_type:client_type_filtro.value,
      fechaDesde_filtro:fechaDesde_filtro.value,
      fechaHasta_filtro:fechaHasta_filtro.value,
      has_phone: has_phone_filtro.value,
  }
  let respuestaApi= await filtrarSinPaginar(filtros)
  console.log("respuesta => ",respuestaApi)

  if(respuestaApi.length==0){
    toast.info("No hay clientes para poder genera un reporte")
    return null;
  }

  pdfClienstGenerator(respuestaApi)

}

async function exportarExcel(formato){

  try{
      let params={
      buscardor_filtro:buscardor_filtro.value,
      tipo_identificacion_filtro:tipo_identificacion_filtro.value,
      company_id:company_id_filtro.value,
      client_type:client_type_filtro.value,
      fechaDesde_filtro:fechaDesde_filtro.value,
      fechaHasta_filtro:fechaHasta_filtro.value,
      has_phone: has_phone_filtro.value,
      formato,
    }

    let respuestaApi = await axios.get(`/crm/clients/exportar/excel`,{
      params,
      responseType: "blob",
    })

    console.log("res => ",respuestaApi)

    if(respuestaApi.status!=200){
      toast.success("Error al filtrar los datos")
    }
    const url = window.URL.createObjectURL(new Blob([respuestaApi.data]));
    const link = document.createElement("a");
    link.href = url;

    const contentDisposition = respuestaApi.headers["content-disposition"];
    let fileName = `clients.${formato}`;
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
  }

}



onMounted(async () => {
  await actualizarTabla()

  let responseComponies = await consultAllcomapanies()
  statuModule.comapanies=[...responseComponies]
})
</script>
<template>
  <div>
    <ClientsFilters
      v-model:buscador="buscardor_filtro"
      v-model:tipo_identificacion_filtro="tipo_identificacion_filtro"
      v-model:company_id_filtro="company_id_filtro"
      v-model:client_type_filtro="client_type_filtro"
      v-model:fechaDesde_filtro="fechaDesde_filtro"
      v-model:fechaHasta_filtro="fechaHasta_filtro"
      v-model:has_phone_filtro="has_phone_filtro"
      :companies="statuModule.comapanies"
      @clear="limpiarFiltros"
      @add-client="mostarModal"
      @export-pdf="exportarPdf"
      @export-excel="exportarExcel"
      @bulk-cleanup="handleBulkCleanup"
      @bulk-cne-verify="handleBulkCneVerification"
    />
    <ClientFormDialoge
      :companies="statuModule.comapanies"
      :modal-formulario="modal.statu"
      :titulo="modal.titulo"
      :form-data="formulario"
      :form-error="formularioError"
      @modal-close="cerrarModal"
      @clear-error-form="limpiarErroresFormulario"
      @save="enviar"
    />
    <VCard title="Clientes">
      <VDivider />
      <ClientTable
        :clients="statuModule.itemsClientes"
        :total-clients="statuModule.totalClientes"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :page="page"
        :sort-by="sortBy"
        :order-by="orderBy"
        @edit="mostarModoEdit"
        @delete="confirmarEliminarCliente"
        @view-stats="openStatsModal"
        @update:options="updateTableOptions"
        @verify-cne="handleIndividualCneVerification"
      />
    </VCard>

    <ClientStatsModal
      v-model="isStatsModalVisible"
      :client-id="statsClientId"
    />
  </div>
</template>
