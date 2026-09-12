<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useDisplay } from 'vuetify'
import ChronicClientMobileCard from '@/components/cards/ChronicClientMobileCard.vue'
import AppFilterBase from '@/components/AppFilterBase.vue'
import TablePagination from '@/@core/components/TablePagination.vue'
import { $api } from '@/utils/api'
import { toast } from '@/plugins/sweetalert'

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
  consumption_type: 'chronic',
  treatment_duration_days: 30,
})

// Estado de datos
const loading = ref(false)
const statsLoading = ref(false)

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
]

const productConsumptionFilterOptions = [
  { title: 'Todos los Tipos', value: 'all' },
  { title: 'Crónico (Uso Continuo)', value: 'chronic' },
  { title: 'Tratamiento Único / Ciclo', value: 'single_treatment' },
  { title: 'Sin Alerta / Insumos', value: 'no_alert' },
  { title: 'Esporádico / Ocasional', value: 'sporadic' },
]

const formConsumptionTypes = [
  { title: 'Crónico (Uso Continuo - Recompra Recurrente)', value: 'chronic' },
  { title: 'Tratamiento Único / Ciclo (Seguimiento al finalizar)', value: 'single_treatment' },
  { title: 'Sin Alerta / Insumos (No alertar ni recordar)', value: 'no_alert' },
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
  selectedProduct.consumption_type = item.consumption_type || 'chronic'
  selectedProduct.treatment_duration_days = item.treatment_duration_days || (item.consumption_type === 'single_treatment' ? 7 : 30)
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
    toast.success('Clasificación de producto actualizada correctamente.')
    editDialog.value = false
    await fetchProductsConfig()
    await fetchStats()
    await fetchChronicClients()
    fetchChronicProductsList()
  } catch (e) {
    console.error(e)
    toast.error('No se pudo actualizar la clasificación del producto.')
  } finally {
    savingProduct.value = false
  }
}

