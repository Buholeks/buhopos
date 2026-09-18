import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import * as esc from '../resources/js/helpers/printing/escpos.js';
import * as raster from '../resources/js/helpers/printing/raster.js';
import { obtenerPrinterConfig, guardarPrinterConfig } from '../resources/js/helpers/printing/printerConfig.js';
import { altoZona, validarZona } from '../resources/js/helpers/printing/ticketLayout.js';
import { crearTicketVenta } from '../resources/js/helpers/tickets/ticketVenta.js';
import { printErrorTitle } from '../resources/js/helpers/printing/printErrors.js';

const sourceUrl = (source) => 'data:text/javascript;base64,' + Buffer.from(source).toString('base64');
async function sourceWithDependencies(file, replacements) {
    let source = await readFile(new URL(file, import.meta.url), 'utf8');
    for (const [from, to] of replacements) source = source.replaceAll(from, to);
    return sourceUrl(source);
}
async function regressionModules() {
    const errorsUrl = new URL('../resources/js/helpers/printing/printErrors.js', import.meta.url).href;
    const rendererUrl = await sourceWithDependencies('../resources/js/helpers/printing/ticketRenderer.js', [
        ['import { crearSvgBarcode } from "@/helpers/etiquetas";', 'const crearSvgBarcode = () => "<img alt=barcode>";'],
        ['"./ticketLayout.js"', JSON.stringify(new URL('../resources/js/helpers/printing/ticketLayout.js', import.meta.url).href)],
        ['"./printErrors.js"', JSON.stringify(errorsUrl)],
    ]);
    const configUrl = new URL('../resources/js/helpers/printing/printerConfig.js', import.meta.url).href;
    const serviceUrl = await sourceWithDependencies('../resources/js/helpers/printing/printerService.js', [
        ["import { enviarQz } from '../qzTray.js';", 'const enviarQz = (...args) => globalThis.__regressionTransport(...args);'],
        ["'./printerConfig.js'", JSON.stringify(configUrl)],
        ["'./escpos.js'", JSON.stringify(new URL('../resources/js/helpers/printing/escpos.js', import.meta.url).href)],
        ["'./raster.js'", JSON.stringify(new URL('../resources/js/helpers/printing/raster.js', import.meta.url).href)],
        ["'./printErrors.js'", JSON.stringify(errorsUrl)],
    ]);
    const entryUrl = await sourceWithDependencies('../resources/js/helpers/tickets/imprimirTicketVenta.js', [
        ['"../printing/printerService.js"', JSON.stringify(serviceUrl)],
        ['"../printing/printerConfig.js"', JSON.stringify(configUrl)],
        ['"../printing/ticketRenderer.js"', JSON.stringify(rendererUrl)],
        ['import http from "@/lib/http";', 'const http = { put() { throw new Error("No modificar configuración"); } };'],
        ['import { toastWarning, swal } from "@/lib/alert";', 'const toastWarning = message => globalThis.__regressionWarnings.push(message); const swal = { isVisible: () => globalThis.__saleDialogVisible, update: value => { globalThis.__saleDialogFooter = value.footer; } };'],
    ]);
    return { renderer: await import(rendererUrl), service: await import(serviceUrl), entry: await import(entryUrl) };
}

test('geometría: tolerancia, margen histórico y números string son avisos; datos corruptos son fatales', () => {
    const element = { id: 'borde', tipo: 'campo', x: 0, y: 0, ancho: 72, alto: 4 };
    assert.deepEqual(validarZona([element], 72), []);
    for (const ancho of [72.000001, 72.02, 76, 80]) {
        const input = { ...element, ancho };
        const before = structuredClone(input);
        assert.equal(validarZona([input], 72)[0].code, 'CANVAS_OVERFLOW');
        assert.deepEqual(input, before);
    }
    assert.deepEqual(validarZona([{ ...element, x: '0', ancho: '72', alto: '4' }], 72), []);
    assert.equal(validarZona([{ ...element, x: -0.01 }], 72)[0].code, 'CANVAS_OVERFLOW');
    assert.deepEqual(validarZona([{ ...element, tipo: 'separador', alto: 0 }], 72), []);
    for (const field of ['x', 'y', 'ancho', 'alto']) {
        for (const invalid of [NaN, Infinity, -Infinity, 'NaN', 'Infinity', undefined, null, '', false, [], {}]) {
            assert.throws(() => validarZona([{ ...element, [field]: invalid }], 72), { code: 'INVALID_DESIGN' });
        }
    }
    for (const invalid of [0, -1]) {
        assert.throws(() => validarZona([{ ...element, ancho: invalid }], 72), { code: 'INVALID_DESIGN' });
        assert.throws(() => validarZona([{ ...element, alto: invalid }], 72), { code: 'INVALID_DESIGN' });
    }
    assert.throws(() => validarZona({}, 72), { code: 'INVALID_DESIGN' });
    assert.throws(() => validarZona([null], 72), { code: 'INVALID_DESIGN' });
});

