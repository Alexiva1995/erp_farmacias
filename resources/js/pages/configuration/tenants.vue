<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from '@axios'
import { toast, confirmDialog } from '@/plugins/sweetalert'
import { useBrandingStore } from '@/stores/useBrandingStore'

const brandingStore = useBrandingStore()
const isTenantInstance = computed(() => Boolean(brandingStore.settings.is_tenant))

// Pestaña activa de la consola SaaS
const activeTab = ref('tenants')

// Estados reactivos principales
const isLoading = ref(false)
const isLoadingMetrics = ref(false)
const isProcessingExpirations = ref(false)
const isSubmitting = ref(false)
const isDialogOpen = ref(false)
const searchQuery = ref('')
const statusFilter = ref('all')
const verticalFilter = ref('all')
const tenants = ref([])
const showPassword = ref(false)

// Métricas SaaS (MRR, ARR, Vencimientos, Recaudación)
const metrics = reactive({
  mrr_usd: 0,
  arr_usd: 0,
  collected_this_month_usd: 0,
  pending_approvals_count: 0,
  total_subscriptions: 0,
  active_count: 0,
  trial_count: 0,
  past_due_count: 0,
  suspended_count: 0,
  expiring_soon_count: 0,
})

// Catálogo dinámico de planes SaaS
const saasPlans = ref([])
const isLoadingPlans = ref(false)
const isPlanDialogOpen = ref(false)
const isSavingPlan = ref(false)
const editingPlanId = ref(null)
const planForm = reactive({
  code: '',
  name: '',
  description: '',
  billing_cycle: 'monthly',
  price_usd: 29,
  setup_fee_usd: 0,
  trial_days: 14,
  grace_days: 5,
  max_users: 5,
  max_branches: 1,
  features_text: '',
  is_active: true,
})

// Historial de Pagos y Renovaciones
const payments = ref([])
const isLoadingPayments = ref(false)
const paymentSearch = ref('')
const paymentStatusFilter = ref('')
const isPaymentDialogOpen = ref(false)
const isSavingPayment = ref(false)
const paymentForm = reactive({
  tenant_id: '',
  company_name: '',
  plan_code: 'basic',
  months_to_add: 1,
  amount_usd: 29,
  currency: 'USD',
  exchange_rate: 1,
  payment_method: 'transfer',
  reference_number: '',
  paid_at: new Date().toISOString().slice(0, 10),
  notes: '',
})

// Verificador de Llaves de Licencia
const verifyKeyInput = ref('')
const isVerifyingKey = ref(false)
const verifiedLicenseResult = ref(null)

// Envío de correo de cobro de mensualidad
const isSendBillingEmailDialogOpen = ref(false)
const isSendingBillingEmail = ref(false)
const sendBillingEmailForm = reactive({
  tenant_id: '',
  company_name: '',
  email: '',
  plan: '',
  usd_amount: 29,
  billing_date: '',
  rate: 3400,
})

const openSendBillingEmailDialog = (item) => {
  const raw = item?.raw ?? item
  sendBillingEmailForm.tenant_id = raw.id
  sendBillingEmailForm.company_name = raw.company_name || raw.id
  sendBillingEmailForm.email = raw.contact_email || ''
  sendBillingEmailForm.plan = raw.plan || 'basic'
  sendBillingEmailForm.usd_amount = Number(raw.monthly_price ?? 29)
  sendBillingEmailForm.billing_date = raw.billing_date || ''
  sendBillingEmailForm.rate = 3400
  isSendBillingEmailDialogOpen.value = true
}

const submitSendBillingEmail = async () => {
  if (!sendBillingEmailForm.email) {
    toast.error('Ingresa el correo electrónico de destino')
    return
  }
  isSendingBillingEmail.value = true
  try {
    const { data } = await axios.post(`/central/tenants/${sendBillingEmailForm.tenant_id}/send-billing-email`, {
      email: sendBillingEmailForm.email,
      rate: sendBillingEmailForm.rate,
    })
    toast.success(data?.message || 'Correo de cobro enviado exitosamente')
    isSendBillingEmailDialogOpen.value = false
    await fetchTenants()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error al enviar el correo de cobro')
  } finally {
    isSendingBillingEmail.value = false
  }
}

// Notificación / Toast feedback
const alert = reactive({
  show: false,
  type: 'success',
  message: '',
})

// Formulario de nueva empresa aprovisionada
const form = reactive({
  company_name: '',
  tenant_id: '',
  admin_name: 'Administrador',
  admin_email: '',
  password: '',
  vertical: 'pharmacy',
  plan: 'basic',
  monthly_price: 29,
  billing_date: new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10),
  seller_name: '',
})

// Helpers para resolución robusta de dominio y enlace
const getTenantDomain = (item) => {
  const doms = item?.domains ?? item?.raw?.domains
  if (Array.isArray(doms) && doms.length > 0 && doms[0]?.domain) {
    return doms[0].domain
  }
  const id = item?.id ?? item?.raw?.id
  return id ? `${id}.tovaerp.com` : ''
}

const getTenantUrl = (item) => {
  const domain = getTenantDomain(item)
  return domain ? `https://${domain}` : '#'
}

// Catálogo base de verticales del sistema
const defaultVerticalOptions = [
  {
    title: 'Farmacia & Salud Integral',
    value: 'pharmacy',
    icon: 'tabler-pill',
    color: 'primary',
    description: 'Control de medicamentos, lotes FEFO, vencimientos, psicotrópicos y récipes.',
  },
  {
    title: 'Droguería & Distribución Mayorista B2B',
    value: 'drogueria',
    icon: 'tabler-truck-delivery',
    color: 'info',
    description: 'Ventas mayoristas a farmacias, crédito x producto, cartera B2B y WMS picking.',
  },
  {
    title: 'Restaurante con Salón (Mesas & Comandas)',
    value: 'restaurant',
    icon: 'tabler-tools-kitchen-2',
    color: 'success',
    description: 'Gestión de mesas, comanderas/KDS por estación, recetas y división de cuentas.',
  },
  {
    title: 'Comida Rápida / Dark Kitchen / Cafetería',
    value: 'fast_food',
    icon: 'tabler-burger',
    color: 'warning',
    description: 'Venta rápida por mostrador/delivery, comanda directa, combos y cobro express.',
  },
  {
    title: 'Bodegón / Minimarket / Retail',
    value: 'minimarket',
    icon: 'tabler-building-store',
    color: 'secondary',
    description: 'Venta minorista de alta rotación, códigos de barras, bultos y promociones.',
  },
  {
    title: 'Administrador de Suscripciones SaaS & Licencias',
    value: 'saas_admin',
    icon: 'tabler-cloud-lock',
    color: 'primary',
    description: 'Consola comercial de software: venta de licencias, catálogo de planes y cobros recurrentes.',
  },
]

const verticalOptions = ref([...defaultVerticalOptions])

const selectedVerticalMeta = computed(() => {
  return verticalOptions.value.find(v => v.value === form.vertical) || defaultVerticalOptions[0]
})

const getVerticalBadge = (key) => {
  switch (key) {
    case 'drogueria':
      return { label: 'Droguería B2B', color: 'info', icon: 'tabler-truck-delivery' }
    case 'restaurant':
      return { label: 'Restaurante', color: 'success', icon: 'tabler-tools-kitchen-2' }
    case 'fast_food':
      return { label: 'Comida Rápida', color: 'warning', icon: 'tabler-burger' }
    case 'minimarket':
      return { label: 'Minimarket', color: 'secondary', icon: 'tabler-building-store' }
    case 'saas_admin':
      return { label: 'Admin SaaS', color: 'primary', icon: 'tabler-cloud-lock' }
    case 'orphan':
      return { label: 'BD Huérfana', color: 'error', icon: 'tabler-database-off' }
    case 'pharmacy':
      return { label: 'Farmacia', color: 'primary', icon: 'tabler-pill' }
    default: {
      const custom = verticalOptions.value.find(v => v.value === key)
      if (custom) {
        return { label: custom.title, color: custom.color || 'primary', icon: custom.icon || 'tabler-building' }
      }
      return { label: 'Farmacia', color: 'primary', icon: 'tabler-pill' }
    }
  }
}

// Cargar verticales dinámicas adicionales desde el backend
const fetchVerticalPresets = async () => {
  try {
    const response = await axios.get('/public/general-settings/presets')
    const presets = response.data?.data ?? []
    if (Array.isArray(presets) && presets.length > 0) {
      const merged = [...defaultVerticalOptions]
      presets.forEach((p) => {
        if (!merged.some(item => item.value === p.key)) {
          merged.push({
            title: p.label,
            value: p.key,
            icon: 'tabler-building',
            color: 'primary',
            description: p.description || 'Vertical personalizada del sistema.',
          })
        }
      })
      verticalOptions.value = merged
    }
  } catch {
    // Mantiene el catálogo base
  }
}

const errors = reactive({
  company_name: '',
  tenant_id: '',
  admin_name: '',
  admin_email: '',
  password: '',
  vertical: '',
})

const handleCompanyNameInput = () => {
  if (!form.tenant_id || form.tenant_id === slugify(form.company_name.slice(0, -1))) {
    form.tenant_id = slugify(form.company_name)
  }
}

const slugify = (text) => {
  return text
    .toString()
    .toLowerCase()
    .trim()
    .replace(/\s+/g, '-')
    .replace(/[^\w\-]+/g, '')
    .replace(/\-\-+/g, '-')
}

// Encabezados de la tabla de Empresas y Licencias
const headers = [
  { title: 'Empresa / Dominio', key: 'company_name', sortable: true },
  { title: 'Vertical', key: 'vertical', sortable: true },
  { title: 'Plan / Tarifa', key: 'plan', sortable: true },
  { title: 'Llave de Licencia', key: 'license_key', sortable: false },
  { title: 'Estado', key: 'subscription_status', sortable: true },
  { title: 'Vencimiento / Corte', key: 'billing_date', sortable: true },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'end' },
]

// Encabezados de Pagos y Renovaciones
const paymentHeaders = [
  { title: 'Recibo', key: 'receipt_number', sortable: true },
  { title: 'Empresa / Tenant', key: 'company_name', sortable: true },
  { title: 'Plan', key: 'plan_code', sortable: true },
  { title: 'Monto (USD)', key: 'amount_usd', sortable: true },
  { title: 'Método / Referencia', key: 'payment_method', sortable: false },
  { title: 'Periodo Cubierto', key: 'period', sortable: false },
  { title: 'Estado', key: 'status', sortable: true },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'end' },
]

// Opciones dinámicas de planes computadas desde saasPlans o fallback
const planOptions = computed(() => {
  if (saasPlans.value.length > 0) {
    return saasPlans.value.map(p => ({
      title: `${p.name} ($${Number(p.price_usd).toFixed(2)} USD)`,
      value: p.code,
      defaultPrice: Number(p.price_usd),
      maxBranches: Number(p.max_branches || 1),
      maxUsers: Number(p.max_users || 5),
    }))
  }
  return [
    { title: 'Plan Básico ($29 USD)', value: 'basic', defaultPrice: 29, maxBranches: 1, maxUsers: 5 },
    { title: 'Plan PRO ($49 USD)', value: 'pro', defaultPrice: 49, maxBranches: 5, maxUsers: 20 },
    { title: 'Plan Enterprise ($499 USD)', value: 'enterprise', defaultPrice: 499, maxBranches: 50, maxUsers: 999 },
  ]
})

const subscriptionStatusOptions = [
  { title: 'Activa', value: 'active' },
  { title: 'Periodo de Prueba (Trial)', value: 'trial' },
  { title: 'Pago Pendiente (Gracia)', value: 'past_due' },
  { title: 'Suspendida por Falta de Pago', value: 'suspended' },
  { title: 'Cancelada', value: 'cancelled' },
]

