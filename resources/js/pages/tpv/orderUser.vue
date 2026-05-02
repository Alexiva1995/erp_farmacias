<script setup>
import OpenOrderCard from "@/components/cards/OpenOrderCard.vue";
import OrderClienteCard from "@/components/cards/OrderClienteCard.vue";
import BuysModal from "@/components/dialogs/BuysModal.vue";
import RegisterClientModal from "@/components/dialogs/ClientFormDialoge.vue";
import PackDetailsModal from "@/components/dialogs/PackDetailsModal.vue";
import OrderFilters from "@/components/OrderFilters.vue";
import OrderProductsTable from "@/components/OrderProductsTable.vue";
import OrderTicketThermal54 from "@/components/OrderTicketThermal54.vue";
import { THERMAL_54MM_CSS } from "@/constants/thermalTicket54.js";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js";
import {
  computed,
  nextTick,
  onMounted,
  onUnmounted,
  reactive,
  ref,
  watch,
} from "vue";

const activeTab = ref("products");
const isSpecialTaxpayer = ref(false);
const allForeignSalesSpe = ref(false);
const foreignOrdersCount = ref(0);
const exchangeRates = ref({});
const packs = ref([]);
const totalPacks = ref(0);
const loadingPacks = ref(false);
const packsPage = ref(1);
const packsItemsPerPage = ref(10);
const showPackDetailsModal = ref(false);
const selectedPack = ref(null);

const products = ref([]);
const totalProduct = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(25);
const sortBy = ref();
const orderBy = ref();

const filterSearchQuery = ref("");
const selectedLaboratory = ref(null);
const selectedOrigin = ref(null);
const stockStatusFilter = ref(null);
const isStrictSearch = ref(false);
const discount = ref(0);
const discountMinProducts = ref(0);
const discountMaxProducts = ref(0);

const laboratories = ref([]);
const origins = ref([]);
const isLoadingFilters = ref(false);

const barcodeSearchQuery = ref("");
let barcodeInputTimer;
const BARCODE_LENGTH_THRESHOLD = 10;

const clientIdentification = ref("");
const showRegisterClientModal = ref(false);
const selectedClient = ref(null);
const isLoadingInitialOrder = ref(true);
const isPrinting = ref(false);

const selectedDisplayCurrency = ref("COP");

const recipeDiscountForPrint = ref(0);
const doctorDiscountForPrint = ref(0);
const companyDiscountForPrint = ref(0);
const discountTypeForPrint = ref(null);
const expirationDiscountForPrint = ref(0);
const speSurchargeAmount = ref(0);

const isFinishingOrder = ref(false);

const ratesLoaded = ref(false);
const isCurrencyChanging = ref(false);

const fetchExchangeRates = async () => {
  ratesLoaded.value = false;
  try {
    const response = await axios.get("/public/exchange-rates");
    if (response.status != 200) {
      throw new Error(`HTTP error! Status: ${response.status}`);
    }

    const apiRates = response.data;
    const formattedRates = {};
    apiRates.forEach((rateItem) => {
      const currencyCode = rateItem.currency_code;
      const rateValue = parseFloat(rateItem.rate);

      if (!formattedRates["USD"]) {
        formattedRates["USD"] = {};
      }
      formattedRates["USD"][currencyCode] = rateValue;
      if (!formattedRates[currencyCode]) {
        formattedRates[currencyCode] = {};
      }
      if (rateValue !== 0) {
        formattedRates[currencyCode]["USD"] = 1 / rateValue;
      }

      if (formattedRates["COP"] && formattedRates["BS"]) {
        formattedRates["COP"]["BS"] =
          parseFloat(formattedRates["USD"]["BS"]) /
          parseFloat(formattedRates["USD"]["COP"]);
        formattedRates["BS"]["COP"] =
          parseFloat(formattedRates["USD"]["COP"]) /
          parseFloat(formattedRates["USD"]["BS"]);
      }
    });

    exchangeRates.value = formattedRates;
    ratesLoaded.value = true;
    console.log("[ORDER_USER] Tasas de cambio cargadas:", exchangeRates.value);
  } catch (error) {
    console.error("[ORDER_USER] Error fetching exchange rates:", error);
    toast.error("No se pudieron cargar las tasas de cambio.");
  }
};

const getEffectiveRate = (fromCurrency, toCurrency) => {
  if (fromCurrency === toCurrency) return 1;

  const rates = exchangeRates.value?.[fromCurrency];
  if (!rates) return 0;

  // REGLA NEGOCIO: Si convertimos de USD a COP, usar COPC (Tasa Manual) si existe
  if (fromCurrency === "USD" && toCurrency === "COP" && rates["COPC"]) {
    return rates["COPC"];
  }

  return rates[toCurrency] || 0;
};

const newClientFormData = ref({
  id: null,
  identification_type: "",
  identification: "",
  name: "",
  last_name: "",
  email: "",
  phone: "",
  birthdate: "",
  company_id: null,
  address: "",
  is_spe: false,
});

const newClientFormErrors = reactive({
  id: "",
  identification: "",
  identification_type: "",
  name: "",
  last_name: "",
  email: "",
  phone: "",
  address: "",
  birthdate: "",
  company_id: "",
  is_spe: "",
});

const tableOptions = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
});

const companies = ref([]);

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const hasOpenOrder = ref(false);
const openOrderData = ref(null);
const orderData = ref(null);
const reservedOrderData = ref(null);
const pendingOpenOrder = ref(null); // Orden que será abierta tras imprimir.
const pendingQuotationProducts = ref([]); // Productos de cotización pendientes cuando no hay cliente.

const orderItems = ref([]);
const itemsToPrint = ref([]);
const TotalToPrint = ref(0);
const speSurchargeAmountPrint = ref(0);

const showBuysModal = ref(false);

const paymentsForPrint = ref([]);
const changeAmountForPrint = ref(0);
const changeAmountOriginForPrint = ref(0);
const creditAmountForPrint = ref(0);
const creditForPrint = ref(false);

const selectedDiscountType = ref(null);
const activeDoctorOffers = ref([]);
const selectedDoctorOffer = ref(null);
const loadingDoctorOffers = ref(false);

const activePrescriptionOffers = ref([]);
const prescriptionFile = ref(null);
const activeCompanyOffers = ref([]);
const selectedCompanyId = ref(null);

const selectedCompany = ref(null);
const selectedDoctor = ref(null);

const handleDoctorSelected = (id) => {
  selectedDoctor.value = id;
};

const currentPrescriptionDiscountPercentage = computed(() => {
  if (activePrescriptionOffers.value.length > 0) {
    return parseFloat(activePrescriptionOffers.value[0].discount_percentage);
  }
  return 0;
});

const currentGroupId = ref(null);

const fetchGeneralSettings = async () => {
  try {
    const { data } = await axios.get("/general-settings");
    isSpecialTaxpayer.value = data.special_taxpayer_status === "activa";
    allForeignSalesSpe.value = !!data.all_foreign_sales_spe;
  } catch (error) {
    console.error("Error al cargar configuración", error);
    toast.error("Error al cargar configuración");
  }
};

onMounted(async () => {
  await authStore.fetchUser();
  await fetchProducts();
});

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
    ...(currentGroupId.value !== null && {
      groupId: currentGroupId.value,
    }),
    isStrictSearch: isStrictSearch.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key],
  );

  try {
    const response = await axios.get("/tpv/order", { params });
    products.value = response.data.data;
    totalProduct.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los productos:", error);
    toast.error("Error al obtener los productos.");
  } finally {
    loading.value = false;
  }
};

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

const fetchDoctorOffers = async () => {
  loadingDoctorOffers.value = true;
  try {
    const response = await axios.get("/tpv/promotions/doctor-offer", {
      params: {
        per_page: 100,
        sort_by: "id",
        sort_order: "desc",
      },
    });

    if (response.data.success) {
      activeDoctorOffers.value = response.data.data.map((offer) => ({
        id: offer.id,
        title: `${offer.doctor.name} - ${offer.discount}%`,
        value: offer.id,
        percentage: parseFloat(offer.discount),
        doctor_id: offer.doctor_id,
      }));
    }
  } catch (error) {
    console.error("Error fetching doctor offers:", error);
    toast.error("Error al cargar las ofertas de médicos.");
  } finally {
    loadingDoctorOffers.value = false;
  }
};

const fetchPrescriptionOffers = async () => {
  try {
    const response = await axios.get("/tpv/promotions/prescription-offer", {
      params: {
        is_active: true,
        per_page: 1,
        sort_by: "discount_percentage",
        sort_order: "desc",
      },
    });
    if (response.data.success) {
      activePrescriptionOffers.value = response.data.data;
    }
  } catch (error) {
    console.error("Error fetching prescription offers:", error);
  }
};

const fetchCompanyOffers = async (companyId = null) => {
  try {
    const params = {
      is_active: true,
      per_page: 100,
      sort_by: "id",
      order_by: "desc", // Cambiado de sort_order a order_by para tu Laravel
    };

    if (companyId) {
      params.search = companyId;
    }

    const response = await axios.get("/tpv/promotions/company-offer", {
      params,
    });

    if (response.data && response.data.data) {
      activeCompanyOffers.value = response.data.data.map((offer) => {
        const scales = offer.scales || [];
        // Calculate min and max percentage for display
        let discountText = "";
        if (scales.length > 0) {
          const percentages = scales.map((s) =>
            parseFloat(s.discount_percentage),
          );
          const minP = Math.min(...percentages);
          const maxP = Math.max(...percentages);
          discountText = minP === maxP ? `${minP}%` : `${minP}-${maxP}%`;
        }

        return {
          title: `${offer.company?.name || "N/A"} ${
            discountText ? "- " + discountText : ""
          }`,
          value: offer.company_id,
          scales: scales,
          id: offer.id,
          current_discount: offer.company?.current_discount || 0,
        };
      });
    }
  } catch (error) {
    console.error("Error fetching company offers:", error);
  }
};

/*const handlePrescriptionFileSelected = (file) => {
  prescriptionFile.value = file;
  if (file && activePrescriptionOffers.value.length > 0) {
    // Apply discount from the first active offer (highest discount due to sort)
    const offer = activePrescriptionOffers.value[0];

    orderItems.value = orderItems.value.map((item) => {
      if (!item.originalPrice) {
        item.originalPrice = item.price;
        item.originalPriceBs = item.price_bs;
        item.originalPriceCop = item.price_cop;
      }

      const discountFactor = 1 - offer.discount_percentage / 100;

      return {
        ...item,
        price: item.originalPrice * discountFactor,
        price_bs: item.originalPriceBs * discountFactor,
        price_cop: item.originalPriceCop * discountFactor,

        discountApplied: true, // Standardize flag
        discountSource: "prescription",
        discountSourceId: offer.id,
        appliedDiscountPercentage: offer.discount_percentage,
      };
    });
    toast.success(
      `Descuento de receta del ${offer.discount_percentage}% aplicado.`
    );
  } else {
    // Revert changes if file removed or no offer
    handleDoctorDiscountSelected(null); // Reuse revert logic or make a shared revert function
    // Since handleDoctorDiscountSelected(null) reverts to originalPrice, it works for this too
    // BUT we should be careful if we want to support stacking (likely not based on UI)
    // For now, assuming mutually exclusive discounts based on selectedDiscountType
  }
};*/

// Handshake idéntico a handleDoctorDiscountSelected: validación + applyDiscount
const handlePrescriptionFileSelected = (file) => {
  prescriptionFile.value = file;
  validateAndApplyPrescriptionDiscount();
  if (file && activePrescriptionOffers.value.length > 0) {
    const porcentaje = parseFloat(
      activePrescriptionOffers.value[0].discount_percentage || 0,
    );
    if (porcentaje > 0) {
      toast.success(`Descuento de receta del ${porcentaje}% aplicado.`);
    } else {
      toast.info("No hay un descuento de receta activo.");
    }
  } else if (selectedDiscountType.value === "Recipe") {
    toast.info("Descuento de receta removido.");
  }
};

const handleDoctorDiscountSelected = (offerId) => {
  const offer = activeDoctorOffers.value.find((o) => o.value === offerId);
  selectedDoctorOffer.value = offer;
  validateAndApplyDoctorDiscount();
  if (offer) {
    toast.success(
      `Descuento de médico ${offer.percentage}% habilitado para esta orden.`,
    );
  } else {
    toast.info("Descuento de médico removido.");
  }
};

