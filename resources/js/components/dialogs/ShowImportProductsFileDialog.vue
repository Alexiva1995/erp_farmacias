<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { reactive, ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  selectedSupplier: { type: Object, default: () => ({}) },
});

const emit = defineEmits([
  "update:modelValue",
  "close-dialog",
  "refresh-products",
]);

const errors = ref({});
const activeFormat = ref("primary"); // 'primary' | 'secondary'

// Estructuras reactivas para formato principal y secundario
const formats = reactive({
  primary: {
    start_row: 1,
    cod_supplier: "",
    name: "",
    barcode_match: null,
    unit_cost: "",
    unit_cost_usd: "",
    active_ingredient: "",
    expiration: null,
    quantity: null,
    currency: null,
  },
  secondary: {
    start_row: 1,
    cod_supplier: "",
    name: "",
    barcode_match: null,
    unit_cost: "",
    unit_cost_usd: "",
    active_ingredient: "",
    expiration: null,
    quantity: null,
    currency: null,
  },
});

const file = ref(null);
const isSavingStructure = ref(false);

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

  const current = formats[activeFormat.value];
  const form = new FormData();
  form.append("start_row", current.start_row || 1);
  form.append("cod_supplier", current.cod_supplier || "");
  form.append("name", current.name || "");
  form.append("barcode_match", current.barcode_match || "");
  form.append("quantity", current.quantity || "");
  form.append("unit_cost", current.unit_cost || "");
  form.append("unit_cost_usd", current.unit_cost_usd || "");
  form.append("active_ingredient", current.active_ingredient || "");
  form.append("expiration", current.expiration || "");
  form.append("format_type", activeFormat.value);
  form.append("save_as_secondary", activeFormat.value === "secondary" ? "1" : "0");

  if (current.currency !== null && current.currency !== "") {
    form.append("currency", current.currency);
  }

  let fileToUpload = file.value;
  if (Array.isArray(file.value) && file.value.length > 0) {
    fileToUpload = file.value[0];
  }

  if (!fileToUpload) {
    toast.error("Debes seleccionar un archivo Excel o CSV.");
    return;
  }

  form.append("file", fileToUpload);

  try {
    const uploadUrl = `/suppliers/${props.selectedSupplier.id}/import`;

    await axios.post(uploadUrl, form, {
      transformRequest: (data, headers) => {
        delete headers["Content-Type"];
        return data;
      },
    });

    toast.success(`Datos cargados correctamente para ${props.selectedSupplier.name} (${activeFormat.value === 'secondary' ? 'Formato 2' : 'Formato 1'})`);

    file.value = null;
    emit("close-dialog");
    emit("refresh-products");
  } catch (error) {
    console.error("[Import] Error en la petición:", error);
    toast.error(
      `No se pudo cargar los datos del excel para el proveedor ${props.selectedSupplier.name}`
    );

    if (error.response && error.response.status === 422) {
      errors.value = error.response.data.errors || {};
    }
  }
};

const fetchSupplierConnection = async (id) => {
  try {
    const { data } = await axios.get(`suppliers/${id}/first-connection`);
    const structure = data.data?.structure || {};
    const secondaryStructure = data.data?.secondary_structure || {};

    formats.primary = {
      start_row: structure.start_row ?? 1,
      cod_supplier: structure.cod_supplier ?? "",
      name: structure.name ?? "",
      barcode_match: structure.barcode_match ?? null,
      unit_cost: structure.unit_cost ?? "",
      unit_cost_usd: structure.unit_cost_usd ?? "",
      active_ingredient: structure.active_ingredient ?? "",
      expiration: structure.expiration != "null" ? structure.expiration : null,
      quantity: structure.quantity != "null" ? structure.quantity : null,
      currency: structure.currency ?? null,
    };

    formats.secondary = {
      start_row: secondaryStructure.start_row ?? structure.start_row ?? 1,
      cod_supplier: secondaryStructure.cod_supplier ?? "",
      name: secondaryStructure.name ?? "",
      barcode_match: secondaryStructure.barcode_match ?? null,
      unit_cost: secondaryStructure.unit_cost ?? "",
      unit_cost_usd: secondaryStructure.unit_cost_usd ?? "",
      active_ingredient: secondaryStructure.active_ingredient ?? "",
      expiration: secondaryStructure.expiration != "null" ? secondaryStructure.expiration : null,
      quantity: secondaryStructure.quantity != "null" ? secondaryStructure.quantity : null,
      currency: secondaryStructure.currency ?? null,
    };
  } catch (error) {
    console.error("Error al obtener estructura de conexión:", error);
  }
};

