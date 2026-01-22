<script setup>
import QuotationCard from "@/components/cards/QuotationCard.vue";
import QuotationProducts from "@/components/cards/QuotationProducts.vue";
import QuotationFilters from "@/components/QuotationFilters.vue";
import QuotationTable from "@/components/QuotationTable.vue";
import QuotationTicket from "@/components/QuotationTicket.vue";
import axios from "@/plugins/axios";
import { computed, nextTick, onMounted, ref, watch } from "vue";

import { toast } from "@/plugins/sweetalert";

const products = ref([]);
const totalProduct = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const clientSearchQuery = ref("");
const selectedClient = ref({});
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
    (key) => (params[key] === null || params[key] === "") && delete params[key],
  );
  try {
    const response = await axios.get("/tpv/quotation", { params });
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
  { deep: true },
);

watch(
  [filterSearchQuery, selectedLaboratory, selectedOrigin, stockStatusFilter],
  () => {
    page.value = 1;
  },
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
      error.response ? error.response.data : error.message,
    );
    toast.error(
      "Producto no encontrado o error al agregar por código de barras.",
    );
  }
};

const addProductToQuotation = async ({ productId, quantity }) => {
  if (quantity <= 0) {
    toast.error("La cantidad a agregar debe ser mayor que cero.");
    return;
  }

  try {
    const response = await axios.get(`/tpv/quotation/${productId}`);
    const productDetails = response.data;
    const availableQuantity = productDetails.valid_stock_sum;
    if (quantity > availableQuantity) {
      toast.error(
        `No hay suficiente stock para "${productDetails.name}". Disponible: ${availableQuantity}. Solicitado: ${quantity}.`,
      );
      return;
    }

    const existingItemIndex = quotationItems.value.findIndex(
      (item) => item.id === productId,
    );
    if (existingItemIndex !== -1) {
      const currentSelectedQuantity =
        quotationItems.value[existingItemIndex].selectedQuantity;
      const newTotalSelectedQuantity = currentSelectedQuantity + quantity;

      if (newTotalSelectedQuantity > availableQuantity) {
        toast.warning(
          `Ya se agrego la cantidad maxima disponible de "${productDetails.name}"`,
        );
        quotationItems.value[existingItemIndex].selectedQuantity =
          availableQuantity;
      } else {
        quotationItems.value[existingItemIndex].selectedQuantity =
          newTotalSelectedQuantity;
        toast.success(
          `Cantidad de "${productDetails.name}" incrementada a ${newTotalSelectedQuantity}.`,
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
      error.response ? error.response.data : error.message,
    );
    toast.error(
      "Error al agregar el producto a la cotización. Inténtalo de nuevo.",
    );
  }
};

const removeQuotationItem = (productId) => {
  quotationItems.value = quotationItems.value.filter(
    (item) => item.id !== productId,
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
  sortBy.value = undefined;
  orderBy.value = undefined;
};

const handleClearSortOrder = () => {
  sortBy.value = undefined; // Reinicia el orden de la tabla
  orderBy.value = undefined; // Reinicia el orden de la tabla
};

const handleCurrencyChanged = (newCurrency) => {
  selectedDisplayCurrency.value = newCurrency;
};

const saveQuotation = async () => {
  if (quotationItems.value.length === 0) {
    throw new Error("No hay productos en la cotización para guardar.");
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
      client_id: selectedClient.value.id,
      products: quotationItems.value.map((item) => ({
        id: item.id,
        quantity: item.selectedQuantity,
        tax_rate: item.taxRate,
      })),
    };
    const response = await axios.post("/tpv/quotations", payload);
    quotationDetails.value = response.data.quotation;
    return response.data.quotation;
  } catch (error) {
    console.error("Error al guardar la cotización:", error);
    throw error;
  }
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
    const response = await axios.post("/tpv/quotations", payload);
    quotationDetails.value = response.data.quotation;
    toast.success("Cotización guardada exitosamente. Preparando impresión...");
    isPrinting.value = true;
    await nextTick();
    const printContents = document.getElementById("orderInvoicePrintArea");
    if (printContents) {
      const printWindow = window.open("", "", "height=600,width=800");

      printWindow.document.write(`
        <html>
          <head>
            <title>Farmacia Barrio Sucre - Cotización</title>
            <style>
              @media print {
                @page {
                  size: 54mm auto;
                  margin: 0;
                  padding: 0;
                }
                
                body {
                  width: 54mm !important;
                  max-width: 54mm !important;
                  margin: 0 !important;
                  padding: 2mm !important;
                  font-family: 'Courier New', monospace !important;
                  font-size: 10px !important;
                  line-height: 1.2 !important;
                }
                
                * {
                  max-width: 50mm !important;
                  box-sizing: border-box !important;
                  word-wrap: break-word !important;
                }
                
                .no-print, button, .actions {
                  display: none !important;
                }
                
                table {
                  width: 100% !important;
                  border-collapse: collapse !important;
                }
                
                td, th {
                  padding: 1px 0 !important;
                  font-size: 9px !important;
                }
                
                .break-word {
                  word-break: break-word !important;
                  overflow-wrap: break-word !important;
                }
              }
              
              @media screen {
                body {
                  width: 54mm;
                  border: 1px dashed #ccc;
                  margin: 0;
                  padding: 2mm;
                  font-family: 'Courier New', monospace;
                  font-size: 10px;
                  line-height: 1.2;
                }
              }
            </style>
      `);

      const styleSheets = document.styleSheets;
      for (let i = 0; i < styleSheets.length; i++) {
        const sheet = styleSheets[i];
        try {
          if (sheet.cssRules) {
            let cssText = "";
            for (let j = 0; j < sheet.cssRules.length; j++) {
              cssText += sheet.cssRules[j].cssText;
            }
            printWindow.document.write(`<style>${cssText}</style>`);
          } else if (sheet.href) {
            printWindow.document.write(
              `<link rel="stylesheet" href="${sheet.href}">`,
            );
          }
        } catch (e) {
          console.warn(
            "No se pudo acceder a la hoja de estilo:",
            sheet.href || sheet,
            e,
          );
        }
      }

      printWindow.document.write(`
          </head>
          <body>
      `);
      printWindow.document.write(printContents.innerHTML);
      printWindow.document.write(`
          </body>
        </html>
      `);

      printWindow.document.close();
      printWindow.focus();

      printWindow.onload = function () {
        setTimeout(() => {
          printWindow.print();
          printWindow.close();
        }, 100);
      };
    } else {
      console.warn(
        "Elemento #orderInvoicePrintArea no encontrado para impresión tipo ticket. Imprimiendo toda la página.",
      );
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
      error.response ? error.response.data : error.message,
    );
    toast.error(
      "Error al guardar o imprimir la cotización. Inténtalo de nuevo.",
    );
    isPrinting.value = false;
  }
};

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};

