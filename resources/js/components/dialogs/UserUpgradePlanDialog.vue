<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'payment-reported'])

const isLoading = ref(false)
const isSubmitting = ref(false)
const subscription = ref(null)
const plans = ref([])
const activeTab = ref('overview')

const paymentForm = reactive({
  plan_code: 'basic',
  months_to_add: 1,
  amount_usd: 29,
  currency: 'USD',
  exchange_rate: 1,
  amount_paid_currency: 29,
  payment_method: 'transfer',
  reference_number: '',
  paid_at: new Date().toISOString().slice(0, 10),
  notes: '',
})

const paymentMethods = [
  { title: 'Transferencia Bancaria', value: 'transfer' },
  { title: 'Pago Móvil', value: 'pago_movil' },
  { title: 'Zelle (USD)', value: 'zelle' },
  { title: 'Binance Pay (USDT)', value: 'binance' },
  { title: 'Efectivo Divisas', value: 'cash_usd' },
]

const currencyOptions = [
  { title: 'Dólares (USD)', value: 'USD' },
  { title: 'Bolívares (BS)', value: 'BS' },
  { title: 'Pesos (COP)', value: 'COP' },
]

const dialogModelValueUpdate = (val) => {
  emit('update:isDialogVisible', val)
}

const selectedPlanObj = computed(() => {
  return plans.value.find(p => p.code === paymentForm.plan_code) || null
})

const recalculateAmount = () => {
  if (selectedPlanObj.value) {
    const basePrice = Number(selectedPlanObj.value.price_usd || 29)
    paymentForm.amount_usd = Number((basePrice * Number(paymentForm.months_to_add || 1)).toFixed(2))
    paymentForm.amount_paid_currency = Number((paymentForm.amount_usd * Number(paymentForm.exchange_rate || 1)).toFixed(2))
  }
}

const fetchSubscriptionAndPlans = async () => {
  isLoading.value = true
  try {
    const [subRes, plansRes] = await Promise.all([
      axios.get('/my-subscription'),
      axios.get('/public/saas/plans', { params: { only_active: 1 } }),
    ])

    subscription.value = subRes.data?.data || null
    plans.value = plansRes.data?.data || []

    if (subscription.value) {
      paymentForm.plan_code = subscription.value.plan_code || 'basic'
      paymentForm.amount_usd = Number(subscription.value.price_agreed_usd || 29)
      paymentForm.amount_paid_currency = paymentForm.amount_usd
    }
  } catch (error) {
    console.error('Error cargando estado de suscripción:', error)
  } finally {
    isLoading.value = false
  }
}

const submitReportPayment = async () => {
  if (!paymentForm.reference_number && paymentForm.payment_method !== 'cash_usd') {
    toast.error('Ingresa el número de referencia o comprobante del pago')
    return
  }

  isSubmitting.value = true
  try {
    const { data } = await axios.post('/my-subscription/report-payment', paymentForm)
    toast.success(data?.message || 'Pago reportado exitosamente')
    paymentForm.reference_number = ''
    paymentForm.notes = ''
    await fetchSubscriptionAndPlans()
    activeTab.value = 'overview'
    emit('payment-reported', data?.data)
  } catch (error) {
    toast.error(error.response?.data?.message || 'No se pudo registrar el reporte de pago')
  } finally {
    isSubmitting.value = false
  }
}

const copyLicenseKey = async (key) => {
  if (!key) return
  try {
    await navigator.clipboard.writeText(key)
    toast.success('Llave de licencia copiada al portapapeles')
  } catch {
    toast.info(key)
  }
}

watch(
  () => props.isDialogVisible,
  (val) => {
    if (val) {
      fetchSubscriptionAndPlans()
    }
  }
)

onMounted(() => {
  if (props.isDialogVisible) {
    fetchSubscriptionAndPlans()
  }
})
</script>

