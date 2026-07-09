<script setup>
import axios from "@/plugins/axios";
import { ref, watch, computed } from "vue";

import { useBrandingStore } from "@/stores/useBrandingStore";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  clientId: { type: [Number, null], default: null },
});

const emit = defineEmits(["update:modelValue"]);

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => (brandingStore.settings.business_type === 'restaurant' || brandingStore.settings.business_type === 'minimarket'));

const loading = ref(false);
const clientData = ref(null);
const stats = ref(null);
const topProducts = ref([]);
const lastProducts = ref([]);

const badgeConfig = computed(() => {
  if (!stats.value) return {};
  const map = {
    VIP: { color: "warning", icon: "tabler-crown", text: "VIP" },
    Frecuente: { color: "success", icon: "tabler-heart", text: "Frecuente" },
    "En Riesgo": { color: "error", icon: "tabler-alert-triangle", text: "En Riesgo" },
    Ocasional: { color: "info", icon: "tabler-user", text: "Ocasional" },
    Nuevo: { color: "primary", icon: "tabler-user-plus", text: "Nuevo" },
    Inactivo: { color: "secondary", icon: "tabler-user-off", text: "Inactivo" },
  };
  return map[stats.value.badge] || map["Nuevo"];
});

const dialogTitle = computed(() => {
  if (!clientData.value) return "Estadísticas del Cliente";
  const name = clientData.value.name || "";
  const lastName = clientData.value.last_name || "";
  return `${name} ${lastName}`.trim();
});

const fetchStats = async (id) => {
  if (!id) return;
  loading.value = true;
  try {
    const response = await axios.get(`/crm/clients/${id}/stats`);
    const data = response.data.data;
    clientData.value = data.client;
    stats.value = data.stats;
    topProducts.value = data.top_products || [];
    lastProducts.value = data.last_products || [];
  } catch (error) {
    console.error("Error al cargar estadísticas:", error);
  } finally {
    loading.value = false;
  }
};

watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible && props.clientId) {
      fetchStats(props.clientId);
    }
    if (!isVisible) {
      clientData.value = null;
      stats.value = null;
      topProducts.value = [];
      lastProducts.value = [];
    }
  }
);

