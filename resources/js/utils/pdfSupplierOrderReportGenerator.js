import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import { roundIaAnalysis } from './iaAnalysisRounding';

/**
 * Genera un PDF optimizado para órdenes de compra, agrupando productos por proveedor.
 */
export default function pdfSupplierOrderReportGenerator(data) {
    const doc = new jsPDF({
        orientation: 'portrait',
        unit: 'mm',
        format: 'a4'
    });
    
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const today = new Date();
    const dateStr = `${today.getDate()}/${String(today.getMonth() + 1).padStart(2, '0')}/${today.getFullYear()}`;

    // --- 1. MEMBRETE ---
    try {
        const logoSrc = '/images/logoDonative.png';
        const logoWidth = 40;
        const logoHeight = 15;
        doc.addImage(logoSrc, 'PNG', 15, 10, logoWidth, logoHeight);
    } catch (error) {
        console.warn("Logo no disponible");
    }

    doc.setFontSize(12);
    doc.setFont('helvetica', 'bold');
    doc.text('FARMACIA BARRIO SUCRE 2024, C.A.', pageWidth - 15, 15, { align: 'right' });
    doc.setFontSize(9);
    doc.setFont('helvetica', 'normal');
    doc.text('ORDEN DE COMPRA SUGERIDA (IA)', pageWidth - 15, 20, { align: 'right' });
    doc.text(`Fecha de Emisión: ${dateStr}`, pageWidth - 15, 24, { align: 'right' });

    doc.setDrawColor(41, 121, 255);
    doc.setLineWidth(0.5);
    doc.line(10, 30, pageWidth - 10, 30);

    // --- 2. FILTRADO Y AGRUPAMIENTO POR PROVEEDOR ---
    // Solo incluir productos que se necesitan pedir (análisis positivo)
    const filteredData = data.filter(p => {
        const qty = roundIaAnalysis(p.solicitar || 0);
        return qty > 0;
    });

    const groupedData = filteredData.reduce((acc, product) => {
        const supplierName = product.best_supplier?.name || 'PRODUCTOS SIN PROVEEDOR SUGERIDO';
        if (!acc[supplierName]) acc[supplierName] = [];
        acc[supplierName].push(product);
        return acc;
    }, {});

    let currentY = 38;

    // --- 3. GENERAR TABLAS POR PROVEEDOR ---
    const supplierNames = Object.keys(groupedData).sort();

    supplierNames.forEach((supplierName) => {
        const products = groupedData[supplierName];

        // Verificar espacio antes de imprimir el encabezado del proveedor
        if (currentY > pageHeight - 30) {
            doc.addPage();
            currentY = 20;
        }

        // Título del Proveedor
        doc.setFontSize(10);
        doc.setFont('helvetica', 'bold');
        doc.setFillColor(41, 121, 255);
        doc.setTextColor(255, 255, 255);
        doc.rect(10, currentY, pageWidth - 20, 8, 'F');
        doc.text(`PROVEEDOR: ${supplierName.toUpperCase()}`, 15, currentY + 5.5);
        
        currentY += 8;

        const headers = [['PRODUCTO', 'LABORATORIO', 'PRECIO OFERTA', 'CANT. PEDIR']];
        const rows = products.map(p => [
            p.name,
            p.laboratory?.name || 'N/A',
            p.best_supplier_price ? `$${parseFloat(p.best_supplier_price).toFixed(2)}` : '---',
            { 
                content: roundIaAnalysis(p.solicitar || 0), 
                styles: { halign: 'center', fontStyle: 'bold', fontSize: 9 } 
            }
        ]);

        autoTable(doc, {
            startY: currentY,
            head: headers,
            body: rows,
            theme: 'grid',
            styles: { 
                fontSize: 8, 
                cellPadding: 1.5,
                lineColor: [200, 200, 200],
                lineWidth: 0.1
            },
            headStyles: { 
                fillColor: [50, 50, 50], 
                textColor: 255,
                halign: 'center'
            },
            columnStyles: {
                0: { cellWidth: 'auto' },
                1: { cellWidth: 40 },
                2: { cellWidth: 30, halign: 'right' },
                3: { cellWidth: 25, halign: 'center' }
            },
            margin: { left: 10, right: 10 },
            didDrawPage: (data) => {
                currentY = data.cursor.y;
            }
        });

        currentY += 12; // Espacio entre proveedores
    });

    // --- 4. PIE DE PÁGINA ---
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.setTextColor(100);
        doc.text(
            `Página ${i} de ${pageCount}`,
            pageWidth / 2,
            pageHeight - 8,
            { align: 'center' }
        );
    }

    doc.save(`Pedido_Proveedores_${today.toISOString().slice(0, 10)}.pdf`);
}
