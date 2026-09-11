<script setup>
import QuotationCard from "@/components/cards/QuotationCard.vue";
import QuotationProducts from "@/components/cards/QuotationProducts.vue";
import TpvCatalogSection from "@/components/tpv/TpvCatalogSection.vue";
import QuotationTicket from "@/components/QuotationTicket.vue";
import RegisterClientModal from "@/components/dialogs/ClientFormDialoge.vue";
import PackDetailsModal from "@/components/dialogs/PackDetailsModal.vue";
import axios from "@/plugins/axios";
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import { toast } from "@/plugins/sweetalert";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { useQuotationClient } from "@/composables/useQuotationClient";
import { useTpvRates } from "@/composables/useTpvRates";
import { useTpvPromotions } from "@/composables/useTpvPromotions";
import { useTpvCatalog } from "@/composables/useTpvCatalog";
import { getItemPriceByCurrency } from "@/composables/useTpvItemFormatter";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";

// ─── Configuración y Estado Base ─────────────────────────────────────────────
const brandingStore = useBrandingStore();
const isRestaurant = ref(false);
const enableDishes = ref(false);

// ─── Tasas de cambio ──────────────────────────────────────────────────────────
const { exchangeRates, fetchExchangeRates } = useTpvRates(brandingStore, isRestaurant);

// ─── Gestión de Cliente ───────────────────────────────────────────────────────
const {
  selectedClient,
  clientSearchQuery,
  clientIdentification,
  verifyClient: verifyClientComposable,
  fetchSearchedClient,
} = useQuotationClient();

// ─── Persistencia del Carrito de Cotización ──────────────────────────────────
const quotationItems = ref([]);

const saveQuotationToLocalStorage = () => {
  localStorage.setItem("tpv_current_quotation", JSON.stringify(quotationItems.value));
};

const loadQuotationFromLocalStorage = () => {
  const saved = localStorage.getItem("tpv_current_quotation");
  if (saved) {
    try {
      quotationItems.value = JSON.parse(saved);
    } catch (e) {
      console.error("Error al cargar cotización guardada:", e);
    }
  }
};

watch(
  quotationItems,
  () => {
    saveQuotationToLocalStorage();
  },
  { deep: true },
);

// ─── Promociones y Ofertas (Mismo comportamiento que /tpv/orderUser) ─────────
const {
  activeDoctorOffers,
  loadingDoctorOffers,
  activePrescriptionOffers,
  loadingPrescriptionOffers,
  activeCompanyOffers,
  loadingCompanyOffers,
  selectedDoctorOffer,
  prescriptionFile,
  selectedCompany,
  selectedCompanyId,
  selectedDiscountType,
  currentPrescriptionDiscountPercentage,
  currentGlobalDiscountDetails,
  fetchDoctorOffers,
  fetchPrescriptionOffers,
  fetchCompanyOffers,
  validateAndApplyDoctorDiscount,
  validateAndApplyPrescriptionDiscount,
  validateAndApplyCompanyDiscount,
  handlePrescriptionFileSelected,
  handleDoctorDiscountSelected,
  handleCompanyDiscountSelected,
  applyDiscount,
  removeDiscount,
} = useTpvPromotions({ orderItems: quotationItems, selectedClient });

// Porcentaje de descuento global actualmente activo
const globalDiscountPercentage = computed(() => {
  if (selectedDiscountType.value === "Empresa" && selectedCompanyId.value) {
    const offer = activeCompanyOffers.value.find((o) => o.value === selectedCompanyId.value);
    return parseFloat(offer?.current_discount || 0);
  } else if (selectedDiscountType.value === "Medico" && selectedDoctorOffer.value) {
    return parseFloat(selectedDoctorOffer.value.percentage || 0);
  } else if (selectedDiscountType.value === "Recipe") {
    return parseFloat(currentPrescriptionDiscountPercentage.value || 0);
  }
  return 0;
});

// ─── Catálogo de Productos y Filtros Centralizados ───────────────────────────
const filterSearchQuery = ref("");
const selectedLaboratory = ref(null);
const selectedOrigin = ref(null);
const selectedCategory = ref(null);
const stockStatusFilter = ref(null);
const isStrictSearch = ref(false);
const sortBy = ref();
const orderBy = ref();
const page = ref(1);
const itemsPerPage = ref(10);
const currentGroupId = ref(null);

const tableOptions = computed(() => ({
  page: page.value,
  itemsPerPage: itemsPerPage.value,
  sortBy: sortBy.value ? [{ key: sortBy.value, order: orderBy.value || "asc" }] : [],
}));

