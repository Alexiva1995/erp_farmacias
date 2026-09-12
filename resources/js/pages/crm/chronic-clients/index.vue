<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useDisplay } from 'vuetify'
import ChronicClientMobileCard from '@/components/cards/ChronicClientMobileCard.vue'
import TablePagination from '@/@core/components/TablePagination.vue'
import { $api } from '@/utils/api'

const { mobile: isMobile } = useDisplay()

const activeTab = ref('patients')
const consumptionTypeFilter = ref('all')

const productsLoading = ref(false)
const productsConfigList = ref([])
const totalProducts = ref(0)
const productPage = ref(1)
const productPerPage = ref(15)
const productSearchQuery = ref('')
const productConsumptionFilter = ref('all')

const editDialog = ref(false)
const savingProduct = ref(false)
const selectedProduct = reactive({
  id: null,
  name: '',
  consumption_type: 'sporadic',
  treatment_duration_days: 30,
})

// Estado de datos
const loading = ref(false)
const statsLoading = ref(false)
const aiSyncing = ref(false)
const aiSyncSuccessDialog = ref(false)
const aiSyncResult = reactive({
  total_analyzed: 0,
  chronic_detected: 0,
  updated_count: 0,
  ai_assisted: false,
})

const chronicClients = ref([])
const totalRecords = ref(0)
const page = ref(1)
const perPage = ref(15)

// Filtros
const searchQuery = ref('')
const statusFilter = ref('all')
const productFilter = ref(null)
const productsList = ref([])

// Estadísticas
const stats = reactive({
  total_patients: 0,
  urgent_reminders: 0,
  active_treatments: 0,
  expired_treatments: 0,
  total_treatments: 0,
})

// Opciones de Estado para filtro
const consumptionTypeOptions = [
  { title: 'Todos los Tipos', value: 'all' },
  { title: 'Crónico (Uso Continuo)', value: 'chronic' },
  { title: 'Tratamiento Único / Ciclo', value: 'single_treatment' },
  { title: 'Esporádico / Ocasional', value: 'sporadic' },
]

const productConfigHeaders = [
  { title: 'ID / Código', key: 'barcode', sortable: false },
  { title: 'Medicamento / Producto', key: 'name', sortable: false },
  { title: 'Categoría', key: 'category', sortable: false },
  { title: 'Tipo de Consumo', key: 'consumption_type', sortable: false },
  { title: 'Duración Estimada', key: 'treatment_duration_days', sortable: false },
  { title: 'Automatización WhatsApp', key: 'automation', sortable: false },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'center' },
]

const statusOptions = [
  { title: 'Todos los Estados', value: 'all' },
  { title: 'Alerta Urgente (≤ 5 días)', value: 'urgent' },
  { title: 'Tratamiento Activo (> 5 días)', value: 'active' },
  { title: 'Tratamiento Vencido', value: 'expired' },
]

// Headers para la tabla en escritorio
const headers = [
  { title: 'Paciente / Cliente', key: 'client_name', sortable: false },
  { title: 'Medicamento / Frecuencia', key: 'product_name', sortable: false },
  { title: 'Tipo de Consumo', key: 'consumption_type', sortable: false },
  { title: 'Última Compra', key: 'last_order_date_formatted', sortable: false },
  { title: 'Duración / Fin', key: 'treatment_end_date_formatted', sortable: false },
  { title: 'Precio Actual', key: 'pricing', sortable: false },
  { title: 'Estado', key: 'status_label', sortable: false },
  { title: 'Acción WhatsApp', key: 'actions', sortable: false, align: 'center' },
]

// Cargar estadísticas
const fetchStats = async () => {
  statsLoading.value = true
  try {
    const res = await $api('/crm/chronic-clients/stats')
    if (res?.data) {
      Object.assign(stats, res.data)
    }
  } catch (error) {
    console.error('Error fetching chronic stats:', error)
  } finally {
    statsLoading.value = false
  }
}

// Cargar listado de clientes crónicos
const fetchChronicClients = async () => {
  loading.value = true
  try {
    const params = {
      page: page.value,
      itemsPerPage: perPage.value,
      search: searchQuery.value || undefined,
      status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
      product_id: productFilter.value || undefined,
    }

    const res = await $api('/crm/chronic-clients', { params })
    if (res?.data) {
      chronicClients.value = res.data.items || []
      totalRecords.value = res.data.total || 0
    }
  } catch (error) {
    console.error('Error fetching chronic clients:', error)
  } finally {
    loading.value = false
  }
}

