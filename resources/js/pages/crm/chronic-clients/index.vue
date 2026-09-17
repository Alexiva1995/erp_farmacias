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

// Estado de carga para remoción de teléfono sin WhatsApp
const removingPhoneClientId = ref(null)

// Remover teléfono si no tiene WhatsApp o es erróneo
const markInvalidPhone = async (item) => {
  removingPhoneClientId.value = item.client_id
  try {
    await $api(`/crm/chronic-clients/remove-phone/${item.client_id}`, {
      method: 'DELETE',
    })

    toast.success(`Se removió el teléfono de "${item.client_name}".`)

    // Recargar lista y estadísticas
    await fetchChronicClients()
    fetchStats()
  } catch (e) {
    console.error('Error markInvalidPhone:', e)
    const errorMsg = e?.response?._data?.message || e?.message || 'No se pudo remover el teléfono.'
    toast.error(errorMsg)
  } finally {
    removingPhoneClientId.value = null
  }
}

// Formatear texto a Title Case
const toTitleCase = (str) => {
  if (!str) return ''
  return str
    .toLowerCase()
    .split(' ')
    .filter(Boolean)
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}

// Obtener badge visual según tipo de consumo
const getConsumptionBadge = (type) => {
  switch (type) {
    case 'chronic':          return { color: 'primary',   label: 'Uso Continuo', icon: 'tabler-repeat' }
    case 'single_treatment': return { color: 'info',      label: 'Tratamiento',  icon: 'tabler-calendar-event' }
    case 'no_alert':         return { color: 'secondary', label: 'Sin Alerta',   icon: 'tabler-bell-off' }
    case 'sporadic':         return { color: 'secondary', label: 'Botiquín',     icon: 'tabler-first-aid-kit' }
    default:                 return { color: 'secondary', label: 'Sin Clasificar', icon: 'tabler-help' }
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
        <template #prepend-actions>
          <!-- Contador Diario de Fidelización (Cuota de 5 pacientes) antes de los botones de filtros -->
          <VChip
            :color="stats.today_contacted >= stats.daily_quota ? 'success' : 'primary'"
            variant="flat"
            class="font-weight-black px-3 me-1"
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
          :loading="contactingClientId === item.client_id || verifyingWhatsAppClientId === item.client_id || removingPhoneClientId === item.client_id"
          @mark-contacted="markContacted"
          @open-whatsapp="openWhatsApp"
          @remove-phone="markInvalidPhone"
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
            <span class="font-weight-semibold text-high-emphasis">
              {{ toTitleCase(item.client_name) }}
            </span>
          </div>
        </template>

        <!-- Columna Medicamento -->
        <template #item.product_name="{ item }">
          <div class="py-2 min-width-0">
            <div v-if="item.products && item.products.length > 1" class="d-flex flex-column gap-2">
              <div
                v-for="(prod, pIdx) in item.products"
                :key="prod.product_id"
                class="pb-1"
                :class="{ 'border-b': pIdx < item.products.length - 1 }"
              >
                <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate d-block" style="max-inline-size: 320px;" :title="prod.product_name">
                  {{ (prod.product_name || '—')?.toUpperCase() }}
                </span>
                <div class="d-flex align-center flex-wrap gap-1 text-super-xs mt-0-5">
                  <span class="text-disabled font-weight-normal truncate" style="max-inline-size: 160px;">{{ prod.active_ingredient || '—' }}</span>
                  <span class="text-disabled mx-1">|</span>
                  <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 140px;">
                    {{ prod.laboratory_name || 'S/L' }}
                  </span>
                </div>
              </div>
            </div>
            <div v-else>
              <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate d-block" style="max-inline-size: 320px;" :title="item.product_name">
                {{ (item.product_name || '—')?.toUpperCase() }}
              </span>
              <div class="d-flex align-center flex-wrap gap-1 text-super-xs mt-0-5">
                <span class="text-disabled font-weight-normal truncate" style="max-inline-size: 160px;">{{ item.active_ingredient || '—' }}</span>
                <span class="text-disabled mx-1">|</span>
                <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 140px;">
                  {{ item.laboratory_name || 'S/L' }}
                </span>
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
            size="x-small"
            :color="getConsumptionBadge(item.consumption_type).color"
            variant="tonal"
            class="font-weight-medium"
          >
            <VIcon :icon="getConsumptionBadge(item.consumption_type).icon" start size="12" />
            {{ getConsumptionBadge(item.consumption_type).label }}
          </VChip>
        </template>

        <!-- Columna Última Compra -->
        <template #item.last_order_date_formatted="{ item }">
          <div class="py-2 text-caption font-weight-medium text-medium-emphasis">
            {{ item.last_order_date_formatted }}
          </div>
        </template>

        <!-- Columna Duración -->
        <template #item.treatment_end_date_formatted="{ item }">
          <div class="py-2">
            <span
              class="text-sm font-weight-semibold"
              :class="item.days_until_end <= 5 && item.days_until_end >= -30 ? 'text-warning' : (item.days_until_end < -30 ? 'text-error' : 'text-medium-emphasis')"
            >
              {{ item.days_until_end }} d
            </span>
          </div>
        </template>

        <!-- Columna Precios (Solo USD) -->
        <template #item.pricing="{ item }">
          <div class="py-2">
            <div v-if="item.products && item.products.length > 1" class="d-flex flex-column gap-1">
              <div v-for="prod in item.products" :key="prod.product_id" class="font-weight-medium text-high-emphasis text-sm">
                ${{ Number(prod.price_usd || 0).toFixed(2) }}
              </div>
            </div>
            <div v-else class="font-weight-medium text-high-emphasis text-sm">
              ${{ Number(item.price_usd || 0).toFixed(2) }}
            </div>
          </div>
        </template>

        <!-- Columna Stock Actual -->
        <template #item.stock="{ item }">
          <div class="py-2 text-center">
            <div v-if="item.products && item.products.length > 1" class="d-flex flex-column gap-1 align-center">
              <span
                v-for="prod in item.products"
                :key="prod.product_id"
                class="text-sm font-weight-medium"
                :class="Number(prod.stock || 0) > 0 ? 'text-medium-emphasis' : 'text-error'"
              >
                {{ Math.round(Number(prod.stock || 0)) }}
              </span>
            </div>
            <span
              v-else
              class="text-sm font-weight-medium"
              :class="Number(item.stock || 0) > 0 ? 'text-medium-emphasis' : 'text-error'"
            >
              {{ Math.round(Number(item.stock || 0)) }}
            </span>
          </div>
        </template>

        <!-- Columna Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <IconBtn
              v-if="item.whatsapp_url || item.phone"
              color="success"
              size="small"
              :loading="verifyingWhatsAppClientId === item.client_id"
              @click="openWhatsApp(item)"
            >
              <VIcon icon="tabler-brand-whatsapp" size="18" />
              <VTooltip activator="parent">Contactar por WhatsApp</VTooltip>
            </IconBtn>
            <span v-else class="text-caption text-disabled">—</span>

            <IconBtn
              color="primary"
              size="small"
              :loading="contactingClientId === item.client_id"
              @click="markContacted(item)"
            >
              <VIcon icon="tabler-check" size="18" />
              <VTooltip activator="parent">Marcar como Contactado</VTooltip>
            </IconBtn>

            <IconBtn
              v-if="item.phone"
              color="error"
              size="small"
              :loading="removingPhoneClientId === item.client_id"
              @click="markInvalidPhone(item)"
            >
              <VIcon icon="tabler-x" size="18" />
              <VTooltip activator="parent">Número no posee WhatsApp (Remover)</VTooltip>
            </IconBtn>
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
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}
.mt-0-5 {
  margin-top: 2px !important;
}
</style>
