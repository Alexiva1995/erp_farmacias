<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useDisplay } from 'vuetify'
import ChronicClientMobileCard from '@/components/cards/ChronicClientMobileCard.vue'
import TablePagination from '@/@core/components/TablePagination.vue'
import { $api } from '@/utils/api'

const { mobile: isMobile } = useDisplay()

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
const statusOptions = [
  { title: 'Todos los Estados', value: 'all' },
  { title: 'Alerta Urgente (≤ 5 días)', value: 'urgent' },
  { title: 'Tratamiento Activo (> 5 días)', value: 'active' },
  { title: 'Tratamiento Vencido', value: 'expired' },
]

// Headers para la tabla en escritorio
const headers = [
  { title: 'Paciente / Cliente', key: 'client_name', sortable: false },
  { title: 'Medicamento Crónico', key: 'product_name', sortable: false },
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
    // Si la búsqueda general de productos no soporta is_chronic, fallback silencioso
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

watch([statusFilter, productFilter], () => {
  page.value = 1
  fetchChronicClients()
})

onMounted(() => {
  fetchStats()
  fetchChronicClients()
  fetchChronicProductsList()
})
</script>

<template>
  <div class="chronic-clients-page">
    <!-- Encabezado de Página -->
    <div class="d-flex flex-wrap align-center justify-space-between gap-4 mb-6">
      <div>
        <h2 class="text-h4 font-weight-bold text-high-emphasis">
          Seguimiento de Pacientes Crónicos
        </h2>
        <div class="text-subtitle-1 text-medium-emphasis mt-1">
          Detección de recompra y recordatorios automatizados de tratamiento
        </div>
      </div>

      <div class="d-flex align-center gap-2">
        <VBtn
          color="primary"
          variant="tonal"
          prepend-icon="tabler-refresh"
          :loading="loading || statsLoading"
          @click="fetchStats(); fetchChronicClients();"
        >
          Actualizar Datos
        </VBtn>
      </div>
    </div>

    <!-- KPI Cards Superiores -->
    <VRow dense class="mb-4">
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

    <!-- Barra de Filtros Nativa -->
    <VCard variant="flat" border class="pa-4 rounded-xl mb-4">
      <VRow dense align="center">
        <VCol cols="12" md="5">
          <VTextField
            v-model="searchQuery"
            prepend-inner-icon="tabler-search"
            placeholder="Buscar por paciente, cédula, teléfono o medicamento..."
            density="compact"
            variant="outlined"
            clearable
            hide-details
          />
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <VSelect
            v-model="statusFilter"
            :items="statusOptions"
            item-title="title"
            item-value="value"
            label="Estado de Tratamiento"
            density="compact"
            variant="outlined"
            hide-details
          />
        </VCol>

        <VCol cols="12" sm="6" md="3" v-if="productsList.length > 0">
          <VAutocomplete
            v-model="productFilter"
            :items="productsList"
            item-title="name"
            item-value="id"
            label="Medicamento"
            density="compact"
            variant="outlined"
            clearable
            hide-details
          />
        </VCol>

        <VCol cols="12" md="1" class="d-flex justify-end">
          <VBtn
            variant="text"
            color="secondary"
            icon="tabler-filter-off"
            title="Limpiar filtros"
            @click="resetFilters"
          />
        </VCol>
      </VRow>
    </VCard>

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
              Dosis comprada: {{ item.purchased_quantity }} un. ({{ item.total_treatment_days }} días de cobertura)
            </div>
          </div>
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