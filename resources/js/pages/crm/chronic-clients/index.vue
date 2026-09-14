<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useDisplay } from 'vuetify'
import ChronicClientMobileCard from '@/components/cards/ChronicClientMobileCard.vue'
import AppFilterBase from '@/components/AppFilterBase.vue'
import TablePagination from '@/@core/components/TablePagination.vue'
import { $api } from '@/utils/api'
import { toast } from '@/plugins/sweetalert'

const { mobile: isMobile } = useDisplay()

// Estado pestaña 1 — Pacientes
const loading = ref(false)
const statsLoading = ref(false)
const chronicClients = ref([])
const totalRecords = ref(0)
const page = ref(1)
const perPage = ref(15)
const searchQuery = ref('')
const statusFilter = ref('all')
const consumptionTypeFilter = ref('all')
const productFilter = ref(null)
const productsList = ref([])

// Estadísticas y cuota diaria
const stats = reactive({
  total_patients: 0,
  urgent_reminders: 0,
  active_treatments: 0,
  expired_treatments: 0,
  total_treatments: 0,
  today_contacted: 0,
  daily_quota: 5,
  is_quota_completed: false,
})

// Opciones de filtros
const consumptionTypeOptions = [
  { title: 'Todos los Tipos', value: 'all' },
  { title: 'Crónico (Uso Continuo)', value: 'chronic' },
  { title: 'Tratamiento Único / Ciclo', value: 'single_treatment' },
  { title: 'Esporádico / Ocasional', value: 'sporadic' },
]

const statusOptions = [
  { title: 'Todos los Estados', value: 'all' },
  { title: 'Alerta Urgente (≤ 5 días)', value: 'urgent' },
  { title: 'Tratamiento Activo (> 5 días)', value: 'active' },
  { title: 'Tratamiento Vencido', value: 'expired' },
]

const headers = [
  { title: 'Paciente / Cliente', key: 'client_name', sortable: false },
  { title: 'Medicamentos / Frecuencia', key: 'product_name', sortable: false },
  { title: 'Tipo de Consumo', key: 'consumption_type', sortable: false },
  { title: 'Última Compra', key: 'last_order_date_formatted', sortable: false },
  { title: 'Duración', key: 'treatment_end_date_formatted', sortable: false },
  { title: 'Precio Actual', key: 'pricing', sortable: false },
  { title: 'Stock Actual', key: 'stock', sortable: false, align: 'center' },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'center' },
]

// Cargar estadísticas del panel superior
const fetchStats = async () => {
  statsLoading.value = true
  try {
    const res = await $api('/crm/chronic-clients/stats')
    if (res?.data) Object.assign(stats, res.data)
  } catch (error) {
    console.error('Error fetchStats:', error)
  } finally {
    statsLoading.value = false
  }
}

// Cargar listado de pacientes crónicos
const fetchChronicClients = async () => {
  loading.value = true
  try {
    const params = {
      page: page.value,
      itemsPerPage: perPage.value,
      search: searchQuery.value || undefined,
      status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
      consumption_type: consumptionTypeFilter.value !== 'all' ? consumptionTypeFilter.value : undefined,
      product_id: productFilter.value || undefined,
    }
    const res = await $api('/crm/chronic-clients', { params })
    if (res?.data) {
      chronicClients.value = res.data.items || []
      totalRecords.value = res.data.total || 0
    }
  } catch (error) {
    console.error('Error fetchChronicClients:', error)
  } finally {
    loading.value = false
  }
}

// Handler ejecutado cuando se actualiza la configuración de un producto desde el subcomponente
const onProductConfigUpdated = () => {
  fetchStats()
  fetchChronicClients()
  fetchChronicProductsList()
}

// Estado de carga por cliente al marcar contacto
const contactingClientId = ref(null)

// Marcar paciente como contactado por WhatsApp
const markContacted = async (item) => {
  contactingClientId.value = item.client_id
  try {
    const payload = {
      client_id: item.client_id,
      product_ids: item.product_ids || (item.product_id ? [item.product_id] : []),
    }

    await $api('/crm/chronic-clients/mark-contacted', {
      method: 'POST',
      body: payload,
      data: payload,
    })

    toast.success(`Seguimiento de "${item.client_name}" marcado como contactado exitosamente.`)

    // Recargar lista y estadísticas
    await fetchChronicClients()
    fetchStats()
  } catch (e) {
    console.error('Error markContacted:', e)
    const errorMsg = e?.response?._data?.message || e?.message || 'No se pudo registrar el seguimiento.'
    toast.error(errorMsg)
  } finally {
    contactingClientId.value = null
  }
}

// Abrir WhatsApp codificando el mensaje en el cliente con encodeURIComponent tras validar disponibilidad
const verifyingWhatsAppClientId = ref(null)

