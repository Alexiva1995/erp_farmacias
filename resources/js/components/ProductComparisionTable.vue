<script setup>
const props = defineProps({
  checkingApiId: { type: Number, default: null },
  supplierConnections: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalSupplierConnections: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  searchQuery: { type: String, default: "" },
});

const emit = defineEmits([
  "update:options",
  "show-products",
  "update-products",
  "load-products",
  "delete-products",
  "open-discount-dialog",
  "update:searchQuery",
]);

const headers = [
  { title: "Id", key: "id", sortable: false },
  { title: "Proveedor", key: "name", sortable: false },
  { title: "Fecha Conexión", key: "last_connection", sortable: false },
  { title: "Tipo", key: "type", sortable: false },
  { title: "Acciones", key: "actions", sortable: false },
];
</script>

<template>
  <VCard>
    <VCardText class="py-4 gap-4">
      <AppTextField
        :model-value="props.searchQuery"
        placeholder="Nombre del proveedor..."
        clearable
        @update:model-value="emit('update:searchQuery', $event)"
        class="w-25"
      />
    </VCardText>

    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.supplierConnections"
      :items-length="props.totalSupplierConnections"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.id="{ item }">
        <span class="font-weight-medium">{{ item.id }}</span>
      </template>

      <template #item.name="{ item }">
        <span class="text-body-1 font-weight-medium text-high-emphasis">
          {{ item.name }}
        </span>
      </template>

      <template #item.last_connection="{ item }">
        <span class="font-weight-medium">{{ item.last_connection }}</span>
      </template>

      <template #item.type="{ item }">
        <span class="font-weight-medium">{{ item.type }}</span>
      </template>

      <template #item.actions="{ item }">
        <VTooltip text="Ver Productos" location="top">
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('show-products', item)">
              <VIcon icon="tabler-eye" />
            </IconBtn>
          </template>
        </VTooltip>

        <VTooltip text="Borrar Productos" location="top">
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('delete-products', item)">
              <VIcon icon="tabler-trash" />
            </IconBtn>
          </template>
        </VTooltip>

        <VTooltip text="Aplicar Descuento" location="top">
          <template #activator="{ props }">
            <IconBtn
              v-bind="props"
              color="warning"
              @click="emit('open-discount-dialog', item)"
            >
              <VIcon icon="tabler-percentage" />
            </IconBtn>
          </template>
        </VTooltip>

        <VTooltip
          v-if="item.type !== 'NO REGISTRADO' && item.type !== 'ARCHIVO EXCEL'"
          text="Actualizar Productos"
          location="top"
        >
          <template #activator="{ props }">
            <IconBtn
              v-bind="props"
              :disabled="checkingApiId === item.id"
              @click="emit('update-products', item)"
            >
              <VIcon
                :icon="
                  checkingApiId === item.id ? 'tabler-loader' : 'tabler-api'
                "
                :class="checkingApiId === item.id ? 'spin-icon' : ''"
              />
            </IconBtn>
          </template>
        </VTooltip>

        <VTooltip
          v-if="item.type === 'NO REGISTRADO' || item.type === 'ARCHIVO EXCEL'"
          text="Cargar Productos"
          location="top"
        >
          <template #activator="{ props }">
            <IconBtn
              v-bind="props"
              :disabled="checkingApiId === item.id"
              @click="emit('load-products', item)"
            >
              <VIcon
                :icon="
                  checkingApiId === item.id ? 'tabler-loader' : 'tabler-upload'
                "
                :class="checkingApiId === item.id ? 'spin-icon' : ''"
              />
            </IconBtn>
          </template>
        </VTooltip>
      </template>
    </VDataTableServer>
  </VCard>
</template>
