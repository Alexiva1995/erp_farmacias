// Content Script que se ejecuta en https://sic.drogueriascobeca.com/*
// Captura el token JWT guardado por el portal SIC tras el inicio de sesión exitoso

(function () {
  console.log('[ERP Farmacias] Extensión de Cobeca inicializada.');

  function extractToken() {
    let token = null;

    // 1. Buscar en localStorage
    try {
      token = localStorage.getItem('token') ||
              localStorage.getItem('access_token') ||
              localStorage.getItem('jwt') ||
              localStorage.getItem('authToken');
    } catch (e) {}

    // 2. Buscar en sessionStorage
    if (!token) {
      try {
        token = sessionStorage.getItem('token') ||
                sessionStorage.getItem('access_token');
      } catch (e) {}
    }

    return token;
  }

  let lastSentToken = null;

  async function syncTokenWithErp(token) {
    if (!token || token === lastSentToken) return;

    // Obtener la URL del ERP configurada en storage de la extensión
    chrome.storage.local.get(['erpUrl', 'supplierId'], async (items) => {
      const erpUrl = (items.erpUrl || window.location.origin).replace(/\/+$/, '');
      const supplierId = items.supplierId || 1011; // ID default de Cobeca/Mafarta

      console.log('[ERP Farmacias] Token de sesión detectado en Cobeca. Sincronizando con ERP...', { erpUrl, supplierId });

      try {
        const response = await fetch(`${erpUrl}/api/public/mafarta/receive-token`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          body: JSON.stringify({
            token: token,
            supplier_id: supplierId,
            origin: 'chrome_extension',
            captured_at: new Date().toISOString()
          })
        });

        if (response.ok) {
          lastSentToken = token;
          console.log('[ERP Farmacias] ¡Token de Cobeca enviado y activado en el ERP con éxito!');
          chrome.storage.local.set({ lastTokenStatus: 'Sincronizado con éxito', lastSyncAt: new Date().toLocaleTimeString() });
        } else {
          console.warn('[ERP Farmacias] El ERP respondió con error al sincronizar token:', await response.text());
        }
      } catch (err) {
        console.error('[ERP Farmacias] Error de red al comunicar con el ERP:', err);
      }
    });
  }

  // Interceptar llamadas fetch y XMLHttpRequest para capturar el token del endpoint /api/auth/login
  const originalFetch = window.fetch;
  window.fetch = async function (...args) {
    const response = await originalFetch.apply(this, args);
    try {
      const url = typeof args[0] === 'string' ? args[0] : args[0]?.url || '';
      if (url.includes('/api/auth/login') || url.includes('/login')) {
        const clone = response.clone();
        clone.json().then(data => {
          const token = data?.token || data?.data?.token || data?.access_token;
          if (token) {
            syncTokenWithErp(token);
          }
        }).catch(() => {});
      }
    } catch (e) {}
    return response;
  };

  // Monitoreo continuo de almacenamiento
  setInterval(() => {
    const token = extractToken();
    if (token && token !== lastSentToken) {
      syncTokenWithErp(token);
    }
  }, 3000);

  // Verificación inicial
  const initToken = extractToken();
  if (initToken) {
    syncTokenWithErp(initToken);
  }
})();
