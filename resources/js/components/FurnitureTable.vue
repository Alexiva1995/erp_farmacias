<script setup>
const props = defineProps({
  furniture: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalFurniture: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits([
  "update:options",
  "edit-furniture",
  "delete-furniture",
]);

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Nombre", key: "name", sortable: true, width: "35%" },
  { title: "Año Adquisición", key: "acquisition_year", sortable: true },
  { title: "Costo Original", key: "cost", sortable: true },
  { title: "Depreciación", key: "annual_depreciation_rate", sortable: true },
  { title: "Valor Actual", key: "current_value", sortable: true },
  { title: "Estado", key: "status", sortable: false },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

const formatPrice = (price) => {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(price);
};

const calculateCurrentValue = (item) => {
  const currentYear = new Date().getFullYear();
  const yearsDepreciated = Math.max(0, currentYear - item.acquisition_year);
  const totalDepreciation =
    (item.annual_depreciation_rate / 100) * yearsDepreciated;
  const depreciationFactor = Math.max(0, 1 - totalDepreciation);
  console.log(depreciationFactor);

  return item.cost * depreciationFactor;
};

const getDepreciationStatus = (item) => {
  const currentValue = calculateCurrentValue(item);
  const depreciationPercentage = ((item.cost - currentValue) / item.cost) * 100;

  if (depreciationPercentage <= 20) {
    return { text: "Excelente", color: "success" };
  } else if (depreciationPercentage <= 50) {
    return { text: "Bueno", color: "info" };
  } else if (depreciationPercentage <= 80) {
    return { text: "Regular", color: "warning" };
  } else {
    return { text: "Depreciado", color: "error" };
  }
};

const getAgeStatus = (acquisitionYear) => {
  const currentYear = new Date().getFullYear();
  const age = currentYear - acquisitionYear;

  if (age <= 2) {
    return { text: "Nuevo", color: "success" };
  } else if (age <= 5) {
    return { text: "Reciente", color: "info" };
  } else if (age <= 10) {
    return { text: "Usado", color: "warning" };
  } else {
    return { text: "Antiguo", color: "error" };
  }
};

const formatDepreciation = (rate) => {
  return `${rate}% anual`;
};
</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.furniture"
      :items-length="props.totalFurniture"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.id="{ item }">
        <span class="font-weight-medium">#{{ item.id }}</span>
      </template>

      <template #item.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">
              {{ item.name }}
            </span>
            <span class="text-sm text-disabled">
              Adquirido en {{ item.acquisition_year }}
            </span>
          </div>
        </div>
      </template>

      <template #item.acquisition_year="{ item }">
        <div class="d-flex flex-column align-center">
          <span class="font-weight-medium">{{ item.acquisition_year }}</span>
          <VChip
            :color="getAgeStatus(item.acquisition_year).color"
            variant="tonal"
            size="x-small"
            class="mt-1"
          >
            {{ getAgeStatus(item.acquisition_year).text }}
          </VChip>
        </div>
      </template>

      <template #item.cost="{ item }">
        <span class="font-weight-medium">{{ formatPrice(item.cost) }}</span>
      </template>

      <template #item.annual_depreciation_rate="{ item }">
        <div class="d-flex align-center gap-2">
          <VIcon
            :icon="
              item.annual_depreciation_rate > 15
                ? 'tabler-trending-down'
                : 'tabler-trending-up'
            "
            :color="item.annual_depreciation_rate > 15 ? 'error' : 'success'"
            size="16"
          />
          <span>{{ formatDepreciation(item.annual_depreciation_rate) }}</span>
        </div>
      </template>

      <template #item.current_value="{ item }">
        <div class="d-flex flex-column">
          <span class="font-weight-medium">{{
            formatPrice(calculateCurrentValue(item))
          }}</span>
          <span class="text-xs text-disabled">
            {{ ((calculateCurrentValue(item) / item.cost) * 100).toFixed(1) }}%
            del valor original
          </span>
        </div>
      </template>

      <template #item.status="{ item }">
        <VChip
          :color="getDepreciationStatus(item).color"
          variant="tonal"
          size="small"
        >
          {{ getDepreciationStatus(item).text }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <IconBtn @click="emit('edit-furniture', item)">
            <VIcon icon="tabler-edit" />
            <VTooltip activator="parent" location="top">
              Editar mobiliario
            </VTooltip>
          </IconBtn>

          <IconBtn @click="emit('delete-furniture', item.id)">
            <VIcon icon="tabler-trash" />
            <VTooltip activator="parent" location="top">
              Eliminar mobiliario
            </VTooltip>
          </IconBtn>
        </div>
      </template>

      <!-- Loading state -->
      <template #loading>
        <VSkeletonLoader type="table-row@10" />
      </template>

      <!-- No data state -->
      <template #no-data>
        <div class="text-center pa-4">
          <VIcon icon="tabler-sofa-off" size="48" class="mb-2 text-disabled" />
          <div class="text-body-1 font-weight-medium mb-1">
            No hay mobiliario
          </div>
          <div class="text-body-2 text-disabled">
            No se encontró mobiliario con los filtros aplicados
          </div>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
