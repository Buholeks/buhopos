import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import * as esc from '../resources/js/helpers/printing/escpos.js';
import { obtenerPrinterConfig, guardarPrinterConfig } from '../resources/js/helpers/printing/printerConfig.js';
import { altoZona, validarZona } from '../resources/js/helpers/printing/ticketLayout.js';

test('RAW: bytes, un corte final, énfasis y ningún pulso de cajón', () => {
    const cfg = { printerName: 'Qian\x1b p', feedAfterPrint: 4, autoCut: true, cutType: 'partial' };
    const bytes = esc.crearDiagnostico(cfg, new Date('2026-09-17T12:00:00Z'));
    assert.ok(bytes.startsWith('\x1b@BUHOPOS\n'));
    assert.ok(bytes.includes('\x1bE\x01Texto enfatizado\n\x1bE\x00'));
    assert.ok(bytes.endsWith('\x1b\x64\x04\x1d\x56\x01'));
    assert.equal(bytes.split('\x1dV').length - 1, 1);
    assert.equal(bytes.includes('\x1bp'), false);
    assert.equal(esc.aHex(esc.crearPruebaCorte({ ...cfg, cutType: 'full' })), '1b400a1b64041d5600');
    assert.equal(esc.aHex(esc.cashDrawerPulse()), '1b700019fa');
    assert.throws(() => esc.feed(256));
    assert.throws(() => esc.feed(-1));
});

test('configuración conserva selección clásica y separa diseño de terminal', () => {
    const store = new Map([['buhopos_qz_impresora_ticket', 'Epson'], ['buhopos_ticket_config', '{"encabezado":{}}']]);
    globalThis.localStorage = { getItem: key => store.get(key) ?? null, setItem: (k, v) => store.set(k, v), removeItem: k => store.delete(k) };
    assert.equal(obtenerPrinterConfig().mode, 'qz-html');
    guardarPrinterConfig({ printerName: 'Qian', mode: 'qz-raw', feedAfterPrint: 4 });
    assert.equal(obtenerPrinterConfig().mode, 'qz-html', 'RAW experimental no activa tickets gráficos');
    guardarPrinterConfig({ mode: 'browser' });
    assert.equal(obtenerPrinterConfig().mode, 'browser');
    assert.equal(store.get('buhopos_ticket_config'), '{"encabezado":{}}');
});

test('canvas crece con elementos y rechaza desbordamiento horizontal', () => {
    const elements = [{ x: 0, y: 21, ancho: 70, alto: 10 }];
    assert.equal(altoZona(elements, 22), 31);
    validarZona(elements, 73);
    assert.throws(() => validarZona(elements, 58));
});

test('QZ 2.2.6: firma valida JSON completo y etiquetas mantienen esquema pixel', async () => {
    const requests = [], jobs = [];
    let hasher, signer;
    const qz = {
        security: { setCertificatePromise() {}, setSignatureAlgorithm() {}, setSignaturePromise(fn) { signer = fn; } },
        api: { setSha256Type(fn) { hasher = fn; } },
        websocket: { isActive: () => false, connect: async () => {} },
        configs: { create: (name, options) => ({ name, options }) },
        print: async (config, data) => { jobs.push({ config, data }); },
    };
    globalThis.__qzTest = qz;
    globalThis.__httpTest = { post: async (_, body) => {
        requests.push(body.request);
        return { data: { hash: createHash('sha256').update(body.request).digest('hex'), signature: 'signed' } };
    } };
    let source = await readFile(new URL('../resources/js/helpers/qzTray.js', import.meta.url), 'utf8');
    source = source.replace('import qz from "qz-tray";', 'const qz = globalThis.__qzTest;')
        .replace('import http from "@/lib/http";', 'const http = globalThis.__httpTest;');
    const mod = await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'));
    await mod.conectar();
    const payload = JSON.stringify({ call: 'printers.find', params: {}, timestamp: Date.now() });
    const hash = await hasher(payload);
    assert.equal(await new Promise(signer(hash)), 'signed');
    assert.deepEqual(requests, [payload]);
    await mod.imprimirHtml('Brother', '<p>Etiqueta</p>', 62, 29);
    assert.equal(jobs[0].config.options.size.height, 29);
    assert.deepEqual(jobs[0].data, [{ type: 'pixel', format: 'html', flavor: 'plain', data: '<p>Etiqueta</p>' }]);
});

test('servicio RAW: un envío, sin reintento ni navegador, y bloqueo de ticket gráfico', async () => {
    const jobs = [];
    globalThis.__printTransport = async (...args) => { jobs.push(args); throw new Error('Desconexión'); };
    let opened = 0;
    globalThis.window = { open: () => { opened++; } };
    let source = await readFile(new URL('../resources/js/helpers/printing/printerService.js', import.meta.url), 'utf8');
    source = source.replace("import { enviarQz } from '../qzTray.js';", 'const enviarQz = (...args) => globalThis.__printTransport(...args);')
        .replace("'./printerConfig.js'", JSON.stringify(new URL('../resources/js/helpers/printing/printerConfig.js', import.meta.url).href))
        .replace("'./escpos.js'", JSON.stringify(new URL('../resources/js/helpers/printing/escpos.js', import.meta.url).href));
    const service = await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'));
    const config = { mode: 'qz-raw', printerName: 'Qian' };
    await assert.rejects(service.imprimirDocumento({ config, raw: '\x1b@\x0a' }), /podría haber sido recibido/);
    assert.equal(jobs.length, 1);
    assert.equal(opened, 0);
    assert.deepEqual(jobs[0][2], [{ type: 'raw', format: 'command', flavor: 'hex', data: '1b400a' }]);
    await assert.rejects(service.imprimirDocumento({ config, html: '<p>Venta</p>' }), /solo está habilitado/);
    assert.equal(jobs.length, 1);
    globalThis.__printTransport = async () => ({ status: 'submitted' });
    assert.deepEqual(await service.imprimirDocumento({ config, raw: 'A\n' }), { status: 'submitted' });
});

test('render conserva 50 productos y pagos, amplía pie y no introduce altura de página fija', async () => {
    let source = await readFile(new URL('../resources/js/helpers/printing/ticketRenderer.js', import.meta.url), 'utf8');
    source = source.replace('import { crearSvgBarcode } from "@/helpers/etiquetas";', 'const crearSvgBarcode = () => "<img>";')
        .replace('"./ticketLayout.js"', JSON.stringify(new URL('../resources/js/helpers/printing/ticketLayout.js', import.meta.url).href));
    const { crearHtmlTicket } = await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'));
    const ticket = { folio: 'TEST', fecha: '2026-09-17', total: 50, pagos: [{ forma_pago: 'tarjeta', monto: 50 }],
        productos: Array.from({ length: 50 }, (_, n) => ({ nombre: `Producto-${n}`, importe: 1, cantidad: 1, precio_unitario: 1 })) };
    const cfg = { encabezado: { elementos: [] }, pie: { alto_mm: 22, elementos: [
        { tipo: 'texto', texto: 'FIN COMPLETO', x: 0, y: 21, ancho: 70, alto: 10 },
    ] } };
    const html = crearHtmlTicket(ticket, cfg);
    assert.equal((html.match(/class="producto"/g) || []).length, 50);
    assert.ok(html.includes('FIN COMPLETO'));
    assert.ok(html.includes('Tarjeta'));
    assert.ok(html.includes('height:31mm;overflow:visible'));
    assert.ok(html.includes('@page { size: auto;'));
    assert.equal(html.includes('overflow:hidden'), false);
    assert.ok(crearHtmlTicket(ticket, {}).includes('Conserve este ticket'));
});
