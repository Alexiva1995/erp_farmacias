<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, ref, watch } from "vue";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
});

const emit = defineEmits(["update:modelValue", "supplier-activated"]);

// --- Estado reactivo del catálogo ---
const suppliers = ref([]);
const loading = ref(false);
const searchQuery = ref("");
const statusFilter = ref("available"); // 'all', 'available', 'active'
const page = ref(1);
const totalSuppliers = ref(0);
const perPage = ref(25);
let searchDebounce = null;

// --- Estado para el formulario de activación ---
const isActivateModalOpen = ref(false);
const selectedSupplier = ref(null);
const senderEmail = ref("");
const creditDays = ref(0);
const isActivating = ref(false);
const activateErrors = ref({});

// Opciones de filtro de estado
const statusFilterOptions = [
  { title: "Listos para Activar", value: "available" },
  { title: "Ya Activos en Farmacia", value: "active" },
  { title: "Todos en Master", value: "all" },
];

// Carga de catálogo desde la API
const fetchMasterCatalog = async () => {
  loading.value = true;
  try {
    const response = await axios.get("/suppliers-master-catalog", {
      params: {
        search: searchQuery.value,
        status: statusFilter.value,
        page: page.value,
        per_page: perPage.value,
      },
    });

    suppliers.value = response.data?.data || [];
    totalSuppliers.value = response.data?.meta?.total || 0;
  } catch (error) {
    console.error("Error al cargar catálogo maestro:", error);
    toast.error("No se pudo cargar el catálogo de proveedores maestros.");
  } finally {
    loading.value = false;
  }
};

// Abrir formulario de activación para un proveedor específico
const handleOpenActivate = (item) => {
  selectedSupplier.value = item;
  senderEmail.value = item.configured_email || "";
  creditDays.value = item.credit_days || 0;
  activateErrors.value = {};
  isActivateModalOpen.value = true;
};

// Cerrar submodal de activación
const closeActivateModal = () => {
  isActivateModalOpen.value = false;
  selectedSupplier.value = null;
  senderEmail.value = "";
  creditDays.value = 0;
  activateErrors.value = {};
};

// Enviar activación al backend
const handleConfirmActivate = async () => {
  if (!selectedSupplier.value) return;

  activateErrors.value = {};

  // Si es tipo Excel o correo, sugerir/validar formato de correo si fue ingresado
  if (senderEmail.value && !/^\S+@\S+\.\S+$/.test(senderEmail.value.trim())) {
    activateErrors.value.sender_email = ["Ingrese un correo electrónico válido."];
    return;
  }

  isActivating.value = true;
  try {
    const payload = {
      master_supplier_id: selectedSupplier.value.master_id,
      sender_email: senderEmail.value ? senderEmail.value.trim() : null,
      credit_days: Number(creditDays.value) || 0,
      is_active: true,
    };

    const response = await axios.post("/suppliers-master-catalog/activate", payload);

    toast.success(response.data?.message || `Proveedor ${selectedSupplier.value.name} activado correctamente.`);
    closeActivateModal();
    await fetchMasterCatalog();
    emit("supplier-activated", response.data?.supplier);
  } catch (error) {
    console.error("Error al activar proveedor desde el master:", error);
    if (error.response?.status === 422 && error.response?.data?.errors) {
      activateErrors.value = error.response.data.errors;
    } else {
      toast.error(error.response?.data?.message || "Ocurrió un error al activar el proveedor.");
    }
  } finally {
    isActivating.value = false;
  }
};

// Resumen legible de columnas Excel
const getColumnsSummaryText = (summary) => {
  if (!summary || typeof summary !== "object" || Object.keys(summary).length === 0) {
    return "Estructura estándar";
  }
  const pairs = Object.entries(summary).slice(0, 4).map(([k, v]) => `${k}: Col ${v}`);
  return pairs.join(" | ");
};

// Observadores de diálogo y filtros
watch(
  () => props.modelValue,
  (val) => {
    if (val) {
      searchQuery.value = "";
      page.value = 1;
      fetchMasterCatalog();
    }
  }
);

watch(searchQuery, () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    page.value = 1;
    fetchMasterCatalog();
  }, 350);
});

watch(statusFilter, () => {
  page.value = 1;
  fetchMasterCatalog();
});

