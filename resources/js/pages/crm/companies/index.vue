<script setup lang="js">

import CompanyFilters from "@/components/CompanyFilters.vue";
import CompanyTable from "@/components/CompanyTable.vue";
import CompanyFormDialoge from "@/components/dialogs/CompanyFormDialoge.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import pdfCompaniesGenerator from "@/utils/pdfCompaniesGenerator";
import Swal from 'sweetalert2';
import { onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from "vue-router";

const route = useRouter()
const isSubmitting = ref(false)

const modal = reactive({
  statu: false,
  titulo: "Nuevo",
})

const statuModule= reactive({
  items:[],
  total:0,
  comapanies:[],
})

const formulario= reactive({
  id:null,
  identification:"",
  type_company:"",
  name:"",
  address:"",
})

const formularioError= reactive({
  id:"",
  identification:"",
  type_company:"",
  name:"",
  address:"",
})

const loading = ref(false)

const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref()
const orderBy = ref()


const buscardor_filtro= ref("");// nombre, identificación, direccion
const tipo_empresa_filtro= ref("");
const fechaDesde_filtro= ref("");
const fechaHasta_filtro= ref("");


function cerrarModal(payload){
  modal.statu=payload
  limpiarDatosFormulario()
  limpiarErroresFormulario()
}

function insertarDatosAlFormulario(datos){
  formulario.id=datos.id
  formulario.identification=datos.identification
  formulario.type_company=datos.type_company
  formulario.name=datos.name
  formulario.address=remplazarSiEsNullPor(datos.address)
}

function limpiarDatosFormulario(){
  formulario.id=null
  formulario.identification=""
  formulario.type_company=""
  formulario.name=""
  formulario.address=""
}

function limpiarErroresFormulario(){
  formularioError.id=""
  formularioError.identification=""
  formularioError.type_company=""
  formularioError.name=""
  formularioError.address=""
}

function cargarErrores(errores){
  formularioError.id=(errores.id)?errores.id.join(", "):""
  formularioError.identification=(errores.identification)?errores.identification.join(", "):""
  formularioError.type_company=(errores.type_company)?errores.type_company.join(", "):""
  formularioError.name=(errores.name)?errores.name.join(", "):""
  formularioError.address=(errores.address)?errores.address.join(", "):""
}

function mostarModal(){
  modal.statu=true
  modal.titulo="Nueva Empresa"
}

async function actualizarTabla(){
  loading.value = true;
  let filtros={
    page:page.value,
    itemsPerPage:itemsPerPage.value,
    orderBy:orderBy.value,
    sortBy:sortBy.value,
    buscardor_filtro:buscardor_filtro.value,
    tipo_empresa_filtro:tipo_empresa_filtro.value,
    fechaDesde_filtro:fechaDesde_filtro.value,
    fechaHasta_filtro:fechaHasta_filtro.value,
  }
  let responseApi= await filtraCompany(filtros)
  statuModule.items=responseApi.data
  statuModule.total=responseApi.total
  loading.value = false;
  return {...responseApi}
}


async function confirmarEliminar(payload){
  // alert(payload)

  const result = await Swal.fire({
    title: '¿Estás seguro?',
    text: "¡No podrás revertir la eliminación de esta Empresa!",
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
  });

  if (result.isConfirmed) {
    // alert("ula")
    await eliminar(payload)
  }
}

async function eliminar(id){
  try {
    let respuesApi=await axios.delete(`/crm/companies/${id}`)
    if(respuesApi.status==200){
        toast.success("El registro ha sido eliminado correctamente")
        cerrarModal(false)
        await actualizarTabla()
    }
  } catch (error) {
    toast.error("Error al eliminar el registro")
    console.log("error en el servidor => ",error)
    let errores={...error.response.data.data.errors}
    cargarErrores(errores)
  }
}

function enviar(payload){
  console.log("data id => ",payload.get("id"))
  if(formulario.id==null){
    crear(payload)
  }
  else{
    actualizar(payload)
  }
}


async function crear(data){
  isSubmitting.value = true
  try {
    let respuesApi=await axios.post("/crm/companies",data)
    if(respuesApi.status==200 || respuesApi.status==201){
        toast.success("La empresa se ha guardado correctamente")
        cerrarModal(false)
        await actualizarTabla()
    }
  } catch (error) {
    let msg = error?.response?.data?.message || "Error al crear la empresa"
    toast.error(msg)
    let errores = error?.response?.data?.data?.errors || error?.response?.data?.errors || {}
    cargarErrores(errores)
  } finally {
    isSubmitting.value = false
  }
}

async function actualizar(data){
  isSubmitting.value = true
  try {
    let config={
        headers: {
          'Content-Type': 'multipart/form-data',
        },
    }
    let respuesApi=await axios.post(`/crm/companies/edit/${data.get("id")}`,data,config)
    if(respuesApi.status==200){
        toast.success("Se guardaron los cambios correctamente")
        cerrarModal(false)
        await actualizarTabla()
    }
  } catch (error) {
    let msg = error?.response?.data?.message || "Error al guardar los cambios de la empresa"
    toast.error(msg)
    let errores = error?.response?.data?.data?.errors || error?.response?.data?.errors || {}
    cargarErrores(errores)
  } finally {
    isSubmitting.value = false
  }
}

function mostarModoEdit(payload){
  let registro= statuModule.items.find(registro => registro.id==payload)
  modal.statu=true
  modal.titulo=registro.name
  insertarDatosAlFormulario({...registro})
}

async function filtraCompany(dataFiltro){
  let respuestaApi = await axios.post(`/crm/companies/filtrar?page=${dataFiltro.page}`,dataFiltro)
  if(respuestaApi.status!=200){
    toast.success("Error al filtrar los datos")
  }
  // console.log("respues api => ",respuestaApi)

  return {...respuestaApi.data.data}
}

watch(
    [
      buscardor_filtro,
      tipo_empresa_filtro,
      fechaDesde_filtro,
      fechaHasta_filtro,
      page,
      itemsPerPage,
      orderBy,
      sortBy
  ],
  async () =>{
    // console.log("uwu")
    await actualizarTabla()
  }
)

const updateTableOptionsTableCompany = options => {
  // console.log(options)
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

function limpiarFiltros(){
  buscardor_filtro.value=""
  tipo_empresa_filtro.value=""
  fechaDesde_filtro.value=""
  fechaHasta_filtro.value=""
}

async function filtrarSinPaginar(dataFiltro){
  let respuestaApi = await axios.post(`/crm/companies/filtrar-sin-paginar`,dataFiltro)
  if(respuestaApi.status!=200){
    toast.success("Error al filtrar los datos")
  }
  // console.log("respues api => ",respuestaApi)

  return [...respuestaApi.data.data]
}

async function exportarPdf(){
    let filtros={
      // filtros
      buscardor_filtro:buscardor_filtro.value,
      tipo_empresa_filtro:tipo_empresa_filtro.value,
      fechaDesde_filtro:fechaDesde_filtro.value,
      fechaHasta_filtro:fechaHasta_filtro.value,
  }
  let respuestaApi= await filtrarSinPaginar(filtros)
  console.log("respuesta => ",respuestaApi)

  if(respuestaApi.length==0){
    toast.info("No hay empresas para poder genera un reporte")
    return null;
  }

  pdfCompaniesGenerator(respuestaApi)

}

async function exportarExcel(formato){

  try{
      let params={
      buscardor_filtro:buscardor_filtro.value,
      tipo_empresa_filtro:tipo_empresa_filtro.value,
      fechaDesde_filtro:fechaDesde_filtro.value,
      fechaHasta_filtro:fechaHasta_filtro.value,
      formato,
    }

    let respuestaApi = await axios.get(`/crm/companies/exportar/excel`,{
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
    let fileName = `companies.${formato}`;
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

function irHaVerClientesEmpresa(payload){
  // alert(payload)
  route.push(`/crm/companies/${payload}`)
}


function remplazarSiEsNullPor(dato,por=""){
  return (dato==null)?por:dato
}

onMounted(async () => {
  await actualizarTabla()
})
</script>
<template>
  <div>
    <CompanyFilters
      v-model:buscador="buscardor_filtro"
      v-model:tipo_empresa_filtro="tipo_empresa_filtro"
      v-model:fechaDesde_filtro="fechaDesde_filtro"
      v-model:fechaHasta_filtro="fechaHasta_filtro"
      @clear="limpiarFiltros"
      @add-company="mostarModal"
      @export-pdf="exportarPdf"
      @export-excel="exportarExcel"
    />
    <CompanyFormDialoge
      :modal-formulario="modal.statu"
      :titulo="modal.titulo"
      :form-data="formulario"
      :form-error="formularioError"
      @modal-close="cerrarModal"
      @clear-error-form="limpiarErroresFormulario"
      @save="enviar"
    />
    <VCard title="Empresas">
      <VDivider />
      <CompanyTable
        :items="statuModule.items"
        :total="statuModule.total"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :page="page"
        @edit="mostarModoEdit"
        @delete="confirmarEliminar"
        @ver-clientes="irHaVerClientesEmpresa"
        @update:options="updateTableOptionsTableCompany"
      />
    </VCard>
  </div>
</template>
