<script setup>
import OrderProductsTable from "@/components/OrderProductsTable.vue";
import OrderFilters from "@/components/OrderFilters.vue";
import OrderClienteCard from "@/components/cards/OrderClienteCard.vue";
import RegisterClientModal from "@/components/dialogs/ClientFormDialoge.vue";
import axios from "@/plugins/axios";
import { onMounted, ref, watch } from "vue";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";


const products = ref([]);
const totalProduct = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref()

const filterSearchQuery = ref("");
const selectedLaboratory = ref(null);
const selectedOrigin = ref(null);
const stockStatusFilter = ref(null);

const laboratories = ref([]);
const origins = ref([]);
const isLoadingFilters = ref(false);

const clientIdentification = ref('');
const showRegisterClientModal = ref(false);
const selectedClient = ref(null)

const newClientFormData = ref({
  id: null,
  identification_type: '',
  identification: '',
  name: '',
  last_name: '',
  email: '',
  phone: '',
  birthdate: '',
  company_id: null,
  address: '',
});

const newClientFormErrors = reactive({
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
})

const companies = ref([]);

const fetchProducts = async () => {
  loading.value = true;
  const params = {
    q: filterSearchQuery.value,
    laboratoryId: selectedLaboratory.value,
    originId: selectedOrigin.value,
    ...(stockStatusFilter.value !== null && {
      hasStock: stockStatusFilter.value,
    }),
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );

  try {
    const response = await axios.get("/tpv/order", { params });
    products.value = response.data.data;
    totalProduct.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los productos:", error);
    toast.error("Error al obtener los productos.");
  } finally {
    loading.value = false;
  }
};


const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse, originResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
    ]);
    laboratories.value = labResponse.data;
    origins.value = originResponse.data;
  } catch (error) {
    console.error("Error al cargar opciones de los selects:", error);
    toast.error("No se pudieron cargar los filtros.");
  } finally {
    isLoadingFilters.value = false;
  }
};

let debounceTimer;
watch(
  [
    page,
    itemsPerPage,
    sortBy,
    orderBy,
    filterSearchQuery,
    selectedLaboratory,
    selectedOrigin,
    stockStatusFilter,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProducts(), 300);
  },
  { deep: true }
);

const consultAllcomapanies = async () => {
  const companiesResponse = await axios.get("/crm/companies");
  companies.value = companiesResponse.data.data;
};

onMounted(() => {
  fetchSelectOptions();
  fetchProducts();
  consultAllcomapanies()
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};


const addProductToQuotation = async ({ productId, quantity }) => {
};

const handleClearFilters = () => {
  filterSearchQuery.value = "";
  selectedLaboratory.value = null;
  selectedOrigin.value = null;
  stockStatusFilter.value = null;
  sortBy.value = undefined;
  orderBy.value = undefined;
};

watch(
  [filterSearchQuery, selectedLaboratory, selectedOrigin, stockStatusFilter],
  () => {
    page.value = 1;
  }
);


const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};

const verifyClient = async (identification) => {
clientIdentification.value = identification;
  if (!identification) {
    toast.warning("Por favor, ingrese un número de identificación.");
    return;
  }
  try {
    const response = await axios.get(`/tpv/order/client/${identification}`);
    const responseData = response.data.data;
    if(responseData.found === false){
        toast.info("Cliente no encontrado. Por favor, regístrelo.");
         newClientFormData.value = {
        ...newClientFormData.value,
        identification: identification,
      };
        showRegisterClientModal.value = true;
    }else{
      const clientData = response.data.data.client;
      selectedClient.value = clientData;
      toast.success(`Cliente ${clientData.name} ${clientData.last_name} encontrado.`);
      if(responseData.has_pending_credits){
        confirmacionPagoCredit(clientData.id);
      }else{
        addOrden(clientData.id)
      }

    }
  }catch (error) {
      console.error("Error al verificar cliente:", error);
      toast.error("Error al verificar el cliente.");
  }
};


