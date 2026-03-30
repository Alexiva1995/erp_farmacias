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
    max-width="820px"
    persistent
    scrollable
    @update:model-value="emit('close-dialog')"
  >
    <VCard class="detail-dialog-card overflow-hidden">
      <!-- Header Premium Institucional -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
            <VIcon icon="tabler-file-spreadsheet" color="primary" size="22" />
          </VAvatar>
          <div class="d-flex flex-column leading-none text-white">
            <h2 class="text-h6 font-weight-black leading-tight mb-0 uppercase text-white">
              Cargar Productos desde Excel
            </h2>
            <span class="text-super-xs opacity-75 font-weight-bold uppercase letter-spacing-1">
              Importación Masiva • Mapeo de Columnas
            </span>
          </div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="white" size="small" class="rounded-lg" @click="emit('close-dialog')" />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light">

        <!-- Info del proveedor -->
        <div class="d-flex align-center gap-2 mb-3">
          <div class="header-indicator primary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Información del Proveedor</span>
        </div>

        <VCard variant="flat" class="pa-4 bg-white rounded-xl border shadow-sm mb-4">
          <VRow>
            <VCol cols="12" sm="6">
              <div class="d-flex align-center gap-2 mb-1">
                <VIcon icon="tabler-building-store" size="14" color="primary" />
                <span class="text-super-xs font-weight-black text-disabled uppercase letter-spacing-1">Proveedor</span>
              </div>
              <div class="text-subtitle-2 font-weight-black text-high-emphasis">{{ selectedSupplier.name }}</div>
            </VCol>
            <VCol cols="12" sm="6">
              <div class="d-flex align-center gap-2 mb-1">
                <VIcon icon="tabler-calendar-time" size="14" color="primary" />
                <span class="text-super-xs font-weight-black text-disabled uppercase letter-spacing-1">Última Actualización</span>
              </div>
              <VChip
                :color="formatDate(selectedSupplier.last_connection) === 'N/A' ? 'warning' : 'success'"
                size="small"
                class="font-weight-black rounded-lg"
                variant="tonal"
              >
                {{ formatDate(selectedSupplier.last_connection) }}
              </VChip>
            </VCol>
          </VRow>
        </VCard>

        <!-- Mapeo de columnas -->
        <div class="d-flex align-center gap-2 mb-3">
          <div class="header-indicator secondary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Mapeo de Columnas del Archivo</span>
        </div>

        <VCard variant="flat" class="pa-4 bg-white rounded-xl border shadow-sm mb-4">
          <VAlert type="info" variant="tonal" density="compact" icon="tabler-info-circle" class="rounded-xl mb-4">
            <span class="text-super-xs font-weight-black">Indica el número de columna en la que se encuentra cada campo dentro del archivo Excel.</span>
          </VAlert>

          <VRow>
            <VCol cols="6">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Fila de Inicio</span>
              <VTextField
                v-model="start_row"
                type="text"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
                prepend-inner-icon="tabler-row-insert-top"
                class="rounded-lg font-weight-black"
                :error-messages="errors.start_row"
              />
            </VCol>
            <VCol cols="6">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Nombre del Producto</span>
              <VTextField
                v-model="name"
                type="text"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
                prepend-inner-icon="tabler-tag"
                class="rounded-lg font-weight-black"
                :error-messages="errors.name"
              />
            </VCol>
            <VCol cols="6">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Código del Proveedor</span>
              <VTextField
                v-model="cod_supplier"
                type="text"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
                prepend-inner-icon="tabler-barcode"
                class="rounded-lg font-weight-black"
                :error-messages="errors.cod_supplier"
              />
            </VCol>
            <VCol cols="6">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Código de Barras</span>
              <VTextField
                v-model="barcode"
                type="text"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
                prepend-inner-icon="tabler-scan"
                class="rounded-lg font-weight-black"
                :error-messages="errors.barcode_match"
              />
            </VCol>
            <VCol cols="6">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Coste Unitario (Bs)</span>
              <VTextField
                v-model="bs_cost"
                type="text"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
                prepend-inner-icon="tabler-currency-boliviano"
                class="rounded-lg font-weight-black"
                :error-messages="errors.unit_cost"
              />
            </VCol>
            <VCol cols="6">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Coste Unitario (USD)</span>
              <VTextField
                v-model="usd_cost"
                type="text"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
                prepend-inner-icon="tabler-currency-dollar"
                class="rounded-lg font-weight-black"
                :error-messages="errors.usd_cost"
              />
            </VCol>
            <VCol cols="6">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Tasa de Cambio</span>
              <VTextField
                v-model="currency"
                type="number"
                :step="0.01"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
                prepend-inner-icon="tabler-arrows-exchange"
                class="rounded-lg font-weight-black"
                :error-messages="errors.currency"
              />
            </VCol>
            <VCol cols="6">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Principio Activo</span>
              <VTextField
                v-model="active_ingredient"
                type="text"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
                prepend-inner-icon="tabler-flask"
                class="rounded-lg font-weight-black"
                :error-messages="errors.active_ingredient"
              />
            </VCol>
            <VCol cols="6">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Fecha de Expiración</span>
              <VTextField
                v-model="expiration"
                type="text"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
                prepend-inner-icon="tabler-calendar-x"
                class="rounded-lg font-weight-black"
                :error-messages="errors.expiration"
              />
            </VCol>
            <VCol cols="6">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Cantidad</span>
              <VTextField
                v-model="quantity"
                type="text"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
                prepend-inner-icon="tabler-packages"
                class="rounded-lg font-weight-black"
                :error-messages="errors.quantity"
              />
            </VCol>
          </VRow>
        </VCard>

        <!-- Archivo Excel -->
        <div class="d-flex align-center gap-2 mb-3">
          <div class="header-indicator primary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Archivo de Productos</span>
        </div>

        <VCard variant="flat" class="pa-4 bg-white rounded-xl border shadow-sm">
          <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Listado de Productos (Excel)</span>
          <VFileInput
            v-model="file"
            accept="application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="tabler-file-spreadsheet"
            prepend-icon=""
            clearable
            hide-details="auto"
            class="rounded-lg font-weight-black"
            :error-messages="errors.file"
          />
        </VCard>

      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 pa-sm-6 bg-white border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
              class="font-weight-black rounded-lg uppercase"
              @click="emit('close-dialog')"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary uppercase"
              @click="submitForm"
            >
              <VIcon start icon="tabler-upload" size="18" />
              Cargar Productos
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e5128 100%);
}

.bg-light {
  background-color: #f8faff !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary { background-color: rgb(var(--v-theme-primary)); }
.header-indicator.secondary { background-color: rgb(var(--v-theme-secondary)); }

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 { letter-spacing: 1px !important; }
.leading-none { line-height: 1 !important; }
.leading-tight { line-height: 1.25 !important; }

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