<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 780"
    :model-value="props.isDialogVisible"
    @update:model-value="dialogModelValueUpdate"
  >
    <VCard class="rounded-xl overflow-hidden">
      <VCardTitle class="pa-4 bg-primary text-white d-flex align-center justify-space-between">
        <div class="d-flex align-center ga-3">
          <VAvatar color="white" variant="flat" size="38">
            <VIcon icon="tabler-certificate" color="primary" size="22" />
          </VAvatar>
          <div>
            <div class="text-h6 font-weight-bold text-white">
              Mi Licencia y Suscripción SaaS
            </div>
            <div class="text-caption text-white opacity-90">
              Estado de cuenta, llave de licencia, planes y reporte de renovación
            </div>
          </div>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          color="white"
          size="small"
          @click="dialogModelValueUpdate(false)"
        />
      </VCardTitle>

      <VTabs v-model="activeTab" color="primary" class="border-b px-4">
        <VTab value="overview" prepend-icon="tabler-shield-check">
          Estado y Recibos
        </VTab>
        <VTab value="report" prepend-icon="tabler-cash-banknote">
          Reportar Pago / Renovar
        </VTab>
        <VTab value="plans" prepend-icon="tabler-rocket">
          Planes Disponibles
        </VTab>
      </VTabs>

      <VCardText class="pa-5">
        <div v-if="isLoading" class="py-8">
          <VSkeletonLoader type="article, table-row-divider@3" />
        </div>

        <VWindow v-else v-model="activeTab">
          <!-- Pestaña 1: Resumen de Licencia e Historial -->
          <VWindowItem value="overview">
            <div v-if="subscription">
              <VAlert
                v-if="!subscription.can_operate"
                type="error"
                variant="tonal"
                class="mb-4"
                title="Licencia Suspendida por Vencimiento"
              >
                Tu suscripción se encuentra vencida. Reporta tu pago en la pestaña <strong>Reportar Pago / Renovar</strong> para restablecer todas las operaciones.
              </VAlert>

              <VRow class="mb-4">
                <VCol cols="12" md="6">
                  <VCard variant="outlined" class="pa-4 rounded-lg h-100">
                    <div class="d-flex align-center justify-space-between mb-2">
                      <span class="text-caption text-uppercase font-weight-bold text-medium-emphasis">
                        Plan Contratado
                      </span>
                      <VChip
                        :color="subscription.status_color || 'success'"
                        size="small"
                        variant="tonal"
                        class="font-weight-bold"
                      >
                        {{ subscription.status_label }}
                      </VChip>
                    </div>
                    <div class="text-h5 font-weight-black text-primary mb-1">
                      {{ subscription.plan_name }}
                    </div>
                    <div class="text-body-2 text-high-emphasis mb-2">
                      Tarifa: <strong>${{ Number(subscription.price_agreed_usd || 0).toFixed(2) }} USD</strong> ({{ subscription.billing_cycle_label }})
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      Límites: Hasta {{ subscription.max_branches }} sede(s) · {{ subscription.max_users }} usuarios
                    </div>
                  </VCard>
                </VCol>

                <VCol cols="12" md="6">
                  <VCard variant="outlined" class="pa-4 rounded-lg h-100">
                    <div class="text-caption text-uppercase font-weight-bold text-medium-emphasis mb-1">
                      Llave de Licencia Activa
                    </div>
                    <div class="d-flex align-center justify-space-between bg-surface-light pa-2 rounded border mb-3">
                      <code class="text-body-2 font-weight-bold text-primary">
                        {{ subscription.license_key }}
                      </code>
                      <VBtn
                        icon="tabler-copy"
                        size="x-small"
                        variant="text"
                        color="primary"
                        title="Copiar llave"
                        @click="copyLicenseKey(subscription.license_key)"
                      />
                    </div>
                    <div class="d-flex justify-space-between text-body-2">
                      <span>Próximo corte:</span>
                      <strong :class="(subscription.days_remaining ?? 10) < 0 ? 'text-error' : 'text-high-emphasis'">
                        {{ subscription.current_period_ends_at || 'Sin definir' }}
                      </strong>
                    </div>
                  </VCard>
                </VCol>
              </VRow>

              <div class="d-flex align-center justify-space-between mb-2">
                <h4 class="text-subtitle-1 font-weight-bold">
                  Últimos Pagos y Renovaciones
                </h4>
                <VBtn
                  color="primary"
                  size="small"
                  prepend-icon="tabler-plus"
                  @click="activeTab = 'report'"
                >
                  Reportar Pago
                </VBtn>
              </div>

              <VTable v-if="subscription.payments?.length" density="compact" class="border rounded-lg">
                <thead>
                  <tr>
                    <th>Recibo</th>
                    <th>Fecha</th>
                    <th>Monto</th>
                    <th>Método / Ref.</th>
                    <th>Periodo</th>
                    <th>Estado</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="p in subscription.payments" :key="p.id">
                    <td class="font-weight-bold text-caption">{{ p.receipt_number }}</td>
                    <td class="text-caption">{{ p.paid_at ? p.paid_at.slice(0, 10) : '—' }}</td>
                    <td class="font-weight-bold">${{ Number(p.amount_usd).toFixed(2) }}</td>
                    <td class="text-caption">{{ p.payment_method }} ({{ p.reference_number || 'S/R' }})</td>
                    <td class="text-caption">{{ p.period_starts_at }} → {{ p.period_ends_at }}</td>
                    <td>
                      <VChip
                        size="x-small"
                        :color="p.status === 'approved' ? 'success' : (p.status === 'rejected' ? 'error' : 'warning')"
                        variant="tonal"
                      >
                        {{ p.status === 'approved' ? 'Aprobado' : (p.status === 'rejected' ? 'Rechazado' : 'En Revisión') }}
                      </VChip>
                    </td>
                  </tr>
                </tbody>
              </VTable>
              <VAlert v-else type="info" variant="tonal" density="compact">
                Aún no hay recibos de renovación registrados para esta cuenta.
              </VAlert>
            </div>
            <VAlert v-else type="info" variant="tonal">
              Esta es la instancia central de administración SaaS. Gestiona todas las licencias desde el módulo <strong>Empresas y Licencias (SaaS)</strong>.
            </VAlert>
          </VWindowItem>

          <!-- Pestaña 2: Formulario de Reporte de Pago -->
          <VWindowItem value="report">
            <VForm @submit.prevent="submitReportPayment">
              <VRow>
                <VCol cols="12" sm="6">
                  <VSelect
                    v-model="paymentForm.plan_code"
                    :items="plans"
                    item-title="name"
                    item-value="code"
                    label="Plan a Renovar o Actualizar"
                    variant="outlined"
                    density="comfortable"
                    @update:model-value="recalculateAmount"
                  />
                </VCol>
                <VCol cols="12" sm="6">
                  <VTextField
                    v-model.number="paymentForm.months_to_add"
                    type="number"
                    min="1"
                    max="36"
                    label="Cantidad de Períodos / Meses"
                    variant="outlined"
                    density="comfortable"
                    @update:model-value="recalculateAmount"
                  />
                </VCol>
                <VCol cols="12" sm="4">
                  <VTextField
                    v-model.number="paymentForm.amount_usd"
                    type="number"
                    step="0.01"
                    prefix="$"
                    label="Monto Equivalente (USD)"
                    variant="outlined"
                    density="comfortable"
                  />
                </VCol>
                <VCol cols="12" sm="4">
                  <VSelect
                    v-model="paymentForm.currency"
                    :items="currencyOptions"
                    label="Moneda de Pago"
                    variant="outlined"
                    density="comfortable"
                  />
                </VCol>
                <VCol cols="12" sm="4">
                  <VSelect
                    v-model="paymentForm.payment_method"
                    :items="paymentMethods"
                    label="Método de Pago"
                    variant="outlined"
                    density="comfortable"
                  />
                </VCol>
                <VCol cols="12" sm="6">
                  <VTextField
                    v-model="paymentForm.reference_number"
                    label="Número de Referencia / Comprobante *"
                    placeholder="Ej: 009482192"
                    variant="outlined"
                    density="comfortable"
                  />
                </VCol>
                <VCol cols="12" sm="6">
                  <VTextField
                    v-model="paymentForm.paid_at"
                    type="date"
                    label="Fecha del Pago"
                    variant="outlined"
                    density="comfortable"
                  />
                </VCol>
                <VCol cols="12">
                  <VTextarea
                    v-model="paymentForm.notes"
                    rows="2"
                    label="Observaciones adicionales"
                    placeholder="Indica titular de la cuenta o requerimiento de sucursales extra..."
                    variant="outlined"
                    density="comfortable"
                  />
                </VCol>
              </VRow>

              <div class="d-flex justify-end ga-2 mt-4">
                <VBtn variant="outlined" color="secondary" @click="activeTab = 'overview'">
                  Cancelar
                </VBtn>
                <VBtn
                  color="primary"
                  type="submit"
                  :loading="isSubmitting"
                  :disabled="isSubmitting"
                  prepend-icon="tabler-send"
                >
                  Enviar Reporte de Pago
                </VBtn>
              </div>
            </VForm>
          </VWindowItem>

          <!-- Pestaña 3: Catálogo de Planes -->
          <VWindowItem value="plans">
            <VRow>
              <VCol v-for="plan in plans" :key="plan.id" cols="12" md="4">
                <VCard variant="outlined" class="pa-4 rounded-lg h-100 d-flex flex-column justify-space-between">
                  <div>
                    <div class="text-subtitle-1 font-weight-bold text-high-emphasis">
                      {{ plan.name }}
                    </div>
                    <div class="text-h4 font-weight-black text-primary my-2">
                      ${{ Number(plan.price_usd).toFixed(2) }}
                      <span class="text-caption text-medium-emphasis">/ {{ plan.billing_cycle_label }}</span>
                    </div>
                    <p class="text-caption text-medium-emphasis mb-3">
                      {{ plan.description }}
                    </p>
                    <VDivider class="mb-3" />
                    <div class="text-caption mb-1">
                      <VIcon icon="tabler-check" color="success" size="16" class="me-1" />
                      Hasta <strong>{{ plan.max_branches }}</strong> sede(s)
                    </div>
                    <div class="text-caption mb-1">
                      <VIcon icon="tabler-check" color="success" size="16" class="me-1" />
                      Hasta <strong>{{ plan.max_users }}</strong> usuarios
                    </div>
                  </div>

                  <VBtn
                    block
                    color="primary"
                    :variant="subscription?.plan_code === plan.code ? 'tonal' : 'flat'"
                    class="mt-4"
                    @click="paymentForm.plan_code = plan.code; recalculateAmount(); activeTab = 'report'"
                  >
                    {{ subscription?.plan_code === plan.code ? 'Renovar Plan Actual' : 'Solicitar este Plan' }}
                  </VBtn>
                </VCard>
              </VCol>
            </VRow>
          </VWindowItem>
        </VWindow>
      </VCardText>
    </VCard>
  </VDialog>
</template>
