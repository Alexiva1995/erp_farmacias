<script setup>
import { useDisplay } from "vuetify";

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

const { mobile } = useDisplay();

const headers = [
  { title: "ID", key: "employee_id", sortable: true, class: "font-weight-black text-super-xs" },
  { title: "EMPLEADO", key: "employee_name", sortable: true, width: "35%", class: "font-weight-black text-super-xs" },
  { title: "IDENTIFICACIÓN", key: "identification", sortable: false, width: "20%", class: "font-weight-black text-super-xs" },
  { title: "PRODUCTOS", key: "products_count", sortable: true, align: "center", class: "font-weight-black text-super-xs text-center" },
  { title: "ACCIONES", key: "actions", sortable: false, align: "end", class: "font-weight-black text-super-xs" },
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
  <VCard class="rounded-xl border-0 shadow-sm overflow-hidden">
    <!-- Vista Escritorio: Tabla Premium -->
    <VDataTableServer
      v-if="!mobile"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.employeeProducts"
      :items-length="props.totalRecords"
      :loading="props.loading"
      class="premium-table text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.employee_id="{ item }">
        <span class="text-xs font-weight-black text-disabled tabular-nums">#{{ item.employee_id }}</span>
      </template>

      <template #item.employee_name="{ item }">
        <div class="d-flex align-center gap-3 py-2">
          <VAvatar
            :color="getAvatarColor(item.employee_id)"
            size="34"
            variant="tonal"
            class="rounded font-weight-black text-super-xs"
          >
            {{ getInitials(item.employee_name) }}
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-xs font-weight-black text-high-emphasis leading-tight">{{ item.employee_name }}</span>
            <span class="text-super-xs text-disabled mt-1">{{ item.is_active ? "Activo" : "Inactivo" }}</span>
          </div>
        </div>
      </template>

      <template #item.identification="{ item }">
        <span class="text-xs text-medium-emphasis tabular-nums">{{ item.identification || "N/A" }}</span>
      </template>

      <template #item.products_count="{ item }">
        <div class="d-flex justify-center">
          <VChip
            :color="item.products_count > 0 ? 'success' : 'default'"
            size="small"
            class="font-weight-black rounded px-3"
            variant="flat"
            style="min-inline-size: 40px;"
          >
            {{ item.products_count }}
          </VChip>
        </div>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex justify-end gap-1">
          <VTooltip text="Ver Productos" location="top">
            <template #activator="{ props: tp }">
              <VBtn v-bind="tp" icon="tabler-eye" variant="text" color="info" size="32" @click="emit('view-products', item)" />
            </template>
          </VTooltip>
          <VTooltip text="Editar Asignación" location="top">
            <template #activator="{ props: tp }">
              <VBtn v-bind="tp" icon="tabler-edit" variant="text" color="warning" size="32" @click="emit('edit-assignment', item)" />
            </template>
          </VTooltip>
          <VMenu>
            <template #activator="{ props: menuProps }">
              <VTooltip text="Eliminar Producto" location="top">
                <template #activator="{ props: tp }">
                  <VBtn
                    v-bind="{ ...menuProps, ...tp }"
                    icon="tabler-trash"
                    variant="text"
                    color="error"
                    size="32"
                    :disabled="item.products.length === 0"
                  />
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
                <VListItemTitle class="text-xs font-weight-bold">{{ prod.name }}</VListItemTitle>
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
            @update:model-value="(newPage) => emit('update:options', { ...props, page: newPage })"
          />
        </div>
      </template>
    </VDataTableServer>

    <!-- Vista Móvil: Cards Premium -->
    <div v-else class="pa-4 bg-light">
      <VRow>
        <VCol v-for="item in props.employeeProducts" :key="item.employee_id" cols="12">
          <VCard class="rounded-xl border shadow-sm overflow-hidden">
            <!-- Header tarjeta -->
            <div class="pa-4 d-flex align-center gap-3 border-b bg-surface">
              <VAvatar
                :color="getAvatarColor(item.employee_id)"
                size="44"
                variant="tonal"
                class="rounded font-weight-black"
              >
                {{ getInitials(item.employee_name) }}
              </VAvatar>
              <div class="flex-grow-1">
                <span class="text-sm font-weight-black text-high-emphasis d-block leading-tight">{{ item.employee_name }}</span>
                <span class="text-super-xs text-disabled uppercase font-weight-bold">ID: #{{ item.employee_id }}</span>
              </div>
              <div class="d-flex flex-column align-end gap-1">
                <VChip
                  :color="item.products_count > 0 ? 'success' : 'default'"
                  size="x-small"
                  class="font-weight-black rounded"
                  variant="flat"
                >
                  {{ item.products_count }} productos
                </VChip>
                <VChip
                  :color="item.is_active ? 'success' : 'error'"
                  size="x-small"
                  variant="tonal"
                  class="font-weight-black rounded"
                >
                  {{ item.is_active ? "ACTIVO" : "INACTIVO" }}
                </VChip>
              </div>
            </div>

            <!-- Info extra -->
            <VCardText class="pa-4 pb-0">
              <div class="d-flex align-center gap-2 mb-3">
                <VIcon icon="tabler-id" size="16" color="disabled" />
                <span class="text-xs text-medium-emphasis">{{ item.identification || "Sin identificación" }}</span>
              </div>

              <!-- Chips de productos -->
              <div v-if="item.products && item.products.length > 0" class="mb-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-2">Productos Asignados</span>
                <div class="d-flex flex-wrap gap-1">
                  <VChip
                    v-for="prod in item.products.slice(0, 3)"
                    :key="prod.id"
                    size="x-small"
                    variant="tonal"
                    color="primary"
                    class="rounded font-weight-bold"
                  >
                    {{ prod.name }}
                  </VChip>
                  <VChip
                    v-if="item.products.length > 3"
                    size="x-small"
                    variant="tonal"
                    color="secondary"
                    class="rounded font-weight-bold"
                  >
                    +{{ item.products.length - 3 }} más
                  </VChip>
                </div>
              </div>
            </VCardText>

            <VCardText class="pa-4 pt-2">
              <VDivider class="border-dashed mb-3" />
              <div class="d-flex gap-2">
                <VBtn
                  color="info"
                  variant="tonal"
                  size="small"
                  class="rounded-lg flex-grow-1 font-weight-black"
                  @click="emit('view-products', item)"
                >
                  <VIcon start icon="tabler-eye" size="16" />
                  VER
                </VBtn>
                <VBtn
                  color="warning"
                  variant="tonal"
                  size="small"
                  class="rounded-lg flex-grow-1 font-weight-black"
                  @click="emit('edit-assignment', item)"
                >
                  <VIcon start icon="tabler-edit" size="16" />
                  EDITAR
                </VBtn>
                <VMenu v-if="item.products && item.products.length > 0">
                  <template #activator="{ props: menuProps }">
                    <VBtn
                      v-bind="menuProps"
                      color="error"
                      variant="tonal"
                      size="small"
                      class="rounded-lg font-weight-black"
                      icon="tabler-trash"
                    />
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
                      <VListItemTitle class="text-xs font-weight-bold">{{ prod.name }}</VListItemTitle>
                    </VListItem>
                  </VList>
                </VMenu>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Empty State -->
      <div v-if="props.employeeProducts.length === 0 && !props.loading" class="text-center py-12">
        <VIcon icon="tabler-users-group" size="64" color="disabled" class="mb-4 opacity-20" />
        <div class="text-sm font-weight-black text-disabled uppercase">Sin asignaciones registradas</div>
      </div>

      <!-- Loading State -->
      <div v-if="props.loading" class="text-center py-12">
        <VProgressCircular indeterminate color="primary" size="32" class="mb-4" />
        <div class="text-super-xs font-weight-black text-disabled uppercase">Cargando empleados...</div>
      </div>

      <!-- Paginación Móvil -->
      <div v-if="props.totalRecords > props.itemsPerPage" class="d-flex justify-center mt-4">
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
    background: transparent !important;
    block-size: 48px !important;
    border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
    color: rgb(var(--v-theme-disabled)) !important;
  }

  tbody tr {
    transition: background-color 0.2s ease;

    &:hover {
      background-color: rgba(var(--v-theme-primary), 0.02) !important;
    }

    td {
      block-size: 60px !important;
      border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.03) !important;
    }
  }
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.bg-light {
  background-color: rgba(var(--v-theme-on-surface), 0.015);
}

.leading-tight {
  line-height: 1.2;
}

.border-dashed {
  border-style: dashed !important;
}
</style>