const {
  products,
  totalProduct,
  loading,
  laboratories,
  origins,
  categories,
  isLoadingFilters,
  fetchProducts,
  fetchSelectOptions,
  handleClearFilters,
  handleClearSortOrder,
  updateTableOptions,
  handleSort,
  fetchGroupProducts,
  fetchFailuresProducts,
  handleBackFromGroupView,
  handleExternalSort,
} = useTpvCatalog({
  filterSearchQuery,
  selectedLaboratory,
  selectedOrigin,
  selectedCategory,
  stockStatusFilter,
  isStrictSearch,
  sortBy,
  orderBy,
  page,
  itemsPerPage,
  currentGroupId,
  tableOptions,
});

// Modales y Detalles
const isSaving = ref(false);
const showRegisterClientModal = ref(false);
const quotationDetails = ref(null);
const isPrinting = ref(false);
const barcodeSearchQuery = ref("");
let barcodeInputTimer;
const BARCODE_LENGTH_THRESHOLD = 10;

const selectedPack = ref(null);
const showPackDetailsModal = ref(false);

// Platos / Restaurante
const dishes = ref([]);
const dishesLoading = ref(false);
const dishFilterQuery = ref("");
const selectedDishCategory = ref(null);
const activeTab = ref("products");

// ─── Monedas Disponibles ──────────────────────────────────────────────────────
const availableCurrencies = computed(() => {
  const defaults = ["USD", "BS", "COP"];
  const configured = brandingStore.settings?.tpv_payment_methods;
  if (!configured) return defaults;
  return defaults.filter((currency) => configured[currency] && configured[currency].enabled !== false);
});

const defaultCurrency = computed(() => brandingStore.settings?.default_currency || "USD");
const selectedDisplayCurrency = ref(defaultCurrency.value);

watch(
  defaultCurrency,
  (newVal) => {
    if (newVal && availableCurrencies.value.includes(newVal)) {
      selectedDisplayCurrency.value = newVal;
    }
  },
  { immediate: true },
);

// ─── Cálculos de Totales ──────────────────────────────────────────────────────
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

const totalAmountBs = computed(() => {
  let total = 0;
  quotationItems.value.forEach((item) => {
    const priceBs = item.price_bs || 0;
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;
    total += priceBs * quantity * (1 + taxRate);
  });
  return total;
});

const totalAmountUsd = computed(() => {
  let total = 0;
  quotationItems.value.forEach((item) => {
    const priceUsd = item.price || 0;
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;
    total += priceUsd * quantity * (1 + taxRate);
  });
  return total;
});

const totalAmountCop = computed(() => {
  let total = 0;
  quotationItems.value.forEach((item) => {
    const priceCop = item.price_cop || 0;
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;
    let priceWithIva = priceCop * (1 + taxRate);
    priceWithIva = roundUpToNearestHundred(priceWithIva);
    total += priceWithIva * quantity;
  });
  return total;
});

const totalEligibleBaseAmount = computed(() => {
  let total = 0;
  quotationItems.value.forEach((item) => {
    if (item.discount_type === "expiration") return;
    const basePrice = getItemPriceByCurrency(item, selectedDisplayCurrency.value, true);
    const quantity = item.selectedQuantity || 0;
    total += basePrice * quantity;
  });
  return total;
});

const totalCompanyDiscountAmount = computed(() => {
  if (selectedDiscountType.value === "Empresa" && selectedCompanyId.value) {
    const offer = activeCompanyOffers.value.find((o) => o.value === selectedCompanyId.value);
    const porcentaje = parseFloat(offer?.current_discount || 0);
    if (porcentaje > 0) {
      return totalEligibleBaseAmount.value * (porcentaje / 100);
    }
  }
  return 0;
});

const totalDoctorDiscountAmount = computed(() => {
  if (selectedDiscountType.value === "Medico" && selectedDoctorOffer.value) {
    const porcentaje = parseFloat(selectedDoctorOffer.value.percentage || 0);
    if (porcentaje > 0) {
      return totalEligibleBaseAmount.value * (porcentaje / 100);
    }
  }
  return 0;
});

const totalRecipeDiscountAmount = computed(() => {
  if (selectedDiscountType.value === "Recipe") {
    const porcentaje = parseFloat(currentPrescriptionDiscountPercentage.value || 0);
    if (porcentaje > 0) {
      return totalEligibleBaseAmount.value * (porcentaje / 100);
    }
  }
  return 0;
});

