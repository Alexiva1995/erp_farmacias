<script setup>
import { ref } from 'vue';

const isColorDark = (hex) => {
  const r = parseInt(hex.slice(1, 3), 16);
  const g = parseInt(hex.slice(3, 5), 16);
  const b = parseInt(hex.slice(5, 7), 16);
  const luminance = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
  console.log(luminance);
  return luminance < 0.8; // Si la luminancia es baja (oscuro), devuelve true
};

const vehicleData = [
  {
    status: 'Total USD',
    percentage: 39.7,
    icon: 'tabler-currency-dollar',
    barColor: '#D9D9D9', // Gris claro
    rounded: 'rounded-e-0 rounded-lg'
  },
  {
    status: 'Total BS',
    percentage: 28.3,
    icon: 'tabler-cash',
    barColor: '#7F77E3', // Azul/Morado
    rounded: 'rounded-0'
  },
  {
    status: 'Total COP',
    percentage: 17.4,
    icon: 'tabler-coin',
    barColor: '#33CCCC', // Turquesa
    rounded: 'rounded-0'
  },
  {
    status: 'Total Créditos',
    percentage: 14.6,
    icon: 'tabler-credit-card',
    barColor: '#343B42', // Gris oscuro/Negro
    rounded: 'rounded-s-0 rounded-lg'
  },
];

// Añadir 'textColorClass' dinámicamente o sobrescribir si ya existe
vehicleData.forEach(item => {
  item.textColorClass = isColorDark(item.barColor) ? 'text-white' : 'text-black';
});

const menuOptions = [
  { title: 'Ver Detalles', value: 'details' },
  { title: 'Editar', value: 'edit' },
];
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Resumen de Caja</VCardTitle>
      <template #append>
        <VBtn
          icon
          variant="text"
          size="small"
          color="default"
        >
          <VIcon size="24" icon="tabler-dots-vertical" />
          <VMenu activator="parent">
            <VList>
              <VListItem
                v-for="(option, i) in menuOptions"
                :key="i"
                :value="option.value"
              >
                <VListItemTitle>{{ option.title }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </VBtn>
      </template>
    </VCardItem>

    <VCardText>
      <div class="d-flex justify-space-between text-caption text-high-emphasis mb-2">
        <span
          v-for="(item, index) in vehicleData"
          :key="index"
          :style="{ width: item.percentage + '%' }"
          class="text-start"
        >
          {{ item.status.replace('Total ', '') }} </span>
      </div>

      <VProgressLinear
        :model-value="100"
        height="46"
        rounded
        class="mb-6"
      >
        <template #default>
          <div class="d-flex w-100 h-100">
            <div
              v-for="(item, index) in vehicleData"
              :key="index"
              :style="{
                width: item.percentage + '%',
                backgroundColor: item.barColor,
                height: '100%',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
              }"
              :class="[item.rounded]"
            >
              <span class="text-sm font-weight-medium" :class="item.textColorClass"> {{ item.percentage }}% </span>
            </div>
          </div>
        </template>
      </VProgressLinear>

      <div class="vehicle-details-list">
        <div
          v-for="item in vehicleData"
          :key="item.status"
          class="d-flex align-center justify-space-between py-2"
        >
          <div class="d-flex align-center gap-x-2">
            <VIcon :icon="item.icon" :style="{ color: item.barColor }" size="24" />
            <span class="text-body-1 text-high-emphasis">{{ item.status }}</span>
          </div>
          <div class="d-flex align-center gap-x-4">
            <span class="text-body-1">{{ item.percentage }}% </span>
          </div>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>
<style scoped>
.v-progress-linear :deep(.v-progress-linear__background) {
  display: none;
}
.v-progress-linear :deep(.v-progress-linear__determinate) {
  width: 100% !important;
  background-color: transparent !important;
}
.v-progress-linear :deep(.text-white) {
  color: white !important;
}
.v-progress-linear :deep(.text-black) {
  color: black !important;
}

</style>
