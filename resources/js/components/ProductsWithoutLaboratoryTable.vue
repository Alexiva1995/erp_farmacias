<script setup>
import { ref } from "vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  productWithError: { type: [Number, null], default: null },
  laboratories: { type: Array, default: () => [] },
});

const emit = defineEmits(["update:options", "update-product", "laboratory-created"]);

const editingProductId = ref(null);
const editingValue = ref(null);
const searchInput = ref("");
const currentEditingProduct = ref(null);

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
  { title: "Acciones", key: "actions", sortable: false },
];

const createLaboratory = async (name) => {
  try {
    const response = await axios.post("/laboratories", { name });
    toast.success("Laboratorio creado con éxito");
    emit("laboratory-created", response.data.laboratory);
    return response.data.laboratory;
  } catch (err) {
    if (err.response?.status === 422) {
      const errorMessage = err.response.data?.errors?.name?.[0] || err.response.data?.message || "Error al crear el laboratorio";
      toast.error(errorMessage);
    } else {
      toast.error("Error al crear el laboratorio");
    }
    throw err;
  }
};

const saveInlineEdit = async (product) => {
  try {
    emit("update-product", {
      id: product.id,
      laboratory_id: editingValue.value,
    });
  } catch (err) {
    console.error(err);
  }
};

const startEdit = (product) => {
  editingProductId.value = product.id;
  editingValue.value = product.laboratory_id || null;
  currentEditingProduct.value = product;
  searchInput.value = "";
};

const cancelEdit = () => {
  editingProductId.value = null;
  editingValue.value = null;
  currentEditingProduct.value = null;
  searchInput.value = "";
};

const handleLaboratorySearch = (search) => {
  searchInput.value = search;
};

const handleCreateLaboratoryOnEnter = async (event) => {
  if (!searchInput.value || !searchInput.value.trim()) return;
  
  const labName = searchInput.value.trim();
  
  // Verificar si ya existe un laboratorio con ese nombre (case-insensitive)
  const exists = props.laboratories.some(
    (lab) => lab.name.toLowerCase() === labName.toLowerCase()
  );
  
  if (exists) {
    toast.error("Ya existe un laboratorio con ese nombre");
    return;
  }
  
  try {
    const newLab = await createLaboratory(labName);
    // Asignar el nuevo laboratorio al producto
    editingValue.value = newLab.id;
    // Guardar automáticamente
    if (currentEditingProduct.value) {
      await saveInlineEdit(currentEditingProduct.value);
    }
  } catch (err) {
    // El error ya se maneja en createLaboratory
    console.error(err);
  }
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
        <template v-if="editingProductId === item.id">
          <VAutocomplete
            v-model="editingValue"
            :items="props.laboratories"
            item-title="name"
            item-value="id"
            density="compact"
            variant="outlined"
            style="width: 300px"
            placeholder="Buscar o crear laboratorio"
            clearable
            @keydown.enter.prevent="
              searchInput && searchInput.trim() && !editingValue
                ? handleCreateLaboratoryOnEnter()
                : saveInlineEdit(item)
            "
            autofocus
            :error="props.productWithError === item.id"
            :error-messages="
              props.productWithError === item.id
                ? 'Error al asignar laboratorio'
                : ''
            "
            @update:search="handleLaboratorySearch"
            :no-data-text="
              searchInput && searchInput.trim()
                ? 'No se encontró. Presiona Enter para crear uno nuevo.'
                : 'No hay laboratorios disponibles.'
            "
          />
        </template>
        <template v-else>
          {{ item.laboratory?.name || "—" }}
        </template>
      </template>

      <template #item.valid_stock="{ item }">
        <span class="font-weight-medium">{{ item.stock_calculado || 0 }}</span>
      </template>

      <template #item.next_expiration="{ item }">
        <span>{{ nextExpirationDate(item) }}</span>
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

