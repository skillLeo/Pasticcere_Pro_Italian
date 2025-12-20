<style>
    .modal-title{color: #e2ae76}
</style>

{{-- resources/views/frontend/recipe/quick-actions.blade.php --}}
<section id="qa-banner" class="mini-banner reveal" aria-label="Tutoriales y recursos">
    <div class="mini-banner-head d-flex align-items-center mb-2">
        <span class="banner-kicker">Nuevo</span>
        <h6 class="banner-title ms-2">Consejos rápidos &amp; Tutoriales</h6>
        <button type="button" class="ms-auto banner-cta" data-bs-toggle="modal" data-bs-target="#tipAll">
            Ver todos <i class="bi bi-arrow-right-short"></i>
        </button>
    </div>

    {{-- <button class="scroll-arrow left" type="button" aria-label="Scorri a sinistra"><i class="bi bi-chevron-left"></i></button>
    <button class="scroll-arrow right" type="button" aria-label="Scorri a destra"><i class="bi bi-chevron-right"></i></button> --}}

    <div class="scroller" tabindex="0">
        <button type="button" class="chip chip--cta" data-bs-toggle="modal" data-bs-target="#tipVideo">
            <span class="icon-badge"><i class="bi bi-play-circle"></i></span>Vídeo: receta perfecta<span class="shine"></span>
        </button>

        <button type="button" class="chip" data-bs-toggle="modal" data-bs-target="#tipCostiKg">
            <span class="icon-badge"><i class="bi bi-currency-euro"></i></span>Costes al kg (guía)<span class="shine"></span>
        </button>

        <button type="button" class="chip" data-bs-toggle="modal" data-bs-target="#tipPrezzi">
            <span class="icon-badge"><i class="bi bi-bag-check"></i></span>Precios de venta: buenas prácticas<span class="shine"></span>
        </button>

        <button type="button" class="chip chip--hot" data-bs-toggle="modal" data-bs-target="#tipMarginiIva">
            <span class="icon-badge"><i class="bi bi-graph-up-arrow"></i></span>Márgenes &amp; IVA explicados<span class="ping"></span><span class="shine"></span>
        </button>

        <button type="button" class="chip" data-bs-toggle="modal" data-bs-target="#tipManodopera">
            <span class="icon-badge"><i class="bi bi-speedometer2"></i></span>Optimiza mano de obra<span class="shine"></span>
        </button>

        <button type="button" class="chip" data-bs-toggle="modal" data-bs-target="#tipTemplateIng">
            <span class="icon-badge"><i class="bi bi-journal-text"></i></span>MODELO de ingredientes<span class="shine"></span>
        </button>
    </div>
</section>

{{-- ====== Estilos de modales (acotados) ====== --}}
<style>
    .modal-glass{
        background: rgba(255,255,255,.9);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(226,174,118,.35);
    }
    .modal-glass .modal-header{
        background: linear-gradient(135deg,#0b2b53, #041930);
        color:#ffe5c7;
        border:0;
    }
    .modal-glass .btn-close{
        filter: invert(1) grayscale(1) brightness(200%);
        opacity:.7
    }
    .formula{
        background:#fff7ef;
        border:1px dashed rgba(226,174,118,.6);
        border-radius:12px;
        padding:12px 14px;
        font-weight:600;
    }
    .callout{
        background:#f8fafc;
        border-left:4px solid #e2ae76;
        border-radius:10px;
        padding:10px 12px;
    }
</style>

{{-- ====== Modales ====== --}}

{{-- Ver todos --}}
<div class="modal fade" id="tipAll" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content modal-glass rounded-4 shadow-lg">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Consejos rápidos &amp; Tutoriales — vista general</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3">¡Bienvenido! Aquí encuentras las bases operativas para calcular costes, precios y márgenes de forma sencilla y coherente con el flujo de la app.</p>
        <ul class="mb-0">
            <li><strong>1. Ingredientes →</strong> introduce cantidades en gramos y obtén el <em>costo de materias</em>.</li>
            <li><strong>2. Mano de obra →</strong> minutos × tarifa de departamento = <em>costo de trabajo</em>.</li>
            <li><strong>3. Embalaje →</strong> añade el coste unitario (por kg o por pieza).</li>
            <li><strong>4. Costo unitario →</strong> €/kg o €/pz antes/después del embalaje.</li>
            <li><strong>5. Precio de venta →</strong> elige modalidad (kg/pieza), aplica IVA, comprueba el <em>margen</em>.</li>
        </ul>
      </div>
      <div class="modal-footer">
        <button class="btn btn-accent" data-bs-dismiss="modal">¡Entendido!</button>
      </div>
    </div>
  </div>
</div>

{{-- 1) Video: ricetta perfetta --}}
<div class="modal fade" id="tipVideo" tabindex="-1" aria-hidden="true" aria-labelledby="tipVideoLabel">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content modal-glass rounded-4 shadow-lg">
      <div class="modal-header">
        <h5 id="tipVideoLabel" class="modal-title fw-bold">Vídeo: receta perfecta — flujo en 3 minutos</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="ratio ratio-16x9 rounded mb-3" style="border:1px solid rgba(226,174,118,.35);">
          <iframe src="https://www.youtube.com/embed/HhC75Ion8fA?rel=0&modestbranding=1"
                  title="Guía rápida Pasticcere Pro" allowfullscreen></iframe>
        </div>
        <div class="callout mb-2"><strong>Checklist rápida:</strong> ingredientes → mano de obra → embalaje → precio → margen.</div>
        <ul class="mb-0">
          <li>Introduce ingredientes en <strong>gramos</strong> (la app calcula € con la tarifa €/kg).</li>
          <li>Selecciona el <strong>departamento</strong> para usar la tarifa correcta €/min.</li>
          <li>Configura el embalaje por dosis (si es por pieza) o por kg.</li>
          <li>Comprueba el <strong>margen</strong> (neto, antes del IVA) y ajusta el precio si hace falta.</li>
        </ul>
      </div>
      <div class="modal-footer">
        <button class="btn btn-accent" data-bs-dismiss="modal">Todo claro</button>
      </div>
    </div>
  </div>
</div>

{{-- 2) Costi al kg (guida) --}}
<div class="modal fade" id="tipCostiKg" tabindex="-1" aria-hidden="true" aria-labelledby="tipCostiKgLabel">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content modal-glass rounded-4 shadow-lg">
      <div class="modal-header">
        <h5 id="tipCostiKgLabel" class="modal-title fw-bold">Costes al kg — guía práctica</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">El coste €/kg reúne materias primas y mano de obra, relacionándolos con el peso final.</p>
        <div class="formula mb-3">
          Costo de materias (€) + Costo mano de obra (€) = <u>Total producción (€)</u><br>
          Peso final (kg) = Peso tras merma (g) / 1000<br>
          <strong>Costo €/kg (antes de embalaje) = Total producción / Peso final</strong><br>
          <em>Costo €/kg (después de embalaje) = Costo €/kg + Embalaje (€/kg)</em>
        </div>
        <p class="mb-2"><strong>Notas:</strong></p>
        <ul class="mb-0">
          <li>Si vendes por pieza, la app convierte a <em>€/pieza</em> manteniendo la lógica anterior.</li>
          <li>Comprueba siempre el peso final: influye directamente en el costo unitario.</li>
        </ul>
      </div>
      <div class="modal-footer">
        <button class="btn btn-accent" data-bs-dismiss="modal">Ok, gracias</button>
      </div>
    </div>
  </div>
