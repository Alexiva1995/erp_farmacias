<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import { useBrandingStore } from '@/stores/useBrandingStore'

const router = useRouter()
const brandingStore = useBrandingStore()

const activeTab = ref('discounts')
const loadingMetrics = ref(false)
const metrics = ref({
  summary: { total_uses: 0, total_sales_usd: 0, gross_margin_usd: 0 },
  by_type: {},
})

const enabledOffers = computed(() => {
  return brandingStore.settings?.enabled_offer_types || [
    'general',
    'individual',
    'category',
    'pack',
    'company',
    'doctor',
    'prescription',
    'expiration',
  ]
})

const showExpirationTab = computed(() => enabledOffers.value.includes('expiration'))
const showPharmacyTab = computed(() =>
  enabledOffers.value.some((t) => ['company', 'doctor', 'prescription'].includes(t))
)

const fetchMetrics = async () => {
  loadingMetrics.value = true
  try {
    const response = await axios.get('/tpv/promotions/metrics')
    if (response.data?.success) {
      metrics.value = response.data.data
    }
  } catch (error) {
    toast.error('No se pudieron cargar las métricas de promociones')
  } finally {
    loadingMetrics.value = false
  }
}

const goTo = (path) => {
  router.push(path)
}

onMounted(() => {
  fetchMetrics()
})
</script>

