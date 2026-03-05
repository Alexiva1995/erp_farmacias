<script setup>
import { computed } from 'vue';
const props = defineProps({
  stats: {
    type: Object,
    required: true,
    default: () => ({
      total_debt: 0,
      active_suppliers_count: 0,
      connection_success_rate: 100,
      successful_connections: 0,
      total_connections_24h: 0,
    }),
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(value);
};

const statistics = computed(() => [
  {
    title: 'Deuda Total',
    value: formatCurrency(props.stats.total_debt),
    icon: 'tabler-currency-dollar',
    color: 'error',
    description: 'Pendiente por pagar',
  },
  {
    title: 'Proveedores',
    value: props.stats.active_suppliers_count,
    icon: 'tabler-users',
    color: 'primary',
    description: 'Registrados activos',
  },
  {
    title: 'Éxito Conexión',
    value: `${props.stats.connection_success_rate}%`,
    icon: 'tabler-api',
    color: 'success',
    description: 'Últimas 24 horas',
  },
]);
</script>

<template>
  <VRow>
    <VCol
      v-for="item in statistics"
      :key="item.title"
      cols="12"
      sm="6"
      md="4"
    >
      <VCard>
        <VCardText class="d-flex align-center justify-space-between">
          <div>
            <div class="d-flex align-center gap-2 mb-1">
              <VIcon
                :icon="item.icon"
                :color="item.color"
                size="24"
              />
              <span class="text-overline">{{ item.title }}</span>
            </div>
            <h4 class="text-h4 font-weight-bold mb-1">
              <template v-if="loading">
                <VProgressCircular indeterminate size="20" width="2" />
              </template>
              <template v-else>
                {{ item.value }}
              </template>
            </h4>
            <span class="text-caption text-disabled">{{ item.description }}</span>
          </div>

          <VAvatar
            :color="item.color"
            variant="tonal"
            size="48"
            rounded
          >
            <VIcon
              :icon="item.icon"
              size="28"
            />
          </VAvatar>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
/* Estilos eliminados ya que usamos variant="tonal" de Vuetify */
</style>
