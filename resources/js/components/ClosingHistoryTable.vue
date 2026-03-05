<script setup>
const props = defineProps({
  closing: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalClosing: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});
const emit = defineEmits(["update:options","print-cash"]);
const headers = [
  { title: "id", key: "id", sortable: true},
  { title: "Fecha", key: "date", sortable: true, maxWidth: '55px'},
  { title: 'Acción', key: 'actions', sortable: false, maxWidth: '95px'},
];

const date = (order) => {
  const time = new Date(order);
  return time.toISOString().split("T")[0];
};

</script>

<template>
<VCard>
<VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.closing"
      :items-length="props.totalClosing"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
       <template #item.date="{ item }">
        <span>{{ date(item.closing_date) }}</span>
      </template>
        <template #item.actions="{ item }">
        <div class="d-flex align-center gap-2">
          <IconBtn
            @click="emit('print-cash', item)">
            <VIcon icon="tabler-printer" />
          </IconBtn>
        </div>
      </template>
</VDataTableServer>
  </VCard>
</template>