</div>

{{-- 3) Prezzi di vendita: best practice --}}
<div class="modal fade" id="tipPrezzi" tabindex="-1" aria-hidden="true" aria-labelledby="tipPrezziLabel">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content modal-glass rounded-4 shadow-lg">
      <div class="modal-header">
        <h5 id="tipPrezziLabel" class="modal-title fw-bold">Precios de venta — buenas prácticas</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">Define primero el <strong>precio neto</strong> (sin IVA) y luego añade el IVA.</p>
        <div class="formula mb-3">
          <strong>Precio neto objetivo = Costo unitario / (1 − Margen%)</strong><br>
          Precio bruto = Precio neto × (1 + IVA%)
        </div>
        <ul class="mb-2">
          <li><strong>Margen%</strong> recomendado para pastelería de mostrador: 45–60% (varía por categoría).</li>
          <li><strong>Recargo</strong> (markup) ≠ Margen%: Recargo% = Margen / Costo.</li>
          <li>Usa “Sugerir precio (×2.2)” como base, luego ajusta según mercado y calidad.</li>
        </ul>
        <div class="callout mb-0">Recuerda: el margen que muestra la app es <em>neto</em> (antes del IVA), así comparas peras con peras.</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-accent" data-bs-dismiss="modal">Perfecto</button>
      </div>
    </div>
  </div>