// Cargar productos crónicos para el selector de filtro
const fetchProductsConfig = async () => {
  productsLoading.value = true
  try {
    const params = {
      page: productPage.value,
      itemsPerPage: productPerPage.value,
      search: productSearchQuery.value || undefined,
      consumption_type: productConsumptionFilter.value !== 'all' ? productConsumptionFilter.value : undefined,
    }
    const res = await $api('/crm/chronic-clients/products-config', { params })
    if (res?.data) {
      productsConfigList.value = res.data.items || []
      totalProducts.value = res.data.total || 0
    }
  } catch (e) { console.error(e) }
  finally { productsLoading.value = false }
}

const openEditProduct = (item) => {
  selectedProduct.id = item.id
  selectedProduct.name = item.name
  selectedProduct.consumption_type = item.consumption_type || 'sporadic'
  selectedProduct.treatment_duration_days = item.treatment_duration_days || 30
  editDialog.value = true
}

const saveProductConsumption = async () => {
  savingProduct.value = true
  try {
    await $api(`/crm/chronic-clients/products-config/${selectedProduct.id}`, {
      method: 'PUT',
      data: {
        consumption_type: selectedProduct.consumption_type,
        treatment_duration_days: selectedProduct.treatment_duration_days,
      },
    })
    toast.success('Tipo de consumo actualizado correctamente.')
    editDialog.value = false
    await fetchProductsConfig()
    await fetchStats()
    await fetchChronicClients()
  } catch (e) {
    console.error(e)
    toast.error('No se pudo actualizar el producto.')
  } finally {
    savingProduct.value = false
  }
}

const getConsumptionBadge = (type) => {
  switch (type) {
    case 'chronic': return { color: 'primary', label: 'Crónico', icon: 'tabler-repeat' }
    case 'single_treatment': return { color: 'warning', label: 'Tratamiento Único', icon: 'tabler-calendar-event' }
    default: return { color: 'secondary', label: 'Esporádico', icon: 'tabler-shopping-bag' }
  }
}

const resetProductFilters = () => {
  productSearchQuery.value = ''
  productConsumptionFilter.value = 'all'
  productPage.value = 1
  fetchProductsConfig()
}

const fetchChronicProductsList = async () => {
  try {
    const res = await $api('/products/search', { params: { is_chronic: 1, per_page: 100 } })
    if (res?.data?.data) {
      productsList.value = res.data.data.map(p => ({
        id: p.id,
        name: `${p.name} (${p.treatment_duration_days || 30} días)`,
      }))
    }
  } catch (e) {
    // Fallback silencioso si no aplica
  }
}

// Clasificar / Escanear catálogo con IA
const runAiChronicSync = async () => {
  aiSyncing.value = true
  try {
    const res = await $api('/crm/chronic-clients/sync-ai', { method: 'POST' })
    if (res?.data) {
      Object.assign(aiSyncResult, res.data)
      aiSyncSuccessDialog.value = true
      await fetchStats()
      await fetchChronicClients()
      await fetchProductsConfig()
  fetchChronicProductsList()
    }
  } catch (error) {
    console.error('Error syncing chronic products with AI:', error)
  } finally {
    aiSyncing.value = false
  }
}

// Helpers de estilo para estado
const getStatusColor = (item) => {
  if (item.is_urgent) return 'warning'
  if (item.is_active) return 'success'
  return 'error'
}

const getStatusLabel = (item) => {
  if (item.is_urgent) return 'Alerta (≤ 5 días)'
  if (item.is_active) return 'Activo'
  return 'Agotado'
}

const getStatusIcon = (item) => {
  if (item.is_urgent) return 'tabler-alert-triangle'
  if (item.is_active) return 'tabler-circle-check'
  return 'tabler-clock-off'
}

// Resetear filtros
const resetFilters = () => {
  searchQuery.value = ''
  statusFilter.value = 'all'
  productFilter.value = null
  page.value = 1
  fetchChronicClients()
}

// Disparador debounce para búsqueda
let searchTimeout = null
watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    page.value = 1
    fetchChronicClients()
  }, 400)
})

watch([statusFilter, consumptionTypeFilter, productFilter], () => {
  page.value = 1
  fetchChronicClients()
})

onMounted(() => {
  fetchStats()
  fetchChronicClients()
  fetchProductsConfig()
  fetchChronicProductsList()
})
</script>

