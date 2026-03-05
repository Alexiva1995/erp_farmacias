import axios from "@/plugins/axios";
import { reactive, ref } from "vue";

export const useBalance = () => {
  const balance = reactive({
    assets: {
      details: {
        cash: 0,
        inventory: 0,
        furniture_bruto: 0
      },
      total_bruto: 0,
      total_neto: 0,
      depreciation: 0
    },
    liabilities: {
      details: {
        supplier_debts: 0,
        loans: 0
      },
      total: 0
    },
    equity: 0,
    ratios: {
      liquidity: 0,
      solvency: 0
    }
  });

  const loading = ref(false);

  const fetchBalance = async () => {
    loading.value = true;
    try {
      const { data } = await axios.get("/finances/balance-general");
      Object.assign(balance, data.data);
    } catch (error) {
      console.error("Error al obtener el balance:", error);
    } finally {
      loading.value = false;
    }
  };

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat("en-US", {
      style: "currency",
      currency: "USD",
    }).format(amount);
  };

  return {
    balance,
    loading,
    fetchBalance,
    formatCurrency
  };
};