test('config real empresa 10: venta y ambas reimpresiones llegan a browser/QZ HTML sin modificar diseño', async () => {
    const { entry, service, renderer } = await regressionModules();
    const historical = JSON.parse(await readFile(new URL('./fixtures/ticket-config-historico.json', import.meta.url), 'utf8'));
    const snapshot = JSON.stringify(historical);
    const jobs = [], browserHtml = [];
    let printed = 0, framesRemoved = 0;
    globalThis.__regressionWarnings = [];
    globalThis.__saleDialogVisible = false;
    globalThis.__regressionTransport = async (...args) => { jobs.push(args); return { status: 'submitted' }; };
    globalThis.localStorage = { getItem: key => key === 'buhopos_ticket_config' ? snapshot : null,
        setItem() { throw new Error('No escribir preferencias al imprimir'); }, removeItem() { throw new Error('No borrar preferencias al imprimir'); } };
    const fakeDoc = () => ({
        body: { offsetHeight: 200 }, images: [], fonts: { ready: Promise.resolve() },
        open() {}, write(html) { this.html = html; }, close() {},
        // Reproduce la segunda barrera fatal: texto de pie más alto que su caja.
        querySelectorAll: () => [{ getBoundingClientRect: () => ({ height: 15 }),
            firstElementChild: { getBoundingClientRect: () => ({ height: 20 }) } }],
    });
    globalThis.document = {
        body: { appendChild() {} },
        createElement: () => ({ style: {}, contentDocument: fakeDoc(), remove() { framesRemoved++; } }),
    };
    globalThis.window = { open: () => {
        const doc = fakeDoc();
        return { document: doc, focus() {}, close() {}, print() { printed++; browserHtml.push(doc.html); } };
    } };
    const originalWarn = console.warn;
    console.warn = () => {};
    try {
        for (const impresora of [null, 'Epson TM-T88V']) {
            for (const flujo of ['venta', 'ultima', 'reportes']) {
                const ticket = crearTicketVenta({ folio: 'TKT-001', reimpresion: flujo !== 'venta',
                    empresa: { nombre: 'Empresa de prueba' }, detalles: [{ producto_nombre: 'Producto', cantidad: 1, precio_venta: 10 }],
                    total: 10, pagos: [] });
                const result = await entry.imprimirTicketVenta(ticket, impresora);
                assert.equal(result.status, impresora ? 'submitted' : 'dialog-opened');
                assert.equal(result.warnings.filter(w => w.code === 'CANVAS_OVERFLOW').length, 8);
                assert.ok(result.warnings.some(w => w.code === 'TEXT_OVERFLOW'));
                assert.equal(result.warnings[0].id, 'ba9f83dc-4ff1-4fb3-a5fa-952526d2ccad');
            }
        }
        assert.equal(printed, 3);
        assert.equal(jobs.length, 3);
        assert.equal(framesRemoved, 3);
        assert.equal(globalThis.__regressionWarnings.length, 6);
        const htmls = [...browserHtml, ...jobs.map(j => j[2][0].data)];
        for (const html of htmls) {
            assert.ok(html.includes('left:0mm;top:0mm;width:76mm;height:17mm;overflow:visible'));
            assert.ok(html.includes('Conserve este ticket para Cambios o aclaración'));
            assert.ok(html.includes('left:2.5mm;top:16mm;width:67mm;height:10mm;'));
        }
        assert.equal(JSON.stringify(historical), snapshot);
        // Fallback browser también funciona con la configuración explícita del diseñador.
        await entry.imprimirTicketVenta(crearTicketVenta({}), null, historical);
        assert.equal(printed, 4);
        globalThis.__saleDialogVisible = true;
        await entry.imprimirTicketVenta(crearTicketVenta({}), null, historical);
        assert.equal(globalThis.__regressionWarnings.length, 7, 'No reemplazar el modal de cobro por un toast');
        assert.match(globalThis.__saleDialogFooter, /Se conservó su posición/);
        globalThis.__saleDialogVisible = false;
        const invalid = structuredClone(historical);
        invalid.encabezado.elementos[0].x = Infinity;
        for (const impresora of [null, 'Epson TM-T88V']) {
            await assert.rejects(entry.imprimirTicketVenta(crearTicketVenta({}), impresora, invalid), { code: 'INVALID_DESIGN' });
            await assert.rejects(entry.imprimirTicketVenta(crearTicketVenta({}), impresora, null), { code: 'INVALID_DESIGN' });
        }
        assert.equal(jobs.length, 3);
        assert.throws(() => renderer.crearHtmlTicket(crearTicketVenta({}), { encabezado: { elementos: {} } }), { code: 'INVALID_DESIGN' });
        globalThis.window.open = () => null;
        await assert.rejects(entry.imprimirTicketVenta(crearTicketVenta({}), null, historical), { code: 'POPUP_BLOCKED' });
        globalThis.__regressionTransport = async () => { throw Object.assign(new Error('QZ no disponible'), { code: 'QZ_UNAVAILABLE' }); };
        await assert.rejects(service.imprimirDocumento({ raw: 'A\n', config: { mode: 'qz-raw', printerName: 'Epson' } }), { code: 'QZ_UNAVAILABLE' });
    } finally { console.warn = originalWarn; }
});

