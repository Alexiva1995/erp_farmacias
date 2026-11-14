<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from '@axios'
import { toast } from '@/plugins/sweetalert'
import { useBrandingStore } from '@/stores/useBrandingStore'

const brandingStore = useBrandingStore()
const isTenantInstance = computed(() => Boolean(brandingStore.settings.is_tenant))

// Estados reactivos
const isLoading = ref(false)
const isSubmitting = ref(false)
const isDialogOpen = ref(false)
const searchQuery = ref('')
const tenants = ref([])
const showPassword = ref(false)

// Notificación / Toast feedback
const alert = reactive({
  show: false,
  type: 'success',
  message: '',
})

// Formulario de nueva empresa
const form = reactive({
  company_name: '',
  tenant_id: '',
  admin_name: 'Administrador',
  admin_email: '',
  password: '',
  vertical: 'pharmacy',
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

// Catálogo base de verticales del sistema (incluye Droguería B2B)
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
    // Mantiene el catálogo base con las 5 verticales principales
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

// Auto-generar identificador slug a partir del nombre
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

// Encabezados de la tabla
const headers = [
  { title: 'Empresa / Razón Social', key: 'company_name', sortable: true },
  { title: 'Tipo de Negocio', key: 'vertical', sortable: true },
  { title: 'Plan / Costo', key: 'plan', sortable: true },
  { title: 'Fecha de Vencimiento', key: 'billing_date', sortable: true },
  { title: 'Fecha de Ingreso', key: 'created_at', sortable: true },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'end' },
]

// Opciones de planes y lógica de Tenant Externo / Cobros
const planOptions = [
  { title: 'Plan Básico ($29 USD)', value: 'basic', defaultPrice: 29 },
  { title: 'Plan PRO ($49 USD)', value: 'pro', defaultPrice: 49 },
]

const isExternalDialogOpen = ref(false)
const isSubmittingExternal = ref(false)
const externalForm = reactive({
  company_name: '',
  tenant_id: '',
  domain: '',
  vertical: 'pharmacy',
  plan: 'basic',
  monthly_price: 29,
  billing_date: '',
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
  externalForm.monthly_price = 29
  externalForm.billing_date = new Date().toISOString().slice(0, 10)
  Object.keys(externalErrors).forEach(k => { externalErrors[k] = '' })
  isExternalDialogOpen.value = true
}

const onExternalPlanChange = (planValue) => {
  const found = planOptions.find(p => p.value === planValue)
  if (found) {
    externalForm.monthly_price = found.defaultPrice
  }
}

const submitExternalTenant = async () => {
  isSubmittingExternal.value = true
  Object.keys(externalErrors).forEach(k => { externalErrors[k] = '' })
  try {
    await axios.post('/central/tenants/external', externalForm)
    toast.success('Tenant externo registrado correctamente')
    isExternalDialogOpen.value = false
    await fetchTenants()
  } catch (error) {
    if (error.response?.status === 422 && error.response?.data?.errors) {
      const errs = error.response.data.errors
      Object.keys(errs).forEach((k) => {
        if (k in externalErrors) {
          externalErrors[k] = Array.isArray(errs[k]) ? errs[k][0] : errs[k]
        }
      })
    }
    toast.error(error.response?.data?.message || 'Error al registrar tenant externo')
  } finally {
    isSubmittingExternal.value = false
  }
}

const isBillingDialogOpen = ref(false)
const isSubmittingBilling = ref(false)
const billingForm = reactive({
  id: '',
  company_name: '',
  plan: 'basic',
  monthly_price: 29,
  billing_date: '',
  created_at: '',
})

const openBillingDialog = (item) => {
  const raw = item?.raw ?? item
  billingForm.id = raw.id
  billingForm.company_name = raw.company_name || raw.id
  billingForm.plan = raw.plan || 'basic'
  billingForm.monthly_price = raw.monthly_price ?? (billingForm.plan === 'pro' ? 49 : 29)
  billingForm.billing_date = raw.billing_date || ''
  billingForm.created_at = raw.created_at ? String(raw.created_at).slice(0, 10) : ''
  isBillingDialogOpen.value = true
}

const onBillingPlanChange = (planValue) => {
  const found = planOptions.find(p => p.value === planValue)
  if (found) {
    billingForm.monthly_price = found.defaultPrice
  }
}

const submitBillingUpdate = async () => {
  isSubmittingBilling.value = true
  try {
    await axios.patch(`/central/tenants/${billingForm.id}/billing`, {
      plan: billingForm.plan,
      monthly_price: billingForm.monthly_price,
      billing_date: billingForm.billing_date || null,
      created_at: billingForm.created_at || null,
    })
    toast.success('Plan y cobro actualizados')
    isBillingDialogOpen.value = false
    await fetchTenants()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error al actualizar plan y cobro')
  } finally {
    isSubmittingBilling.value = false
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

// Limpiar formulario y errores
const resetForm = () => {
  form.company_name = ''
  form.tenant_id = ''
  form.admin_name = 'Administrador'
  form.admin_email = ''
  form.password = ''
  form.vertical = 'pharmacy'
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

// Mostrar alerta / notificación
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

// Crear empresa y aprovisionar
const handleCreateTenant = async () => {
  // Limpiar errores previos
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
    })

    showAlert('success', response.data.message || 'Empresa creada y aprovisionada exitosamente')
    closeCreateDialog()
    await fetchTenants()
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
    await fetchTenants()
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
      params: { database }
    })
    showAlert('success', response.data.message || 'Empresa y base de datos eliminadas exitosamente')
    isDeleteDialogOpen.value = false
    tenantToDelete.value = null
    await fetchTenants()
  } catch (error) {
    showAlert('error', error.response?.data?.message || 'Error al eliminar la empresa')
  } finally {
    isDeleting.value = false
  }
}

