<script setup>
import ExpirationsFilters from '@/components/ExpirationsFilters.vue';
import axios from '@/plugins/axios';
import { onMounted, ref, watch } from 'vue';

const loading = ref(false);
const isDetailViewVisible = ref(false);

const summaryData = ref({
  oldest_date: null,
  newest_date: null,
  total_quantity: 0,
  total_lost_value: 0,
});
const summaryHeaders = [
  { title: 'Fecha (Rango)', key: 'date_range', sortable: false },
  { title: 'Unds. Caducadas', key: 'total_quantity', sortable: false, align: 'end' },
  { title: 'Costo Total Perdido', key: 'total_lost_value', sortable: false, align: 'end' },
  { title: 'Acción', key: 'actions', sortable: false, align: 'center' },
];

const expiredLogs = ref([]);
const totalExpiredLogs = ref(0);
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref('created_at');
const orderBy = ref('desc');
const searchQuery = ref('');

const detailHeaders = [
  { title: 'ID', key: 'id' },
  { title: 'Producto', key: 'product_info', sortable: false },
  { title: 'Unds.', key: 'expired_quantity', align: 'end' },
  { title: 'Exp.', key: 'created_at' },
  { title: 'Acción', key: 'actions', sortable: false, align: 'center' },
];

// --- ESTADO PARA EL MODAL DE DONACIÓN ---
const isDonationModalVisible = ref(false);
const institutionName = ref('');
const donationProducts = ref([]);
const isFetchingAllLogs = ref(false);

const donationHeaders = [
  { title: 'Producto', key: 'product_name' },
  { title: 'Unds.', key: 'expired_quantity', align: 'end' },
  { title: 'Acción', key: 'actions', sortable: false, align: 'center' },
];


const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString();
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP' }).format(value);
}

const fetchSummary = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/expired-logs/summary');
    summaryData.value = response.data;
  } catch (error) {
    console.error('Hubo un error al obtener el resumen de caducados:', error);
  } finally {
    loading.value = false;
  }
};

const fetchExpiredLogs = async () => {
  loading.value = true;
  const params = {
    q: searchQuery.value,
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };
  Object.keys(params).forEach(key => (params[key] === null || params[key] === '') && delete params[key]);

  try {
    const response = await axios.get('/expired-logs', { params });
    expiredLogs.value = response.data.data;
    totalExpiredLogs.value = response.data.total;
  } catch (error)
  {
    console.error('Hubo un error al obtener los lotes caducados:', error);
  } finally {
    loading.value = false;
  }
}

const fetchAllExpiredLogs = async () => {
  isFetchingAllLogs.value = true;
  try {
    const response = await axios.get('/expired-logs', { params: { itemsPerPage: -1 } });
    donationProducts.value = response.data.data;
  } catch (error) {
    console.error('Error al cargar todos los lotes caducados:', error);
    toast.error('No se pudieron cargar los productos para la donación.');
  } finally {
    isFetchingAllLogs.value = false;
  }
};

const openDonationModal = async () => {
  isDonationModalVisible.value = true;
  await fetchAllExpiredLogs();
};

const closeDonationModal = () => {
  isDonationModalVisible.value = false;
  institutionName.value = '';
  donationProducts.value = [];
};

const discardProductFromDonation = (productToDiscard) => {
  donationProducts.value = donationProducts.value.filter(p => p.id !== productToDiscard.id);
  toast.success(`"${productToDiscard.product_name}" descartado de la donación.`);
};

const generateDonationLetter = () => {
  if (!institutionName.value.trim()) {
    toast.warning('Por favor, ingrese el nombre de la institución.');
    return;
  }

  console.log('--- CARTA DE DONACIÓN GENERADA ---');
  console.log('Institución:', institutionName.value);
  console.log('Productos a donar:', donationProducts.value);
  console.log('Total de productos:', donationProducts.value.length);
  
  toast.success('Carta de donación generada con éxito (ver consola).');
  closeDonationModal();
};

let debounceTimer;
watch(
  [page, itemsPerPage, sortBy, orderBy, searchQuery],
  () => {
    if (isDetailViewVisible.value) {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => fetchExpiredLogs(), 300);
    }
  }, 
  { deep: true }
);

watch(searchQuery, () => {
  page.value = 1;
});

onMounted(() => {
  fetchSummary();
});

