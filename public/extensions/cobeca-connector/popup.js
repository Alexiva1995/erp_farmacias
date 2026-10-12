document.addEventListener('DOMContentLoaded', () => {
  const erpUrlInput = document.getElementById('erpUrl');
  const saveBtn = document.getElementById('saveBtn');
  const openPortalBtn = document.getElementById('openPortalBtn');
  const syncStatus = document.getElementById('syncStatus');
  const syncTime = document.getElementById('syncTime');

  // Cargar configuración guardada
  chrome.storage.local.get(['erpUrl', 'lastTokenStatus', 'lastSyncAt'], (data) => {
    if (data.erpUrl) {
      erpUrlInput.value = data.erpUrl;
    } else {
      // Default: sugerir el dominio actual si es el ERP
      erpUrlInput.value = window.location.origin.includes('cobeca') ? 'https://farmaciabs.com' : window.location.origin;
    }

    if (data.lastTokenStatus) {
      syncStatus.textContent = data.lastTokenStatus;
    }
    if (data.lastSyncAt) {
      syncTime.textContent = data.lastSyncAt;
    }
  });

  // Guardar configuración
  saveBtn.addEventListener('click', () => {
    const url = erpUrlInput.value.trim().replace(/\/+$/, '');
    if (!url) return;

    chrome.storage.local.set({ erpUrl: url }, () => {
      saveBtn.textContent = '¡Guardado!';
      setTimeout(() => {
        saveBtn.textContent = 'Guardar Conexión';
      }, 1500);
    });
  });

  // Enviar sesión actual al ERP
  const syncNowBtn = document.getElementById('syncNowBtn');
  if (syncNowBtn) {
    syncNowBtn.addEventListener('click', async () => {
      syncNowBtn.disabled = true;
      syncNowBtn.textContent = 'Consultando sesión...';
      const erpUrl = erpUrlInput.value.trim().replace(/\/+$/, '') || 'https://farmaciabs.com';

      try {
        const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
        if (tab && tab.url && tab.url.includes('cobeca')) {
          const results = await chrome.scripting.executeScript({
            target: { tabId: tab.id },
            func: () => localStorage.getItem('token') || localStorage.getItem('lastToken'),
          });
          const token = results?.[0]?.result;
          if (token) {
            syncNowBtn.textContent = 'Enviando a ERP...';
            const response = await fetch(`${erpUrl}/api/public/mafarta/receive-token`, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ token: token.trim(), supplier_id: 1011 })
            });
            const data = await response.json();
            if (response.ok && data.success) {
              syncStatus.textContent = 'Sincronizado con éxito';
              syncTime.textContent = new Date().toLocaleTimeString();
              syncNowBtn.textContent = '¡Sincronizado!';
            } else {
              alert(data.message || 'Error al validar token en el ERP');
              syncNowBtn.textContent = 'Error';
            }
          } else {
            alert('No se encontró token en esta pestaña de Cobeca. Asegúrate de haber iniciado sesión.');
            syncNowBtn.textContent = '⚡ Enviar Sesión Actual';
          }
        } else {
          alert('Por favor ve a la pestaña de Cobeca (https://sic.drogueriascobeca.com) y vuelve a presionar este botón.');
          syncNowBtn.textContent = '⚡ Enviar Sesión Actual';
        }
      } catch (err) {
        alert('Error: ' + err.message);
        syncNowBtn.textContent = '⚡ Enviar Sesión Actual';
      } finally {
        syncNowBtn.disabled = false;
      }
    });
  }

  // Abrir portal de Cobeca
  openPortalBtn.addEventListener('click', () => {
    chrome.tabs.create({ url: 'https://sic.drogueriascobeca.com/autenticacion' });
  });
});
