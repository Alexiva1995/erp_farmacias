<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  selectedSupplier: { type: Object, default: () => ({}) },
});

const emit = defineEmits([
  "update:modalValue",
  "close-dialog",
  "refresh-products",
]);

const errors = ref({});

const start_row = ref(1);
const cod_supplier = ref("");
const name = ref("");
const barcode = ref(null);
const bs_cost = ref("");
const usd_cost = ref("");
const active_ingredient = ref("");
const expiration = ref(null);
const quantity = ref(null);
const currency = ref(null);
const file = ref(null);

const formatDate = (dateString) => {
  if (!dateString || dateString === "No se ha establecido conexión")
    return "N/A";
  try {
    const date = new Date(dateString);
    const year = date.getUTCFullYear();
    const month = (date.getUTCMonth() + 1).toString().padStart(2, "0");
    const day = date.getUTCDate().toString().padStart(2, "0");
    return `${year}-${month}-${day}`;
  } catch (error) {
    return "Fecha inválida";
  }
};

const submitForm = async () => {
  errors.value = {};

  const form = new FormData();
  form.append("start_row", start_row.value);
  form.append("cod_supplier", cod_supplier.value);
  form.append("name", name.value);
  form.append("barcode_match", barcode.value);
  form.append("quantity", quantity.value);
  form.append("unit_cost", bs_cost.value);
  form.append("unit_cost_usd", usd_cost.value);
  form.append("active_ingredient", active_ingredient.value);
  form.append("expiration", expiration.value);

  if (currency.value !== null && currency.value !== "") {
    form.append("currency", currency.value);
  }
  if (file.value && Array.isArray(file.value)) {
    form.append("file", file.value[0]);
  } else {
    form.append("file", file.value);
  }

  try {
    toast.info(
      `Procesando los datos de ${props.selectedSupplier.name}, le notificaremos al finalizar`
    );
    await axios.post(`/suppliers/${props.selectedSupplier.id}/import`, form, {
      headers: { "Content-Type": "multipart/form-data" },
    });

    start_row.value = 1;
    cod_supplier.value = "";
    name.value = "";
    barcode.value = null;
    bs_cost.value = "";
    usd_cost.value = "";
    active_ingredient.value = "";
    currency.value = null;
    expiration.value = null;
    quantity.value = null;
    file.value = null;
    emit("close-dialog");
    handleCleanFormData();

    emit("refresh-products");
  } catch (error) {
    console.error(error);
    toast.error(
      `No se pudo cargar los datos del excel para el proveedor ${props.selectedSupplier.name}`
    );

    if (error.response.status === 422) {
      errors.value = error.response.data.errors;
    }
  }
};

const fetchSupplierConnection = async (id) => {
  try {
    const { data } = await axios.get(`suppliers/${id}/first-connection`);
    const structure = data.data.structure;
    start_row.value = structure.start_row ?? 1;
    cod_supplier.value = structure.cod_supplier ?? "";
    name.value = structure.name ?? "";
    barcode.value = structure.barcode_match ?? null;
    bs_cost.value = structure.unit_cost ?? "";
    usd_cost.value = structure.unit_cost_usd ?? "";
    active_ingredient.value = structure.active_ingredient ?? "";
    expiration.value =
      structure.expiration != "null" ? structure.expiration : null;
    quantity.value = structure.quantity != "null" ? structure.quantity : null;
  } catch (error) {}
};

const handleCleanFormData = () => {
  start_row.value = 1;
  cod_supplier.value = "";
  name.value = "";
  barcode.value = null;
  bs_cost.value = "";
  usd_cost.value = "";
  active_ingredient.value = "";
  expiration.value = null;
  quantity.value = null;
  currency.value = null;
  file.value = null;
};

