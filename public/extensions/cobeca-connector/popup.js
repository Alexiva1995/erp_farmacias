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

  // Abrir portal de Cobeca
  openPortalBtn.addEventListener('click', () => {
    chrome.tabs.create({ url: 'https://sic.drogueriascobeca.com/autenticacion' });
  });
});
