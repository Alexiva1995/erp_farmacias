import businessIntelligence from './businessIntelligence'
import configuration from './configuration'
import crm from './crm'
import cyclicInventory from './cyclicInventory'
import finances from './finances'
import fiscal from './fiscal'
import inventory from './inventory'
import rrhh from './rrhh'
import suppliers from './suppliers'
import tpv from './tpv'

export default [...inventory, ...cyclicInventory, ...tpv, ...crm, ...rrhh, ...fiscal, ...finances,...suppliers, ...invoice, ...productivity, ...businessIntelligence, ...configuration]
