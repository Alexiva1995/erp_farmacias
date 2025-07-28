<script setup>
import Swal from "sweetalert2";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emits = defineEmits([
  "update:options",
  "product-click",
  "approve-product",
  "reject-product",
]);

// --- 1. AJUSTE: Actualizamos los encabezados de la tabla ---
const headers = [
  { title: "Producto", key: "product.name" },
  // Columnas añadidas
  { title: "Stock Actual", key: "system_quantity", align: "center" },
  { title: "Cant. Contada", key: "counted_quantity", align: "center" },
  {
    title: "Diferencia",
    key: "discrepancy",
    sortable: false,
    align: "center",
  },
  // Fin de columnas añadidas
  { title: "Usuario", key: "user.email" },
  { title: "Accion", key: "actions", sortable: false },
];

const emitProductClick = (product) => {
  console.log(product);
  emits("product-click", product);
};

const handleApproveProduct = async (product) => {
  // Verificamos si hay un usuario y creamos un texto dinámico
  const userName = product.user?.email || "un usuario"; // Optional chaining (?.) y valor por defecto

  const result = await Swal.fire({
    title: "¿Estás seguro?",
    // Usamos la variable userName que ya maneja el caso nulo
    text: `Vas a aprobar el conteo del producto "${product.product.name}" realizado por ${userName}.`,
    icon: "question",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Aprobar",
    confirmButtonColor: "#28a745",
    reverseButtons: true,
    didOpen: () => {
      const actions = Swal.getActions();
      const confirmButton = Swal.getConfirmButton();
      const cancelButton = Swal.getCancelButton();

      actions.style.display = "flex";
      actions.style.gap = "10px";
      actions.style.width = "100%";
      actions.style.padding = "0 20px";

      confirmButton.style.flex = "1";
      confirmButton.style.width = "50%";

      cancelButton.style.flex = "1";
      cancelButton.style.width = "50%";
    },
  });

  if (result.isConfirmed) {
    emits("approve-product", product);
  }
};

const handleRejectProduct = async (product) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    // Aquí no se menciona al usuario, pero es bueno tener la costumbre de verificar
    text: `Vas a rechazar el conteo del producto "${product.product.name}" y abrir el modal de corrección.`,
    icon: "warning",
    showCancelButton: true,
    cancelButtonText: "Cancelar",
    confirmButtonText: "Abrir Corrección",
    confirmButtonColor: "#dc3545",
    reverseButtons: true,
    didOpen: () => {
      const actions = Swal.getActions();
      const confirmButton = Swal.getConfirmButton();
      const cancelButton = Swal.getCancelButton();

      actions.style.display = "flex";
      actions.style.gap = "10px";
      actions.style.width = "100%";
      actions.style.padding = "0 20px";

      confirmButton.style.flex = "1";
      confirmButton.style.width = "50%";

      cancelButton.style.flex = "1";
      cancelButton.style.width = "50%";
    },
  });

  if (result.isConfirmed) {
    emits("reject-product", product);
  }
};
</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.products"
      :items-length="props.totalProduct"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="$emit('update:options', $event)"
      item-value="id"
      hover
    >
      <template #item.id="{ item }">
        <span class="font-weight-medium">{{ item.id }}</span>
      </template>

      <template #item.product.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <VAvatar
            v-if="item.product.photo_url"
            size="38"
            variant="tonal"
            rounded
            :image="item.product.photo_url"
          />
          <div class="d-flex flex-column">
            <span
              class="text-body-1 font-weight-medium text-high-emphasis"
              :class="{ 'text-primary': item.product.psychotropic == 1 }"
            >
              {{ item.product.name }}

              <span v-if="item.product.iva == 1"> (G)</span>

              <span v-if="item.product.is_colombian_origin == 1"> (COL)</span>
            </span>

            <span class="text-sm text-disabled">{{
              item.product.active_ingredient
            }}</span>
          </div>
        </div>
      </template>

      <!-- --- 2. AJUSTE: Eliminamos el template antiguo de 'counted_quantity' --- -->
      <!-- El template que estaba aquí ha sido eliminado ya que la lógica de colores se mueve a la columna de diferencia. -->
      <!-- Las nuevas columnas de 'Stock Actual' y 'Cant. Contada' usarán el renderizado por defecto, que es suficiente. -->

      <!-- --- 3. AJUSTE: Añadimos el nuevo template para la columna 'discrepancy' --- -->
      <template #item.discrepancy="{ item }">
        <!-- Verificamos que los datos necesarios existan para evitar errores -->
        <template
          v-if="item.product && typeof item.product.stock !== 'undefined'"
        >
          <span
            :class="{
              'text-success': item.counted_quantity - item.product.stock > 0,
              'text-error': item.counted_quantity - item.product.stock < 0,
            }"
            class="font-weight-medium"
          >
            <!-- Calculamos la diferencia y añadimos un '+' si es positiva -->
            {{ item.counted_quantity - item.product.stock > 0 ? "+" : ""
            }}{{ item.counted_quantity - item.product.stock }}
          </span>
        </template>
        <!-- Mostramos 'N/A' si no hay datos de stock -->
        <template v-else>
          <span class="text-disabled">N/A</span>
        </template>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex gap-2">
          <IconBtn @click="handleApproveProduct(item)" size="small">
            <VIcon icon="tabler-check" />
            <VTooltip activator="parent" location="top"> Aprobar </VTooltip>
          </IconBtn>

          <IconBtn @click="handleRejectProduct(item)" size="small">
            <VIcon icon="tabler-x" />
            <VTooltip activator="parent" location="top"> Rechazar </VTooltip>
          </IconBtn>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