const openWhatsApp = async (item) => {
  if (!item.phone && !item.whatsapp_url) return

  // Verificar si otro usuario ya atendió al paciente
  verifyingWhatsAppClientId.value = item.client_id
  try {
    const checkRes = await $api(`/crm/chronic-clients/check-availability/${item.client_id}`)
    if (checkRes?.data && !checkRes.data.available) {
      toast.warning(checkRes.data.message || 'Este paciente ya fue atendido por otro usuario.')
      await fetchChronicClients()
      fetchStats()
      return
    }
  } catch (err) {
    console.error('Error checkAvailability:', err)
  } finally {
    verifyingWhatsAppClientId.value = null
  }

  let phone = item.clean_phone || ''
  if (!phone && item.phone) {
    let raw = String(item.phone).replace(/[^0-9]/g, '')
    if (raw.startsWith('0') && raw.length === 11) {
      phone = '58' + raw.slice(1)
    } else if (raw.length === 10) {
      phone = '58' + raw
    } else {
      phone = raw
    }
  }

  const message = item.whatsapp_message || ''
  const encoded = encodeURIComponent(message)
  const url = `https://api.whatsapp.com/send?phone=${phone}&text=${encoded}`
  window.open(url, '_blank', 'noopener,noreferrer')
}

// Obtener badge visual según tipo de consumo
const getConsumptionBadge = (type) => {
  switch (type) {
    case 'chronic':          return { color: 'primary',   label: 'Crónico (Recurrente)', icon: 'tabler-repeat' }
    case 'single_treatment': return { color: 'warning',   label: 'Tratamiento Único',    icon: 'tabler-calendar-event' }
    case 'no_alert':         return { color: 'secondary', label: 'Sin Alerta / Insumos', icon: 'tabler-bell-off' }
    case 'sporadic':         return { color: 'secondary', label: 'Esporádico',            icon: 'tabler-shopping-bag' }
    default:                 return { color: 'error',     label: 'Sin Clasificar',       icon: 'tabler-help' }
  }
}

// Resetear filtros de pestaña 1
const resetFilters = () => {
  searchQuery.value = ''
  statusFilter.value = 'all'
  consumptionTypeFilter.value = 'all'
  productFilter.value = null
  page.value = 1
  fetchChronicClients()
}

// Cargar lista de productos crónicos para el autocomplete de filtro
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
    // Fallback silencioso
  }
}

// Helpers de estado para pestaña 1
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

// Watchers pestaña 1: búsqueda con debounce
let searchTimeout = null
watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    page.value = 1
    fetchChronicClients()
  }, 400)
})

// Watchers pestaña 1: filtros selectores
watch([statusFilter, consumptionTypeFilter, productFilter], () => {
  page.value = 1
  fetchChronicClients()
})

// Watchers pestaña 1: paginación
watch([page, perPage], fetchChronicClients)

