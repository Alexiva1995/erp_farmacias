<script setup>
const props = defineProps({
  company: {
    type: Object,
    default: () => ({}),
  },
  totalClients: {
    type: Number,
    default: 0,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});
</script>

<template>
  <VCard class="mb-4 border shadow-sm overflow-hidden position-relative">
    <VSkeletonLoader v-if="props.loading" type="article" class="pa-4" />

    <div v-else class="pa-5 relative-content">
      <div class="card-bg-decoration"></div>
      
      <div class="d-flex flex-column flex-md-row align-start align-md-center justify-space-between gap-4">
        <!-- Avatar y Nombre -->
        <div class="d-flex align-center gap-4">
          <VAvatar
            size="64"
            color="primary"
            variant="tonal"
            class="rounded-lg border shadow-xs"
          >
            <VIcon icon="tabler-building-factory-2" size="36" />
          </VAvatar>

          <div class="d-flex flex-column">
            <div class="d-flex align-center gap-2 mb-1">
              <span class="text-h5 font-weight-black text-high-emphasis text-uppercase leading-tight">
                {{ props.company.name || 'Cargando empresa...' }}
              </span>
              <VChip
                :color="props.company.type_company === 'Clínica' ? 'info' : 'primary'"
                variant="tonal"
                size="x-small"
                class="font-weight-black text-uppercase rounded"
              >
                {{ props.company.type_company || 'Empresa' }}
              </VChip>
            </div>

            <div class="d-flex flex-wrap align-center gap-x-4 gap-y-1 text-caption text-medium-emphasis">
              <div class="d-flex align-center gap-1">
                <VIcon icon="tabler-id" size="16" class="text-disabled" />
                <span class="font-weight-bold">RIF / ID:</span>
                <span class="font-weight-black text-high-emphasis">{{ props.company.identification || 'N/A' }}</span>
              </div>
              
              <div class="d-flex align-center gap-1">
                <VIcon icon="tabler-map-pin" size="16" class="text-disabled" />
                <span class="font-weight-bold">Dirección:</span>
                <span class="font-weight-medium text-high-emphasis truncate-address">
                  {{ props.company.address || 'Sin dirección registrada' }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Estadísticas Rápidas -->
        <div class="d-flex align-center gap-3 w-100 w-md-auto justify-space-between justify-md-end">
          <div class="pa-3 px-4 rounded-lg border bg-surface text-center min-w-120">
            <span class="text-super-xs font-weight-black text-disabled text-uppercase d-block mb-1">
              CLIENTES ASOCIADOS
            </span>
            <div class="d-flex align-center justify-center gap-1">
              <VIcon icon="tabler-users" size="20" color="primary" />
              <span class="text-h5 font-weight-black text-primary">
                {{ props.totalClients }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.card-bg-decoration {
  position: absolute;
  z-index: 0;
  border-radius: 50%;
  block-size: 100px;
  filter: blur(40px);
  inline-size: 100px;
  inset-block-start: -20px;
  inset-inline-end: -20px;
  background: linear-gradient(45deg, rgba(var(--v-theme-primary), 0.12), transparent);
  pointer-events: none;
}

.relative-content {
  position: relative;
  z-index: 1;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: normal;
}

.min-w-120 {
  min-width: 120px;
}

.truncate-address {
  max-width: 320px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
