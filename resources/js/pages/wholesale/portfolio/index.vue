<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'

const isLoading = ref(false)
const isAssigning = ref(false)
const isSavingTerms = ref(false)
const isCalculatingCommissions = ref(false)

const clients = ref([])
const selectedClientIds = ref([])
const users = ref([])
const routes = ref([])
const activeTab = ref('portfolio')

const metrics = reactive({
  total_clients: 0,
  total_credit_limit_usd: 0,
  total_pending_debt_usd: 0,
  total_overdue_debt_usd: 0,
})

const filters = reactive({
  search: '',
  sales_rep_id: null,
  telemarketing_user_id: null,
  collector_user_id: null,
  delivery_route_id: null,
  has_debt: false,
  is_overdue: false,
})

const assignForm = reactive({
  sales_rep_id: null,
  telemarketing_user_id: null,
  collector_user_id: null,
  delivery_route_id: null,
})

const showTermsModal = ref(false)
const editingClient = ref(null)
const termsForm = reactive({
  credit_limit_usd: 0,
  base_credit_days: 7,
  monthly_purchase_cap_usd: null,
  sacs_permit_number: '',
  sicm_code: '',
  linked_tenant_id: '',
  b2b_portal_enabled: true,
})

const commissionForm = reactive({
  sales_rep_id: null,
  start_date: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
  end_date: new Date().toISOString().slice(0, 10),
  commission_pct: 2.0,
})

const commissionResult = ref(null)

const salesReps = computed(() => {
  return users.value.filter(u => {
    const rName = (u.role?.name || '').toLowerCase()
    return !u.role || rName.includes('asesor') || rName.includes('admin') || rName.includes('supervisor') || u.role_id === 6 || u.role_id === 1
  })
})

const telemarketers = computed(() => {
  return users.value.filter(u => {
    const rName = (u.role?.name || '').toLowerCase()
    return !u.role || rName.includes('telemarketing') || rName.includes('admin') || rName.includes('supervisor') || u.role_id === 7 || u.role_id === 1
  })
})

const collectors = computed(() => {
  return users.value.filter(u => {
    const rName = (u.role?.name || '').toLowerCase()
    return !u.role || rName.includes('cobranza') || rName.includes('admin') || rName.includes('supervisor') || u.role_id === 8 || u.role_id === 1
  })
})

const fetchMetrics = async () => {
  try {
    const { data } = await axios.get('/wholesale/portfolio/metrics')
    Object.assign(metrics, data.data || {})
  } catch (error) {
    console.error('Error cargando métricas de cartera:', error)
  }
}

const fetchPortfolio = async () => {
  isLoading.value = true
  try {
    const params = {
      search: filters.search || undefined,
      sales_rep_id: filters.sales_rep_id || undefined,
      telemarketing_user_id: filters.telemarketing_user_id || undefined,
      collector_user_id: filters.collector_user_id || undefined,
      delivery_route_id: filters.delivery_route_id || undefined,
      has_debt: filters.has_debt ? 1 : undefined,
      is_overdue: filters.is_overdue ? 1 : undefined,
      per_page: 50,
    }
    const { data } = await axios.get('/wholesale/portfolio', { params })
    clients.value = data.data?.data || data.data || []
  } catch (error) {
    toast.error('No se pudo cargar la cartera de clientes B2B')
  } finally {
    isLoading.value = false
  }
}

const fetchUsersAndRoutes = async () => {
  try {
    const [usersRes, routesRes] = await Promise.all([
      axios.get('/users', { params: { per_page: 200 } }).catch(() => ({ data: { data: [] } })),
      axios.get('/wholesale/wms/routes').catch(() => ({ data: { data: [] } })),
    ])
    users.value = usersRes.data?.data?.data || usersRes.data?.data || usersRes.data || []
    routes.value = routesRes.data?.data || []
  } catch (error) {
    console.error('Error cargando usuarios y rutas:', error)
  }
}

const submitBulkAssignment = async () => {
  if (selectedClientIds.value.length === 0) {
    toast.warning('Selecciona al menos una farmacia de la tabla')
    return
  }

  isAssigning.value = true
  try {
    const payload = {
      client_ids: selectedClientIds.value,
      sales_rep_id: assignForm.sales_rep_id,
      telemarketing_user_id: assignForm.telemarketing_user_id,
      collector_user_id: assignForm.collector_user_id,
      delivery_route_id: assignForm.delivery_route_id,
    }
    const { data } = await axios.post('/wholesale/portfolio/assign', payload)
    toast.success(data.message || 'Cartera asignada correctamente')
    selectedClientIds.value = []
    await Promise.all([fetchPortfolio(), fetchMetrics()])
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error al asignar cartera')
  } finally {
    isAssigning.value = false
  }
}

