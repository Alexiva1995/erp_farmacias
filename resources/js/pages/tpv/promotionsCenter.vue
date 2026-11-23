<script setup>
import { computed, defineAsyncComponent, onMounted, ref, watch } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import { useBrandingStore } from '@/stores/branding'

const GeneralOfferView = defineAsyncComponent(() => import('@/pages/tpv/generalOffer.vue'))
const CategoryOfferView = defineAsyncComponent(() => import('@/pages/tpv/categoryOffer.vue'))
const IndividualOfferView = defineAsyncComponent(() => import('@/pages/tpv/individualOffer.vue'))
const PackOfferView = defineAsyncComponent(() => import('@/pages/tpv/packOffer.vue'))
const ExpirationOfferView = defineAsyncComponent(() => import('@/pages/tpv/expirationOffer.vue'))
const CompanyOfferView = defineAsyncComponent(() => import('@/pages/tpv/companyOffer.vue'))
const DoctorOfferView = defineAsyncComponent(() => import('@/pages/tpv/doctorOffer.vue'))
const PrescriptionOfferView = defineAsyncComponent(() => import('@/pages/tpv/prescriptionOffer.vue'))

const brandingStore = useBrandingStore()

const activeTab = ref('campaigns')
const campaignSubTab = ref('general')
const pharmacySubTab = ref('company')
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

const showCampaignsTab = computed(() =>
  enabledOffers.value.includes('general') || enabledOffers.value.includes('category')
)
const showIndividualTab = computed(() => enabledOffers.value.includes('individual'))
const showPackTab = computed(() => enabledOffers.value.includes('pack'))
const showExpirationTab = computed(() => enabledOffers.value.includes('expiration'))
const showPharmacyTab = computed(() =>
  enabledOffers.value.some((t) => ['company', 'doctor', 'prescription'].includes(t))
)

const availableTabs = computed(() => {
  const tabs = []
  if (showCampaignsTab.value) tabs.push('campaigns')
  if (showIndividualTab.value) tabs.push('individual')
  if (showPackTab.value) tabs.push('packs')
  if (showExpirationTab.value) tabs.push('expiration')
  if (showPharmacyTab.value) tabs.push('pharmacy')
  return tabs
})

watch(
  availableTabs,
  (tabs) => {
    if (tabs.length > 0 && !tabs.includes(activeTab.value)) {
      activeTab.value = tabs[0]
    }
  },
  { immediate: true }
)

watch(
  enabledOffers,
  (offers) => {
    if (!offers.includes('general') && offers.includes('category')) {
      campaignSubTab.value = 'category'
    } else {
      campaignSubTab.value = 'general'
    }

    if (offers.includes('company')) pharmacySubTab.value = 'company'
    else if (offers.includes('doctor')) pharmacySubTab.value = 'doctor'
    else if (offers.includes('prescription')) pharmacySubTab.value = 'prescription'
  },
  { immediate: true }
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

onMounted(() => {
  fetchMetrics()
})
</script>

<template>
  <VContainer fluid class="pa-4 pa-md-6">
    <VRow class="mb-4 align-center">
      <VCol cols="12" md="8">
        <h1 class="text-h5 font-weight-bold mb-1">
          Promociones
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Gestiona campañas, Happy Hour, ofertas por ítem, combos y convenios desde un solo lugar.
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

    <!-- Pestañas con gestión embebida -->
    <VCard variant="outlined">
      <VTabs v-model="activeTab" color="primary" show-arrows>
        <VTab v-if="showCampaignsTab" value="campaigns" prepend-icon="tabler-percentage">
          Campañas y Happy Hour
        </VTab>
        <VTab v-if="showIndividualTab" value="individual" prepend-icon="tabler-package">
          Por Producto / Plato
        </VTab>
        <VTab v-if="showPackTab" value="packs" prepend-icon="tabler-packages">
          Combos y Packs
        </VTab>
        <VTab v-if="showExpirationTab" value="expiration" prepend-icon="tabler-calendar-time">
          Vencimientos (FEFO)
        </VTab>
        <VTab v-if="showPharmacyTab" value="pharmacy" prepend-icon="tabler-building-hospital">
          Convenios y Récipes
        </VTab>
      </VTabs>

      <VDivider />

      <VWindow v-model="activeTab">
        <VWindowItem v-if="showCampaignsTab" value="campaigns">
          <div
            v-if="enabledOffers.includes('general') && enabledOffers.includes('category')"
            class="px-4 pt-4 d-flex gap-2"
          >
            <VBtn
              size="small"
              :color="campaignSubTab === 'general' ? 'primary' : 'secondary'"
              :variant="campaignSubTab === 'general' ? 'flat' : 'outlined'"
              @click="campaignSubTab = 'general'"
            >
              Campañas (%, 2x1, 3x2, Happy Hour, Cupones)
            </VBtn>
            <VBtn
              size="small"
              :color="campaignSubTab === 'category' ? 'primary' : 'secondary'"
              :variant="campaignSubTab === 'category' ? 'flat' : 'outlined'"
              @click="campaignSubTab = 'category'"
            >
              Reglas por Categoría
            </VBtn>
          </div>
          <GeneralOfferView v-if="campaignSubTab === 'general'" />
          <CategoryOfferView v-else />
        </VWindowItem>

        <VWindowItem v-if="showIndividualTab" value="individual">
          <IndividualOfferView />
        </VWindowItem>

        <VWindowItem v-if="showPackTab" value="packs">
          <PackOfferView />
        </VWindowItem>

        <VWindowItem v-if="showExpirationTab" value="expiration">
          <ExpirationOfferView />
        </VWindowItem>

        <VWindowItem v-if="showPharmacyTab" value="pharmacy">
          <div class="px-4 pt-4 d-flex flex-wrap gap-2">
            <VBtn
              v-if="enabledOffers.includes('company')"
              size="small"
              :color="pharmacySubTab === 'company' ? 'primary' : 'secondary'"
              :variant="pharmacySubTab === 'company' ? 'flat' : 'outlined'"
              @click="pharmacySubTab = 'company'"
            >
              Convenios (Empresas)
            </VBtn>
            <VBtn
              v-if="enabledOffers.includes('doctor')"
              size="small"
              :color="pharmacySubTab === 'doctor' ? 'primary' : 'secondary'"
              :variant="pharmacySubTab === 'doctor' ? 'flat' : 'outlined'"
              @click="pharmacySubTab = 'doctor'"
            >
              Médicos
            </VBtn>
            <VBtn
              v-if="enabledOffers.includes('prescription')"
              size="small"
              :color="pharmacySubTab === 'prescription' ? 'primary' : 'secondary'"
              :variant="pharmacySubTab === 'prescription' ? 'flat' : 'outlined'"
              @click="pharmacySubTab = 'prescription'"
            >
              Récipes
            </VBtn>
          </div>
          <CompanyOfferView v-if="pharmacySubTab === 'company'" />
          <DoctorOfferView v-else-if="pharmacySubTab === 'doctor'" />
          <PrescriptionOfferView v-else-if="pharmacySubTab === 'prescription'" />
        </VWindowItem>
      </VWindow>
    </VCard>
  </VContainer>
</template>