const closeDialog = () => {
  emit("update:modelValue", false);
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="960"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="rounded-lg">
      <!-- Encabezado -->
      <VCardItem class="pb-3 border-b">
        <div class="d-flex align-center justify-space-between w-100 flex-wrap gap-2">
          <div class="d-flex align-center gap-2">
            <VAvatar color="primary" variant="tonal" rounded size="38">
              <VIcon icon="tabler-database-import" size="22" />
            </VAvatar>
            <div>
              <VCardTitle class="text-h6 font-weight-bold">
                Directorio de Droguerías — Catálogo Maestro
              </VCardTitle>
              <VCardSubtitle class="text-caption">
                Active droguerías con formatos de Excel preconfigurados sin mapear columnas manualmente.
              </VCardSubtitle>
            </div>
          </div>
          <VBtn icon variant="text" size="small" @click="closeDialog">
            <VIcon icon="tabler-x" />
          </VBtn>
        </div>
      </VCardItem>

      <VCardText class="pa-4">
        <!-- Banner Explicativo -->
        <VAlert
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
          border="start"
        >
          <div class="text-xs">
            <strong>Plantillas Oficiales Centralizadas:</strong> Las droguerías listadas aquí tienen sus formatos de archivo y columnas homologadas desde el Master central. Al activarlas en su farmacia, el sistema importará automáticamente sus listas de precios; usted solo debe indicar el correo remitente donde las recibe.
          </div>
        </VAlert>

        <!-- Barra de Búsqueda y Filtros -->
        <VRow dense class="mb-3">
          <VCol cols="12" sm="7" md="8">
            <VTextField
              v-model="searchQuery"
              placeholder="Buscar por Nombre de droguería o RIF..."
              density="compact"
              prepend-inner-icon="tabler-search"
              clearable
              hide-details
            />
          </VCol>
          <VCol cols="12" sm="5" md="4">
            <VSelect
              v-model="statusFilter"
              :items="statusFilterOptions"
              item-title="title"
              item-value="value"
              density="compact"
              hide-details
              prepend-inner-icon="tabler-filter"
            />
          </VCol>
        </VRow>

        <!-- Estado de Carga -->
        <div v-if="loading" class="d-flex justify-center align-center py-12">
          <VProgressCircular indeterminate color="primary" size="36" />
          <span class="text-body-2 ms-3 text-medium-emphasis">Consultando Catálogo Maestro...</span>
        </div>

        <!-- Estado Vacío -->
        <div
          v-else-if="suppliers.length === 0"
          class="text-center py-12 border rounded bg-light"
        >
          <VIcon icon="tabler-folder-off" size="48" color="medium-emphasis" class="mb-2" />
          <p class="text-body-1 font-weight-medium mb-1">No se encontraron droguerías</p>
          <p class="text-caption text-medium-emphasis">
            No hay proveedores en el catálogo maestro que coincidan con los criterios de búsqueda.
          </p>
        </div>

        <!-- Lista de Proveedores Maestros -->
        <div v-else class="master-suppliers-list overflow-y-auto" style="max-height: 480px;">
          <VTable density="compact" class="text-no-wrap">
            <thead>
              <tr>
                <th class="text-uppercase text-caption font-weight-bold">Droguería / RIF</th>
                <th class="text-uppercase text-caption font-weight-bold">Integración</th>
                <th class="text-uppercase text-caption font-weight-bold">Estado Local</th>
                <th class="text-uppercase text-caption font-weight-bold text-center">Acción</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in suppliers" :key="item.master_id">
                <!-- Nombre y RIF -->
                <td>
                  <div class="d-flex flex-column py-1">
                    <span class="font-weight-bold text-body-2">{{ item.name }}</span>
                    <span class="text-caption text-medium-emphasis">
                      RIF: {{ item.rif || 'Sin RIF' }}
                      <span v-if="item.configured_email" class="ms-2 text-primary font-weight-medium">
                        • Correo: {{ item.configured_email }}
                      </span>
                    </span>
                  </div>
                </td>

                <!-- Tipo de Integración y Plantilla -->
                <td>
                  <div class="d-flex flex-column gap-1">
                    <div class="d-flex align-center gap-1">
                      <VChip
                        v-if="item.has_excel_structure || item.integration_type === 'file'"
                        color="success"
                        size="x-small"
                        variant="tonal"
                        prepend-icon="tabler-file-spreadsheet"
                      >
                        Excel por Correo
                      </VChip>
                      <VChip
                        v-else-if="item.integration_type === 'ftp'"
                        color="info"
                        size="x-small"
                        variant="tonal"
                        prepend-icon="tabler-server"
                      >
                        FTP
                      </VChip>
                      <VChip
                        v-else
                        color="secondary"
                        size="x-small"
                        variant="tonal"
                        prepend-icon="tabler-api"
                      >
                        {{ item.integration_type.toUpperCase() }}
                      </VChip>

                      <VTooltip
                        v-if="item.has_excel_structure"
                        location="top"
                        :text="getColumnsSummaryText(item.excel_columns_summary)"
                      >
                        <template #activator="{ props: tooltipProps }">
                          <VIcon
                            v-bind="tooltipProps"
                            icon="tabler-info-circle"
                            size="16"
                            color="success"
                          />
                        </template>
                      </VTooltip>
                    </div>
                  </div>
                </td>

                <!-- Estado en la Farmacia Local -->
                <td>
                  <VChip
                    v-if="item.is_active"
                    color="success"
                    size="small"
                    variant="flat"
                  >
                    Activo en Farmacia
                  </VChip>
                  <VChip
                    v-else-if="item.is_installed"
                    color="warning"
                    size="small"
                    variant="tonal"
                  >
                    Desactivado Local
                  </VChip>
                  <VChip
                    v-else
                    color="default"
                    size="small"
                    variant="outlined"
                  >
                    Disponible en Master
                  </VChip>
                </td>

                <!-- Botón de Acción -->
                <td class="text-center">
                  <VBtn
                    v-if="!item.is_active"
                    color="primary"
                    size="small"
                    variant="elevated"
                    prepend-icon="tabler-power"
                    @click="handleOpenActivate(item)"
                  >
                    Activar en Farmacia
                  </VBtn>
                  <VBtn
                    v-else
                    color="primary"
                    size="small"
                    variant="tonal"
                    prepend-icon="tabler-settings"
                    @click="handleOpenActivate(item)"
                  >
                    Configurar Correo
                  </VBtn>
                </td>
              </tr>
            </tbody>
          </VTable>
        </div>
      </VCardText>

      <VCardActions class="pa-4 border-t d-flex justify-space-between align-center">
        <span class="text-caption text-medium-emphasis">
          Total disponibles en Catálogo Maestro: {{ totalSuppliers }}
        </span>
        <VBtn variant="tonal" color="secondary" @click="closeDialog">
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>

    <!-- Subdiálogo de Activación / Configuración de Correo -->
    <VDialog
      v-model="isActivateModalOpen"
      max-width="520"
      persistent
    >
      <VCard class="rounded-lg" v-if="selectedSupplier">
        <VCardItem class="pb-2 border-b">
          <div class="d-flex align-center justify-space-between w-100">
            <div class="d-flex align-center gap-2">
              <VIcon icon="tabler-mail-check" color="primary" size="24" />
              <VCardTitle class="text-subtitle-1 font-weight-bold">
                Activar Droguería: {{ selectedSupplier.name }}
              </VCardTitle>
            </div>
            <VBtn icon variant="text" size="small" @click="closeActivateModal">
              <VIcon icon="tabler-x" />
            </VBtn>
          </div>
        </VCardItem>

        <VCardText class="pa-4">
          <VAlert
            v-if="selectedSupplier.has_excel_structure"
            type="success"
            variant="tonal"
            density="compact"
            class="mb-3"
            border="start"
          >
            <div class="text-xs">
              ✓ Estructura de columnas Excel ya vinculada desde el Master. Solo indique la dirección de correo remitente desde donde le envían las listas.
            </div>
          </VAlert>

          <VForm @submit.prevent="handleConfirmActivate">
            <VRow dense>
              <!-- Campo: Correo Electrónico Remitente -->
              <VCol cols="12">
                <VTextField
                  v-model="senderEmail"
                  label="Correo del Remitente de la Droguería *"
                  placeholder="ejemplo: pedidos@drogueria.com"
                  prepend-inner-icon="tabler-mail"
                  hint="Dirección de correo desde la cual la droguería le envía los archivos Excel adjuntos."
                  persistent-hint
                  :error-messages="activateErrors.sender_email"
                  class="mb-3"
                  autofocus
                />
              </VCol>

              <!-- Campo: Días de Crédito Comercial -->
              <VCol cols="12">
                <VTextField
                  v-model.number="creditDays"
                  label="Días de Crédito Comercial Pactados"
                  type="number"
                  min="0"
                  max="365"
                  prepend-inner-icon="tabler-calendar-time"
                  hint="Plazo de pago convenido con la droguería (0 para contado)."
                  persistent-hint
                  :error-messages="activateErrors.credit_days"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VCardActions class="pa-4 border-t d-flex justify-end gap-2">
          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="isActivating"
            @click="closeActivateModal"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            variant="elevated"
            prepend-icon="tabler-check"
            :loading="isActivating"
            :disabled="isActivating"
            @click="handleConfirmActivate"
          >
            Vincular y Activar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VDialog>
</template>
