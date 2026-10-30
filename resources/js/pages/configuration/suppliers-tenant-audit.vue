<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from '@axios'
import { toast } from '@/plugins/sweetalert'

// Estados reactivos
const isLoading = ref(false)
const searchQuery = ref('')
const selectedSupplierId = ref(1016) // Vickmedicals por defecto
const selectedMonth = ref(new Date().toISOString().slice(0, 7)) // YYYY-MM actual

// Datos cargados desde la API
const auditPeriod = reactive({
  month: '',
  label: '',
  start_date: '',
  end_date: '',
})

const auditSupplier = reactive({
  id: 1016,
  name: 'VICKMEDICALS',
})

const kpis = reactive({
  total_tenants: 0,
  buying_now_count: 0,
  historical_buyers_count: 0,
  catalog_only_count: 0,
  inactive_count: 0,
  orders_month_count: 0,
  invoices_month_count: 0,
  invoices_month_usd: 0,
  invoices_total_usd: 0,
})

const tenants = ref([])
const availableSuppliers = ref([])

// Columnas de la tabla
const headers = [
  { title: 'Farmacia / Tenant', key: 'tenant_name', sortable: true },
  { title: 'Estado Relación', key: 'relationship_label', sortable: true },
  { title: 'Actualiz. Catálogo', key: 'catalog_updates_month', sortable: true },
  { title: 'Prods. en Catálogo', key: 'current_products', sortable: true },
  { title: 'Pedidos AutoOrder', key: 'orders_month', sortable: true },
  { title: 'Facturas Compra', key: 'invoices_month', sortable: true },
  { title: 'Total Facturado USD', key: 'invoices_total_usd', sortable: true },
  { title: 'Última Factura', key: 'last_invoice_date', sortable: true },
]

// Opciones mapeadas para el selector de proveedores
const supplierOptions = computed(() => {
  return availableSuppliers.value.map(s => ({
    title: `${s.name}${s.rif ? ' (' + s.rif + ')' : ''}`,
    value: s.id,
  }))
})

// Filtrado reactivo de la tabla por buscador
const filteredTenants = computed(() => {
  if (!searchQuery.value) return tenants.value

  const q = searchQuery.value.toLowerCase().trim()
  return tenants.value.filter(t => {
    return (
      (t.tenant_name && t.tenant_name.toLowerCase().includes(q)) ||
      (t.tenant_id && t.tenant_id.toLowerCase().includes(q)) ||
      (t.tenant_domain && t.tenant_domain.toLowerCase().includes(q)) ||
      (t.relationship_label && t.relationship_label.toLowerCase().includes(q))
    )
  })
})

// Petición a la API central
const fetchAuditData = async () => {
  isLoading.value = true
  try {
    const response = await axios.get('/central/suppliers-audit', {
      params: {
        supplier_id: selectedSupplierId.value,
        month: selectedMonth.value,
      },
    })

    const payload = response.data?.data || response.data || {}

    // Asignar período
    if (payload.period) {
      auditPeriod.month = payload.period.month || ''
      auditPeriod.label = payload.period.label || ''
      auditPeriod.start_date = payload.period.start_date || ''
      auditPeriod.end_date = payload.period.end_date || ''
    }

    // Asignar proveedor
    if (payload.supplier) {
      auditSupplier.id = payload.supplier.id || 1016
      auditSupplier.name = payload.supplier.name || 'VICKMEDICALS'
    }

    // Asignar KPIs
    if (payload.kpis) {
      Object.assign(kpis, payload.kpis)
    }

    // Asignar filas de tenants
    tenants.value = Array.isArray(payload.tenants) ? payload.tenants : []

    // Asignar lista de proveedores
    if (Array.isArray(payload.suppliers) && payload.suppliers.length > 0) {
      availableSuppliers.value = payload.suppliers
    } else if (availableSuppliers.value.length === 0) {
      availableSuppliers.value = [{ id: 1016, name: 'VICKMEDICALS', rif: 'J412279149' }]
    }
  } catch (error) {
    toast.error('Error al consultar auditoría multi-tenant: ' + (error.response?.data?.message || error.message))
  } finally {
    isLoading.value = false
  }
}

