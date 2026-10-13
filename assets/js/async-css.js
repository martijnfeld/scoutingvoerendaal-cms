/**
 * Activeert stylesheets die met media="print" + data-async-css zijn geladen
 * (zie font_awesome_head()), zodat ze het renderen van de pagina niet
 * ophouden. Geen inline onload-handler: die wordt door de CSP geblokkeerd.
 */
(function () {
  var links = document.querySelectorAll('link[data-async-css]');
  Array.prototype.forEach.call(links, function (link) {
    var activate = function () { link.media = 'all'; };
    if (link.sheet) {
      activate();
    } else {
      link.addEventListener('load', activate);
      link.addEventListener('error', activate);
    }
  });
})();
