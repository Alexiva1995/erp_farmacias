// src/plugins/ability.js

import { AbilityBuilder } from '@casl/ability';

export const buildAbilityForRules = (user) => {
  // Inicializa can y rules. Rules SIEMPRE será un array vacío por defecto.
  const { can, rules } = new AbilityBuilder(); 
  console.log(user);
  if (user && user.role_id) {
    if (user.role_id === 1) {
      can('manage', 'admin'); 
      can('manage', 'supervisor-or-admin');
      can('manage', 'cyclic-menu');
      can('manage', 'cyclic-history');
      can('manage', 'pending-cyclics');
      can('manage', 'closing-cyclics');
      can('manage', 'cycli-user');
     // can('manage', 'supervisor');
    //  can('manage', 'user');
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
    }

    if (user.role_id === 2) {
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
      can('manage', 'productividad');
      can('manage', 'comparadorAssistence');
      can('manage', 'gastos-expenses');
    }

    if (user.role_id === 3) {
      can('manage', 'user');
      can('manage', 'cyclic-menu');
      can('manage', 'pending-cyclics');
      can('manage', 'user-only-cyclic');
      can('manage', 'cycli-user');
      can('manage', 'productividad');
      can('manage', 'comparadorAssistence');
      can('manage', 'gastos-expenses');
      can('manage', 'order-general-user');
    }
    // ... otros permisos para otros roles
  }

  // Si no hay usuario o rol, 'rules' será el array vacío inicial, lo cual es correcto.
  return rules; 
};