// Formateador de moneda
const formatCurrency = (val) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
  }).format(Number(val) || 0)
}

onMounted(() => {
  fetchAuditData()
})
</script>

<template>
  <div>
    <!-- Encabezado de la página -->
    <div class="d-flex flex-column flex-sm-row align-sm-center justify-space-between mb-6 ga-3">
      <div>
        <div class="d-flex align-center ga-2 mb-1">
          <h1 class="text-h5 font-weight-bold text-high-emphasis mb-0">
            Auditoría de Droguerías por Tenant
          </h1>
          <VChip
            color="primary"
            variant="tonal"
            size="small"
            class="font-weight-medium"
          >
            {{ auditSupplier.name }}
          </VChip>
        </div>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Monitoreo consolidado de cuántas farmacias consultan, piden o compran a cada droguería en tiempo real.
        </p>
      </div>

      <div class="d-flex align-center ga-2">
        <VBtn
          color="secondary"
          variant="outlined"
          prepend-icon="tabler-arrow-left"
          to="/configuration/tenants"
        >
          Volver a Empresas
        </VBtn>

        <VBtn
          color="primary"
          variant="flat"
          prepend-icon="tabler-refresh"
          :loading="isLoading"
          @click="fetchAuditData"
        >
          Actualizar
        </VBtn>
      </div>
    </div>

    <!-- Barra de Filtros -->
    <VCard class="mb-6 elevation-1">
      <VCardText>
        <VRow dense align="center">
          <VCol cols="12" sm="6" md="5">
            <VSelect
              v-model="selectedSupplierId"
              label="Droguería / Proveedor"
              :items="supplierOptions"
              item-title="title"
              item-value="value"
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-truck-delivery"
              hide-details
              @update:model-value="fetchAuditData"
            />
          </VCol>

          <VCol cols="12" sm="6" md="4">
            <VTextField
              v-model="selectedMonth"
              type="month"
              label="Mes de Análisis"
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-calendar"
              hide-details
              @update:model-value="fetchAuditData"
            />
          </VCol>

          <VCol cols="12" sm="12" md="3">
            <VTextField
              v-model="searchQuery"
              placeholder="Buscar farmacia..."
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-search"
              clearable
              hide-details
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Tarjetas KPI -->
    <VRow class="mb-6">
      <VCol cols="12" sm="6" md="3">
        <VCard elevation="1">
          <VCardText class="d-flex align-center ga-4">
            <VAvatar
              color="primary"
              variant="tonal"
              rounded="lg"
              size="48"
            >
              <VIcon icon="tabler-building-store" size="28" />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">Total Farmacias</div>
              <div class="text-h5 font-weight-bold">{{ kpis.total_tenants }}</div>
              <div class="text-caption text-secondary">Instancias evaluadas</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard elevation="1">
          <VCardText class="d-flex align-center ga-4">
            <VAvatar
              color="success"
              variant="tonal"
              rounded="lg"
              size="48"
            >
              <VIcon icon="tabler-shopping-cart-check" size="28" />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">Compran Este Mes</div>
              <div class="text-h5 font-weight-bold text-success">{{ kpis.buying_now_count }}</div>
              <div class="text-caption text-success">Facturas u órdenes activas</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard elevation="1">
          <VCardText class="d-flex align-center ga-4">
            <VAvatar
              color="warning"
              variant="tonal"
              rounded="lg"
              size="48"
            >
              <VIcon icon="tabler-file-search" size="28" />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">Solo Consultan Catálogo</div>
              <div class="text-h5 font-weight-bold text-warning">{{ kpis.catalog_only_count }}</div>
              <div class="text-caption text-warning">Catálogo activo sin compras</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard elevation="1">
          <VCardText class="d-flex align-center ga-4">
            <VAvatar
              color="info"
              variant="tonal"
              rounded="lg"
              size="48"
            >
              <VIcon icon="tabler-currency-dollar" size="28" />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis">Comprado Mes (USD)</div>
              <div class="text-h5 font-weight-bold text-primary">{{ formatCurrency(kpis.invoices_month_usd) }}</div>
              <div class="text-caption text-medium-emphasis">Histórico: {{ formatCurrency(kpis.invoices_total_usd) }}</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabla Detallada de Farmacias -->
    <VCard elevation="1">
      <VDataTable
        :headers="headers"
        :items="filteredTenants"
        :loading="isLoading"
        hover
        class="text-no-wrap"
      >
        <!-- Columna Farmacia / Tenant -->
        <template #item.tenant_name="{ item }">
          <div class="d-flex flex-column py-2">
            <span class="font-weight-bold text-high-emphasis">
              {{ item.tenant_name }}
            </span>
            <div class="d-flex align-center ga-1 mt-1">
              <span class="text-caption text-medium-emphasis font-mono">
                {{ item.tenant_domain }}
              </span>
              <a
                v-if="item.tenant_url"
                :href="item.tenant_url"
                target="_blank"
                rel="noopener noreferrer"
                class="text-primary text-decoration-none"
                title="Abrir ERP de esta farmacia"
              >
                <VIcon icon="tabler-external-link" size="14" />
              </a>
            </div>
          </div>
        </template>

        <!-- Columna Estado de Relación -->
        <template #item.relationship_label="{ item }">
          <VChip
            :color="item.relationship_color"
            variant="tonal"
            size="small"
            class="font-weight-medium"
          >
            {{ item.relationship_label }}
          </VChip>
        </template>

        <!-- Columna Actualizaciones de Catálogo -->
        <template #item.catalog_updates_month="{ item }">
          <div class="d-flex flex-column">
            <span class="font-weight-bold text-high-emphasis">
              {{ item.catalog_updates_month }} este mes
            </span>
            <span class="text-caption text-medium-emphasis">
              Total histórico: {{ item.catalog_updates_total }}
            </span>
          </div>
        </template>

        <!-- Columna Productos en Catálogo -->
        <template #item.current_products="{ item }">
          <VChip
            :color="item.current_products > 0 ? 'primary' : 'secondary'"
            variant="outlined"
            size="small"
          >
            {{ item.current_products }} ítems
          </VChip>
        </template>

        <!-- Columna Pedidos AutoOrder -->
        <template #item.orders_month="{ item }">
          <div class="d-flex flex-column">
            <span class="font-weight-medium">
              {{ item.orders_month }} pedidos
            </span>
            <span class="text-caption text-medium-emphasis">
              Total: {{ item.orders_total }} ({{ formatCurrency(item.orders_total_usd) }})
            </span>
          </div>
        </template>

        <!-- Columna Facturas de Compra -->
        <template #item.invoices_month="{ item }">
          <div class="d-flex flex-column">
            <span class="font-weight-bold" :class="item.invoices_month > 0 ? 'text-success' : ''">
              {{ item.invoices_month }} facturas
            </span>
            <span class="text-caption text-medium-emphasis">
              Mes: {{ formatCurrency(item.invoices_month_usd) }}
            </span>
          </div>
        </template>

        <!-- Columna Total Facturado USD Histórico -->
        <template #item.invoices_total_usd="{ item }">
          <div class="d-flex flex-column">
            <span class="font-weight-bold text-high-emphasis">
              {{ formatCurrency(item.invoices_total_usd) }}
            </span>
            <span class="text-caption text-medium-emphasis">
              {{ item.invoices_total }} facturas acum.
            </span>
          </div>
        </template>

        <!-- Columna Última Factura -->
        <template #item.last_invoice_date="{ item }">
          <span v-if="item.last_invoice_date" class="text-body-2 font-mono">
            {{ item.last_invoice_date }}
          </span>
          <span v-else class="text-caption text-medium-emphasis">
            Sin facturas
          </span>
        </template>

        <!-- Estado vacío -->
        <template #no-data>
          <div class="text-center py-8">
            <VIcon
              icon="tabler-database-off"
              size="48"
              color="secondary"
              class="mb-2"
            />
            <div class="text-body-1 font-weight-medium text-high-emphasis">
              No se encontraron farmacias registradas
            </div>
            <div class="text-caption text-medium-emphasis">
              Verifique la conexión con los tenants del sistema o el proveedor seleccionado.
            </div>
          </div>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>