watch(
  () => props.selectedSupplier?.id,
  (supplierId) => {
    if (supplierId) {
      handleCleanFormData();
      fetchSupplierConnection(supplierId);
    }
  },
  { immediate: true }
);
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="800px"
    persistent
    @update:model-value="emit('close-dialog')"
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard class="d-flex flex-column">
      <VCardTitle class="d-flex align-center">
        <span class="text-h5 font-weight-bold">Cargar Productos</span>

        <VSpacer />

        <VBtn icon variant="text" @click="emit('close-dialog')">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VSheet variant="tonal" rounded="lg" class="pa-4">
        <VRow>
          <VCol cols="6">
            <div class="d-flex align-center gap-4 mb-4">
              <span class="font-weight-medium">Proveedor</span>
              <VChip color="primary" label>{{ selectedSupplier.name }}</VChip>
              <VSpacer />
            </div>
          </VCol>
          <VCol cols="6">
            <div class="d-flex align-center gap-4 mb-4">
              <span class="font-weight-medium">Última actualización</span>
              <VChip color="primary" label>{{
                formatDate(selectedSupplier.last_connection)
              }}</VChip>
              <VSpacer />
            </div>
          </VCol>
        </VRow>

        <p>
          <span class="font-weight-bold">Nota</span>: Debe indicar las columnas
          en las cuales se encuentran los campos solicitados a continuación:
        </p>

        <VRow>
          <VCol cols="6">
            <VTextField
              v-model="start_row"
              label="Fila de inicio"
              type="text"
              variant="outlined"
              hide-details="auto"
              :error-messages="errors.start_row"
            />
          </VCol>
          <VCol cols="6">
            <VTextField
              v-model="name"
              label="Nombre"
              type="text"
              variant="outlined"
              hide-details="auto"
              :error-messages="errors.name"
            />
          </VCol>
          <VCol cols="6">
            <VTextField
              v-model="cod_supplier"
              label="Código"
              type="text"
              variant="outlined"
              hide-details="auto"
              :error-messages="errors.cod_supplier"
            />
          </VCol>
          <VCol cols="6">
            <VTextField
              v-model="barcode"
              label="Código de Barras"
              type="text"
              variant="outlined"
              hide-details="auto"
              :error-messages="errors.barcode_match"
            />
          </VCol>
          <VCol cols="6">
            <VTextField
              v-model="bs_cost"
              label="Coste Unitario (Bs)"
              type="text"
              variant="outlined"
              hide-details="auto"
              :error-messages="errors.unit_cost"
            />
          </VCol>
          <VCol cols="6">
            <VTextField
              v-model="usd_cost"
              label="Coste Unitario (Usd)"
              type="text"
              variant="outlined"
              hide-details="auto"
              :error-messages="errors.usd_cost"
            />
          </VCol>
          <VCol cols="6">
            <VTextField
              v-model="currency"
              label="Tasa de Cambio"
              type="number"
              variant="outlined"
              hide-details="auto"
              :step="0.01"
              :error-messages="errors.currency"
            />
          </VCol>
          <VCol cols="6">
            <VTextField
              v-model="active_ingredient"
              label="Principio Activo"
              type="text"
              variant="outlined"
              hide-details="auto"
              :error-messages="errors.active_ingredient"
            />
          </VCol>
          <VCol cols="6">
            <VTextField
              v-model="expiration"
              label="Fecha de Expiración"
              type="text"
              variant="outlined"
              hide-details="auto"
              :error-messages="errors.expiration"
            />
          </VCol>
          <VCol cols="6">
            <VTextField
              v-model="quantity"
              label="Cantidad"
              type="text"
              variant="outlined"
              hide-details="auto"
              :error-messages="errors.quantity"
            />
          </VCol>
          <VCol cols="12">
            <VFileInput
              v-model="file"
              label="Listado de Productos"
              accept="application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
              variant="outlined"
              prepend-icon="tabler-file"
              clearable
              :error-messages="errors.file"
            />
          </VCol>
        </VRow>
      </VSheet>

      <VDivider />

      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="emit('close-dialog')"
          class="flex-grow-1 w-0 mr-4"
        >
          Cerrar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          class="flex-grow-1 w-0"
        >
          Cargar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