// ─── Payload para Envío al Backend ────────────────────────────────────────────
const buildPayload = computed(() => {
  let productsUsd = 0;
  let ivaUsd = 0;

  quotationItems.value.forEach((item) => {
    const priceUsd = item.price || 0;
    const q = item.selectedQuantity || 0;
    const t = item.taxRate || 0;
    productsUsd += priceUsd * q;
    ivaUsd += priceUsd * q * t;
  });

  const grandTotal = productsUsd + ivaUsd;

  return {
    total_amount_usd: productsUsd,
    total_iva_usd: ivaUsd,
    grand_total_usd: grandTotal,
    currency: selectedDisplayCurrency.value,
    client_id: selectedClient.value?.id || null,
    discount_type: selectedDiscountType.value || null,
    discount_percentage: globalDiscountPercentage.value || 0,
    products: quotationItems.value.map((item) => ({
      id: item.id || null,
      dish_id: item.dish_id || null,
      quantity: item.selectedQuantity,
      price: item.price || 0,
      base_price: item.base_price || 0,
      discount_percentage: item.appliedDiscountPercentage || item.discount_percentage || 0,
    })),
  };
});

// ─── Carga de Configuración General ──────────────────────────────────────────
const fetchGeneralSettings = async () => {
  try {
    const { data } = await axios.get("/general-settings");
    const settings = data.data || data;
    enableDishes.value = settings.enable_dishes !== undefined ? !!settings.enable_dishes : true;
    isRestaurant.value = settings.quotation_style === "restaurant";
    if (isRestaurant.value && enableDishes.value) {
      activeTab.value = "menu";
    }
  } catch (error) {
    console.error("Error al cargar configuración", error);
  }
};

const fetchDishes = async () => {
  if (!enableDishes.value) return;
  dishesLoading.value = true;
  try {
    const { data } = await axios.get("/dishes", {
      params: { status: 1, q: dishFilterQuery.value || undefined },
    });
    dishes.value = Array.isArray(data.data) ? data.data : data;
  } catch (error) {
    console.error("[TPV] Error al cargar platos:", error);
  } finally {
    dishesLoading.value = false;
  }
};

// ─── Agregar Items al Carrito de Cotización ───────────────────────────────────
const addProductToQuotation = async ({ productId, quantity, productData = null }) => {
  if (quantity <= 0) {
    toast.error("La cantidad a agregar debe ser mayor que cero.");
    return;
  }

  try {
    let productDetails = productData;
    if (!productDetails) {
      const response = await axios.get(`/tpv/quotation/${productId}`);
      productDetails = response.data;
    }

    const availableQuantity = productDetails.valid_stock_sum ?? 0;
    if (quantity > availableQuantity && availableQuantity > 0) {
      toast.error(
        `No hay suficiente stock para "${productDetails.name}". Disponible: ${availableQuantity}. Solicitado: ${quantity}.`,
      );
      return;
    }

    const existingItemIndex = quotationItems.value.findIndex(
      (item) => item.id === productId || item.product_id === productId,
    );
    if (existingItemIndex !== -1) {
      const currentSelectedQuantity = quotationItems.value[existingItemIndex].selectedQuantity;
      const newTotalSelectedQuantity = currentSelectedQuantity + quantity;

      if (newTotalSelectedQuantity > availableQuantity && availableQuantity > 0) {
        toast.warning(`Ya se agregó la cantidad máxima disponible de "${productDetails.name}"`);
        quotationItems.value[existingItemIndex].selectedQuantity = availableQuantity;
      } else {
        quotationItems.value[existingItemIndex].selectedQuantity = newTotalSelectedQuantity;
        toast.success(`Cantidad de "${productDetails.name}" incrementada a ${newTotalSelectedQuantity}.`);
      }
    } else {
      if (!productDetails.id || !productDetails.name) {
        toast.error("El producto no tiene la información completa necesaria.");
        return;
      }

      const rawUsd = parseFloat(productDetails.sale_price ?? productDetails.price ?? 0);
      const rawBs = parseFloat(productDetails.price_bs ?? 0);
      const rawCop = parseFloat(productDetails.price_cop ?? 0);
      const prodPct = parseFloat(productDetails.discount_percentage || productDetails.discount_percentage_expiration || 0);

      // Descuento global actualmente activo en la orden
      const currentGlobalPct = globalDiscountPercentage.value || 0;
      const isExpiration = productDetails.discount_type === "expiration" || productDetails.is_expiration_discount;
      const effectiveDiscountPct = isExpiration ? prodPct : Math.max(prodPct, currentGlobalPct);
      const discountFactor = effectiveDiscountPct > 0 ? 1 - effectiveDiscountPct / 100 : 1;

      const itemToAdd = {
        id: productDetails.id,
        product_id: productDetails.id,
        dish_id: null,
        title: productDetails.name || "Producto sin nombre",
        active_ingredient: productDetails.active_ingredient || null,
        itemCode: productDetails.barcode || null,
        price: rawUsd * discountFactor,
        price_bs: rawBs * discountFactor,
        price_cop: Math.round(rawCop * discountFactor),
        base_price: rawUsd,
        base_price_bs: rawBs,
        base_price_cop: rawCop,
        original_price_usd: rawUsd,
        original_price_bs: rawBs,
        original_price_cop: rawCop,
        availableQuantity: availableQuantity,
        selectedQuantity: quantity,
        laboratory: productDetails.laboratory?.name || productDetails.laboratory_name || "Genérico",
        laboratory_name: productDetails.laboratory?.name || productDetails.laboratory_name || "Genérico",
        taxRate: productDetails.iva == 1 ? 0.16 : 0,
        discount_percentage: prodPct,
        discount_type: productDetails.discount_type || null,
        discount_source_id: productDetails.discount_source_id || null,
        discountApplied: effectiveDiscountPct > 0,
        discountSource: effectiveDiscountPct === prodPct ? (productDetails.discount_type || "individual") : selectedDiscountType.value,
        discountSourceId: effectiveDiscountPct === prodPct ? productDetails.discount_source_id : null,
        appliedDiscountPercentage: effectiveDiscountPct,
        is_dish: false,
      };

      quotationItems.value.push(itemToAdd);
      toast.success(`"${itemToAdd.title}" agregado a la cotización.`);
    }
  } catch (error) {
    console.error("Error al agregar producto a cotización:", error);
    toast.error("Error al agregar el producto a la cotización.");
  }
};

