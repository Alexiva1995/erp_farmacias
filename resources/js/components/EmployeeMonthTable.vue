<script setup>
const props = defineProps({
  items: {
    type: Array,
    required: true,
  },
});

const headers = [
  { title: "Empl.", key: "name", width: "200px" },
  { title: "Tota.", key: "scores.total", align: "end", sortable: true },
  { title: "Vent. 25%", key: "scores.sales", align: "end" },
  { title: "Crec. 15%", key: "scores.growth", align: "end" },
  { title: "Venc. 15%", key: "scores.expiration", align: "end" },
  { title: "Inv. 10%", key: "scores.inventory", align: "end" },
  { title: "Prem. 10%", key: "scores.premium", align: "end" },
  { title: "Fact. 15%", key: "scores.invoice", align: "end" },
  { title: "Limp. 5%", key: "scores.cleaning", align: "end" },
  { title: "Estr. 5%", key: "scores.strategy", align: "end" },
];

const formatNumber = (num) =>
  new Intl.NumberFormat("es-VE", { maximumFractionDigits: 2 }).format(num);

const formatCurrency = (amount) =>
  new Intl.NumberFormat("es-US", {
    style: "currency",
    currency: "USD",
  }).format(amount);
</script>

<template>
  <VCard>
    <VDataTable
      :headers="headers"
      :items="props.items"
      item-value="id"
      class="text-no-wrap"
    >
      <template #item.name="{ item }">
        <div class="d-flex align-center gap-2">
          <VAvatar color="primary" variant="tonal" size="32">
            {{ item.name.charAt(0) }}{{ item.last_name.charAt(0) }}
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="font-weight-medium"
              >{{ item.name }} {{ item.last_name }}</span
            >
            <span class="text-caption text-disabled">ID: {{ item.id }}</span>
          </div>
          <VIcon
            v-if="props.items.indexOf(item) === 0"
            color="warning"
            icon="tabler-trophy"
            size="20"
            class="ms-2"
          />
        </div>
      </template>

      <template #item.scores.total="{ item }">
        <VChip
          :color="props.items.indexOf(item) === 0 ? 'warning' : 'primary'"
          variant="elevated"
          class="font-weight-bold"
        >
          {{ formatNumber(item.scores.total) }}
        </VChip>
      </template>

      <template #item.scores.sales="{ item }">
        <div class="text-end">
          <div class="font-weight-bold">
            {{ formatNumber(item.scores.sales) }}
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ formatCurrency(item.sales) }}
          </div>
        </div>
      </template>

      <template #item.scores.growth="{ item }">
        <div class="text-end">
          <div class="font-weight-bold">
            {{ formatNumber(item.scores.growth) }}
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ item.growth }}%
          </div>
        </div>
      </template>

      <template #item.scores.expiration="{ item }">
        <div class="text-end">
          <div class="font-weight-bold">
            {{ formatNumber(item.scores.expiration) }}
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ item.expirations }} unid.
          </div>
        </div>
      </template>

      <template #item.scores.inventory="{ item }">
        <div class="text-end">
          <div class="font-weight-bold">
            {{ formatNumber(item.scores.inventory) }}
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ item.inventory_counted }} ({{ item.inventory_errors }} err)
          </div>
        </div>
      </template>

      <template #item.scores.premium="{ item }">
        <div class="text-end">
          <div class="font-weight-bold">
            {{ formatNumber(item.scores.premium) }}
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ item.premium_products }} unid.
          </div>
        </div>
      </template>

      <template #item.scores.invoice="{ item }">
        <div class="text-end">
          <div class="font-weight-bold">
            {{ formatNumber(item.scores.invoice) }}
          </div>
          <VTooltip location="top">
            <template #activator="{ props }">
              <VIcon
                v-bind="props"
                size="16"
                icon="tabler-info-circle"
                class="text-disabled"
              />
            </template>
            <span>
              Productos: {{ item.invoice_items }} <br />
              Registradas: {{ item.invoice_headers }} <br />
              Ordenadas: {{ item.invoice_archived }}
            </span>
          </VTooltip>
        </div>
      </template>

      <template #item.scores.cleaning="{ item }">
        <div class="text-end">
          <div class="font-weight-bold">
            {{ formatNumber(item.scores.cleaning) }}
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ item.cleaning_completed }} tareas
          </div>
        </div>
      </template>

      <template #item.scores.strategy="{ item }">
        <div class="text-end">
          <div class="font-weight-bold">
            {{ formatNumber(item.scores.strategy) }}
          </div>
          <div class="text-caption text-medium-emphasis">
            {{ item.strategy_sales }} unid.
          </div>
        </div>
      </template>
    </VDataTable>
  </VCard>
</template>