const handleCleanFormData = () => {
  file.value = null;
  activeFormat.value = "primary";
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
    max-width="840px"
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
              Importación Masiva • Soporte de Múltiples Formatos
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

        <!-- Selector de Formato / Mapeo -->
        <div class="d-flex align-center justify-space-between mb-3">
          <div class="d-flex align-center gap-2">
            <div class="header-indicator secondary shadow-sm" />
            <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Mapeo de Columnas</span>
          </div>

          <!-- Tabs para cambiar entre Formato 1 y Formato 2 -->
          <VBtnToggle
            v-model="activeFormat"
            mandatory
            color="primary"
            variant="outlined"
            density="compact"
            class="rounded-lg border"
          >
            <VBtn value="primary" size="small" class="px-3 text-none font-weight-bold">
              <VIcon start icon="tabler-file-text" size="16" />
              Formato 1 (Principal)
            </VBtn>
            <VBtn value="secondary" size="small" class="px-3 text-none font-weight-bold">
              <VIcon start icon="tabler-file-plus" size="16" />
              Formato 2 (Secundario)
            </VBtn>
          </VBtnToggle>
        </div>

        <VCard variant="flat" class="pa-4 bg-white rounded-xl border shadow-sm mb-4">
          <VAlert
            :type="activeFormat === 'secondary' ? 'warning' : 'info'"
            variant="tonal"
            density="compact"
            icon="tabler-info-circle"
            class="rounded-xl mb-4"
          >
            <span class="text-super-xs font-weight-black">
              Editando columnas para el <strong>{{ activeFormat === 'secondary' ? 'Formato 2 (Secundario / Alternativo)' : 'Formato 1 (Principal por Defecto)' }}</strong>.
              Indica la letra o número de columna en la que se encuentra cada campo en este archivo.
            </span>
          </VAlert>

          <VRow>
            <VCol cols="6">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Fila de Inicio</span>
              <VTextField
                v-model="formats[activeFormat].start_row"
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
                v-model="formats[activeFormat].name"
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
                v-model="formats[activeFormat].cod_supplier"
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
                v-model="formats[activeFormat].barcode_match"
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
                v-model="formats[activeFormat].unit_cost"
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
                v-model="formats[activeFormat].unit_cost_usd"
                type="text"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
                prepend-inner-icon="tabler-currency-dollar"
                class="rounded-lg font-weight-black"
                :error-messages="errors.unit_cost_usd"
              />
            </VCol>
            <VCol cols="6">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Tasa de Cambio</span>
              <VTextField
                v-model="formats[activeFormat].currency"
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
                v-model="formats[activeFormat].active_ingredient"
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
                v-model="formats[activeFormat].expiration"
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
                v-model="formats[activeFormat].quantity"
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
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Archivo de Productos ({{ activeFormat === 'secondary' ? 'Formato 2' : 'Formato 1' }})</span>
        </div>

        <VCard variant="flat" class="pa-4 bg-white rounded-xl border shadow-sm">
          <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Listado de Productos (.xlsx, .xls o .csv)</span>
          <VFileInput
            v-model="file"
            accept=".xlsx,.xls,.csv"
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
              Cargar con {{ activeFormat === 'secondary' ? 'Formato 2' : 'Formato 1' }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient) !important;
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