</div>

{{-- 4) Margini & IVA spiegati --}}
<div class="modal fade" id="tipMarginiIva" tabindex="-1" aria-hidden="true" aria-labelledby="tipMarginiIvaLabel">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content modal-glass rounded-4 shadow-lg">
      <div class="modal-header">
        <h5 id="tipMarginiIvaLabel" class="modal-title fw-bold">Márgenes &amp; IVA — diferencias y fórmulas</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="formula mb-3">
          Margen (neto) = Precio neto − Costo unitario<br>
          <strong>Margen% = Margen / Precio neto</strong><br>
          Recargo% = Margen / Costo unitario<br>
          Precio bruto = Precio neto × (1 + IVA%)
        </div>
        <ul class="mb-0">
          <li><strong>¿Por qué neto?</strong> El IVA es un impuesto aguas abajo: no aumenta el beneficio.</li>
          <li><strong>Control rápido:</strong> si el Margen% baja del objetivo, revisa precio o costes.</li>
        </ul>
      </div>
      <div class="modal-footer">
        <button class="btn btn-accent" data-bs-dismiss="modal">Claro</button>
      </div>
    </div>
  </div>
</div>

{{-- 5) Ottimizza manodopera --}}
<div class="modal fade" id="tipManodopera" tabindex="-1" aria-hidden="true" aria-labelledby="tipManodoperaLabel">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content modal-glass rounded-4 shadow-lg">
      <div class="modal-header">
        <h5 id="tipManodoperaLabel" class="modal-title fw-bold">Optimiza mano de obra — impacto real en el coste</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="formula mb-3">
          <strong>Costo trabajo = Minutos × Tarifa (€/min) del departamento</strong>
        </div>
        <ul class="mb-2">
          <li>Usa el <strong>departamento</strong> correcto para cargar la tarifa adecuada.</li>
          <li>Batching: aumentar la dosis a menudo <em>diluye</em> el coste/min en cada pieza.</li>
          <li>Automatizaciones y mise en place reducen minutos “no productivos”.</li>
        </ul>
        <div class="callout mb-0">Sugerencia: prueba a duplicar la dosis y verifica cómo cambia el costo unitario.</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-accent" data-bs-dismiss="modal">Genial</button>
      </div>
    </div>
  </div>
</div>

{{-- 6) Template ingredienti --}}
<div class="modal fade" id="tipTemplateIng" tabindex="-1" aria-hidden="true" aria-labelledby="tipTemplateIngLabel">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content modal-glass rounded-4 shadow-lg">
      <div class="modal-header">
        <h5 id="tipTemplateIngLabel" class="modal-title fw-bold">MODELO de ingredientes — orden &amp; precisión</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <ul class="mb-3">
          <li>Usa nombres claros y <strong>unidades en gramos</strong> para coherencia en los cálculos.</li>
          <li>Añade recetas base como <em>ingredientes</em> (p. ej. almíbar, crema) para reutilización y control de costes.</li>
          <li>Actualiza los <strong>precios €/kg</strong> según la tarifa del proveedor cuando cambien.</li>
        </ul>
        <div class="formula mb-0">
          <strong>Costo por línea de ingrediente = (€/kg ÷ 1000) × gramos</strong>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-accent" data-bs-dismiss="modal">Perfecto</button>
      </div>
    </div>
  </div>
</div>

{{-- Script pequeño: flechas solo para este banner --}}
<script>
(function(){
    const wrap = document.getElementById('qa-banner');
    if (!wrap) return;
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
    left?.addEventListener('click', ()=> scroller.scrollBy({left: -step(), behavior:'smooth'}));
    right?.addEventListener('click',()=> scroller.scrollBy({left:  step(), behavior:'smooth'}));
    scroller?.addEventListener('scroll', update, {passive:true});
    window.addEventListener('resize', update);
    update();
})();
</script>
