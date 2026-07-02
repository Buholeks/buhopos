import { computed, reactive, ref } from 'vue'
import http from '@/lib/http'
import { toastError, toastSuccess, toastWarning } from '@/lib/alert'

export function useEncargos({ tipo = 'pedido' } = {}) {
    const pedidos = ref([])
    const cargando = ref(false)
    const guardando = ref(false)
    const buscar = ref('')
    const filtroEstado = ref('')
    const filtroFechaDesde = ref('')
    const filtroFechaHasta = ref('')
    const cliente = ref(null)
    const resumenCliente = reactive({ saldo_favor: 0, pedidos_disponibles: [] })
    const abonos = reactive({})
    const cuentasBancarias = ref([])
    const terminalesPago = ref([])

    const form = reactive({
        tipo,
        fecha_promesa: '',
        anticipo: 0,
        forma_pago: 'efectivo',
        cuenta_bancaria_id: '',
        terminal_pago_id: '',
        notas: '',
        detalles: [],
    })

    async function cargarCuentasBancarias() {
        try {
            const { data } = await http.get('/api/cuentas-bancarias', { params: { activo: 1 } })
            cuentasBancarias.value = data
        } catch {
            cuentasBancarias.value = []
        }
    }

    async function cargarTerminalesPago() {
        try {
            const { data } = await http.get('/api/terminales-pago', { params: { activo: 1 } })
            terminalesPago.value = data
        } catch {
            terminalesPago.value = []
        }
    }

    const subtotal = computed(() => form.detalles.reduce((total, detalle) => total + Number(detalle.cantidad || 0) * Number(detalle.precio_acordado || 0), 0))
    const saldoPendiente = computed(() => Math.max(0, subtotal.value - Number(form.anticipo || 0)))

    function money(value) {
        return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(value || 0))
    }

    function nuevoDetalle() {
        return {
            uid: `${Date.now()}-${Math.random()}`,
            selector_id: null,
            producto_id: null,
            variante_id: null,
            producto_label: '',
            descripcion: '',
            marca_texto: '',
            modelo_texto: '',
            color_texto: '',
            talla_texto: '',
            cantidad: 1,
            precio_acordado: 0,
            notas: '',
        }
    }

    function inicializarAbono(pedidoId) {
        if (!abonos[pedidoId]) {
            abonos[pedidoId] = { monto: '', forma_pago: 'efectivo', cuenta_bancaria_id: '', terminal_pago_id: '' }
        }
    }

    async function buscarClientes(q) {
        const { data } = await http.get('/api/clientes/buscar', { params: { q } })
        return Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
    }

    async function seleccionarCliente(item) {
        cliente.value = item
        resumenCliente.saldo_favor = 0
        resumenCliente.pedidos_disponibles = []
        if (!item?.id) return

        try {
            const { data } = await http.get(`/api/clientes/${item.id}/pedidos-resumen`)
            resumenCliente.saldo_favor = Number(data?.saldo_favor ?? 0)
            resumenCliente.pedidos_disponibles = data?.pedidos_disponibles ?? []
        } catch {
            toastError('No se pudo cargar el saldo del cliente')
        }
    }

    async function buscarProductosConStock(q) {
        const { data } = await http.get('/api/ventas/buscar-variantes', { params: { q } })
        const items = Array.isArray(data) ? data.filter((item) => !item.sin_stock) : []
        return items.map((item) => ({ ...item, selector_id: `${item.producto_id}:${item.id ?? 'sin-variante'}:${item.serie_id ?? 'sin-serie'}` }))
    }

    async function buscarProductosCatalogo(q) {
        const { data } = await http.get('/api/pedidos/buscar-catalogo', { params: { q } })
        const items = Array.isArray(data) ? data : []
        return items.map((item) => ({ ...item, selector_id: `${item.tipo_resultado}:${item.producto_id}:${item.id ?? 'sin-variante'}`, precio_venta: Number(item.precio_venta ?? 0) }))
    }

    function labelProducto(item) {
        const variante = item.nombre_variante ? ` - ${item.nombre_variante}` : ''
        return `${item.nombre || 'Producto'}${variante}`
    }

    function subLabelProducto(item) {
        const partes = []
        if (item.codigo) partes.push(item.codigo)
        if (item.tipo_resultado === 'producto' && item.tiene_variantes) partes.push('seleccionar variante')
        if (item.stock != null) partes.push(`Stock ${Number(item.stock ?? 0)}`)
        partes.push(money(item.precio_venta))
        return partes.join(' | ')
    }

    function agregarProducto(item) {
        if (!item) return
        const detalle = nuevoDetalle()
        detalle.selector_id = item.selector_id
        detalle.producto_id = item.producto_id
        detalle.variante_id = item.id || null
        detalle.producto_label = labelProducto(item)
        detalle.descripcion = ''
        detalle.precio_acordado = Number(item.precio_venta ?? 0)

        if (item.nombre_variante) {
            const partes = String(item.nombre_variante).split('/').map((parte) => parte.trim()).filter(Boolean)
            detalle.color_texto = partes[0] ?? ''
            detalle.talla_texto = partes[1] ?? ''
        }

        form.detalles.push(detalle)
    }

    function quitarDetalle(index) {
        form.detalles.splice(index, 1)
    }

    async function cargarPedidos() {
        cargando.value = true
        try {
            const { data } = await http.get('/api/pedidos', {
                params: {
                    buscar: buscar.value || undefined,
                    tipo,
                    estado: filtroEstado.value || undefined,
                    fecha_desde: filtroFechaDesde.value || undefined,
                    fecha_hasta: filtroFechaHasta.value || undefined,
                    por_pagina: 25,
                },
            })
            pedidos.value = data?.data ?? []
            pedidos.value.forEach((pedido) => inicializarAbono(pedido.id))
        } catch {
            toastError(`No se pudieron cargar los ${tipo === 'apartado' ? 'apartados' : 'pedidos'}`)
        } finally {
            cargando.value = false
        }
    }

    async function guardarEncargo() {
        if (!cliente.value?.id) {
            toastError('Selecciona un cliente')
            return false
        }

        if (Number(form.anticipo) > subtotal.value) {
            toastError('El anticipo no puede superar el total')
            return false
        }

        if (Number(form.anticipo) > 0) {
            if (form.forma_pago === 'transferencia' && !form.cuenta_bancaria_id) {
                toastError('Selecciona la cuenta bancaria del anticipo')
                return false
            }
            if (form.forma_pago === 'tarjeta' && !form.terminal_pago_id) {
                toastError('Selecciona la terminal del anticipo')
                return false
            }
        }

        const detalles = form.detalles.map((detalle) => ({
            producto_id: detalle.producto_id,
            variante_id: detalle.variante_id,
            descripcion: String(detalle.descripcion || '').trim() || detalle.producto_label || '',
            marca_texto: detalle.marca_texto,
            modelo_texto: detalle.modelo_texto,
            color_texto: detalle.color_texto,
            talla_texto: detalle.talla_texto,
            cantidad: Number(detalle.cantidad || 1),
            precio_acordado: Number(detalle.precio_acordado || 0),
            notas: detalle.notas,
        }))

        if (!detalles.length || detalles.some((detalle) => !detalle.descripcion || detalle.cantidad < 1)) {
            toastError('Completa los artículos')
            return false
        }

        const precioCero = detalles.filter((detalle) => Number(detalle.precio_acordado) === 0)
        if (precioCero.length) toastWarning(`${precioCero.length} artículo(s) tienen precio acordado en $0.00`)

        guardando.value = true
        try {
            const hayAnticipo = Number(form.anticipo || 0) > 0
            await http.post('/api/pedidos', {
                tipo,
                cliente_id: cliente.value.id,
                fecha_promesa: form.fecha_promesa || null,
                anticipo: Number(form.anticipo || 0),
                forma_pago: hayAnticipo ? form.forma_pago : null,
                cuenta_bancaria_id: hayAnticipo && form.forma_pago === 'transferencia' ? form.cuenta_bancaria_id || null : null,
                terminal_pago_id: hayAnticipo && form.forma_pago === 'tarjeta' ? form.terminal_pago_id || null : null,
                notas: form.notas,
                detalles,
            })
            toastSuccess(`${tipo === 'apartado' ? 'Apartado' : 'Pedido'} registrado`)
            limpiarFormulario()
            await cargarPedidos()
            return true
        } catch (e) {
            toastError(e?.response?.data?.message || 'No se pudo registrar')
            return false
        } finally {
            guardando.value = false
        }
    }

    async function registrarAbono(pedido) {
        const estado = abonos[pedido.id] || {}
        const monto = Number(estado.monto || 0)
        if (monto <= 0) return toastError('Captura un monto de abono')
        if (monto > Number(pedido.saldo_pendiente)) return toastError('El abono supera el saldo pendiente')

        const formaPago = estado.forma_pago || 'efectivo'
        if (formaPago === 'transferencia' && !estado.cuenta_bancaria_id) return toastError('Selecciona la cuenta bancaria')
        if (formaPago === 'tarjeta' && !estado.terminal_pago_id) return toastError('Selecciona la terminal')

        try {
            await http.post(`/api/pedidos/${pedido.id}/abonos`, {
                monto,
                forma_pago: formaPago,
                cuenta_bancaria_id: formaPago === 'transferencia' ? estado.cuenta_bancaria_id || null : null,
                terminal_pago_id: formaPago === 'tarjeta' ? estado.terminal_pago_id || null : null,
            })
            abonos[pedido.id] = { monto: '', forma_pago: 'efectivo', cuenta_bancaria_id: '', terminal_pago_id: '' }
            toastSuccess('Abono registrado')
            await cargarPedidos()
        } catch (e) {
            toastError(e?.response?.data?.message || 'No se pudo registrar el abono')
        }
    }

    async function cancelarPedido(pedido) {
        const { data } = await http.post(`/api/pedidos/${pedido.id}/cancelar`)
        toastSuccess(data?.message || 'Cancelado correctamente')
        await cargarPedidos()
    }

    function limpiarFormulario() {
        cliente.value = null
        resumenCliente.saldo_favor = 0
        resumenCliente.pedidos_disponibles = []
        form.fecha_promesa = ''
        form.anticipo = 0
        form.forma_pago = 'efectivo'
        form.cuenta_bancaria_id = ''
        form.terminal_pago_id = ''
        form.notas = ''
        form.detalles = []
    }

    return {
        pedidos,
        cargando,
        guardando,
        buscar,
        filtroEstado,
        filtroFechaDesde,
        filtroFechaHasta,
        cliente,
        resumenCliente,
        abonos,
        cuentasBancarias,
        terminalesPago,
        cargarCuentasBancarias,
        cargarTerminalesPago,
        form,
        subtotal,
        saldoPendiente,
        money,
        buscarClientes,
        seleccionarCliente,
        buscarProductosConStock,
        buscarProductosCatalogo,
        labelProducto,
        subLabelProducto,
        agregarProducto,
        quitarDetalle,
        cargarPedidos,
        guardarEncargo,
        registrarAbono,
        cancelarPedido,
        limpiarFormulario,
    }
}
