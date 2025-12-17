<script setup>
defineProps({
  groups: { type: Array, required: true },
  loading: Boolean,
  totalGroups: { type: Number, default: 0 },
  itemsPerPage: { type: Number, default: 10 },
  page: { type: Number, default: 1 },
});

const emit = defineEmits([
  "update:options",
  "edit-group",
  "delete-group",
  "show-group",
  "add-products",
]);

const headers = [
  { title: "ID", key: "id", width: "10%" },
  { title: "Nombre", key: "name" },
  { title: "Acción", key: "actions", sortable: false, align: "end" },
];

const updateOptions = (options) => {
  emit("update:options", options);
};
</script>

<template>
  <VCard>
    <VDataTableServer
      :headers="headers"
      :items="groups"
      :items-length="totalGroups"
      :loading="loading"
      :items-per-page="itemsPerPage"
      :page="page"
      item-value="id"
      class="text-no-wrap"
      @update:options="updateOptions"
    >
      <!-- Acciones -->
      <template #item.actions="{ item }">
        <div class="d-flex gap-1 justify-end">
          <IconBtn @click="$emit('add-products', item)">
            <VIcon icon="tabler-plus" />
          </IconBtn>
          <IconBtn @click="$emit('show-group', item)">
            <VIcon icon="tabler-eye" />
          </IconBtn>
          <IconBtn @click="$emit('edit-group', item)">
            <VIcon icon="tabler-pencil" />
          </IconBtn>
          <IconBtn @click="$emit('delete-group', item.id)">
            <VIcon icon="tabler-trash" />
          </IconBtn>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
