<script setup>
import QuotationTable from "@/components/QuotationTable.vue";
import QuotationFilters from "@/components/QuotationFilters.vue";
import QuotationProducts from "@/components/cards/QuotationProducts.vue";
import QuotationCard from "@/components/cards/QuotationCard.vue";
import QuotationTicket from "@/components/QuotationTicket.vue";
import axios from "@/plugins/axios";
import { onMounted, ref, watch, computed, nextTick } from "vue";

import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";

const products = ref([]);
const totalProduct = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const barcodeSearchQuery = ref("");
const filterSearchQuery = ref("");
const selectedLaboratory = ref(null);
const selectedOrigin = ref(null);
const stockStatusFilter = ref(null);

const laboratories = ref([]);
const origins = ref([]);

const isLoadingFilters = ref(false);
const quotationItems = ref([]);
const selectedDisplayCurrency = ref("USD");

const quotationDetails = ref(null);
const isPrinting = ref(false);

let barcodeInputTimer;
const BARCODE_LENGTH_THRESHOLD = 10;

const getItemPriceByCurrency = (item, currency) => {
  if (currency === "BS") {
    return item.price_bs || 0;
  } else if (currency === "COP") {
    return item.price_cop || 0;
  } else {
    // Por defecto o si es 'USD'
    return item.price || 0;
  }
};

const totalAmountBs = computed(() => {
  let total = 0;
  quotationItems.value.forEach((item) => {
    const basePriceBs = item.price_bs || 0;
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;
    total += basePriceBs * quantity * (1 + taxRate);
  });
  return total;
});

const totalAmountUsd = computed(() => {
  let total = 0;
  quotationItems.value.forEach((item) => {
    const basePriceUsd = item.price || 0;
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;
    total += basePriceUsd * quantity * (1 + taxRate);
  });
  return total;
});

const totalAmountCop = computed(() => {
  let total = 0;
  quotationItems.value.forEach((item) => {
    const basePriceCop = item.price_cop || 0;
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;
    total += basePriceCop * quantity * (1 + taxRate);
  });
  return total;
});

const totalProductsAmount = computed(() => {
  let total = 0;
  quotationItems.value.forEach((item) => {
    const price = getItemPriceByCurrency(item, selectedDisplayCurrency.value);
    const quantity = item.selectedQuantity || 0;
    total += price * quantity;
  });
  return total;
});

const totalIVAAmount = computed(() => {
  let totalIVA = 0;
  quotationItems.value.forEach((item) => {
    const price = getItemPriceByCurrency(item, selectedDisplayCurrency.value);
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;
    totalIVA += price * quantity * taxRate;
  });
  return totalIVA;
});

const totalQuotationAmount = computed(() => {
  return totalProductsAmount.value + totalIVAAmount.value;
});

const fetchSelectOptions = async () => {
  isLoadingFilters.value = true;
  try {
    const [labResponse, originResponse] = await Promise.all([
      axios.get("/laboratories"),
      axios.get("/origins"),
    ]);
    laboratories.value = labResponse.data;
    origins.value = originResponse.data;
  } catch (error) {
    console.error("Error al cargar opciones de los selects:", error);
    toast.error("No se pudieron cargar los filtros.");
  } finally {
    isLoadingFilters.value = false;
  }
};

const fetchProducts = async () => {
  loading.value = true;
  const params = {
    q: filterSearchQuery.value,
    laboratoryId: selectedLaboratory.value,
    originId: selectedOrigin.value,
    ...(stockStatusFilter.value !== null && {
      hasStock: stockStatusFilter.value,
    }),
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );
  try {
    const response = await axios.get("/quotation", { params });
    products.value = response.data.data;
    totalProduct.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los productos:", error);
    toast.error("Error al obtener los productos.");
  } finally {
    loading.value = false;
  }
};

let debounceTimer;
watch(
  [
    page,
    itemsPerPage,
    sortBy,
    orderBy,
    filterSearchQuery,
    selectedLaboratory,
    selectedOrigin,
    stockStatusFilter,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchProducts(), 300);
  },
  { deep: true }
);

watch(
  [filterSearchQuery, selectedLaboratory, selectedOrigin, stockStatusFilter],
  () => {
    page.value = 1;
  }
);