const validateAndApplyDoctorDiscount = () => {
  if (activeDoctorOffers.value.length === 0) {
    return;
  }

  if (!selectedDoctorOffer.value) {
    if (selectedDiscountType.value === "Medico") {
      removeDiscount();
    }
    return;
  }

  const offer = activeDoctorOffers.value.find(
    (o) => o.value === selectedDoctorOffer.value.value,
  );

  if (!offer) {
    selectedDoctorOffer.value = null;
    return;
  }

  const porcentaje = parseFloat(offer.percentage || 0);
  if (porcentaje > 0) {
    applyDiscount(porcentaje, {
      type: "doctor",
      name: offer.title,
      id: offer.id,
    });
  } else {
    removeDiscount();
    selectedDoctorOffer.value = null;
    toast.info("Esta oferta de médico no tiene un descuento configurado.");
  }
};

// Espejo exacto de validateAndApplyDoctorDiscount para Recipe
const validateAndApplyPrescriptionDiscount = () => {
  if (activePrescriptionOffers.value.length === 0) {
    return;
  }

  if (!prescriptionFile.value) {
    if (selectedDiscountType.value === "Recipe") {
      removeDiscount();
    }
    return;
  }

  const offer = activePrescriptionOffers.value[0];
  const porcentaje = parseFloat(offer.discount_percentage || 0);

  if (porcentaje > 0) {
    applyDiscount(porcentaje, {
      type: "recipe",
      name: "Recipe médica",
      id: offer.id,
    });
  } else {
    removeDiscount();
  }
};

watch(
  () => selectedClient.value,
  async (newCliente, oldCliente) => {
    console.log("[ORDER_USER] Watcher selectedClient ejecutado:", {
      newCliente,
      oldCliente,
    });
    if (!newCliente) {
      console.log("[ORDER_USER] No hay cliente, saliendo del watcher");
      return;
    }
    if (newCliente?.id === oldCliente?.id) {
      console.log("[ORDER_USER] Mismo cliente, saliendo del watcher");
      return;
    }

    try {
      if (newCliente.company_id) {
        console.log(
          "[ORDER_USER] Cliente tiene company_id:",
          newCliente.company_id,
        );
        await fetchCompanyOffers(newCliente.company_id);
        selectedDiscountType.value = "Empresa";
        selectedCompany.value = newCliente.company_id;
        console.log("[ORDER_USER] Ofertas de empresa cargadas desde watcher");
      } else {
        console.log(
          "[ORDER_USER] Cliente no tiene company_id, cargando ofertas generales",
        );
        selectedCompany.value = null;
        await fetchCompanyOffers();
      }
    } catch (error) {
      console.error("[ORDER_USER] Error en watcher de selectedClient:", error);
    }
  },
  { immediate: false },
);

const currentGlobalDiscountDetails = computed(() => {
  if (selectedDiscountType.value === "Empresa" && selectedCompany.value) {
    const offer = activeCompanyOffers.value.find(
      (o) => o.value === selectedCompany.value,
    );
    if (offer && offer.current_discount > 0) {
      return {
        type: "Empresa",
        percentage: parseFloat(offer.current_discount),
        label: "Empresa",
      };
    }
  }

  if (selectedDiscountType.value === "Medico" && selectedDoctorOffer.value) {
    const offer = activeDoctorOffers.value.find(
      (o) => o.value === selectedDoctorOffer.value.value,
    );

    if (offer && offer.percentage > 0) {
      return {
        type: "Medico",
        percentage: parseFloat(offer.percentage),
        label: "Médico",
      };
    }
  }

  if (
    selectedDiscountType.value === "Recipe" &&
    currentPrescriptionDiscountPercentage.value > 0
  ) {
    return {
      type: "Recipe",
      percentage: parseFloat(currentPrescriptionDiscountPercentage.value),
      label: "Recipe",
    };
  }
  return null;
});

const handleCompanyDiscountSelected = async (companyId) => {
  selectedCompanyId.value = companyId;
  if (companyId) {
    await fetchCompanyOffers(companyId);
  }
  validateAndApplyCompanyDiscount();
};

const validateAndApplyCompanyDiscount = () => {
  if (activeCompanyOffers.value.length === 0) {
    console.warn("Validación abortada: No hay ofertas cargadas aún.");
    return;
  }

  if (!selectedCompanyId.value) {
    if (selectedDiscountType.value === "Empresa") {
      removeDiscount();
    }
    return;
  }

  const offer = activeCompanyOffers.value.find(
    (o) => o.value === selectedCompanyId.value,
  );

  if (!offer) {
    console.warn("No se encontró oferta para el ID:", selectedCompanyId.value);
    selectedCompanyId.value = null;
    return;
  }

  const porcentaje = parseFloat(offer.current_discount || 0);
  if (porcentaje > 0) {
    applyDiscount(porcentaje, {
      type: "company",
      name: offer.title,
      id: offer.id,
    });
    toast.success(
      `Descuento de empresa ${porcentaje}% habilitado para esta orden.`,
    );
  } else {
    removeDiscount();
    selectedCompanyId.value = null;
    toast.info(
      `Esta empresa no cuenta con un descuento activo para el periodo actual.`,
    );
  }
};

/*const validateAndApplyCompanyDiscount = () => {
  if (!selectedCompanyId.value) {
    if (selectedDiscountType.value === "Empresa") {
      removeDiscount();
    }
    return;
  }

  const offer = activeCompanyOffers.value.find(
    (o) => o.value === selectedCompanyId.value
  );
  if (!offer) return;

  // Calculate total quantity of items (excluding filtered items if any logic existed for that, but usually total quantity)
  // Assuming company discount applies to ALL items or requires total volume of order
  let totalQuantity = 0;
  orderItems.value.forEach((item) => {
    totalQuantity += item.selectedQuantity || 0;
  });

  // Find matching scale
  // scale: { min_volume, max_volume, discount_percentage }
  const validScale = offer.scales.find(
    (scale) =>
      totalQuantity >= scale.min_volume && totalQuantity <= scale.max_volume
  );

  if (validScale) {
    applyDiscount(parseFloat(validScale.discount_percentage), {
      type: "company",
      name: offer.title,
      id: offer.id,
    });
    // toast.success(`Descuento de empresa ${validScale.discount_percentage}% aplicado.`); // Optional: prevent spamming toasts on quantity change
  } else {
    removeDiscount();
    toast.warning(
      `La cantidad total (${totalQuantity}) no cumple con los rangos de volumen para el descuento de esta empresa.`
    );
  }
};*/

// Precio Final = Original * (1 - Max(DescuentoGlobal, DescuentoIndividual) / 100)
// Usado por Médico, Recipe y Empresa. Evita doble descuento.
const applyDiscount = (percentage, source) => {
  orderItems.value = orderItems.value.map((item) => {
    if (item.discount_type === "expiration" || item.pack_id) {
      return item;
    }

    const rawUsd = item.original_price_usd;
    const rawBs = item.original_price_bs;
    const rawCop = item.original_price_cop;

    const productPct = parseFloat(item.discount_percentage || 0);
    const bestPct = Math.max(percentage, productPct);
    const discountFactor = bestPct > 0 ? 1 - bestPct / 100 : 1;

    return {
      ...item,
      price: rawUsd * discountFactor,
      price_bs: rawBs * discountFactor,
      price_cop: rawCop * discountFactor,
      discountApplied: true,
      discountSource:
        bestPct === productPct
          ? item.discount_type || "individual"
          : source.type,
      discountSourceId:
        bestPct === productPct ? item.discount_source_id : source.id,
      appliedDiscountPercentage: bestPct,
    };
  });
};

const removeDiscount = () => {
  orderItems.value = orderItems.value.map((item) => {
    // Exclude expiration or pack items from global discount removal
    if (item.discount_type === "expiration" || item.pack_id) {
      return item;
    }

    // Restaurar al precio base (con descuento individual/categoría si aplica)
    return {
      ...item,
      price: item.base_price,
      price_bs: item.base_price_bs,
      price_cop: item.base_price_cop,
      discountApplied: false,
      discountSource: null,
      discountSourceId: null,
      appliedDiscountPercentage: 0,
    };
  });
};

// Refactor handlePrescriptionFileSelected to use common apply/remove
// Leaving it separate for now as it was already implemented, but could reuse applyDiscount

watch(selectedDiscountType, (newValue) => {
  // Limpiar estados de otros tipos pero NO llamar a removeDiscount aún
  if (newValue !== "Medico") {
    selectedDoctorOffer.value = null;
  }
  if (newValue !== "Recipe") {
    prescriptionFile.value = null;
  }
  if (newValue !== "Empresa") {
    selectedCompanyId.value = null;
  }

  // Siempre remover descuento previo para limpiar el estado de los ítems
  removeDiscount();

  // Si hay un nuevo tipo, intentar aplicarlo
  if (newValue === "Medico" && selectedDoctorOffer.value) {
    validateAndApplyDoctorDiscount();
  } else if (newValue === "Recipe" && prescriptionFile.value) {
    validateAndApplyPrescriptionDiscount();
  } else if (newValue === "Empresa" && selectedCompanyId.value) {
    validateAndApplyCompanyDiscount();
  }
});

// Watch orderItems for quantity changes (mapped to string key) to re-validate company discount
// This avoids infinite loop since applying discount (changing prices) won't change the key
// Watcher para validar descuento de empresa cuando cambian las cantidades
// Usamos un debounce para evitar bucles infinitos
let discountValidationTimer;
watch(
  () =>
    orderItems.value
      .map((i) => `${i.product_id}:${i.selectedQuantity}`)
      .join("|"),
  (newVal) => {
    if (selectedDiscountType.value === "Empresa" && selectedCompanyId.value) {
      clearTimeout(discountValidationTimer);
      discountValidationTimer = setTimeout(() => {
        validateAndApplyCompanyDiscount();
      }, 300);
    }
    if (selectedDiscountType.value === "Medico" && selectedDoctorOffer.value) {
      clearTimeout(discountValidationTimer);
      discountValidationTimer = setTimeout(() => {
        validateAndApplyDoctorDiscount();
      }, 300);
    }
    if (
      selectedDiscountType.value === "Recipe" &&
      prescriptionFile.value &&
      activePrescriptionOffers.value.length > 0
    ) {
      clearTimeout(discountValidationTimer);
      discountValidationTimer = setTimeout(() => {
        validateAndApplyPrescriptionDiscount();
      }, 300);
    }
  },
);

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
    isStrictSearch,
    currentGroupId,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      try {
        fetchProducts();
      } catch (error) {
        console.error("Error en watcher de productos:", error);
      }
    }, 300);
  },
  { deep: false },
);

const consultAllcomapanies = async () => {
  const companiesResponse = await axios.get("/crm/companies");
  companies.value = companiesResponse.data.data;
};

const formatOrderItemForFrontend = (backendItem) => {
  const product = backendItem.product;
  const availableQuantity = product.lots_sum_quantity ?? 0;

  const discountFactor =
    backendItem.discount_type === "expiration" &&
    backendItem.discount_percentage
      ? 1 - backendItem.discount_percentage / 100
      : 1;

  const taxMultiplier = product.iva == 1 ? 0.16 : 0;

  // Precio original del producto
  const originalPrice = parseFloat(product.sale_price) || 0;
  const originalPriceBs = parseFloat(product.price_bs) || 0;
  const originalPriceCop = parseFloat(product.price_cop) || 0;

  // Precio con descuento (unit_price_usd del pack o precio normal)
  const discountedPrice =
    parseFloat(backendItem.unit_price_usd) || originalPrice * discountFactor;

  /*const discountedPriceBs = backendItem.unit_cost
    ? originalPriceBs * (discountedPrice / originalPriceBs)
    : originalPriceBs * discountFactor;

  const discountedPriceCop = backendItem.unit_cost
    ? originalPriceCop * (discountedPrice / originalPriceCop)
    : originalPriceCop * discountFactor;*/

  let priceFactor = discountFactor; // Por defecto el factor de vencimiento (1 o menos)

  if (backendItem.unit_price_usd) {
    const unitPriceUsd = parseFloat(backendItem.unit_price_usd);
    // El factor de precio siempre debe ser la relación entre el precio USD actual (con descuento) y el precio USD original
    priceFactor = originalPrice > 0 ? unitPriceUsd / originalPrice : 1;
  }

  // 3. Aplicar el factor resultante a todas las monedas para mantener la paridad
  const discountedPriceBs = originalPriceBs * priceFactor;
  const discountedPriceCop = originalPriceCop * priceFactor;

  // Determinar si hay descuento de pack (precio personalizado diferente al original)
  const hasPackDiscount =
    backendItem.pack_id &&
    backendItem.unit_price_usd &&
    Math.abs(parseFloat(backendItem.unit_price_usd) - originalPrice) > 0.01;

  return {
    order_detail_id: backendItem.id,
    product_id: product.id,
    title: product.name,
    active_ingredient: product.active_ingredient,
    itemCode: product.barcode,
    price: discountedPrice,
    price_before_discount: hasPackDiscount ? originalPrice : discountedPrice,
    price_bs: discountedPriceBs,
    price_cop: discountedPriceCop,
    base_price: discountedPrice,
    base_price_bs: discountedPriceBs,
    base_price_cop: discountedPriceCop,
    unitCost: parseFloat(product.unit_cost) || 0,
    basePrice: originalPrice, // Store original base price
    original_price_usd: originalPrice,
    original_price_bs: originalPriceBs,
    original_price_cop: originalPriceCop,
    availableQuantity:
      parseInt(product.valid_stock_sum) || parseInt(product.lots_sum_quantity),
    selectedQuantity: parseInt(backendItem.quantity) || 0,
    laboratory: product.laboratory ? product.laboratory.name : "N/A",
    taxRate: taxMultiplier,
    pack_id: backendItem.pack_id || null,
    discount_percentage: parseFloat(backendItem.discount_percentage) || 0,
    discount_type: backendItem.discount_type || null,
    discount_source_id: backendItem.discount_source_id || null,
    original_pack_config:
      backendItem.pack_config || backendItem.product?.pack_config || null,
    has_pack_discount: hasPackDiscount, // Flag para indicar si tiene descuento de pack
  };
};

