<script setup>
const props = defineProps({
  histories: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalHistories: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "show-detailHistory"]);

const headers = [
  { title: "Fecha", key: "invoice_date", sortable: true },
  { title: "#", key: "invoice_number", sortable: true },
  { title: "Razón Social", key: "business_name", sortable: true },
  { title: "ID", key: "id", sortable: true },
  { title: "Dirección", key: "address", sortable: true },
  { title: "Exento", key: "exempt_amount", sortable: true },
  { title: "IVA", key: "iva_amount", sortable: true },
  { title: "Total", key: "total_amount", sortable: true },
  { title: "Acción", key: "actions", sortable: false },
];
</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.histories"
      :items-length="props.totalHistories"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.invoice_date="{ item }">
        <span>{{ item.invoice_date }}</span>
      </template>

      <template #item.id="{ item }">
        <span class="font-weight-medium">{{ item.id }}</span>
      </template>

      <template #item.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <VAvatar
            v-if="item.photo_url"
            size="38"
            variant="tonal"
            rounded
            :image="item.photo_url"
          />
          <div class="d-flex flex-column">
            <span
              class="text-body-1 font-weight-medium text-high-emphasis"
              :class="{ 'text-primary': item.psychotropic == 1 }"
            >
              {{ item.name }}

              <span v-if="item.iva == 1"> (G)</span>

              <span v-if="item.is_colombian_origin == 1"> (COL)</span>
            </span>

            <span class="text-sm text-disabled">{{
              item.active_ingredient
            }}</span>
          </div>
        </div>
      </template>

      <template #item.valid_stock="{ item }">
        <span class="font-weight-medium">{{ calculateValidStock(item) }}</span>
      </template>

      <template #item.next_expiration="{ item }">
        <span>{{ nextExpirationDate(item) }}</span>
      </template>

      <template #item.cost_price="{ item }">
        <span class="font-weight-medium">{{ item.unit_cost }}</span>
      </template>

      <template #item.sale_price="{ item }">
        <span class="font-weight-medium">{{ item.sale_price }}</span>
      </template>

      <template #item.actions="{ item }">
        <IconBtn @click="emit('show-detailHistory', item)" color="info">
          <VIcon icon="tabler-eye" />
        </IconBtn>
      </template>
    </VDataTableServer>
  </VCard>
</template>
