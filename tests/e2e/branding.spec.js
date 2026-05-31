import { test, expect } from '@playwright/test';

test.describe('Pruebas Visuales y de Branding (E2E)', () => {
  
  test('Bug 1: El loader inicial debe usar la variable CSS --initial-loader-color del localStorage', async ({ page }) => {
    // 1. Inyectamos la variable en localStorage usando el prefijo "ERP-" configurado en initCore y themeConfig
    await page.addInitScript(() => {
      window.localStorage.setItem('ERP-initial-loader-color', '#FF0000'); // Rojo
    });

    // 2. Navegamos a la aplicación
    await page.goto('/');

    // 3. Verificamos que el spinner herede la propiedad CSS dinámica y no use el valor original hardcoded '#E20074' (rgb(226, 0, 116))
    const spinnerEffect = page.locator('.loading .effect-1');
    
    // Validar el color computado
    const borderLeftColor = await spinnerEffect.evaluate((el) => {
      return window.getComputedStyle(el).borderInlineStartColor;
    });

    // Comprobar que NO sea el color hardcoded original, demostrando que la variable CSS está activa y surte efecto
    expect(borderLeftColor).not.toBe('rgb(226, 0, 116)');
  });

  test('Bug 3: El logotipo principal debe usar la configuración de app_favicon y cargarse dinámicamente', async ({ page }) => {
    // 1. Interceptar la llamada de configuraciones generales para inyectar un favicon y logo de prueba
    await page.route('**/api/general-settings', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            app_name: 'Farmacia Test E2E',
            app_logo: '/test-dynamic-logo.png', // Logo dinámico de prueba
            app_favicon: '/test-favicon-logo.png',
            primary_color: '#E20074',
            secondary_color: '#7A0099',
          }
        })
      });
    });

    // 2. Navegamos a /login. El login carga la UI y lee la configuración de branding del general-settings
    await page.goto('/login');

    // 3. Verificar que la imagen del logo cargada en la pantalla de login use el logo dinámico inyectado
    const logoImg = page.locator('.app-logo img');
    
    // Validar que tenga el atributo src correcto
    await expect(logoImg).toHaveAttribute('src', '/test-dynamic-logo.png');
  });

});
