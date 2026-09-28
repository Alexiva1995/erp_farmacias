import { computed } from 'vue';
import { useTheme } from 'vuetify';

/**
 * Composable para obtener los colores del tema de Vuetify reactivamente.
 * Utilizado para alimentar ApexCharts y elementos visuales de BI con la paleta oficial.
 */
export function useBiThemeColors() {
  const theme = useTheme();

  const colors = computed(() => {
    const currentColors = theme.current.value.colors;

    return {
      primary: currentColors.primary || '#E20074',
      secondary: currentColors.secondary || '#7A0099',
      success: currentColors.success || '#28C76F',
      info: currentColors.info || '#00BAD1',
      warning: currentColors.warning || '#FF9F43',
      error: currentColors.error || '#FF4C51',
      surface: currentColors.surface || '#FFFFFF',
      background: currentColors.background || '#F8F9FA',
      onSurface: currentColors['on-surface'] || '#2F2B3D',
      isDark: theme.current.value.dark,
    };
  });

  return {
    colors,
  };
}
