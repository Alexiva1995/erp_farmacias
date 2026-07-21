<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from "@/plugins/sweetalert";
import { useBrandingStore } from "@/stores/useBrandingStore";

const brandingStore = useBrandingStore()

const enabledFinanceViews = ref([
  'profitability',
  'exchangerate',
  'pending-payments',
  'payment-history',
  'cashout',
  'payslips',
  'cash-closure',
  'cash-closure-user',
  'income-statement',
  'expense-expenses',
  'balance-general',
  'furnitures-list',
  'loans-list'
])

const profitabilityCalculationType = ref('simple')

const availableFinanceViews = [
  { key: 'profitability', title: 'Rentabilidad', description: 'Cálculo de márgenes globales y edición de costos/márgenes de ganancia.', icon: 'tabler-chart-pie' },
  { key: 'exchangerate', title: 'Tasa de cambio', description: 'Actualización diaria de tasas BCV, COP, EUR y Binance.', icon: 'tabler-coin' },
  { key: 'pending-payments', title: 'Por Pagar', description: 'Control de facturas acumuladas por pagar y pagos parciales.', icon: 'tabler-file-analytics' },
  { key: 'payment-history', title: 'Historial de Pagos', description: 'Consulta detallada de todos los pagos completados.', icon: 'tabler-receipt' },
  { key: 'cashout', title: 'Flujo de caja', description: 'Reportes de balance, wallets y ajustes de saldos.', icon: 'tabler-wallet' },
  { key: 'payslips', title: 'Nómina', description: 'Administración y entrega digital de recibos de nómina.', icon: 'tabler-users-group' },
  { key: 'cash-closure', title: 'Cierre de caja', description: 'Visualización y control administrativo de cierres de caja.', icon: 'tabler-device-desktop-analytics' },
  { key: 'cash-closure-user', title: 'Cierre de caja Usuarios', description: 'Gestión de cierres específicos para cajeros del sistema.', icon: 'tabler-lock-square' },
  { key: 'income-statement', title: 'Estado de Resultados', description: 'Reportes financieros consolidados de ganancias y pérdidas.', icon: 'tabler-presentation' },
  { key: 'expense-expenses', title: 'Gastos', description: 'Seguimiento de egresos, egresos rápidos y recurrentes.', icon: 'tabler-trending-down' },
  { key: 'balance-general', title: 'Balance General', description: 'Estado general de situación financiera y activos.', icon: 'tabler-report' },
  { key: 'furnitures-list', title: 'Mobiliario', description: 'Control de activos fijos, estantería y mobiliario.', icon: 'tabler-armchair' },
  { key: 'loans-list', title: 'Préstamos', description: 'Registro y seguimiento de préstamos y amortizaciones.', icon: 'tabler-cash-banknote' },
]

const fetchSettings = async () => {
  try {
    const response = await axios.get('/general-settings')
    const settings = response.data.data
    if (settings.enabled_finance_views) {
      enabledFinanceViews.value = settings.enabled_finance_views
    }
    if (settings.profitability_calculation_type) {
      profitabilityCalculationType.value = settings.profitability_calculation_type
    }
  } catch (error) {
    console.error("Error cargando configuración de Finanzas:", error)
    toast.error("Error al cargar la configuración")
  }
}

const toggleFinanceView = (key) => {
  const index = enabledFinanceViews.value.indexOf(key)
  if (index > -1) {
    enabledFinanceViews.value.splice(index, 1)
  } else {
    enabledFinanceViews.value.push(key)
  }
  updateSettings()
}

const changeCalculationType = (type) => {
  profitabilityCalculationType.value = type
  updateSettings()
}

