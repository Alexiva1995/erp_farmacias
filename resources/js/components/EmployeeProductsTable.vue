<script setup>
import { useDisplay } from "vuetify";

const props = defineProps({
  employeeProducts: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalRecords: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "edit-assignment", "delete-assignment"]);

const { mobile } = useDisplay();

const headers = [
  { title: "ID", key: "employee_id", sortable: true },
  { title: "Empleado", key: "employee_name", sortable: true, width: "25%" },
  { title: "Identificación", key: "identification", sortable: false },
  { title: "Productos Asignados", key: "products", sortable: false, width: "35%", align: "center" },
  { title: "Total", key: "products_count", sortable: true, align: "center" },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

const getInitials = (name) => {
  if (!name) return "N/A";
  return name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .substring(0, 2);
};

const getAvatarColor = (index) => {
  const colors = ["primary", "secondary", "success", "info", "warning", "error"];
  return colors[index % colors.length];
};
</script>

<template>
  <VCard class="border-0 shadow-sm overflow-hidden">
    <!-- Vista de Escritorio -->
    <VDataTableServer
      v-if="!mobile"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.employeeProducts"
      :items-length="props.totalRecords"
      :loading="props.loading"
      class="premium-table text-no-wrap"
      density="compact"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.employee_id="{ item }">
        <span class="text-xs font-weight-black text-disabled">#{{ item.employee_id }}</span>
      </template>

      <template #item.employee_name="{ item }">
        <div class="d-flex align-center gap-3 py-2">
          <VAvatar :color="getAvatarColor(item.employee_id)" size="34" variant="tonal" class="rounded">
            <span class="text-super-xs font-weight-black">{{ getInitials(item.employee_name) }}</span>
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-xs font-weight-black text-high-emphasis leading-tight">
              {{ item.employee_name }}
            </span>
            <span class="text-super-xs text-disabled mt-1 font-weight-bold uppercase">
              {{ item.is_active ? "Activo" : "Inactivo" }}
            </span>
          </div>
        </div>
      </template>

      <template #item.identification="{ item }">
        <span class="text-xs text-medium-emphasis font-weight-black tabular-nums">
          {{ item.identification || "N/A" }}
        </span>
      </template>

      <template #item.products="{ item }">
        <div class="d-flex flex-wrap justify-center gap-1 max-w-ch-40 mx-auto">
          <VChip
            v-for="prod in item.products"
            :key="prod.id"
            size="x-small"
            color="primary"
            variant="tonal"
            class="rounded font-weight-black"
          >
            {{ prod.name }}
          </VChip>
          <span v-if="item.products.length === 0" class="text-super-xs text-disabled">Sin productos</span>
        </div>
      </template>

      <template #item.products_count="{ item }">
        <VChip
          :color="item.products_count > 0 ? 'info' : 'surface-variant'"
          size="x-small"
          variant="flat"
          class="font-weight-black rounded px-3"
          style="color: white !important; min-inline-size: 36px;"
        >
          {{ item.products_count }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex gap-1 justify-center">
          <VTooltip text="Editar Productos" location="top">
            <template #activator="{ props: tp }">
              <VBtn v-bind="tp" icon="tabler-edit" variant="text" color="primary" size="32" class="rounded-lg" @click="emit('edit-assignment', item)" />
            </template>
          </VTooltip>

          <VMenu v-if="item.products.length > 0">
            <template #activator="{ props: menuProps }">
              <VTooltip text="Eliminar Producto" location="top">
                <template #activator="{ props: tp }">
                  <VBtn v-bind="{ ...menuProps, ...tp }" icon="tabler-trash" variant="text" color="error" size="32" class="rounded-lg" />
                </template>
              </VTooltip>
            </template>
            <VList density="compact" class="rounded-lg py-1 border shadow-lg">
              <VListItem
                v-for="prod in item.products"
                :key="prod.id"
                @click="emit('delete-assignment', item.employee_id, prod.id)"
              >
                <template #prepend>
                  <VIcon icon="tabler-trash" size="16" color="error" class="me-2" />
                </template>
                <VListItemTitle class="text-xs font-weight-black">{{ prod.name }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </div>
      </template>

      <template #bottom>
        <VDivider class="opacity-10" />
        <div class="d-flex justify-end pa-2">
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(props.totalRecords / props.itemsPerPage)"
            size="small"
            @update:model-value="(newPage) => emit('update:options', { ...props, page: newPage })"
          />
        </div>
      </template>
    </VDataTableServer>

    <!-- Vista Móvil -->
    <div v-else class="pa-4 bg-light">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-4 rounded" />

      <div v-if="props.employeeProducts.length === 0 && !props.loading" class="text-center pa-10">
        <VIcon icon="tabler-package-off" size="48" class="text-disabled mb-2 opacity-20" />
        <p class="text-disabled text-sm uppercase font-weight-black">No se encontraron empleados</p>
      </div>

      <VRow>
        <VCol v-for="item in props.employeeProducts" :key="item.employee_id" cols="12">
          <VCard class="border-0 shadow-sm overflow-hidden">
            <div class="pa-4">
              <div class="d-flex align-center gap-3 mb-4">
                <VAvatar :color="getAvatarColor(item.employee_id)" size="44" variant="tonal" class="rounded">
                  <span class="text-body-1 font-weight-black">{{ getInitials(item.employee_name) }}</span>
                </VAvatar>
                <div class="d-flex flex-column flex-grow-1">
                  <span class="text-sm font-weight-black text-high-emphasis leading-tight">
                    {{ item.employee_name }}
                  </span>
                  <span class="text-super-xs text-disabled mt-1 font-weight-bold">
                    ID: #{{ item.employee_id }} • {{ item.identification || 'Sin DNI' }}
                  </span>
                </div>
                <div class="d-flex gap-1">
                  <VBtn icon="tabler-edit" variant="tonal" color="primary" size="32" class="rounded" @click="emit('edit-assignment', item)" />
                  <VMenu v-if="item.products.length > 0">
                    <template #activator="{ props: menuProps }">
                      <VBtn v-bind="menuProps" icon="tabler-trash" variant="tonal" color="error" size="32" class="rounded" />
                    </template>
                    <VList density="compact" class="rounded-lg py-1 border shadow-lg">
                      <VListItem
                        v-for="prod in item.products"
                        :key="prod.id"
                        @click="emit('delete-assignment', item.employee_id, prod.id)"
                      >
                        <template #prepend>
                          <VIcon icon="tabler-trash" size="16" color="error" class="me-2" />
                        </template>
                        <VListItemTitle class="text-xs font-weight-black">{{ prod.name }}</VListItemTitle>
                      </VListItem>
                    </VList>
                  </VMenu>
                </div>
              </div>

              <VDivider class="mb-4 opacity-10" />

              <div>
                <div class="text-super-xs font-weight-black text-disabled text-uppercase mb-2 d-flex justify-space-between align-center">
                  <span>Productos Asignados</span>
                  <VChip size="x-small" color="info" variant="flat" class="rounded px-2 font-weight-black">
                    {{ item.products_count }}
                  </VChip>
                </div>
                <div class="d-flex flex-wrap gap-1">
                  <VChip
                    v-for="prod in item.products"
                    :key="prod.id"
                    size="x-small"
                    color="secondary"
                    variant="tonal"
                    label
                    class="rounded font-weight-bold"
                  >
                    {{ prod.name }}
                  </VChip>
                  <div v-if="item.products.length === 0" class="text-super-xs text-disabled italic font-weight-medium">
                    Sin productos asignados
                  </div>
                </div>
              </div>
            </div>
          </VCard>
        </VCol>
      </VRow>

      <div v-if="props.totalRecords > props.itemsPerPage" class="mt-6 d-flex justify-center">
        <VPagination
          :model-value="props.page"
          :length="Math.ceil(props.totalRecords / props.itemsPerPage)"
          size="small"
          @update:model-value="(newPage) => emit('update:options', { ...props, page: newPage })"
        />
      </div>
    </div>
  </VCard>
</template>

<style scoped>
:deep(.premium-table) {
  background: transparent !important;

  thead th {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
    font-size: 0.75rem !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
  }

  tbody tr {
    transition: background-color 0.2s ease;
    &:hover {
      background-color: rgba(var(--v-theme-primary), 0.02) !important;
    }
    td {
      border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.03) !important;
    }
  }
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.max-w-ch-40 {
  max-width: 40ch;
}

.bg-light {
  background-color: rgba(var(--v-theme-on-surface), 0.015);
}

.leading-tight {
  line-height: 1.2;
}
</style>