const handleAddDishToQuotation = async ({ dish, quantity }) => {
  if (quantity <= 0) return;

  const existingItemIndex = quotationItems.value.findIndex(
    (item) => item.dish_id === dish.id && item.is_dish,
  );

  if (existingItemIndex !== -1) {
    quotationItems.value[existingItemIndex].selectedQuantity += quantity;
    toast.success(`Cantidad de "${dish.name}" incrementada a ${quotationItems.value[existingItemIndex].selectedQuantity}.`);
  } else {
    const unitPrice = parseFloat(dish.designated_price) || parseFloat(dish.sale_price) || 0;
    const unitPriceBs = parseFloat(dish.price_bs) || unitPrice;
    const unitPriceCop = parseFloat(dish.price_cop) || unitPrice;

    const itemToAdd = {
      id: null,
      product_id: null,
      dish_id: dish.id,
      title: dish.name,
      active_ingredient: dish.active_ingredient || (dish.category?.name || "Plato"),
      itemCode: null,
      price: unitPrice,
      price_bs: unitPriceBs,
      price_cop: unitPriceCop,
      base_price: unitPrice,
      base_price_bs: unitPriceBs,
      base_price_cop: unitPriceCop,
      original_price_usd: unitPrice,
      original_price_bs: unitPriceBs,
      original_price_cop: unitPriceCop,
      availableQuantity: 9999,
      selectedQuantity: quantity,
      laboratory: dish.laboratory_name || (dish.category?.name || "Plato"),
      taxRate: 0,
      pack_id: null,
      discount_percentage: 0,
      discount_type: null,
      discount_source_id: null,
      discountApplied: false,
      appliedDiscountPercentage: 0,
      is_dish: true,
    };
    quotationItems.value.push(itemToAdd);
    toast.success(`"${itemToAdd.title}" agregado a la cotización.`);
  }
};

const handleViewPackDetails = async (item) => {
  try {
    const response = await axios.get(`/tpv/promotions/product-packs/${item.id}`);
    selectedPack.value = response.data.data;
    showPackDetailsModal.value = true;
  } catch (error) {
    console.error("Error al obtener detalles del pack:", error);
  }
};

