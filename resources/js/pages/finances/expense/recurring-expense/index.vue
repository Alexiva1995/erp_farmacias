<script setup lang="js">
import ExpenseFormDialoge from '@/components/dialogs/ExpenseFormDialoge.vue';
import FiltrosGastoRecurrente from '@/components/FiltrosGastoRecurrente.vue';

// import axios from "@/plugins/axios";
// import { toast } from "@/plugins/sweetalert";
// import Swal from 'sweetalert2';
import { onMounted, reactive, watch } from 'vue';
// import { useRouter } from "vue-router";

// const route= useRouter()

const modal= reactive({
  statu:false,
  titulo:"Nuevo",
})

const statuModule= reactive({
  items:[],
  total:0,
  comapanies:[],
})

const formulario= reactive({
  id:null,
  category_id:"",
  amount:"",
  amount_usd:"",
  currency:"",
  has_invoice:false,
  is_deductible:false,
  expense_date:"",
  user_id:"",
  count:"",
})

const formularioError= reactive({
  id:"",
  category_id:"",
  amount:"",
  amount_usd:"",
  currency:"",
  has_invoice:"",
  is_deductible:"",
  expense_date:"",
  user_id:"",
  count:"",
})

const buscardor_filtro= ref("");// nombre, identificación, direccion

const loading = ref(false)

const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref()
const orderBy = ref()

function insertarDatosAlFormulario(datos){
  formulario.id=datos.id
  formulario.category_id=datos.category_id
  formulario.amount=datos.amount
  formulario.amount_usd=datos.amount_usd
  formulario.currency=datos.currency
  formulario.has_invoice=datos.has_invoice
  formulario.is_deductible=datos.is_deductible
  formulario.expense_date=datos.expense_date
  formulario.user_id=datos.user_id
  formulario.count=datos.count
}

function limpiarDatosFormulario(){
  formulario.id=null
  formulario.category_id=""
  formulario.amount=""
  formulario.amount_usd=""
  formulario.currency=""
  formulario.has_invoice=false
  formulario.is_deductible=false
  formulario.expense_date=""
  formulario.user_id=""
  formulario.count=""
}

function limpiarErroresFormulario(){
  formularioError.id=""
  formularioError.category_id=""
  formularioError.amount=""
  formularioError.amount_usd=""
  formularioError.currency=""
  formularioError.has_invoice=false
  formularioError.is_deductible=false
  formularioError.expense_date=""
  formularioError.user_id=""
  formularioError.count=""
}

function cargarErrores(errores){
  formularioError.id=(errores.id)?errores.id.join(", "):""
  formularioError.category_id=(errores.identification)?errores.identification.join(", "):""
  formularioError.amount=(errores.identification)?errores.identification.join(", "):""
  formularioError.amount_usd=(errores.identification)?errores.identification.join(", "):""
  formularioError.currency=(errores.identification)?errores.identification.join(", "):""
  formularioError.has_invoice=(errores.identification)?errores.identification.join(", "):""
  formularioError.is_deductible=(errores.identification)?errores.identification.join(", "):""
  formularioError.expense_date=(errores.identification)?errores.identification.join(", "):""
  formularioError.user_id=(errores.identification)?errores.identification.join(", "):""
  formularioError.count=(errores.identification)?errores.identification.join(", "):""
}

function mostarModal(){
  modal.statu=true
  modal.titulo="Nuevo Gasto"
}

function cerrarModal(payload){
  modal.statu=payload
  limpiarDatosFormulario()
  limpiarErroresFormulario()
}



watch(
    [
      buscardor_filtro,
      page,
      itemsPerPage,
      sortBy,
      orderBy
  ],
  async () =>{
    console.log("uwu")
  }
)

function enviar(){
  alert("hola")
}

onMounted(async () => {

})
</script>
<template>
  <div>
    <FiltrosGastoRecurrente @add="mostarModal" />
    <ExpenseFormDialoge
      :modal-formulario="modal.statu"
      :titulo="modal.titulo"
      :form-data="formulario"
      :form-error="formularioError"
      @modal-close="cerrarModal"
      @clear-error-form="limpiarErroresFormulario"
      @save="enviar"
    />
  </div>
</template>
