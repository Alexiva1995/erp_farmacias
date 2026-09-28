<script setup>
const props = defineProps({
  hallOfFame: { type: Object, default: () => ({}) }
});

const formatNumber = (value) => new Intl.NumberFormat('en-US').format(value || 0);

const getLabel = (key) => {
  if (key === 'employee_of_the_month') return 'Empleado del Mes';
  if (key === 'top_seller') return 'Mejor Vendedor';
  return key.replace(/_/g, ' ');
};
</script>

<template>
  <VRow class="mb-6" dense>
    <VCol cols="12" sm="6" md="3" v-for="(hero, key) in hallOfFame" :key="key">
      <VCard border class="rounded-lg h-100 position-relative overflow-hidden">
        <div class="position-absolute top-0 right-0 pa-2">
          <VIcon
            :icon="key === 'employee_of_the_month' ? 'tabler-crown' : 'tabler-medal'"
            :color="key === 'employee_of_the_month' ? 'warning' : 'primary'"
            size="44"
            style="opacity: 0.12;"
          />
        </div>
        <VCardText class="pa-4 d-flex align-center">
          <VAvatar size="54" class="me-3 border-2 border-primary border-opacity-50">
            <VImg :src="hero?.photo || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(hero?.name || 'User')" />
          </VAvatar>
          <div class="overflow-hidden">
            <p class="text-overline text-medium-emphasis mb-0 font-weight-bold letter-spacing-1 text-truncate">
              {{ getLabel(key) }}
            </p>
            <h4 class="text-subtitle-1 font-weight-bold mb-1 text-truncate">{{ hero?.name }} {{ hero?.last_name }}</h4>
            <VChip size="x-small" color="primary" variant="tonal" class="font-weight-bold">
              {{ formatNumber(hero?.points) }} PTS
            </VChip>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.letter-spacing-1 {
  letter-spacing: 0.5px;
}
</style>
