<script setup lang="ts">
import axios from 'axios'
import { onMounted, ref } from 'vue'

const labUnits = ref([])

const fetchAnalytics = async () => {
  try {
    const response = await axios.get('/api/dashboard/analytics-data')
    labUnits.value = response.data.lab_summary_units
  } catch (error) {
    console.error('Error al cargar unidades por laboratorio:', error)
  }
}

onMounted(fetchAnalytics)
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Unidades por Laboratorio</VCardTitle>
      <VCardSubtitle>Ventas en Unidades del Mes</VCardSubtitle>
    </VCardItem>

    <VCardText>
      <VList class="card-list">
        <VListItem
          v-for="lab in labUnits"
          :key="lab.name"
        >
          <template #prepend>
            <VAvatar
              color="info"
              variant="tonal"
              size="34"
              rounded
              class="me-1"
            >
              <VIcon icon="tabler-package" size="22" />
            </VAvatar>
          </template>

          <VListItemTitle class="font-weight-medium me-4">
            {{ lab.name }}
          </VListItemTitle>

          <template #append>
            <div class="d-flex gap-x-4">
              <div class="text-body-1 font-weight-bold">
                {{ lab.units }} uds
              </div>
              <div :class="`d-flex align-center font-weight-medium ${lab.is_positive ? 'text-success' : 'text-error'}`">
                <VIcon
                  :icon="lab.is_positive ? 'tabler-chevron-up' : 'tabler-chevron-down'"
                  size="18"
                  class="me-1"
                />
                {{ Math.abs(lab.change_pct) }}%
              </div>
            </div>
          </template>
        </VListItem>
        
        <VListItem v-if="labUnits.length === 0" class="text-center py-4">
          <span class="text-caption text-disabled">Sin datos de unidades</span>
        </VListItem>
      </VList>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 1.5rem;
}
</style>
