<script setup>
import { computed } from "vue";

const props = defineProps({
  modelValue: {
    type: Array,
    required: true,
  },
  lots: {
    type: Array,
    required: true,
  },
  loading: {
    type: Boolean,
    required: true,
  },
  totalLots: {
    type: Number,
    required: true,
  },
  itemsPerPage: {
    type: Number,
    required: true,
  },
  page: {
    type: Number,
    required: true,
  },
});

const emit = defineEmits([
  "update:modelValue",
  "update:options",
  "expire-lot",
]);

const headers = [
  { title: "ID", key: "product.id", sortable: true },
  { title: "Producto", key: "product.name", sortable: true },
  { 
    title: "Laboratorio", 
    key: "laboratory_name", 
    sortable: true,
    value: (item) => item.product?.laboratory?.name || "—"
  },
  { title: "Nº Lote", key: "lot_number", sortable: false },
  { title: "Exp.", key: "expiration_date", sortable: true },
  { title: "Stock", key: "quantity", sortable: true },
  { title: "Acciones", key: "actions", sortable: false },
];

const formatDate = (dateString) => {
  const options = { year: "numeric", month: "2-digit", day: "2-digit" };
  return new Date(dateString).toLocaleDateString("es-ES", options);
};

const selected = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});
</script>

<template>
  <VCard>
    <VDataTableServer
      v-model="selected"
      :show-select="true"
      item-value="id"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.lots"
      :items-length="props.totalLots"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.product.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <VAvatar
            v-if="item.product?.photo_url"
            size="38"
            variant="tonal"
            rounded
            :image="item.product.photo_url"
          />
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">{{
              item.product?.name?.toUpperCase() || ""
            }}</span>
            <span class="text-sm text-disabled">{{
              item.product?.active_ingredient || ""
            }}</span>
          </div>
        </div>
      </template>

      <template #item.laboratory_name="{ item }">
        <span>{{ item.product?.laboratory?.name?.toUpperCase() || "—" }}</span>
      </template>

      <template #item.lot_number="{ item }">
        <span class="font-weight-medium">{{ item.lot_number }}</span>
      </template>

      <template #item.expiration_date="{ item }">
        <span class="font-weight-medium">{{
          formatDate(item.expiration_date)
        }}</span>
      </template>

      <template #item.quantity="{ item }">
        <span class="font-weight-medium">{{ item.quantity }}</span>
      </template>

      <template #item.actions="{ item }">
        <VTooltip location="top" text="Marcar como Caducado">
          <template #activator="{ props: tooltipProps }">
            <IconBtn
              v-bind="tooltipProps"
              color="error"
              @click="emit('expire-lot', item)"
            >
              <VIcon icon="tabler-calendar-off" />
            </IconBtn>
          </template>
        </VTooltip>
      </template>
    </VDataTableServer>
  </VCard>
</template>
