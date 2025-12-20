<style>
    .modal-title{color: #e2ae76}
</style>

{{-- resources/views/frontend/labor-cost/quick-help.blade.php --}}
<section id="lc-banner" class="mini-banner reveal" aria-label="Guía rápida de costes laborales">
    <div class="mini-banner-head d-flex align-items-center mb-2">
        <span class="banner-kicker">Nuevo</span>
        <h6 class="banner-title ms-2">Entender los costes laborales</h6>
        <button type="button" class="ms-auto banner-cta" data-bs-toggle="modal" data-bs-target="#lcAll">
            Ver todos <i class="bi bi-arrow-right-short"></i>
        </button>
    </div>

    <button class="scroll-arrow left" type="button" aria-label="Desplazar a la izquierda"><i class="bi bi-chevron-left"></i></button>
    <button class="scroll-arrow right" type="button" aria-label="Desplazar a la derecha"><i class="bi bi-chevron-right"></i></button>

    <div class="scroller" tabindex="0">
        <button type="button" class="chip" data-bs-toggle="modal" data-bs-target="#lcBuckets">
            <span class="icon-badge"><i class="bi bi-diagram-3"></i></span>Partidas: compartidas vs departamento<span class="shine"></span>
        </button>

        <button type="button" class="chip" data-bs-toggle="modal" data-bs-target="#lcIncidenza">
            <span class="icon-badge"><i class="bi bi-percent"></i></span>Incidencia del departamento (%)<span class="shine"></span>
        </button>

        <button type="button" class="chip chip--hot" data-bs-toggle="modal" data-bs-target="#lcBEP">
            <span class="icon-badge"><i class="bi bi-graph-up-arrow"></i></span>BEP: mensual y diario<span class="ping"></span><span class="shine"></span>
        </button>

        <button type="button" class="chip" data-bs-toggle="modal" data-bs-target="#lcConsigli">
            <span class="icon-badge"><i class="bi bi-lightbulb"></i></span>Consejos prácticos<span class="shine"></span>
        </button>

        <button type="button" class="chip" data-bs-toggle="modal" data-bs-target="#lcEsempio">
            <span class="icon-badge"><i class="bi bi-calculator"></i></span>Ejemplo paso a paso<span class="shine"></span>
        </button>
    </div>
</section>

{{-- ============ Styles (scoped for banner + modals) ============ --}}
<style>
/* palette */
:root { --primary:#041930; --accent:#e2ae76; }
.mini-banner{--br:18px; position:relative; margin-top:1.25rem; padding:14px 16px 12px; color:#fff; border-radius:var(--br);
  background: radial-gradient(120% 140% at 0% 0%, #0b2b53 0%, var(--primary) 42%, #0a264a 100%);
  border:1px solid rgba(226,174,118,.35); box-shadow:0 12px 32px rgba(4,25,48,.30), inset 0 1px 0 rgba(255,255,255,.04); overflow:hidden;}
.mini-banner::before{content:"";position:absolute;inset:-1px;padding:1.25px;border-radius:inherit;
  background:conic-gradient(from 0deg, rgba(226,174,118,.85) 0deg, transparent 90deg, rgba(226,174,118,.65) 180deg, transparent 270deg, rgba(226,174,118,.85) 360deg);
  -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);-webkit-mask-composite:xor;mask-composite:exclude;animation:spin 10s linear infinite;pointer-events:none;}
.mini-banner::after{content:"";position:absolute;inset:0;background:
  radial-gradient(600px 200px at 90% -50%, rgba(226,174,118,.25), transparent 60%),
  radial-gradient(180px 180px at 10% 120%, rgba(255,255,255,.12), transparent 55%),
  radial-gradient(2px 2px at 40% 60%, rgba(255,255,255,.35), transparent 40%),
  radial-gradient(2px 2px at 70% 35%, rgba(255,255,255,.25), transparent 40%);mix-blend-mode:screen;animation:floatSparkles 8s ease-in-out infinite alternate;pointer-events:none;}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes floatSparkles{0%{transform:translateY(0)}100%{transform:translateY(-6px)}}
@keyframes shineSweep{0%{transform:translateX(-120%) skewX(-12deg)}100%{transform:translateX(120%) skewX(-12deg)}}
@keyframes ping{0%{transform:scale(.9);opacity:.9}70%{transform:scale(1.15);opacity:.15}100%{transform:scale(1.35);opacity:0}}
.banner-kicker{font-size:.70rem;letter-spacing:.14em;text-transform:uppercase;color:var(--accent);padding:2px 8px;border-radius:999px;border:1px solid rgba(226,174,118,.5);background:rgba(226,174,118,.08);}
.banner-title{margin:0;font-weight:800;letter-spacing:.2px;background:linear-gradient(90deg,#ffe0bf,var(--accent));-webkit-background-clip:text;background-clip:text;color:transparent;text-shadow:0 0 .0px transparent, 0 8px 28px rgba(226,174,118,.18);}
.banner-cta{display:inline-flex;align-items:center;gap:2px;font-weight:600;color:#fff;text-decoration:none;padding:6px 10px;border-radius:10px;background:rgba(255,255,255,.08);border:1px solid rgba(226,174,118,.35);transition:transform .15s ease, background .15s ease, border-color .15s ease;}
.banner-cta:hover{transform:translateY(-1px);background:rgba(255,255,255,.14);border-color:rgba(226,174,118,.6)}
.scroller{position:relative;z-index:2;display:flex;gap:.6rem;overflow:auto;scroll-snap-type:x mandatory;padding:6px 44px 8px 44px;}
.scroller:focus{outline:none;box-shadow:0 0 0 .25rem rgba(226,174,118,.25)}
.scroll-arrow{position:absolute;top:50%;transform:translateY(-50%);width:38px;height:38px;border-radius:12px;background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(226,174,118,.35);display:grid;place-items:center;z-index:3;cursor:pointer;backdrop-filter:blur(6px);transition:transform .15s ease, background .15s ease, border-color .15s ease, opacity .15s ease;}
.scroll-arrow:hover{transform:translateY(-50%) scale(1.06);background:rgba(255,255,255,.18);border-color:rgba(226,174,118,.6)}
.scroll-arrow.left{left:8px} .scroll-arrow.right{right:8px}
.chip{position:relative;scroll-snap-align:center;white-space:nowrap;display:inline-flex;align-items:center;gap:.55rem;padding:10px 14px;background:rgba(255,255,255,.10);border:1px solid rgba(226,174,118,.38);color:#fff;border-radius:999px;transition:transform .15s ease, background .15s ease, border-color .15s ease, box-shadow .15s ease;will-change:transform;box-shadow:0 6px 16px rgba(4,25,48,.18);text-decoration:none;}
.chip:hover{transform:translateY(-2px);background:rgba(255,255,255,.16);border-color:rgba(226,174,118,.7);box-shadow:0 10px 22px rgba(4,25,48,.22);}
.icon-badge{width:24px;height:24px;display:grid;place-items:center;border-radius:999px;background:rgba(226,174,118,.20);border:1px solid rgba(226,174,118,.45);color:var(--accent);flex:0 0 auto;}
.chip--cta{background:linear-gradient(90deg, rgba(226,174,118,.95), #ffd7ac);color:var(--primary);border-color:rgba(226,174,118,1);box-shadow:0 10px 24px rgba(226,174,118,.45);}
.chip--cta .icon-badge{background:rgba(255,255,255,.7);border-color:rgba(255,255,255,.9);color:var(--primary)}
.chip--hot .ping{position:absolute;inset:0;border-radius:inherit;pointer-events:none;border:2px solid rgba(226,174,118,.7);filter:drop-shadow(0 0 6px rgba(226,174,118,.35));animation:ping 2.2s cubic-bezier(0,0,0.2,1) infinite;}
.chip .shine{content:"";position:absolute;inset:auto 0 0 0;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.45),transparent);mix-blend-mode:screen;opacity:.0;pointer-events:none;transform:translateX(-120%) skewX(-12deg);}
.chip:hover .shine{animation:shineSweep .9s ease forwards}
/* Modals */
.modal-glass{background:rgba(255,255,255,.9); backdrop-filter: blur(8px); border:1px solid rgba(226,174,118,.35);}
.modal-glass .modal-header{background:linear-gradient(135deg,#0b2b53,#041930); color:#ffe5c7; border:0;}
.modal-glass .btn-close{filter:invert(1) grayscale(1) brightness(200%); opacity:.7}
.formula{background:#fff7ef;border:1px dashed rgba(226,174,118,.6);border-radius:12px;padding:12px 14px;font-weight:600;}
.callout{background:#f8fafc;border-left:4px solid #e2ae76;border-radius:10px;padding:10px 12px;}
</style>

{{-- ============ Modals ============ --}}

{{-- Panorámica --}}
<div class="modal fade" id="lcAll" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content modal-glass rounded-4 shadow-lg">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Costes laborales — visión general para todos</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">Esta página sirve para calcular dos tarifas: <strong>€/min interno</strong> (producción en el local) y <strong>€/min externo</strong> (suministros/terceros).</p>
        <ol class="mb-0">
          <li><strong>Rellena las partidas del departamento</strong> (p. ej. pasteleros, ingredientes, embalaje).</li>
          <li><strong>Rellena las partidas compartidas</strong> (alquiler, electricidad, impuestos…).</li>
          <li>Configura <strong>días/horas de apertura</strong> y (si hace falta) la <strong>incidencia del departamento %</strong>.</li>
          <li>El sistema calcula los dos <em>€/min</em> y el <strong>BEP</strong> (mensual/diario).</li>
        </ol>
      </div>
      <div class="modal-footer">
        <button class="btn btn-accent" data-bs-dismiss="modal">¡Entendido!</button>
      </div>
    </div>
  </div>
</div>

{{-- 1) €/min: cómo funciona --}}
<div class="modal fade" id="lcHowWorks" tabindex="-1" aria-hidden="true" aria-labelledby="lcHowWorksLabel">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content modal-glass rounded-4 shadow-lg">
      <div class="modal-header">
        <h5 id="lcHowWorksLabel" class="modal-title fw-bold">Cómo se calcula el coste por minuto (€/min)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="formula mb-3">
          Minutos totales/mes = <strong>Días de apertura</strong> × <strong>Horas/día</strong> × 60<br>
          <em>División por</em> <strong>n.º de pasteleros</strong> para tener el coste por minuto por empleado.
        </div>

        <p class="mb-1"><strong>Interno (shop_cost_per_min)</strong> — excluye costes NO productivos externos:</p>
        <div class="formula mb-3">
          Base interno = (Total partidas <u>habilitadas</u> − Ingredientes − Alquiler furgoneta − Salarios suministro externo) ÷ (Minutos totales × Pasteleros)
        </div>

        <p class="mb-1"><strong>Externo (external_cost_per_min)</strong> — excluye lo que no pesa sobre proveedores:</p>
        <div class="formula mb-3">
          Base externo = (Total partidas <u>habilitadas</u> − Ingredientes − Dependientes de tienda<span class="text-muted">*</span>) ÷ (Minutos totales × Pasteleros)
        </div>

        <div class="callout mb-2">
          <strong>Factor corrector 4/3</strong>: para cubrir tiempos muertos, seguridad, vacaciones, imprevistos, la app aplica
          un factor ≈ <code>× 4/3</code> a la “base”. Es el mismo que ves en el script de la página.
        </div>
        <small class="text-muted d-block">* Si estás trabajando en un departamento con <em>Incidencia%</em>, la app usa la cuota de los dependientes de tienda proporcional a esa incidencia.</small>
      </div>
      <div class="modal-footer">
        <button class="btn btn-accent" data-bs-dismiss="modal">Todo claro</button>
      </div>
    </div>
  </div>
</div>

{{-- 2) Partidas compartidas vs departamento --}}
<div class="modal fade" id="lcBuckets" tabindex="-1" aria-hidden="true" aria-labelledby="lcBucketsLabel">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content modal-glass rounded-4 shadow-lg">
      <div class="modal-header">
        <h5 id="lcBucketsLabel" class="modal-title fw-bold">Partidas: compartidas y de departamento — qué entra en el cálculo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <ul class="mb-3">
          <li><strong>Compartidas</strong>: electricidad, alquiler/hipoteca, propietario, impuestos, alquiler furgoneta, dependientes de tienda.
              En modo “departamento”, estas están <em>bloqueadas</em> y se aplican en proporción (ver Incidencia%).</li>
          <li><strong>Departamento</strong>: ingredientes, embalaje, pasteleros, otros salarios, conductor suministro externo, otras categorías.
              Son modificables por departamento.</li>
        </ul>
        <div class="callout mb-0">
          El total “habilitado” es la base desde la que restamos algunas partidas (ver ficha anterior) para obtener las dos tarifas €/min coherentes.
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-accent" data-bs-dismiss="modal">Ok, gracias</button>
      </div>
    </div>
  </div>
</div>

{{-- 3) Incidencia del departamento (%) --}}
<div class="modal fade" id="lcIncidenza" tabindex="-1" aria-hidden="true" aria-labelledby="lcIncidenzaLabel">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content modal-glass rounded-4 shadow-lg">
      <div class="modal-header">
        <h5 id="lcIncidenzaLabel" class="modal-title fw-bold">Incidencia del departamento (%) — cómo funciona el reparto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p>Cuando seleccionas un <strong>departamento</strong>, las partidas compartidas pasan a ser de solo lectura. Se aplica la cuota:</p>
        <div class="formula mb-3">
          Cuota compartidas por departamento = Valor compartido × (Incidencia% ÷ 100)
        </div>
        <p class="mb-0">Ejemplo: si el departamento “Pastelería” tiene una incidencia del 40 %, de un alquiler de 2.000 € se imputan <strong>800 €</strong> (2.000 × 0,40).</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-accent" data-bs-dismiss="modal">Entendido</button>
      </div>
    </div>
  </div>
</div>

{{-- 4) BEP --}}
<div class="modal fade" id="lcBEP" tabindex="-1" aria-hidden="true" aria-labelledby="lcBEPLabel">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content modal-glass rounded-4 shadow-lg">
      <div class="modal-header">
        <h5 id="lcBEPLabel" class="modal-title fw-bold">Punto de equilibrio (BEP)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">El BEP mostrado en la página es una referencia de <strong>facturación mínima</strong> necesaria para cubrir los costes fijos/variables indicados.</p>
        <div class="formula mb-3">
          BEP <em>mensual</em> ≈ Suma de partidas (compartidas + departamento) del mes<br>
          BEP <em>diario</em> = BEP mensual ÷ Días de apertura
        </div>
        <div class="callout mb-0">
          Usa el BEP como brújula: si la facturación media diaria está por debajo del BEP diario, revisa volúmenes, precios o costes.
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-accent" data-bs-dismiss="modal">Perfecto</button>
      </div>
    </div>
  </div>
</div>

{{-- 5) Consejos prácticos --}}
<div class="modal fade" id="lcConsigli" tabindex="-1" aria-hidden="true" aria-labelledby="lcConsigliLabel">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content modal-glass rounded-4 shadow-lg">
      <div class="modal-header">
        <h5 id="lcConsigliLabel" class="modal-title fw-bold">Consejos prácticos para tarifas saludables</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <ul class="mb-0">
          <li><strong>Batching</strong>: aumentar la producción por tanda reduce €/pieza porque diluye los minutos.</li>
          <li><strong>Datos realistas</strong>: días/horas demasiado optimistas falsean el coste por minuto.</li>
          <li><strong>Departamentos separados</strong>: usa Incidencia% solo si realmente compartes recursos.</li>
          <li><strong>Control trimestral</strong>: actualiza precios de energía, alquileres, salarios.</li>
        </ul>
      </div>
      <div class="modal-footer">
        <button class="btn btn-accent" data-bs-dismiss="modal">Muy bien</button>
      </div>
    </div>
  </div>
</div>

{{-- 6) Ejemplo práctico --}}
<div class="modal fade" id="lcEsempio" tabindex="-1" aria-hidden="true" aria-labelledby="lcEsempioLabel">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content modal-glass rounded-4 shadow-lg">
      <div class="modal-header">
        <h5 id="lcEsempioLabel" class="modal-title fw-bold">Ejemplo paso a paso</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <ol class="mb-3">
          <li>Apertura: <strong>22 días</strong>, <strong>8 horas/día</strong> → 22×8×60 = <strong>10.560 min</strong>.</li>
          <li>Pasteleros: <strong>2</strong>.</li>
          <li>Total partidas habilitadas (tras incidencia): <strong>18.000 €</strong>.</li>
        </ol>
        <div class="formula mb-2">
          Base interno = (18.000 € − ingredientes − furgoneta − conductor externo) ÷ (10.560 × 2)
        </div>
        <div class="formula mb-3">
          Base externo = (18.000 € − ingredientes − cuota dependientes de tienda) ÷ (10.560 × 2)
        </div>
        <div class="callout">Tarifa final ≈ Base × <strong>4/3</strong>. Compara el resultado con el que se muestra en los campos “€/min”.</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-accent" data-bs-dismiss="modal">Claro</button>
      </div>
    </div>
  </div>
</div>

{{-- ============ Tiny script: arrows only for this banner ============ --}}
<script>
(function(){
  const wrap = document.getElementById('lc-banner'); if (!wrap) return;
  const scroller = wrap.querySelector('.scroller');
  const left = wrap.querySelector('.scroll-arrow.left');
  const right = wrap.querySelector('.scroll-arrow.right');
  function step(){ return Math.max(200, Math.round(scroller.clientWidth * 0.6)); }
  function update(){
    const max = scroller.scrollWidth - scroller.clientWidth - 2;
    left.disabled  = scroller.scrollLeft <= 2;
    right.disabled = scroller.scrollLeft >= max;
    left.style.opacity  = left.disabled  ? .5 : 1;
    right.style.opacity = right.disabled ? .5 : 1;
  }
  left?.addEventListener('click', ()=> scroller.scrollBy({left:-step(), behavior:'smooth'}));
  right?.addEventListener('click',()=> scroller.scrollBy({left: step(), behavior:'smooth'}));
  scroller?.addEventListener('scroll', update, {passive:true});
  window.addEventListener('resize', update);
  update();
})();
</script>
