<script setup lang="js">
import CompaniesClientsFilters from "@/components/CompaniesClientsFilters.vue";
import CompanyHeaderCard from "@/components/CompanyHeaderCard.vue";
import CompaniesClientFormDialoge from "@/components/dialogs/CompaniesClientFormDialoge.vue";
import AddClientToCompanyModal from "@/components/dialogs/AddClientToCompanyModal.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import pdfClienstGenerator from "@/utils/pdfClienstGenerator";
import Swal from 'sweetalert2';
import { onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from "vue-router";

const route = useRoute();
const companyId = route.params.id;

const stateModule = reactive({
  itemsClientes: [],
  totalClientes: 0,
  company: {},
});

const modal = reactive({
  status: false,
  titulo: "Nuevo",
});

const isAddClientModalVisible = ref(false);
const loading = ref(false);
const companyLoading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref('id');
const orderBy = ref('desc');

const buscadorFiltro = ref("");
const tipoIdentificacionFiltro = ref(null);
const fechaDesdeFiltro = ref("");
const fechaHastaFiltro = ref("");

const formulario = reactive({
  id: null,
  identification: "",
  identification_type: "",
  name: "",
  last_name: "",
  email: "",
  phone: "",
  address: "",
  birthdate: "",
  company_id: companyId,
});

const formularioError = reactive({
  id: "",
  identification: "",
  identification_type: "",
  name: "",
  last_name: "",
  email: "",
  phone: "",
  address: "",
  birthdate: "",
});

function mostarModal() {
  modal.status = true;
  modal.titulo = "Nuevo Cliente de " + (stateModule.company.name || '');
}

function mostarModoEdit(payload) {
  let cliente = stateModule.itemsClientes.find(client => client.id == payload);
  if (!cliente) return;
  modal.status = true;
  modal.titulo = `${cliente.name} ${cliente.last_name || ''}`;
  insertarDatosAlFormulario({ ...cliente });
}

function cerrarModal(payload) {
  modal.status = payload;
  limpiarDatosFormulario();
  limpiarErroresFormulario();
}

function insertarDatosAlFormulario(datos) {
  formulario.id = datos.id;
  formulario.identification = datos.identification;
  formulario.identification_type = datos.identification_type;
  formulario.name = datos.name;
  formulario.last_name = datos.last_name;
  formulario.email = datos.email ?? "";
  formulario.phone = datos.phone;
  formulario.address = datos.address;
  formulario.birthdate = datos.birthdate;
}

function limpiarDatosFormulario() {
  formulario.id = null;
  formulario.identification = "";
  formulario.identification_type = "";
  formulario.name = "";
  formulario.last_name = "";
  formulario.email = "";
  formulario.phone = "";
  formulario.address = "";
  formulario.birthdate = null;
}

function limpiarErroresFormulario() {
  Object.keys(formularioError).forEach(key => formularioError[key] = "");
}

function enviar(payload) {
  if (formulario.id == null) {
    crear(payload);
  } else {
    actualizar(payload);
  }
}

async function crear(data) {
  try {
    let respuesApi = await axios.post("/crm/clients", data);
    if (respuesApi.status == 200) {
      toast.success("El cliente se ha guardado correctamente");
      cerrarModal(false);
      await actualizarTablaClientes();
    }
  } catch (error) {
    toast.error("Error al crear el cliente");
    let errores = { ...error.response?.data?.data?.errors };
    cargarErrores(errores);
  }
}

async function actualizar(data) {
  try {
    let config = { headers: { 'Content-Type': 'multipart/form-data' } };
    let respuesApi = await axios.post(`/crm/clients/edit/${data.get("id")}`, data, config);
    if (respuesApi.status == 200) {
      toast.success("Se guardaron los cambios correctamente");
      cerrarModal(false);
      await actualizarTablaClientes();
    }
  } catch (error) {
    toast.error("Error al guardar los cambios del cliente");
    let errores = { ...error.response?.data?.data?.errors };
    cargarErrores(errores);
  }
}

function cargarErrores(errores) {
  Object.keys(formularioError).forEach(key => {
    formularioError[key] = errores[key] ? errores[key].join(", ") : "";
  });
}

async function actualizarTablaClientes() {
  loading.value = true;
  let filtros = {
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    orderBy: orderBy.value,
    sortBy: sortBy.value,
    buscardor_filtro: buscadorFiltro.value,
    tipo_identificacion_filtro: tipoIdentificacionFiltro.value,
    company_id: companyId,
    fechaDesde_filtro: fechaDesdeFiltro.value,
    fechaHasta_filtro: fechaHastaFiltro.value,
  };
  try {
    let respuestaApi = await axios.post(`/crm/clients/filtrar?page=${filtros.page}`, filtros);
    if (respuestaApi.status == 200 && respuestaApi.data?.data) {
      stateModule.itemsClientes = respuestaApi.data.data.data || [];
      stateModule.totalClientes = respuestaApi.data.data.total || 0;
    }
  } catch (err) {
    toast.error("Error al obtener lista de clientes");
  } finally {
    loading.value = false;
  }
}

async function cargarDatosEmpresa() {
  companyLoading.value = true;
  try {
    let respuestaApi = await axios.get(`/crm/companies/${companyId}`);
    if (respuestaApi.status == 200 && respuestaApi.data?.data) {
      stateModule.company = respuestaApi.data.data;
    }
  } catch (err) {
    toast.error("Error al consultar información de la empresa");
  } finally {
    companyLoading.value = false;
  }
}

async function confirmarEliminarCliente(payload) {
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
  });

  if (result.isConfirmed) {
    await eliminarCliente(payload);
  }
}