<template>
  <div class="chronic-clients-page">
    <!-- Encabezado de Página -->
    <VCard variant="flat" class="pa-4 border rounded-lg mb-4">
      <div class="d-flex align-center justify-space-between flex-wrap gap-4">
        <div class="d-flex align-center">
          <VAvatar color="primary" variant="tonal" size="48" class="me-3">
            <VIcon icon="tabler-heart-rate-monitor" size="28" />
          </VAvatar>
          <div>
            <h1 class="text-h5 font-weight-black text-high-emphasis leading-tight mb-0">
              Seguimiento de Pacientes y Frecuencia de Recompra
            </h1>
            <p class="text-caption text-medium-emphasis mb-0">
              Gestión de consumos crónicos, ciclos de tratamiento único y recordatorios automatizados por WhatsApp.
            </p>
          </div>
        </div>

        <VTabs v-model="activeTab" color="primary" density="compact">
          <VTab value="patients">
            <VIcon start icon="tabler-users" size="18" />
            Pacientes y Recompras
          </VTab>
          <VTab value="products">
            <VIcon start icon="tabler-pill" size="18" />
            Clasificación de Productos
          </VTab>
        </VTabs>
      </div>
    </VCard>

    <!-- KPI Cards Superiores -->
    <VRow dense class="mb-4" v-if="activeTab === 'patients'">
      <VCol cols="12" sm="6" md="3">
        <VCard variant="flat" border class="pa-4 rounded-xl stat-card">
          <div class="d-flex align-center justify-space-between">
            <div>
              <div class="text-caption font-weight-bold text-disabled text-uppercase">Total Pacientes</div>
              <div class="text-h4 font-weight-black mt-1 text-primary">{{ stats.total_patients }}</div>
              <div class="text-caption text-medium-emphasis mt-1">{{ stats.total_treatments }} tratamientos registrados</div>
            </div>
            <VAvatar color="primary" variant="tonal" rounded="lg" size="48">
              <VIcon icon="tabler-users" size="28" />
            </VAvatar>
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard
          variant="flat"
          border
          class="pa-4 rounded-xl stat-card cursor-pointer"
          :class="statusFilter === 'urgent' ? 'border-warning border-opacity-100' : ''"
          @click="statusFilter = 'urgent'"
        >
          <div class="d-flex align-center justify-space-between">
            <div>
              <div class="text-caption font-weight-bold text-warning text-uppercase">Recordatorio Urgente</div>
              <div class="text-h4 font-weight-black mt-1 text-warning">{{ stats.urgent_reminders }}</div>
              <div class="text-caption text-medium-emphasis mt-1">Tratamientos a límite (≤ 5 días)</div>
            </div>
            <VAvatar color="warning" variant="tonal" rounded="lg" size="48">
              <VIcon icon="tabler-alert-triangle" size="28" />
            </VAvatar>
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard
          variant="flat"
          border
          class="pa-4 rounded-xl stat-card cursor-pointer"
          :class="statusFilter === 'active' ? 'border-success border-opacity-100' : ''"
          @click="statusFilter = 'active'"
        >
          <div class="d-flex align-center justify-space-between">
            <div>
              <div class="text-caption font-weight-bold text-success text-uppercase">Tratamientos Activos</div>
              <div class="text-h4 font-weight-black mt-1 text-success">{{ stats.active_treatments }}</div>
              <div class="text-caption text-medium-emphasis mt-1">Con cobertura de medicamento</div>
            </div>
            <VAvatar color="success" variant="tonal" rounded="lg" size="48">
              <VIcon icon="tabler-pill" size="28" />
            </VAvatar>
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard
          variant="flat"
          border
          class="pa-4 rounded-xl stat-card cursor-pointer"
          :class="statusFilter === 'expired' ? 'border-error border-opacity-100' : ''"
          @click="statusFilter = 'expired'"
        >
          <div class="d-flex align-center justify-space-between">
            <div>
              <div class="text-caption font-weight-bold text-error text-uppercase">Tratamientos Vencidos</div>
              <div class="text-h4 font-weight-black mt-1 text-error">{{ stats.expired_treatments }}</div>
              <div class="text-caption text-medium-emphasis mt-1">Días de dosis agotados</div>
            </div>
            <VAvatar color="error" variant="tonal" rounded="lg" size="48">
              <VIcon icon="tabler-clock-off" size="28" />
            </VAvatar>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- PESTAÑA 1: PACIENTES Y RECOMPRAS -->
    <div v-show="activeTab === 'patients'">
      <AppFilterBase
        :search="searchQuery"
        :has-advanced-filters="statusFilter !== 'all' || consumptionTypeFilter !== 'all' || !!productFilter"
        search-placeholder="Buscar por paciente, cédula, teléfono o medicamento..."
        class="py-1"
        @update:search="searchQuery = $event"
        @clear="resetFilters"
      >
        <template #prepend-actions>
          <VBtn
            color="primary"
            variant="flat"
            prepend-icon="tabler-sparkles"
            :loading="aiSyncing"
            class="me-1"
            @click="runAiChronicSync"
          >
            Detectar con IA
          </VBtn>

          <VBtn
            color="secondary"
            variant="tonal"
            icon="tabler-refresh"
            size="38"
            rounded="circle"
            :loading="loading || statsLoading"
            title="Actualizar datos"
            class="me-1"
            @click="fetchStats(); fetchChronicClients();"
          />
        </template>

        <template #advanced-filters>
          <VCol cols="12" sm="6" md="4">
            <VSelect
              v-model="statusFilter"
              :items="statusOptions"
              item-title="title"
              item-value="value"
              label="Estado de Tratamiento"
              density="compact"
              hide-details
              prepend-inner-icon="tabler-clock"
            />
          </VCol>

          <VCol cols="12" sm="6" md="4">
            <VSelect
              v-model="consumptionTypeFilter"
              :items="consumptionTypeOptions"
              item-title="title"
              item-value="value"
              label="Tipo de Consumo"
              density="compact"
              hide-details
              prepend-inner-icon="tabler-category"
            />
          </VCol>

          <VCol cols="12" sm="6" md="4" v-if="productsList.length > 0">
            <VAutocomplete
              v-model="productFilter"
              :items="productsList"
              item-title="name"
              item-value="id"
              label="Medicamento Específico"
              density="compact"
              clearable
              hide-details
              prepend-inner-icon="tabler-pill"
            />
          </VCol>
        </template>
      </AppFilterBase>

    <!-- Vista Móvil: Tarjetas -->
    <div v-if="isMobile">
      <div v-if="loading" class="d-flex flex-column gap-3">
        <VSkeletonLoader
          v-for="n in 4"
          :key="n"
          type="article"
          class="rounded-xl border"
        />
      </div>

      <div v-else-if="chronicClients.length > 0" class="d-flex flex-column gap-3">
        <ChronicClientMobileCard
          v-for="item in chronicClients"
          :key="item.client_id + '_' + item.product_id"
          :client="item"
        />

        <div class="mt-4">
          <TablePagination
            v-model:page="page"
            :items-per-page="perPage"
            :total-items="totalRecords"
            @update:page="fetchChronicClients"
          />
        </div>
      </div>

      <VCard v-else variant="flat" border class="pa-8 text-center rounded-xl">
        <VAvatar color="primary" variant="tonal" size="56" class="mb-3">
          <VIcon icon="tabler-user-search" size="32" />
        </VAvatar>
        <div class="text-h6 font-weight-bold">No se encontraron pacientes</div>
        <div class="text-caption text-medium-emphasis mb-4">
          No hay pacientes crónicos que coincidan con los criterios de búsqueda aplicados.
        </div>
        <VBtn variant="tonal" color="primary" @click="resetFilters">
          Limpiar Filtros
        </VBtn>
      </VCard>
    </div>

    <!-- Vista Escritorio: Tabla Servidor -->
    <VCard v-else variant="flat" border class="rounded-xl">
      <VDataTableServer
        v-model:page="page"
        v-model:items-per-page="perPage"
        :headers="headers"
        :items="chronicClients"
        :items-length="totalRecords"
        :loading="loading"
        density="comfortable"
        class="elevation-0"
        @update:options="fetchChronicClients"
      >
        <!-- Columna Paciente -->
        <template #item.client_name="{ item }">
          <div class="py-2">
            <div class="font-weight-bold text-high-emphasis">
              {{ item.client_name }}
            </div>
            <div class="text-caption text-medium-emphasis d-flex align-center gap-1">
              <VIcon icon="tabler-id" size="14" />
              <span>{{ item.identification || 'S/N' }}</span>
              <span v-if="item.phone" class="ms-2">
                <VIcon icon="tabler-phone" size="14" />
                {{ item.phone }}
              </span>
            </div>
          </div>
        </template>

        <!-- Columna Medicamento -->
        <template #item.product_name="{ item }">
          <div class="py-2">
            <div class="font-weight-semibold text-primary">
              {{ item.product_name }}
            </div>
            <div class="text-caption text-medium-emphasis">
              Dosis: {{ item.purchased_quantity }} un. ({{ item.total_treatment_days }} días estimados)
            </div>
          </div>
        </template>

        <!-- Columna Tipo Consumo -->
        <template #item.consumption_type="{ item }">
          <VChip
            size="small"
            :color="getConsumptionBadge(item.consumption_type).color"
            variant="tonal"
            class="font-weight-medium"
          >
            <VIcon :icon="getConsumptionBadge(item.consumption_type).icon" start size="14" />
            {{ getConsumptionBadge(item.consumption_type).label }}
          </VChip>
        </template>

        <!-- Columna Última Compra -->
        <template #item.last_order_date_formatted="{ item }">
          <div class="py-2">
            <div class="font-weight-medium">{{ item.last_order_date_formatted }}</div>
            <div class="text-caption text-disabled">Lab: {{ item.laboratory_name }}</div>
          </div>
        </template>

        <!-- Columna Duración y Días Restantes -->
        <template #item.treatment_end_date_formatted="{ item }">
          <div class="py-2">
            <div class="d-flex align-center gap-2">
              <span class="font-weight-bold" :class="item.days_until_end <= 5 ? 'text-warning' : 'text-high-emphasis'">
                {{ item.days_until_end }} días restantes
              </span>
            </div>
            <div class="text-caption text-medium-emphasis">
              Fin: {{ item.treatment_end_date_formatted }}
            </div>
          </div>
        </template>

        <!-- Columna Precios Multimoneda -->
        <template #item.pricing="{ item }">
          <div class="py-2">
            <div class="font-weight-bold text-success">
              ${{ Number(item.price_usd || 0).toFixed(2) }}
            </div>
            <div class="text-caption text-medium-emphasis">
              Bs. {{ Number(item.price_bs || 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
            </div>
            <div class="text-caption text-medium-emphasis">
              COP {{ Number(item.price_cop || 0).toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}
            </div>
          </div>
        </template>

        <!-- Columna Estado -->
        <template #item.status_label="{ item }">
          <VChip
            size="small"
            :color="getStatusColor(item)"
            variant="tonal"
            class="font-weight-medium"
          >
            <VIcon :icon="getStatusIcon(item)" start size="14" />
            {{ getStatusLabel(item) }}
          </VChip>
        </template>

        <!-- Columna Acciones WhatsApp -->
        <template #item.actions="{ item }">
          <VBtn
            v-if="item.whatsapp_url"
            color="success"
            variant="flat"
            size="small"
            prepend-icon="tabler-brand-whatsapp"
            :href="item.whatsapp_url"
            target="_blank"
            rel="noopener noreferrer"
            class="rounded-lg text-none"
          >
            WhatsApp
          </VBtn>
          <span v-else class="text-caption text-disabled">Sin teléfono</span>
        </template>

        <!-- Estado Vacío en Tabla -->
        <template #no-data>
          <div class="py-8 text-center">
            <VAvatar color="primary" variant="tonal" size="56" class="mb-3">
              <VIcon icon="tabler-user-search" size="32" />
            </VAvatar>
            <div class="text-h6 font-weight-bold">No se encontraron pacientes</div>
            <div class="text-caption text-medium-emphasis mb-4">
              No hay pacientes crónicos para mostrar con los filtros seleccionados.
            </div>
            <VBtn variant="tonal" color="primary" @click="resetFilters">
              Limpiar Filtros
            </VBtn>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Diálogo de Resultado de Sincronización con IA -->
    <VDialog v-model="aiSyncSuccessDialog" max-width="500">
      <VCard class="rounded-xl pa-2">
        <VCardItem>
          <template #prepend>
            <VAvatar color="primary" variant="tonal" size="48">
              <VIcon icon="tabler-sparkles" size="26" />
            </VAvatar>
          </template>
          <VCardTitle class="text-h6 font-weight-bold">
            Detección con IA Finalizada
          </VCardTitle>
          <VCardSubtitle>
            Análisis de catálogo farmacológico
          </VCardSubtitle>
        </VCardItem>

        <VCardText class="pt-2">
          <div class="d-flex flex-column gap-2">
            <div class="d-flex justify-space-between py-1 border-b">
              <span class="text-medium-emphasis">Productos analizados:</span>
              <span class="font-weight-bold">{{ aiSyncResult.total_analyzed }}</span>
            </div>
            <div class="d-flex justify-space-between py-1 border-b">
              <span class="text-medium-emphasis">Medicamentos crónicos detectados:</span>
              <span class="font-weight-bold text-primary">{{ aiSyncResult.chronic_detected }}</span>
            </div>
            <div class="d-flex justify-space-between py-1 border-b">
              <span class="text-medium-emphasis">Nuevos productos actualizados:</span>
              <span class="font-weight-bold text-success">{{ aiSyncResult.updated_count }}</span>
            </div>
          </div>
        </VCardText>

        <VCardActions class="justify-end">
          <VBtn color="primary" variant="flat" @click="aiSyncSuccessDialog = false">
            Entendido
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.stat-card {
  transition: all 0.2s ease-in-out;
}
.stat-card:hover {
  transform: translateY(-2px);
}
</style>