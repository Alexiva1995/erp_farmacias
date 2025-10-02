import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

export default function pdfStockProductsGenerator(data) {
    console.log("Datos recibidos => ", data);
    const doc = new jsPDF();
    const pageWidth = doc.internal.pageSize.getWidth();
    
    // 1. Encabezado
    const headerY = 10;
    doc.setFontSize(18);
    doc.setFont('helvetica', 'bold');
    doc.text('REPORTE DE PRODUCTOS', pageWidth / 2, headerY, { align: 'center' });
    
    // 2. Fecha de generación
    const today = new Date();
    const dateStr = `Generado el: ${today.getDate()}/${String(today.getMonth() + 1).padStart(2, '0')}/${today.getFullYear()}`;
    doc.text(dateStr, pageWidth - 15, headerY + 10, { align: 'right' });
    
    // 3. Configuración de la tabla
    const headers = [
        [
            { content: '#', styles: { halign: 'center', fontStyle: 'bold' } },
            { content: 'Producto', styles: { fontStyle: 'bold' } },
            { content: 'Ventas', styles: { halign: 'center', fontStyle: 'bold' } },
            { content: 'Stock', styles: { halign: 'center', fontStyle: 'bold' } },
            { content: 'Promedio', styles: { halign: 'center', fontStyle: 'bold' } },
            { content: 'Preferencia', styles: { halign: 'center', fontStyle: 'bold' } },
            { content: 'Diferencia', styles: { halign: 'center', fontStyle: 'bold' } }
        ]
    ];
    
    const rows = data.map((product, index) => [
        { content: index + 1, styles: { halign: 'center' } },
        product.name || 'N/A',
        { content: product.total_sold_completed || '0', styles: { halign: 'center' } },
        { content: product.lote_quantity || '0', styles: { halign: 'center' } },
        { content: product.promedio_calculado || '0', styles: { halign: 'center' } },
        { content: product.preferencia_product || 'N/A', styles: { halign: 'center' } },
        { content: product.diferencia_product || 'N/A', styles: { halign: 'center' } }
    ]);
    
    // 4. Generar tabla
    autoTable(doc, {
        startY: headerY + 20,
        head: headers,
        body: rows,
        theme: 'grid',
        headStyles: { 
            fillColor: [41, 128, 185],
            textColor: 255,
            halign: 'center'
        },
        alternateRowStyles: {
            fillColor: [245, 245, 245]
        },
        styles: {
            fontSize: 9,
            cellPadding: 3,
            overflow: 'linebreak'
        },
        columnStyles: {
            0: { cellWidth: 10, halign: 'center' },
            1: { cellWidth: 45 },
            2: { cellWidth: 20, halign: 'center' },
            3: { cellWidth: 20, halign: 'center' },
            4: { cellWidth: 25, halign: 'center' },
            5: { cellWidth: 25, halign: 'center' },
            6: { cellWidth: 25, halign: 'center' }
        },
        margin: { left: 15, right: 15 }
    });
    
    // 5. Pie de página
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(10);
        doc.text(
            `Página ${i} de ${pageCount}`,
            pageWidth / 2,
            doc.internal.pageSize.height - 10,
            { align: 'center' }
        );
    }
    
    // 6. Guardar PDF
    const fileName = `Stock_Productos_Reporte_${today.toISOString().slice(0, 10)}.pdf`;
    doc.save(fileName);
}