const billingCycleOptions = [
  { title: 'Mensual', value: 'monthly' },
  { title: 'Trimestral', value: 'quarterly' },
  { title: 'Anual', value: 'annual' },
  { title: 'Vitalicia (Pago Único)', value: 'lifetime' },
]

const paymentMethodOptions = [
  { title: 'Transferencia Bancaria', value: 'transfer' },
  { title: 'Pago Móvil', value: 'pago_movil' },
  { title: 'Zelle (USD)', value: 'zelle' },
  { title: 'Binance Pay (USDT)', value: 'binance' },
  { title: 'Efectivo Divisas', value: 'cash_usd' },
]

// Filtrado computado de empresas
const filteredTenants = computed(() => {
  return tenants.value.filter((item) => {
    const isOrphan = Boolean(item.is_orphan ?? item.raw?.is_orphan)
    const status = item.subscription_status ?? item.raw?.subscription_status ?? 'active'
    const vert = item.vertical ?? item.raw?.vertical ?? 'pharmacy'

    if (statusFilter.value === 'orphan' && !isOrphan) return false
    if (statusFilter.value !== 'all' && statusFilter.value !== 'orphan') {
      if (isOrphan || status !== statusFilter.value) return false
    }

    if (verticalFilter.value !== 'all' && vert !== verticalFilter.value) {
      return false
    }

    return true
  })
})

const nonOrphanTenants = computed(() => {
  return tenants.value.filter(t => !(t.is_orphan ?? t.raw?.is_orphan))
})

// Tenant Externo
const isExternalDialogOpen = ref(false)
const isSubmittingExternal = ref(false)
const externalForm = reactive({
  company_name: '',
  tenant_id: '',
  domain: '',
  vertical: 'pharmacy',
  plan: 'basic',
  billing_cycle: 'monthly',
  status: 'active',
  monthly_price: 29,
  billing_date: '',
  contact_name: '',
  contact_email: '',
  contact_phone: '',
  seller_name: '',
  notes: '',
})
const externalErrors = reactive({
  company_name: '',
  tenant_id: '',
  domain: '',
  plan: '',
  monthly_price: '',
  billing_date: '',
})

const openExternalDialog = () => {
  externalForm.company_name = ''
  externalForm.tenant_id = ''
  externalForm.domain = ''
  externalForm.vertical = 'pharmacy'
  externalForm.plan = 'basic'
  externalForm.billing_cycle = 'monthly'
  externalForm.status = 'active'
  externalForm.monthly_price = 29
  externalForm.billing_date = new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10)
  externalForm.contact_name = ''
  externalForm.contact_email = ''
  externalForm.contact_phone = ''
  externalForm.seller_name = ''
  externalForm.notes = ''
  Object.keys(externalErrors).forEach(k => { externalErrors[k] = '' })
  isExternalDialogOpen.value = true
}

const onExternalPlanChange = (planValue) => {
  const found = planOptions.value.find(p => p.value === planValue)
  if (found) {
    externalForm.monthly_price = found.defaultPrice
  }
}

const onCreatePlanChange = (planValue) => {
  const found = planOptions.value.find(p => p.value === planValue)
  if (found) {
    form.monthly_price = found.defaultPrice
  }
}

const submitExternalTenant = async () => {
  isSubmittingExternal.value = true
  Object.keys(externalErrors).forEach(k => { externalErrors[k] = '' })
  try {
    await axios.post('/central/tenants/external', externalForm)
    toast.success('Licencia externa registrada correctamente')
    isExternalDialogOpen.value = false
    await Promise.all([fetchTenants(), fetchMetrics()])
  } catch (error) {
    if (error.response?.status === 422 && error.response?.data?.errors) {
      const errs = error.response.data.errors
      Object.keys(errs).forEach((k) => {
        if (k in externalErrors) {
          externalErrors[k] = Array.isArray(errs[k]) ? errs[k][0] : errs[k]
        }
      })
    }
    toast.error(error.response?.data?.message || 'Error al registrar licencia externa')
  } finally {
    isSubmittingExternal.value = false
  }
}

// Editar Plan, Límites y Fecha de Cobro
const isBillingDialogOpen = ref(false)
const isSubmittingBilling = ref(false)
const billingForm = reactive({
  id: '',
  company_name: '',
  plan: 'basic',
  billing_cycle: 'monthly',
  status: 'active',
  monthly_price: 29,
  billing_date: '',
  created_at: '',
  max_users: 5,
  max_branches: 1,
  can_create_branches: false,
  contact_name: '',
  contact_email: '',
  contact_phone: '',
  seller_name: '',
  notes: '',
})

const openBillingDialog = (item) => {
  const raw = item?.raw ?? item
  billingForm.id = raw.id
  billingForm.company_name = raw.company_name || raw.id
  billingForm.plan = raw.plan || 'basic'
  billingForm.billing_cycle = raw.billing_cycle || 'monthly'
  billingForm.status = raw.subscription_status || 'active'
  billingForm.monthly_price = raw.monthly_price ?? (billingForm.plan === 'pro' ? 49 : 29)
  billingForm.billing_date = raw.billing_date || ''
  billingForm.created_at = raw.created_at ? String(raw.created_at).slice(0, 10) : ''
  billingForm.max_users = raw.max_users ?? 5
  billingForm.max_branches = raw.max_branches ?? 1
  billingForm.can_create_branches = Boolean(raw.can_create_branches)
  billingForm.contact_name = raw.contact_name || ''
  billingForm.contact_email = raw.contact_email || ''
  billingForm.contact_phone = raw.contact_phone || ''
  billingForm.seller_name = raw.seller_name || ''
  billingForm.notes = raw.notes || ''
  isBillingDialogOpen.value = true
}

const onBillingPlanChange = (planValue) => {
  const found = planOptions.value.find(p => p.value === planValue)
  if (found) {
    billingForm.monthly_price = found.defaultPrice
    billingForm.max_branches = found.maxBranches
    billingForm.max_users = found.maxUsers
    billingForm.can_create_branches = found.maxBranches > 1
  }
}

const submitBillingUpdate = async () => {
  isSubmittingBilling.value = true
  try {
    await axios.patch(`/central/tenants/${billingForm.id}/billing`, {
      plan: billingForm.plan,
      billing_cycle: billingForm.billing_cycle,
      status: billingForm.status,
      monthly_price: billingForm.monthly_price,
      billing_date: billingForm.billing_date || null,
      created_at: billingForm.created_at || null,
      max_users: billingForm.max_users,
      max_branches: billingForm.max_branches,
      can_create_branches: billingForm.can_create_branches,
      contact_name: billingForm.contact_name || null,
      contact_email: billingForm.contact_email || null,
      contact_phone: billingForm.contact_phone || null,
      seller_name: billingForm.seller_name || null,
      notes: billingForm.notes || null,
    })
    toast.success('Plan, límites y cobro actualizados')
    isBillingDialogOpen.value = false
    await Promise.all([fetchTenants(), fetchMetrics()])
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error al actualizar plan y cobro')
  } finally {
    isSubmittingBilling.value = false
  }
}

// Cargar métricas SaaS
const fetchMetrics = async () => {
  isLoadingMetrics.value = true
  try {
    const { data } = await axios.get('/central/saas/metrics')
    if (data?.data) {
      Object.assign(metrics, data.data)
    }
  } catch (error) {
    console.error('Error cargando métricas SaaS:', error)
  } finally {
    isLoadingMetrics.value = false
  }
}

// Cargar catálogo de planes SaaS
const fetchPlans = async () => {
  isLoadingPlans.value = true
  try {
    const { data } = await axios.get('/central/saas/plans')
    saasPlans.value = Array.isArray(data?.data) ? data.data : []
  } catch (error) {
    console.error('Error cargando planes SaaS:', error)
  } finally {
    isLoadingPlans.value = false
  }
}

const openCreatePlanDialog = () => {
  editingPlanId.value = null
  planForm.code = ''
  planForm.name = ''
  planForm.description = ''
  planForm.billing_cycle = 'monthly'
  planForm.price_usd = 29
  planForm.setup_fee_usd = 0
  planForm.trial_days = 14
  planForm.grace_days = 5
  planForm.max_users = 5
  planForm.max_branches = 1
  planForm.features_text = '1 Sede activa\nHasta 5 usuarios\nSoporte técnico incluido'
  planForm.is_active = true
  isPlanDialogOpen.value = true
}

const openEditPlanDialog = (plan) => {
  editingPlanId.value = plan.id
  planForm.code = plan.code
  planForm.name = plan.name
  planForm.description = plan.description || ''
  planForm.billing_cycle = plan.billing_cycle || 'monthly'
  planForm.price_usd = Number(plan.price_usd || 0)
  planForm.setup_fee_usd = Number(plan.setup_fee_usd || 0)
  planForm.trial_days = Number(plan.trial_days ?? 14)
  planForm.grace_days = Number(plan.grace_days ?? 5)
  planForm.max_users = Number(plan.max_users ?? 5)
  planForm.max_branches = Number(plan.max_branches ?? 1)
  planForm.features_text = Array.isArray(plan.features) ? plan.features.join('\n') : ''
  planForm.is_active = Boolean(plan.is_active)
  isPlanDialogOpen.value = true
}

const submitSavePlan = async () => {
  isSavingPlan.value = true
  try {
    const payload = {
      code: planForm.code,
      name: planForm.name,
      description: planForm.description,
      billing_cycle: planForm.billing_cycle,
      price_usd: planForm.price_usd,
      setup_fee_usd: planForm.setup_fee_usd,
      trial_days: planForm.trial_days,
      grace_days: planForm.grace_days,
      max_users: planForm.max_users,
      max_branches: planForm.max_branches,
      features: planForm.features_text
        .split('\n')
        .map(s => s.trim())
        .filter(Boolean),
      is_active: planForm.is_active,
    }

    if (editingPlanId.value) {
      await axios.put(`/central/saas/plans/${editingPlanId.value}`, payload)
      toast.success('Plan actualizado exitosamente')
    } else {
      await axios.post('/central/saas/plans', payload)
      toast.success('Plan creado exitosamente')
    }
    isPlanDialogOpen.value = false
    await fetchPlans()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error al guardar el plan')
  } finally {
    isSavingPlan.value = false
  }
}

const handleDeletePlan = async (plan) => {
  const confirmed = await confirmDialog({
    title: `¿Eliminar "${plan.name}"?`,
    text: 'Las suscripciones existentes conservarán su tarifa acordada.',
    icon: 'warning',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
  })
  if (!confirmed) return

  try {
    await axios.delete(`/central/saas/plans/${plan.id}`)
    toast.success('Plan eliminado')
    await fetchPlans()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error al eliminar el plan')
  }
}

// Cargar historial de pagos y renovaciones
const fetchPayments = async () => {
  isLoadingPayments.value = true
  try {
    const { data } = await axios.get('/central/saas/payments', {
      params: {
        search: paymentSearch.value || undefined,
        status: paymentStatusFilter.value || undefined,
        per_page: 50,
      },
    })
    payments.value = Array.isArray(data?.data) ? data.data : []
  } catch (error) {
    console.error('Error cargando pagos SaaS:', error)
  } finally {
    isLoadingPayments.value = false
  }
}