const fetchOpenOrder = async () => {
  console.log("[ORDER_USER] fetchOpenOrder iniciado");
  try {
    console.log(
      "[ORDER_USER] Haciendo petición a /tpv/order/seller/my-open-order",
    );
    const response = await axios.get("/tpv/order/seller/my-open-order");
    console.log("[ORDER_USER] Respuesta recibida:", response.data);
    if (response.data.data && response.data.data.order) {
      if (response.data.data.order.pending_order) {
        openOrderData.value = response.data.data.order.pending_order;
        reservedOrderData.value = response.data.data.order.reserved_order;
        selectedClient.value = response.data.data.order.pending_order.client;
        hasOpenOrder.value = true;
        if (openOrderData.value.currency) {
          selectedDisplayCurrency.value =
            openOrderData.value.currency.toUpperCase();
        }
        if (openOrderData.value.details) {
          orderItems.value = openOrderData.value.details.map((item) =>
            formatOrderItemForFrontend(item),
          );
        } else {
          orderItems.value = [];
        }
      } else {
        hasOpenOrder.value = false;
        openOrderData.value = null;
        reservedOrderData.value = null;
        selectedClient.value = null;
        orderItems.value = [];
      }
      foreignOrdersCount.value = response.data.data.foreign_orders_count || 0;
    } else {
      hasOpenOrder.value = false;
      openOrderData.value = null;
      reservedOrderData.value = null;
      selectedClient.value = null;
      orderItems.value = [];
      foreignOrdersCount.value = 0;
    }
  } catch (error) {
    console.error("Error al verificar orden abierta del vendedor:", error);
    hasOpenOrder.value = false;
    openOrderData.value = null;
    selectedClient.value = null;
    orderItems.value = [];
  } finally {
    isLoadingInitialOrder.value = false;
  }
};

onMounted(async () => {
  console.log("[ORDER_USER] onMounted iniciado");

  try {
    // Primero cargar la configuración del usuario
    console.log("[ORDER_USER] Cargando configuración del usuario...");
    try {
      const configResponse = await axios.get("/user/config");
      console.log("[ORDER_USER] Configuración cargada:", configResponse.data);
      if (
        configResponse.data.config &&
        configResponse.data.config.sort_products_orders
      ) {
        const [key, order] =
          configResponse.data.config.sort_products_orders.split("|");
        sortBy.value = key;
        orderBy.value = order;
        tableOptions.value.sortBy = [{ key, order }];
        console.log("[ORDER_USER] Ordenamiento configurado:", { key, order });
      }
    } catch (error) {
      console.error("[ORDER_USER] Error al cargar configuración", error);
    }

    // Luego cargar la orden abierta de forma asíncrona sin bloquear
    // Usar nextTick para asegurar que el componente esté completamente montado
    console.log("[ORDER_USER] Preparando carga de orden abierta...");
    nextTick(async () => {
      console.log("[ORDER_USER] nextTick ejecutado, cargando orden abierta...");
      try {
        await fetchOpenOrder();
        console.log(
          "[ORDER_USER] Orden abierta cargada, selectedClient:",
          selectedClient.value,
        );
        // Después de cargar la orden, cargar ofertas de empresa si hay cliente con company_id
        await nextTick(); // Esperar a que selectedClient se actualice
        console.log(
          "[ORDER_USER] Verificando company_id:",
          selectedClient.value?.company_id,
        );
        if (selectedClient.value?.company_id) {
          try {
            console.log(
              "[ORDER_USER] Cargando ofertas de empresa para:",
              selectedClient.value.company_id,
            );
            await fetchCompanyOffers(selectedClient.value.company_id);
            selectedDiscountType.value = "Empresa";
            selectedCompany.value = selectedClient.value.company_id;
            console.log("[ORDER_USER] Ofertas de empresa cargadas");
          } catch (error) {
            console.error(
              "[ORDER_USER] Error al cargar ofertas de empresa:",
              error,
            );
          }
        } else {
          // Cargar ofertas generales si no hay company_id
          try {
            console.log("[ORDER_USER] Cargando ofertas generales...");
            await fetchCompanyOffers();
            console.log("[ORDER_USER] Ofertas generales cargadas");
          } catch (error) {
            console.error(
              "[ORDER_USER] Error al cargar ofertas generales:",
              error,
            );
          }
        }
      } catch (error) {
        console.error("[ORDER_USER] Error al cargar orden abierta", error);
        // Asegurar que isLoadingInitialOrder se establezca en false incluso si hay error
        isLoadingInitialOrder.value = false;
      }
    });

    // Finalmente cargar los datos iniciales (sin await para no bloquear)
    console.log("[ORDER_USER] Cargando datos iniciales...");
    try {
      console.log("[ORDER_USER] Cargando opciones de filtros...");
      fetchSelectOptions();
    } catch (error) {
      console.error("[ORDER_USER] Error al cargar opciones de filtros", error);
    }

    try {
      console.log("[ORDER_USER] Cargando productos...");
      fetchProducts();
    } catch (error) {
      console.error("[ORDER_USER] Error al cargar productos", error);
    }

    try {
      console.log("[ORDER_USER] Cargando compañías...");
      consultAllcomapanies();
    } catch (error) {
      console.error("[ORDER_USER] Error al cargar compañías", error);
    }

    try {
      console.log("[ORDER_USER] Cargando ofertas de médicos...");
      fetchDoctorOffers();
    } catch (error) {
      console.error("[ORDER_USER] Error al cargar ofertas de médicos", error);
    }

    try {
      console.log("[ORDER_USER] Cargando ofertas de recetas...");
      fetchPrescriptionOffers();
    } catch (error) {
      console.error("[ORDER_USER] Error al cargar ofertas de recetas", error);
    }

    try {
      console.log("[ORDER_USER] Cargando tasas de cambio...");
      fetchExchangeRates();
    } catch (error) {
      console.error("[ORDER_USER] Error al cargar tasas", error);
    }

    try {
      console.log("[ORDER_USER] Cargando configuración general...");
      fetchGeneralSettings();
    } catch (error) {
      console.error(
        "[ORDER_USER] Error al cargar configuración general",
        error,
      );
    }

    console.log("[ORDER_USER] onMounted completado");
  } catch (error) {
    console.error("[ORDER_USER] Error crítico en onMounted:", error);
    toast.error("Error al cargar algunos datos. Por favor, recarga la página.");
  }
});

const totalOrderCost = computed(() => {
  let totalCost = 0;
  orderItems.value.forEach((item) => {
    const cost = item.unitCost || 0;
    const quantity = item.selectedQuantity || 0;
    totalCost += cost * quantity;
  });
  return parseFloat(totalCost.toFixed(2));
});

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;

  // Si la tabla cambia el orden por clic en columna
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key;
    orderBy.value = options.sortBy[0].order;
  }
};

const addProductToQuotation = async ({ productId, quantity }) => {};

const handleClearFilters = () => {
  filterSearchQuery.value = "";
  selectedLaboratory.value = null;
  selectedOrigin.value = null;
  stockStatusFilter.value = null;
  isStrictSearch.value = false;
  sortBy.value = undefined;
  orderBy.value = undefined;
  tableOptions.value.sortBy = [];
};

const handleClearSortOrder = () => {
  sortBy.value = undefined;
  orderBy.value = undefined;
  tableOptions.value.sortBy = [];
};

watch(
  [filterSearchQuery, selectedLaboratory, selectedOrigin, stockStatusFilter],
  () => {
    page.value = 1;
  },
);

watch(barcodeSearchQuery, (newValueBar) => {
  clearTimeout(barcodeInputTimer);
  if (!newValueBar) {
    return;
  }
  if (newValueBar.length >= BARCODE_LENGTH_THRESHOLD) {
    barcodeInputTimer = setTimeout(async () => {
      await addProductToOrderByBarcode(newValueBar);
      barcodeSearchQuery.value = "";
    }, 300);
  }
});

