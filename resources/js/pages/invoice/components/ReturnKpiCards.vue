<script setup>
defineProps({
  stats: {
    type: Object,
    required: true,
    default: () => ({
      total: 0,
      pending: 0,
      approved: 0,
      rejected: 0,
    }),
  },
  loading: {
    type: Boolean,
    default: false,
  },
})
</script>

<template>
  <v-row class="mb-4">
    <v-col cols="12" sm="6" md="3">
      <v-card class="elevation-2 rounded-lg pa-4 border">
        <div class="d-flex align-center justify-space-between">
          <div>
            <span class="text-caption text-medium-emphasis uppercase font-weight-bold">Total Registros</span>
            <h3 class="text-h5 font-weight-black mt-1">
              <v-progress-circular v-if="loading" indeterminate size="20" width="2" color="primary" />
              <template v-else>{{ stats.total }}</template>
            </h3>
          </div>
          <v-avatar color="primary" variant="tonal" size="44">
            <v-icon icon="tabler-file-invoice" size="24" />
          </v-avatar>
        </div>
      </v-card>
    </v-col>

    <v-col cols="12" sm="6" md="3">
      <v-card class="elevation-2 rounded-lg pa-4 border">
        <div class="d-flex align-center justify-space-between">
          <div>
            <span class="text-caption text-medium-emphasis uppercase font-weight-bold">Pendientes</span>
            <h3 class="text-h5 font-weight-black text-warning mt-1">
              <v-progress-circular v-if="loading" indeterminate size="20" width="2" color="warning" />
              <template v-else>{{ stats.pending }}</template>
            </h3>
          </div>
          <v-avatar color="warning" variant="tonal" size="44">
            <v-icon icon="tabler-clock" size="24" />
          </v-avatar>
        </div>
      </v-card>
    </v-col>

    <v-col cols="12" sm="6" md="3">
      <v-card class="elevation-2 rounded-lg pa-4 border">
        <div class="d-flex align-center justify-space-between">
          <div>
            <span class="text-caption text-medium-emphasis uppercase font-weight-bold">Aprobadas</span>
            <h3 class="text-h5 font-weight-black text-success mt-1">
              <v-progress-circular v-if="loading" indeterminate size="20" width="2" color="success" />
              <template v-else>{{ stats.approved }}</template>
            </h3>
          </div>
          <v-avatar color="success" variant="tonal" size="44">
            <v-icon icon="tabler-circle-check" size="24" />
          </v-avatar>
        </div>
      </v-card>
    </v-col>

    <v-col cols="12" sm="6" md="3">
      <v-card class="elevation-2 rounded-lg pa-4 border">
        <div class="d-flex align-center justify-space-between">
          <div>
            <span class="text-caption text-medium-emphasis uppercase font-weight-bold">Rechazadas</span>
            <h3 class="text-h5 font-weight-black text-error mt-1">
              <v-progress-circular v-if="loading" indeterminate size="20" width="2" color="error" />
              <template v-else>{{ stats.rejected }}</template>
            </h3>
          </div>
          <v-avatar color="error" variant="tonal" size="44">
            <v-icon icon="tabler-circle-x" size="24" />
          </v-avatar>
        </div>
      </v-card>
    </v-col>
  </v-row>
</template>
