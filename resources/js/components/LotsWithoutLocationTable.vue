<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref } from "vue";

const props = defineProps({
  lots: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalLots: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  lotWithError: { type: [Number, null], default: null },
  errorMessage: { type: String, default: "" },
});

const emit = defineEmits(["update:options", "update-lot"]);

const editingLotId = ref(null);
const editingLocation = ref("");
const searchInput = ref("");
const currentEditingLot = ref(null);

const locations = [
  "E-001", "E-002", "E-003", "E-004", "E-005", "E-006", "E-007", "E-008", "E-009", "E-010",
  "G-001", "G-002", "G-003", "G-004", "G-005", "G-006", "G-007", "G-008", "G-009", "G-010",
  "I-001", "I-002", "I-003", "I-004", "I-005", "I-006", "I-007", "I-008", "I-009", "I-010",
  "N-001", "N-002",
  "P-001", "P-002", "P-003", "P-004", "P-005", "P-006", "P-007", "P-008", "P-009", "P-010",
  "D-001", "D-002", "D-003", "D-004", "D-005", "D-006", "D-007", "D-008", "D-009", "D-010",
].sort();

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Producto", key: "product.name", sortable: true },
  { title: "Laboratorio", key: "product.laboratory.name", sortable: true },
  { title: "# Lote", key: "lot_number", sortable: true },
  { title: "Cantidad", key: "quantity", sortable: true },
  { title: "Expiración", key: "expiration_date", sortable: true },
  { title: "Ubicación", key: "location", sortable: false },
  { title: "Acciones", key: "actions", sortable: false },
];

const saveInlineEdit = async (lot) => {
  if (!editingLocation.value || !editingLocation.value.trim()) {
    toast.warning("Por favor ingrese una ubicación");
    return;
  }

  try {
    emit("update-lot", {
      lot_id: lot.lot_id,
      location: editingLocation.value.trim(),
    });
  } catch (err) {
    console.error(err);
  }
};

const startEdit = (lot) => {
  editingLotId.value = lot.lot_id;
  editingLocation.value = lot.location || "";
  currentEditingLot.value = lot;
  searchInput.value = "";
};

const cancelEdit = () => {
  editingLotId.value = null;
  editingLocation.value = "";
  currentEditingLot.value = null;
  searchInput.value = "";
};

const handleLocationSearch = (search) => {
  searchInput.value = search;
};

const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  try {
    const date = new Date(dateString);
    const year = date.getUTCFullYear();
    const month = (date.getUTCMonth() + 1).toString().padStart(2, "0");
    const day = date.getUTCDate().toString().padStart(2, "0");
    return `${year}-${month}-${day}`;
  } catch (error) {
    return "Fecha inválida";
  }
};
</script>

<template>
  <VCard>
    <VDataTableServer
      :headers="headers"
      :items="lots"
      :items-length="totalLots"
      :items-per-page="itemsPerPage"
      :page="page"
      :loading="loading"
      @update:options="(opts) => emit('update:options', opts)"
    >
      <template #item.id="{ item }">
        {{ item.id }}
      </template>

      <template #item.product.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <VAvatar
            v-if="item.product?.photo_url"
            size="38"
            variant="tonal"
            rounded
            :image="item.product.photo_url"
          />
          <div class="d-flex flex-column">
            <span
              class="text-body-1 font-weight-medium text-high-emphasis"
              :class="{
                'text-warning font-weight-bold':
                  item.product?.psychotropic == 1 || item.product?.psychotropic === true,
              }"
            >
              {{ item.product?.name?.toUpperCase() || "—" }}
              <span v-if="item.product?.iva == 1 || item.product?.iva === true"> (G)</span>
              <span
                v-if="
                  item.product?.is_colombian_origin == 1 ||
                  item.product?.is_colombian_origin === true
                "
              >
                (COL)</span
              >
            </span>
            <span class="text-sm text-disabled">{{
              item.product?.active_ingredient || ""
            }}</span>
          </div>
        </div>
      </template>

      <template #item["product.laboratory.name"]="{ item }">
        <span>{{ item.product?.laboratory?.name || "—" }}</span>
      </template>

      <template #item.lot_number="{ item }">
        <span class="font-weight-medium">{{ item.lot_number || "—" }}</span>
      </template>

      <template #item.quantity="{ item }">
        <span class="font-weight-medium">{{ item.quantity || 0 }}</span>
      </template>

      <template #item.expiration_date="{ item }">
        <span>{{ formatDate(item.expiration_date) }}</span>
      </template>

      <template #item.location="{ item }">
        <template v-if="editingLotId === item.lot_id">
          <VAutocomplete
            v-model="editingLocation"
            :items="locations"
            density="compact"
            variant="outlined"
            style="width: 200px"
            placeholder="Seleccionar ubicación"
            clearable
            @keydown.enter.prevent="saveInlineEdit(item)"
            autofocus
            :error="props.lotWithError === item.lot_id"
            :error-messages="
              props.lotWithError === item.lot_id
                ? props.errorMessage || 'Error al asignar ubicación'
                : ''
            "
            @update:search="handleLocationSearch"
          />
        </template>
        <template v-else>
          <span class="text-error font-weight-medium">Sin ubicación</span>
        </template>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex gap-2">
          <template v-if="editingLotId === item.lot_id">
            <VBtn
              icon="tabler-check"
              size="small"
              color="success"
              @click="saveInlineEdit(item)"
            />
            <VBtn icon="tabler-x" size="small" color="error" @click="cancelEdit" />
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