const addProductToOrderByBarcode = async (barcode) => {
  try {
    const response = await axios.get(`/barcode/${barcode}`);
    const productDetails = response.data;

    await addProductToOrder({ productId: productDetails.id, quantity: 1 });
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

const handleSort = (sortOptions) => {
  sortBy.value = sortOptions.key;
  orderBy.value = sortOptions.order;
};

const verifyClient = async (identification) => {
  clientIdentification.value = identification;

  if (!identification) {
    toast.warning("Por favor, ingrese un número de identificación.");
    return false;
  }

  try {
    const response = await axios.get(`/tpv/order/client/${identification}`);
    const responseData = response.data.data;

    if (responseData.found === false) {
      toast.info("Consultando identidad...");
      
      try {
        const cneResponse = await axios.post('/crm/clients/cne-verify', {
          identification: identification
        });
        
        if (cneResponse.status === 200 && cneResponse.data.data) {
          const cneData = cneResponse.data.data;
          newClientFormData.value = {
            ...newClientFormData.value,
            identification: identification,
            identification_type: 'V-',
            name: cneData.name,
            last_name: cneData.last_name
          };
          toast.success("Datos precargados desde CNE.");
        }
      } catch (cneError) {
        console.log("[TPV] Sin datos CNE:", cneError);
        newClientFormData.value = {
          ...newClientFormData.value,
          identification: identification,
          identification_type: 'V-',
        };
      }

      showRegisterClientModal.value = true;
      return false;
    } else {
      const clientData = responseData.client;

      // NUEVO: Si no tiene teléfono o es inválido (solo ceros o longitud insuficiente), abrir modal
      const isInvalidPhone = !clientData.phone || 
                            clientData.phone.trim().length < 10 || 
                            /^0+$/.test(clientData.phone.trim());

      if (isInvalidPhone) {
        toast.warning("El cliente no tiene un teléfono válido. Por favor, complételo.");
        newClientFormData.value = {
          ...newClientFormData.value,
          ...clientData,
          identification: clientData.identification,
          identification_type: clientData.identification_type
        };
        showRegisterClientModal.value = true;
        return false;
      }

      selectedClient.value = clientData;
      toast.success(
        `Cliente ${clientData.name} ${clientData.last_name} encontrado.`,
      );

      if (responseData.found_open_order) {
        hasOpenOrder.value = true;
        openOrderData.value = responseData.order;

        if (openOrderData.value.currency) {
          selectedDisplayCurrency.value =
            openOrderData.value.currency.toUpperCase();
        }
      } else {
        hasOpenOrder.value = false;
        openOrderData.value = null;
        const order = await addOrden(clientData.id);
        if (order && pendingQuotationProducts.value.length > 0) {
          await handleAddQuotationProducts(pendingQuotationProducts.value);
          pendingQuotationProducts.value = [];
        }
      }
      return true;
    }
  } catch (error) {
    console.error("Error al verificar cliente:", error);
    toast.error("Error al verificar el cliente.");
    return false;
  }
};

const handleLoadQuotation = async (quotationId) => {
  if (!quotationId?.trim()) return false;
  try {
    const response = await axios.get(`/tpv/quotations/${quotationId}/products`);
    const { products, client } = response.data;

    if (!products || products.length === 0) {
      return false;
    }

    if (client && client.id) {
      const order = await addOrden(client.id);
      if (order) {
        selectedClient.value = order.client;
        await handleAddQuotationProducts(products);
        toast.success("Cotización cargada. Productos agregados al pedido.");
        return true;
      }
    } else {
      pendingQuotationProducts.value = products;
      toast.warning(
        "La cotización no tiene cliente. Ingrese la cédula del cliente.",
      );
      return true;
    }
    return false;
  } catch (error) {
    return false;
  }
};

const handleIdentifyAndStart = async (value) => {
  if (!value) return;
  
  // Primero intentamos como cotización (si tiene éxito, termina)
  const isQuotation = await handleLoadQuotation(value);
  if (isQuotation) return;
  
  // Si no fue cotización, intentamos verificar como cliente
  await verifyClient(value);
};

const reservedOrderCliente = async () => {
  try {
    const response = await axios.get(`/tpv/order/searchReserved`);
    if (response.data && response.data.message) {
      toast.success(response.data.message);
    }
    await fetchOpenOrder();
  } catch (error) {
    if (error.response && error.response.data && error.response.data.message) {
      toast.warning(error.response.data.message);
    } else {
      console.error("Error al verificar la orden reservada:", error);
      toast.error("Ocurrió un error inesperado al procesar la orden.");
    }
  }
};

const addOrden = async (id) => {
  const params = {
    client_id: id,
    seller_id: currentUser.value?.id || 3,
    currency: selectedDisplayCurrency.value,
  };
  try {
    
    const response = await axios.post("/tpv/orders", params);
    openOrderData.value = response.data.data.order;
    selectedClient.value = response.data.data.order.client;
    hasOpenOrder.value = true;
    toast.success("Orden creada exitosamente.");
    return response.data.data.order;
  } catch (error) {
    console.error("Error al agregar la orden:", error);
    toast.error("Error al agregar la orden.");
    return null;
  }
};

function cargarErrores(errores) {
  newClientFormErrors.id = errores.id ? errores.id.join(", ") : "";
  newClientFormErrors.identification = errores.identification
    ? errores.identification.join(", ")
    : "";
  newClientFormErrors.identification_type = errores.identification_type
    ? errores.identification_type.join(", ")
    : "";
  newClientFormErrors.name = errores.name ? errores.name.join(", ") : "";
  newClientFormErrors.last_name = errores.last_name
    ? errores.last_name.join(", ")
    : "";
  newClientFormErrors.email = errores.email ? errores.email.join(", ") : "";
  newClientFormErrors.phone = errores.phone ? errores.phone.join(", ") : "";
  newClientFormErrors.address = errores.address
    ? errores.address.join(", ")
    : "";
  newClientFormErrors.birthdate = errores.birthdate
    ? errores.birthdate.join(", ")
    : "";
  newClientFormErrors.company_id = errores.company_id
    ? errores.company_id.join(", ")
    : "";
  newClientFormErrors.is_spe = errores.is_spe ? errores.is_spe.join(", ") : ""; // Nuevo campo agregado
}

const handleSaveNewClient = async (formData) => {
  try {
    const clientId = newClientFormData.value.id;
    const url = clientId ? `/crm/clients/edit/${clientId}` : "/crm/clients";
    
    let respuesApi = await axios.post(url, formData);
    if (respuesApi.status == 200) {
      toast.success(clientId ? "Cliente actualizado" : "Cliente creado");
      handleCloseRegisterModal();
      addOrden(respuesApi.data.data.id);
    }
  } catch (error) {
    toast.error("Error al crear el cliente");
    let errores = { ...error.response.data.data.errors };
    cargarErrores(errores);
  }
};

const handleEditCliente = (client) => {
  newClientFormData.value = {
    ...newClientFormData.value,
    ...client,
    identification: client.identification,
    identification_type: client.identification_type
  };
  showRegisterClientModal.value = true;
};

const handleCloseRegisterModal = () => {
  showRegisterClientModal.value = false;
  limpiarDatosFormulario();
  limpiarErroresFormulario();
};

function limpiarDatosFormulario() {
  newClientFormData.id = null;
  newClientFormData.identification = "";
  newClientFormData.identification_type = "";
  newClientFormData.name = "";
  newClientFormData.last_name = "";
  newClientFormData.email = "";
  newClientFormData.phone = "";
  newClientFormData.address = "";
  newClientFormData.birthdate = null;
  newClientFormData.company_id = "";
  newClientFormData.is_spe = false; // Nuevo campo agregado
}

function limpiarErroresFormulario() {
  newClientFormErrors.id = "";
  newClientFormErrors.identification = "";
  newClientFormErrors.identification_type = "";
  newClientFormErrors.name = "";
  newClientFormErrors.last_name = "";
  newClientFormErrors.email = "";
  newClientFormErrors.phone = "";
  newClientFormErrors.address = "";
  newClientFormErrors.birthdate = "";
  newClientFormErrors.company_id = "";
  newClientFormErrors.is_spe = ""; // Nuevo campo agregado
}

const clearFormErrors = () => {
  newClientFormErrors.value = {};
};

const handleCurrencyChanged = async (newCurrency) => {
  isCurrencyChanging.value = true;
  try {
    if (hasOpenOrder.value && openOrderData.value?.id) {
      // Calculate totals for the new currency to satisfy backend validation
      let calculatedTotal = 0;
      let calculatedTotalUSD = 0;
      let calculatedTotalCost = 0;

      orderItems.value.forEach((item) => {
        const qty = item.selectedQuantity || 0;
        calculatedTotalCost += (item.unitCost || 0) * qty;

        // Calculate USD Total (Use basePrice if available, else fallback)
        const usdPrice =
          item.basePrice ||
          (selectedDisplayCurrency.value === "USD" ? item.price : 0);
        // Note: If current view is not USD, and basePrice missing, we can't easily guess USD price.
        // But basePrice should be present for all valid products now.
        calculatedTotalUSD += usdPrice * qty;

        console.log("LLAMANDO ANTES DE ACTUZALIAR newCurrency " + newCurrency);
        console.log(item);

        // Calculate Target Currency Total
        if (newCurrency === "BS") {
          calculatedTotal += (item.price_bs || 0) * qty;
        } else if (newCurrency === "COP") {
          calculatedTotal += (item.price_cop || 0) * qty;
        } else {
          // USD
          calculatedTotal += usdPrice * qty;
        }
        console.log("LLAMANDO ANTES DE ACTUZALIAR MONEDA " + item.price_cop);
      });
      console.log("LLAMANDO ANTES DE ACTUZALIAR newCurrency " + newCurrency);

      await axios.patch(`/tpv/orders/${openOrderData.value.id}`, {
        currency: newCurrency,
        total_amount: calculatedTotal,
        total_amount_usd: calculatedTotalUSD,
        total_cost: calculatedTotalCost,
      });
      await fetchOpenOrder();
    }
    selectedDisplayCurrency.value = newCurrency;
  } catch (error) {
    console.error("Error updating currency:", error);
    toast.error("Error al actualizar la moneda de la orden.");
  } finally {
    isCurrencyChanging.value = false;
  }
};

const totalEligibleAmount = computed(() => {
  let total = 0;
  orderItems.value.forEach((item) => {
    // Exclude items with expiration discount from the eligible base
    if (item.discount_type === "expiration") {
      return;
    }
    // Usamos el precio base (sin descuento global) para calcular el monto elegible
    const price = getItemPriceByCurrency(item, selectedDisplayCurrency.value, true);
    const quantity = item.selectedQuantity || 0;
    total += price * quantity;
  });
  return total;
});

const totalCompanyDiscountAmount = computed(() => {
  if (selectedDiscountType.value === "Empresa" && selectedCompanyId.value) {
    const offer = activeCompanyOffers.value.find(
      (o) => o.value === selectedCompanyId.value,
    );
    const porcentaje = parseFloat(offer?.current_discount || 0);
    if (porcentaje > 0) {
      return totalEligibleAmount.value * (porcentaje / 100);
    }
  }
  return 0;
});

const totalDoctorDiscountAmount = computed(() => {
  if (selectedDiscountType.value === "Medico" && selectedDoctorOffer.value) {
    const porcentaje = parseFloat(selectedDoctorOffer.value.percentage || 0);
    if (porcentaje > 0) {
      return totalEligibleAmount.value * (porcentaje / 100);
    }
  }
  return 0;
});

const totalRecipeDiscountAmount = computed(() => {
  if (selectedDiscountType.value === "Recipe") {
    const porcentaje = parseFloat(
      currentPrescriptionDiscountPercentage.value || 0,
    );
    if (porcentaje > 0) {
      return totalEligibleAmount.value * (porcentaje / 100);
    }
  }
  return 0;
});

const totalExpirationDiscountAmount = computed(() => {
  let total = 0;
  orderItems.value.forEach((item) => {
    const percentage = parseFloat(item.discount_percentage || 0);
    if (item.discount_type === "expiration" && percentage > 0) {
      let originalPrice = item.basePrice || 0;
      if (selectedDisplayCurrency.value === "BS") {
        originalPrice = item.original_price_bs || 0;
      } else if (selectedDisplayCurrency.value === "COP") {
        originalPrice = item.original_price_cop || 0;
        // For COP, we typically round up price before calculating total
        originalPrice = roundUpToNearestHundred(originalPrice);
      }

      const quantity = item.selectedQuantity || 0;
      const discountAmount = originalPrice * quantity * (percentage / 100);
      total += discountAmount;
    }
  });
  return total;
});

const appliesSpecialTax = computed(() => {
  return (
    isSpecialTaxpayer.value &&
    (selectedDisplayCurrency.value === "USD" ||
      selectedDisplayCurrency.value === "COP")
  );
});

const specialTaxAmount = computed(() => {
  if (!appliesSpecialTax.value) return 0;
  let tax = totalOrderAmount.value * 0.03;
  if (selectedDisplayCurrency.value === "COP") {
    tax = Math.ceil(tax / 100) * 100;
  }
  return tax;
});

const totalOrderAmountWithspecialTaxAmount = computed(() => {
  const base = totalOrderAmount.value || 0;
  if (appliesSpecialTax.value) {
    return base + specialTaxAmount.value;
  }
  return base;
});

// Monto Total = suma de totales de cada fila (Precio con Descuento + IVA)
// El descuento ya está aplicado en el Precio Base de cada producto
const totalOrderAmount = computed(() => {
  const base = totalProductsAmount.value + totalIVAAmount.value;
  let globalDiscount = 0;

  if (selectedDiscountType.value === "Empresa") {
    globalDiscount = totalCompanyDiscountAmount.value;
  } else if (selectedDiscountType.value === "Medico") {
    globalDiscount = totalDoctorDiscountAmount.value;
  } else if (selectedDiscountType.value === "Recipe") {
    globalDiscount = totalRecipeDiscountAmount.value;
  }

  let total = base - globalDiscount;

  if (selectedDisplayCurrency.value === "COP") {
    total = roundUpToNearestHundred(total);
  }

  return total;
});

const totalOrderAmountSinDiscount = computed(() => {
  const baseTotal = totalProductsAmount.value + totalIVAAmount.value;
  let discountToSubtract = 0;
  return baseTotal;
});

const totalSPESavings = computed(() => {
  if (!selectedClient.value?.is_spe) return 0;

  let totalOriginalIVA = 0;
  orderItems.value.forEach((item) => {
    const price = getItemPriceByCurrency(item, selectedDisplayCurrency.value);
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;
    totalOriginalIVA += price * quantity * taxRate;
  });

  // El ahorro es el 75% del IVA original
  const savings = totalOriginalIVA * 0.75;
  if (selectedDisplayCurrency.value === "COP") {
    return roundUpToNearestHundred(savings);
  }
  return parseFloat(savings.toFixed(2));
});
const totalIVAAmount = computed(() => {
  let totalIVA = 0;
  orderItems.value.forEach((item) => {
    const price = getItemPriceByCurrency(item, selectedDisplayCurrency.value);
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;

    let ivaAmount = price * quantity * taxRate;

    // Si el cliente es SPE, aplicar solo el 25% del IVA (descuento del 75%)
    if (selectedClient.value?.is_spe) {
      ivaAmount = ivaAmount * 0.25;
    }

    totalIVA += ivaAmount;
  });
  return totalIVA;
});

const totalProductsAmount = computed(() => {
  let total = 0;
  orderItems.value.forEach((item) => {
    // Usamos el precio base para el subtotal de productos
    const price = getItemPriceByCurrency(item, selectedDisplayCurrency.value, true);
    const quantity = item.selectedQuantity || 0;
    total += price * quantity;
  });
  return total;
});
const totalAmountBs = computed(() => {
  let total = 0;
  orderItems.value.forEach((item) => {
    const basePriceBs = item.price_bs || 0;
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;

    // Aplicar descuento SPE si corresponde
    let effectiveTaxRate = taxRate;
    if (selectedClient.value?.is_spe) {
      effectiveTaxRate = taxRate * 0.25;
    }

    total += basePriceBs * quantity * (1 + effectiveTaxRate);
  });
  return total;
});

const totalAmountUsd = computed(() => {
  let total = 0;
  let subtotalProductosUSD = 0;

  orderItems.value.forEach((item) => {
    const basePriceUsd = item.original_price_usd || 0;
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;

    // Aplicar descuento SPE si corresponde
    let effectiveTaxRate = taxRate;
    if (selectedClient.value?.is_spe) {
      effectiveTaxRate = taxRate * 0.25;
    }
    subtotalProductosUSD += basePriceUsd * quantity;
    total += basePriceUsd * quantity * (1 + effectiveTaxRate);
  });

  // return total;

  let porcentaje = 0;

  if (selectedDiscountType.value === "Empresa" && selectedCompanyId.value) {
    const offer = activeCompanyOffers.value.find(
      (o) => o.value === selectedCompanyId.value,
    );
    porcentaje = parseFloat(offer?.current_discount || 0);
  } else if (
    selectedDiscountType.value === "Medico" &&
    selectedDoctorOffer.value
  ) {
    porcentaje = parseFloat(selectedDoctorOffer.value.percentage || 0);
  } else if (selectedDiscountType.value === "Recipe") {
    porcentaje = parseFloat(currentPrescriptionDiscountPercentage.value || 0);
  }

  const descuentoUSD = subtotalProductosUSD * (porcentaje / 100);
  let finalTotalUSD = total - descuentoUSD;

  if (appliesSpecialTax.value) {
    let tax = finalTotalUSD * 0.03;
    finalTotalUSD += tax;
  }

  return finalTotalUSD;
});

const totalAmountCop = computed(() => {
  let total = 0;
  orderItems.value.forEach((item) => {
    const basePriceCop = item.price_cop || 0;
    const quantity = item.selectedQuantity || 0;
    const taxRate = item.taxRate || 0;

    // Aplicar descuento SPE si corresponde
    let effectiveTaxRate = taxRate;
    if (selectedClient.value?.is_spe) {
      effectiveTaxRate = taxRate * 0.25;
    }

    total += basePriceCop * quantity * (1 + effectiveTaxRate);
  });
  return total;
});

const updateOrderTotalsInBackend = async () => {
  if (!openOrderData.value || !openOrderData.value.id) {
    return;
  }

  let total =
    selectedDisplayCurrency.value == "COP"
      ? roundUpToNearestHundred(totalOrderAmountWithspecialTaxAmount.value)
      : totalOrderAmountWithspecialTaxAmount.value.toFixed(2);

  try {
    const payload = {
      total_amount: total,
      total_amount_usd: parseFloat(totalAmountUsd.value) || 0,
      total_cost: parseFloat(totalOrderCost.value) || 0,
      currency: selectedDisplayCurrency.value,
      discount_type: selectedDiscountType.value || null,
    };
    await axios.patch(`/tpv/orders/${openOrderData.value.id}`, payload);
  } catch (error) {
    toast.error("Error al actualizar los totales de la orden.");
  }
};
/*
const updateOrderItemQuantity = async ({
  productId,
  quantity,
  orderDetailId,
}) => {
  if (quantity <= 0) {
    return;
  }

  if (!hasOpenOrder.value || !openOrderData.value || !openOrderData.value.id) {
    toast.error("Debe haber una orden abierta para modificar productos.");
    return;
  }

  try {
    let currentItem = null;
    let computedTotalQuantity = quantity;

    if (orderDetailId) {
      currentItem = orderItems.value.find(
        (item) => item.order_detail_id === orderDetailId
      );

      // Calculate cumulative quantity for split items (non-packs)
      if (currentItem && !currentItem.pack_id) {
        // Sum quantity of OTHER matching items + NEW quantity for THIS item
        const otherItemsQuantity = orderItems.value
          .filter(
            (item) =>
              item.product_id === productId &&
              !item.pack_id &&
              item.order_detail_id !== orderDetailId
          )
          .reduce((sum, item) => sum + item.selectedQuantity, 0);

        computedTotalQuantity = otherItemsQuantity + quantity;
      }
    }

    // Fallback if no orderDetailId or item not found (legacy behavior)
    if (!currentItem) {
      currentItem = orderItems.value.find(
        (item) => item.product_id === productId
      );
    }

    if (!currentItem) {
      toast.error(
        "Producto no encontrado en la orden para actualizar su cantidad."
      );
      return;
    }
    const payload = {
      product_id: productId,
      quantity: computedTotalQuantity,
      price_usd_unit: currentItem.basePrice || currentItem.price,
      price_at_product:
        currentItem.basePrice || currentItem.orderPrice || currentItem.price,
      currency_at_order: selectedDisplayCurrency.value,
    };

    const backendResponse = await axios.post(
      `/tpv/orders/${openOrderData.value.id}/items`,
      payload
    );
    // Force refresh of the order to reflect any backend-side logical splits (e.g. expiration offers)
    await fetchOpenOrder();
    toast.success("Cantidad actualizada correctamente.");
  } catch (error) {
    const errorMessage =
      error.response?.data?.message ||
      "Error al actualizar el producto en la orden. Inténtalo de nuevo.";
    toast.error(errorMessage);
    if (
      error.response &&
      error.response.status === 400 &&
      error.response.data.data
    ) {
      const { available_stock, requested_quantity, product_name } =
        error.response.data.data;
      toast.error(
        `Stock insuficiente para "${product_name}". Disponible: ${available_stock}. Solicitado: ${requested_quantity}.`
      );
    }
  }
};*/

const updateOrderItemQuantity = async ({
  productId,
  quantity,
  orderDetailId,
}) => {
  if (quantity <= 0) return;

  if (!hasOpenOrder.value || !openOrderData.value || !openOrderData.value.id) {
    toast.error("Debe haber una orden abierta para modificar productos.");
    return;
  }

  try {
    // 1. Buscamos el ítem específico usando el orderDetailId
    let currentItem = orderItems.value.find(
      (item) => item.order_detail_id === orderDetailId,
    );

    // Fallback por seguridad
    if (!currentItem) {
      currentItem = orderItems.value.find(
        (item) => item.product_id === productId,
      );
    }

    if (!currentItem) {
      toast.error("Producto no encontrado en la orden.");
      return;
    }

    // 2. Lógica de cantidad acumulada
    let computedTotalQuantity = quantity;

    // Solo acumulamos si NO es un pack (los packs se manejan por líneas únicas)
    if (!currentItem.pack_id && orderDetailId) {
      const otherItemsQuantity = orderItems.value
        .filter(
          (item) =>
            item.product_id === productId &&
            !item.pack_id &&
            item.order_detail_id !== orderDetailId,
        )
        .reduce((sum, item) => sum + item.selectedQuantity, 0);

      computedTotalQuantity = otherItemsQuantity + quantity;
    }

    // 3. Construcción del Payload con pack_id
    console.log(currentItem);
    const payload = {
      product_id: productId,
      quantity: computedTotalQuantity,
      price_usd_unit: currentItem.basePrice || currentItem.price,
      price_at_product: currentItem.price,
      currency_at_order: selectedDisplayCurrency.value,
      // --- CAMBIO CRUCIAL ---
      // Enviamos el pack_id si el ítem lo tiene para que el backend mantenga la relación
      pack_id: currentItem.pack_id || null,
    };

    await axios.post(`/tpv/orders/${openOrderData.value.id}/items`, payload);
    await fetchOpenOrder();
    toast.success("Cantidad actualizada.");
  } catch (error) {
    // ... tu manejo de errores actual
    console.error("Error al actualizar cantidad:", error);
    toast.error(error.response?.data?.message || "Error al actualizar");
  }
};

const addProductToOrder = async ({
  productId,
  quantity,
  packId = null,
  customPrice = null,
}) => {
  if (quantity <= 0) {
    toast.error("La cantidad a agregar debe ser mayor que cero.");
    return;
  }

  if (!hasOpenOrder.value || !openOrderData.value || !openOrderData.value.id) {
    toast.error("Debe haber una orden abierta para agregar productos.");
    return;
  }

  try {
    const response = await axios.get(`/product/${productId}`);
    const productDetails = response.data;

    const availableQuantity = productDetails.lots_sum_quantity;

    const currentItemInOrder = orderItems.value.find(
      (item) => item.product_id === productId,
    );
    const currentQuantityInOrder = currentItemInOrder
      ? currentItemInOrder.selectedQuantity
      : 0;
    const newTotalQuantity = currentQuantityInOrder + quantity;

    if (quantity > availableQuantity) {
      toast.error(
        `No hay suficiente stock para "${productDetails.name}". Disponible: ${availableQuantity}. Solicitado: ${quantity}.`,
      );
      return;
    }

    // Si hay customPrice (viene del pack en USD), convertirlo a la moneda seleccionada
    let priceInSelectedCurrency;
    if (customPrice !== null) {
      const customPriceUSD = parseFloat(customPrice);
      // Convertir el precio USD a la moneda seleccionada
      if (selectedDisplayCurrency.value === "USD") {
        priceInSelectedCurrency = customPriceUSD;
      } else if (selectedDisplayCurrency.value === "BS") {
        // Calcular la tasa de conversión basada en los precios del producto
        const rate =
          productDetails.sale_price > 0
            ? productDetails.price_bs / productDetails.sale_price
            : 1;
        priceInSelectedCurrency = customPriceUSD * rate;
      } else if (selectedDisplayCurrency.value === "COP") {
        // Calcular la tasa de conversión basada en los precios del producto
        const rate =
          productDetails.sale_price > 0
            ? productDetails.price_cop / productDetails.sale_price
            : 1;
        priceInSelectedCurrency = customPriceUSD * rate;
      } else {
        priceInSelectedCurrency = customPriceUSD;
      }
    } else {
      priceInSelectedCurrency = getItemPriceByCurrency(
        productDetails,
        selectedDisplayCurrency.value,
      );
    }

    const payload = {
      product_id: productDetails.id,
      quantity: newTotalQuantity,
      price_usd_unit:
        customPrice !== null
          ? parseFloat(customPrice)
          : productDetails.sale_price,
      price_at_product: priceInSelectedCurrency,
      tax_rate_at_order: productDetails.iva == 1 ? 0.16 : 0,
      currency_at_order: selectedDisplayCurrency.value,
      pack_id: packId,
    };

    const backendResponse = await axios.post(
      `/tpv/orders/${openOrderData.value.id}/items`,
      payload,
    );
    const backendOrderItem = backendResponse.data.data.order_item;
    const existingItemIndex = orderItems.value.findIndex(
      (item) => item.product_id === backendOrderItem.product_id,
    );

    if (existingItemIndex !== -1) {
      orderItems.value[existingItemIndex] =
        formatOrderItemForFrontend(backendOrderItem);
      toast.success(
        `Cantidad de "${productDetails.name}" incrementada a ${backendOrderItem.quantity}.`,
      );
    } else {
      const itemToAdd = formatOrderItemForFrontend(backendOrderItem);
      orderItems.value.push(itemToAdd);
      toast.success(`"${itemToAdd.title}" agregado a la orden.`);
    }

    // Si hay descuento global activo, aplicar al producto recién agregado (igual que Médico)
    if (selectedDiscountType.value === "Medico" && selectedDoctorOffer.value) {
      validateAndApplyDoctorDiscount();
    } else if (
      selectedDiscountType.value === "Recipe" &&
      prescriptionFile.value &&
      activePrescriptionOffers.value.length > 0
    ) {
      validateAndApplyPrescriptionDiscount();
    }
  } catch (error) {
    console.error(
      "Error al obtener o agregar el producto a la orden:",
      error.response ? error.response.data : error.message,
    );
    const errorMessage =
      error.response?.data?.message ||
      "Error al agregar el producto a la orden. Inténtalo de nuevo.";
    toast.error(errorMessage);
    if (
      error.response &&
      error.response.status === 400 &&
      error.response.data.data
    ) {
      const { available_stock, requested_quantity, product_name } =
        error.response.data.data;
      toast.error(
        `Stock insuficiente para "${product_name}". Disponible: ${available_stock}. Solicitado: ${requested_quantity}.`,
      );
    }
  }
};

// Watcher para actualizar totales en backend con debounce para evitar múltiples llamadas
let updateTotalsTimer;
watch(
  [totalOrderAmount, selectedDisplayCurrency],
  async (newValue, oldValue) => {
    if (!isFinishingOrder.value) {
      if (newValue[0] !== oldValue[0] || newValue[1] !== oldValue[1]) {
        if (hasOpenOrder.value && openOrderData.value?.id) {
          clearTimeout(updateTotalsTimer);
          updateTotalsTimer = setTimeout(async () => {
            try {
              
    await updateOrderTotalsInBackend();
    
            } catch (error) {
              console.error("Error al actualizar totales:", error);
            }
          }, 500);
        }
      }
    }
  },
  { deep: false },
);

const getItemPriceByCurrency = (item, currency, useBase = false) => {
  if (currency === "BS") {
    return useBase ? item.base_price_bs || 0 : item.price_bs || 0;
  } else if (currency === "COP") {
    return useBase ? item.base_price_cop || 0 : item.price_cop || 0;
  } else {
    return useBase
      ? item.base_price || item.original_price_usd || 0
      : item.price || item.sale_price || 0;
  }
};

const removeOrderItem = async (productIdToRemove) => {
  /* Swal.fire({
    title: "¿Estás seguro?",
    text: "¡Desea eliminar el producto!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Continuar",
    cancelButtonText: "Cancelar",
  }).then(async (result) => {
    if (result.isConfirmed) {*/
  if (!hasOpenOrder.value || !openOrderData.value || !openOrderData.value.id) {
    toast.error("No hay una orden abierta para eliminar productos.");
    return;
  }
  try {
    const itemToRemove = orderItems.value.find(
      (item) => item.product_id === productIdToRemove,
    );
    if (!itemToRemove || !itemToRemove.order_detail_id) {
      toast.error(
        "No se encontró el detalle del producto en la orden para eliminar.",
      );
      return;
    }
    await axios.delete(
      `/tpv/orders/${openOrderData.value.id}/items/${itemToRemove.order_detail_id}`,
    );
    orderItems.value = orderItems.value.filter(
      (item) => item.product_id !== productIdToRemove,
    );
    toast.success("Producto eliminado de la orden.");
  } catch (error) {
    toast.error("Error al eliminar el producto de la orden.");
  }
  //}
  //});
};

const cancelarOrder = async () => {
  try {
    await axios.patch(`/tpv/orders/${openOrderData.value.id}/abandon`);
    toast.success("Orden abandonada exitosamente.");
    hasOpenOrder.value = false;
    openOrderData.value = null;
    selectedClient.value = null;
    orderItems.value = [];
  } catch (error) {
    console.error(
      "Error al abandonar la orden:",
      error.response ? error.response.data : error.message,
    );
    const errorMessage =
      error.response?.data?.message ||
      "Error al abandonar la orden. Inténtalo de nuevo.";
    toast.error(errorMessage);
  }
};

const reserverOrder = async () => {
  try {
    const response = await axios.patch(
      `/tpv/order/${openOrderData.value.id}/reserve`,
    );
    hasOpenOrder.value = false;
    openOrderData.value = null;
    selectedClient.value = null;
    orderItems.value = [];
    reservedOrderData.value = response.data.data.reserved_order;
    toast.success("Orden reservada exitosamente.");
  } catch (error) {
    console.error(
      "Error al reservar la orden:",
      error.response ? error.response.data : error.message,
    );

    if (
      error.response?.data?.message.includes("Ya tienes una orden reservada")
    ) {
      try {
        const checkResponse = await axios.get(
          "/tpv/order/seller/my-open-order",
        );
        if (checkResponse.data.data) {
          openOrderData.value = checkResponse.data.data.order.pending_order;
          reservedOrderData.value =
            checkResponse.data.data.order.reserved_order;
          toast.info(
            "Ya hay una orden reservada. La orden abierta se mantiene.",
          );
          return;
        }
      } catch (checkError) {
        console.error(
          "Error al verificar el estado de las órdenes:",
          checkError,
        );
      }
    }
    console.error(
      "Error al reservar la orden:",
      error.response ? error.response.data : error.message,
    );
    toast.error(errorMessage);
  }
};

const openBuysModal = () => {
  if (selectedDiscountType.value === "Recipe" && !prescriptionFile.value) {
    toast.error(
      "Debe adjuntar la foto de la receta para aplicar este descuento.",
    );
    return;
  }
  showBuysModal.value = true;
};

const closeBuysModal = () => {
  showBuysModal.value = false;
};

const resetFormSelectors = () => {
  selectedDiscountType.value = null;
  selectedCompanyId.value = null;
  selectedDoctorOffer.value = null;
  prescriptionFile.value = null;
  currentPrescriptionDiscountPercentage.value = 0;
};

const printFiscalPNP = async (order) => {
  if (!order || !order.id) return;
  
  try {
    toast.info("Enviando a cola de impresión fiscal...");
    const response = await axios.post(`/fiscal/queue/${order.id}`);
    toast.success(response.data.message || "Orden encolada correctamente.");
  } catch (error) {
    console.error("Error al encolar impresión fiscal:", error);
    toast.error(error.response?.data?.error || "Error al conectar con el servidor.");
  }
};

const handleBuysCompletion = async (
  orderId,
  paymentsData,
  credit,
  changeAmount,
  changeAmountUSD,
  switchStates,
  changeAmountOrigin = 0,
) => {
  try {
    isFinishingOrder.value = true;
    
    

    if (typeof updateTotalsTimer !== "undefined")
      clearTimeout(updateTotalsTimer);
    
    await updateOrderTotalsInBackend();
    
    const finalAmount = parseFloat(totalOrderAmountWithspecialTaxAmount.value);
    if (
      orderItems.value.length > 0 &&
      (finalAmount <= 0 || isNaN(finalAmount))
    ) {
      throw new Error(
        "El monto total calculado es inválido. Por favor, revisa los productos.",
      );
    }

    const balanceUsed = paymentsData.some(
      (payment) => payment.type === "balance",
    );

    let currentPercentage = 0;
    let currentSourceId = null;
    let currentTypeName = null;

    if (selectedDiscountType.value === "Empresa" && selectedCompanyId.value) {
      const offer = activeCompanyOffers.value.find(
        (o) => o.value === selectedCompanyId.value,
      );
      currentPercentage = parseFloat(offer?.current_discount || 0);
      currentSourceId = selectedCompanyId.value;
      currentTypeName = "company";
    } else if (
      selectedDiscountType.value === "Medico" &&
      selectedDoctorOffer.value
    ) {
      currentPercentage = parseFloat(selectedDoctorOffer.value.percentage || 0);
      currentSourceId = selectedDoctorOffer.value.id;
      currentTypeName = "doctor";
    } else if (
      selectedDiscountType.value === "Recipe" &&
      prescriptionFile.value
    ) {
      currentPercentage = parseFloat(
        currentPrescriptionDiscountPercentage.value,
      );
      currentSourceId = activePrescriptionOffers.value[0]?.id;
      currentTypeName = "recipe";
    }

    let taxable_base = (appliesSpecialTax.value || switchStates.spe_surcharge_rate) ? totalOrderAmount.value : 0.0;
    let spe_surcharge_rate = switchStates.spe_surcharge_rate || (appliesSpecialTax.value ? 3.0 : 0.0);
    let spe_surcharge_amount = (switchStates.spe_surcharge_rate && !appliesSpecialTax.value) 
      ? (totalOrderAmount.value * (switchStates.spe_surcharge_rate / 100)) 
      : (appliesSpecialTax.value ? specialTaxAmount.value : 0.0);

    const safeChangeAmount = isNaN(parseFloat(changeAmount))
      ? 0
      : parseFloat(changeAmount);
    const safeChangeAmountUSD = isNaN(parseFloat(changeAmountUSD))
      ? 0
      : parseFloat(changeAmountUSD);

    const formData = new FormData();
    formData.append("order_id", orderId);
    formData.append("total_amount", totalOrderAmountWithspecialTaxAmount.value);
    formData.append("currency", selectedDisplayCurrency.value);
    formData.append("client_id", selectedClient.value?.id || "");
    formData.append("seller_id", currentUser.value?.id || "");
    formData.append("balance_used", balanceUsed ? 1 : 0);
    formData.append("generate_invoice", (switchStates.invoice_switch || switchStates.generate_invoice) ? 1 : 0);
    formData.append("credit", credit ? 1 : 0);
    formData.append("changeAmount", safeChangeAmount);
    formData.append("changeAmountUSD", safeChangeAmountUSD);
    formData.append("spe", switchStates.spe ? 1 : 0);
    formData.append("payments", JSON.stringify(paymentsData));
    formData.append("taxable_base", taxable_base);
    formData.append("spe_surcharge_rate", spe_surcharge_rate);
    formData.append("spe_surcharge_amount", spe_surcharge_amount);

    const mappedItems = orderItems.value.map((item) => {
      const isTaxable = item.taxRate != 0;
      const taxRateValue = isTaxable ? 0.16 : 0;
      const taxMultiplier = isTaxable ? 1.16 : 1;

      // getItemPriceByCurrency devuelve item.price/price_bs/price_cop (ya modificados por applyDiscount si aplica)
      let finalPrice = getItemPriceByCurrency(
        item,
        selectedDisplayCurrency.value,
      );
      let finalPriceBeforeDiscount = finalPrice;
      let dType = null;
      let dPercent = 0;
      let dSourceId = null;

      // Si applyDiscount ya modificó el item, NO recalcular: el precio ya está correcto
      if (item.discountApplied) {
        dType = item.discountSource || item.discount_type;
        dPercent = parseFloat(item.appliedDiscountPercentage || 0);
        dSourceId = item.discountSourceId || item.discount_source_id;
        const orig =
          selectedDisplayCurrency.value === "BS"
            ? (item.originalPriceBs ?? item.original_price_bs)
            : selectedDisplayCurrency.value === "COP"
              ? (item.originalPriceCop ?? item.original_price_cop)
              : (item.originalPrice ?? item.original_price_usd);
        if (orig != null) finalPriceBeforeDiscount = orig;
      } else {
        const productPct = parseFloat(item.discount_percentage || 0);
        const globalPct =
          currentPercentage > 0 && !item.pack_id ? currentPercentage : 0;
        if (globalPct > productPct) {
          dType = currentTypeName;
          dPercent = globalPct;
          dSourceId = currentSourceId;
          const basePrice =
            selectedDisplayCurrency.value === "BS"
              ? (item.original_price_bs ??
                item.originalPriceBs ??
                item.basePrice)
              : selectedDisplayCurrency.value === "COP"
                ? (item.original_price_cop ??
                  item.originalPriceCop ??
                  item.basePrice)
                : (item.basePrice ??
                  item.original_price_usd ??
                  item.originalPrice);
          finalPriceBeforeDiscount = basePrice;
          finalPrice = basePrice * (1 - dPercent / 100);
        } else if (productPct > 0) {
          dType = item.discount_type || "individual";
          dPercent = productPct;
          dSourceId = item.discount_source_id;
          finalPriceBeforeDiscount =
            (selectedDisplayCurrency.value === "BS"
              ? (item.original_price_bs ?? item.originalPriceBs)
              : selectedDisplayCurrency.value === "COP"
                ? (item.original_price_cop ?? item.originalPriceCop)
                : (item.basePrice ??
                  item.original_price_usd ??
                  item.originalPrice)) ?? finalPrice;
        }
      }

      const ivaAmount = finalPrice * taxRateValue;
      let finalPriceTax = finalPrice * taxMultiplier;
      let finalPriceBeforeDiscountTax =
        finalPriceBeforeDiscount * taxMultiplier;

      let finalIva;
      finalIva =
        selectedDisplayCurrency.value === "COP"
          ? roundUpToNearestHundred(ivaAmount)
          : parseFloat(ivaAmount.toFixed(2));

      // 4. Rounding for specific currencies if needed (e.g. COP)
      // getItemPriceByCurrency usually handles base rounding, but discount might introduce decimals.
      /*if (selectedDisplayCurrency.value === 'COP') {
          // finalPrice = roundUpToNearestHundred(finalPrice);
          // Depending on if backend expects strict rounding or specific value
      }*/

      // CÁLCULO FIJO EN BS PARA FISCALIDAD (Independiente de la moneda de pago)
      // Usamos el precio original en BS (neto) y le aplicamos el descuento e IVA
      const rawPriceBs = (item.originalPriceBs ?? item.original_price_bs ?? item.basePrice) || 0;
      const finalPriceBs = rawPriceBs * (1 - dPercent / 100);
      const finalPriceTaxBs = finalPriceBs * taxMultiplier;
      const finalPriceBeforeDiscountTaxBs = rawPriceBs * taxMultiplier;

      return {
        order_detail_id: item.order_detail_id,
        unit_cost: finalPrice,
        iva_amount: finalIva,
        price: finalPriceTax,
        tax: item.taxRate,
        price_before_discount: finalPriceBeforeDiscountTax,
        price_bs: finalPriceTaxBs,
        price_before_discount_bs: finalPriceBeforeDiscountTaxBs,
        discount_percentage: dPercent > 0 ? dPercent : null,
        discount_type: dType,
        discount_source_id: dSourceId,
      };
    });
    formData.append("items", JSON.stringify(mappedItems));

    // ADJUNTO DE IMAGEN: Solo si es tipo Recipe y hay archivo
    if (selectedDiscountType.value === "Recipe" && prescriptionFile.value) {
      formData.append("prescription_image", prescriptionFile.value);
    }
    const idempotencyKey = `order-complete-${orderId}-${Date.now()}`;
    
    const response = await axios.post(
      `/tpv/orders/${orderId}/complete`,
      formData,
      {
        headers: {
          "Content-Type": "multipart/form-data",
          "X-Idempotency-Key": idempotencyKey,
        },
      },
    );

    if (response.status === 200 || response.status === 201) {
      toast.success("¡Compra finalizada y registrada con éxito!");

      // DISPARAR IMPRESIÓN FISCAL INMEDIATAMENTE
      const orderCompletada = response.data.data.orderCompletada;
      console.log("[FISCAL] Verificando condición inmediata:", { 
          inv: switchStates.invoice_switch, 
          gen: switchStates.generate_invoice 
      });

      prescriptionFile.value = null;
      changeAmountForPrint.value = changeAmount;
      changeAmountOriginForPrint.value = changeAmountOrigin;
      creditAmountForPrint.value = totalOrderAmountWithspecialTaxAmount.value;
      creditForPrint.value = credit;
      expirationDiscountForPrint.value = totalExpirationDiscountAmount.value;
      speSurchargeAmount.value = specialTaxAmount.value;
      clientIdentification.value = "";
      selectedClient.value = null;
      await fetchProducts();
      recipeDiscountForPrint.value = totalRecipeDiscountAmount.value;
      doctorDiscountForPrint.value = totalDoctorDiscountAmount.value;
      companyDiscountForPrint.value = totalCompanyDiscountAmount.value;
      discountTypeForPrint.value = selectedDiscountType.value;
      orderData.value = orderCompletada;
      itemsToPrint.value = JSON.parse(JSON.stringify(orderItems.value));
      TotalToPrint.value = parseFloat(orderData.value.total_amount);
      paymentsForPrint.value = orderData.value.payment_methods.filter((p) => {
        const name = (p.method || "").toString().toUpperCase();
        return (
          name !== "N/A" &&
          name !== "" &&
          name !== "UNDEFINED" &&
          name !== "NULL"
        );
      });

      
    }
  } catch (error) {
    console.error("Error al finalizar la compra:", error);
    const msg =
      error.response?.data?.message ||
      (error.response?.status === 422
        ? "Stock insuficiente para uno o más productos."
        : "Hubo un problema al procesar su compra.");
    toast.error(msg);
    hasOpenOrder.value = false;
    openOrderData.value = null;
    selectedClient.value = null;
    orderItems.value = [];
    reservedOrderData.value = null;
    clientIdentification.value = "";
  } finally {
    isFinishingOrder.value = false;
  }
};

const printTickeCompletion = async () => {
  if (!orderData.value) {
    console.error("No hay datos de orden para imprimir");
    return;
  }
  speSurchargeAmountPrint.value = parseFloat(
    orderData.value.spe_surcharge_amount || 0,
  );
  isSpecialTaxpayer.value =
    parseFloat(orderData.value.spe_surcharge_amount) > 0;
  isPrinting.value = true;
  await nextTick();
  await new Promise((resolve) => setTimeout(resolve, 600));
  const printContents = document.getElementById("orderPrint");

  if (printContents && printContents.innerHTML.trim() !== "") {
    const printWindow = window.open("", "", "height=600,width=800");
    printWindow.document.write(
      "<html><head><title>Ticket 54mm - Farmacia Barrio Sucre</title>",
    );
    printWindow.document.write("<style>" + THERMAL_54MM_CSS + "</style>");

    printWindow.document.write("</head><body>");
    printWindow.document.write(printContents.innerHTML);
    printWindow.document.write("</body></html>");

    printWindow.document.close();
    setTimeout(() => {
      printWindow.focus();
      printWindow.print();
      printWindow.close();
      isPrinting.value = false;
      finalizeAndCheckPending();
    }, 500);
  } else {
    alert("Error: El ticket está vacío. Intente de nuevo.");
    isPrinting.value = false;
  }
};

const fetchGroupProducts = async (groupId) => {
  if (!groupId) {
    toast.info("Este producto no pertenece a un grupo.");
    if (currentGroupId.value !== null) {
      currentGroupId.value = null;
    }
    return;
  }
  currentGroupId.value = groupId;
};

const fetchFailuresProducts = async (productId) => {
  try {
    
    const response = await axios.post("/tpv/product-failure", {
      product_id: productId,
    });
    toast.info("Reporte de falla guardado correctamente.");
  } catch (error) {
    if (error.response) {
      console.error("Errores de validación:", error.response.data.errors);
      toast.error("Hubo un problema al procesar su reporte de falla.");
    } else {
      console.error("Error de conexión:", error.message);
    }
  }
};

const handleBackFromGroupView = () => {
  currentGroupId.value = null;
};

const handleAddQuotationProducts = async (productsFromQuotation) => {
  if (!productsFromQuotation || productsFromQuotation.length === 0) {
    toast.info("La cotización no tiene productos o está vacía.");
    return;
  }

  for (const product of productsFromQuotation) {
    try {
      await addProductToOrder({
        productId: product.product_id,
        quantity: product.units,
      });
    } catch (error) {
      console.error(
        `Error al agregar el producto con ID ${product.product_id}:`,
        error,
      );
    }
  }

  toast.success("Productos de la cotización agregados al pedido.");
  await fetchProducts();
};

const addReserverOrder = async () => {
  try {
    const response = await axios.patch(
      `/tpv/order/${openOrderData.value.id}/reserveAdd`,
    );
    const { pending_order, reserved_order } = response.data.data;

    if (pending_order?.currency) {
      selectedDisplayCurrency.value = pending_order.currency.toUpperCase();
    }
    /*
    openOrderData.value = pending_order;
    reservedOrderData.value = reserved_order;
    selectedClient.value = pending_order.client;

    if (openOrderData.value.details) {
      orderItems.value = openOrderData.value.details.map((item) =>
        formatOrderItemForFrontend(item),
      );
    } else {
      orderItems.value = [];
    }

    hasOpenOrder.value = true;
    await nextTick();
    toast.success("Orden agregada exitosamente.");
    return response.data.data.order;*/

    // Actualizamos los datos solo si existen
    if (pending_order) {
      openOrderData.value = pending_order;
      selectedClient.value = pending_order.client;

      if (pending_order.details) {
        orderItems.value = pending_order.details.map((item) =>
          formatOrderItemForFrontend(item),
        );
      } else {
        orderItems.value = [];
      }
      hasOpenOrder.value = true;
    }

    // La reservada siempre debería existir según tu lógica
    reservedOrderData.value = reserved_order;

    await nextTick();
    toast.success("Orden actualizada correctamente.");
  } catch (error) {
    const errorMessage =
      error.response?.data?.message ||
      "Error al reservar la orden. Inténtalo de nuevo.";
    toast.error(errorMessage);
  }
};

const fetchPacks = async () => {
  loadingPacks.value = true;
  try {
    const response = await axios.get("/tpv/promotions/product-packs", {
      params: {
        page: packsPage.value,
        itemsPerPage: packsItemsPerPage.value,
      },
    });
    if (response.data.data) {
      packs.value = response.data.data;
      totalPacks.value = response.data.total || response.data.data.length;
    }
  } catch (error) {
    console.error("Error fetching packs:", error);
    toast.error("Error al cargar los packs.");
  } finally {
    loadingPacks.value = false;
  }
};

const updatePacksOptions = (options) => {
  packsPage.value = options.page;
  packsItemsPerPage.value = options.itemsPerPage;
  fetchPacks();
};

const handleViewPackDetails = async (item) => {
  try {
    const response = await axios.get(
      `/tpv/promotions/product-packs/${item.id}`,
    );
    const packCompleto = response.data;
    selectedPack.value = packCompleto.data;
    showPackDetailsModal.value = true;
  } catch (error) {
    console.error("Error al obtener los detalles del pack:", error);
  }
};

/*
const handleAddPackToOrder = async ({ pack, quantity }) => {

  //if (!pack || !pack.pack_config) return;


  const productsToAdd = JSON.parse(pack.pack_config)
  if (Object.keys(productsToAdd).length === 0) {
    toast.warning("El pack no contiene configuración de productos.");
    return;
  }

  loading.value = true;
  let addedCount = 0;

  for (let i = 0; i < quantity; i++) {
    for (const [productId, config] of Object.entries(productsToAdd)) {
      try {
        // Handle both object {quantity: 1, sale_price: 1.2} and direct number formats
        const productQty =
          typeof config === "object" && config !== null
            ? config.quantity || 1
            : config;

        const productPrice =
          typeof config === "object" && config !== null
            ? config.sale_price !== undefined
              ? config.sale_price
              : null
            : null;

        await addProductToOrder({
          productId: parseInt(productId),
          quantity: parseInt(productQty),
          packId: pack.id,
          customPrice: productPrice,
          original_pack_config: pack.pack_config
        });
        addedCount++;
      } catch (e) {
        console.error(`Error adding product ${productId} from pack`, e);
      }
    }
  }
  loading.value = false;

  if (addedCount > 0) {
    toast.success(`Pack agregado (x${quantity}).`);
  }
};*/

const handleAddPackToOrder = async ({ pack, quantity }) => {
  // if (!pack || !pack.pack_config) return;

  let configStr = pack.pack_config;

  if (!configStr) {
    const itemWithConfig = orderItems.value.find(
      (i) => i.pack_id === pack.id && i.original_pack_config,
    );
    configStr = itemWithConfig?.original_pack_config;
  }

  if (!configStr) {
    console.error("No se encontró la configuración del pack ID:", pack.id);
    return;
  }

  const productsToAdd = JSON.parse(pack.pack_config);
  loading.value = true;

  try {
    const itemsInOrderBelongingToPack = orderItems.value.filter(
      (item) => item.pack_id === pack.id,
    );

    if (itemsInOrderBelongingToPack.length > 0) {
      // CASO: EL PACK YA EXISTE EN LA ORDEN
      // Iteramos sobre los productos que ya están en el carrito

      for (const item of itemsInOrderBelongingToPack) {
        // Buscamos en la configuración cuánto debe aumentar este producto específico
        const productConfig = productsToAdd[item.product_id];
        const unitsPerPack =
          typeof productConfig === "object" && productConfig !== null
            ? productConfig.quantity || 1
            : productConfig || 1;
        const totalToAdd = unitsPerPack * quantity;

        // Llamamos a actualizar con la cantidad acumulada
        await updateOrderItemQuantity({
          productId: item.product_id,
          quantity: item.selectedQuantity + totalToAdd,
          orderDetailId: item.order_detail_id,
          packId: pack.id, // IMPORTANTE: pasar el packId para que no se pierda
        });
      }
    } else {
      // CASO: EL PACK ES NUEVO (No hay productos con ese pack_id aún)
      for (const [productId, config] of Object.entries(productsToAdd)) {
        const unitsPerPack =
          typeof config === "object" ? config.quantity || 1 : config;
        const productPrice =
          typeof config === "object" ? config.sale_price : null;

        await addProductToOrder({
          productId: parseInt(productId),
          quantity: unitsPerPack * quantity,
          packId: pack.id,
          customPrice: productPrice,
        });
      }
    }

    // Recorremos cada producto definido en la configuración del pack
    /*for (const [productId, config] of Object.entries(productsToAdd)) {
      const idNumeric = parseInt(productId);

      // 1. Obtenemos cuántas unidades de este producto vienen en 1 solo pack
      const unitsPerPack = typeof config === "object" && config !== null
        ? parseInt(config.quantity || 1)
        : parseInt(config);

      // 2. Calculamos cuánto vamos a añadir (unidades_del_pack * cantidad_de_packs_a_sumar)
      // Si quantity es 1 y unitsPerPack es 2, sumaremos 2 unidades.
      const amountToAdd = unitsPerPack * quantity;

      // 3. Buscamos el producto en la orden actual que pertenezca a este pack
      const existingItem = orderItems.value.find(
        (item) => item.product_id === idNumeric && item.pack_id === pack.id
      );

      if (existingItem) {

        await updateOrderItemQuantity({
          productId: idNumeric,
          quantity: existingItem.selectedQuantity + amountToAdd,
          orderDetailId: existingItem.order_detail_id,
        });
      } else {
        const productPrice = typeof config === "object" && config !== null ? config.sale_price : null;
        await addProductToOrder({
          productId: idNumeric,
          quantity: amountToAdd,
          packId: pack.id,
          customPrice: productPrice,
        });
      }
    }*/

    // Sincronizamos la interfaz con el backend
    await fetchOpenOrder();
    toast.success(`Pack actualizado: +${quantity} unidad(es) de pack.`);
  } catch (e) {
    console.error("Error al procesar el pack:", e);
    toast.error("Error al actualizar las cantidades del pack.");
  } finally {
    loading.value = false;
  }
};

watch(activeTab, (val) => {
  if (val === "packs" && packs.value.length === 0 && totalPacks.value === 0) {
    fetchPacks();
  }
});

/*
const handleAddPackToOrder = async ({ pack, quantity }) => {
  if (!pack || !pack.pack_config) return;

  const productsInPack = JSON.parse(pack.pack_config);
  if (Object.keys(productsInPack).length === 0) {
    toast.warning("El pack no tiene productos configurados.");
    return;
  }

  loading.value = true;

  try {
    // 1. Agrupamos todos los productos del pack para enviarlos de forma eficiente
    for (const [productId, config] of Object.entries(productsInPack)) {
      const idNumeric = parseInt(productId);

      // Calculamos cuántas unidades totales se van a agregar de este producto
      // (Cantidad base en el pack * cantidad de packs solicitados)
      const baseQtyInPack = typeof config === "object" ? parseInt(config.quantity || 1) : parseInt(config);
      const totalNewQty = baseQtyInPack * quantity;

      const productPrice = typeof config === "object" ? config.sale_price : null;

      // Buscamos si ya existe en la orden LOCALMENTE
      const existingItem = orderItems.value.find(
        (item) => Number(item.product_id) === idNumeric && Number(item.pack_id) === Number(pack.id)
      );

      if (existingItem) {
        // Si existe, actualizamos cantidad sumando la nueva
        await updateOrderItemQuantity({
          productId: idNumeric,
          quantity: existingItem.selectedQuantity + totalNewQty,
          orderDetailId: existingItem.order_detail_id,
        });
      } else {
        // Si no existe, lo creamos
        await addProductToOrder({
          productId: idNumeric,
          quantity: totalNewQty,
          pack_id: pack.id,
          customPrice: productPrice,
          original_pack_config: pack.pack_config
        });
      }

      // CRUCIAL: Refrescamos la orden después de cada producto del pack
      // para que el siguiente producto vea la lista actualizada y no duplique.
      await fetchOpenOrder();
    }

    toast.success(`Pack "${pack.name}" procesado correctamente.`);
  } catch (e) {
    console.error("Error al procesar el pack:", e);
    toast.error("Error al agregar algunos productos del pack.");
  } finally {
    loading.value = false;
  }
};

*/
const itemsForTicket = computed(() => {
  const globalDiscount = currentGlobalDiscountDetails.value;

  // Si no hay nada que imprimir, retornamos array vacío
  if (!itemsToPrint.value || itemsToPrint.value.length === 0) return [];

  return itemsToPrint.value.map((item) => {
    // Logic for Best Discount on Ticket
    const qty = parseFloat(item.quantity || item.selectedQuantity || 1);
    const productPct = parseFloat(item.discount_percentage || 0);

    const globalPct =
      globalDiscount && globalDiscount.percentage > 0 && !item.pack_id
        ? parseFloat(globalDiscount.percentage)
        : 0;

    const bestPct = Math.max(productPct, globalPct);

    if (bestPct > 0) {
      const factor = 1 - bestPct / 100;
      return {
        ...item,
        price: item.price_before_discount * factor * qty,
        price_bs: item.original_price_bs * factor * qty,
        price_cop: item.original_price_cop * factor * qty,
        price_before_discount: item.price_before_discount * qty,
        selectedQuantity: qty,
        // Override display info for ticket if global won
        discount_percentage: bestPct,
        discount_type:
          globalPct > productPct ? globalDiscount.type : item.discount_type,
      };
    }

    // Si no hay descuento global, devolvemos el item con su cantidad normalizada
    return {
      ...item,
      selectedQuantity: qty,
    };
  });
});
const handleExternalSort = async (sortData) => {
  sortBy.value = sortData.key;
  orderBy.value = sortData.order;
  tableOptions.value.sortBy = [{ key: sortData.key, order: sortData.order }];
  try {
    await axios.post("/user/update-sort-config", {
      sortBy: sortData.key,
      orderBy: sortData.order,
    });
    toast.success("Orden guardada como preferida");
  } catch (error) {
    console.error("Error al guardar preferencia:", error);
  }
};

const finalizeAndCheckPending = () => {
  showBuysModal.value = false;
  console.log("Revisando órdenes pendientes...", pendingOpenOrder.value);
  /*if (pendingOpenOrder.value) {
    hasOpenOrder.value = true;
    openOrderData.value = pendingOpenOrder.value;
    selectedClient.value = pendingOpenOrder.value.client;
    reservedOrderData.value = null;
    if (pendingOpenOrder.value.currency) {
      selectedDisplayCurrency.value =
        pendingOpenOrder.value.currency.toUpperCase();
    }
    orderItems.value = pendingOpenOrder.value.details.map((item) => {
      return formatOrderItemForFrontend(item, selectedDisplayCurrency.value);
    });
    pendingOpenOrder.value = null;
    reservedOrderData.value = null;
    toast.info("Orden reservada cargada automáticamente.");
  } else {*/
  hasOpenOrder.value = false;
  openOrderData.value = null;
  selectedDisplayCurrency.value = "COP";
  orderItems.value = [];
  selectedClient.value = null;
  clientIdentification.value = "";
  reservedOrderData.value = null;
  //}
};

// Enviamos una petición cada 5 minutos (300,000 ms)
const startHeartbeat = () => {
  const interval = setInterval(async () => {
    try {
      await axios.get("/api/tpv/heartbeat");
      console.log("Sesión renovada automáticamente");
    } catch (error) {
      // Si el token ya se perdió, intentamos recuperarlo
      if (error.response?.status === 419) {
        window.location.reload();
      }
    }
  }, 300000);
  return interval;
};

let heartbeatInterval;
onMounted(() => {
  heartbeatInterval = startHeartbeat();
});

onUnmounted(() => {
  clearInterval(heartbeatInterval);
});
</script>
<template>
  <div>
    <VOverlay
      v-model="isCurrencyChanging"
      persistent
      class="align-center justify-center"
      scrim="rgba(255, 255, 255, 0.7)"
      z-index="10000"
    >
      <div class="d-flex flex-column align-center gap-3">
        <v-progress-circular
          indeterminate
          color="primary"
          size="64"
          width="6"
        ></v-progress-circular>
        <span class="text-h6 font-weight-black text-primary text-uppercase letter-spacing-1">Convirtiendo Moneda...</span>
      </div>
    </VOverlay>

    <div v-if="isLoadingInitialOrder">
      <p>Cargando sesión de orden...</p>
    </div>

    <div v-else-if="hasOpenOrder">
      <OpenOrderCard
        v-model:searchQuery="barcodeSearchQuery"
        :order-products="orderItems || []"
        :order="openOrderData || null"
        :order-reserved="reservedOrderData || null"
        :total-products-amount="totalProductsAmount || 0"
        :total-iva-amount="totalIVAAmount || 0"
        :total-order-amount="totalOrderAmount || 0"
        :company-discount-total="totalCompanyDiscountAmount || 0"
        :doctor-discount-total="totalDoctorDiscountAmount || 0"
        :recipe-discount-total="totalRecipeDiscountAmount || 0"
        :expiration-discount-total="totalExpirationDiscountAmount || 0"
        :cliente="selectedClient || null"
        :exchange-rates="exchangeRates"
        :selected-display-currency="selectedDisplayCurrency || 'USD'"
        @currency-changed="handleCurrencyChanged"
        @update-quantity="updateOrderItemQuantity"
        @remove-item="removeOrderItem"
        @cancelar-order="cancelarOrder"
        @reserve-order="reserverOrder"
        @open-buys-modal="openBuysModal"
        @add-quotation-products="handleAddQuotationProducts"
        @add-reserved-order="addReserverOrder"
        v-model:selected-discount-type="selectedDiscountType"
        :active-doctor-offers="activeDoctorOffers || []"
        :prescription-discount-percentage="
          currentPrescriptionDiscountPercentage || 0
        "
        :active-company-offers="activeCompanyOffers || []"
        @doctor-discount-selected="handleDoctorDiscountSelected"
        @prescription-file-selected="handlePrescriptionFileSelected"
        @company-discount-selected="handleCompanyDiscountSelected"
        :global-discount="currentGlobalDiscountDetails || null"
        @add-pack="handleAddPackToOrder"
        @edit-cliente="handleEditCliente"
        :is-special-taxpayer="isSpecialTaxpayer || false"
      />
    </div>
    <div v-else>
      <OrderClienteCard
        v-model="clientIdentification"
        :buttons-icon-only="true"
        :show-quotation-input="true"
        @verify-client="verifyClient"
        @identify-and-start="handleIdentifyAndStart"
        @reserved-order-cliente="reservedOrderCliente"
        @load-quotation="handleLoadQuotation"
      />
    </div>

    <OrderFilters
      v-model:searchQuery="filterSearchQuery"
      v-model:selectedLaboratory="selectedLaboratory"
      v-model:selectedOrigin="selectedOrigin"
      v-model:stockStatusFilter="stockStatusFilter"
      v-model:isStrictSearch="isStrictSearch"
      :laboratories="laboratories || []"
      :origins="origins || []"
      :loading="isLoadingFilters || false"
      :sort-by="sortBy"
      :order-by="orderBy"
      @clear="handleClearFilters"
      @clear-sort="handleClearSortOrder"
      @sort="handleExternalSort"
      @back="handleBackFromGroupView"
    >
    </OrderFilters>

    <OrderProductsTable
      :products="products || []"
      :loading="loading"
      :total-product="totalProduct || 0"
      v-model:items-per-page="itemsPerPage"
      v-model:page="page"
      :discount-min-products="discountMinProducts || 0"
      :discount-max-products="discountMaxProducts || 0"
      :current-discount="discount || 0"
      :order-items="orderItems || []"
      :options="tableOptions"
      :exchange-rates="exchangeRates"
      :currency="selectedDisplayCurrency"
      @update:options="updateTableOptions"
      @add-product="addProductToOrder"
      @view-group-products="fetchGroupProducts"
      @failures-products="fetchFailuresProducts"
      @view-pack-details="handleViewPackDetails"
      @add-pack="handleAddPackToOrder"
    />

    <!--   <OrderPacksTable
          :packs="packs"
          :loading="loadingPacks"
          :total-packs="totalPacks"
          :items-per-page="packsItemsPerPage"
          :page="packsPage"
          @update:options="updatePacksOptions"
          @add-pack="handleAddPackToOrder"
          @view-pack-details="handleViewPackDetails"
        />-->

    <PackDetailsModal
      v-model:isDialogVisible="showPackDetailsModal"
      :pack="selectedPack || null"
    />

    <RegisterClientModal
      :companies="companies || []"
      :modalFormulario="showRegisterClientModal || false"
      titulo="Registrar Nuevo Cliente"
      :formData="newClientFormData || {}"
      :formError="newClientFormErrors || {}"
      @modalClose="handleCloseRegisterModal"
      @save="handleSaveNewClient"
      @clearErrorForm="clearFormErrors"
    />

    <BuysModal
      v-model:is-dialog-visible="showBuysModal"
      :order-products="orderItems || []"
      :order-data="openOrderData || null"
      :is-external-loading="isFinishingOrder"
      :total-amount="totalOrderAmount || 0"
      :selected-currency="selectedDisplayCurrency || 'USD'"
      @modal-closed="closeBuysModal"
      @purchase-completed="handleBuysCompletion"
      :company-discount-total="totalCompanyDiscountAmount || 0"
      :selected-discount-type="selectedDiscountType || null"
      :doctor-discount-total="totalDoctorDiscountAmount || 0"
      :recipe-discount-total="totalRecipeDiscountAmount || 0"
      :expiration-discount-total="totalExpirationDiscountAmount || 0"
      :active-doctor-offers="activeDoctorOffers || []"
      :prescription-discount-percentage="
        currentPrescriptionDiscountPercentage || 0
      "
      :active-company-offers="activeCompanyOffers || []"
      :global-discount="currentGlobalDiscountDetails || null"
      :is-special-taxpayer="isSpecialTaxpayer || false"
      :all-foreign-sales-spe="allForeignSalesSpe || false"
      :foreign-orders-count="foreignOrdersCount || 0"
      @printTicke-completed="printTickeCompletion"
      @print-fiscal="printFiscalPNP"
      @finish-and-reload="finalizeAndCheckPending"
    />

    <div
      id="orderPrint"
      :style="
        isPrinting
          ? {
              position: 'fixed',
              left: '0',
              top: '0',
              zIndex: 9999,
              background: 'white',
              width: '54mm',
            }
          : { position: 'absolute', left: '-9999px' }
      "
    >
      <OrderTicketThermal54
        v-if="orderData"
        :order-data="orderData"
        :order-products="itemsForTicket || []"
        :total-amount="TotalToPrint || 0"
        :selected-currency="selectedDisplayCurrency || 'USD'"
        :payments="paymentsForPrint || []"
        :change-amount="changeAmountForPrint || 0"
        :change-amount-origin="changeAmountOriginForPrint || 0"
        :credit-amount="creditAmountForPrint || 0"
        :credit="creditForPrint || false"
        :company-discount-total="companyDiscountForPrint || 0"
        :selected-discount-type="discountTypeForPrint || null"
        :doctor-discount-total="doctorDiscountForPrint || 0"
        :recipe-discount-total="recipeDiscountForPrint || 0"
        :is-special-taxpayer="isSpecialTaxpayer || false"
        :spe-surcharge-amount="speSurchargeAmountPrint || 0"
      />
    </div>
  </div>
</template>
