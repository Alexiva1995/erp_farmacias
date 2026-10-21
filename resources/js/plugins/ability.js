// src/plugins/ability.js

import { AbilityBuilder } from '@casl/ability';

export const buildAbilityForRules = (user) => {
  // Inicializa can y rules. Rules SIEMPRE será un array vacío por defecto.
  const { can, rules } = new AbilityBuilder(); 
  if (user && user.role_id) {
    if (user.role_id === 1 || user.role_id === 4 || user.role?.name?.toLowerCase() === 'admin' || user.role?.name?.toLowerCase() === 'administrador') {
      can('manage', 'all');
      can('manage', 'admin'); 
      can('manage', 'GeneralSetting');
      can('manage', 'supervisor-or-admin');
      can('manage', 'cyclic-menu');
      can('manage', 'cyclic-history');
      can('manage', 'pending-cyclics');
      can('manage', 'closing-cyclics');
      can('manage', 'cycli-user');
      can('manage', 'gastos-expenses');
      can('manage', 'suppliers-list');
      can('manage', 'suppliers-purchase-orders-list');
      can('manage', 'suppliers-purchase-orders-history-list');
      can('manage', 'finances-pending-payments');
      can('manage', 'finances-payment-history');
      can('manage', 'finances-cashout');
      can('manage', 'finances-payslips');
      can('manage', 'finances-cash-closure');
      can('manage', 'finances-cash-closure-user');
      can('manage', 'comparadorAssistence');
      can('manage', 'ia-pedidos');
      can('manage', 'fiscal');
      can('manage', 'iva');
      can('manage', 'islr');
    }

    if (user.role_id === 2 || user.role?.name?.toLowerCase() === 'supervisor') {
      can('manage', 'supervisor'); 
      can('manage', 'supervisor-or-admin');
      can('manage', 'cyclic-menu');
      can('manage', 'pending-cyclics');
      can('manage', 'cycli-user');
      can('manage', 'suppliers-list');
      can('manage', 'suppliers-purchase-orders-list');
      can('manage', 'suppliers-purchase-orders-history-list');
      can('manage', 'finances-pending-payments');
      can('manage', 'finances-payment-history');
      can('manage', 'finances-cashout');
      can('manage', 'finances-payslips');
      can('manage', 'finances-cash-closure');
      can('manage', 'finances-cash-closure-user');
      can('manage', 'user');
      can('manage', 'productividad');
      can('manage', 'gastos-expenses');
      can('manage', 'ia-pedidos');
      can('manage', 'fiscal');
    }

    if (user.role_id === 3 || user.role?.name?.toLowerCase() === 'employee' || user.role?.name?.toLowerCase() === 'empleado') {
      can('manage', 'user');
      can('manage', 'cyclic-menu');
      can('manage', 'pending-cyclics');
      can('manage', 'user-only-cyclic');
      can('manage', 'cycli-user');
      can('manage', 'productividad');
      can('manage', 'gastos-expenses');
      can('manage', 'ia-pedidos');
    }

    // Permisos CASL para rol Contador
    if (user.role_id === 5 || user.role?.name?.toLowerCase() === 'contador') {
      can('manage', 'fiscal');
      can('manage', 'supervisor-or-admin');
      can('manage', 'iva');
      can('manage', 'islr');
      can('manage', 'admin');
    }
  }

  // Si no hay usuario o rol, 'rules' será el array vacío inicial, lo cual es correcto.
  return rules; 
};
