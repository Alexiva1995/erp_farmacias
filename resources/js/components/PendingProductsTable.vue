<script setup>
import { ref } from "vue";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  productWithError: { type: [Number, null], default: null },
});

const emit = defineEmits(["update:options", "update-product"]);

const editingProductId = ref(null);
const editingValue = ref("");

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Producto", key: "name", sortable: true },
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
        {{ item.name }}
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
                ? 'Ya se encuentra registrado'
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
            <IconBtn @click="startEdit(item)">
              <VIcon icon="tabler-edit" />
            </IconBtn>
          </template>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
