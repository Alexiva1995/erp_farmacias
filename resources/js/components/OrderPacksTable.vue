<script setup>
import { formatCurrency } from "@/utils/currencyFormatter";
import { ref, watch } from "vue";

const props = defineProps({
  packs: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  itemsPerPage: { type: Number, default: 10 },
  page: { type: Number, default: 1 },
  totalPacks: { type: Number, default: 0 },
});

const emit = defineEmits(["update:options", "add-pack", "view-pack-details"]);

const inputQuantities = ref(new Map());

const headers = [
  { title: "ID", key: "id", sortable: true, width: "80px" },
  { title: "Nombre", key: "name", sortable: true, width: "25%" },
  { title: "Productos", key: "products_count", sortable: true, width: "120px" },
  { title: "Precio Total", key: "total_price", sortable: true, width: "120px" },
  { title: "Cant. Máx.", key: "max_quantity", sortable: true, width: "120px" },
  {
    title: "Fecha Límite",
    key: "max_sale_date",
    sortable: true,
    width: "140px",
  },
  { title: "Estado", key: "is_active", sortable: true, width: "100px" },
  {
    title: "Añadir",
    key: "add_action_with_quantity",
    sortable: false,
    width: "150px",
    align: "center",
  },
  {
    title: "Acciones",
    key: "actions",
    sortable: false,
    align: "center",
    width: "100px",
  },
];

const formatDate = (date) => {
  if (!date) return "-";
  return new Date(date).toLocaleDateString("es-ES");
};

const handleAddPack = (packId) => {
  const quantityToAdd = inputQuantities.value.get(packId);
  if (
    quantityToAdd === null ||
    quantityToAdd === undefined ||
    quantityToAdd <= 0
  ) {
    return;
  }
  const pack = props.packs.find((p) => p.id === packId);
  if (pack) {
    emit("add-pack", { pack, quantity: quantityToAdd });
  }

  // Reset input
  inputQuantities.value.set(packId, 1);
};

const handleViewPack = (pack) => {
  emit("view-pack-details", pack);
};

const handleInputOrderChange = (packId, val) => {
  let cleanVal = parseInt(val);
  if (isNaN(cleanVal) || cleanVal < 0) {
    cleanVal = 0;
  }
  inputQuantities.value.set(packId, cleanVal);
};

watch(
  () => props.packs,
  (newPacks) => {
    const newOrderMap = new Map();
    newPacks.forEach((pack) => {
      let previousQty = inputQuantities.value.get(pack.id);
      if (
        previousQty === undefined ||
        previousQty === null ||
        previousQty < 1
      ) {
        newOrderMap.set(pack.id, 1);
      } else {
        newOrderMap.set(pack.id, previousQty);
      }
    });
    inputQuantities.value = newOrderMap;
  },
  { immediate: true }
);
</script>

<template>
  <VCard class='mt-6'>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.packs"
      :items-length="props.totalPacks"
      :loading="props.loading"
      class="premium-table"
      @update:options="(options) => emit('update:options', options)"
    >
     <!-- <template #item.products_count="{ item }">
         <VChip variant="outlined" color="primary" size="small">
          {{ item.pack_config ? Object.keys(item.pack_config).length : 0 }}
        </VChip>
      </template>-->

<template #item.products_count="{ item }">
  <div class="d-flex flex-column gap-1 py-1">
    <template v-if="item.products_info && item.products_info.length > 0">
      <span 
        v-for="(prod, index) in item.products_info" 
        :key="index"
        class="text-caption text-grey-darken-1 font-weight-medium"
      >
       ({{ prod.product_name}}
        <span v-if="prod.product_info" class="text-sm text-disabled">
          <template v-if="prod.product_info.laboratory">
            &nbsp;-&nbsp;{{ prod.product_info.laboratory }}
          </template>
          <template v-if="prod.product_info.laboratory && prod.product_info.active_ingredient">
            &nbsp;-&nbsp;
          </template>
          <template v-if="prod.product_info.active_ingredient">
            {{prod.product_info.active_ingredient}}
          </template>)
        </span>
      </span>
    </template>
    
    <span v-else class="text-caption text-grey-lighten-1">
      Sin productos
    </span>
  </div>
</template>

      <template #item.total_price="{ item }">
        <span class="font-weight-bold">
          {{ formatCurrency(parseFloat(item.total_price)) }}
        </span>
      </template>

      <template #item.max_quantity="{ item }">
        <span v-if="item.max_quantity" class="text-caption">
          {{ item.max_quantity }}
        </span>
        <span v-else class="text-disabled text-caption">Ilimitado</span>
      </template>

      <template #item.max_sale_date="{ item }">
        <span class="text-caption">
          {{ formatDate(item.max_sale_date) }}
        </span>
      </template>

      <template #item.is_active="{ item }">
        <VChip
          :color="item.is_active ? 'success' : 'error'"
          variant="flat"
          size="small"
        >
          {{ item.is_active ? "Activo" : "Inactivo" }}
        </VChip>
      </template>

      <template #item.add_action_with_quantity="{ item }">
        <div class="d-flex align-center justify-center gap-2">
          <VTextField
            :model-value="inputQuantities.get(item.id) ?? 0"
            @update:model-value="(val) => handleInputOrderChange(item.id, val)"
            type="number"
            min="0"
            density="compact"
            variant="outlined"
            hide-details
            single-line
            style="max-inline-size: 70px; min-inline-size: 70px;"
            class="my-1 text-center"
            :disabled="!item.is_active"
          />
          <IconBtn
            @click="handleAddPack(item.id)"
            :disabled="
              (inputQuantities.get(item.id) ?? 0) <= 0 || !item.is_active
            "
            color="primary"
            variant="tonal"
            size="small"
          >
            <VIcon icon="tabler-plus" />
          </IconBtn>
        </div>
      </template>

      <template #item.actions="{ item }">
        <VBtn
          icon
          variant="text"
          size="small"
          color="info"
          @click="handleViewPack(item)"
        >
          <VIcon>tabler-eye</VIcon>
        </VBtn>
      </template>
    </VDataTableServer>
  </VCard>
</template>