const updateSettings = async () => {
  try {
    await axios.post('/general-settings', {
      enabled_finance_views: enabledFinanceViews.value,
      profitability_calculation_type: profitabilityCalculationType.value
    })
    await brandingStore.fetchSettings()

    toast.success("Configuración de Finanzas actualizada exitosamente")
  } catch (error) {
    console.error("Error al guardar:", error)
    toast.error("Error al actualizar la configuración")
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <div>
    <!-- Configuración del Tipo de Cálculo de Rentabilidad -->
    <VCard class="mb-6 rounded-lg border shadow-sm">
      <VCardItem class="py-5">
        <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2 mb-2">
          <VIcon icon="tabler-calculator" class="text-primary" size="26" />
          Fórmula de Rentabilidad y Precios de Venta
        </VCardTitle>
        <p class="text-caption text-medium-emphasis mb-6">
          Selecciona la fórmula matemática y estructura de cálculo financiero a utilizar para definir los precios de venta a partir de los costos unitarios del inventario.
        </p>

        <VRow>
          <VCol cols="12" md="6">
            <VCard
              variant="outlined"
              class="rounded-lg cursor-pointer transition-all h-100"
              :class="profitabilityCalculationType === 'simple' ? 'border-primary bg-var-theme-background' : 'opacity-60'"
              @click="changeCalculationType('simple')"
            >
              <VCardItem class="py-5 px-5">
                <div class="d-flex align-start gap-4">
                  <VAvatar color="primary" variant="tonal" size="48" class="rounded-lg">
                    <VIcon icon="tabler-percentage" size="24" />
                  </VAvatar>
                  <div class="d-flex flex-column gap-1">
                    <span class="font-weight-black text-body-1 text-high-emphasis">Fórmula Simple (Markup)</span>
                    <span class="text-xs text-disabled font-weight-bold">PV = Costo * (1 + Margen / 100)</span>
                    <p class="text-caption text-medium-emphasis mt-2 mb-0">
                      Calcula el precio de venta añadiendo un porcentaje directo sobre el costo base del producto. Ideal para flujos simples y rápidos.
                    </p>
                  </div>
                </div>
              </VCardItem>
            </VCard>
          </VCol>

          <VCol cols="12" md="6">
            <VCard
              variant="outlined"
              class="rounded-lg cursor-pointer transition-all h-100"
              :class="profitabilityCalculationType === 'compound' ? 'border-primary bg-var-theme-background' : 'opacity-60'"
              @click="changeCalculationType('compound')"
            >
              <VCardItem class="py-5 px-5">
                <div class="d-flex align-start gap-4">
                  <VAvatar color="primary" variant="tonal" size="48" class="rounded-lg">
                    <VIcon icon="tabler-math-function" size="24" />
                  </VAvatar>
                  <div class="d-flex flex-column gap-1">
                    <span class="font-weight-black text-body-1 text-high-emphasis">Fórmula Compuesta (Margen Financiero)</span>
                    <span class="text-xs text-disabled font-weight-bold">PV = (Costo USA * (1 + TAX) + Envío + Embalaje + Gastos Fijos) / (1 - Margen Ganancia)</span>
                    <p class="text-caption text-medium-emphasis mt-2 mb-0">
                      Cálculo estructurado multi-variable que descuenta impuestos de importación, flete, embalaje y margen de gastos para garantizar el porcentaje de beneficio neto deseado.
                    </p>
                  </div>
                </div>
              </VCardItem>
            </VCard>
          </VCol>
        </VRow>
      </VCardItem>
    </VCard>

    <!-- Tarjeta Principal de Configuración de Vistas de Finanzas -->
    <VCard class="mb-6 rounded-lg border shadow-sm">
      <VCardItem class="py-5">
        <VCardTitle class="text-h5 font-weight-black text-uppercase d-flex align-center gap-2 mb-2">
          <VIcon icon="tabler-chart-bar" class="text-primary" size="26" />
          Configuración de Vistas de Finanzas
        </VCardTitle>
        <p class="text-caption text-medium-emphasis mb-6">
          Habilita o deshabilita los módulos y vistas financieras en la barra de navegación lateral. Las vistas desmarcadas se ocultarán inmediatamente para todos los usuarios.
        </p>

        <VRow>
          <VCol v-for="view in availableFinanceViews" :key="view.key" cols="12" sm="6" md="3">
            <VCard
              variant="outlined"
              class="rounded-lg cursor-pointer transition-all h-100"
              :class="enabledFinanceViews.includes(view.key) ? 'border-primary bg-var-theme-background' : 'opacity-60'"
              @click="toggleFinanceView(view.key)"
            >
              <VCardItem class="py-4 px-4">
                <div class="d-flex flex-column h-100 justify-space-between">
                  <div>
                    <div class="d-flex align-center justify-space-between w-100 mb-2">
                      <div class="d-flex align-center">
                        <VAvatar color="primary" variant="tonal" size="32" class="me-2">
                          <VIcon :icon="view.icon" size="18" />
                        </VAvatar>
                        <span class="font-weight-black text-body-2" :class="enabledFinanceViews.includes(view.key) ? 'text-high-emphasis' : 'text-disabled'">
                          {{ view.title }}
                        </span>
                      </div>
                      <VSwitch
                        :model-value="enabledFinanceViews.includes(view.key)"
                        density="compact"
                        hide-details
                        color="primary"
                        @click.stop
                        @update:model-value="toggleFinanceView(view.key)"
                      />
                    </div>
                    <p class="text-caption text-medium-emphasis mb-0 leading-tight">
                      {{ view.description }}
                    </p>
                  </div>
                </div>
              </VCardItem>
            </VCard>
          </VCol>
        </VRow>
      </VCardItem>
    </VCard>
  </div>
</template>
