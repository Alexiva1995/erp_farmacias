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
    @click:outside="onClose"
    @keydown.esc="onClose"
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between pa-5 bg-primary">
        <div class="d-flex align-center gap-3">
          <VIcon icon="tabler-user-plus" size="24" color="white" />
          <span class="text-h6 text-white">Añadir Cliente a {{ companyName }}</span>
        </div>
        <VBtn icon variant="text" color="white" size="small" @click="onClose">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VCardText class="pa-5">
        <AppTextField
          v-model="searchQuery"
          placeholder="Buscar cliente por nombre o identificación..."
          prepend-inner-icon="tabler-search"
          clearable
          autofocus
          class="mb-4"
        />

        <div v-if="loading" class="d-flex justify-center py-6">
          <VProgressCircular indeterminate color="info" size="40" />
        </div>

        <div v-else-if="clients.length === 0 && searchQuery.length >= 2" class="text-center py-6">
          <VIcon icon="tabler-users-minus" size="48" color="secondary" class="mb-2" />
          <div class="text-body-1 text-medium-emphasis">No se encontraron clientes disponibles</div>
        </div>

        <div v-else-if="searchQuery.length < 2 && searchQuery.length > 0" class="text-center py-6">
          <div class="text-body-2 text-medium-emphasis">Escribe al menos 2 caracteres para buscar</div>
        </div>

        <VList v-if="clients.length > 0" lines="two" density="compact" class="rounded border">
          <template v-for="(client, index) in clients" :key="client.id">
            <VListItem>
              <template #prepend>
                <VAvatar color="primary" variant="tonal" size="40">
                  <VIcon icon="tabler-user" size="20" />
                </VAvatar>
              </template>
              <VListItemTitle class="text-body-2 font-weight-medium">
                {{ client.name }} {{ client.last_name || '' }}
              </VListItemTitle>
              <VListItemSubtitle class="text-caption">
                {{ client.identification_type }}{{ client.identification }}
                <span v-if="client.company" class="ms-2">· {{ client.company.name }}</span>
              </VListItemSubtitle>
              <template #append>
                <VBtn
                  color="info"
                  variant="tonal"
                  size="small"
                  prepend-icon="tabler-plus"
                  :loading="assigning"
                  @click="assignClient(client.id)"
                >
                  Añadir
                </VBtn>
              </template>
            </VListItem>
            <VDivider v-if="index < clients.length - 1" />
          </template>
        </VList>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-5">
        <VRow class="w-100 ma-0">
          <VCol cols="12" class="pa-2">
            <VBtn
              color="secondary"
              variant="outlined"
              prepend-icon="tabler-x"
              block
              @click="onClose"
            >
              Cerrar
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
