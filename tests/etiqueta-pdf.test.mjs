import { test } from 'node:test';
import assert from 'node:assert/strict';
import { crearPdfMonocromo } from '../resources/js/helpers/printing/etiquetaPdf.js';

for (const alto of [29, 50, 75]) {
    test(`PDF 62x${alto}: una página a tamaño real y referencias válidas`, () => {
        const pdf = Buffer.from(crearPdfMonocromo({ width: 2, height: 1,
            data: new Uint8ClampedArray([0, 0, 0, 255, 255, 255, 255, 255]) }, 62, alto), 'base64').toString('latin1');
        assert.match(pdf, /\/Count 1\b/);
        const box = pdf.match(/\/MediaBox \[0 0 ([\d.]+) ([\d.]+)\]/);
        assert.ok(Math.abs(Number(box[1]) * 25.4 / 72 - 62) < 0.00001);
        assert.ok(Math.abs(Number(box[2]) * 25.4 / 72 - alto) < 0.00001);
        const xref = Number(pdf.match(/startxref\n(\d+)/)[1]);
        assert.equal(pdf.slice(xref, xref + 4), 'xref');
        const offsets = [...pdf.matchAll(/(\d{10}) 00000 n/g)];
        offsets.forEach((entry, index) => assert.ok(pdf.slice(Number(entry[1])).startsWith(`${index + 1} 0 obj`)));
        assert.ok(pdf.includes('stream\n\x80\nendstream'), 'Conserva negro y blanco sin compresión con pérdida');
    });
}