const handleAddPackToQuotation = async ({ pack, quantity }) => {
  let configStr = pack.pack_config;
  if (!configStr) {
    const itemWithConfig = quotationItems.value.find(
      (i) => i.pack_id === pack.id && i.original_pack_config,
    );
    configStr = itemWithConfig?.original_pack_config;
  }

  if (!configStr) {
    console.error("No se encontró la configuración del pack ID:", pack.id);
    return;
  }

  try {
    const productsToAdd = JSON.parse(configStr);
    for (const [productId, config] of Object.entries(productsToAdd)) {
      const unitsPerPack = typeof config === "object" ? config.quantity || 1 : config;
      await addProductToQuotation({
        productId: parseInt(productId),
        quantity: unitsPerPack * quantity,
      });
    }
    toast.success("Pack agregado a la cotización.");
  } catch (e) {
    console.error("Error al procesar pack:", e);
    toast.error("Error al agregar el pack a la cotización.");
  }
};

const addProductToQuotationByBarcode = async (barcode) => {
  try {
    const response = await axios.get(`/barcode/${barcode}`);
    const productDetails = response.data;
    if (productDetails && productDetails.id) {
      await addProductToQuotation({ productId: productDetails.id, quantity: 1, productData: productDetails });
    }
  } catch (error) {
    console.error("Error al agregar por código de barras:", error);
    toast.error("Producto no encontrado o error al agregar por código de barras.");
  }
};

watch(barcodeSearchQuery, (newValue) => {
  clearTimeout(barcodeInputTimer);
  if (!newValue) return;
  if (newValue.length >= BARCODE_LENGTH_THRESHOLD) {
    barcodeInputTimer = setTimeout(async () => {
      await addProductToQuotationByBarcode(newValue);
      barcodeSearchQuery.value = "";
    }, 300);
  }
});

const removeQuotationItem = (productId) => {
  quotationItems.value = quotationItems.value.filter((item) => item.id !== productId && item.product_id !== productId);
  toast.success("Producto eliminado exitosamente.");
};

const removeQuotation = () => {
  quotationItems.value = [];
};

const handleCurrencyChanged = (newCurrency) => {
  selectedDisplayCurrency.value = newCurrency;
};

// ─── Guardar e Imprimir ───────────────────────────────────────────────────────
const saveQuotation = async () => {
  if (quotationItems.value.length === 0) {
    throw new Error("No hay productos en la cotización para guardar.");
  }

  isSaving.value = true;
  try {
    const response = await axios.post("/tpv/quotations", buildPayload.value);
    quotationDetails.value = response.data.quotation;
    return response.data.quotation;
  } catch (error) {
    console.error("Error al guardar la cotización:", error);
    throw error;
  } finally {
    isSaving.value = false;
  }
};

const saveAndPrintQuotation = async () => {
  if (quotationItems.value.length === 0) {
    toast.error("No hay productos en la cotización para guardar e imprimir.");
    return;
  }

  isSaving.value = true;
  try {
    const response = await axios.post("/tpv/quotations", buildPayload.value);
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
                @page { size: 54mm auto; margin: 0; padding: 0; }
                body { width: 54mm !important; max-width: 54mm !important; margin: 0 !important; padding: 2mm !important; font-family: 'Courier New', monospace !important; font-size: 10px !important; line-height: 1.2 !important; }
                * { max-width: 50mm !important; box-sizing: border-box !important; word-wrap: break-word !important; }
                .no-print, button, .actions { display: none !important; }
                table { width: 100% !important; border-collapse: collapse !important; }
                td, th { padding: 1px 0 !important; font-size: 9px !important; }
              }
              @media screen {
                body { width: 54mm; border: 1px dashed #ccc; margin: 0; padding: 2mm; font-family: 'Courier New', monospace; font-size: 10px; line-height: 1.2; }
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
            printWindow.document.write(`<link rel="stylesheet" href="${sheet.href}">`);
          }
        } catch (e) {
          console.warn("No se pudo acceder a la hoja de estilo:", sheet.href || sheet, e);
        }
      }

      printWindow.document.write(`</head><body>`);
      printWindow.document.write(printContents.innerHTML);
      printWindow.document.write(`</body></html>`);
      printWindow.document.close();
      printWindow.focus();

      printWindow.onload = function () {
        setTimeout(() => {
          printWindow.print();
          printWindow.close();
        }, 100);
      };
    } else {
      window.print();
    }

    setTimeout(() => {
      removeQuotation();
      quotationDetails.value = null;
      isPrinting.value = false;
    }, 500);
  } catch (error) {
    console.error("Error al guardar o imprimir la cotización:", error);
    toast.error("Error al guardar o imprimir la cotización.");
    isPrinting.value = false;
  } finally {
    isSaving.value = false;
  }
};

