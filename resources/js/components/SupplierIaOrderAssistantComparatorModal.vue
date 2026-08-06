<script setup>
import ProductComparisionProductsTable from "@/components/ProductComparisionProductsTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  product: { type: Object, default: null },
  quantity: { type: Number, default: 0 },
  conDescuento: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "product-added"]);

const isVisible = ref(props.modelValue);
watch(() => props.modelValue, (val) => { isVisible.value = val; });
watch(isVisible, (val) => { emit("update:modelValue", val); });

const comparatorSearchQuery = ref("");
const comparatorProducts = ref([]);
const comparatorLoading = ref(false);
const comparatorTotal = ref(0);
const comparatorPage = ref(1);
const comparatorItemsPerPage = ref(10);
const comparatorSortBy = ref([{ key: "unit_cost_usd", order: "asc" }]);

watch(() => props.product, (newProd) => {
  if (newProd) {
    const namePart = newProd.name ? newProd.name.substring(0, 5) : "";
    const labPart = newProd.laboratory?.name ? newProd.laboratory.name.substring(0, 3) : "";
    comparatorSearchQuery.value = `${namePart} ${labPart}`.trim();
    comparatorPage.value = 1;
  }
});

const fetchComparatorProducts = async () => {
  if (!isVisible.value) return;
  comparatorLoading.value = true;
  try {
    const { data } = await axios.get("/suppliers/available-products", {
      params: {
        page: comparatorPage.value,
        perPage: comparatorItemsPerPage.value,
        q: comparatorSearchQuery.value,
        sortBy: comparatorSortBy.value[0]?.key,
        order: comparatorSortBy.value[0]?.order,
      },
    });
    comparatorProducts.value = data.data;
    comparatorTotal.value = data.total;
  } catch (error) {
    console.error("[Comparator] Error:", error);
    toast.error("Error al buscar productos de proveedores");
  } finally {
    comparatorLoading.value = false;
  }
};

watch(
  [isVisible, comparatorSearchQuery, comparatorPage, comparatorItemsPerPage, comparatorSortBy],
  () => {
    if (isVisible.value) {
      fetchComparatorProducts();
    }
  }
);

const handleSendToAutoOrder = async ({ id, quantity, item }) => {
  try {
    const nuestroBarcode = props.product?.barcode ? String(props.product.barcode).trim() : "";
    const listadoBarcode = item?.barcode_match ? String(item.barcode_match).trim() : "";

    if (listadoBarcode && nuestroBarcode !== listadoBarcode) {
      const { isConfirmed } = await Swal.fire({
        title: "¿Reemplazar código de barras?",
        html: `Nuestro producto actual tiene el código: <strong>${nuestroBarcode || "vacío"}</strong>.<br>El del listado del proveedor es: <strong>${listadoBarcode}</strong>.<br><br>¿Desea actualizar nuestro código de barras por el del listado?`,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Sí, reemplazar",
        cancelButtonText: "No, mantener actual",
      });

      if (isConfirmed) {
        try {
          await axios.post(`/suppliers-ia-order-assistant/products/${props.product.id}/update-barcode`, {
            barcode: listadoBarcode,
          });
          toast.success("Código de barras actualizado correctamente.");
          if (props.product) props.product.barcode = listadoBarcode;
        } catch (updateError) {
          console.error("Error updating barcode:", updateError);
          if (updateError.response?.status === 409 && updateError.response?.data?.conflict) {
            const { isConfirmed: confirmForce } = await Swal.fire({
              title: "Código duplicado",
              text: updateError.response.data.message,
              icon: "warning",
              showCancelButton: true,
              confirmButtonText: "Sí, desvincular y asignar",
              cancelButtonText: "Cancelar",
            });
            if (confirmForce) {
              try {
                await axios.post(`/suppliers-ia-order-assistant/products/${props.product.id}/update-barcode`, {
                  barcode: listadoBarcode,
                  force: true,
                });
                toast.success("Código de barras actualizado correctamente.");
                if (props.product) props.product.barcode = listadoBarcode;
              } catch (forceError) {
                console.error("Error forcing barcode update:", forceError);
                toast.error("No se pudo forzar el reajuste del código de barras.");
              }
            }
          } else {
            toast.error("No se pudo actualizar el código de barras, pero se procederá con el pedido.");
          }
        }
      }
    }

    const form = new FormData();
    form.append("productId", id);
    form.append("main_product_id", props.product.id);
    form.append("quantity", quantity);

    await axios.post("/suppliers/add-product-to-order", form);

    toast.success("Producto añadido a la orden de compra.");
    isVisible.value = false;
    emit("product-added", props.product.id);
  } catch (error) {
    console.error("[Comparator] Error sending to order:", error);
    toast.error("Error al añadir producto a la orden.");
  }
};
</script>

<template>
  <VDialog v-model="isVisible" max-width="1200" scrollable persistent transition="dialog-bottom-transition">
    <VCard class="rounded-xl shadow-2xl overflow-hidden border-0 elevation-24">
      <VCardTitle class="pa-0">
        <div class="bg-primary px-6 py-4 d-flex align-center justify-space-between w-100 border-b border-primary-darken-1">
          <div class="d-flex align-center">
            <div class="bg-white bg-opacity-10 pa-2 rounded-lg mr-4 border border-white border-opacity-10">
              <VIcon icon="tabler-arrows-exchange" color="white" size="24" />
            </div>
            <div class="d-flex flex-column overflow-hidden">
              <span class="text-h6 font-weight-black text-white leading-tight mb-0">Comparador de Proveedores</span>
              <span class="text-caption text-white text-opacity-80 d-flex align-center">
                Buscando para: <VChip color="surface" size="x-small" class="ml-2 font-weight-black text-truncate text-primary" max-width="600">{{ props.product?.name }}</VChip>
              </span>
            </div>
          </div>
          <VBtn icon="tabler-x" variant="tonal" color="white" size="small" @click="isVisible = false" class="rounded-lg hover-rotate" />
        </div>
      </VCardTitle>
      <VDivider />
      <VCardText class="pa-0 bg-var-theme-background">
        <div class="pa-6">
          <ProductComparisionProductsTable
            :products="comparatorProducts"
            :loading="comparatorLoading"
            :total-products="comparatorTotal"
            :items-per-page="comparatorItemsPerPage"
            :page="comparatorPage"
            :search-query="comparatorSearchQuery"
            :selected-product="props.product"
            enable-usd-amount-col
            enable-discount-col
            :enable-discounts="props.conDescuento"
            v-model:sort-by="comparatorSortBy"
            @update:searchQuery="comparatorSearchQuery = $event"
            @update:options="(options) => { 
                comparatorPage = options.page; 
                comparatorItemsPerPage = options.itemsPerPage; 
            }"
            @send-product="handleSendToAutoOrder"
          />
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.03);
}
.hover-rotate:hover {
  transform: rotate(90deg);
  transition: transform 0.3s ease;
}
</style>
