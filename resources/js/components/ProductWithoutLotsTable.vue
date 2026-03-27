<script setup>
const props = defineProps({
  lots: { type: Array, required: true },
  totalLots: { type: Number, required: true },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:options", "edit-lot", "delete-lot"]);

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Producto", key: "product.name", sortable: true },
  {
    title: "Stock Product",
    key: "product.stock",

    sortable: false,
  },
  {
    title: "Cantidad en Lote",
    key: "quantity",

    sortable: true,
  },
  { title: "Expira", key: "expiration_date", sortable: true },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];
</script>

<template>
  <VCard title="Listado de Lotes">
    <VDataTableServer
      :headers="headers"
      :items="props.lots"
      :items-length="props.totalLots"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.id="{ item }">
        <a
          :href="'/inventory/traceability?q=' + item.product?.id"
          target="_blank"
          class="text-decoration-none font-weight-black text-primary"
        >
          {{ item.id }}
        </a>
      </template>
      <template #item.product.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <VAvatar
            v-if="item.product.photo_url"
            size="38"
            variant="tonal"
            rounded
            :image="item.product.photo_url"
          />
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">{{
              item.product.name
            }}</span>
            <span class="text-sm text-disabled">{{
              item.product.active_ingredient
            }}</span>
          </div>
        </div>
      </template>

      <template #item.product.stock="{ item }">
        <VChip
          size="small"
          :color="item.product.stock > 0 ? 'success' : 'error'"
        >
          {{ item.product.stock }}
        </VChip>
      </template>

      <template #item.quantity="{ item }">
        <span class="font-weight-medium">{{ item.quantity }}</span>
      </template>

      <template #item.expiration_date="{ item }">
        <span>{{ new Date(item.expiration_date).toLocaleDateString() }}</span>
      </template>

      <template #item.actions="{ item }">
        <IconBtn @click="emit('edit-lot', item)">
          <VIcon icon="tabler-edit" />
        </IconBtn>
        <IconBtn color="error" @click="emit('delete-lot', item)">
          <VIcon icon="tabler-trash" />
        </IconBtn>
      </template>
    </VDataTableServer>
  </VCard>
</template>
