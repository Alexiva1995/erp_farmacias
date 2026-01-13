<script setup>
import { ref } from "vue";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  productWithError: { type: [Number, null], default: null },
  errorMessage: { type: String, default: "" },
});

const emit = defineEmits(["update:options", "update-product"]);

const editingProductId = ref(null);
const editingValue = ref("");

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Producto", key: "name", sortable: true },
  { title: "Laboratorio", key: "laboratory", sortable: true },
  {
    title: "Stock",
    key: "valid_stock",
    visible: true,
    sortable: true,
    value: (item) => {
      return item.stock_calculado;
    },
  },
  { title: "Exp.", key: "next_expiration", sortable: true },
  { title: "Barcode", key: "barcode", sortable: false },
  { title: "Acciones", key: "actions", sortable: false },
];

const saveInlineEdit = async (product) => {
  if (!editingValue.value.trim()) return;

  try {
    emit("update-product", {
      id: product.id,
      barcode: editingValue.value,
    });
  } catch (err) {
    console.error(err);
  }
};

const startEdit = (product) => {
  editingProductId.value = product.id;
  editingValue.value = product.barcode || "";
};

const cancelEdit = () => {
  editingProductId.value = null;
  editingValue.value = "";
};

// TODO: hay que modificar la funcion para que muestre la fecha de vencimiento a pesar de que los lotes ya estén todos vencidos (puede que se tenga que modificar la consulta en el backend)
const nextExpirationDate = (product) => {
  if (
    !product.lots ||
    !Array.isArray(product.lots) ||
    product.lots.length === 0
  )
    return "N/A";
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const validLots = product.lots.filter((lot) => {
    if (!lot.expiration_date) return false;
    const expirationDate = new Date(lot.expiration_date);
    return !isNaN(expirationDate.getTime()) && expirationDate >= today;
  });
  // if (validLots.length === 0) return "Todos expiraron";
  if (validLots.length === 0) return product.ultima_fecha_vencimiento || "N/A";
  validLots.sort(
    (a, b) => new Date(a.expiration_date) - new Date(b.expiration_date)
  );
  const closestDate = new Date(validLots[0].expiration_date);
  return closestDate.toISOString().split("T")[0];
};
</script>

<template>
  <VCard>
    <VDataTableServer
      :headers="headers"
      :items="products"
      :items-length="totalProduct"
      :items-per-page="itemsPerPage"
      :page="page"
      :loading="loading"
      @update:options="(opts) => emit('update:options', opts)"
    >
      <template #item.id="{ item }">
        {{ item.id }}
      </template>

      <template #item.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <VAvatar
            v-if="item.photo_url"
            size="38"
            variant="tonal"
            rounded
            :image="item.photo_url"
          />
          <div class="d-flex flex-column">
            <span
              class="text-body-1 font-weight-medium text-high-emphasis"
              :class="{ 
                'text-warning font-weight-bold': item.psychotropic == 1 || item.psychotropic === true
              }"
            >
              {{ item.name.toUpperCase() }}
              <span v-if="item.iva == 1 || item.iva === true"> (G)</span>
              <span v-if="item.is_colombian_origin == 1 || item.is_colombian_origin === true"> (COL)</span>
            </span>
            <span class="text-sm text-disabled">{{
              item.active_ingredient
            }}</span>
          </div>
        </div>
      </template>

      <template #item.laboratory="{ item }">
        <span>{{ item.laboratory?.name || "—" }}</span>
      </template>

      <template #item.valid_stock="{ item }">
        <span class="font-weight-medium">{{ item.stock_calculado || 0 }}</span>
      </template>

      <template #item.next_expiration="{ item }">
        <span>{{ nextExpirationDate(item) }}</span>
      </template>

      <template #item.barcode="{ item }">
        <template v-if="editingProductId === item.id">
          <VTextField
            v-model="editingValue"
            density="compact"
            variant="outlined"
            style="width: 300px"
            @keyup.enter="saveInlineEdit(item)"
            autofocus
            :error="props.productWithError === item.id"
            :error-messages="
              props.productWithError === item.id
                ? props.errorMessage || 'Ya se encuentra registrado'
                : ''
            "
          />
        </template>
        <template v-else>
          {{ item.barcode || "—" }}
        </template>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex gap-2">
          <template v-if="editingProductId === item.id">
            <VBtn
              icon="tabler-check"
              size="small"
              @click="saveInlineEdit(item)"
            />
            <VBtn icon="tabler-x" size="small" @click="cancelEdit" />
          </template>
          <template v-else>
            <IconBtn @click="startEdit(item)" color="warning">
              <VIcon icon="tabler-edit" />
            </IconBtn>
          </template>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
