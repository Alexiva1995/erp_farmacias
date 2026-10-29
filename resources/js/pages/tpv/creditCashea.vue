<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import Swal from 'sweetalert2'

// Control de estados
const activeTab = ref('credits')
const isLoading = ref(false)
const isKpisLoading = ref(false)
const isPaying = ref(false)

// KPIs
const kpis = reactive({
  total_financed_usd: 0,
  total_pending_usd: 0,
  total_paid_usd: 0,
  due_next_14_days_usd: 0,
  overdue_usd: 0,
})

// Listado de Créditos Cashea
const credits = ref([])
const totalCredits = ref(0)
const page = ref(1)
const itemsPerPage = ref(15)
const searchQuery = ref('')
const statusFilter = ref('')

// Calendario / Cuotas
const installments = ref([])
const totalInstallments = ref(0)
const schedulePage = ref(1)
const scheduleItemsPerPage = ref(15)
const scheduleStatusFilter = ref('')
const scheduleSearch = ref('')
const scheduleDateFrom = ref('')
const scheduleDateTo = ref('')

// Modal de cobro de cuota
const isPaymentModalOpen = ref(false)
const selectedInstallment = ref(null)
const paymentForm = reactive({
  amount_usd: 0,
  payment_reference: '',
})

// Cargar KPIs
const fetchKpis = async () => {
  isKpisLoading.value = true
  try {
    const { data } = await axios.get('/tpv/credits/cashea/kpis')
    Object.assign(kpis, data.data || {})
  } catch (error) {
    console.error('Error al cargar KPIs de Cashea:', error)
  } finally {
    isKpisLoading.value = false
  }
}

// Cargar listado de créditos Cashea
const fetchCredits = async () => {
  isLoading.value = true
  try {
    const params = {
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      search: searchQuery.value || undefined,
      status: statusFilter.value || undefined,
    }
    const { data } = await axios.get('/tpv/credits/cashea', { params })
    credits.value = data.data || []
    totalCredits.value = data.total || 0
  } catch (error) {
    console.error('Error al cargar créditos Cashea:', error)
    toast.error('No se pudieron obtener los créditos de Cashea')
  } finally {
    isLoading.value = false
  }
}

// Cargar calendario de cuotas
const fetchSchedule = async () => {
  isLoading.value = true
  try {
    const params = {
      page: schedulePage.value,
      itemsPerPage: scheduleItemsPerPage.value,
      search: scheduleSearch.value || undefined,
      status: scheduleStatusFilter.value || undefined,
      date_from: scheduleDateFrom.value || undefined,
      date_to: scheduleDateTo.value || undefined,
    }
    const { data } = await axios.get('/tpv/credits/cashea/schedule', { params })
    installments.value = data.data || []
    totalInstallments.value = data.total || 0
  } catch (error) {
    console.error('Error al cargar calendario de cuotas:', error)
    toast.error('No se pudo obtener el cronograma de cuotas')
  } finally {
    isLoading.value = false
  }
}

// Abrir modal de cobro
const openPaymentModal = (installment) => {
  selectedInstallment.value = installment
  paymentForm.amount_usd = installment.amount_usd
  paymentForm.payment_reference = ''
  isPaymentModalOpen.value = true
}

// Confirmar cobro de cuota
const submitPayment = async () => {
  if (!selectedInstallment.value) return
  isPaying.value = true
  try {
    await axios.post(`/tpv/credits/cashea/installments/${selectedInstallment.value.id}/pay`, {
      amount_usd: paymentForm.amount_usd,
      payment_reference: paymentForm.payment_reference,
    })
    toast.success('Cuota liquidada exitosamente')
    isPaymentModalOpen.value = false
    selectedInstallment.value = null
    await Promise.all([fetchKpis(), activeTab.value === 'credits' ? fetchCredits() : fetchSchedule()])
  } catch (error) {
    console.error('Error al conciliar cuota:', error)
    toast.error(error.response?.data?.message || 'Error al registrar cobro de la cuota')
  } finally {
    isPaying.value = false
  }
}