const confirmacionPagoCredit = async (id) => {
 const result = await Swal.fire({
    text: "¡Desea cancelar el crédito pendiente!",
    icon: "warning",
    showCancelButton: false,
    showDenyButton: true,
    denyButtonText: "No",
    confirmButtonText: "Si",
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    
  }else if (result.isDenied){
      addOrden(id)
  }

};


const addOrden = async (id) => {
    console.log(id);
    const params = {
      client_id: id,
    };
    try {
      const response = await axios.get("/tpv/order/add",params);
    }catch (error) {
      console.error("Error al agregar la orden:", error);
      toast.error("Error al agregar la orden.");
    }
}


function cargarErrores(errores){
  newClientFormErrors.id=(errores.id)?errores.id.join(", "):""
  newClientFormErrors.identification=(errores.identification)?errores.identification.join(", "):""
  newClientFormErrors.identification_type=(errores.identification_type)?errores.identification_type.join(", "):""
  newClientFormErrors.name=(errores.name)?errores.name.join(", "):""
  newClientFormErrors.last_name=(errores.last_name)?errores.last_name.join(", "):""
  newClientFormErrors.email=(errores.email)?errores.email.join(", "):""
  newClientFormErrors.phone=(errores.phone)?errores.phone.join(", "):""
  newClientFormErrors.address=(errores.address)?errores.address.join(", "):""
  newClientFormErrors.birthdate=(errores.birthdate)?errores.birthdate.join(", "):""
  newClientFormErrors.company_id=(errores.company_id)?errores.company_id.join(", "):""
}

const handleSaveNewClient = async (formData) => {
  try {
    let respuesApi=await axios.post("/crm/clients",formData)
    if(respuesApi.status==200){
        toast.success("El cliente se a guardado correctamente")
        handleCloseRegisterModal();
        addOrden(respuesApi.data.data.id);
    }
  } catch (error) {
    toast.error("Error al crear el cliente")
    console.log("error en el servidor => ",error)
    let errores={...error.response.data.data.errors}
    cargarErrores(errores)
  }
};

const handleCloseRegisterModal = () => {
  showRegisterClientModal.value = false;
  limpiarDatosFormulario()
  limpiarErroresFormulario()
};

function limpiarDatosFormulario(){
  newClientFormData.id=null
  newClientFormData.identification=""
  newClientFormData.identification_type=""
  newClientFormData.name=""
  newClientFormData.last_name=""
  newClientFormData.email=""
  newClientFormData.phone=""
  newClientFormData.address=""
  newClientFormData.birthdate=null
  newClientFormData.company_id=""
}

function limpiarErroresFormulario(){
  newClientFormErrors.id=""
  newClientFormErrors.identification=""
  newClientFormErrors.identification_type=""
  newClientFormErrors.name=""
  newClientFormErrors.last_name=""
  newClientFormErrors.email=""
  newClientFormErrors.phone=""
  newClientFormErrors.address=""
  newClientFormErrors.birthdate=""
  newClientFormErrors.company_id=""
}

const clearFormErrors = () => {
  newClientFormErrors.value = {};
};
</script>
<template>
<div>

  <OrderClienteCard
    v-model="clientIdentification"
    @verify-client="verifyClient"
  />

  <OrderFilters
      v-model:searchQuery="filterSearchQuery"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:selectedOrigin="selectedOrigin"
      v-model:stockStatusFilter="stockStatusFilter"
      :laboratories="laboratories"
      :origins="origins"
      :loading="isLoadingFilters"
      @clear="handleClearFilters"
      @sort="handleSort"
    >
    </OrderFilters>

    <OrderProductsTable
      :products="products"
      :loading="loading"
      :total-product="totalProduct"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @add-product="addProductToOrder"
    />

    <RegisterClientModal
      :companies="companies"
      :modalFormulario="showRegisterClientModal"
      titulo="Registrar Nuevo Cliente"
      :formData="newClientFormData"
      :formError="newClientFormErrors"
      @modalClose="handleCloseRegisterModal"
      @save="handleSaveNewClient"
      @clearErrorForm="clearFormErrors"
    />
</div>
</template>