const openPaymentDialog = (item = null) => {
  const raw = item?.raw ?? item
  paymentForm.tenant_id = raw?.id || (nonOrphanTenants.value[0]?.id ?? '')
  paymentForm.company_name = raw?.company_name || ''
  paymentForm.plan_code = raw?.plan || 'basic'
  paymentForm.months_to_add = 1
  paymentForm.amount_usd = Number(raw?.monthly_price ?? 29)
  paymentForm.currency = 'USD'
  paymentForm.exchange_rate = 1
  paymentForm.payment_method = 'transfer'
  paymentForm.reference_number = ''
  paymentForm.paid_at = new Date().toISOString().slice(0, 10)
  paymentForm.notes = ''
  isPaymentDialogOpen.value = true
}

const onPaymentTenantChange = (tenantId) => {
  const found = tenants.value.find(t => (t.id ?? t.raw?.id) === tenantId)
  if (found) {
    paymentForm.company_name = found.company_name
    paymentForm.plan_code = found.plan || 'basic'
    paymentForm.amount_usd = Number(found.monthly_price ?? 29) * Number(paymentForm.months_to_add || 1)
  }
}

const submitRegisterPayment = async () => {
  if (!paymentForm.tenant_id) {
    toast.error('Selecciona la empresa a renovar')
    return
  }
  isSavingPayment.value = true
  try {
    const { data } = await axios.post('/central/saas/payments', {
      ...paymentForm,
      auto_approve: true,
    })
    toast.success(data?.message || 'Pago registrado y licencia renovada')
    isPaymentDialogOpen.value = false
    await Promise.all([fetchTenants(), fetchMetrics(), fetchPayments()])
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error al registrar el pago')
  } finally {
    isSavingPayment.value = false
  }
}

const handleApprovePayment = async (payment) => {
  try {
    const { data } = await axios.post(`/central/saas/payments/${payment.id}/approve`)
    toast.success(data?.message || 'Pago aprobado y licencia renovada')
    await Promise.all([fetchPayments(), fetchTenants(), fetchMetrics()])
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error al aprobar el pago')
  }
}

const handleRejectPayment = async (payment) => {
  try {
    const { data } = await axios.post(`/central/saas/payments/${payment.id}/reject`)
    toast.success(data?.message || 'Pago rechazado')
    await Promise.all([fetchPayments(), fetchMetrics()])
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error al rechazar el pago')
  }
}

// Suspender / Reactivar Licencia con 1 clic
const handleToggleSubscriptionStatus = async (item) => {
  const raw = item?.raw ?? item
  const currentStatus = raw.subscription_status || 'active'
  const nextStatus = currentStatus === 'suspended' ? 'active' : 'suspended'
  const actionLabel = nextStatus === 'suspended' ? 'Suspender acceso de' : 'Reactivar licencia de'

  const confirmed = await confirmDialog({
    title: `¿${actionLabel} "${raw.company_name}"?`,
    text: nextStatus === 'suspended'
      ? 'La empresa pasará a modo restringido (solo lectura y pantalla de pago pendiente) de inmediato.'
      : 'La empresa recuperará el acceso operativo completo inmediatamente.',
    icon: nextStatus === 'suspended' ? 'warning' : 'question',
    confirmButtonText: nextStatus === 'suspended' ? 'Sí, suspender licencia' : 'Sí, reactivar licencia',
    cancelButtonText: 'Cancelar',
  })

  if (!confirmed) return

  try {
    await axios.patch(`/central/tenants/${raw.id}/status`, { status: nextStatus })
    toast.success(nextStatus === 'suspended' ? 'Licencia suspendida' : 'Licencia reactivada exitosamente')
    await Promise.all([fetchTenants(), fetchMetrics()])
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error cambiando el estado de la licencia')
  }
}

// Copiar o regenerar llave de licencia
const copyLicenseKey = async (key) => {
  if (!key) return
  try {
    await navigator.clipboard.writeText(key)
    toast.success('Llave de licencia copiada al portapapeles')
  } catch {
    toast.info(key)
  }
}

const handleRegenerateKey = async (item) => {
  const raw = item?.raw ?? item
  const confirmed = await confirmDialog({
    title: `¿Regenerar llave de licencia para "${raw.company_name}"?`,
    text: 'La llave anterior quedará revocada de inmediato.',
    icon: 'question',
    confirmButtonText: 'Sí, generar nueva llave',
    cancelButtonText: 'Cancelar',
  })
  if (!confirmed) return

  try {
    const { data } = await axios.post(`/central/tenants/${raw.id}/regenerate-key`)
    toast.success(data?.message || 'Nueva llave generada')
    await fetchTenants()
  } catch (error) {
    toast.error(error.response?.data?.message || 'No se pudo regenerar la llave')
  }
}

// Ejecutar corte automático de vencimientos
const handleProcessExpirations = async () => {
  isProcessingExpirations.value = true
  try {
    const { data } = await axios.post('/central/saas/process-expirations')
    toast.success(data?.message || 'Corte de vencimientos procesado')
    await Promise.all([fetchTenants(), fetchMetrics()])
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error al procesar corte de vencimientos')
  } finally {
    isProcessingExpirations.value = false
  }
}

// Verificar llave de licencia en pestaña Validador
const handleVerifyLicenseKey = async () => {
  if (!verifyKeyInput.value.trim()) {
    toast.error('Ingresa una llave de licencia para verificar')
    return
  }
  isVerifyingKey.value = true
  verifiedLicenseResult.value = null
  try {
    const { data } = await axios.post('/public/licenses/verify', {
      license_key: verifyKeyInput.value.trim(),
    })
    verifiedLicenseResult.value = data?.data || null
    toast.success('Licencia válida y verificada')
  } catch (error) {
    verifiedLicenseResult.value = error.response?.data?.data || {
      valid: false,
      can_operate: false,
      message: error.response?.data?.message || 'Licencia inválida o inexistente',
    }
    toast.error('La llave de licencia no es válida')
  } finally {
    isVerifyingKey.value = false
  }
}

// Cargar lista de empresas registradas
const fetchTenants = async () => {
  isLoading.value = true
  try {
    const response = await axios.get('/central/tenants')
    const data = response.data?.data ?? response.data
    tenants.value = Array.isArray(data) ? data : []
  } catch (error) {
    console.error('Error al cargar empresas:', error)
    tenants.value = []
    showAlert('error', error.response?.data?.message || 'Error al conectar con el servidor central')
  } finally {
    isLoading.value = false
  }
}

const resetForm = () => {
  form.company_name = ''
  form.tenant_id = ''
  form.admin_name = 'Administrador'
  form.admin_email = ''
  form.password = ''
  form.vertical = 'pharmacy'
  form.plan = 'basic'
  form.monthly_price = 29
  form.billing_date = new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10)
  form.seller_name = ''
  showPassword.value = false

  Object.keys(errors).forEach((key) => {
    errors[key] = ''
  })
}

const openCreateDialog = () => {
  resetForm()
  isDialogOpen.value = true
}

const closeCreateDialog = () => {
  isDialogOpen.value = false
  resetForm()
}

const showAlert = (type, message) => {
  alert.type = type
  alert.message = message
  alert.show = true
  if (type === 'success') {
    toast.success(message)
  } else {
    toast.error(message)
  }
  setTimeout(() => {
    alert.show = false
  }, 6000)
}

const handleCreateTenant = async () => {
  Object.keys(errors).forEach((key) => {
    errors[key] = ''
  })

  isSubmitting.value = true
  try {
    const response = await axios.post('/central/tenants', {
      company_name: form.company_name,
      tenant_id: form.tenant_id,
      domain: form.tenant_id,
      admin_name: form.admin_name,
      admin_email: form.admin_email,
      password: form.password,
      vertical: form.vertical,
      plan: form.plan,
      monthly_price: form.monthly_price,
      billing_date: form.billing_date,
      seller_name: form.seller_name,
    })

    showAlert('success', response.data.message || 'Empresa creada, aprovisionada y licenciada exitosamente')
    closeCreateDialog()
    await Promise.all([fetchTenants(), fetchMetrics()])
  } catch (error) {
    if (error.response?.status === 422 && error.response?.data?.errors) {
      const serverErrors = error.response.data.errors
      Object.keys(serverErrors).forEach((field) => {
        if (errors[field] !== undefined) {
          errors[field] = serverErrors[field][0]
        }
      })
    } else {
      showAlert('error', error.response?.data?.message || 'Ocurrió un error al aprovisionar la empresa')
    }
  } finally {
    isSubmitting.value = false
  }
}

// Estado para modal de eliminación
const isDeleteDialogOpen = ref(false)
const isDeleting = ref(false)
const tenantToDelete = ref(null)

// Estado para modal de vinculación de base de datos existente
const isLinkDialogOpen = ref(false)
const isLinking = ref(false)
const linkForm = reactive({
  company_name: '',
  tenant_id: '',
  database: '',
  domain: '',
  vertical: 'pharmacy',
})

const openLinkDialog = (item) => {
  const rawId = item.id ?? item.raw?.id ?? ''
  const rawDb = item.database ?? item.raw?.database ?? ''
  linkForm.company_name = (item.company_name ?? item.raw?.company_name ?? rawId).replace(/^BD Huérfana \((.*?)\)$/, '$1')
  linkForm.tenant_id = rawId
  linkForm.database = rawDb
  linkForm.domain = rawId ? `${rawId}.tovaerp.com` : ''
  linkForm.vertical = 'pharmacy'
  isLinkDialogOpen.value = true
}

const handleLinkTenant = async () => {
  if (!linkForm.tenant_id || !linkForm.database) return

  isLinking.value = true
  try {
    const response = await axios.post('/central/tenants/link', linkForm)
    showAlert('success', response.data.message || 'Empresa vinculada exitosamente')
    isLinkDialogOpen.value = false
    await Promise.all([fetchTenants(), fetchMetrics()])
  } catch (error) {
    showAlert('error', error.response?.data?.message || 'Error al vincular la empresa')
  } finally {
    isLinking.value = false
  }
}

const confirmDeleteTenant = (tenant) => {
  tenantToDelete.value = tenant
  isDeleteDialogOpen.value = true
}

// Estado para gestión de dominios del tenant
const isManageDomainsDialogOpen = ref(false)
const isAddingDomain = ref(false)
const isDeletingDomain = ref(false)
const tenantForDomains = ref(null)
const newDomainInput = ref('')
const domainError = ref('')

const openManageDomainsDialog = (tenant) => {
  tenantForDomains.value = tenant
  newDomainInput.value = ''
  domainError.value = ''
  isManageDomainsDialogOpen.value = true
}

const handleAddDomain = async () => {
  const domain = newDomainInput.value.trim().toLowerCase()
  if (!domain) {
    domainError.value = 'Ingresa un dominio o subdominio válido'
    return
  }

  const tenantId = tenantForDomains.value?.id ?? tenantForDomains.value?.raw?.id
  if (!tenantId) return

  isAddingDomain.value = true
  domainError.value = ''
  try {
    const response = await axios.post(`/central/tenants/${tenantId}/domains`, { domain })
    showAlert('success', response.data.message || 'Dominio agregado exitosamente')
    newDomainInput.value = ''

    await fetchTenants()
    const updated = tenants.value.find(t => (t.id ?? t.raw?.id) === tenantId)
    if (updated) {
      tenantForDomains.value = updated
    }
  } catch (error) {
    domainError.value = error.response?.data?.errors?.domain?.[0] || error.response?.data?.message || 'Error al agregar el dominio'
    showAlert('error', domainError.value)
  } finally {
    isAddingDomain.value = false
  }
}

const handleRemoveDomain = async (domainItem) => {
  const tenantId = tenantForDomains.value?.id ?? tenantForDomains.value?.raw?.id
  const domainId = domainItem.id || domainItem.domain
  if (!tenantId || !domainId) return

  isDeletingDomain.value = true
  try {
    const response = await axios.delete(`/central/tenants/${tenantId}/domains/${domainId}`)
    showAlert('success', response.data.message || 'Dominio eliminado exitosamente')

    await fetchTenants()
    const updated = tenants.value.find(t => (t.id ?? t.raw?.id) === tenantId)
    if (updated) {
      tenantForDomains.value = updated
    }
  } catch (error) {
    showAlert('error', error.response?.data?.message || 'Error al eliminar el dominio')
  } finally {
    isDeletingDomain.value = false
  }
}

