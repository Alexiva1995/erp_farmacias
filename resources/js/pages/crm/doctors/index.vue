<script setup lang="js">
import DoctorFormDialoge from "@/components/dialogs/DoctorFormDialoge.vue";
import DoctorFilters from "@/components/DoctorFilters.vue";
import DoctorTable from "@/components/DoctorTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import pdfDoctorsGenerator from "@/utils/pdfDoctorsGenerator";
import Swal from 'sweetalert2';
import { onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from "vue-router";

const route= useRouter()

const modal= reactive({
  statu:false,
  titulo:"Nuevo",
})

const statuModule= reactive({
  items:[],
  total:0,
  comapanies:[],
  specialties:[],
})

const formulario= reactive({
  id:null,
  identification:"",
  name:"",
  address:"",
  specialty_id:null,
})

const formularioError= reactive({
  id:"",
  identification:"",
  name:"",
  address:"",
  specialty_id:"",
})

const loading = ref(false)

const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref()
const orderBy = ref()


const buscardor_filtro= ref("");// nombre, identificación, direccion
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
  formulario.name=datos.name
  formulario.address=remplazarSiEsNullPor(datos.address)
  formulario.specialty_id=datos.specialty_id
}

function limpiarDatosFormulario(){
  formulario.id=null
  formulario.identification=""
  formulario.name=""
  formulario.address=""
  formulario.specialty_id=null
}

function limpiarErroresFormulario(){
  formularioError.id=""
  formularioError.identification=""
  formularioError.name=""
  formularioError.address=""
  formularioError.specialty_id=""
}

const isSubmitting = ref(false)

function cargarErrores(errores){
  formularioError.id = (errores.id) ? (Array.isArray(errores.id) ? errores.id.join(", ") : errores.id) : ""
  formularioError.identification = (errores.identification) ? (Array.isArray(errores.identification) ? errores.identification.join(", ") : errores.identification) : ""
  formularioError.name = (errores.name) ? (Array.isArray(errores.name) ? errores.name.join(", ") : errores.name) : ""
  formularioError.address = (errores.address) ? (Array.isArray(errores.address) ? errores.address.join(", ") : errores.address) : ""
  formularioError.specialty_id = (errores.specialty_id) ? (Array.isArray(errores.specialty_id) ? errores.specialty_id.join(", ") : errores.specialty_id) : ""
}

function mostarModal(){
  modal.statu=true
  modal.titulo="Nuevo Doctor"
}

async function actualizarTabla(){
  loading.value = true;
  let filtros={
    page:page.value,
    itemsPerPage:itemsPerPage.value,
    orderBy:orderBy.value,
    sortBy:sortBy.value,
    buscardor_filtro:buscardor_filtro.value,
    fechaDesde_filtro:fechaDesde_filtro.value,
    fechaHasta_filtro:fechaHasta_filtro.value,
  }
  let responseApi= await filtraDoctores(filtros)
  statuModule.items=responseApi.data
  statuModule.total=responseApi.total
  loading.value = false;
  return {...responseApi}
}


async function filtraDoctores(dataFiltro){
  let respuestaApi = await axios.post(`/crm/doctors/filtrar?page=${dataFiltro.page}`,dataFiltro)
  if(respuestaApi.status!=200){
    toast.success("Error al filtrar los datos")
  }
  // console.log("respues api => ",respuestaApi)

  return {...respuestaApi.data.data}
}

async function filtrarSinPaginar(dataFiltro){
  let respuestaApi = await axios.post(`/crm/doctors/filtrar-sin-paginar`,dataFiltro)
  if(respuestaApi.status!=200){
    toast.success("Error al filtrar los datos")
  }
  // console.log("respues api => ",respuestaApi)

  return [...respuestaApi.data.data]
}

async function fetchSpecialties(){
  try {
    const response = await axios.get("/crm/specialties")
    statuModule.specialties = response.data.data
  } catch (error) {
    console.error("Error fetching specialties:", error)
    toast.error("Error al cargar las especialidades")
  }
}


