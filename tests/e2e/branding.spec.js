import { test, expect } from '@playwright/test';

test.describe('Pruebas Visuales y de Branding (E2E)', () => {
  
  test('Bug 1: El loader inicial debe usar la variable CSS --initial-loader-color del localStorage', async ({ page }) => {
    // 1. Inyectamos la variable en localStorage antes de abrir la página para verificar que la configuración de color no se ignore
    await page.addInitScript(() => {
      window.localStorage.setItem('vuexy-initial-loader-color', '#FF0000'); // Rojo
    });

    // 2. Navegamos a la aplicación
    await page.goto('/');

    // 3. Verificamos que el spinner herede la propiedad CSS dinámica y no ignore el localStorage
    const spinnerEffect = page.locator('.loading .effect-1');
    
    // Validar el color computado
    const borderLeftColor = await spinnerEffect.evaluate((el) => {
      return window.getComputedStyle(el).borderInlineStartColor;
    });

    // #FF0000 corresponde a rgb(255, 0, 0)
    expect(borderLeftColor).toBe('rgb(255, 0, 0)');
  });

  test('Bug 3: El logotipo colapsado debe usar la configuración de app_favicon y no /favicon-96x96.png fijo', async ({ page }) => {
    // 1. Interceptar la llamada de configuraciones generales para inyectar un favicon de prueba
    await page.route('**/api/general-settings', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            app_name: 'Farmacia Test E2E',
            app_favicon: '/test-favicon-logo.png', // Logo dinámico de prueba
            primary_color: '#E20074',
            secondary_color: '#7A0099',
          }
        })
      });
    });

    // 2. Vamos al login
    await page.goto('/login');

    // 3. Verificar que la imagen del logo colapsado en la barra lateral tenga el SRC correcto (branding dinámico)
    const collapsedLogo = page.locator('img.logo-collapsed');
    await expect(collapsedLogo).toHaveAttribute('src', '/test-favicon-logo.png');
  });

});