const handleCleanAfterSave = () => {
  handleClearFilters();
  handleClearSortOrder();
  removeQuotation();
  page.value = 1;
  selectedClient.value = null;
  clientIdentification.value = "";
};

const handleClientRegistered = (client) => {
  selectedClient.value = client;
  showRegisterClientModal.value = false;
  toast.success("Cliente registrado exitosamente.");
};

onMounted(async () => {
  await fetchGeneralSettings();
  fetchExchangeRates();
  fetchSelectOptions();
  fetchProducts();
  fetchDoctorOffers();
  fetchPrescriptionOffers();
  fetchCompanyOffers();
  loadQuotationFromLocalStorage();
});

onUnmounted(() => {
  clearTimeout(barcodeInputTimer);
});
</script>

<template>
  <div>
    <!-- Fila Superior: Resumen de Totales y Carrito de Cotización -->
    <VRow class="mb-4">
      <VCol cols="12" sm="12" md="6">
        <QuotationCard
          :total-products-amount="totalProductsAmount"
          :total-iva-amount="totalIVAAmount"
          :total-quotation-amount="totalQuotationAmount"
          :quotation-items="quotationItems"
          :selected-display-currency="selectedDisplayCurrency"
          :company-discount-total="totalCompanyDiscountAmount"
          :doctor-discount-total="totalDoctorDiscountAmount"
          :recipe-discount-total="totalRecipeDiscountAmount"
          :selected-discount-type="selectedDiscountType"
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
          :is-saving="isSaving"
          v-model:selected-discount-type="selectedDiscountType"
          :active-doctor-offers="activeDoctorOffers"
          :prescription-discount-percentage="currentPrescriptionDiscountPercentage"
          :active-company-offers="activeCompanyOffers"
          :global-discount-percentage="globalDiscountPercentage"
          :is-restaurant="isRestaurant"
          @doctor-discount-selected="handleDoctorDiscountSelected"
          @prescription-file-selected="handlePrescriptionFileSelected"
          @company-discount-selected="handleCompanyDiscountSelected"
          @remove-quotation-product="removeQuotationItem"
          @remove="removeQuotation"
          @print-quotation="saveAndPrintQuotation"
          @search-client="fetchSearchedClient"
          @clean-post-save="handleCleanAfterSave"
        />
      </VCol>
    </VRow>

    <!-- Sección de Catálogo Idéntica a /tpv/orderUser -->
    <TpvCatalogSection
      v-model:filter-search-query="filterSearchQuery"
      v-model:selected-laboratory="selectedLaboratory"
      v-model:selected-origin="selectedOrigin"
      v-model:selected-category="selectedCategory"
      v-model:stock-status-filter="stockStatusFilter"
      v-model:is-strict-search="isStrictSearch"
      :laboratories="laboratories || []"
      :origins="origins || []"
      :categories="categories || []"
      :is-restaurant="isRestaurant || false"
      :is-loading-filters="isLoadingFilters || false"
      :sort-by="sortBy"
      :order-by="orderBy"
      @handle-clear-filters="handleClearFilters"
      @handle-clear-sort-order="handleClearSortOrder"
      @handle-external-sort="handleExternalSort"
      @handle-back-from-group-view="handleBackFromGroupView"
      :products="products || []"
      :loading="loading"
      :total-product="totalProduct || 0"
      v-model:items-per-page="itemsPerPage"
      v-model:page="page"
      :discount-min-products="0"
      :discount-max-products="0"
      :discount="globalDiscountPercentage || 0"
      :order-items="quotationItems || []"
      :table-options="tableOptions"
      :exchange-rates="exchangeRates"
      :selected-display-currency="selectedDisplayCurrency"
      @update-table-options="updateTableOptions"
      @add-product-to-order="addProductToQuotation"
      @handle-add-dish-to-order="handleAddDishToQuotation"
      @fetch-group-products="fetchGroupProducts"
      @fetch-failures-products="fetchFailuresProducts"
      @handle-view-pack-details="handleViewPackDetails"
      @handle-add-pack-to-order="handleAddPackToQuotation"
    />

    <!-- Modal de Detalles de Pack -->
    <PackDetailsModal
      v-model:is-dialog-visible="showPackDetailsModal"
      :pack="selectedPack"
    />

    <!-- Área de Impresión Térmica de Cotización -->
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

    <!-- Modal de Registro Rápido de Cliente -->
    <RegisterClientModal
      v-model="showRegisterClientModal"
      @client-registered="handleClientRegistered"
    />
  </div>
</template>

