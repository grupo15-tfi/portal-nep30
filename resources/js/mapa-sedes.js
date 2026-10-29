/**
 * HU03 (#49): mapa de sedes y sede más cercana.
 *
 * Contrato (#45):
 *   GET sedes.datos     → [{id, nombre, tipo, direccion, localidad, telefono, latitud, longitud, cursos: [{curso_id, plan, turno, disponibles}]}]
 *   GET sedes.cercanas  → ?lat=..&lng=.. o ?domicilio=.. → {origen: {lat, lng}, sedes: [{id, nombre, distancia_km}]}; 422 si no encuentra el domicilio
 *   GET sedes.localidades (Castillo, #50) → [{localidad, lat, lng}]; si todavía no existe, no se muestra el selector
 *
 * La ubicación y el domicilio no se guardan: solo viajan en el pedido y se usan para ordenar la lista.
 */
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const raiz = document.getElementById('sedes');
if (raiz) iniciar(raiz);

function iniciar(raiz) {
    const url = raiz.dataset;
    const estado = document.getElementById('sedes-estado');
    const lista = document.getElementById('lista-sedes');
    const botonUbicacion = document.getElementById('usar-ubicacion');
    const formulario = document.getElementById('buscar-domicilio');
    const campoDomicilio = document.getElementById('domicilio');
    const campoLocalidad = document.getElementById('campo-localidad');
    const selectLocalidad = document.getElementById('localidad');

    const marcadores = new Map(); // id de sede → marcador
    let marcadorOrigen = null;
    let localidades = [];

    // ---------- Mapa ----------
    const mapa = L.map('mapa-sedes', { scrollWheelZoom: false }).setView([-26.1775, -58.1781], 13);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">colaboradores de OpenStreetMap</a>',
    }).addTo(mapa);
    mapa.attributionControl.setPrefix('<a href="https://leafletjs.com">Leaflet</a>');

    // Botones que solo funcionan con JavaScript
    botonUbicacion.hidden = !('geolocation' in navigator);
    document.getElementById('separador-domicilio').hidden = botonUbicacion.hidden;
    lista.querySelectorAll('[data-ver-en-mapa]').forEach((b) => (b.hidden = false));

    cargarSedes();
    if (url.urlLocalidades) cargarLocalidades();

    // ---------- Eventos ----------
    botonUbicacion.addEventListener('click', () => {
        avisar('Buscando tu ubicación…');
        navigator.geolocation.getCurrentPosition(
            (pos) => buscarCercanas({ lat: pos.coords.latitude.toFixed(6), lng: pos.coords.longitude.toFixed(6) }),
            () => {
                avisar('No pudimos usar tu ubicación (no diste permiso o el celular no la encontró). Escribí tu dirección y tocá «Buscar».', true);
                campoDomicilio.focus();
            },
            { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 },
        );
    });

    formulario.addEventListener('submit', (e) => {
        e.preventDefault();
        const domicilio = campoDomicilio.value.trim();
        const localidad = localidadElegida();

        if (domicilio === '' && localidad) {
            // Sin dirección pero con localidad: se usa el centro de la localidad
            buscarCercanas({ lat: localidad.lat, lng: localidad.lng }, `el centro de ${localidad.localidad}`);
            return;
        }
        if (domicilio === '') {
            avisar('Escribí tu dirección, por ejemplo «Av. 25 de Mayo 1200».', true);
            campoDomicilio.focus();
            return;
        }
        buscarCercanas({ domicilio: localidad ? `${domicilio}, ${localidad.localidad}` : domicilio });
    });

    lista.addEventListener('click', (e) => {
        const boton = e.target.closest('[data-ver-en-mapa]');
        if (!boton) return;
        const id = Number(boton.closest('[data-sede-id]').dataset.sedeId);
        const marcador = marcadores.get(id);
        if (!marcador) return;
        mapa.setView(marcador.getLatLng(), Math.max(mapa.getZoom(), 15));
        marcador.openPopup();
        document.getElementById('mapa-sedes').scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    // ---------- Datos ----------
    async function cargarSedes() {
        try {
            const sedes = await pedir(url.urlDatos);
            const grupo = [];
            for (const sede of sedes) {
                const tipoSede = sede.tipo === 'central' ? 'central' : 'sede';
                const marcador = L.marker([sede.latitud, sede.longitud], {
                    icon: icono(tipoSede),
                    tipoSede,
                    title: sede.nombre,       // texto al pasar el mouse y nombre para el teclado
                    alt: sede.nombre,
                    keyboard: true,
                    riseOnHover: true,
                }).bindPopup(() => ficha(sede), { maxWidth: 280 });
                marcador.addTo(mapa);
                marcadores.set(sede.id, marcador);
                grupo.push([sede.latitud, sede.longitud]);
            }
            if (grupo.length > 1) mapa.fitBounds(grupo, { padding: [40, 40], maxZoom: 15 });
            else if (grupo.length === 1) mapa.setView(grupo[0], 15);
        } catch {
            avisar('No pudimos cargar el mapa. La lista de sedes sigue disponible.', true);
        }
    }

    async function cargarLocalidades() {
        try {
            localidades = await pedir(url.urlLocalidades);
            if (!localidades.length) return;
            selectLocalidad.replaceChildren(new Option('Elegí tu localidad', ''));
            localidades.forEach((l, i) => selectLocalidad.add(new Option(l.localidad, String(i))));
            campoLocalidad.hidden = false;
        } catch {
            // Sin localidades se puede buscar igual por domicilio
        }
    }

    function localidadElegida() {
        return selectLocalidad.value === '' ? null : localidades[Number(selectLocalidad.value)];
    }

    async function buscarCercanas(params, descripcion = null) {
        avisar('Buscando las sedes más cercanas…');
        try {
            const r = await pedir(`${url.urlCercanas}?${new URLSearchParams(params)}`);
            mostrarCercanas(r, descripcion);
        } catch (error) {
            if (error.status === 422) {
                const hayLocalidades = !campoLocalidad.hidden;
                avisar(hayLocalidades
                    ? (error.datos?.message ?? 'No encontramos ese domicilio.')
                    : 'No encontramos ese domicilio. Probá con otra dirección, agregando el barrio.', true);
                (hayLocalidades ? selectLocalidad : campoDomicilio).focus();
            } else if (error.status === 429) {
                avisar('Hiciste muchas búsquedas seguidas. Esperá un minuto y probá de nuevo.', true);
            } else {
                avisar('No pudimos buscar en este momento. Probá de nuevo en un rato.', true);
            }
        }
    }

    // ---------- Resultado ----------
    function mostrarCercanas({ origen, sedes }, descripcion) {
        if (!sedes.length) {
            avisar('No hay sedes cargadas para comparar.', true);
            return;
        }

        // Ordenar la lista por distancia y resaltar la más cercana
        sedes.forEach((s, i) => {
            const item = lista.querySelector(`[data-sede-id="${s.id}"]`);
            if (!item) return;
            item.querySelector('[data-distancia]').textContent = `A ${km(s.distancia_km)} (aproximada, en línea recta)`;
            const cercana = i === 0;
            item.classList.toggle('border-2', cercana);
            item.classList.toggle('border-primario', cercana);
            item.querySelector('.sede-cercana-etiqueta').classList.toggle('hidden', !cercana);
            lista.appendChild(item);
        });

        // Marcar dónde está la persona y la sede más cercana
        marcadorOrigen?.remove();
        marcadorOrigen = L.circleMarker([origen.lat, origen.lng], {
            radius: 8, color: '#FFFFFF', weight: 3, fillColor: '#2F6FD6', fillOpacity: 1,
        }).addTo(mapa).bindTooltip('Vos estás acá', { direction: 'top' });

        marcadores.forEach((m, id) => m.setIcon(icono(id === sedes[0].id ? 'cercana' : m.options.tipoSede)));
        const cercana = marcadores.get(sedes[0].id);
        if (cercana) mapa.fitBounds([[origen.lat, origen.lng], cercana.getLatLng()], { padding: [50, 50], maxZoom: 16 });

        const desde = descripcion ? ` desde ${descripcion}` : '';
        avisar(`La sede más cercana${desde} es ${sedes[0].nombre}, a ${km(sedes[0].distancia_km)} en línea recta. La lista quedó ordenada por distancia.`);
        lista.querySelector('[data-sede-id]')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function avisar(texto, esError = false) {
        estado.textContent = texto;
        estado.classList.toggle('text-error', esError);
        estado.classList.toggle('text-texto', !esError);
        estado.classList.toggle('font-semibold', esError);
    }
}

// ---------- Ayudas ----------

async function pedir(direccion) {
    const r = await fetch(direccion, { headers: { Accept: 'application/json' } });
    if (!r.ok) {
        const error = new Error(`HTTP ${r.status}`);
        error.status = r.status;
        error.datos = await r.json().catch(() => null);
        throw error;
    }
    return r.json();
}

function km(valor) {
    return `${Number(valor).toLocaleString('es-AR', { maximumFractionDigits: 1 })} km`;
}

/** Marcador en forma de gota: azul (sede o anexo), azul más grande (central), verde (la más cercana) */
function icono(clase) {
    const color = clase === 'cercana' ? '#24693F' : '#23358C';
    if (clase === 'central') {
        return L.divIcon({
            className: 'marcador-sede marcador-central',
            html: `<svg width="36" height="48" viewBox="0 0 30 40" aria-hidden="true"><path d="M15 1C7.3 1 1 7.2 1 14.9 1 25.3 15 39 15 39s14-13.7 14-24.1C29 7.2 22.7 1 15 1z" fill="${color}" stroke="#fff" stroke-width="2"/><circle cx="15" cy="15" r="5.5" fill="#fff"/></svg>`,
            iconSize: [36, 48],
            iconAnchor: [18, 47],
            popupAnchor: [0, -40],
        });
    }
    return L.divIcon({
        className: `marcador-sede marcador-${clase}`,
        html: `<svg width="30" height="40" viewBox="0 0 30 40" aria-hidden="true"><path d="M15 1C7.3 1 1 7.2 1 14.9 1 25.3 15 39 15 39s14-13.7 14-24.1C29 7.2 22.7 1 15 1z" fill="${color}" stroke="#fff" stroke-width="2"/><circle cx="15" cy="15" r="5.5" fill="#fff"/></svg>`,
        iconSize: [30, 40],
        iconAnchor: [15, 39],
        popupAnchor: [0, -34],
    });
}

/** Ficha del marcador: se arma con nodos (textContent) para no insertar HTML de los datos */
function ficha(sede) {
    const div = document.createElement('div');
    div.className = 'ficha-sede';
    const linea = (etiqueta, texto, clase = '') => {
        const p = document.createElement(etiqueta);
        p.textContent = texto;
        if (clase) p.className = clase;
        div.appendChild(p);
        return p;
    };
    linea('strong', sede.nombre, 'block text-base');
    linea('p', [sede.direccion, sede.localidad].filter(Boolean).join(', '), 'm-0');
    if (sede.telefono) linea('p', `Teléfono: ${sede.telefono}`, 'm-0');

    if (sede.cursos.length) {
        linea('p', 'Cursos:', 'mt-2 mb-0 font-semibold');
        const ul = document.createElement('ul');
        ul.className = 'm-0 pl-4 list-disc';
        sede.cursos.forEach((c) => {
            const li = document.createElement('li');
            li.textContent = `${c.plan} · turno ${c.turno} · ${c.disponibles > 0 ? (c.disponibles === 1 ? '1 cupo' : `${c.disponibles} cupos`) : 'lista de espera'}`;
            ul.appendChild(li);
        });
        div.appendChild(ul);
    } else {
        linea('p', 'Sin cursos con inscripción abierta.', 'mt-2 mb-0');
    }

    const a = document.createElement('a');
    a.href = `${document.getElementById('sedes').dataset.urlOferta}?sede=${sede.id}`;
    a.textContent = 'Ver la oferta de esta sede';
    a.className = 'mt-2 inline-block font-semibold';
    div.appendChild(a);
    return div;
}