watch([page, itemsPerPage, statusFilter], () => {
  if (activeTab.value === 'credits') fetchCredits()
})

watch([schedulePage, scheduleItemsPerPage, scheduleStatusFilter], () => {
  if (activeTab.value === 'schedule') fetchSchedule()
})

watch(activeTab, (newTab) => {
  if (newTab === 'credits') fetchCredits()
  if (newTab === 'schedule') fetchSchedule()
})

onMounted(() => {
  fetchKpis()
  fetchCredits()
})
</script>

<template>
  <div class="cashea-credits-container">
    <!-- Encabezado con información del módulo -->
    <VCard variant="flat" class="mb-6 rounded-lg border shadow-sm">
      <VCardItem class="px-6 py-4">
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div class="d-flex align-center gap-3">
            <VAvatar color="warning" variant="tonal" size="48" class="rounded-lg">
              <VIcon icon="tabler-building-bank" size="28" />
            </VAvatar>
            <div>
              <div class="d-flex align-center gap-2">
                <h1 class="text-h5 font-weight-bold mb-0">Créditos Cashea</h1>
                <VChip color="warning" size="small" variant="tonal">
                  BNPL 14 Días
                </VChip>
              </div>
              <p class="text-body-2 text-medium-emphasis mb-0">
                Seguimiento de compras financiadas con Cashea, control de iniciales y liquidación de cuotas cada 14 días.
              </p>
            </div>
          </div>

          <div class="d-flex align-center gap-2">
            <VBtn
              color="primary"
              variant="outlined"
              prepend-icon="tabler-reload"
              :loading="isLoading || isKpisLoading"
              @click="fetchKpis(); activeTab === 'credits' ? fetchCredits() : fetchSchedule();"
            >
              Actualizar
            </VBtn>
          </div>
        </div>
      </VCardItem>
    </VCard>

    <!-- Fila de KPIs -->
    <VRow class="mb-6">
      <VCol cols="12" sm="6" md="3">
        <VCard variant="outlined" class="rounded-lg">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar color="primary" variant="tonal" size="44" class="rounded">
              <VIcon icon="tabler-receipt-2" size="24" />
            </VAvatar>
            <div>
              <div class="text-body-2 text-medium-emphasis">Total Financiado</div>
              <div class="text-h6 font-weight-bold">${{ kpis.total_financed_usd.toFixed(2) }}</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard variant="outlined" class="rounded-lg">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar color="warning" variant="tonal" size="44" class="rounded">
              <VIcon icon="tabler-clock-hour-4" size="24" />
            </VAvatar>
            <div>
              <div class="text-body-2 text-medium-emphasis">Saldo Pendiente</div>
              <div class="text-h6 font-weight-bold text-warning">${{ kpis.total_pending_usd.toFixed(2) }}</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard variant="outlined" class="rounded-lg">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar color="info" variant="tonal" size="44" class="rounded">
              <VIcon icon="tabler-calendar-due" size="24" />
            </VAvatar>
            <div>
              <div class="text-body-2 text-medium-emphasis">Próximos 14 Días</div>
              <div class="text-h6 font-weight-bold text-info">${{ kpis.due_next_14_days_usd.toFixed(2) }}</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard variant="outlined" class="rounded-lg">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar :color="kpis.overdue_usd > 0 ? 'error' : 'success'" variant="tonal" size="44" class="rounded">
              <VIcon :icon="kpis.overdue_usd > 0 ? 'tabler-alert-triangle' : 'tabler-check'" size="24" />
            </VAvatar>
            <div>
              <div class="text-body-2 text-medium-emphasis">Cuotas Vencidas</div>
              <div :class="['text-h6 font-weight-bold', kpis.overdue_usd > 0 ? 'text-error' : 'text-success']">
                ${{ kpis.overdue_usd.toFixed(2) }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Pestañas de Navegación -->
    <VTabs v-model="activeTab" class="mb-4" density="comfortable">
      <VTab value="credits">
        <VIcon start icon="tabler-list" />
        Órdenes Cashea
      </VTab>
      <VTab value="schedule">
        <VIcon start icon="tabler-calendar-time" />
        Cronograma de Cuotas (Cada 14 Días)
      </VTab>
    </VTabs>

    <VWindow v-model="activeTab" class="disable-tab-transition">
      <!-- Pestaña 1: Órdenes Cashea -->
      <VWindowItem value="credits">
        <VCard variant="outlined" class="rounded-lg">
          <VCardText class="pa-4 border-b">
            <VRow align="center">
              <VCol cols="12" sm="6" md="4">
                <VTextField
                  v-model="searchQuery"
                  density="compact"
                  variant="outlined"
                  placeholder="Buscar por cliente o ref. Cashea..."
                  prepend-inner-icon="tabler-search"
                  clearable
                  hide-details
                  @keydown.enter="fetchCredits"
                />
              </VCol>
              <VCol cols="12" sm="4" md="3">
                <VSelect
                  v-model="statusFilter"
                  density="compact"
                  variant="outlined"
                  label="Estado"
                  :items="[
                    { title: 'Todos', value: '' },
                    { title: 'Activos / Pendientes', value: 'Pending' },
                    { title: 'Pagados / Liquidados', value: 'Paid' },
                  ]"
                  hide-details
                />
              </VCol>
              <VCol cols="12" sm="2" md="2">
                <VBtn color="primary" variant="tonal" block @click="fetchCredits">
                  Filtrar
                </VBtn>
              </VCol>
            </VRow>
          </VCardText>

          <!-- Tabla de Órdenes Cashea -->
          <VTable density="comfortable" hover>
            <thead>
              <tr>
                <th class="text-left font-weight-bold">CLIENTE</th>
                <th class="text-left font-weight-bold">REF. CASHEA</th>
                <th class="text-left font-weight-bold">FECHA VENTA</th>
                <th class="text-right font-weight-bold">INICIAL CAJA</th>
                <th class="text-right font-weight-bold">TOTAL CRÉDITO</th>
                <th class="text-right font-weight-bold">PENDIENTE</th>
                <th class="text-center font-weight-bold">CUOTAS</th>
                <th class="text-center font-weight-bold">ESTADO</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="isLoading">
                <td colspan="8" class="text-center py-6">
                  <VProgressCircular indeterminate color="primary" size="32" />
                </td>
              </tr>
              <tr v-else-if="credits.length === 0">
                <td colspan="8" class="text-center py-8 text-medium-emphasis">
                  <VIcon icon="tabler-inbox" size="36" class="mb-2 d-block mx-auto text-disabled" />
                  No se encontraron créditos registrados con Cashea.
                </td>
              </tr>
              <tr v-for="item in credits" :key="item.id">
                <td>
                  <div class="font-weight-medium">
                    {{ item.client?.name }} {{ item.client?.last_name }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    ID: {{ item.client?.identification || 'S/N' }}
                  </div>
                </td>
                <td>
                  <VChip size="small" variant="outlined" color="primary">
                    {{ item.cashea_order_reference || 'CASHEA-' + item.id }}
                  </VChip>
                </td>
                <td>{{ item.credit_date || '-' }}</td>
                <td class="text-right font-weight-medium">
                  ${{ item.initial_payment_usd.toFixed(2) }}
                </td>
                <td class="text-right font-weight-bold">
                  ${{ item.credit_amount.toFixed(2) }}
                </td>
                <td class="text-right font-weight-bold text-warning">
                  ${{ item.pending_amount.toFixed(2) }}
                </td>
                <td class="text-center">
                  <div class="d-flex justify-center gap-1">
                    <VChip
                      v-for="inst in item.installments"
                      :key="inst.id"
                      size="x-small"
                      :color="inst.status === 'Paid' ? 'success' : (inst.status === 'Overdue' ? 'error' : 'secondary')"
                      variant="tonal"
                      class="cursor-pointer"
                      @click="inst.status !== 'Paid' && openPaymentModal(inst)"
                    >
                      #{{ inst.installment_number }}: ${{ inst.amount_usd.toFixed(0) }}
                    </VChip>
                  </div>
                </td>
                <td class="text-center">
                  <VChip
                    size="small"
                    :color="item.status === 'Paid' ? 'success' : 'warning'"
                    variant="tonal"
                  >
                    {{ item.status === 'Paid' ? 'Liquidado' : 'Pendiente' }}
                  </VChip>
                </td>
              </tr>
            </tbody>
          </VTable>

          <!-- Paginación -->
          <VCardActions class="d-flex justify-space-between align-center px-4 py-3 border-t">
            <span class="text-caption text-medium-emphasis">
              Total registros: {{ totalCredits }}
            </span>
            <VPagination
              v-model="page"
              :length="Math.ceil(totalCredits / itemsPerPage) || 1"
              :total-visible="5"
              density="compact"
            />
          </VCardActions>
        </VCard>
      </VWindowItem>

      <!-- Pestaña 2: Cronograma de Cuotas -->
      <VWindowItem value="schedule">
        <VCard variant="outlined" class="rounded-lg">
          <VCardText class="pa-4 border-b">
            <VRow align="center">
              <VCol cols="12" sm="6" md="4">
                <VTextField
                  v-model="scheduleSearch"
                  density="compact"
                  variant="outlined"
                  placeholder="Buscar por cliente o ref. pago..."
                  prepend-inner-icon="tabler-search"
                  clearable
                  hide-details
                  @keydown.enter="fetchSchedule"
                />
              </VCol>
              <VCol cols="12" sm="3" md="2">
                <VSelect
                  v-model="scheduleStatusFilter"
                  density="compact"
                  variant="outlined"
                  label="Estado"
                  :items="[
                    { title: 'Todas', value: '' },
                    { title: 'Pendientes', value: 'Pending' },
                    { title: 'Pagadas', value: 'Paid' },
                  ]"
                  hide-details
                />
              </VCol>
              <VCol cols="12" sm="3" md="2">
                <VTextField
                  v-model="scheduleDateFrom"
                  type="date"
                  density="compact"
                  variant="outlined"
                  label="Desde"
                  hide-details
                />
              </VCol>
              <VCol cols="12" sm="3" md="2">
                <VTextField
                  v-model="scheduleDateTo"
                  type="date"
                  density="compact"
                  variant="outlined"
                  label="Hasta"
                  hide-details
                />
              </VCol>
              <VCol cols="12" sm="2" md="2">
                <VBtn color="primary" variant="tonal" block @click="fetchSchedule">
                  Filtrar
                </VBtn>
              </VCol>
            </VRow>
          </VCardText>

          <VTable density="comfortable" hover>
            <thead>
              <tr>
                <th class="text-left font-weight-bold">CLIENTE</th>
                <th class="text-center font-weight-bold">NRO. CUOTA</th>
                <th class="text-center font-weight-bold">FECHA LÍMITE (+14d)</th>
                <th class="text-right font-weight-bold">MONTO USD</th>
                <th class="text-center font-weight-bold">ESTADO</th>
                <th class="text-left font-weight-bold">REF. LIQUIDACIÓN</th>
                <th class="text-center font-weight-bold">ACCIÓN</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="isLoading">
                <td colspan="7" class="text-center py-6">
                  <VProgressCircular indeterminate color="primary" size="32" />
                </td>
              </tr>
              <tr v-else-if="installments.length === 0">
                <td colspan="7" class="text-center py-8 text-medium-emphasis">
                  <VIcon icon="tabler-calendar-off" size="36" class="mb-2 d-block mx-auto text-disabled" />
                  No hay cuotas programadas en este período.
                </td>
              </tr>
              <tr v-for="inst in installments" :key="inst.id">
                <td>
                  <div class="font-weight-medium">{{ inst.client_name }}</div>
                  <div class="text-caption text-medium-emphasis">
                    Ref: {{ inst.cashea_order_reference || 'Orden #' + inst.credit_id }}
                  </div>
                </td>
                <td class="text-center font-weight-bold">
                  Cuota {{ inst.installment_number }}
                </td>
                <td class="text-center font-weight-medium">
                  {{ inst.due_date }}
                </td>
                <td class="text-right font-weight-bold">
                  ${{ inst.amount_usd.toFixed(2) }}
                </td>
                <td class="text-center">
                  <VChip
                    size="small"
                    :color="inst.status === 'Paid' ? 'success' : 'warning'"
                    variant="tonal"
                  >
                    {{ inst.status === 'Paid' ? 'Liquidada' : 'Pendiente' }}
                  </VChip>
                </td>
                <td>
                  {{ inst.payment_reference || '-' }}
                </td>
                <td class="text-center">
                  <VBtn
                    v-if="inst.status !== 'Paid'"
                    size="small"
                    color="primary"
                    variant="tonal"
                    prepend-icon="tabler-check"
                    @click="openPaymentModal(inst)"
                  >
                    Liquidar
                  </VBtn>
                  <span v-else class="text-caption text-success font-weight-bold">
                    Cobrado
                  </span>
                </td>
              </tr>
            </tbody>
          </VTable>

          <!-- Paginación -->
          <VCardActions class="d-flex justify-space-between align-center px-4 py-3 border-t">
            <span class="text-caption text-medium-emphasis">
              Total cuotas: {{ totalInstallments }}
            </span>
            <VPagination
              v-model="schedulePage"
              :length="Math.ceil(totalInstallments / scheduleItemsPerPage) || 1"
              :total-visible="5"
              density="compact"
            />
          </VCardActions>
        </VCard>
      </VWindowItem>
    </VWindow>

    <!-- Modal para conciliar/liquidar pago de cuota -->
    <VDialog v-model="isPaymentModalOpen" max-width="480">
      <VCard class="rounded-lg">
        <VCardTitle class="pa-4 border-b d-flex align-center justify-space-between">
          <span>Registrar Liquidación de Cuota</span>
          <VBtn icon="tabler-x" variant="text" size="small" @click="isPaymentModalOpen = false" />
        </VCardTitle>

        <VCardText class="pa-6">
          <div class="mb-4 text-body-2">
            <strong>Cliente:</strong> {{ selectedInstallment?.client_name }}<br />
            <strong>Cuota:</strong> #{{ selectedInstallment?.installment_number }}<br />
            <strong>Vencimiento:</strong> {{ selectedInstallment?.due_date }}
          </div>

          <VTextField
            v-model.number="paymentForm.amount_usd"
            label="Monto Liquidado (USD)"
            type="number"
            density="compact"
            variant="outlined"
            class="mb-4"
          />

          <VTextField
            v-model="paymentForm.payment_reference"
            label="Nro. Referencia Bancaria / Cashea"
            placeholder="Ej. TRANSF-892348"
            density="compact"
            variant="outlined"
          />
        </VCardText>

        <VCardActions class="pa-4 border-t d-flex justify-end gap-2">
          <VBtn variant="outlined" color="secondary" @click="isPaymentModalOpen = false">
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            prepend-icon="tabler-device-floppy"
            :loading="isPaying"
            @click="submitPayment"
          >
            Confirmar Liquidación
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.cashea-credits-container {
  width: 100%;
}
.disable-tab-transition :deep(.v-window__container) {
  transition: none !important;
}
</style>
