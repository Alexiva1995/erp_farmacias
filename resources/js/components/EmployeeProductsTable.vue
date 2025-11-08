<script setup>
const props = defineProps({
  employeeProducts: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalRecords: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits([
  "update:options",
  "view-products",
  "edit-assignment",
  "delete-assignment",
]);

const headers = [
  { title: "ID Empleado", key: "employee_id", sortable: true },
  { title: "Empleado", key: "employee_name", sortable: true, width: "35%" },
  {
    title: "Identificación",
    key: "identification",
    sortable: false,
    width: "20%",
  },
  { title: "Total Productos", key: "products_count", sortable: true },
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
  const colors = [
    "primary",
    "secondary",
    "success",
    "info",
    "warning",
    "error",
  ];
  return colors[index % colors.length];
};
</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.employeeProducts"
      :items-length="props.totalRecords"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.employee_id="{ item }">
        <span class="font-weight-medium">#{{ item.employee_id }}</span>
      </template>

      <template #item.employee_name="{ item }">
        <div class="d-flex align-center gap-3">
          <VAvatar
            :color="getAvatarColor(item.employee_id)"
            size="38"
            variant="tonal"
          >
            <span class="text-sm">{{ getInitials(item.employee_name) }}</span>
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">
              {{ item.employee_name }}
            </span>
            <span class="text-xs text-disabled">
              {{ item.is_active ? "Activo" : "Inactivo" }}
            </span>
          </div>
        </div>
      </template>

      <template #item.identification="{ item }">
        <span class="text-sm text-medium-emphasis">
          {{ item.identification || "N/A" }}
        </span>
      </template>

      <template #item.products_count="{ item }">
        <VChip
          :color="item.products_count > 0 ? 'success' : 'default'"
          size="small"
          variant="tonal"
        >
          {{ item.products_count }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <IconBtn @click="emit('view-products', item)">
            <VIcon icon="tabler-eye" />
            <VTooltip activator="parent" location="top">
              Ver Productos
            </VTooltip>
          </IconBtn>

          <IconBtn @click="emit('edit-assignment', item)">
            <VIcon icon="tabler-edit" />
            <VTooltip activator="parent" location="top">
              Editar Productos
            </VTooltip>
          </IconBtn>

          <VMenu>
            <template #activator="{ props: menuProps }">
              <IconBtn
                v-bind="menuProps"
                :disabled="item.products.length === 0"
              >
                <VIcon icon="tabler-trash" />
                <VTooltip activator="parent" location="top">
                  Eliminar Producto
                </VTooltip>
              </IconBtn>
            </template>
            <VList>
              <VListItem
                v-for="prod in item.products"
                :key="prod.id"
                @click="emit('delete-assignment', item.employee_id, prod.id)"
              >
                <template #prepend>
                  <VIcon icon="tabler-trash" size="20" class="me-2" />
                </template>
                <VListItemTitle>Eliminar {{ prod.name }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </div>
      </template>

      <template #bottom>
        <VDivider />
        <div class="d-flex justify-end pa-2">
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(props.totalRecords / props.itemsPerPage)"
            @update:model-value="
              (newPage) => emit('update:options', { ...props, page: newPage })
            "
          />
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