const updateTableOptions = options => {
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const showDetailView = () => {
  isDetailViewVisible.value = true;
  fetchExpiredLogs();
}

const showSummaryView = () => {
  isDetailViewVisible.value = false;
}

const handleActivateOffer = (item) => {
  toast.info(`Funcionalidad 'Activar Oferta' para "${item.product_name}" no implementada.`);
  console.log('Activar oferta para:', item);
}

</script>

<template>
  <div>
    <!-- VISTA DE RESUMEN -->
    <VCard v-if="!isDetailViewVisible" title="Resumen de Lotes Caducados">
      <VDataTable
        :headers="summaryHeaders"
        :items="[summaryData]"
        :loading="loading"
        hide-default-footer
        hide-default-header
        class="text-no-wrap"
      >
        <template #item.date_range="{ item }">
          <span v-if="item.oldest_date">{{ formatDate(item.oldest_date) }} — {{ formatDate(item.newest_date) }}</span>
          <span v-else>No hay registros</span>
        </template>
        <template #item.total_lost_value="{ item }">
          <span>{{ formatCurrency(item.total_lost_value) }}</span>
        </template>
        <template #item.actions>
          <div class="d-flex justify-center gap-2">
            <VBtn color="secondary" @click="openDonationModal">
              Carta de Donativo
            </VBtn>
            <VBtn color="primary" @click="showDetailView">
              Ver Listado
            </VBtn>
          </div>
        </template>
        <template #bottom></template>
      </VDataTable>
    </VCard>

    <!-- VISTA DE DETALLE -->
    <div v-else>
      <VCardTitle class="d-flex align-center">
        <span>Listado de los productos que se han vencido a lo largo del tiempo</span>
        <VSpacer />
        <VBtn 
          prepend-icon="tabler-arrow-left" 
          @click="showSummaryView"
        >
          Volver al Resumen
        </VBtn>
      </VCardTitle>

      <ExpirationsFilters v-model:searchQuery="searchQuery" />

      <VDataTable
        :headers="detailHeaders"
        :items="expiredLogs"
        :items-per-page="itemsPerPage"
        :page="page"
        :items-length="totalExpiredLogs"
        :loading="loading"
        class="text-no-wrap"
        @update:options="updateTableOptions"
      >
        <template #item.product_info="{ item }">
          <div v-if="item.product">
            <div class="font-weight-bold">{{ item.product.name }}</div>
            <small class="text-muted d-block">{{ item.product.active_ingredient }}</small>
            <small v-if="item.product.laboratory" class="text-muted d-block">
              Lab: {{ item.product.laboratory.name }}
            </small>
          </div>
          <span v-else>{{ item.product_name }} (Datos no disponibles)</span>
        </template>

        <template #item.created_at="{ item }">
          {{ formatDate(item.created_at) }}
        </template>
        
        <template #item.actions="{ item }">
          <div class="d-flex gap-1 justify-center">
            <VTooltip text="Activar Oferta">
              <template #activator="{ props }">
                <IconBtn v-bind="props" @click="handleActivateOffer(item)">
                  <VIcon icon="tabler-tag" color="info" />
                </IconBtn>
              </template>
            </VTooltip>

            <VTooltip text="Este lote ya ha sido caducado">
               <template #activator="{ props }">
                <div v-bind="props">
                   <IconBtn disabled>
                     <VIcon icon="tabler-calendar-off" />
                   </IconBtn>
                </div>
              </template>
            </VTooltip>
          </div>
        </template>
      </VDataTable>
    </div>

    <!-- MODAL PARA LA CARTA DE DONACIÓN -->
    <VDialog
      v-model="isDonationModalVisible"
      max-width="800px"
      persistent
    >
      <VCard :loading="isFetchingAllLogs">
        <VCardTitle class="d-flex align-center">
          <span>Generar Carta de Donativo</span>
          <VSpacer />
          <VBtn icon variant="text" @click="closeDonationModal">
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </VCardTitle>

        <VDivider />

        <VCardText>
          <VTextField
            v-model="institutionName"
            label="Nombre de la Institución"
            variant="outlined"
            class="mb-4"
          />

          <p class="font-weight-medium">Productos a donar:</p>

          <VDataTable
            :headers="donationHeaders"
            :items="donationProducts"
            density="compact"
            class="mb-4"
            height="300px"
            fixed-header
            no-data-text="No hay productos para donar o todos han sido descartados."
          >
            <template #item.actions="{ item }">
              <VTooltip text="Descartar de la donación">
                <template #activator="{ props }">
                  <IconBtn v-bind="props" @click="discardProductFromDonation(item)">
                    <VIcon icon="tabler-trash" color="error" />
                  </IconBtn>
                </template>
              </VTooltip>
            </template>
          </VDataTable>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn color="secondary" variant="outlined" @click="closeDonationModal">Cancelar</VBtn>
          <VBtn color="primary" variant="flat" @click="generateDonationLetter">Generar Carta</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
