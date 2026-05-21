<script setup lang="ts">
import axios from 'axios'
import { onMounted, ref, computed } from 'vue'

const expirationsSummary = ref([])

const fetchExpirations = async () => {
  try {
    const response = await axios.get('/api/dashboard/analytics-data')
    expirationsSummary.value = response.data.expirations_summary || []
  } catch (error) {
    console.error('Error al cargar resumen de caducidad:', error)
  }
}

const totalExpirations = computed(() => {
  return expirationsSummary.value.reduce((acc, curr) => acc + curr.count, 0)
})

const expirationsData = computed(() => {
  const icons = ['tabler-calendar-event', 'tabler-calendar-stats', 'tabler-calendar-search', 'tabler-calendar-cancel']
  const colors = ['error', 'warning', 'info', 'primary']

  return expirationsSummary.value.map((item, index) => {
    const percentage = totalExpirations.value > 0 ? (item.count / totalExpirations.value) * 100 : 0
    return {
      icon: icons[index] || 'tabler-calendar',
      color: colors[index] || 'secondary',
      title: item.month.charAt(0).toUpperCase() + item.month.slice(1),
      count: item.count,
      percentage: Math.round(percentage * 10) / 10,
    }
  })
})

onMounted(fetchExpirations)
</script>

<template>
  <VCard>
    <VCardItem title="Resumen de Caducidad">
      <VCardSubtitle>Lotes venciendo en los próximos 4 meses</VCardSubtitle>
    </VCardItem>

    <VCardText>
      <div class="d-flex mb-6 h-auto overflow-hidden rounded-lg border">
        <div 
          v-for="(item, index) in expirationsData" 
          :key="index"
          :style="{ inlineSize: `${item.percentage}%`, minWidth: item.percentage > 0 ? '40px' : '0' }"
          class="position-relative"
        >
          <VProgressLinear
            :color="index === 0 ? 'error' : (index === 1 ? 'warning' : (index === 2 ? 'info' : 'primary'))"
            model-value="100"
            height="46"
            class="rounded-0"
          >
            <div class="text-white text-xs font-weight-bold text-center w-100">
              {{ item.percentage }}%
            </div>
          </VProgressLinear>
        </div>
      </div>

      <VTable class="text-no-wrap">
        <tbody>
          <tr
            v-for="(item, index) in expirationsData"
            :key="index"
          >
            <td
              width="70%"
              style="padding-inline-start: 0 !important;"
            >
              <div class="d-flex align-center gap-x-2">
                <VAvatar
                  variant="tonal"
                  :color="item.color"
                  size="30"
                  rounded
                >
                  <VIcon
                    :icon="item.icon"
                    size="18"
                  />
                </VAvatar>
                <div class="text-body-1 text-high-emphasis font-weight-medium">
                  {{ item.title }}
                </div>
              </div>
            </td>
            <td>
              <h6 class="text-h6">
                {{ item.count }} unidades
              </h6>
            </td>
            <td class="text-end">
              <div class="text-body-1">
                {{ item.percentage }}%
              </div>
            </td>
          </tr>
        </tbody>
      </VTable>
      
      <div v-if="totalExpirations === 0" class="text-center py-4 text-disabled">
        No hay lotes con fecha de caducidad cercana
      </div>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.vehicle-progress-label {
  padding-block-end: 1rem;

  &::after {
    position: absolute;
    display: inline-block;
    background-color: rgba(var(--v-theme-on-surface), var(--v-border-opacity));
    block-size: 10px;
    content: "";
    inline-size: 2px;
    inset-block-end: 0;
    inset-inline-start: 0;

    [dir="rtl"] & {
      inset-inline: unset 0;
    }
  }
}
</style>