const onClose = () => {
  emit("update:modelValue", false);
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="750px"
    persistent
    scrollable
    :retain-focus="false"
    :fullscreen="$vuetify.display.xs"
    transition="dialog-bottom-transition"
    class="premium-dialog"
    @click:outside="onClose"
    @keydown.esc="onClose"
  >
    <VCard class="detail-dialog-card overflow-hidden border-0 elevation-12">
      <!-- Cabecera Compacta Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-3 d-flex align-center shadow-sm">
          <div class="d-flex align-center">
            <VAvatar color="white" variant="flat" size="32" class="me-3 elevation-1">
              <VIcon icon="tabler-chart-bar" color="primary" size="18" />
            </VAvatar>
            <div>
              <h2 class="text-subtitle-2 font-weight-black text-white leading-tight mb-0 uppercase">{{ dialogTitle }}</h2>
              <div class="d-flex align-center gap-1 mt-0">
                <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold">
                  Indicadores de Comportamiento
                </span>
                <VChip
                  v-if="stats"
                  :color="badgeConfig.color"
                  :prepend-icon="badgeConfig.icon"
                  size="x-small"
                  variant="flat"
                  class="font-weight-black px-2 py-0 h-auto"
                  style="block-size: 14px !important; font-size: 0.55rem;"
                >
                  {{ badgeConfig.text.toUpperCase() }}
                </VChip>
              </div>
            </div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" color="white" size="x-small" @click="onClose">
            <VIcon size="18">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-4 bg-light" style="max-block-size: 80vh;">
        <!-- Loading -->
        <div v-if="loading" class="d-flex flex-column justify-center align-center py-10 gap-3">
          <VProgressCircular indeterminate color="primary" size="48" />
          <span class="text-super-xs text-disabled font-weight-black uppercase">Analizando datos del cliente...</span>
        </div>

        <template v-else-if="stats">
          <!-- Datos Rápidos -->
          <div v-if="clientData" class="mb-4">
            <div class="d-flex align-center flex-wrap gap-x-4 gap-y-2 text-super-xs font-weight-bold text-medium-emphasis uppercase">
              <span class="d-flex align-center gap-1 bg-white px-2 py-1 rounded border shadow-sm">
                <VIcon icon="tabler-id" size="12" class="text-primary" />
                {{ clientData.identification_type }}{{ clientData.identification }}
              </span>
              <span v-if="clientData.company" class="d-flex align-center gap-1 bg-white px-2 py-1 rounded border shadow-sm">
                <VIcon icon="tabler-building" size="12" class="text-primary" />
                {{ clientData.company }}
              </span>
              <span v-if="stats.last_purchase_date" class="d-flex align-center gap-1 bg-white px-2 py-1 rounded border shadow-sm border-primary border-opacity-25">
                <VIcon icon="tabler-calendar-check" size="12" class="text-primary" />
                Última: {{ stats.last_purchase_date }}
              </span>
            </div>
          </div>

          <!-- Grid de Métricas Premium -->
          <VRow dense class="mb-4">
            <VCol cols="6" sm="3">
              <VCard variant="flat" border class="pa-3 text-center stat-box-premium h-100">
                <div class="label-premium mb-1">Total Gastado</div>
                <div class="text-subtitle-1 font-weight-black text-success leading-tight">${{ stats.total_spent?.toFixed(2) }}</div>
                <VIcon icon="tabler-currency-dollar" size="14" class="text-disabled mt-1" />
              </VCard>
            </VCol>

            <VCol cols="6" sm="3">
              <VCard variant="flat" border class="pa-3 text-center stat-box-premium h-100">
                <div class="label-premium mb-1">Compras</div>
                <div class="text-subtitle-1 font-weight-black text-primary leading-tight">{{ stats.total_orders }}</div>
                <VIcon icon="tabler-shopping-cart" size="14" class="text-disabled mt-1" />
              </VCard>
            </VCol>

            <VCol cols="6" sm="3">
              <VCard variant="flat" border class="pa-3 text-center stat-box-premium h-100">
                <div class="label-premium mb-1">Ticket Prom.</div>
                <div class="text-subtitle-1 font-weight-black text-info leading-tight">${{ stats.average_ticket?.toFixed(2) }}</div>
                <VIcon icon="tabler-receipt" size="14" class="text-disabled mt-1" />
              </VCard>
            </VCol>

            <VCol cols="6" sm="3">
              <VCard
                variant="flat"
                border
                class="pa-3 text-center stat-box-premium h-100"
                :class="stats.days_since_last_purchase > 30 ? 'border-error border-opacity-25' : ''"
              >
                <div class="label-premium mb-1">Inactividad</div>
                <div
                  class="text-subtitle-1 font-weight-black leading-tight"
                  :class="stats.days_since_last_purchase > 30 ? 'text-error' : 'text-warning'"
                >
                  {{ stats.days_since_last_purchase !== null ? Math.round(stats.days_since_last_purchase) : '0' }} <small class="text-xs">Días</small>
                </div>
                <VIcon icon="tabler-clock-exclamation" size="14" class="text-disabled mt-1" />
              </VCard>
            </VCol>
          </VRow>

          <!-- Tops y Listados -->
          <div class="d-flex flex-column gap-4">
            <!-- Favoritos -->
            <VCard v-if="topProducts.length > 0" variant="flat" border class="overflow-hidden">
              <div class="bg-light pa-3 border-b d-flex align-center gap-2">
                <VIcon icon="tabler-star-filled" size="16" color="warning" />
                <span class="text-xs font-weight-black uppercase">
                  {{ isRestaurant ? 'Platos/Productos Favoritos' : 'Productos Favoritos' }}
                </span>
              </div>
              <VList density="compact" class="pa-0">
                <template v-for="(product, index) in topProducts" :key="'top-' + index">
                  <VListItem class="py-2">
                    <template #prepend>
                      <div class="text-h6 font-weight-black text-primary opacity-25 me-3" style="inline-size: 24px;">{{ index + 1 }}</div>
                    </template>
                    <VListItemTitle class="text-super-xs font-weight-black uppercase truncate">{{ product.product_name }}</VListItemTitle>
                    <VListItemSubtitle class="text-super-xs font-weight-bold text-disabled uppercase">
                      <span v-if="product.is_dish">Plato</span>
                      <span v-else>{{ product.laboratory_name || 'Sin lab.' }}</span>
                      · {{ product.total_quantity }} Uds.
                    </VListItemSubtitle>
                  </VListItem>
                  <VDivider v-if="index < topProducts.length - 1" class="border-opacity-10" />
                </template>
              </VList>
            </VCard>
 
            <!-- Historial Reciente -->
            <VCard v-if="lastProducts.length > 0" variant="flat" border class="overflow-hidden">
              <div class="bg-light pa-3 border-b d-flex align-center gap-2">
                <VIcon icon="tabler-history" size="16" color="primary" />
                <span class="text-xs font-weight-black uppercase">Historial Reciente</span>
              </div>
              <div class="max-h-300 overflow-y-auto">
                <VList density="compact" class="pa-0">
                  <template v-for="(product, index) in lastProducts" :key="'last-' + index">
                    <VListItem class="py-3">
                      <div class="d-flex justify-space-between align-center w-100">
                        <div class="min-width-0 me-2">
                          <div class="text-super-xs font-weight-black uppercase truncate">{{ product.product_name }}</div>
                          <div class="text-super-xs text-disabled font-weight-bold uppercase">
                            {{ product.date }} · {{ product.quantity }} uds. <span v-if="product.is_dish">· Plato</span>
                          </div>
                        </div>
                        <div class="text-right flex-shrink-0">
                          <div class="text-xs font-weight-black text-primary">${{ product.total_usd?.toFixed(2) }}</div>
                        </div>
                      </div>
                    </VListItem>
                    <VDivider v-if="index < lastProducts.length - 1" class="border-opacity-10" />
                  </template>
                </VList>
              </div>
            </VCard>
          </div>

          <!-- Estado Vacío -->
          <div v-if="stats.total_orders === 0" class="text-center py-10 opacity-50">
            <VIcon icon="tabler-shopping-cart-off" size="48" class="mb-3" />
            <div class="text-xs font-weight-black uppercase">Sin historial comercial</div>
          </div>
        </template>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-light">
        <VBtn
          color="primary"
          size="large"
          block
          block-size="52"
          class="font-weight-black rounded-lg text-button uppercase text-white header-gradient"
          @click="onClose"
        >
          CERRAR ANÁLISIS
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #173b1f 100%);
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.label-premium {
  font-size: 0.55rem;
  color: rgba(var(--v-theme-on-surface), 0.45);
  font-weight: 900;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.stat-box-premium {
  border-radius: 12px !important;
  background: white !important;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 5%) !important;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.leading-tight { line-height: 1.25 !important; }

.uppercase { text-transform: uppercase; }

.max-h-300 {
  max-block-size: 300px;
}

.text-button {
  font-size: 0.875rem !important;
  letter-spacing: 1px !important;
}
</style>