onMounted(() => {
  fetchStats()
  fetchChronicClients()
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
      </div>
    </VCard>

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

    <!-- PACIENTES Y RECOMPRAS -->
    <div class="d-flex flex-column gap-y-4">
      <AppFilterBase
        :search="searchQuery"
        :has-advanced-filters="statusFilter !== 'all' || consumptionTypeFilter !== 'all' || !!productFilter"
        search-placeholder="Buscar por paciente, cédula, teléfono o medicamento..."
        class="py-1"
        @update:search="searchQuery = $event"
        @clear="resetFilters"
      >
        <template #actions-extra>
          <!-- Contador Diario de Fidelización (Cuota de 5 pacientes) -->
          <VChip
            :color="stats.today_contacted >= stats.daily_quota ? 'success' : 'primary'"
            variant="flat"
            class="font-weight-black px-3"
            size="default"
          >
            <VIcon
              :icon="stats.today_contacted >= stats.daily_quota ? 'tabler-circle-check' : 'tabler-target'"
              start
              size="18"
            />
            Meta diaria: {{ stats.today_contacted }}/{{ stats.daily_quota }}
            <VTooltip activator="parent" location="top">
              {{ stats.today_contacted >= stats.daily_quota ? '¡Meta diaria completada exitosamente!' : `Has contactado ${stats.today_contacted} de ${stats.daily_quota} pacientes hoy` }}
            </VTooltip>
          </VChip>
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
          :key="item.client_id"
          :client="item"
          :loading="contactingClientId === item.client_id || verifyingWhatsAppClientId === item.client_id"
          @mark-contacted="markContacted"
          @open-whatsapp="openWhatsApp"
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
          </div>
        </template>

        <!-- Columna Medicamento -->
        <template #item.product_name="{ item }">
          <div class="py-2 min-width-0">
            <div v-if="item.products && item.products.length > 1" class="d-flex flex-column gap-1">
              <div
                v-for="(prod, pIdx) in item.products"
                :key="prod.product_id"
                class="pb-1"
                :class="{ 'border-b': pIdx < item.products.length - 1 }"
              >
                <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate d-block" style="max-inline-size: 320px;">
                  {{ prod.product_name?.toUpperCase() || '—' }}
                </span>
                <div class="d-flex align-center flex-wrap gap-1 text-caption mt-0-5">
                  <span class="text-disabled">{{ prod.active_ingredient || 'N/A' }}</span>
                  <span class="text-disabled mx-1">|</span>
                  <span class="text-primary font-weight-bold text-uppercase">{{ prod.laboratory_name }}</span>
                </div>
              </div>
            </div>
            <div v-else>
              <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate d-block" style="max-inline-size: 320px;">
                {{ item.product_name?.toUpperCase() || '—' }}
              </span>
              <div class="d-flex align-center flex-wrap gap-1 text-caption mt-0-5">
                <span class="text-disabled">{{ item.active_ingredient || 'N/A' }}</span>
                <span class="text-disabled mx-1">|</span>
                <span class="text-primary font-weight-bold text-uppercase">{{ item.laboratory_name }}</span>
              </div>
            </div>
          </div>
        </template>

        <!-- Columna Tipo Consumo -->
        <template #item.consumption_type="{ item }">
          <div v-if="item.products && item.products.length > 1" class="d-flex flex-column gap-1 py-2">
            <VChip
              v-for="prod in item.products"
              :key="prod.product_id"
              size="x-small"
              :color="getConsumptionBadge(prod.consumption_type).color"
              variant="tonal"
              class="font-weight-medium"
            >
              <VIcon :icon="getConsumptionBadge(prod.consumption_type).icon" start size="12" />
              {{ getConsumptionBadge(prod.consumption_type).label }}
            </VChip>
          </div>
          <VChip
            v-else
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
          </div>
        </template>

        <!-- Columna Duración -->
        <template #item.treatment_end_date_formatted="{ item }">
          <div class="py-2">
            <span class="font-weight-bold" :class="item.days_until_end <= 5 && item.days_until_end >= -30 ? 'text-warning' : (item.days_until_end < -30 ? 'text-error' : 'text-success')">
              {{ item.days_until_end }} días
            </span>
          </div>
        </template>

        <!-- Columna Precios (Solo USD) -->
        <template #item.pricing="{ item }">
          <div class="py-2">
            <div v-if="item.products && item.products.length > 1" class="d-flex flex-column gap-1">
              <div v-for="prod in item.products" :key="prod.product_id" class="font-weight-bold text-success text-sm">
                ${{ Number(prod.price_usd || 0).toFixed(2) }}
              </div>
            </div>
            <div v-else class="font-weight-bold text-success">
              ${{ Number(item.price_usd || 0).toFixed(2) }}
            </div>
          </div>
        </template>

        <!-- Columna Stock Actual -->
        <template #item.stock="{ item }">
          <div class="py-2 text-center">
            <div v-if="item.products && item.products.length > 1" class="d-flex flex-column gap-1 align-center">
              <VChip
                v-for="prod in item.products"
                :key="prod.product_id"
                size="x-small"
                :color="Number(prod.stock || 0) > 0 ? 'success' : 'error'"
                variant="tonal"
                class="font-weight-black"
              >
                <VIcon :icon="Number(prod.stock || 0) > 0 ? 'tabler-box' : 'tabler-box-off'" start size="12" />
                {{ Math.round(Number(prod.stock || 0)) }} un.
              </VChip>
            </div>
            <VChip
              v-else
              size="small"
              :color="Number(item.stock || 0) > 0 ? 'success' : 'error'"
              variant="tonal"
              class="font-weight-black"
            >
              <VIcon :icon="Number(item.stock || 0) > 0 ? 'tabler-box' : 'tabler-box-off'" start size="14" />
              {{ Math.round(Number(item.stock || 0)) }} un.
            </VChip>
          </div>
        </template>

        <!-- Columna Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-2">
            <VBtn
              v-if="item.whatsapp_url || item.phone"
              color="success"
              variant="flat"
              size="small"
              icon
              class="rounded-lg"
              :loading="verifyingWhatsAppClientId === item.client_id"
              title="Contactar por WhatsApp"
              @click="openWhatsApp(item)"
            >
              <VIcon icon="tabler-brand-whatsapp" size="20" />
            </VBtn>
            <span v-else class="text-caption text-disabled">Sin teléfono</span>

            <VBtn
              color="primary"
              variant="tonal"
              size="small"
              icon
              class="rounded-lg"
              :loading="contactingClientId === item.client_id"
              title="Marcar como Contactado / Enviado"
              @click="markContacted(item)"
            >
              <VIcon icon="tabler-check" size="20" color="success" />
            </VBtn>
          </div>
        </template>

        <!-- Estado Vacío en Tabla -->
        <template #no-data>
          <div class="py-8 text-center">
            <VAvatar color="primary" variant="tonal" size="56" class="mb-3">
              <VIcon icon="tabler-user-search" size="32" />
            </VAvatar>
            <div class="text-h6 font-weight-bold">No se encontraron pacientes</div>
            <div class="text-caption text-medium-emphasis mb-4">
              No hay pacientes con medicamentos de seguimiento para mostrar.
            </div>
            <VBtn variant="tonal" color="primary" @click="resetFilters">
              Limpiar Filtros
            </VBtn>
          </div>
        </template>
      </VDataTableServer>
    </VCard>
    </div>
  </div>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgba(var(--v-theme-primary), 0.8) 100%);
}
.modal-avatar-icon {
  color: rgb(var(--v-theme-primary)) !important;
}
.stat-card {
  transition: all 0.2s ease-in-out;
}
.stat-card:hover {
  transform: translateY(-2px);
}
.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