test('títulos de error no confunden validación con popup ni transporte', () => {
    for (const [code, title] of Object.entries({ INVALID_DESIGN: 'Diseño inválido', QZ_UNAVAILABLE: 'QZ no disponible',
        PRINTER_NOT_FOUND: 'Impresora no encontrada', QZ_SEND_FAILED: 'Fallo al enviar a QZ',
        POPUP_BLOCKED: 'Popup del navegador bloqueado', PRINT_ERROR: 'Error de impresión' })) {
        assert.equal(printErrorTitle({ code }), title);
    }
});

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

test('escpos: GS v 0 arma encabezado little-endian y valida ancho/alto contra los datos', () => {
    const esperado = ['1d', '76', '30', '00', '01', '00', '02', '00', '00', 'ff'].join('');
    assert.equal(esc.aHex(esc.rasterImage(1, 2, '\x00\xff')), esperado);
    assert.throws(() => esc.rasterImage(0, 1, ''), /Ancho/);
    assert.throws(() => esc.rasterImage(1, 1, 'AB'), /tamaño/);
    assert.throws(() => esc.rasterImage(1, 1, ''), /tamaño/);
});

test('raster: empaqueta 1 bit por punto (alfa bajo = blanco), trocea por bloques y envuelve con init+corte', () => {
    const data = new Uint8ClampedArray([
        0, 0, 0, 255, 255, 255, 255, 255, // fila 0: negro, blanco
        0, 0, 0, 0, 0, 0, 0, 255,         // fila 1: transparente (cuenta como blanco), negro
    ]);
    const bitmap = raster.empaquetarMonocromo({ width: 2, height: 2, data });
    assert.equal(bitmap.widthBytes, 1);
    assert.deepEqual(Array.from(bitmap.bytes), [0x80, 0x40]);
    assert.throws(() => raster.empaquetarMonocromo({ width: 0, height: 1, data }), /Dimensiones/);

    const bloques = raster.trocear({ widthBytes: 1, height: 5, bytes: new Uint8Array([1, 2, 3, 4, 5]) }, 2);
    assert.deepEqual(bloques.map((b) => b.height), [2, 2, 1]);
    assert.deepEqual(Array.from(bloques[2].bytes), [5]);

    // 1 bloque por fila con blockHeight=1: dos comandos GS v 0 de 9 bytes cada uno.
    assert.equal(raster.imagenAEscpos(bitmap, 1).length, 18);
    // blockHeight por defecto (128) agrupa ambas filas en un solo comando.
    assert.equal(raster.imagenAEscpos(bitmap).length, 10);

    const ticket = raster.ticketAEscpos(bitmap, { feedAfterPrint: 3, autoCut: true, cutType: 'partial' });
    assert.ok(ticket.startsWith('\x1b@'), 'debe iniciar con initialize()');
    assert.ok(ticket.endsWith('\x1dV\x01'), 'debe terminar con el corte de finalizar()');
    assert.equal(ticket.length, 2 + 10 + 6);
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

    guardarPrinterConfig({ printerName: 'Qian', mode: 'qz-raster', raster: { blockHeight: 64 } });
    assert.equal(obtenerPrinterConfig().mode, 'qz-raster', 'qz-raster sí se conserva como modo real del terminal');
    assert.equal(obtenerPrinterConfig().raster.blockHeight, 64);
    guardarPrinterConfig({ printerName: '', mode: 'qz-raster' });
    assert.equal(obtenerPrinterConfig('').mode, 'browser', 'sin impresora nunca puede quedar un modo QZ activo');
});