watch(barcodeSearchQuery, (newValue) => {
  clearTimeout(barcodeInputTimer);
  if (!newValue) {
    return;
  }
  if (newValue.length >= BARCODE_LENGTH_THRESHOLD) {
    barcodeInputTimer = setTimeout(async () => {
      await addProductToQuotationByBarcode(newValue);
      barcodeSearchQuery.value = "";
    }, 300);
  }
});

onMounted(() => {

  fetchSelectOptions();
  fetchProducts();
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};

const addProductToQuotationByBarcode = async (barcode) => {
  try {
    const response = await axios.get(`/barcode/${barcode}`);
    const productDetails = response.data;
    await addProductToQuotation({ productId: productDetails.id, quantity: 1 });
  } catch (error) {
    console.error(
      "Error al agregar producto por código de barras:",
      error.response ? error.response.data : error.message
    );
    toast.error(
      "Producto no encontrado o error al agregar por código de barras."
    );
  }
};

const addProductToQuotation = async ({ productId, quantity }) => {
  if (quantity <= 0) {
    toast.error("La cantidad a agregar debe ser mayor que cero.");
    return;
  }

  try {
    const response = await axios.get(`/quotation/${productId}`);
    const productDetails = response.data;
    const availableQuantity = productDetails.valid_stock_sum;
    if (quantity > availableQuantity) {
      toast.error(
        `No hay suficiente stock para "${productDetails.name}". Disponible: ${availableQuantity}. Solicitado: ${quantity}.`
      );
      return;
    }

    const existingItemIndex = quotationItems.value.findIndex(
      (item) => item.id === productId
    );
    if (existingItemIndex !== -1) {
      const currentSelectedQuantity =
        quotationItems.value[existingItemIndex].selectedQuantity;
      const newTotalSelectedQuantity = currentSelectedQuantity + quantity;

      if (newTotalSelectedQuantity > availableQuantity) {
        toast.warning(
          `Ya se agrego la cantidad maxima disponible de "${productDetails.name}"`
        );
        quotationItems.value[existingItemIndex].selectedQuantity =
          availableQuantity;
      } else {
        quotationItems.value[existingItemIndex].selectedQuantity =
          newTotalSelectedQuantity;
        toast.success(
          `Cantidad de "${productDetails.name}" incrementada a ${newTotalSelectedQuantity}.`
        );
      }
    } else {
      const itemToAdd = {
        id: productDetails.id,
        title: productDetails.name,
        active_ingredient: productDetails.active_ingredient,
        itemCode: productDetails.barcode,
        price: productDetails.sale_price,
        price_bs: productDetails.price_bs,
        price_cop: productDetails.price_cop,
        availableQuantity: availableQuantity,
        selectedQuantity: quantity,
        laboratory: productDetails.laboratory
          ? productDetails.laboratory.name
          : "N/A",
        taxRate: productDetails.iva == 1 ? 0.16 : 0,
      };
      quotationItems.value.push(itemToAdd);
      toast.success(`"${itemToAdd.title}" agregado a la cotización.`);
    }
  } catch (error) {
    console.error(
      "Error al obtener o agregar el producto a la cotización:",
      error.response ? error.response.data : error.message
    );
    toast.error(
      "Error al agregar el producto a la cotización. Inténtalo de nuevo."
    );
  }
};

const removeQuotationItem = (productId) => {
  quotationItems.value = quotationItems.value.filter(
    (item) => item.id !== productId
  );
  toast.success("Producto eliminado exitosamente");
};

const removeQuotation = () => {
  quotationItems.value = [];
};

const handleClearFilters = () => {
  filterSearchQuery.value = "";
  selectedLaboratory.value = null;
  selectedOrigin.value = null;
  stockStatusFilter.value = null;
};

const handleCurrencyChanged = (newCurrency) => {
  selectedDisplayCurrency.value = newCurrency;
};

const saveAndPrintQuotation = async () => {
  if (quotationItems.value.length === 0) {
    toast.error("No hay productos en la cotización para guardar e imprimir.");
    return;
  }

  try {
    const totalProductsAmountUSD = computed(() => {
      let total = 0;
      quotationItems.value.forEach((item) => {
        total += (item.price || 0) * (item.selectedQuantity || 0);
      });
      return total;
    });

    const totalIVAAmountUSD = computed(() => {
      let totalIVA = 0;
      quotationItems.value.forEach((item) => {
        totalIVA +=
          (item.price || 0) *
          (item.selectedQuantity || 0) *
          (item.taxRate || 0);
      });
      return totalIVA;
    });

    const totalQuotationAmountUSD = computed(() => {
      return totalProductsAmountUSD.value + totalIVAAmountUSD.value;
    });

    const payload = {
      total_amount_usd: totalProductsAmountUSD.value,
      total_iva_usd: totalIVAAmountUSD.value,
      grand_total_usd: totalQuotationAmountUSD.value,
      currency: selectedDisplayCurrency.value,
      products: quotationItems.value.map((item) => ({
        id: item.id,
        quantity: item.selectedQuantity,
        tax_rate: item.taxRate,
      })),
    };
    const response = await axios.post("/quotations", payload);
    quotationDetails.value = response.data.quotation;
    toast.success("Cotización guardada exitosamente. Preparando impresión...");
    isPrinting.value = true;
    await nextTick();
    const printContents = document.getElementById("orderInvoicePrintArea");
    if (printContents) {
      const printWindow = window.open("", "", "height=600,width=800");
      printWindow.document.write("<html><head><title>Farmacia Barrio Sucre</title>");
      const styleSheets = document.styleSheets;
      for (let i = 0; i < styleSheets.length; i++) {
        const sheet = styleSheets[i];
        try {
          if (sheet.cssRules) {
            let cssText = '';
            for (let j = 0; j < sheet.cssRules.length; j++) {
              cssText += sheet.cssRules[j].cssText;
            }
            printWindow.document.write(`<style>${cssText}</style>`);
          } else if (sheet.href) {
            printWindow.document.write(`<link rel="stylesheet" href="${sheet.href}">`);
          }
        } catch (e) {
          console.warn("No se pudo acceder a la hoja de estilo:", sheet.href || sheet, e);
        }
      }
      printWindow.document.write("</head><body>");
      printWindow.document.write(printContents.innerHTML);
      printWindow.document.write("</body></html>");
      printWindow.document.close();
      printWindow.focus();
      printWindow.print();
      printWindow.close();
    } else {
      console.warn("Elemento #orderInvoicePrintArea no encontrado para impresión tipo ticket. Imprimiendo toda la página.");
      window.print();
    }

    setTimeout(() => {
      removeQuotation();
      quotationDetails.value = null;
      isPrinting.value = false;
    }, 500);
  } catch (error) {
    console.error(
      "Error al guardar o imprimir la cotización:",
      error.response ? error.response.data : error.message
    );
    toast.error(
      "Error al guardar o imprimir la cotización. Inténtalo de nuevo."
    );
    isPrinting.value = false;
  }
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};
</script>

<template>
  <div>
    <VRow class="mb-4">
      <VCol cols="12" sm="12" md="6">
        <QuotationCard
          :total-products-amount="totalProductsAmount"
          :total-iva-amount="totalIVAAmount"
          :total-quotation-amount="totalQuotationAmount"
          :quotation-items="quotationItems"
          :selected-display-currency="selectedDisplayCurrency"
          @currency-changed="handleCurrencyChanged"
        />
      </VCol>
      <VCol cols="12" sm="12" md="6">
        <QuotationProducts
          v-model:searchQuery="barcodeSearchQuery"
          :quotation-products="quotationItems"
          :selected-display-currency="selectedDisplayCurrency"
          :total-amount-bs="totalAmountBs"
          :total-amount-usd="totalAmountUsd"
          :total-amount-cop="totalAmountCop"
          @remove-quotation-product="removeQuotationItem"
          @remove="removeQuotation"
          @print-quotation="saveAndPrintQuotation"
        />
      </VCol>
    </VRow>

    <QuotationFilters
      v-model:searchQuery="filterSearchQuery"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:selectedOrigin="selectedOrigin"
      v-model:stockStatusFilter="stockStatusFilter"
      :laboratories="laboratories"
      :origins="origins"
      :loading="isLoadingFilters"
      @clear="handleClearFilters"
      @sort="handleSort"
    >
    </QuotationFilters>

    <QuotationTable
      :products="products"
      :loading="loading"
      :total-product="totalProduct"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @add-product="addProductToQuotation"
    />

    <div id="orderInvoicePrintArea" :class="{ 'd-none': !isPrinting, 'print-container': true }">
      <QuotationTicket
        :quotation-details="quotationDetails"
        :quotation-items="quotationItems"
        :total-products-amount="totalProductsAmount"
        :total-iva-amount="totalIVAAmount"
        :total-quotation-amount="totalQuotationAmount"
        :selected-display-currency="selectedDisplayCurrency"
      />
    </div>
  </div>
</template>
