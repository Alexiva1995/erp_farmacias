<script setup>
import axios from "@/plugins/axios";
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  companyId: { type: [String, Number], required: true },
  companyName: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue", "client-assigned"]);

const searchQuery = ref("");
const clients = ref([]);
const loading = ref(false);
const assigning = ref(false);
let debounceTimer = null;

const fetchClients = async (query) => {
  if (!query || query.length < 2) {
    clients.value = [];
    return;
  }

  loading.value = true;
  try {
    const response = await axios.post("/crm/clients/filtrar-sin-paginar", {
      buscardor_filtro: query,
    });
    // Filtrar clientes que ya pertenecen a esta empresa
    clients.value = (response.data.data || []).filter(
      (c) => c.company_id != props.companyId
    );
  } catch (error) {
    console.error("Error al buscar clientes:", error);
    clients.value = [];
  } finally {
    loading.value = false;
  }
};

watch(searchQuery, (val) => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    fetchClients(val);
  }, 400);
});

const assignClient = async (clientId) => {
  assigning.value = true;
  try {
    await axios.post(`/crm/clients/${clientId}/update-company/${props.companyId}`, {
      client_id: clientId,
      company_id: parseInt(props.companyId),
      status: true,
    });
    // Quitar el cliente de la lista
    clients.value = clients.value.filter((c) => c.id !== clientId);
    emit("client-assigned");
  } catch (error) {
    console.error("Error al asignar cliente:", error);
  } finally {
    assigning.value = false;
  }
};

const onClose = () => {
  searchQuery.value = "";
  clients.value = [];
  emit("update:modelValue", false);
};

watch(
  () => props.modelValue,
  (val) => {
    if (!val) {
      searchQuery.value = "";
      clients.value = [];
    }
  }
);
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="650px"
    scrollable
    :retain-focus="false"
    :fullscreen="$vuetify.display.xs"
    transition="dialog-bottom-transition"
    class="premium-dialog"
    @click:outside="onClose"
    @keydown.esc="onClose"
  >
    <VCard class="detail-dialog-card overflow-hidden border-0 elevation-12 rounded-lg">
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-3 d-flex align-center shadow-sm">
          <div class="d-flex align-center">
            <VAvatar color="white" variant="flat" size="32" class="me-3 elevation-1">
              <VIcon icon="tabler-user-plus" color="primary" size="18" />
            </VAvatar>
            <div>
              <h2 class="text-subtitle-2 font-weight-black text-white leading-tight mb-0 uppercase">VINCULAR CLIENTE</h2>
              <div class="d-flex align-center gap-1 mt-0">
                <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold truncate">
                  AÑADIR A: {{ companyName }}
                </span>
              </div>
            </div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" color="white" size="x-small" @click="onClose">
            <VIcon size="18">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-4 bg-light" style="max-block-size: 80vh;">
        <AppTextField
          v-model="searchQuery"
          placeholder="BUSCAR CLIENTE POR NOMBRE O IDENTIFICACIÓN..."
          prepend-inner-icon="tabler-search"
          clearable
          autofocus
          density="compact"
          class="mb-4 premium-input"
        />

        <div v-if="loading" class="d-flex justify-center py-6">
          <VProgressCircular indeterminate color="primary" size="40" />
        </div>

        <div v-else-if="clients.length === 0 && searchQuery.length >= 2" class="text-center py-6">
          <VIcon icon="tabler-users-minus" size="48" color="secondary" class="mb-2 opacity-50" />
          <div class="text-sm font-weight-black text-medium-emphasis uppercase">No se encontraron clientes disponibles</div>
        </div>

        <div v-else-if="searchQuery.length < 2 && searchQuery.length > 0" class="text-center py-6">
          <div class="text-xs font-weight-bold text-medium-emphasis uppercase">Escribe al menos 2 caracteres para buscar</div>
        </div>

        <VList v-if="clients.length > 0" lines="two" density="compact" class="rounded-lg border bg-white ma-1">
          <template v-for="(client, index) in clients" :key="client.id">
            <VListItem class="pa-3">
              <template #prepend>
                <VAvatar color="primary" variant="tonal" size="40" class="rounded">
                  <VIcon icon="tabler-user" size="20" />
                </VAvatar>
              </template>
              <VListItemTitle class="text-body-2 font-weight-black uppercase">
                {{ client.name }} {{ client.last_name || '' }}
              </VListItemTitle>
              <VListItemSubtitle class="text-caption font-weight-bold">
                <span class="text-primary">{{ client.identification_type }}{{ client.identification }}</span>
                <span v-if="client.company" class="ms-2 text-disabled uppercase">· {{ client.company.name }}</span>
              </VListItemSubtitle>
              <template #append>
                <VBtn
                  color="primary"
                  variant="flat"
                  size="small"
                  prepend-icon="tabler-plus"
                  class="font-weight-black shadow-sm"
                  :loading="assigning"
                  @click="assignClient(client.id)"
                >
                  VINCULAR
                </VBtn>
              </template>
            </VListItem>
            <VDivider v-if="index < clients.length - 1" class="border-opacity-10" />
          </template>
        </VList>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-light">
        <VBtn
          color="secondary"
          variant="tonal"
          block
          height="48"
          class="font-weight-black rounded-lg text-button uppercase"
          @click="onClose"
        >
          CERRAR VENTANA
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #173b1f 100%);
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.premium-input :deep(.v-field__input) {
  font-size: 0.8rem !important;
  font-weight: 600;
}

.premium-input :deep(.v-label) {
  font-size: 0.7rem !important;
  font-weight: 800;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.leading-tight {
  line-height: 1.25 !important;
}

.uppercase {
  text-transform: uppercase;
}

.text-button {
  font-size: 0.875rem !important;
  letter-spacing: 1px !important;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