<template>
  <VContainer fluid class="pa-4 pa-md-6">
    <VRow class="mb-4 align-center">
      <VCol cols="12" md="8">
        <h1 class="text-h5 font-weight-bold mb-1">
          Centro de Promociones
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Gestiona descuentos, horarios Happy Hour, combos y convenios desde un solo lugar.
        </p>
      </VCol>
      <VCol cols="12" md="4" class="d-flex justify-md-end">
        <VBtn
          color="primary"
          variant="outlined"
          prepend-icon="tabler-refresh"
          :loading="loadingMetrics"
          @click="fetchMetrics"
        >
          Actualizar métricas
        </VBtn>
      </VCol>
    </VRow>

    <!-- KPIs accionables -->
    <VRow class="mb-6">
      <VCol cols="12" sm="4">
        <VCard variant="outlined" class="pa-4">
          <div class="text-caption text-medium-emphasis mb-1">
            Órdenes con promoción
          </div>
          <VSkeletonLoader v-if="loadingMetrics" type="heading" />
          <div v-else class="text-h4 font-weight-bold text-primary">
            {{ metrics.summary.total_uses }}
          </div>
        </VCard>
      </VCol>
      <VCol cols="12" sm="4">
        <VCard variant="outlined" class="pa-4">
          <div class="text-caption text-medium-emphasis mb-1">
            Ventas generadas con promo
          </div>
          <VSkeletonLoader v-if="loadingMetrics" type="heading" />
          <div v-else class="text-h4 font-weight-bold">
            ${{ Number(metrics.summary.total_sales_usd || 0).toFixed(2) }}
          </div>
        </VCard>
      </VCol>
      <VCol cols="12" sm="4">
        <VCard variant="outlined" class="pa-4">
          <div class="text-caption text-medium-emphasis mb-1">
            Margen bruto generado
          </div>
          <VSkeletonLoader v-if="loadingMetrics" type="heading" />
          <div
            v-else
            class="text-h4 font-weight-bold"
            :class="metrics.summary.gross_margin_usd >= 0 ? 'text-success' : 'text-error'"
          >
            ${{ Number(metrics.summary.gross_margin_usd || 0).toFixed(2) }}
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- Pestañas unificadas -->
    <VCard variant="outlined">
      <VTabs v-model="activeTab" color="primary" grow>
        <VTab value="discounts" prepend-icon="tabler-percentage">
          Descuentos y Happy Hour
        </VTab>
        <VTab value="combos" prepend-icon="tabler-packages">
          2x1, 3x2 y Combos
        </VTab>
        <VTab v-if="showExpirationTab" value="expiration" prepend-icon="tabler-calendar-time">
          Liquidación por Vencimiento
        </VTab>
        <VTab v-if="showPharmacyTab" value="pharmacy" prepend-icon="tabler-building-hospital">
          Convenios, Médicos y Récipes
        </VTab>
      </VTabs>

      <VDivider />

      <VWindow v-model="activeTab" class="pa-4 pa-md-6">
        <VWindowItem value="discounts">
          <VRow>
            <VCol v-if="enabledOffers.includes('general')" cols="12" md="4">
              <VCard variant="outlined" class="pa-4 h-100 d-flex flex-column justify-space-between">
                <div>
                  <VIcon icon="tabler-clock-hour-4" color="primary" size="28" class="mb-2" />
                  <h3 class="text-subtitle-1 font-weight-bold mb-1">
                    Promociones Generales y Happy Hour
                  </h3>
                  <p class="text-body-2 text-medium-emphasis">
                    Porcentajes globales o por categoría, horarios Happy Hour, días de la semana, compra mínima y cupones.
                  </p>
                </div>
                <VBtn color="primary" block class="mt-4" @click="goTo('/tpv/generalOffer')">
                  Gestionar Promos Generales
                </VBtn>
              </VCard>
            </VCol>

            <VCol v-if="enabledOffers.includes('category')" cols="12" md="4">
              <VCard variant="outlined" class="pa-4 h-100 d-flex flex-column justify-space-between">
                <div>
                  <VIcon icon="tabler-category" color="primary" size="28" class="mb-2" />
                  <h3 class="text-subtitle-1 font-weight-bold mb-1">
                    Ofertas por Categoría
                  </h3>
                  <p class="text-body-2 text-medium-emphasis">
                    Descuentos directos por línea de productos o categorías de menú con días y horarios.
                  </p>
                </div>
                <VBtn color="primary" variant="outlined" block class="mt-4" @click="goTo('/tpv/categoryOffer')">
                  Gestionar Categorías
                </VBtn>
              </VCard>
            </VCol>

            <VCol v-if="enabledOffers.includes('individual')" cols="12" md="4">
              <VCard variant="outlined" class="pa-4 h-100 d-flex flex-column justify-space-between">
                <div>
                  <VIcon icon="tabler-package" color="primary" size="28" class="mb-2" />
                  <h3 class="text-subtitle-1 font-weight-bold mb-1">
                    Ofertas Individuales (Producto o Plato)
                  </h3>
                  <p class="text-body-2 text-medium-emphasis">
                    Productos gancho o platos destacados con opción 1 por cliente y control de fechas.
                  </p>
                </div>
                <VBtn color="primary" variant="outlined" block class="mt-4" @click="goTo('/tpv/individualOffer')">
                  Gestionar Individuales
                </VBtn>
              </VCard>
            </VCol>
          </VRow>
        </VWindowItem>

        <VWindowItem value="combos">
          <VRow>
            <VCol v-if="enabledOffers.includes('general')" cols="12" md="6">
              <VCard variant="outlined" class="pa-4 h-100 d-flex flex-column justify-space-between">
                <div>
                  <VIcon icon="tabler-sparkles" color="primary" size="28" class="mb-2" />
                  <h3 class="text-subtitle-1 font-weight-bold mb-1">
                    Reglas 2x1, 3x2 y 50% en el 2do
                  </h3>
                  <p class="text-body-2 text-medium-emphasis">
                    Descuentos automáticos por volumen en la orden, cobrando el ítem de mayor valor y descontando el menor.
                  </p>
                </div>
                <VBtn color="primary" block class="mt-4" @click="goTo('/tpv/generalOffer')">
                  Configurar 2x1 / 3x2
                </VBtn>
              </VCard>
            </VCol>

            <VCol v-if="enabledOffers.includes('pack')" cols="12" md="6">
              <VCard variant="outlined" class="pa-4 h-100 d-flex flex-column justify-space-between">
                <div>
                  <VIcon icon="tabler-packages" color="primary" size="28" class="mb-2" />
                  <h3 class="text-subtitle-1 font-weight-bold mb-1">
                    Combos y Packs (Productos o Platos)
                  </h3>
                  <p class="text-body-2 text-medium-emphasis">
                    Arma combos cerrados de menú (plato + bebida) o kits de farmacia con precio promocional fijo.
                  </p>
                </div>
                <VBtn color="primary" variant="outlined" block class="mt-4" @click="goTo('/tpv/packOffer')">
                  Gestionar Combos / Packs
                </VBtn>
              </VCard>
            </VCol>
          </VRow>
        </VWindowItem>

        <VWindowItem v-if="showExpirationTab" value="expiration">
          <VCard variant="outlined" class="pa-4">
            <h3 class="text-subtitle-1 font-weight-bold mb-1">
              Liquidación automática por caducidad (FEFO)
            </h3>
            <p class="text-body-2 text-medium-emphasis mb-4">
              Aplica descuentos escalonados según los meses restantes de vencimiento del lote, respetando productos excluidos.
            </p>
            <VBtn color="primary" @click="goTo('/tpv/expirationOffer')">
              Ir a Ofertas por Caducidad
            </VBtn>
          </VCard>
        </VWindowItem>

        <VWindowItem v-if="showPharmacyTab" value="pharmacy">
          <VRow>
            <VCol v-if="enabledOffers.includes('company')" cols="12" md="4">
              <VCard variant="outlined" class="pa-4 h-100 d-flex flex-column justify-space-between">
                <div>
                  <h3 class="text-subtitle-1 font-weight-bold mb-1">
                    Convenios Corporativos
                  </h3>
                  <p class="text-body-2 text-medium-emphasis">
                    Descuentos acordados con empresas o aseguradoras.
                  </p>
                </div>
                <VBtn color="primary" block class="mt-4" @click="goTo('/tpv/companyOffer')">
                  Gestionar Convenios
                </VBtn>
              </VCard>
            </VCol>

            <VCol v-if="enabledOffers.includes('doctor')" cols="12" md="4">
              <VCard variant="outlined" class="pa-4 h-100 d-flex flex-column justify-space-between">
                <div>
                  <h3 class="text-subtitle-1 font-weight-bold mb-1">
                    Ofertas por Médico
                  </h3>
                  <p class="text-body-2 text-medium-emphasis">
                    Descuentos e incentivos vinculados a médicos prescriptores.
                  </p>
                </div>
                <VBtn color="primary" variant="outlined" block class="mt-4" @click="goTo('/tpv/doctorOffer')">
                  Gestionar Médicos
                </VBtn>
              </VCard>
            </VCol>

            <VCol v-if="enabledOffers.includes('prescription')" cols="12" md="4">
              <VCard variant="outlined" class="pa-4 h-100 d-flex flex-column justify-space-between">
                <div>
                  <h3 class="text-subtitle-1 font-weight-bold mb-1">
                    Descuentos por Récipe
                  </h3>
                  <p class="text-body-2 text-medium-emphasis">
                    Condiciones especiales al adjuntar receta médica en caja.
                  </p>
                </div>
                <VBtn color="primary" variant="outlined" block class="mt-4" @click="goTo('/tpv/prescriptionOffer')">
                  Gestionar Récipes
                </VBtn>
              </VCard>
            </VCol>
          </VRow>
        </VWindowItem>
      </VWindow>
    </VCard>
  </VContainer>
</template>
