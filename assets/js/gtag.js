// Google Analytics-initialisatie. Staat in een eigen bestand in plaats van
// inline in de pagina, omdat de Content-Security-Policy (zie
// includes/site_layout_top.php) geen inline-scripts toestaat. Het
// measurement-ID komt uit het data-ga-id-attribuut van de <script>-tag.
(function(){
  var script = document.currentScript;
  var id = script && script.getAttribute('data-ga-id');
  if(!id) return;
  window.dataLayer = window.dataLayer || [];
  window.gtag = function(){ window.dataLayer.push(arguments); };
  window.gtag('js', new Date());
  window.gtag('config', id);
})();
