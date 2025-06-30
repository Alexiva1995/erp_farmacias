<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  productName: { type: String, default: "" },
  lots: { type: Array, default: () => [] },
  productId: { type: Number, required: true },
});

const emit = defineEmits(["update:modelValue", "save"]);

const editableLots = ref([]);
const tempIdCounter = ref(-1);

watch(
  () => props.lots,
  (newLots) => {
    if (!newLots || newLots.length === 0) {
      editableLots.value = [];
      return;
    }
    editableLots.value = newLots.map((lot) => {
      let formattedDate = "";
      if (lot.expiration_date) {
        const date = new Date(lot.expiration_date);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");
        formattedDate = `${year}-${month}-${day}`;
      }
      return { ...lot, expiration_date: formattedDate };
    });
  },
  { deep: true, immediate: true }
);

const addNewLotRow = () => {
  editableLots.value.push({
    id: tempIdCounter.value,
    lot_number: "",
    quantity: null,
    expiration_date: "",
    unit_cost: null,
  });
  tempIdCounter.value--;
};

const onSave = () => {
  emit("save", editableLots.value);
};

const onCancel = () => {
  emit("update:modelValue", false);
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="800px"
    @update:model-value="onCancel"
  >
    <VCard>
      <VCardTitle
        >Editar Lotes del Producto: {{ props.productName }}</VCardTitle
      >

      <VCardText>
        <VTable density="compact">
          <thead>
            <tr>
              <th class="text-left">Número de Lote</th>
              <th class="text-left">Cantidad</th>
              <th class="text-left">Fecha de Expiración</th>
              <th class="text-left">Costo</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="lot in editableLots" :key="lot.id">
              <td>
                <VTextField
                  v-model="lot.lot_number"
                  variant="underlined"
                  :readonly="lot.id > 0"
                />
              </td>
              <td>
                <VTextField
                  v-model="lot.quantity"
                  type="number"
                  variant="underlined"
                />
              </td>
              <td>
                <VTextField
                  v-model="lot.expiration_date"
                  type="date"
                  variant="underlined"
                />
              </td>
              <td>
                <VTextField
                  v-model="lot.unit_cost"
                  type="number"
                  prefix="$"
                  variant="underlined"
                />
              </td>
            </tr>
          </tbody>
        </VTable>

        <div class="d-flex justify-end mt-4">
          <VBtn prepend-icon="tabler-plus" variant="text" @click="addNewLotRow">
            Agregar Lote
          </VBtn>
        </div>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn color="secondary" variant="outlined" @click="onCancel"
          >Cancelar</VBtn
        >
        <VBtn color="primary" variant="flat" @click="onSave"
          >Guardar Cambios</VBtn
        >
      </VCardActions>
    </VCard>
  </VDialog>
</template>