const getConsumptionBadge = (type) => {
  switch (type) {
    case 'chronic': return { color: 'primary', label: 'Crónico (Recurrente)', icon: 'tabler-repeat' }
    case 'single_treatment': return { color: 'warning', label: 'Tratamiento Único', icon: 'tabler-calendar-event' }
    case 'no_alert': return { color: 'secondary', label: 'Sin Alerta / Insumos', icon: 'tabler-bell-off' }
    case 'sporadic': return { color: 'secondary', label: 'Esporádico', icon: 'tabler-shopping-bag' }
    default: return { color: 'secondary', label: 'Sin Clasificar', icon: 'tabler-help' }
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
  consumptionTypeFilter.value = 'all'
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
    <div v-show="activeTab === 'patients'" class="d-flex flex-column gap-y-4">
      <AppFilterBase
        :search="searchQuery"
        :has-advanced-filters="statusFilter !== 'all' || consumptionTypeFilter !== 'all' || !!productFilter"
        search-placeholder="Buscar por paciente, cédula, teléfono o medicamento..."
        class="py-1"
        @update:search="searchQuery = $event"
        @clear="resetFilters"
      >
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
              No hay pacientes con medicamentos de seguimiento para mostrar. Configura los productos en la pestaña "Clasificación de Productos".
            </div>
            <VBtn variant="tonal" color="primary" @click="resetFilters">
              Limpiar Filtros
            </VBtn>
          </div>
        </template>
      </VDataTableServer>
    </VCard>
    </div>

    <!-- PESTAÑA 2: CLASIFICACIÓN DE PRODUCTOS -->
    <div v-show="activeTab === 'products'" class="d-flex flex-column gap-y-4">
      <AppFilterBase
        :search="productSearchQuery"
        :has-advanced-filters="productConsumptionFilter !== 'all'"
        search-placeholder="Buscar medicamento por nombre, código o principio activo..."
        class="py-1"
        @update:search="productSearchQuery = $event"
        @clear="resetProductFilters"
      >
        <template #advanced-filters>
          <VCol cols="12" sm="6" md="4">
            <VSelect
              v-model="productConsumptionFilter"
              :items="productConsumptionFilterOptions"
              item-title="title"
              item-value="value"
              label="Filtrar por Tipo de Consumo"
              density="compact"
              hide-details
              prepend-inner-icon="tabler-category"
            />
          </VCol>
        </template>
      </AppFilterBase>

      <VCard variant="flat" border class="rounded-xl">
        <VDataTableServer
          v-model:page="productPage"
          v-model:items-per-page="productPerPage"
          :headers="productConfigHeaders"
          :items="productsConfigList"
          :items-length="totalProducts"
          :loading="productsLoading"
          density="comfortable"
          class="elevation-0"
          @update:options="fetchProductsConfig"
        >
          <!-- ID / Código -->
          <template #item.barcode="{ item }">
            <div class="font-weight-medium text-high-emphasis">
              {{ item.barcode || 'S/C' }}
            </div>
          </template>

          <!-- Nombre -->
          <template #item.name="{ item }">
            <div class="py-2">
              <div class="font-weight-bold text-high-emphasis">{{ item.name }}</div>
              <div v-if="item.active_ingredient" class="text-caption text-medium-emphasis">
                {{ item.active_ingredient }}
              </div>
            </div>
          </template>

          <!-- Categoría -->
          <template #item.category="{ item }">
            <span class="text-caption text-medium-emphasis">
              {{ item.category?.name || item.laboratory?.name || 'General' }}
            </span>
          </template>

          <!-- Tipo de Consumo -->
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

          <!-- Duración Estimada -->
          <template #item.treatment_duration_days="{ item }">
            <span v-if="item.consumption_type === 'chronic' || item.consumption_type === 'single_treatment'" class="font-weight-medium">
              {{ item.treatment_duration_days || (item.consumption_type === 'chronic' ? 30 : 7) }} días / caja
            </span>
            <span v-else class="text-caption text-disabled">No aplica</span>
          </template>

          <!-- Automatización -->
          <template #item.automation="{ item }">
            <VChip
              v-if="item.consumption_type === 'chronic'"
              color="primary"
              size="x-small"
              variant="flat"
            >
              Recordatorio Recurrente
            </VChip>
            <VChip
              v-else-if="item.consumption_type === 'single_treatment'"
              color="warning"
              size="x-small"
              variant="flat"
            >
              Seguimiento de Fin de Ciclo
            </VChip>
            <VChip
              v-else-if="item.consumption_type === 'no_alert'"
              color="grey"
              size="x-small"
              variant="tonal"
            >
              Sin Alerta
            </VChip>
            <span v-else class="text-caption text-disabled">Sin alerta</span>
          </template>

          <!-- Acciones -->
          <template #item.actions="{ item }">
            <VBtn
              icon="tabler-edit"
              size="small"
              variant="tonal"
              color="primary"
              title="Configurar Consumo"
              @click="openEditProduct(item)"
            />
          </template>

          <template #no-data>
            <div class="py-8 text-center">
              <VAvatar color="primary" variant="tonal" size="56" class="mb-3">
                <VIcon icon="tabler-pill" size="32" />
              </VAvatar>
              <div class="text-h6 font-weight-bold">No hay productos encontrados</div>
              <div class="text-caption text-medium-emphasis mb-4">
                No se encontraron productos con los términos de búsqueda.
              </div>
              <VBtn variant="tonal" color="primary" @click="resetProductFilters">
                Limpiar Filtros
              </VBtn>
            </div>
          </template>
        </VDataTableServer>
      </VCard>
    </div>

    <!-- Modal para Configurar Tipo de Consumo del Producto -->
    <VDialog v-model="editDialog" max-width="550" persistent>
      <VCard class="rounded-xl pa-2">
        <VCardItem>
          <template #prepend>
            <VAvatar color="primary" variant="tonal" size="48">
              <VIcon icon="tabler-adjustments-horizontal" size="26" />
            </VAvatar>
          </template>
          <VCardTitle class="text-h6 font-weight-bold">
            Configurar Consumo y Frecuencia
          </VCardTitle>
          <VCardSubtitle class="text-wrap">
            {{ selectedProduct.name }}
          </VCardSubtitle>
        </VCardItem>

        <VCardText class="pt-2">
          <VRow dense>
            <VCol cols="12">
              <VSelect
                v-model="selectedProduct.consumption_type"
                :items="formConsumptionTypes"
                item-title="title"
                item-value="value"
                label="Tipo de Consumo *"
                density="comfortable"
                class="mb-3"
              />
            </VCol>

            <VCol cols="12" v-if="selectedProduct.consumption_type === 'chronic' || selectedProduct.consumption_type === 'single_treatment'">
              <VTextField
                v-model.number="selectedProduct.treatment_duration_days"
                label="Duración del Tratamiento / Caja (Días) *"
                type="number"
                min="1"
                max="365"
                density="comfortable"
                :hint="selectedProduct.consumption_type === 'chronic' ? 'Días de cobertura estimada por cada unidad comprada (ej: 30 días).' : 'Días que dura el ciclo completo antes de realizar seguimiento (ej: 7 o 14 días).'"
                persistent-hint
              />
            </VCol>

            <VCol cols="12" v-if="selectedProduct.consumption_type === 'no_alert'">
              <VAlert type="info" variant="tonal" density="compact" class="mt-2">
                Este producto (insumo / descartable) quedará marcado sin alertas. Las futuras compras no generarán recordatorios ni aparecerán en la lista de pacientes crónicos.
              </VAlert>
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions class="justify-end gap-2 pa-4">
          <VBtn
            variant="tonal"
            color="secondary"
            :disabled="savingProduct"
            @click="editDialog = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            variant="flat"
            color="primary"
            :loading="savingProduct"
            @click="saveProductConsumption"
          >
            Guardar Configuración
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