test('canvas crece con elementos y advierte sin bloquear desbordamiento horizontal', () => {
    const elements = [{ x: 0, y: 21, ancho: 70, alto: 10 }];
    assert.equal(altoZona(elements, 22), 31);
    validarZona(elements, 73);
    assert.equal(validarZona(elements, 58)[0].code, 'CANVAS_OVERFLOW');
});

test('QZ 2.2.6: firma valida JSON completo y etiquetas mantienen esquema pixel', async () => {
    const requests = [], jobs = [];
    let hasher, signer;
    const qz = {
        security: { setCertificatePromise() {}, setSignatureAlgorithm() {}, setSignaturePromise(fn) { signer = fn; } },
        api: { setSha256Type(fn) { hasher = fn; } },
        websocket: { isActive: () => false, connect: async () => {} },
        configs: { create: (name, options) => ({ name, options }) },
        printers: { find: async () => ['Brother', 'Epson'] },
        print: async (config, data) => { jobs.push({ config, data }); },
    };
    globalThis.__qzTest = qz;
    globalThis.__httpTest = { post: async (_, body) => {
        requests.push(body.request);
        return { data: { hash: createHash('sha256').update(body.request).digest('hex'), signature: 'signed' } };
    } };
    let source = await readFile(new URL('../resources/js/helpers/qzTray.js', import.meta.url), 'utf8');
    source = source.replace('import qz from "qz-tray";', 'const qz = globalThis.__qzTest;')
        .replace('import http from "@/lib/http";', 'const http = globalThis.__httpTest;')
        .replace("'./printing/printErrors.js'", JSON.stringify(new URL('../resources/js/helpers/printing/printErrors.js', import.meta.url).href));
    const mod = await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'));
    await mod.conectar();
    const payload = JSON.stringify({ call: 'printers.find', params: {}, timestamp: Date.now() });
    const hash = await hasher(payload);
    assert.equal(await new Promise(signer(hash)), 'signed');
    assert.deepEqual(requests, [payload]);
    await mod.imprimirHtml('Brother', '<p>Etiqueta</p>', 62, 29);
    assert.equal(jobs[0].config.options.size.height, 29);
    assert.deepEqual(jobs[0].data, [{ type: 'pixel', format: 'html', flavor: 'plain', data: '<p>Etiqueta</p>' }]);
    await assert.rejects(mod.enviarQz('missing', {}, []), { code: 'PRINTER_NOT_FOUND' });
    qz.print = async () => { throw new Error('Spool error'); };
    await assert.rejects(mod.enviarQz('Epson', {}, []), { code: 'QZ_SEND_FAILED' });
    qz.websocket.connect = async () => { throw new Error('Offline'); };
    await assert.rejects(mod.enviarQz('Epson', {}, []), { code: 'QZ_UNAVAILABLE' });
});

test('servicio RAW: un envío, sin reintento ni navegador, y bloqueo de ticket gráfico', async () => {
    const jobs = [];
    globalThis.__printTransport = async (...args) => { jobs.push(args); throw new Error('Desconexión'); };
    let opened = 0;
    globalThis.window = { open: () => { opened++; } };
    let source = await readFile(new URL('../resources/js/helpers/printing/printerService.js', import.meta.url), 'utf8');
    source = source.replace("import { enviarQz } from '../qzTray.js';", 'const enviarQz = (...args) => globalThis.__printTransport(...args);')
        .replace("'./printerConfig.js'", JSON.stringify(new URL('../resources/js/helpers/printing/printerConfig.js', import.meta.url).href))
        .replace("'./escpos.js'", JSON.stringify(new URL('../resources/js/helpers/printing/escpos.js', import.meta.url).href))
        .replace("'./raster.js'", JSON.stringify(new URL('../resources/js/helpers/printing/raster.js', import.meta.url).href))
        .replace("'./printErrors.js'", JSON.stringify(new URL('../resources/js/helpers/printing/printErrors.js', import.meta.url).href));
    const service = await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'));
    const config = { mode: 'qz-raw', printerName: 'Qian' };
    await assert.rejects(service.imprimirDocumento({ config, raw: '\x1b@\x0a' }), /podría haber sido recibido/);
    assert.equal(jobs.length, 1);
    assert.equal(opened, 0);
    assert.deepEqual(jobs[0][2], [{ type: 'raw', format: 'command', flavor: 'hex', data: '1b400a' }]);
    await assert.rejects(service.imprimirDocumento({ config, html: '<p>Venta</p>' }), /solo está habilitado/);
    assert.equal(jobs.length, 1);
    globalThis.__printTransport = async () => ({ status: 'submitted' });
    assert.deepEqual(await service.imprimirDocumento({ config, raw: 'A\n', renderHtml: () => { throw new Error('RAW no debe validar HTML'); } }), { status: 'submitted' });
});