const openTermsModal = (client) => {
  editingClient.value = client
  termsForm.credit_limit_usd = Number(client.credit_limit_usd || 0)
  termsForm.base_credit_days = Number(client.base_credit_days ?? 7)
  termsForm.monthly_purchase_cap_usd = client.monthly_purchase_cap_usd !== null ? Number(client.monthly_purchase_cap_usd) : null
  termsForm.sacs_permit_number = client.sacs_permit_number || ''
  termsForm.sicm_code = client.sicm_code || ''
  termsForm.linked_tenant_id = client.linked_tenant_id || ''
  termsForm.b2b_portal_enabled = Boolean(client.b2b_portal_enabled ?? true)
  showTermsModal.value = true
}

const saveClientTerms = async () => {
  if (!editingClient.value) return
  isSavingTerms.value = true
  try {
    const { data } = await axios.put(`/wholesale/portfolio/clients/${editingClient.value.id}/terms`, termsForm)
    toast.success(data.message || 'Condiciones de crédito actualizadas')
    showTermsModal.value = false
    await Promise.all([fetchPortfolio(), fetchMetrics()])
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error al guardar condiciones del cliente')
  } finally {
    isSavingTerms.value = false
  }
}

const calculateCommissions = async () => {
  if (!commissionForm.sales_rep_id) {
    toast.warning('Selecciona un Asesor Comercial')
    return
  }
  isCalculatingCommissions.value = true
  try {
    const { data } = await axios.get('/wholesale/portfolio/commissions', { params: commissionForm })
    commissionResult.value = data.data
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error calculando comisiones sobre recaudación')
  } finally {
    isCalculatingCommissions.value = false
  }
}

onMounted(async () => {
  await Promise.all([fetchMetrics(), fetchPortfolio(), fetchUsersAndRoutes()])
})
</script>

