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
  {
    title: "ID",
    key: "id",
    width: "10%",
    cellClass: "font-weight-black text-primary d-none d-sm-table-cell",
    headerClass: "d-none d-sm-table-cell",
  },
  { title: "Nombre", key: "name" },
  { title: "Acción", key: "actions", sortable: false, align: "end" },
];

const updateOptions = (options) => {
  emit("update:options", options);
};
</script>

<template>
  <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
    <!-- Desktop Table -->
    <div class="d-none d-sm-block">
      <VDataTableServer
        :headers="headers"
        :items="groups"
        :items-length="totalGroups"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :page="page"
        item-value="id"
        class="text-no-wrap premium-table"
        @update:options="updateOptions"
      >
        <!-- ID -->
        <template #item.id="{ item }">
          <span class="font-weight-black text-primary">{{ item.id }}</span>
        </template>

        <!-- Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1 justify-end">
            <IconBtn @click="$emit('add-products', item)" color="success">
              <VIcon icon="tabler-plus" />
            </IconBtn>
            <IconBtn @click="$emit('show-group', item)" color="info">
              <VIcon icon="tabler-eye" />
            </IconBtn>
            <IconBtn @click="$emit('edit-group', item)" color="warning">
              <VIcon icon="tabler-edit" />
            </IconBtn>
            <IconBtn @click="$emit('delete-group', item.id)" color="error">
              <VIcon icon="tabler-trash" />
            </IconBtn>
          </div>
        </template>
      </VDataTableServer>
    </div>

    <!-- Mobile Cards -->
    <div class="d-block d-sm-none">
      <div v-if="loading && groups.length === 0" class="pa-5 text-center">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <div class="pa-2">
        <VCard
          v-for="item in groups"
          :key="item.id"
          variant="flat"
          class="group-mobile-card border shadow-sm mb-2 overflow-hidden"
        >
          <div class="pa-3">
            <h3
              class="text-sm font-weight-black text-high-emphasis text-uppercase truncate"
            >
              <span class="text-primary">{{ item.id }}</span>
              <span class="mx-1 text-disabled">|</span>
              {{ item.name }}
            </h3>
          </div>

          <!-- Acciones Rectangulares -->
          <div class="d-flex border-t border-opacity-10">
            <VBtn
              color="success"
              variant="text"
              class="flex-grow-1 rounded-0"
              height="40"
              icon="tabler-plus"
              @click="$emit('add-products', item)"
            />
            <VDivider vertical class="border-opacity-10" />
            <VBtn
              color="info"
              variant="text"
              class="flex-grow-1 rounded-0"
              height="40"
              icon="tabler-eye"
              @click="$emit('show-group', item)"
            />
            <VDivider vertical class="border-opacity-10" />
            <VBtn
              color="warning"
              variant="text"
              class="flex-grow-1 rounded-0"
              height="40"
              icon="tabler-edit"
              @click="$emit('edit-group', item)"
            />
            <VDivider vertical class="border-opacity-10" />
            <VBtn
              color="error"
              variant="text"
              class="flex-grow-1 rounded-0"
              height="40"
              icon="tabler-trash"
              @click="$emit('delete-group', item.id)"
            />
          </div>
        </VCard>

        <!-- Paginación Móvil -->
        <div class="d-flex justify-center mt-4">
          <VPagination
            :model-value="page"
            :length="Math.ceil(totalGroups / itemsPerPage)"
            :total-visible="3"
            density="compact"
            size="small"
            @update:model-value="updateOptions({ page: $event, itemsPerPage })"
          />
        </div>
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.group-mobile-card {
  border-radius: 8px !important;
  background: rgb(var(--v-theme-surface));
}

.premium-table :deep(.v-data-table-header th) {
  background: white !important;
  color: rgba(
    var(--v-theme-on-surface),
    var(--v-high-emphasis-opacity)
  ) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.05rem !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
}

.premium-table :deep(.v-data-table__td) {
  padding-block: 12px !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.03) !important;
}
</style>
