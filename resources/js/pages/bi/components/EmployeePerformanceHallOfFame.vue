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
      <VCard border class="rounded-lg shadow-sm h-100 bg-surface position-relative overflow-hidden">
        <div class="position-absolute top-0 right-0 pa-2">
          <VIcon
            :icon="key === 'employee_of_the_month' ? 'tabler-crown' : 'tabler-medal'"
            :color="key === 'employee_of_the_month' ? 'warning' : 'primary'"
            size="40"
            class="opacity-10"
          />
        </div>
        <VCardText class="pa-4 d-flex align-center">
          <VAvatar size="60" class="me-4 border-2 border-primary border-opacity-50">
            <VImg :src="hero?.photo || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(hero?.name || 'User')" />
          </VAvatar>
          <div>
            <p class="text-[10px] text-disabled mb-0 font-weight-bold uppercase">
              {{ getLabel(key) }}
            </p>
            <h4 class="text-subtitle-1 font-weight-black mb-0">{{ hero?.name }} {{ hero?.last_name }}</h4>
            <VChip size="x-small" color="primary" class="font-weight-black mt-1">
              {{ formatNumber(hero?.points) }} PTS
            </VChip>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.bg-surface { background-color: #fff !important; }
.font-weight-black { font-weight: 900 !important; }
.uppercase { text-transform: uppercase; letter-spacing: 0.5px; }
</style>