<template>
  <VContainer fluid>
    <!-- Encabezado Principal -->
    <VCard class="mb-4" elevation="2">
      <VCardText class="d-flex flex-wrap align-center justify-space-between gap-4">
        <div>
          <h2 class="text-h5 font-weight-bold text-primary">
            Panel de Asignación de Cartera, Cupos y Comisiones B2B
          </h2>
          <p class="text-body-2 text-medium-emphasis mb-0">
            Segmentación de farmacias por Asesor Comercial, Telemarketing, Cobranzas, Ruta Logística y Límites de Crédito.
          </p>
        </div>

        <VTabs v-model="activeTab" color="primary" density="compact">
          <VTab value="portfolio" prepend-icon="tabler-users-group">
            Cartera y Cupos
          </VTab>
          <VTab value="commissions" prepend-icon="tabler-coin">
            Liquidación de Comisiones
          </VTab>
        </VTabs>
      </VCardText>
    </VCard>

    <!-- KPIs de Cartera -->
    <VRow class="mb-4">
      <VCol cols="12" sm="6" md="3">
        <VCard elevation="1" class="pa-4">
          <div class="text-caption text-medium-emphasis">
            Farmacias en Cartera
          </div>
          <div class="text-h5 font-weight-bold text-primary mt-1">
            {{ metrics.total_clients }}
          </div>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard elevation="1" class="pa-4">
          <div class="text-caption text-medium-emphasis">
            Cupo Total Otorgado (USD)
          </div>
          <div class="text-h5 font-weight-bold text-info mt-1">
            ${{ Number(metrics.total_credit_limit_usd || 0).toFixed(2) }}
          </div>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard elevation="1" class="pa-4">
          <div class="text-caption text-medium-emphasis">
            Cuentas por Cobrar Activas (USD)
          </div>
          <div class="text-h5 font-weight-bold text-warning mt-1">
            ${{ Number(metrics.total_pending_debt_usd || 0).toFixed(2) }}
          </div>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard elevation="1" class="pa-4">
          <div class="text-caption text-medium-emphasis">
            Cartera Vencida en Mora (USD)
          </div>
          <div class="text-h5 font-weight-bold text-error mt-1">
            ${{ Number(metrics.total_overdue_debt_usd || 0).toFixed(2) }}
          </div>
        </VCard>
      </VCol>
    </VRow>

    <VWindow v-model="activeTab">
      <!-- Pestaña 1: Cartera y Asignación Masiva -->
      <VWindowItem value="portfolio">
        <!-- Barra de Asignación Masiva -->
        <VCard class="mb-4 border" variant="outlined">
          <VCardTitle class="text-subtitle-1 font-weight-bold pa-4 pb-2 d-flex align-center justify-space-between">
            <span>Asignación Masiva de Ejecutivos y Rutas ({{ selectedClientIds.length }} seleccionadas)</span>
          </VCardTitle>
          <VCardText class="pa-4 pt-2">
            <VRow align="center">
              <VCol cols="12" sm="6" md="3">
                <VSelect
                  v-model="assignForm.sales_rep_id"
                  :items="salesReps"
                  item-title="name"
                  item-value="id"
                  label="Asesor Comercial"
                  density="compact"
                  variant="outlined"
                  clearable
                  hide-details
                />
              </VCol>
              <VCol cols="12" sm="6" md="3">
                <VSelect
                  v-model="assignForm.telemarketing_user_id"
                  :items="telemarketers"
                  item-title="name"
                  item-value="id"
                  label="Operador Telemarketing"
                  density="compact"
                  variant="outlined"
                  clearable
                  hide-details
                />
              </VCol>
              <VCol cols="12" sm="6" md="2">
                <VSelect
                  v-model="assignForm.collector_user_id"
                  :items="collectors"
                  item-title="name"
                  item-value="id"
                  label="Analista Cobranzas"
                  density="compact"
                  variant="outlined"
                  clearable
                  hide-details
                />
              </VCol>
              <VCol cols="12" sm="6" md="2">
                <VSelect
                  v-model="assignForm.delivery_route_id"
                  :items="routes"
                  item-title="name"
                  item-value="id"
                  label="Ruta de Despacho"
                  density="compact"
                  variant="outlined"
                  clearable
                  hide-details
                />
              </VCol>
              <VCol cols="12" md="2">
                <VBtn
                  block
                  color="primary"
                  :loading="isAssigning"
                  :disabled="selectedClientIds.length === 0"
                  prepend-icon="tabler-check"
                  @click="submitBulkAssignment"
                >
                  Asignar ({{ selectedClientIds.length }})
                </VBtn>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- Filtros y Tabla de Cartera -->
        <VCard elevation="2">
          <VCardText>
            <VRow class="mb-3" align="center">
              <VCol cols="12" md="4">
                <VTextField
                  v-model="filters.search"
                  label="Buscar farmacia por nombre, RIF o código SICM..."
                  prepend-inner-icon="tabler-search"
                  density="compact"
                  variant="outlined"
                  clearable
                  hide-details
                  @keyup.enter="fetchPortfolio"
                />
              </VCol>
              <VCol cols="12" sm="6" md="3">
                <VSelect
                  v-model="filters.sales_rep_id"
                  :items="salesReps"
                  item-title="name"
                  item-value="id"
                  label="Filtrar por Asesor"
                  density="compact"
                  variant="outlined"
                  clearable
                  hide-details
                  @update:model-value="fetchPortfolio"
                />
              </VCol>
              <VCol cols="6" sm="3" md="2">
                <VCheckbox
                  v-model="filters.has_debt"
                  label="Con saldo"
                  density="compact"
                  hide-details
                  @update:model-value="fetchPortfolio"
                />
              </VCol>
              <VCol cols="6" sm="3" md="2">
                <VCheckbox
                  v-model="filters.is_overdue"
                  label="En mora"
                  color="error"
                  density="compact"
                  hide-details
                  @update:model-value="fetchPortfolio"
                />
              </VCol>
              <VCol cols="12" md="1" class="text-right">
                <VBtn
                  icon="tabler-reload"
                  variant="tonal"
                  color="primary"
                  size="small"
                  :loading="isLoading"
                  @click="fetchPortfolio"
                />
              </VCol>
            </VRow>

            <VProgressLinear v-if="isLoading" indeterminate color="primary" class="mb-2" />

            <VTable density="comfortable">
              <thead>
                <tr>
                  <th style="width: 42px;">
                    <VCheckbox
                      :model-value="clients.length > 0 && selectedClientIds.length === clients.length"
                      density="compact"
                      hide-details
                      @update:model-value="val => selectedClientIds = val ? clients.map(c => c.id) : []"
                    />
                  </th>
                  <th>Farmacia / RIF</th>
                  <th>Permisos SACS / SICM</th>
                  <th>Asesor / Telemarketing</th>
                  <th>Cobranzas / Ruta</th>
                  <th class="text-right">
                    Cupo Crédito (USD)
                  </th>
                  <th class="text-center">
                    Plazo Base
                  </th>
                  <th class="text-right">
                    Deuda / Mora
                  </th>
                  <th class="text-center">
                    Acciones
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="client in clients" :key="client.id">
                  <td>
                    <VCheckbox
                      v-model="selectedClientIds"
                      :value="client.id"
                      density="compact"
                      hide-details
                    />
                  </td>
                  <td>
                    <div class="font-weight-bold">
                      {{ client.name }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ client.identification_number }}
                      <VChip
                        v-if="client.linked_tenant_id"
                        size="x-small"
                        color="info"
                        variant="tonal"
                        class="ml-1"
                      >
                        Tenant: {{ client.linked_tenant_id }}
                      </VChip>
                    </div>
                  </td>
                  <td>
                    <div class="text-caption">
                      <strong>SACS:</strong> {{ client.sacs_permit_number || 'Sin registrar' }}
                    </div>
                    <div class="text-caption">
                      <strong>SICM:</strong> {{ client.sicm_code || 'N/A' }}
                    </div>
                  </td>
                  <td>
                    <div class="text-caption">
                      <strong>Asesor:</strong> {{ client.sales_rep?.name || 'No asignado' }}
                    </div>
                    <div class="text-caption">
                      <strong>Telemarketing:</strong> {{ client.telemarketing_operator?.name || 'No asignado' }}
                    </div>
                  </td>
                  <td>
                    <div class="text-caption">
                      <strong>Cobrador:</strong> {{ client.collector?.name || 'No asignado' }}
                    </div>
                    <div class="text-caption">
                      <strong>Ruta:</strong> {{ client.delivery_route?.name || 'Sin ruta' }}
                    </div>
                  </td>
                  <td class="text-right font-weight-bold">
                    ${{ Number(client.credit_limit_usd || 0).toFixed(2) }}
                    <div v-if="client.monthly_purchase_cap_usd" class="text-caption text-medium-emphasis">
                      Tope mes: ${{ Number(client.monthly_purchase_cap_usd).toFixed(2) }}
                    </div>
                  </td>
                  <td class="text-center">
                    <VChip size="small" color="primary" variant="tonal">
                      {{ client.base_credit_days ?? 0 }} días
                    </VChip>
                  </td>
                  <td class="text-right">
                    <div class="font-weight-bold">
                      ${{ Number(client.pending_debt_usd || 0).toFixed(2) }}
                    </div>
                    <VChip
                      v-if="Number(client.overdue_debt_usd || 0) > 0"
                      size="x-small"
                      color="error"
                      variant="elevated"
                    >
                      Mora: ${{ Number(client.overdue_debt_usd).toFixed(2) }}
                    </VChip>
                  </td>
                  <td class="text-center">
                    <VBtn
                      size="small"
                      color="primary"
                      variant="tonal"
                      prepend-icon="tabler-edit"
                      @click="openTermsModal(client)"
                    >
                      Condiciones
                    </VBtn>
                  </td>
                </tr>
                <tr v-if="!isLoading && clients.length === 0">
                  <td colspan="9" class="text-center py-8 text-medium-emphasis">
                    No se encontraron farmacias registradas con los filtros aplicados.
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCardText>
        </VCard>
      </VWindowItem>

      <!-- Pestaña 2: Liquidación de Comisiones sobre Cobranza Conciliada -->
      <VWindowItem value="commissions">
        <VCard elevation="2">
          <VCardTitle class="pa-4 font-weight-bold">
            Cálculo de Comisiones de Asesores Comerciales (Sobre Recaudación Conciliada)
          </VCardTitle>
          <VDivider />
          <VCardText class="pa-4">
            <VRow align="center">
              <VCol cols="12" md="4">
                <VSelect
                  v-model="commissionForm.sales_rep_id"
                  :items="salesReps"
                  item-title="name"
                  item-value="id"
                  label="Seleccionar Asesor Comercial"
                  density="compact"
                  variant="outlined"
                />
              </VCol>
              <VCol cols="12" sm="4" md="2">
                <VTextField
                  v-model="commissionForm.start_date"
                  type="date"
                  label="Desde"
                  density="compact"
                  variant="outlined"
                />
              </VCol>
              <VCol cols="12" sm="4" md="2">
                <VTextField
                  v-model="commissionForm.end_date"
                  type="date"
                  label="Hasta"
                  density="compact"
                  variant="outlined"
                />
              </VCol>
              <VCol cols="12" sm="4" md="2">
                <VTextField
                  v-model.number="commissionForm.commission_pct"
                  type="number"
                  step="0.1"
                  label="% Comisión"
                  density="compact"
                  variant="outlined"
                />
              </VCol>
              <VCol cols="12" md="2">
                <VBtn
                  block
                  color="primary"
                  :loading="isCalculatingCommissions"
                  prepend-icon="tabler-calculator"
                  @click="calculateCommissions"
                >
                  Calcular
                </VBtn>
              </VCol>
            </VRow>

            <VAlert
              v-if="commissionResult"
              type="success"
              variant="tonal"
              class="mt-4"
            >
              <VRow align="center">
                <VCol cols="12" md="3">
                  <div class="text-caption">
                    Pagos Conciliados en el Período
                  </div>
                  <div class="text-h6 font-weight-bold">
                    {{ commissionResult.verified_payments_count }} abonos
                  </div>
                </VCol>
                <VCol cols="12" md="3">
                  <div class="text-caption">
                    Total Recaudado Base (USD)
                  </div>
                  <div class="text-h6 font-weight-bold">
                    ${{ Number(commissionResult.total_collected_usd || 0).toFixed(2) }}
                  </div>
                </VCol>
                <VCol cols="12" md="3">
                  <div class="text-caption">
                    Porcentaje Aplicado
                  </div>
                  <div class="text-h6 font-weight-bold">
                    {{ commissionResult.commission_percentage }}%
                  </div>
                </VCol>
                <VCol cols="12" md="3">
                  <div class="text-caption">
                    Comisión a Liquidar (USD)
                  </div>
                  <div class="text-h5 font-weight-bold text-success">
                    ${{ Number(commissionResult.commission_amount_usd || 0).toFixed(2) }}
                  </div>
                </VCol>
              </VRow>
            </VAlert>
          </VCardText>
        </VCard>
      </VWindowItem>
    </VWindow>

    <!-- Modal de Edición de Condiciones de Crédito y Permisos SACS/SICM -->
    <VDialog v-model="showTermsModal" max-width="620">
      <VCard v-if="editingClient">
        <VCardTitle class="pa-4 font-weight-bold">
          Condiciones B2B: {{ editingClient.name }}
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-4">
          <VRow>
            <VCol cols="12" md="4">
              <VTextField
                v-model.number="termsForm.credit_limit_usd"
                type="number"
                label="Límite de Crédito (USD)"
                density="compact"
                variant="outlined"
              />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField
                v-model.number="termsForm.base_credit_days"
                type="number"
                label="Días de Crédito Base"
                density="compact"
                variant="outlined"
              />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField
                v-model.number="termsForm.monthly_purchase_cap_usd"
                type="number"
                label="Tope Mensual Compra (USD)"
                density="compact"
                variant="outlined"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="termsForm.sacs_permit_number"
                label="Permiso Sanitario SACS"
                density="compact"
                variant="outlined"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="termsForm.sicm_code"
                label="Código SICM"
                density="compact"
                variant="outlined"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model="termsForm.linked_tenant_id"
                label="ID Tenant ERP Vinculado (Puente ERP ↔ ERP)"
                placeholder="Ej. farmacia_central"
                density="compact"
                variant="outlined"
              />
            </VCol>
            <VCol cols="12" md="6" class="d-flex align-center">
              <VSwitch
                v-model="termsForm.b2b_portal_enabled"
                color="success"
                label="Acceso activo al Marketplace B2B"
                hide-details
              />
            </VCol>
          </VRow>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-4 justify-end">
          <VBtn variant="text" @click="showTermsModal = false">
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            variant="elevated"
            :loading="isSavingTerms"
            @click="saveClientTerms"
          >
            Guardar Condiciones
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VContainer>
</template>