test('servicio qz-raster: rasteriza el DOM ya cargado y envía imagen+corte por RAW (no pixel/html)', async () => {
    const jobs = [];
    globalThis.__printTransport = async (...args) => { jobs.push(args); return { status: 'submitted' }; };
    let capturado = null;
    globalThis.__rasterStub = {
        capturarBitmap: async (elemento, dpi) => { capturado = { elemento, dpi }; return { widthBytes: 1, height: 1, bytes: new Uint8Array([0xff]) }; },
        ticketAEscpos: (bitmap, cfg) => `IMG(${bitmap.widthBytes}x${bitmap.height})+CUT(${cfg.feedAfterPrint})`,
    };
    const fakeDoc = () => ({
        body: { offsetHeight: 10 }, images: [], fonts: { ready: Promise.resolve() },
        open() {}, write(html) { this.html = html; }, close() {},
        querySelector: (sel) => (sel === '.ticket' ? { marcador: 'raiz-ticket' } : null),
        querySelectorAll: () => [],
    });
    let framesRemoved = 0;
    globalThis.document = { body: { appendChild() {} },
        createElement: () => ({ style: {}, contentDocument: fakeDoc(), remove() { framesRemoved++; } }) };
    let source = await readFile(new URL('../resources/js/helpers/printing/printerService.js', import.meta.url), 'utf8');
    source = source.replace("import { enviarQz } from '../qzTray.js';", 'const enviarQz = (...args) => globalThis.__printTransport(...args);')
        .replace("'./printerConfig.js'", JSON.stringify(new URL('../resources/js/helpers/printing/printerConfig.js', import.meta.url).href))
        .replace("'./escpos.js'", JSON.stringify(new URL('../resources/js/helpers/printing/escpos.js', import.meta.url).href))
        .replace("import { capturarBitmap, ticketAEscpos } from './raster.js';", 'const { capturarBitmap, ticketAEscpos } = globalThis.__rasterStub;')
        .replace("'./printErrors.js'", JSON.stringify(new URL('../resources/js/helpers/printing/printErrors.js', import.meta.url).href));
    const service = await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'));
    const config = { mode: 'qz-raster', printerName: 'Qian', feedAfterPrint: 6, dpi: 300, forceRaw: true };
    const result = await service.imprimirDocumento({ html: '<main class="ticket">x</main>', config });
    assert.equal(result.status, 'submitted');
    assert.equal(jobs.length, 1);
    assert.equal(jobs[0][0], 'Qian');
    assert.deepEqual(jobs[0][1], { copies: 1, forceRaw: true });
    assert.deepEqual(jobs[0][2], [{ type: 'raw', format: 'command', flavor: 'hex',
        data: Buffer.from('IMG(1x1)+CUT(6)', 'latin1').toString('hex') }]);
    assert.deepEqual(capturado, { elemento: { marcador: 'raiz-ticket' }, dpi: 300 });
    assert.equal(framesRemoved, 1);
});

test('render conserva 50 productos y pagos, amplía pie y no introduce altura de página fija', async () => {
    let source = await readFile(new URL('../resources/js/helpers/printing/ticketRenderer.js', import.meta.url), 'utf8');
    source = source.replace('import { crearSvgBarcode } from "@/helpers/etiquetas";', 'const crearSvgBarcode = () => "<img>";')
        .replace('"./ticketLayout.js"', JSON.stringify(new URL('../resources/js/helpers/printing/ticketLayout.js', import.meta.url).href))
        .replace('"./printErrors.js"', JSON.stringify(new URL('../resources/js/helpers/printing/printErrors.js', import.meta.url).href));
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