onMounted(() => {
  fetchVerticalPresets()
  if (!isTenantInstance.value) {
    fetchTenants()
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
      La gestión y aprovisionamiento de empresas SaaS solo está habilitada desde el dominio maestro central del sistema.
    </VAlert>
  </div>

  <div v-else>
    <!-- Encabezado de la Sección -->
    <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between mb-6 ga-3">
      <div>
        <h1 class="text-h5 font-weight-bold text-high-emphasis mb-1">
          Gestión de Empresas (Tenants)
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Aprovisiona nuevas instancias de empresas y negocios independientes con catálogos y formatos maestros preconfigurados.
        </p>
      </div>

      <div class="d-flex align-center ga-2">
        <VBtn
          color="info"
          variant="outlined"
          prepend-icon="tabler-chart-histogram"
          to="/configuration/suppliers-tenant-audit"
        >
          Auditoría de Droguerías
        </VBtn>

        <VBtn
          color="primary"
          variant="outlined"
          prepend-icon="tabler-world-plus"
          class="font-weight-bold rounded-lg"
          @click="openExternalDialog"
        >
          Tenant Externo
        </VBtn>

        <VBtn
          color="primary"
          variant="flat"
          prepend-icon="tabler-plus"
          class="font-weight-black rounded-lg shadow-primary text-button uppercase"
          @click="openCreateDialog"
        >
          Nueva Empresa
        </VBtn>
      </div>
    </div>

    <!-- Alertas globales -->
    <VAlert
      v-if="alert.show"
      :type="alert.type"
      variant="tonal"
      closable
      class="mb-6"
      @click:close="alert.show = false"
    >
      {{ alert.message }}
    </VAlert>

    <!-- Tabla Principal de Empresas -->
    <VCard class="rounded-lg border shadow-sm overflow-hidden">
      <VCardText class="pa-4">
        <VRow align="center" justify="space-between">
          <VCol cols="12" sm="6" md="4">
            <VTextField
              v-model="searchQuery"
              density="compact"
              placeholder="Buscar por empresa o subdominio..."
              prepend-inner-icon="tabler-search"
              variant="outlined"
              hide-details
              clearable
            />
          </VCol>
          <VCol cols="auto" class="d-flex align-center ga-2">
            <VChip size="small" color="primary" variant="tonal" class="font-weight-black">
              {{ tenants.length }} EMPRESAS
            </VChip>
            <VBtn
              variant="tonal"
              color="primary"
              size="small"
              icon="tabler-refresh"
              class="rounded-lg"
              :loading="isLoading"
              @click="fetchTenants"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VDataTable
        :headers="headers"
        :items="tenants"
        :search="searchQuery"
        :loading="isLoading"
        loading-text="Cargando empresas registradas..."
        no-data-text="No hay empresas registradas actualmente"
        hover
        class="elevation-0 text-no-wrap"
      >
        <!-- Columna Empresa -->
        <template #item.company_name="{ item }">
          <div class="py-2">
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
          </div>
        </template>

        <!-- Columna Tipo de Negocio / Vertical -->
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

        <!-- Columna Plan / Costo -->
        <template #item.plan="{ item }">
          <div class="d-flex align-center ga-2">
            <VChip
              size="small"
              variant="tonal"
              :color="(item.plan ?? item.raw?.plan) === 'pro' ? 'primary' : 'info'"
              class="font-weight-bold"
            >
              {{ (item.plan ?? item.raw?.plan) === 'pro' ? 'PRO' : 'Básico' }}
            </VChip>
            <span class="font-weight-bold text-high-emphasis">
              ${{ Number(item.monthly_price ?? item.raw?.monthly_price ?? 29).toFixed(2) }} USD
            </span>
          </div>
        </template>

        <!-- Columna Fecha de Cobro -->
        <template #item.billing_date="{ item }">
          <span class="text-body-2">
            {{ item.billing_date ?? item.raw?.billing_date ?? 'Sin definir' }}
          </span>
        </template>

        <!-- Columna Identificador -->
        <template #item.id="{ item }">
          <VChip size="small" variant="outlined" color="primary" class="font-weight-bold">
            {{ item.id ?? item.raw?.id }}
          </VChip>
        </template>

        <!-- Columna Dominios -->
        <template #item.domains="{ item }">
          <div class="d-flex flex-column ga-1 my-1">
            <div v-if="(item.domains ?? item.raw?.domains)?.length > 0">
              <div v-for="d in (item.domains ?? item.raw?.domains)" :key="d.id || d.domain" class="d-flex align-center ga-1 my-1">
                <a
                  :href="'https://' + d.domain"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-decoration-none text-primary text-body-2 font-weight-bold d-inline-flex align-center ga-1"
                >
                  <span>{{ d.domain }}</span>
                  <VIcon icon="tabler-external-link" size="14" />
                </a>
              </div>
            </div>
            <a
              v-else-if="getTenantDomain(item)"
              :href="getTenantUrl(item)"
              target="_blank"
              rel="noopener noreferrer"
              class="text-decoration-none text-primary text-body-2 font-weight-bold d-inline-flex align-center ga-1"
            >
              <span>{{ getTenantDomain(item) }}</span>
              <VIcon icon="tabler-external-link" size="14" />
            </a>
            <span v-else class="text-caption text-medium-emphasis">—</span>

            <div v-if="!(item.is_orphan ?? item.raw?.is_orphan)">
              <VBtn
                variant="text"
                color="primary"
                size="x-small"
                class="px-1 text-caption font-weight-bold"
                prepend-icon="tabler-plus"
                @click="openManageDomainsDialog(item)"
              >
                + Dominio
              </VBtn>
            </div>
          </div>
        </template>

        <!-- Columna Fecha -->
        <template #item.created_at="{ item }">
          <span class="text-body-2 text-medium-emphasis">
            {{ (item.created_at ?? item.raw?.created_at) ? new Date(item.created_at ?? item.raw?.created_at).toLocaleDateString('es-ES') : '—' }}
          </span>
        </template>

        <!-- Columna Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex justify-end align-center ga-1">
            <VBtn
              v-if="item.is_orphan ?? item.raw?.is_orphan"
              variant="tonal"
              color="success"
              size="small"
              class="rounded-lg font-weight-bold"
              prepend-icon="tabler-link"
              @click="openLinkDialog(item)"
            >
              Vincular
            </VBtn>

            <VBtn
              v-if="!(item.is_orphan ?? item.raw?.is_orphan)"
              icon="tabler-credit-card"
              variant="text"
              color="primary"
              size="small"
              title="Editar plan, vencimiento y fecha de ingreso"
              @click="openBillingDialog(item)"
            />

            <VBtn
              v-if="!(item.is_orphan ?? item.raw?.is_orphan)"
              icon="tabler-world"
              variant="text"
              color="info"
              size="small"
              title="Gestionar dominios personalizados"
              @click="openManageDomainsDialog(item)"
            />

            <VBtn
              icon="tabler-trash"
              variant="text"
              color="error"
              size="small"
              @click="confirmDeleteTenant(item)"
            />
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- Modal de Confirmación de Eliminación -->
    <VDialog
      v-model="isDeleteDialogOpen"
      max-width="500"
      persistent
      transition="dialog-bottom-transition"
    >
      <VCard class="detail-dialog-card overflow-hidden border-0 elevation-12 rounded-xl">
        <!-- Cabecera Premium -->
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
              Esta acción eliminará el subdominio, sus configuraciones y destruirá completamente su base de datos física en MySQL. Esta operación no se puede deshacer.
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
      max-width="680"
      persistent
      scrollable
      :fullscreen="$vuetify.display.xs"
      transition="dialog-bottom-transition"
    >
      <VCard class="detail-dialog-card overflow-hidden border-0 elevation-12 rounded-xl">
        <!-- Cabecera Premium con Gradiente del Sistema -->
        <VCardTitle class="pa-0">
          <div class="header-gradient pa-4 d-flex align-center shadow-sm">
            <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-2">
              <VIcon icon="tabler-building-store" color="primary" size="24" />
            </VAvatar>
            <div class="d-flex flex-column">
              <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
                Aprovisionar Nueva Empresa
              </h2>
              <span class="text-super-xs text-white opacity-90 uppercase font-weight-bold letter-spacing-1">
                Creación de instancia aislada y precarga de vertical
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

        <!-- Contenido del Modal con el Diseño Estándar del Sistema -->
        <VCardText class="pa-4 pa-sm-6 bg-light overflow-y-auto" style="max-height: 75vh;">
          <VForm @submit.prevent="handleCreateTenant">
            <div class="d-flex flex-column ga-4">
              <!-- Bloque 1: Vertical y Perfil Operativo -->
              <VCard variant="flat" class="border pa-4 bg-white rounded-lg elevation-1">
                <div class="d-flex align-center ga-2 mb-3">
                  <div class="header-indicator primary" />
                  <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">
                    Vertical y Perfil Operativo
                  </span>
                </div>

                <p class="text-caption text-medium-emphasis mb-3">
                  Al crear la empresa, el sistema creará automáticamente su base de datos aislada, ejecutará todas las migraciones e inicializará el entorno según la vertical seleccionada.
                </p>

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
                >
                  <template #selection="{ item }">
                    <div class="d-flex align-center ga-2">
                      <VIcon :icon="item.raw?.icon || 'tabler-topology-star-3'" color="primary" size="20" />
                      <span class="font-weight-bold text-high-emphasis">{{ item.title }}</span>
                    </div>
                  </template>

                  <template #item="{ props: itemProps, item }">
                    <VListItem v-bind="itemProps" :title="undefined" class="py-2">
                      <template #prepend>
                        <VAvatar
                          :color="item.raw?.color || 'primary'"
                          variant="tonal"
                          size="34"
                          rounded
                          class="me-3"
                        >
                          <VIcon :icon="item.raw?.icon || 'tabler-building-store'" size="18" />
                        </VAvatar>
                      </template>
                      <VListItemTitle class="font-weight-bold text-body-2">
                        {{ item.raw?.title }}
                      </VListItemTitle>
                      <VListItemSubtitle v-if="item.raw?.description" class="text-caption text-medium-emphasis mt-1">
                        {{ item.raw.description }}
                      </VListItemSubtitle>
                    </VListItem>
                  </template>
                </VSelect>

                <div
                  v-if="selectedVerticalMeta?.description"
                  class="d-flex align-center ga-2 mt-3 pa-2 rounded-lg bg-light-surface border"
                >
                  <VIcon :icon="selectedVerticalMeta.icon" color="primary" size="18" class="ms-1 flex-shrink-0" />
                  <span class="text-caption text-medium-emphasis">
                    {{ selectedVerticalMeta.description }}
                  </span>
                </div>
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
                  <VCol cols="12" class="mb-3">
                    <VTextField
                      v-model="form.company_name"
                      label="Nombre de la Empresa / Razón Social *"
                      placeholder="Ej: Droguería Mayorista C.A."
                      prepend-inner-icon="tabler-building"
                      variant="outlined"
                      density="comfortable"
                      :error-messages="errors.company_name"
                      :disabled="isSubmitting"
                      hide-details="auto"
                      @input="handleCompanyNameInput"
                    />
                  </VCol>

                  <VCol cols="12">
                    <VTextField
                      v-model="form.tenant_id"
                      label="Identificador de Subdominio *"
                      placeholder="drogueria-central"
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

        <!-- Pie del Modal con Botones Estándar del Sistema -->
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
                {{ isSubmitting ? 'Aprovisionando...' : 'Crear y Aprovisionar' }}
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
          <!-- Lista de Dominios Actuales -->
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
              No hay dominios explícitos configurados. Responde por defecto a {{ tenantForDomains?.id }}.tovaerp.com.
            </div>
          </div>

          <VDivider class="my-4" />

          <!-- Formulario para Agregar Nuevo Dominio -->
          <div>
            <h4 class="text-subtitle-2 font-weight-bold text-high-emphasis mb-2">
              Agregar nuevo dominio personalizado:
            </h4>
            <p class="text-caption text-medium-emphasis mb-3">
              Puedes registrar un dominio completo (ej. <strong>toffle.co</strong> o <strong>www.toffle.co</strong>).
            </p>
            <VRow dense>
              <VCol cols="12">
                <VTextField
                  v-model="newDomainInput"
                  label="Nuevo Dominio o Subdominio"
                  placeholder="toffle.co"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="tabler-plus"
                  :error-messages="domainError"
                  @keyup.enter="handleAddDomain"
                />
              </VCol>
            </VRow>
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

    <!-- Dialog: Registrar Tenant Externo -->
    <VDialog v-model="isExternalDialogOpen" max-width="600" persistent>
      <VCard class="rounded-xl">
        <VCardTitle class="pa-5 d-flex align-center justify-space-between">
          <div class="d-flex align-center ga-2">
            <VIcon icon="tabler-world-plus" color="primary" />
            <span class="text-h6 font-weight-bold">Registrar Tenant Externo</span>
          </div>
          <VBtn icon="tabler-x" variant="text" size="small" @click="isExternalDialogOpen = false" />
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-5">
          <VRow>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="externalForm.company_name"
                label="Empresa / Razón Social"
                variant="outlined"
                density="compact"
                :error-messages="externalErrors.company_name"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="externalForm.tenant_id"
                label="Identificador (ID único)"
                placeholder="ej. farmacia_norte"
                variant="outlined"
                density="compact"
                :error-messages="externalErrors.tenant_id"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="externalForm.domain"
                label="Dominio"
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
                label="Plan"
                variant="outlined"
                density="compact"
                :error-messages="externalErrors.plan"
                @update:model-value="onExternalPlanChange"
              />
            </VCol>
            <VCol cols="12" sm="4">
              <VTextField
                v-model.number="externalForm.monthly_price"
                label="Costo mensual (USD)"
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
                label="Fecha de Vencimiento"
                type="date"
                variant="outlined"
                density="compact"
                :error-messages="externalErrors.billing_date"
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
            Guardar Tenant Externo
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog: Editar Plan y Fecha de Cobro -->
    <VDialog v-model="isBillingDialogOpen" max-width="480" persistent>
      <VCard class="rounded-xl">
        <VCardTitle class="pa-5 d-flex align-center justify-space-between">
          <div class="d-flex align-center ga-2">
            <VIcon icon="tabler-credit-card" color="primary" />
            <span class="text-h6 font-weight-bold">Plan y Cobro: {{ billingForm.company_name }}</span>
          </div>
          <VBtn icon="tabler-x" variant="text" size="small" @click="isBillingDialogOpen = false" />
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-5">
          <VRow>
            <VCol cols="12">
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
              <VTextField
                v-model.number="billingForm.monthly_price"
                label="Costo mensual (USD)"
                type="number"
                min="0"
                step="0.01"
                prefix="$"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="billingForm.billing_date"
                label="Fecha de Vencimiento"
                type="date"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model="billingForm.created_at"
                label="Fecha de Ingreso"
                type="date"
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
            Actualizar
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