async function confirmarEliminar(payload){
  // alert(payload)

  const result = await Swal.fire({
    title: '¿Estás seguro?',
    text: "¡No podrás revertir la eliminación de este Doctor!",
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
    let respuesApi=await axios.delete(`/crm/doctors/${id}`)
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
  try {
    let respuesApi=await axios.post("/crm/doctors",data)
    if(respuesApi.status==200){
        toast.success("El cliente se ha guardado correctamente")
        cerrarModal(false)
        await actualizarTabla()
    }
  } catch (error) {
    toast.error("Error al crear la empresa")
    console.log("error en el servidor => ",error)
    let errores={...error.response.data.data.errors}
    cargarErrores(errores)
  }
}

async function actualizar(data){
  try {
    let config={
        headers: {
          'Content-Type': 'multipart/form-data',
        },
    }
    let respuesApi=await axios.post(`/crm/doctors/edit/${data.get("id")}`,data,config)
    if(respuesApi.status==200){
        toast.success("Se guardaron los cambios correctamente")
        cerrarModal(false)
        await actualizarTabla()
    }
  } catch (error) {
    toast.error("Error al guardar los cambios")
    console.log("error en el servidor => ",error)
    let errores={...error.response.data.data.errors}
    cargarErrores(errores)
  }
}

function mostarModoEdit(payload){
  let registro= statuModule.items.find(registro => registro.id==payload)
  modal.statu=true
  modal.titulo=registro.name
  insertarDatosAlFormulario({...registro})
}

const updateTableOptionsTable = options => {
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

function limpiarFiltros(){
  buscardor_filtro.value=""
  fechaDesde_filtro.value=""
  fechaHasta_filtro.value=""
}

let debounceTimer;
watch(
    [
      buscardor_filtro,
      fechaDesde_filtro,
      fechaHasta_filtro,
      page,
      itemsPerPage,
      orderBy,
      sortBy
  ],
  async () =>{
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(async () => {
      await actualizarTabla();
    }, 300);
  }
)

watch([buscardor_filtro, fechaDesde_filtro, fechaHasta_filtro], () => {
  page.value = 1;
});

async function exportarPdf(){
  let filtros={
      // filtros
      buscardor_filtro:buscardor_filtro.value,
      fechaDesde_filtro:fechaDesde_filtro.value,
      fechaHasta_filtro:fechaHasta_filtro.value,
  }
  let respuestaApi= await filtrarSinPaginar(filtros)
  console.log("respuesta => ",respuestaApi)

  if(respuestaApi.length==0){
    toast.info("No hay doctores para poder genera un reporte")
    return null;
  }

  pdfDoctorsGenerator(respuestaApi)
}

async function exportarExcel(formato){

  try{
      let params={
      buscardor_filtro:buscardor_filtro.value,
      fechaDesde_filtro:fechaDesde_filtro.value,
      fechaHasta_filtro:fechaHasta_filtro.value,
      formato,
    }

    let respuestaApi = await axios.get(`/crm/doctors/exportar/excel`,{
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
    let fileName = `doctors.${formato}`;
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

function remplazarSiEsNullPor(dato,por=""){
  return (dato==null)?por:dato
}



onMounted(async () => {
  await actualizarTabla()
  await fetchSpecialties()
})
</script>
<template>
  <div>
    <DoctorFilters
      v-model:buscador="buscardor_filtro"
      v-model:fechaDesde_filtro="fechaDesde_filtro"
      v-model:fechaHasta_filtro="fechaHasta_filtro"
      @clear="limpiarFiltros"
      @add-doctor="mostarModal"
      @export-pdf="exportarPdf"
      @export-excel="exportarExcel"
    />
    <DoctorFormDialoge
      :modal-formulario="modal.statu"
      :titulo="modal.titulo"
      :form-data="formulario"
      :form-error="formularioError"
      :specialties="statuModule.specialties"
      @modal-close="cerrarModal"
      @clear-error-form="limpiarErroresFormulario"
      @save="enviar"
    />
    <VCard title="Doctores">
      <VDivider />
      <DoctorTable
        :items="statuModule.items"
        :total="statuModule.total"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :page="page"
        @edit="mostarModoEdit"
        @delete="confirmarEliminar"
        @update:options="updateTableOptionsTable"
      />
    </VCard>
  </div>
</template>