const handleDeleteTenant = async () => {
  if (!tenantToDelete.value) return

  const id = tenantToDelete.value.id ?? tenantToDelete.value.raw?.id
  const database = tenantToDelete.value.database ?? tenantToDelete.value.raw?.database
  isDeleting.value = true
  try {
    const response = await axios.delete(`/central/tenants/${id}`, {
      params: { database },
    })
    showAlert('success', response.data.message || 'Empresa y base de datos eliminadas exitosamente')
    isDeleteDialogOpen.value = false
    tenantToDelete.value = null
    await Promise.all([fetchTenants(), fetchMetrics()])
  } catch (error) {
    showAlert('error', error.response?.data?.message || 'Error al eliminar la empresa')
  } finally {
    isDeleting.value = false
  }
}

const refreshAllSaasData = async () => {
  await Promise.all([
    fetchTenants(),
    fetchMetrics(),
    fetchPlans(),
    fetchPayments(),
  ])
}

onMounted(() => {
  fetchVerticalPresets()
  if (!isTenantInstance.value) {
    refreshAllSaasData()
  }
})
</script>

<template>
  <div v-if="isTenantInstance">
    <VAlert
      type="warning"
      variant="tonal"
      class="mb-6"
      title="Módulo Exclusivo de la Instancia Central (Master)"
    >
      La consola de administración de suscripciones y licencias SaaS solo está habilitada desde el dominio maestro central del sistema.
    </VAlert>
  </div>

  <div v-else>
    <!-- Encabezado Principal -->
    <div class="d-flex flex-column flex-md-row align-md-center justify-space-between mb-6 ga-3">
      <div>
        <h1 class="text-h5 font-weight-bold text-high-emphasis mb-1">
          Administrador de Suscripciones SaaS & Licencias
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Consola comercial de aprovisionamiento, emisión de llaves de licencia, catálogo de planes, cobranza recurrente y control de vencimientos.
        </p>
      </div>

      <div class="d-flex align-center flex-wrap ga-2">
        <VBtn
          color="warning"
          variant="tonal"
          prepend-icon="tabler-clock-Exclamation"
          :loading="isProcessingExpirations"
          @click="handleProcessExpirations"
        >
          Procesar Corte Diario
        </VBtn>

        <VBtn
          color="success"
          variant="outlined"
          prepend-icon="tabler-cash-banknote"
          @click="openPaymentDialog(null)"
        >
          Registrar Pago
        </VBtn>

        <VBtn
          color="primary"
          variant="outlined"
          prepend-icon="tabler-world-plus"
          @click="openExternalDialog"
        >
          Licencia Externa
        </VBtn>

        <VBtn
          color="primary"
          variant="flat"
          prepend-icon="tabler-plus"
          class="font-weight-black shadow-primary"
          @click="openCreateDialog"
        >
          Nueva Empresa (SaaS)
        </VBtn>
      </div>
    </div>

    <!-- Tarjetas de KPIs Financieros y Operativos SaaS -->
    <VRow class="mb-6">
      <VCol cols="12" sm="6" md="3">
        <VCard variant="outlined" class="pa-4 rounded-lg h-100">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
              MRR (Ingreso Mensual)
            </span>
            <VAvatar color="primary" variant="tonal" size="34" rounded>
              <VIcon icon="tabler-chart-Area-line" size="20" />
            </VAvatar>
          </div>
          <div class="text-h4 font-weight-black text-primary">
            ${{ Number(metrics.mrr_usd || 0).toFixed(2) }}
          </div>
          <div class="text-caption text-medium-emphasis mt-1">
            ARR Proyectado: <strong>${{ Number(metrics.arr_usd || 0).toFixed(2) }} USD/año</strong>
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard variant="outlined" class="pa-4 rounded-lg h-100">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
              Recaudado Este Mes
            </span>
            <VAvatar color="success" variant="tonal" size="34" rounded>
              <VIcon icon="tabler-cash" size="20" />
            </VAvatar>
          </div>
          <div class="text-h4 font-weight-black text-success">
            ${{ Number(metrics.collected_this_month_usd || 0).toFixed(2) }}
          </div>
          <div class="text-caption text-medium-emphasis mt-1">
            Pagos por aprobar:
            <strong :class="metrics.pending_approvals_count > 0 ? 'text-warning' : ''">
              {{ metrics.pending_approvals_count }} pendiente(s)
            </strong>
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard variant="outlined" class="pa-4 rounded-lg h-100">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
              Licencias Activas
            </span>
            <VAvatar color="info" variant="tonal" size="34" rounded>
              <VIcon icon="tabler-certificate" size="20" />
            </VAvatar>
          </div>
          <div class="text-h4 font-weight-black text-high-emphasis">
            {{ metrics.active_count }}
            <span class="text-body-2 text-medium-emphasis">/ {{ metrics.total_subscriptions }}</span>
          </div>
          <div class="text-caption text-medium-emphasis mt-1">
            En prueba (Trial): <strong>{{ metrics.trial_count }}</strong>
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard variant="outlined" class="pa-4 rounded-lg h-100">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">
              Por Vencer / Mora
            </span>
            <VAvatar color="error" variant="tonal" size="34" rounded>
              <VIcon icon="tabler-alert-triangle" size="20" />
            </VAvatar>
          </div>
          <div class="d-flex align-baseline ga-3">
            <div class="text-h4 font-weight-black text-warning">
              {{ metrics.expiring_soon_count }}
              <span class="text-caption">próx. 7d</span>
            </div>
            <div class="text-h5 font-weight-black text-error">
              {{ metrics.past_due_count + metrics.suspended_count }}
              <span class="text-caption">mora/susp.</span>
            </div>
          </div>
          <div class="text-caption text-medium-emphasis mt-1">
            En gracia: <strong>{{ metrics.past_due_count }}</strong> · Suspendidas: <strong>{{ metrics.suspended_count }}</strong>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- Navegación por Pestañas de la Consola SaaS -->
    <VCard class="rounded-lg border shadow-sm overflow-hidden">
      <VTabs v-model="activeTab" color="primary" class="border-b px-4">
        <VTab value="tenants" prepend-icon="tabler-building-store">
          Empresas y Licencias ({{ tenants.length }})
        </VTab>
        <VTab value="plans" prepend-icon="tabler-tags">
          Catálogo de Planes ({{ saasPlans.length }})
        </VTab>
        <VTab value="payments" prepend-icon="tabler-receipt-2">
          Cobranza y Renovaciones
          <VBadge
            v-if="metrics.pending_approvals_count > 0"
            :content="metrics.pending_approvals_count"
            color="warning"
            inline
            class="ms-1"
          />
        </VTab>
        <VTab value="verifier" prepend-icon="tabler-shield-check">
          Verificador de Licencias (API)
        </VTab>
      </VTabs>

      <VWindow v-model="activeTab">
        <!-- PESTAÑA 1: EMPRESAS Y LICENCIAS -->
        <VWindowItem value="tenants">
          <VCardText class="pa-4">
            <VRow align="center">
              <VCol cols="12" sm="4">
                <VTextField
                  v-model="searchQuery"
                  density="compact"
                  placeholder="Buscar por empresa, llave o dominio..."
                  prepend-inner-icon="tabler-search"
                  variant="outlined"
                  hide-details
                  clearable
                />
              </VCol>
              <VCol cols="12" sm="3">
                <VSelect
                  v-model="statusFilter"
                  :items="[
                    { title: 'Todos los Estados', value: 'all' },
                    { title: 'Activas', value: 'active' },
                    { title: 'Periodo de Prueba', value: 'trial' },
                    { title: 'En Gracia (Pago Pendiente)', value: 'past_due' },
                    { title: 'Suspendidas', value: 'suspended' },
                    { title: 'BD Huérfanas', value: 'orphan' },
                  ]"
                  density="compact"
                  variant="outlined"
                  hide-details
                />
              </VCol>
              <VCol cols="12" sm="3">
                <VSelect
                  v-model="verticalFilter"
                  :items="[{ title: 'Todas las Verticales', value: 'all' }, ...verticalOptions]"
                  item-title="title"
                  item-value="value"
                  density="compact"
                  variant="outlined"
                  hide-details
                />
              </VCol>
              <VCol cols="12" sm="2" class="d-flex justify-end align-center ga-2">
                <VBtn
                  variant="tonal"
                  color="primary"
                  size="small"
                  icon="tabler-refresh"
                  :loading="isLoading"
                  @click="refreshAllSaasData"
                />
              </VCol>
            </VRow>
          </VCardText>

          <VDivider />

          <VDataTable
            :headers="headers"
            :items="filteredTenants"
            :search="searchQuery"
            :loading="isLoading"
            loading-text="Cargando empresas y licencias..."
            no-data-text="No se encontraron empresas con los filtros seleccionados"
            hover
            class="elevation-0 text-no-wrap"
          >
            <!-- Empresa y Dominio -->
            <template #item.company_name="{ item }">
              <div class="py-2">
                <div class="d-flex align-center ga-2">
                  <a
                    v-if="!(item.is_orphan ?? item.raw?.is_orphan)"
                    :href="getTenantUrl(item)"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="font-weight-bold text-primary text-decoration-none d-inline-flex align-center ga-1"
                  >
                    <span>{{ item.company_name ?? item.raw?.company_name }}</span>
                    <VIcon icon="tabler-external-link" size="14" />
                  </a>
                  <span v-else class="font-weight-bold text-high-emphasis">
                    {{ item.company_name ?? item.raw?.company_name }}
                  </span>
                  <VChip
                    v-if="item.is_remote ?? item.raw?.is_remote"
                    size="x-small"
                    color="secondary"
                    variant="outlined"
                  >
                    Externa
                  </VChip>
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ getTenantDomain(item) }}
                  <span v-if="item.seller_name ?? item.raw?.seller_name" class="ms-2 text-primary">
                    · Vend: {{ item.seller_name ?? item.raw?.seller_name }}
                  </span>
                </div>
              </div>
            </template>

            <!-- Vertical -->
            <template #item.vertical="{ item }">
              <VChip
                size="small"
                variant="tonal"
                :color="getVerticalBadge(item.vertical ?? item.raw?.vertical).color"
                class="font-weight-bold"
              >
                <VIcon start size="14" :icon="getVerticalBadge(item.vertical ?? item.raw?.vertical).icon" />
                {{ getVerticalBadge(item.vertical ?? item.raw?.vertical).label }}
              </VChip>
            </template>

            <!-- Plan y Costo -->
            <template #item.plan="{ item }">
              <div v-if="!(item.is_orphan ?? item.raw?.is_orphan)" class="d-flex flex-column">
                <div class="d-flex align-center ga-2">
                  <VChip
                    size="x-small"
                    variant="tonal"
                    color="primary"
                    class="font-weight-bold text-uppercase"
                  >
                    {{ item.plan ?? item.raw?.plan ?? 'basic' }}
                  </VChip>
                  <span class="font-weight-bold text-high-emphasis">
                    ${{ Number(item.monthly_price ?? item.raw?.monthly_price ?? 29).toFixed(2) }}
                  </span>
                </div>
                <span class="text-caption text-medium-emphasis">
                  Máx: {{ item.max_branches ?? item.raw?.max_branches ?? 1 }} sede(s) · {{ item.max_users ?? item.raw?.max_users ?? 5 }} usu.
                </span>
              </div>
              <span v-else class="text-caption text-medium-emphasis">—</span>
            </template>

            <!-- Llave de Licencia -->
            <template #item.license_key="{ item }">
              <div v-if="(item.license_key ?? item.raw?.license_key)" class="d-flex align-center ga-1">
                <code class="text-caption font-weight-bold bg-surface-light px-2 py-1 rounded border">
                  {{ item.license_key ?? item.raw?.license_key }}
                </code>
                <VBtn
                  icon="tabler-copy"
                  variant="text"
                  size="x-small"
                  color="primary"
                  title="Copiar llave de licencia"
                  @click="copyLicenseKey(item.license_key ?? item.raw?.license_key)"
                />
                <VBtn
                  icon="tabler-refresh"
                  variant="text"
                  size="x-small"
                  color="secondary"
                  title="Regenerar llave de licencia"
                  @click="handleRegenerateKey(item)"
                />
              </div>
              <span v-else class="text-caption text-medium-emphasis">—</span>
            </template>

            <!-- Estado de Suscripción -->
            <template #item.subscription_status="{ item }">
              <VChip
                v-if="!(item.is_orphan ?? item.raw?.is_orphan)"
                size="small"
                variant="tonal"
                :color="item.subscription_status_color ?? item.raw?.subscription_status_color ?? 'success'"
                class="font-weight-bold"
              >
                {{ item.subscription_status_label ?? item.raw?.subscription_status_label ?? 'Activa' }}
              </VChip>
              <VChip v-else size="small" color="error" variant="tonal">
                Sin Vincular
              </VChip>
            </template>

            <!-- Fecha de Vencimiento -->
            <template #item.billing_date="{ item }">
              <div v-if="!(item.is_orphan ?? item.raw?.is_orphan)" class="d-flex flex-column">
                <span class="text-body-2 font-weight-medium">
                  {{ item.billing_date ?? item.raw?.billing_date ?? 'Sin definir' }}
                </span>
                <span
                  v-if="(item.days_remaining ?? item.raw?.days_remaining) !== null && (item.days_remaining ?? item.raw?.days_remaining) !== undefined"
                  class="text-caption font-weight-bold"
                  :class="(item.days_remaining ?? item.raw?.days_remaining) < 0 ? 'text-error' : ((item.days_remaining ?? item.raw?.days_remaining) <= 7 ? 'text-warning' : 'text-success')"
                >
                  {{ (item.days_remaining ?? item.raw?.days_remaining) < 0
                    ? `Vencida hace ${Math.abs(item.days_remaining ?? item.raw?.days_remaining)} día(s)`
                    : `${item.days_remaining ?? item.raw?.days_remaining} día(s) restantes`
                  }}
                </span>
              </div>
              <span v-else class="text-caption text-medium-emphasis">—</span>
            </template>

            <!-- Acciones -->
            <template #item.actions="{ item }">
              <div class="d-flex justify-end align-center ga-1">
                <VBtn
                  v-if="item.is_orphan ?? item.raw?.is_orphan"
                  variant="tonal"
                  color="success"
                  size="small"
                  prepend-icon="tabler-link"
                  @click="openLinkDialog(item)"
                >
                  Vincular
                </VBtn>

                <template v-else>
                  <VBtn
                    icon="tabler-mail-forward"
                    variant="text"
                    color="info"
                    size="small"
                    title="Enviar correo de cobro de mensualidad"
                    @click="openSendBillingEmailDialog(item)"
                  />

                  <VBtn
                    icon="tabler-cash-banknote"
                    variant="text"
                    color="success"
                    size="small"
                    title="Registrar pago y renovar licencia"
                    @click="openPaymentDialog(item)"
                  />

                  <VBtn
                    :icon="(item.subscription_status ?? item.raw?.subscription_status) === 'suspended' ? 'tabler-lock-open' : 'tabler-lock'"
                    variant="text"
                    :color="(item.subscription_status ?? item.raw?.subscription_status) === 'suspended' ? 'success' : 'warning'"
                    size="small"
                    :title="(item.subscription_status ?? item.raw?.subscription_status) === 'suspended' ? 'Reactivar licencia' : 'Suspender licencia'"
                    @click="handleToggleSubscriptionStatus(item)"
                  />

                  <VBtn
                    icon="tabler-credit-card"
                    variant="text"
                    color="primary"
                    size="small"
                    title="Editar plan, límites, contacto y vencimiento"
                    @click="openBillingDialog(item)"
                  />

                  <VBtn
                    icon="tabler-world"
                    variant="text"
                    color="info"
                    size="small"
                    title="Gestionar dominios personalizados"
                    @click="openManageDomainsDialog(item)"
                  />
                </template>

                <VBtn
                  icon="tabler-trash"
                  variant="text"
                  color="error"
                  size="small"
                  title="Eliminar empresa"
                  @click="confirmDeleteTenant(item)"
                />
              </div>
            </template>
          </VDataTable>
        </VWindowItem>

        <!-- PESTAÑA 2: CATÁLOGO DE PLANES SAAS -->
        <VWindowItem value="plans">
          <VCardText class="pa-5">
            <div class="d-flex align-center justify-space-between mb-4">
              <div>
                <h3 class="text-h6 font-weight-bold">
                  Catálogo Comercial de Planes y Licencias
                </h3>
                <p class="text-caption text-medium-emphasis mb-0">
                  Define las tarifas recurrentes, días de gracia y límites operativos de sedes y usuarios por nivel de licencia.
                </p>
              </div>
              <VBtn
                color="primary"
                prepend-icon="tabler-plus"
                @click="openCreatePlanDialog"
              >
                Nuevo Plan SaaS
              </VBtn>
            </div>

            <VRow v-if="isLoadingPlans">
              <VCol v-for="n in 3" :key="n" cols="12" md="4">
                <VSkeletonLoader type="card, actions" />
              </VCol>
            </VRow>

            <VRow v-else>
              <VCol
                v-for="plan in saasPlans"
                :key="plan.id"
                cols="12"
                md="4"
              >
                <VCard variant="outlined" class="pa-5 rounded-lg h-100 d-flex flex-column justify-space-between">
                  <div>
                    <div class="d-flex align-center justify-space-between mb-2">
                      <VChip size="small" color="primary" variant="tonal" class="font-weight-bold text-uppercase">
                        {{ plan.code }}
                      </VChip>
                      <VChip
                        size="x-small"
                        :color="plan.is_active ? 'success' : 'secondary'"
                        variant="tonal"
                      >
                        {{ plan.is_active ? 'Activo' : 'Inactivo' }}
                      </VChip>
                    </div>

                    <div class="text-h6 font-weight-bold text-high-emphasis">
                      {{ plan.name }}
                    </div>

                    <div class="text-h3 font-weight-black text-primary my-3">
                      ${{ Number(plan.price_usd).toFixed(2) }}
                      <span class="text-body-2 text-medium-emphasis font-weight-regular">
                        USD / {{ plan.billing_cycle_label }}
                      </span>
                    </div>

                    <p class="text-body-2 text-medium-emphasis mb-3">
                      {{ plan.description }}
                    </p>

                    <VDivider class="my-3" />

                    <div class="d-flex flex-column ga-1 text-body-2 mb-3">
                      <div>
                        <VIcon icon="tabler-building" size="16" color="primary" class="me-2" />
                        Hasta <strong>{{ plan.max_branches }}</strong> sede(s) permitidas
                      </div>
                      <div>
                        <VIcon icon="tabler-users" size="16" color="primary" class="me-2" />
                        Hasta <strong>{{ plan.max_users }}</strong> usuarios activos
                      </div>
                      <div>
                        <VIcon icon="tabler-clock" size="16" color="primary" class="me-2" />
                        Prueba: <strong>{{ plan.trial_days }}d</strong> · Gracia antes de corte: <strong>{{ plan.grace_days }}d</strong>
                      </div>
                      <div v-if="Number(plan.setup_fee_usd) > 0">
                        <VIcon icon="tabler-tool" size="16" color="primary" class="me-2" />
                        Implementación inicial: <strong>${{ Number(plan.setup_fee_usd).toFixed(2) }} USD</strong>
                      </div>
                    </div>
                  </div>

                  <div class="d-flex justify-end ga-2 pt-3 border-t">
                    <VBtn
                      variant="outlined"
                      color="primary"
                      size="small"
                      prepend-icon="tabler-edit"
                      @click="openEditPlanDialog(plan)"
                    >
                      Editar Plan
                    </VBtn>
                    <VBtn
                      variant="text"
                      color="error"
                      size="small"
                      icon="tabler-trash"
                      @click="handleDeletePlan(plan)"
                    />
                  </div>
                </VCard>
              </VCol>
            </VRow>
          </VCardText>
        </VWindowItem>

        <!-- PESTAÑA 3: COBRANZA, RECIBOS Y RENOVACIONES -->
        <VWindowItem value="payments">
          <VCardText class="pa-4">
            <VRow align="center">
              <VCol cols="12" sm="5">
                <VTextField
                  v-model="paymentSearch"
                  density="compact"
                  placeholder="Buscar por recibo, referencia o empresa..."
                  prepend-inner-icon="tabler-search"
                  variant="outlined"
                  hide-details
                  clearable
                  @keyup.enter="fetchPayments"
                />
              </VCol>
              <VCol cols="12" sm="4">
                <VSelect
                  v-model="paymentStatusFilter"
                  :items="[
                    { title: 'Todos los Pagos', value: '' },
                    { title: 'Pendientes de Aprobación', value: 'pending' },
                    { title: 'Aprobados', value: 'approved' },
                    { title: 'Rechazados', value: 'rejected' },
                  ]"
                  density="compact"
                  variant="outlined"
                  hide-details
                  @update:model-value="fetchPayments"
                />
              </VCol>
              <VCol cols="12" sm="3" class="d-flex justify-end ga-2">
                <VBtn
                  color="primary"
                  prepend-icon="tabler-cash-banknote"
                  @click="openPaymentDialog(null)"
                >
                  Registrar Pago
                </VBtn>
              </VCol>
            </VRow>
          </VCardText>

          <VDivider />

          <VDataTable
            :headers="paymentHeaders"
            :items="payments"
            :loading="isLoadingPayments"
            loading-text="Cargando historial de pagos y renovaciones..."
            no-data-text="No hay pagos de suscripciones registrados"
            class="elevation-0 text-no-wrap"
          >
            <template #item.receipt_number="{ item }">
              <span class="font-weight-bold text-primary">
                {{ item.receipt_number ?? item.raw?.receipt_number }}
              </span>
            </template>

            <template #item.company_name="{ item }">
              <div>
                <div class="font-weight-bold">
                  {{ item.company_name ?? item.raw?.company_name ?? item.tenant_id }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  Reportado por: {{ item.reported_by ?? item.raw?.reported_by ?? 'Admin' }}
                </div>
              </div>
            </template>

            <template #item.plan_code="{ item }">
              <VChip size="x-small" color="primary" variant="tonal" class="text-uppercase font-weight-bold">
                {{ item.plan_code ?? item.raw?.plan_code }}
              </VChip>
            </template>

            <template #item.amount_usd="{ item }">
              <span class="font-weight-black text-success">
                ${{ Number(item.amount_usd ?? item.raw?.amount_usd ?? 0).toFixed(2) }} USD
              </span>
            </template>

            <template #item.payment_method="{ item }">
              <div>
                <div class="text-body-2 font-weight-medium">
                  {{ item.payment_method ?? item.raw?.payment_method }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  Ref: {{ item.reference_number ?? item.raw?.reference_number ?? 'S/R' }}
                </div>
              </div>
            </template>

            <template #item.period="{ item }">
              <span class="text-caption font-weight-medium">
                {{ item.period_starts_at ?? item.raw?.period_starts_at }} → {{ item.period_ends_at ?? item.raw?.period_ends_at }}
              </span>
            </template>

            <template #item.status="{ item }">
              <VChip
                size="small"
                :color="(item.status ?? item.raw?.status) === 'approved' ? 'success' : ((item.status ?? item.raw?.status) === 'rejected' ? 'error' : 'warning')"
                variant="tonal"
                class="font-weight-bold"
              >
                {{ (item.status ?? item.raw?.status) === 'approved' ? 'Aprobado' : ((item.status ?? item.raw?.status) === 'rejected' ? 'Rechazado' : 'Pendiente') }}
              </VChip>
            </template>

            <template #item.actions="{ item }">
              <div v-if="(item.status ?? item.raw?.status) === 'pending'" class="d-flex justify-end ga-1">
                <VBtn
                  size="small"
                  color="success"
                  variant="tonal"
                  prepend-icon="tabler-check"
                  @click="handleApprovePayment(item.raw ?? item)"
                >
                  Aprobar y Renovar
                </VBtn>
                <VBtn
                  size="small"
                  color="error"
                  variant="text"
                  icon="tabler-x"
                  title="Rechazar pago"
                  @click="handleRejectPayment(item.raw ?? item)"
                />
              </div>
              <span v-else class="text-caption text-medium-emphasis">
                {{ (item.paid_at ?? item.raw?.paid_at) ? String(item.paid_at ?? item.raw?.paid_at).slice(0, 10) : '—' }}
              </span>
            </template>
          </VDataTable>
        </VWindowItem>

        <!-- PESTAÑA 4: VERIFICADOR DE LICENCIAS (API) -->
        <VWindowItem value="verifier">
          <VCardText class="pa-6">
            <VRow>
              <VCol cols="12" md="6">
                <h3 class="text-h6 font-weight-bold mb-2">
                  Verificación en Vivo de Llave de Licencia
                </h3>
                <p class="text-body-2 text-medium-emphasis mb-4">
                  Comprueba cualquier llave de licencia emitida o consulta el endpoint público <code>POST /api/public/licenses/verify</code> para clientes en servidores externos.
                </p>

                <div class="d-flex ga-2 mb-4">
                  <VTextField
                    v-model="verifyKeyInput"
                    label="Llave de Licencia (License Key)"
                    placeholder="TOVA-FARM-PRO2026-XXXX-XXXX"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="tabler-key"
                    hide-details
                    @keyup.enter="handleVerifyLicenseKey"
                  />
                  <VBtn
                    color="primary"
                    size="large"
                    :loading="isVerifyingKey"
                    prepend-icon="tabler-shield-check"
                    @click="handleVerifyLicenseKey"
                  >
                    Verificar
                  </VBtn>
                </div>
              </VCol>

              <VCol cols="12" md="6">
                <VCard v-if="verifiedLicenseResult" variant="outlined" class="pa-4 rounded-lg">
                  <div class="d-flex align-center justify-space-between mb-3">
                    <span class="text-subtitle-1 font-weight-bold">
                      Resultado de Validación
                    </span>
                    <VChip
                      :color="verifiedLicenseResult.valid && verifiedLicenseResult.can_operate ? 'success' : 'error'"
                      variant="tonal"
                      size="small"
                      class="font-weight-bold"
                    >
                      {{ verifiedLicenseResult.valid ? verifiedLicenseResult.status_label : 'Licencia Inválida' }}
                    </VChip>
                  </div>

                  <div v-if="verifiedLicenseResult.valid" class="d-flex flex-column ga-2 text-body-2">
                    <div class="d-flex justify-space-between">
                      <span class="text-medium-emphasis">Empresa:</span>
                      <strong>{{ verifiedLicenseResult.company_name }} ({{ verifiedLicenseResult.tenant_id }})</strong>
                    </div>
                    <div class="d-flex justify-space-between">
                      <span class="text-medium-emphasis">Plan Activo:</span>
                      <strong>{{ verifiedLicenseResult.plan_name }}</strong>
                    </div>
                    <div class="d-flex justify-space-between">
                      <span class="text-medium-emphasis">Vencimiento:</span>
                      <strong>{{ verifiedLicenseResult.current_period_ends_at }}</strong>
                    </div>
                    <div class="d-flex justify-space-between">
                      <span class="text-medium-emphasis">Límite Sedes / Usuarios:</span>
                      <strong>{{ verifiedLicenseResult.max_branches }} sede(s) · {{ verifiedLicenseResult.max_users }} usuarios</strong>
                    </div>
                  </div>
                  <div v-else class="text-body-2 text-error">
                    {{ verifiedLicenseResult.message }}
                  </div>
                </VCard>
              </VCol>
            </VRow>
          </VCardText>
        </VWindowItem>
      </VWindow>
    </VCard>

    <!-- MODAL: REGISTRAR PAGO Y RENOVAR LICENCIA -->
    <VDialog v-model="isPaymentDialogOpen" max-width="560" persistent>
      <VCard class="rounded-xl">
        <VCardTitle class="pa-5 d-flex align-center justify-space-between">
          <div class="d-flex align-center ga-2">
            <VIcon icon="tabler-cash-banknote" color="success" />
            <span class="text-h6 font-weight-bold">Registrar Pago y Renovar Licencia</span>
          </div>
          <VBtn icon="tabler-x" variant="text" size="small" @click="isPaymentDialogOpen = false" />
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-5">
          <VRow>
            <VCol cols="12">
              <VSelect
                v-model="paymentForm.tenant_id"
                :items="nonOrphanTenants"
                item-title="company_name"
                item-value="id"
                label="Empresa / Cliente *"
                variant="outlined"
                density="comfortable"
                @update:model-value="onPaymentTenantChange"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VSelect
                v-model="paymentForm.plan_code"
                :items="planOptions"
                item-title="title"
                item-value="value"
                label="Plan a Renovar"
                variant="outlined"
                density="comfortable"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="paymentForm.months_to_add"
                type="number"
                min="1"
                max="60"
                label="Meses / Periodos a Extender"
                variant="outlined"
                density="comfortable"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="paymentForm.amount_usd"
                type="number"
                step="0.01"
                prefix="$"
                label="Monto Cobrado (USD) *"
                variant="outlined"
                density="comfortable"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VSelect
                v-model="paymentForm.payment_method"
                :items="paymentMethodOptions"
                label="Método de Pago"
                variant="outlined"
                density="comfortable"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="paymentForm.reference_number"
                label="Nro. Referencia / Comprobante"
                variant="outlined"
                density="comfortable"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="paymentForm.paid_at"
                type="date"
                label="Fecha de Pago"
                variant="outlined"
                density="comfortable"
              />
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="paymentForm.notes"
                rows="2"
                label="Notas del Recibo"
                variant="outlined"
                density="comfortable"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-4 justify-end ga-2">
          <VBtn variant="text" @click="isPaymentDialogOpen = false">Cancelar</VBtn>
          <VBtn
            color="success"
            variant="flat"
            :loading="isSavingPayment"
            :disabled="isSavingPayment"
            prepend-icon="tabler-check"
            @click="submitRegisterPayment"
          >
            Confirmar Pago y Extender Licencia
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- MODAL: CREAR / EDITAR PLAN SAAS -->
    <VDialog v-model="isPlanDialogOpen" max-width="600" persistent>
      <VCard class="rounded-xl">
        <VCardTitle class="pa-5 d-flex align-center justify-space-between">
          <div class="d-flex align-center ga-2">
            <VIcon icon="tabler-tags" color="primary" />
            <span class="text-h6 font-weight-bold">
              {{ editingPlanId ? 'Editar Plan SaaS' : 'Crear Nuevo Plan SaaS' }}
            </span>
          </div>
          <VBtn icon="tabler-x" variant="text" size="small" @click="isPlanDialogOpen = false" />
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-5">
          <VRow>
            <VCol cols="12" sm="4">
              <VTextField
                v-model="planForm.code"
                label="Código (ej. pro) *"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="8">
              <VTextField
                v-model="planForm.name"
                label="Nombre Comercial del Plan *"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="4">
              <VSelect
                v-model="planForm.billing_cycle"
                :items="billingCycleOptions"
                label="Ciclo de Cobro"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="4">
              <VTextField
                v-model.number="planForm.price_usd"
                type="number"
                step="0.01"
                prefix="$"
                label="Precio (USD) *"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="4">
              <VTextField
                v-model.number="planForm.setup_fee_usd"
                type="number"
                step="0.01"
                prefix="$"
                label="Costo Instalación"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="3">
              <VTextField
                v-model.number="planForm.max_branches"
                type="number"
                min="1"
                label="Máx. Sedes"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="3">
              <VTextField
                v-model.number="planForm.max_users"
                type="number"
                min="1"
                label="Máx. Usuarios"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="3">
              <VTextField
                v-model.number="planForm.trial_days"
                type="number"
                min="0"
                label="Días Prueba"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="3">
              <VTextField
                v-model.number="planForm.grace_days"
                type="number"
                min="0"
                label="Días Gracia"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="planForm.description"
                rows="2"
                label="Descripción del Plan"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="planForm.features_text"
                rows="3"
                label="Beneficios incluidos (uno por línea)"
                variant="outlined"
                density="compact"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-4 justify-end ga-2">
          <VBtn variant="text" @click="isPlanDialogOpen = false">Cancelar</VBtn>
          <VBtn
            color="primary"
            variant="flat"
            :loading="isSavingPlan"
            :disabled="isSavingPlan"
            @click="submitSavePlan"
          >
            Guardar Plan
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Modal de Confirmación de Eliminación -->
    <VDialog
      v-model="isDeleteDialogOpen"
      max-width="500"
      persistent
      transition="dialog-bottom-transition"
    >
      <VCard class="detail-dialog-card overflow-hidden border-0 elevation-12 rounded-xl">
        <VCardTitle class="pa-0">
          <div class="header-gradient pa-4 d-flex align-center shadow-sm">
            <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-2">
              <VIcon icon="tabler-alert-triangle" color="error" size="24" />
            </VAvatar>
            <div class="d-flex flex-column">
              <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
                ¿Eliminar Empresa?
              </h2>
              <span class="text-super-xs text-white opacity-90 uppercase font-weight-bold letter-spacing-1">
                Destrucción permanente de instancia y base de datos
              </span>
            </div>
            <VSpacer />
            <VBtn
              icon="tabler-x"
              variant="tonal"
              color="white"
              size="small"
              class="rounded-lg"
              :disabled="isDeleting"
              @click="isDeleteDialogOpen = false"
            />
          </div>
        </VCardTitle>

        <VDivider />

        <VCardText class="pa-4 pa-sm-6 bg-light">
          <VCard variant="flat" class="border pa-4 bg-white rounded-lg elevation-1">
            <p class="text-body-1 text-high-emphasis mb-2">
              ¿Estás seguro de que deseas eliminar permanentemente a
              <strong class="text-error">{{ tenantToDelete?.company_name ?? tenantToDelete?.raw?.company_name ?? tenantToDelete?.id ?? tenantToDelete?.raw?.id }}</strong>?
            </p>
            <p v-if="tenantToDelete?.database ?? tenantToDelete?.raw?.database" class="text-caption font-weight-bold text-error mb-2">
              Base de datos que se destruirá en MySQL: {{ tenantToDelete?.database ?? tenantToDelete?.raw?.database }}
            </p>
            <p class="text-caption text-medium-emphasis mb-0">
              Esta acción eliminará el subdominio, revocará su licencia y destruirá su base de datos física en MySQL.
            </p>
          </VCard>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4 bg-light border-t">
          <VRow dense class="w-100 ma-0">
            <VCol cols="12" sm="6" class="pa-1">
              <VBtn
                color="secondary"
                variant="outlined"
                size="large"
                block
                height="48"
                class="font-weight-black rounded-lg text-button uppercase"
                :disabled="isDeleting"
                @click="isDeleteDialogOpen = false"
              >
                Cancelar
              </VBtn>
            </VCol>
            <VCol cols="12" sm="6" class="pa-1">
              <VBtn
                color="error"
                variant="flat"
                size="large"
                block
                height="48"
                class="font-weight-black rounded-lg text-button uppercase"
                :loading="isDeleting"
                :disabled="isDeleting"
                @click="handleDeleteTenant"
              >
                <VIcon icon="tabler-trash" size="18" class="me-2" />
                {{ isDeleting ? 'Eliminando...' : 'Eliminar Empresa' }}
              </VBtn>
            </VCol>
          </VRow>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Modal de Vinculación de Base de Datos Huérfana -->
    <VDialog
      v-model="isLinkDialogOpen"
      max-width="560"
      persistent
      transition="dialog-bottom-transition"
    >
      <VCard class="detail-dialog-card overflow-hidden border-0 elevation-12 rounded-xl">
        <VCardTitle class="pa-0">
          <div class="header-gradient pa-4 d-flex align-center shadow-sm">
            <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-2">
              <VIcon icon="tabler-link" color="primary" size="24" />
            </VAvatar>
            <div class="d-flex flex-column">
              <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
                Vincular Empresa a Base de Datos
              </h2>
              <span class="text-caption text-white opacity-80">
                Activa la base de datos existente como un tenant oficial del SaaS
              </span>
            </div>
            <VSpacer />
            <VBtn
              icon="tabler-x"
              variant="text"
              color="white"
              size="small"
              class="rounded-lg"
              :disabled="isLinking"
              @click="isLinkDialogOpen = false"
            />
          </div>
        </VCardTitle>

        <VDivider />

        <VCardText class="pa-4 pa-sm-6">
          <VRow dense>
            <VCol cols="12" class="mb-2">
              <VTextField
                v-model="linkForm.company_name"
                label="Nombre Comercial de la Empresa"
                placeholder="Ej. Farmacia Zorca Salud"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="tabler-building"
              />
            </VCol>

            <VCol cols="12" sm="6" class="mb-2">
              <VTextField
                v-model="linkForm.tenant_id"
                label="Identificador (ID)"
                placeholder="Ej. zorca"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="tabler-id"
              />
            </VCol>

            <VCol cols="12" sm="6" class="mb-2">
              <VTextField
                v-model="linkForm.database"
                label="Base de Datos Física (MySQL)"
                readonly
                disabled
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="tabler-database"
              />
            </VCol>

            <VCol cols="12" class="mb-2">
              <VTextField
                v-model="linkForm.domain"
                label="Dominio o Subdominio de Acceso"
                placeholder="zorca.tovaerp.com"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="tabler-world"
              />
            </VCol>
          </VRow>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4 bg-light border-t">
          <VRow no-gutters>
            <VCol cols="12" sm="6" class="pa-1">
              <VBtn
                variant="outlined"
                color="secondary"
                size="large"
                block
                height="48"
                class="font-weight-black rounded-lg text-button uppercase"
                :disabled="isLinking"
                @click="isLinkDialogOpen = false"
              >
                Cancelar
              </VBtn>
            </VCol>
            <VCol cols="12" sm="6" class="pa-1">
              <VBtn
                color="primary"
                variant="flat"
                size="large"
                block
                height="48"
                class="font-weight-black rounded-lg text-button uppercase"
                :loading="isLinking"
                :disabled="isLinking || !linkForm.tenant_id"
                @click="handleLinkTenant"
              >
                Vincular y Activar
              </VBtn>
            </VCol>
          </VRow>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Modal de Creación / Aprovisionamiento de Empresa -->
    <VDialog
      v-model="isDialogOpen"
      max-width="720"
      persistent
      scrollable
      :fullscreen="$vuetify.display.xs"
      transition="dialog-bottom-transition"
    >
      <VCard class="detail-dialog-card overflow-hidden border-0 elevation-12 rounded-xl">
        <VCardTitle class="pa-0">
          <div class="header-gradient pa-4 d-flex align-center shadow-sm">
            <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-2">
              <VIcon icon="tabler-building-store" color="primary" size="24" />
            </VAvatar>
            <div class="d-flex flex-column">
              <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
                Aprovisionar Nueva Empresa y Emitir Licencia
              </h2>
              <span class="text-super-xs text-white opacity-90 uppercase font-weight-bold letter-spacing-1">
                Creación de instancia aislada, plan comercial y llave de licencia
              </span>
            </div>
            <VSpacer />
            <VBtn
              icon="tabler-x"
              variant="tonal"
              color="white"
              size="small"
              class="rounded-lg"
              :disabled="isSubmitting"
              @click="closeCreateDialog"
            />
          </div>
        </VCardTitle>

        <VDivider />

        <VCardText class="pa-4 pa-sm-6 bg-light overflow-y-auto" style="max-height: 75vh;">
          <VForm @submit.prevent="handleCreateTenant">
            <div class="d-flex flex-column ga-4">
              <!-- Bloque 1: Vertical y Plan Comercial -->
              <VCard variant="flat" class="border pa-4 bg-white rounded-lg elevation-1">
                <div class="d-flex align-center ga-2 mb-3">
                  <div class="header-indicator primary" />
                  <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">
                    Vertical y Plan de Suscripción
                  </span>
                </div>

                <VRow dense>
                  <VCol cols="12" class="mb-3">
                    <VSelect
                      v-model="form.vertical"
                      :items="verticalOptions"
                      item-title="title"
                      item-value="value"
                      label="Vertical y Tipo de Negocio *"
                      variant="outlined"
                      density="comfortable"
                      :error-messages="errors.vertical"
                      :disabled="isSubmitting"
                      hide-details="auto"
                    />
                  </VCol>

                  <VCol cols="12" sm="4" class="mb-2">
                    <VSelect
                      v-model="form.plan"
                      :items="planOptions"
                      item-title="title"
                      item-value="value"
                      label="Plan Contratado *"
                      variant="outlined"
                      density="comfortable"
                      hide-details="auto"
                      @update:model-value="onCreatePlanChange"
                    />
                  </VCol>

                  <VCol cols="12" sm="4" class="mb-2">
                    <VTextField
                      v-model.number="form.monthly_price"
                      type="number"
                      step="0.01"
                      prefix="$"
                      label="Tarifa Acordada (USD)"
                      variant="outlined"
                      density="comfortable"
                      hide-details="auto"
                    />
                  </VCol>

                  <VCol cols="12" sm="4" class="mb-2">
                    <VTextField
                      v-model="form.billing_date"
                      type="date"
                      label="Próximo Corte"
                      variant="outlined"
                      density="comfortable"
                      hide-details="auto"
                    />
                  </VCol>
                </VRow>
              </VCard>

              <!-- Bloque 2: Identificación de la Empresa y Subdominio -->
              <VCard variant="flat" class="border pa-4 bg-white rounded-lg elevation-1">
                <div class="d-flex align-center ga-2 mb-4">
                  <div class="header-indicator primary" />
                  <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">
                    Datos de la Empresa y Dominio
                  </span>
                </div>

                <VRow dense>
                  <VCol cols="12" sm="8" class="mb-3">
                    <VTextField
                      v-model="form.company_name"
                      label="Nombre de la Empresa / Razón Social *"
                      placeholder="Ej: Farmacia Central C.A."
                      prepend-inner-icon="tabler-building"
                      variant="outlined"
                      density="comfortable"
                      :error-messages="errors.company_name"
                      :disabled="isSubmitting"
                      hide-details="auto"
                      @input="handleCompanyNameInput"
                    />
                  </VCol>

                  <VCol cols="12" sm="4" class="mb-3">
                    <VTextField
                      v-model="form.seller_name"
                      label="Vendedor / Asesor"
                      placeholder="Ej: Carlos Pérez"
                      prepend-inner-icon="tabler-user-check"
                      variant="outlined"
                      density="comfortable"
                      hide-details="auto"
                    />
                  </VCol>

                  <VCol cols="12">
                    <VTextField
                      v-model="form.tenant_id"
                      label="Identificador de Subdominio *"
                      placeholder="farmacia-central"
                      suffix=".tovaerp.com"
                      prepend-inner-icon="tabler-world"
                      variant="outlined"
                      density="comfortable"
                      :error-messages="errors.tenant_id"
                      :disabled="isSubmitting"
                      hint="Solo letras minúsculas, números y guiones. Será la URL de acceso del negocio."
                      persistent-hint
                    />
                  </VCol>
                </VRow>
              </VCard>

              <!-- Bloque 3: Credenciales del Administrador -->
              <VCard variant="flat" class="border pa-4 bg-white rounded-lg elevation-1">
                <div class="d-flex align-center ga-2 mb-4">
                  <div class="header-indicator secondary" />
                  <span class="text-xs font-weight-black text-secondary uppercase letter-spacing-1">
                    Credenciales de Acceso Inicial
                  </span>
                </div>

                <VRow dense>
                  <VCol cols="12" sm="6" class="mb-3">
                    <VTextField
                      v-model="form.admin_name"
                      label="Nombre del Administrador"
                      placeholder="Ej: Administrador"
                      prepend-inner-icon="tabler-user-shield"
                      variant="outlined"
                      density="comfortable"
                      :error-messages="errors.admin_name"
                      :disabled="isSubmitting"
                      hide-details="auto"
                    />
                  </VCol>

                  <VCol cols="12" sm="6" class="mb-3">
                    <VTextField
                      v-model="form.admin_email"
                      label="Correo Electrónico de Acceso *"
                      placeholder="admin@empresa.com"
                      type="email"
                      prepend-inner-icon="tabler-mail"
                      variant="outlined"
                      density="comfortable"
                      :error-messages="errors.admin_email"
                      :disabled="isSubmitting"
                      hide-details="auto"
                    />
                  </VCol>

                  <VCol cols="12">
                    <VTextField
                      v-model="form.password"
                      label="Contraseña de Acceso *"
                      placeholder="••••••••"
                      :type="showPassword ? 'text' : 'password'"
                      prepend-inner-icon="tabler-lock"
                      :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
                      variant="outlined"
                      density="comfortable"
                      :error-messages="errors.password"
                      :disabled="isSubmitting"
                      hint="Mínimo 6 caracteres."
                      persistent-hint
                      @click:append-inner="showPassword = !showPassword"
                    />
                  </VCol>
                </VRow>
              </VCard>
            </div>
          </VForm>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4 bg-light border-t">
          <VRow dense class="w-100 ma-0">
            <VCol cols="12" sm="6" class="pa-1">
              <VBtn
                color="secondary"
                variant="outlined"
                size="large"
                block
                height="48"
                class="font-weight-black rounded-lg text-button uppercase"
                :disabled="isSubmitting"
                @click="closeCreateDialog"
              >
                Cancelar
              </VBtn>
            </VCol>
            <VCol cols="12" sm="6" class="pa-1">
              <VBtn
                color="primary"
                variant="flat"
                size="large"
                block
                height="48"
                class="font-weight-black rounded-lg shadow-primary text-button uppercase"
                :loading="isSubmitting"
                :disabled="isSubmitting"
                @click="handleCreateTenant"
              >
                <VIcon icon="tabler-device-floppy" size="18" class="me-2" />
                {{ isSubmitting ? 'Aprovisionando...' : 'Crear y Emitir Licencia' }}
              </VBtn>
            </VCol>
          </VRow>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Modal de Gestión de Dominios Personalizados -->
    <VDialog
      v-model="isManageDomainsDialogOpen"
      max-width="560"
      transition="dialog-bottom-transition"
    >
      <VCard class="detail-dialog-card overflow-hidden border-0 elevation-12 rounded-xl">
        <VCardTitle class="pa-0">
          <div class="header-gradient pa-4 d-flex align-center shadow-sm">
            <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-2">
              <VIcon icon="tabler-world" color="primary" size="24" />
            </VAvatar>
            <div class="d-flex flex-column">
              <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
                Gestionar Dominios
              </h2>
              <span class="text-caption text-white opacity-80">
                {{ tenantForDomains?.company_name ?? tenantForDomains?.raw?.company_name ?? tenantForDomains?.id }}
              </span>
            </div>
            <VSpacer />
            <VBtn
              icon="tabler-x"
              variant="text"
              color="white"
              size="small"
              class="rounded-lg"
              @click="isManageDomainsDialogOpen = false"
            />
          </div>
        </VCardTitle>

        <VDivider />

        <VCardText class="pa-4 pa-sm-6">
          <div class="mb-4">
            <h4 class="text-subtitle-2 font-weight-bold text-high-emphasis mb-2">
              Dominios y subdominios activos:
            </h4>
            <div v-if="(tenantForDomains?.domains ?? tenantForDomains?.raw?.domains)?.length > 0" class="d-flex flex-column ga-2">
              <div
                v-for="d in (tenantForDomains?.domains ?? tenantForDomains?.raw?.domains)"
                :key="d.id || d.domain"
                class="d-flex align-center justify-space-between pa-3 border rounded-lg bg-light"
              >
                <div class="d-flex align-center ga-2">
                  <VIcon icon="tabler-world" size="18" color="primary" />
                  <a
                    :href="'https://' + d.domain"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-decoration-none text-primary font-weight-bold text-body-2"
                  >
                    {{ d.domain }}
                  </a>
                </div>
                <VBtn
                  v-if="(tenantForDomains?.domains ?? tenantForDomains?.raw?.domains)?.length > 1"
                  icon="tabler-trash"
                  size="x-small"
                  variant="text"
                  color="error"
                  :disabled="isDeletingDomain"
                  @click="handleRemoveDomain(d)"
                />
              </div>
            </div>
            <div v-else class="text-caption text-medium-emphasis">
              Responde por defecto a {{ tenantForDomains?.id }}.tovaerp.com.
            </div>
          </div>

          <VDivider class="my-4" />

          <div>
            <h4 class="text-subtitle-2 font-weight-bold text-high-emphasis mb-2">
              Agregar nuevo dominio personalizado:
            </h4>
            <VTextField
              v-model="newDomainInput"
              label="Nuevo Dominio o Subdominio"
              placeholder="mifarmacia.com"
              variant="outlined"
              density="comfortable"
              prepend-inner-icon="tabler-plus"
              :error-messages="domainError"
              @keyup.enter="handleAddDomain"
            />
          </div>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4 bg-light border-t">
          <VSpacer />
          <VBtn
            variant="outlined"
            color="secondary"
            class="rounded-lg font-weight-bold"
            @click="isManageDomainsDialogOpen = false"
          >
            Cerrar
          </VBtn>
          <VBtn
            color="primary"
            variant="flat"
            class="rounded-lg font-weight-bold"
            :loading="isAddingDomain"
            :disabled="isAddingDomain || !newDomainInput.trim()"
            @click="handleAddDomain"
          >
            <VIcon icon="tabler-plus" size="16" class="me-1" />
            Agregar Dominio
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog: Registrar Licencia / Tenant Externo -->
    <VDialog v-model="isExternalDialogOpen" max-width="640" persistent>
      <VCard class="rounded-xl">
        <VCardTitle class="pa-5 d-flex align-center justify-space-between">
          <div class="d-flex align-center ga-2">
            <VIcon icon="tabler-world-plus" color="primary" />
            <span class="text-h6 font-weight-bold">Registrar Licencia Externa (Otro Servidor / Local)</span>
          </div>
          <VBtn icon="tabler-x" variant="text" size="small" @click="isExternalDialogOpen = false" />
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-5">
          <VRow>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="externalForm.company_name"
                label="Empresa / Razón Social *"
                variant="outlined"
                density="compact"
                :error-messages="externalErrors.company_name"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="externalForm.tenant_id"
                label="Identificador (ID único) *"
                placeholder="ej. farmacia_norte"
                variant="outlined"
                density="compact"
                :error-messages="externalErrors.tenant_id"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="externalForm.domain"
                label="Dominio o IP *"
                placeholder="ej. app.otra-farmacia.com"
                variant="outlined"
                density="compact"
                :error-messages="externalErrors.domain"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VSelect
                v-model="externalForm.vertical"
                :items="verticalOptions"
                item-title="title"
                item-value="value"
                label="Tipo de Negocio"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="4">
              <VSelect
                v-model="externalForm.plan"
                :items="planOptions"
                item-title="title"
                item-value="value"
                label="Plan *"
                variant="outlined"
                density="compact"
                :error-messages="externalErrors.plan"
                @update:model-value="onExternalPlanChange"
              />
            </VCol>
            <VCol cols="12" sm="4">
              <VTextField
                v-model.number="externalForm.monthly_price"
                label="Tarifa Acordada (USD) *"
                type="number"
                min="0"
                step="0.01"
                prefix="$"
                variant="outlined"
                density="compact"
                :error-messages="externalErrors.monthly_price"
              />
            </VCol>
            <VCol cols="12" sm="4">
              <VTextField
                v-model="externalForm.billing_date"
                label="Fecha de Vencimiento *"
                type="date"
                variant="outlined"
                density="compact"
                :error-messages="externalErrors.billing_date"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="externalForm.contact_name"
                label="Persona de Contacto"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="externalForm.seller_name"
                label="Vendedor Asignado"
                variant="outlined"
                density="compact"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-4 justify-end ga-2">
          <VBtn variant="text" @click="isExternalDialogOpen = false">Cancelar</VBtn>
          <VBtn
            color="primary"
            variant="flat"
            :loading="isSubmittingExternal"
            :disabled="isSubmittingExternal"
            @click="submitExternalTenant"
          >
            Guardar y Emitir Licencia
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog: Editar Plan, Límites, Contacto y Fecha de Cobro -->
    <VDialog v-model="isBillingDialogOpen" max-width="640" persistent>
      <VCard class="rounded-xl">
        <VCardTitle class="pa-5 d-flex align-center justify-space-between">
          <div class="d-flex align-center ga-2">
            <VIcon icon="tabler-credit-card" color="primary" />
            <span class="text-h6 font-weight-bold">Licencia y Cobro: {{ billingForm.company_name }}</span>
          </div>
          <VBtn icon="tabler-x" variant="text" size="small" @click="isBillingDialogOpen = false" />
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-5">
          <VRow>
            <VCol cols="12" sm="6">
              <VSelect
                v-model="billingForm.plan"
                :items="planOptions"
                item-title="title"
                item-value="value"
                label="Plan"
                variant="outlined"
                density="compact"
                @update:model-value="onBillingPlanChange"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VSelect
                v-model="billingForm.status"
                :items="subscriptionStatusOptions"
                label="Estado de la Licencia"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="4">
              <VSelect
                v-model="billingForm.billing_cycle"
                :items="billingCycleOptions"
                label="Ciclo de Cobro"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="4">
              <VTextField
                v-model.number="billingForm.monthly_price"
                label="Tarifa Acordada (USD)"
                type="number"
                min="0"
                step="0.01"
                prefix="$"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="4">
              <VTextField
                v-model="billingForm.billing_date"
                label="Fecha de Vencimiento"
                type="date"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="4">
              <VTextField
                v-model.number="billingForm.max_branches"
                label="Máx. Sedes"
                type="number"
                min="1"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="4">
              <VTextField
                v-model.number="billingForm.max_users"
                label="Máx. Usuarios"
                type="number"
                min="1"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="4">
              <VTextField
                v-model="billingForm.created_at"
                label="Fecha de Ingreso"
                type="date"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="4">
              <VTextField
                v-model="billingForm.contact_name"
                label="Nombre Contacto"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="4">
              <VTextField
                v-model="billingForm.contact_email"
                label="Correo de Cobro"
                type="email"
                placeholder="cliente@empresa.com"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="4">
              <VTextField
                v-model="billingForm.contact_phone"
                label="Teléfono / WhatsApp"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="4">
              <VTextField
                v-model="billingForm.seller_name"
                label="Vendedor Asignado"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12">
              <VSwitch
                v-model="billingForm.can_create_branches"
                label="Autorizar creación de sucursales (Multisede)"
                color="primary"
                density="compact"
                hide-details
              />
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="billingForm.notes"
                rows="2"
                label="Notas comerciales / Acuerdos"
                variant="outlined"
                density="compact"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-4 justify-end ga-2">
          <VBtn variant="text" @click="isBillingDialogOpen = false">Cancelar</VBtn>
          <VBtn
            color="primary"
            variant="flat"
            :loading="isSubmittingBilling"
            :disabled="isSubmittingBilling"
            @click="submitBillingUpdate"
          >
            Guardar Cambios
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog: Enviar Correo de Cobro de Mensualidad -->
    <VDialog v-model="isSendBillingEmailDialogOpen" max-width="500" persistent>
      <VCard class="rounded-xl">
        <VCardTitle class="pa-5 d-flex align-center justify-space-between">
          <div class="d-flex align-center ga-2">
            <VIcon icon="tabler-mail-forward" color="info" />
            <span class="text-h6 font-weight-bold">Enviar Correo de Cobro</span>
          </div>
          <VBtn icon="tabler-x" variant="text" size="small" @click="isSendBillingEmailDialogOpen = false" />
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-5">
          <VAlert type="info" variant="tonal" density="compact" class="mb-4">
            <strong>{{ sendBillingEmailForm.company_name }}</strong> · Plan: <strong class="text-uppercase">{{ sendBillingEmailForm.plan }}</strong> ·
            <strong>${{ Number(sendBillingEmailForm.usd_amount).toFixed(2) }} USD</strong> · Vence: <strong>{{ sendBillingEmailForm.billing_date || 'Sin definir' }}</strong>
          </VAlert>

          <VRow>
            <VCol cols="12">
              <VTextField
                v-model="sendBillingEmailForm.email"
                label="Correo Electrónico de Destino *"
                type="email"
                placeholder="cliente@empresa.com"
                prepend-inner-icon="tabler-mail"
                variant="outlined"
                density="comfortable"
                hint="Si lo modificas, quedará guardado como correo de cobro del tenant."
                persistent-hint
              />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model.number="sendBillingEmailForm.rate"
                label="Tasa de Cambio (COP)"
                type="number"
                min="1"
                prepend-inner-icon="tabler-currency-dollar"
                variant="outlined"
                density="comfortable"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-4 justify-end ga-2">
          <VBtn variant="text" @click="isSendBillingEmailDialogOpen = false">Cancelar</VBtn>
          <VBtn
            color="info"
            variant="flat"
            prepend-icon="tabler-send"
            :loading="isSendingBillingEmail"
            :disabled="isSendingBillingEmail"
            @click="submitSendBillingEmail"
          >
            Enviar Correo
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient) !important;
}

.detail-dialog-card {
  border-radius: 14px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.header-indicator.secondary {
  background-color: rgb(var(--v-theme-secondary));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.35) !important;
}

.bg-light-surface {
  background-color: rgba(var(--v-theme-on-surface), 0.03) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 0.06em !important;
}

.leading-tight {
  line-height: 1.25 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.1) !important;
}

.text-button {
  font-size: 0.85rem !important;
  letter-spacing: 0.05em !important;
}
</style>