const fetchGroupProducts = async (groupId) => {
  if (!groupId) {
    toast.info("Este producto no pertenece a un grupo.");
    return;
  }
  toast.info("Funcionalidad de grupos próximamente disponible.");
};

const fetchFailuresProducts = async (productId) => {
  try {
    const response = await axios.post("/tpv/product-failure", {
      product_id: productId,
    });
    toast.success("Reporte de falla guardado correctamente.");
  } catch (error) {
    if (error.response) {
      console.error("Errores de validación:", error.response.data.errors);
      toast.error("Hubo un problema al procesar su reporte de falla.");
    } else {
      console.error("Error de conexión:", error.message);
    }
  }
};

const fetchSearchedClient = async () => {
  try {
    const { data } = await axios.get(
      `/crm/clients/identification/${clientSearchQuery.value}`,
    );

    selectedClient.value = data.data;
    clientSearchQuery.value = "";
  } catch (error) {
    if (error.response.status == 404) {
      toast.error("No se encontró ningún cliente con esa cédula");
    }
  }
};

const handleCleanAfterSave = () => {
  handleClearFilters();
  handleClearSortOrder();
  removeQuotation();

  page.value = 1;
  selectedClient.value = {};
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
          v-model:client-identification="clientSearchQuery"
          :quotation-products="quotationItems"
          :quotation-details="quotationDetails"
          :selected-display-currency="selectedDisplayCurrency"
          :total-amount-bs="totalAmountBs"
          :total-amount-usd="totalAmountUsd"
          :total-amount-cop="totalAmountCop"
          :on-save-quotation="saveQuotation"
          :selected-client="selectedClient"
          @remove-quotation-product="removeQuotationItem"
          @remove="removeQuotation"
          @print-quotation="saveAndPrintQuotation"
          @search-client="fetchSearchedClient"
          @clean-post-save="handleCleanAfterSave"
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
      @clear-sort="handleClearSortOrder"
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
      @view-group-products="fetchGroupProducts"
      @failures-products="fetchFailuresProducts"
    />

    <div
      id="orderInvoicePrintArea"
      :class="{ 'd-none': !isPrinting, 'print-container': true }"
    >
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