async function confirmarQuitarDeEmpresa(clientId) {
  const result = await Swal.fire({
    title: '¿Quitar cliente de la empresa?',
    text: 'El cliente será desvinculado de esta empresa.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, quitar',
    cancelButtonText: 'Cancelar',
    buttonsStyling: false,
    customClass: {
      confirmButton: 'v-btn v-btn--elevated v-theme--light bg-warning v-btn--density-default v-btn--size-default v-btn--variant-elevated',
      cancelButton: 'v-btn v-theme--light text-secondary v-btn--density-default v-btn--size-default v-btn--variant-outlined mx-2'
    },
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    await removeFromCompany(clientId);
  }
}

async function removeFromCompany(clientId) {
  try {
    await axios.post(`/crm/clients/${clientId}/update-company/${companyId}`, {
      client_id: clientId,
      company_id: parseInt(companyId),
      status: false,
    });
    toast.success("Cliente desvinculado de la empresa");
    await actualizarTablaClientes();
    await cargarDatosEmpresa();
  } catch (error) {
    toast.error("Error al desvincular el cliente");
  }
}

async function eliminarCliente(id) {
  try {
    let respuesApi = await axios.delete(`/crm/clients/${id}`);
    if (respuesApi.status == 200) {
      toast.success("El cliente se ha eliminado correctamente");
      cerrarModal(false);
      await actualizarTablaClientes();
      await cargarDatosEmpresa();
    }
  } catch (error) {
    toast.error("Error al eliminar el cliente");
  }
}

const updateTableOptions = options => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

watch(
  [buscadorFiltro, tipoIdentificacionFiltro, fechaDesdeFiltro, fechaHastaFiltro, page, itemsPerPage, orderBy, sortBy],
  async () => { await actualizarTablaClientes(); }
);

function limpiarFiltros() {
  buscadorFiltro.value = "";
  tipoIdentificacionFiltro.value = "";
  fechaDesdeFiltro.value = "";
  fechaHastaFiltro.value = "";
}

async function exportarPdf() {
  let filtros = {
    buscardor_filtro: buscadorFiltro.value,
    tipo_identificacion_filtro: tipoIdentificacionFiltro.value,
    company_id: companyId,
    fechaDesde_filtro: fechaDesdeFiltro.value,
    fechaHasta_filtro: fechaHastaFiltro.value,
  };
  try {
    let respuestaApi = await axios.post(`/crm/clients/filtrar-sin-paginar`, filtros);
    if (!respuestaApi.data?.data || respuestaApi.data.data.length == 0) {
      toast.info("No hay clientes para generar reporte");
      return;
    }
    pdfClienstGenerator(respuestaApi.data.data);
  } catch (err) {
    toast.error("Error al exportar PDF");
  }
}

async function exportarExcel(formato) {
  try {
    let params = {
      buscardor_filtro: buscadorFiltro.value,
      tipo_identificacion_filtro: tipoIdentificacionFiltro.value,
      company_id: companyId,
      fechaDesde_filtro: fechaDesdeFiltro.value,
      fechaHasta_filtro: fechaHastaFiltro.value,
      formato,
    };
    let respuestaApi = await axios.get(`/crm/clients/exportar/excel`, { params, responseType: "blob" });
    const url = window.URL.createObjectURL(new Blob([respuestaApi.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", `clients.${formato}`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch (error) {
    toast.error("Error al exportar Excel");
  }
}

function onClientAssigned() {
  actualizarTablaClientes();
  cargarDatosEmpresa();
}

function openAddClientModal() {
  isAddClientModalVisible.value = true;
}

onMounted(async () => {
  limpiarFiltros();
  await Promise.all([
    cargarDatosEmpresa(),
    actualizarTablaClientes(),
  ]);
});
</script>

<template>
  <div>
    <!-- Cabecera de Empresa -->
    <CompanyHeaderCard
      :company="stateModule.company"
      :total-clients="stateModule.totalClientes"
      :loading="companyLoading"
    />

    <!-- Filtros de Clientes de Empresa -->
    <CompaniesClientsFilters
      v-model:buscador="buscadorFiltro"
      v-model:tipo_identificacion_filtro="tipoIdentificacionFiltro"
      v-model:fechaDesde_filtro="fechaDesdeFiltro"
      v-model:fechaHasta_filtro="fechaHastaFiltro"
      @clear="limpiarFiltros"
      @add-existing-client="openAddClientModal"
      @export-pdf="exportarPdf"
      @export-excel="exportarExcel"
    />

    <CompaniesClientFormDialoge
      :modal-formulario="modal.status"
      :titulo="modal.titulo"
      :form-data="formulario"
      :form-error="formularioError"
      @modal-close="cerrarModal"
      @clear-error-form="limpiarErroresFormulario"
      @save="enviar"
    />

    <AddClientToCompanyModal
      v-model="isAddClientModalVisible"
      :company-id="companyId"
      :company-name="stateModule.company.name || ''"
      @client-assigned="onClientAssigned"
    />

    <VCard border class="mt-4">
      <ClientOfCompanyTable
        :companyId="companyId"
        :clients="stateModule.itemsClientes"
        :total-clients="stateModule.totalClientes"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :page="page"
        :sort-by="sortBy"
        :order-by="orderBy"
        @edit="mostarModoEdit"
        @delete="confirmarEliminarCliente"
        @remove-from-company="confirmarQuitarDeEmpresa"
        @update:options="updateTableOptions"
      />
    </VCard>
  </div>
</template>
