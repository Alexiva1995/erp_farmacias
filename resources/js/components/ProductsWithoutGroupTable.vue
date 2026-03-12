<script setup>
import { ref } from "vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { formatDate } from "@/utils/formatters";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  productWithError: { type: [Number, null], default: null },
  groups: { type: Array, default: () => [] },
});

const emit = defineEmits(["update:options", "update-product", "group-created"]);

const editingProductId = ref(null);
const editingValue = ref(null);
const searchInput = ref("");
const currentEditingProduct = ref(null);

const headers = [
  { 
    title: "ID", 
    key: "id", 
    sortable: true,
    cellClass: "d-none d-sm-table-cell",
    headerClass: "d-none d-sm-table-cell"
  },
  { title: "Producto", key: "name", sortable: true },
  { 
    title: "Laboratorio", 
    key: "laboratory.name", 
    sortable: true,
    cellClass: "d-none d-md-table-cell",
    headerClass: "d-none d-md-table-cell"
  },
  { title: "Grupo de Producto", key: "group", sortable: true },
  {
    title: "Stock",
    key: "valid_stock",
    visible: true,
    sortable: true,
    value: (item) => {
      return item.stock_calculado;
    },
    cellClass: "d-none d-lg-table-cell",
    headerClass: "d-none d-lg-table-cell"
  },
  { title: "Exp.", key: "next_expiration", sortable: true },
  { title: "Acciones", key: "actions", sortable: false },
];

const createGroup = async (name) => {
  try {
    const response = await axios.post("/groups", { name });
    toast.success("Grupo creado con éxito");
    const newGroup = response.data.group || response.data.data || response.data;
    emit("group-created", newGroup);
    return newGroup;
  } catch (err) {
    if (err.response?.status === 422) {
      const errorMessage = err.response.data?.errors?.name?.[0] || err.response.data?.message || "Error al crear el grupo";
      toast.error(errorMessage);
    } else {
      toast.error("Error al crear el grupo");
    }
    throw err;
  }
};

const saveInlineEdit = async (product) => {
  try {
    emit("update-product", {
      id: product.id,
      group_id: editingValue.value,
    });
  } catch (err) {
    console.error(err);
  }
};

const startEdit = (product) => {
  editingProductId.value = product.id;
  editingValue.value = product.group_id || null;
  currentEditingProduct.value = product;
  searchInput.value = "";
};

const cancelEdit = () => {
  editingProductId.value = null;
  editingValue.value = null;
  currentEditingProduct.value = null;
  searchInput.value = "";
};

const handleGroupSearch = (search) => {
  searchInput.value = search;
};

const handleCreateGroupOnEnter = async (event) => {
  if (!searchInput.value || !searchInput.value.trim()) return;
  
  const groupName = searchInput.value.trim();
  
  // Verificar si ya existe un grupo con ese nombre (case-insensitive)
  const exists = props.groups.some(
    (group) => group.name.toLowerCase() === groupName.toLowerCase()
  );
  
  if (exists) {
    toast.error("Ya existe un grupo con ese nombre");
    return;
  }
  
  try {
    const newGroup = await createGroup(groupName);
    // Asignar el nuevo grupo al producto
    editingValue.value = newGroup.id;
    // Guardar automáticamente
    if (currentEditingProduct.value) {
      await saveInlineEdit(currentEditingProduct.value);
    }
  } catch (err) {
    // El error ya se maneja en createGroup
    console.error(err);
  }
};

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
  if (validLots.length === 0) return product.ultima_fecha_vencimiento || "N/A";
  validLots.sort(
    (a, b) => new Date(a.expiration_date) - new Date(b.expiration_date)
  );
  const closestDate = new Date(validLots[0].expiration_date);
  return formatDate(closestDate);
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
              <!-- En móvil añadimos ID y Laboratorio al nombre -->
              <span class="d-inline d-sm-none text-primary font-weight-bold">[{{ item.id }}] </span>
              {{ item.name.toUpperCase() }}
              <span v-if="item.iva == 1 || item.iva === true"> (G)</span>
              <span v-if="item.is_colombian_origin == 1 || item.is_colombian_origin === true"> (COL)</span>
              <div v-if="item.laboratory" class="d-block d-md-none text-xs text-secondary italic">
                {{ item.laboratory.name }}
              </div>
            </span>
            <span class="text-sm text-disabled">{{
              item.active_ingredient
            }}</span>
          </div>
        </div>
      </template>

      <template #item.laboratory.name="{ item }">
        <span>{{ item.laboratory?.name || "—" }}</span>
      </template>

      <template #item.group="{ item }">
        <template v-if="editingProductId === item.id">
          <VAutocomplete
            v-model="editingValue"
            :items="props.groups"
            item-title="name"
            item-value="id"
            variant="outlined"
            class="responsive-autocomplete"
            style=" max-inline-size: 350px;min-inline-size: 200px;"
            placeholder="Buscar o crear grupo"
            clearable
            @keydown.enter.prevent="
              searchInput && searchInput.trim() && !editingValue
                ? handleCreateGroupOnEnter()
                : saveInlineEdit(item)
            "
            autofocus
            :error="props.productWithError === item.id"
            :error-messages="
              props.productWithError === item.id
                ? 'Error al asignar grupo'
                : ''
            "
            @update:search="handleGroupSearch"
            :no-data-text="
              searchInput && searchInput.trim()
                ? 'No se encontró. Presiona Enter para crear uno nuevo.'
                : 'No hay grupos disponibles.'
            "
          />
        </template>
        <template v-else>
          {{ item.group?.name || "—" }}
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
