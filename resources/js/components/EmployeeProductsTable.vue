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
  { title: "EMPLEADO", key: "employee_name", sortable: true, width: "25%" },
  { title: "IDENTIFICACIÓN", key: "identification", sortable: false },
  { title: "PRODUCTOS ASIGNADOS", key: "products", sortable: false, width: "35%", align: "center" },
  { title: "TOTAL", key: "products_count", sortable: true, align: "center" },
  { title: "ACCIONES", key: "actions", sortable: false, align: "center" },
];

const getInitials = (name) => {
  if (!name) return "N/A";
  return name
    .trim()
    .split(/\s+/)
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .substring(0, 2);
};

const getAvatarColor = (id) => {
  const colors = ["primary", "secondary", "success", "info", "warning", "error", "purple", "amber"];
  return colors[id % colors.length];
};
</script>

<template>
  <VCard class="border shadow-sm overflow-hidden">
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
        <span class="font-weight-black text-primary tabular-nums text-xs uppercase">{{ item.employee_id }}</span>
      </template>

      <template #item.employee_name="{ item }">
        <div class="d-flex align-center gap-3 py-2">
          <VAvatar :color="getAvatarColor(item.employee_id)" size="34" variant="tonal" class="rounded">
            <VImg v-if="item.photo_url" :src="item.photo_url" cover />
            <span v-else class="text-super-xs font-weight-black">{{ getInitials(item.employee_name) }}</span>
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-sm font-weight-black text-high-emphasis leading-tight text-uppercase">
              {{ item.employee_name }}
            </span>
            <span class="text-super-xs font-weight-black mt-1 uppercase" :class="item.is_active ? 'text-success' : 'text-error'">
              {{ item.is_active ? "ESTADO: ACTIVO" : "ESTADO: INACTIVO" }}
            </span>
          </div>
        </div>
      </template>

      <template #item.identification="{ item }">
        <span class="text-xs text-medium-emphasis font-weight-black tabular-nums uppercase">
          {{ item.identification || "N/A" }}
        </span>
      </template>

      <template #item.products="{ item }">
        <div class="d-flex flex-wrap justify-center gap-1 max-w-ch-40 mx-auto py-1">
          <VChip
            v-for="prod in item.products"
            :key="prod.id"
            size="x-small"
            color="primary"
            variant="tonal"
            class="rounded font-weight-black text-uppercase"
          >
            {{ prod.name }}
          </VChip>
          <span v-if="item.products.length === 0" class="text-xs font-weight-black text-disabled uppercase">Sin productos</span>
        </div>
      </template>

      <template #item.products_count="{ item }">
        <VChip
          :color="item.products_count > 0 ? 'info' : 'surface-variant'"
          size="x-small"
          variant="tonal"
          class="font-weight-black rounded px-3"
          style="min-inline-size: 36px;"
        >
          {{ item.products_count }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex gap-1 justify-center">
          <IconBtn
            size="small"
            color="warning"
            variant="tonal"
            class="rounded"
            @click="emit('edit-assignment', item)"
          >
            <VIcon icon="tabler-edit" size="18" />
          </IconBtn>

          <VMenu v-if="item.products.length > 0" location="bottom end">
            <template #activator="{ props: menuProps }">
              <IconBtn
                v-bind="menuProps"
                size="small"
                color="error"
                variant="tonal"
                class="rounded"
              >
                <VIcon icon="tabler-trash" size="18" />
              </IconBtn>
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
                <VListItemTitle class="text-xs font-weight-black text-uppercase">{{ prod.name }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </div>
      </template>

      <template #bottom>
        <VDivider class="opacity-10" />
        <div class="d-flex align-center justify-space-between pa-2">
           <span class="text-super-xs text-disabled font-weight-black uppercase ms-2">
            Total: {{ props.totalRecords }} registros
          </span>
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(props.totalRecords / props.itemsPerPage)"
            size="small"
            @update:model-value="(newPage) => emit('update:options', { ...props, page: newPage })"
          />
        </div>
      </template>
    </VDataTableServer>

    <!-- Vista Móvil: Cards Premium -->
    <div v-else class="pa-4 bg-light">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-4 rounded" />

      <div v-if="props.employeeProducts.length === 0 && !props.loading" class="text-center pa-12">
        <VIcon icon="tabler-package-off" size="64" class="text-disabled mb-4 opacity-20" />
        <p class="text-sm uppercase font-weight-black text-disabled">No se encontraron empleados</p>
      </div>

      <VRow>
        <VCol v-for="item in props.employeeProducts" :key="item.employee_id" cols="12">
          <VCard class="rounded-lg border shadow-sm mb-4 overflow-hidden">
            <div class="pa-4">
              <div class="d-flex justify-space-between align-start mb-4">
                <div class="d-flex align-center gap-3 min-width-0">
                  <VAvatar :color="getAvatarColor(item.employee_id)" size="40" variant="tonal" class="rounded">
                    <VImg v-if="item.photo_url" :src="item.photo_url" cover />
                    <span v-else class="text-sm font-weight-black">{{ getInitials(item.employee_name) }}</span>
                  </VAvatar>
                  <div class="d-flex flex-column min-width-0">
                    <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight truncate">
                      {{ item.employee_name }}
                    </h3>
                    <span class="text-super-xs text-primary mt-1 font-weight-black uppercase">
                      ID: #{{ item.employee_id }} • {{ item.identification || 'SIN DNI' }}
                    </span>
                  </div>
                </div>
                <div class="d-flex gap-1">
                  <IconBtn
                    size="small"
                    color="warning"
                    variant="tonal"
                    class="rounded"
                    @click="emit('edit-assignment', item)"
                  >
                    <VIcon icon="tabler-edit" size="18" />
                  </IconBtn>
                  <VMenu v-if="item.products.length > 0" location="bottom end">
                    <template #activator="{ props: menuProps }">
                      <IconBtn
                        v-bind="menuProps"
                        size="small"
                        color="error"
                        variant="tonal"
                        class="rounded"
                      >
                        <VIcon icon="tabler-trash" size="18" />
                      </IconBtn>
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
                        <VListItemTitle class="text-xs font-weight-black text-uppercase">{{ prod.name }}</VListItemTitle>
                      </VListItem>
                    </VList>
                  </VMenu>
                </div>
              </div>

              <VDivider class="my-4 border-opacity-10" />

              <div>
                <div class="d-flex justify-space-between align-center mb-2">
                  <span class="text-super-xs font-weight-black text-disabled uppercase">Productos Asignados</span>
                  <VChip size="x-small" color="info" variant="tonal" class="rounded px-2 font-weight-black">
                    {{ item.products_count }}
                  </VChip>
                </div>
                <div class="d-flex flex-wrap gap-1">
                  <VChip
                    v-for="prod in item.products"
                    :key="prod.id"
                    size="x-small"
                    color="primary"
                    variant="tonal"
                    class="rounded font-weight-black text-uppercase"
                  >
                    {{ prod.name }}
                  </VChip>
                  <div v-if="item.products.length === 0" class="text-xs text-disabled italic font-weight-black uppercase">
                    SIN PRODUCTOS ASIGNADOS
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
    background: white !important;
    color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
    font-size: 0.75rem !important;
    font-weight: 900 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05rem !important;
    border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
  }

  tbody tr {
    transition: background-color 0.2s ease;
    &:hover {
      background-color: rgba(var(--v-theme-primary), 0.02) !important;
    }
    td {
      padding-block: 12px !important;
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

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>

