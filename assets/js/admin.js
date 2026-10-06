(function () {
  document.addEventListener('DOMContentLoaded', function () {
    initRichTextEditors();
    initFilePickers();
    initConfirmForms();
    initServerProbes();
  });

  /**
   * Webservertests op Beheerpaneel → Systeemcontrole (admin/controle.php):
   * vraagt elke <tr data-probe-url> zelf op, zonder cookies (zoals een
   * bezoeker), en beoordeelt het antwoord volgens data-probe-expect
   * (zie checks_browser_probes() in includes/checks.php).
   */
  var PROBE_LABELS = { ok: 'OK', warn: 'Let op', error: 'Fout' };
  var PROBE_HEADERS = ['X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy'];

  function initServerProbes() {
    document.querySelectorAll('tr[data-probe-url]').forEach(function (row) {
      var expect = row.getAttribute('data-probe-expect');
      fetch(row.getAttribute('data-probe-url'), {
        cache: 'no-store',
        credentials: 'omit',
        // Bij 'blocked'/'redirect' zelf zien dát er doorverwezen wordt, in plaats van te volgen.
        redirect: expect === 'blocked' || expect === 'redirect' ? 'manual' : 'follow'
      })
        .then(function (response) {
          var result = evaluateProbe(expect, response);
          setProbeResult(row, result[0], result[1]);
        })
        .catch(function () {
          setProbeResult(row, 'warn', 'Het verzoek kon niet worden uitgevoerd (netwerkfout).');
        });
    });
  }

  function evaluateProbe(expect, response) {
    var status = response.status;
    var redirected = response.type === 'opaqueredirect';

    if (expect === 'blocked') {
      if (redirected) return ['ok', 'Wordt doorgestuurd in plaats van getoond.'];
      if (status === 401 || status === 403 || status === 404) return ['ok', 'Afgeschermd (HTTP ' + status + ').'];
      if (status >= 200 && status < 300) {
        return ['error', 'Bereikbaar voor iedereen (HTTP ' + status + ')! De .htaccess-regels worden niet toegepast. Draait de site op nginx, of staat AllowOverride uit? Zie INSTALL.md.'];
      }
      return ['warn', 'Onverwacht antwoord (HTTP ' + status + ').'];
    }

    if (expect === 'xml') {
      var type = response.headers.get('Content-Type') || '';
      if (status === 200 && type.indexOf('xml') !== -1) return ['ok', 'mod_rewrite werkt.'];
      if (status === 404) {
        return ['error', 'Niet gevonden: mod_rewrite ontbreekt of .htaccess wordt genegeerd (AllowOverride). De sitemap en nette pagina-adressen werken dan niet.'];
      }
      return ['warn', 'Onverwacht antwoord (HTTP ' + status + (type ? ', ' + type : '') + ').'];
    }

    if (expect === 'page') {
      if (status === 200) return ['ok', 'Pagina wordt getoond.'];
      return ['error', 'HTTP ' + status + ': nette pagina-adressen werken niet (mod_rewrite).'];
    }

    if (expect === 'redirect') {
      if (redirected) return ['ok', 'Wordt permanent doorgestuurd naar het nieuwe adres.'];
      if (status === 200) return ['warn', 'Wordt niet doorgestuurd; oude links werken nog wel, maar zoekmachines zien twee adressen voor dezelfde pagina.'];
      return ['warn', 'Onverwacht antwoord (HTTP ' + status + ').'];
    }

    if (expect === 'headers') {
      var missing = PROBE_HEADERS.filter(function (name) { return !response.headers.get(name); });
      if (!response.headers.get('Content-Security-Policy')) missing.push('Content-Security-Policy');
      if (!missing.length) return ['ok', 'Alle security-headers zijn aanwezig.'];
      return ['warn', 'Ontbreekt: ' + missing.join(', ') + '. Waarschijnlijk is mod_headers niet beschikbaar of wordt .htaccess genegeerd.'];
    }

    return ['warn', 'Onbekende test.'];
  }

  function setProbeResult(row, status, detail) {
    row.className = 'check-row-' + status;
    var badge = row.querySelector('.check-status');
    badge.className = 'check-status check-' + status;
    badge.textContent = PROBE_LABELS[status];
    row.querySelector('.check-detail').textContent = detail;

    var counter = document.querySelector('[data-check-count="' + status + '"]');
    if (counter) {
      counter.textContent = String(parseInt(counter.textContent, 10) + 1);
    }
  }

  /**
   * Vraagt bevestiging voor formulieren met een data-confirm-attribuut
   * (bv. verwijderen). Vervangt inline onsubmit-handlers, die door de
   * Content-Security-Policy (zie admin/includes/auth.php) geblokkeerd worden.
   */
  function initConfirmForms() {
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (!window.confirm(form.getAttribute('data-confirm'))) {
          e.preventDefault();
        }
      });
    });
  }

  /**
   * Kleuren in de kleurkiezers (tekst- en achtergrondkleur): de huisstijl-
   * kleuren uit assets/css/style.css plus een paar neutrale tinten.
   */
  var EDITOR_COLORS = [
    { color: '#00A551', label: 'Groen' },
    { color: '#00893f', label: 'Donkergroen' },
    { color: '#0066B2', label: 'Blauw' },
    { color: '#F2940A', label: 'Oranje' },
    { color: '#E1071A', label: 'Rood' },
    { color: '#9e365b', label: 'Brique' },
    { color: '#FCDD17', label: 'Geel' },
    { color: '#222222', label: 'Zwart' },
    { color: '#333333', label: 'Tekstkleur' },
    { color: '#6c757d', label: 'Grijs' },
    { color: '#dddddd', label: 'Lichtgrijs' },
    { color: '#ffffff', label: 'Wit', hasBorder: true }
  ];

  /**
   * Betaalde plugins (en plugins die een externe dienst nodig hebben) uit de
   * CKEditor-"super-build". Zonder licentie/account geven ze een foutmelding
   * en laadt de editor niet, dus ze moeten er altijd uit.
   */
  var EDITOR_REMOVE_PLUGINS = [
    'AIAssistant', 'CKBox', 'CKBoxImageEdit', 'CKFinder', 'CKFinderUploadAdapter', 'EasyImage',
    'Base64UploadAdapter', 'ExportPdf', 'ExportWord', 'MultiLevelList',
    'RealTimeCollaborativeComments', 'RealTimeCollaborativeTrackChanges',
    'RealTimeCollaborativeRevisionHistory', 'PresenceList', 'Comments', 'TrackChanges',
    'TrackChangesData', 'RevisionHistory', 'Pagination', 'WProofreader', 'MathType',
    'SlashCommand', 'Template', 'DocumentOutline', 'FormatPainter', 'TableOfContents',
    'PasteFromOfficeEnhanced', 'CaseChange'
  ];

  function richTextEditorConfig() {
    return {
      language: {
        ui: 'nl',
        content: 'nl',
        textPartLanguage: [
          { title: 'Nederlands', languageCode: 'nl' },
          { title: 'Engels', languageCode: 'en' },
          { title: 'Duits', languageCode: 'de' },
          { title: 'Frans', languageCode: 'fr' }
        ]
      },
      removePlugins: EDITOR_REMOVE_PLUGINS,
      toolbar: {
        items: [
          'undo', 'redo', '|',
          'findAndReplace', 'selectAll', '|',
          'heading', 'style', '|',
          'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor', 'highlight', '|',
          'bold', 'italic', 'underline', 'strikethrough', 'subscript', 'superscript', 'code', 'removeFormat', '|',
          'alignment', 'bulletedList', 'numberedList', 'todoList', 'outdent', 'indent', '|',
          'link', 'insertImage', 'mediaEmbed', 'insertTable', 'blockQuote', 'codeBlock',
          'horizontalLine', 'specialCharacters', 'htmlEmbed', '|',
          'textPartLanguage', '|',
          'showBlocks', 'sourceEditing', 'accessibilityHelp'
        ],
        // Alle knoppen zichtbaar over meerdere regels, in plaats van een "⋮"-menu.
        shouldNotGroupWhenFull: true
      },
      heading: {
        options: [
          { model: 'paragraph', title: 'Alinea', class: 'ck-heading_paragraph' },
          { model: 'heading2', view: 'h2', title: 'Kop 2', class: 'ck-heading_heading2' },
          { model: 'heading3', view: 'h3', title: 'Kop 3', class: 'ck-heading_heading3' },
          { model: 'heading4', view: 'h4', title: 'Kop 4', class: 'ck-heading_heading4' },
          { model: 'heading5', view: 'h5', title: 'Kop 5', class: 'ck-heading_heading5' }
        ]
      },
      // Opmaakstijlen; de bijbehorende CSS staat in assets/css/style.css
      // (publieke site) en assets/css/admin.css (in de editor zelf).
      style: {
        definitions: [
          { name: 'Introtekst', element: 'p', classes: ['cms-lead'] },
          { name: 'Infoblok', element: 'p', classes: ['cms-info'] },
          { name: 'Waarschuwing', element: 'p', classes: ['cms-warning'] },
          { name: 'Kleine tekst', element: 'p', classes: ['cms-small'] },
          { name: 'Knop', element: 'a', classes: ['cms-button'] },
          { name: 'Gemarkeerd', element: 'span', classes: ['cms-marked'] }
        ]
      },
      // Lettergrootte/-type als inline style, zodat de publieke site er
      // geen extra CSS-klassen voor nodig heeft.
      fontSize: {
        options: [12, 14, 'default', 18, 20, 24, 28, 32, 40],
        supportAllValues: true
      },
      fontFamily: {
        options: [
          'default',
          'Arial, Helvetica, sans-serif',
          'Georgia, serif',
          'Courier New, Courier, monospace',
          'Tahoma, Geneva, sans-serif',
          'Times New Roman, Times, serif',
          'Trebuchet MS, Helvetica, sans-serif',
          'Verdana, Geneva, sans-serif'
        ],
        supportAllValues: true
      },
      fontColor: { colors: EDITOR_COLORS, columns: 6, documentColors: 12, colorPicker: { format: 'hex' } },
      fontBackgroundColor: { colors: EDITOR_COLORS, columns: 6, documentColors: 12, colorPicker: { format: 'hex' } },
      alignment: {
        options: ['left', 'center', 'right', 'justify']
      },
      list: {
        properties: { styles: true, startIndex: true, reversed: true }
      },
      link: {
        defaultProtocol: 'https://',
        decorators: {
          openInNewTab: {
            mode: 'manual',
            label: 'Openen in nieuw tabblad',
            attributes: { target: '_blank', rel: 'noopener noreferrer' }
          },
          download: {
            mode: 'manual',
            label: 'Downloadbaar bestand',
            attributes: { download: 'download' }
          }
        }
      },
      image: {
        toolbar: [
          'imageStyle:inline', 'imageStyle:wrapText', 'imageStyle:breakText', '|',
          'resizeImage', '|',
          'toggleImageCaption', 'imageTextAlternative', 'linkImage'
        ],
        resizeUnit: '%',
        resizeOptions: [
          { name: 'resizeImage:original', value: null, label: 'Oorspronkelijk' },
          { name: 'resizeImage:25', value: '25', label: '25%' },
          { name: 'resizeImage:50', value: '50', label: '50%' },
          { name: 'resizeImage:75', value: '75', label: '75%' }
        ],
        insert: { integrations: ['upload', 'url'] }
      },
      table: {
        contentToolbar: [
          'tableColumn', 'tableRow', 'mergeTableCells', '|',
          'tableProperties', 'tableCellProperties', 'toggleTableCaption'
        ],
        tableProperties: { borderColors: EDITOR_COLORS, backgroundColors: EDITOR_COLORS },
        tableCellProperties: { borderColors: EDITOR_COLORS, backgroundColors: EDITOR_COLORS }
      },
      mediaEmbed: {
        // Slaat de kant-en-klare embed (bv. de YouTube-iframe) zelf op in de
        // inhoud, zodat de publieke pagina dit kan tonen zonder CKEditor.
        previewsInData: true,
        // Deze diensten leveren geen embed-code, alleen een lege placeholder
        // die op de publieke site niets toont.
        removeProviders: ['instagram', 'twitter', 'googleMaps', 'flickr', 'facebook']
      },
      // Beheerders zijn vertrouwd (zie "Content trust model" in CLAUDE.md):
      // alle HTML mag blijven staan, zodat "Bron" en het plakken van
      // embed-codes werken. Scripts en event-handlers worden wel gestript;
      // die zou de CSP op de publieke site toch blokkeren.
      htmlSupport: {
        allow: [{ name: /.*/, attributes: true, classes: true, styles: true }],
        disallow: [
          { name: /^script$/i },
          { name: /.*/, attributes: [{ key: /^on/i, value: true }] }
        ]
      },
      htmlEmbed: { showPreviews: false }
    };
  }

  function initRichTextEditors() {
    if (typeof CKEDITOR === 'undefined' || !CKEDITOR.ClassicEditor) return;

    var editors = [];
    var textareas = document.querySelectorAll('textarea.rich-text');

    textareas.forEach(function (el) {
      CKEDITOR.ClassicEditor
        .create(el, richTextEditorConfig())
        .then(function (editor) {
          editor.plugins.get('FileRepository').createUploadAdapter = function (loader) {
            return new CkeditorUploadAdapter(loader, el);
          };
          // Woorden-/tekenteller onder de editor.
          var wordCount = editor.plugins.get('WordCount');
          editor.ui.element.parentNode.insertBefore(wordCount.wordCountContainer, editor.ui.element.nextSibling);
          editors.push(editor);
        })
        .catch(function (err) {
          console.error('CKEditor kon niet worden geladen:', err);
        });
    });

    if (!textareas.length) return;

    document.querySelectorAll('form').forEach(function (form) {
      form.addEventListener('submit', function () {
        editors.forEach(function (editor) {
          editor.updateSourceElement();
        });
      });
    });
  }

  /**
   * Uploadadapter voor CKEditor-afbeeldingen: stuurt het bestand naar
   * admin/upload_image.php en levert de URL terug die CKEditor in de
   * inhoud opneemt. Zie https://ckeditor.com/docs/ckeditor5/latest/framework/deep-dive/upload-adapter.html
   */
  function CkeditorUploadAdapter(loader, textareaEl) {
    this.loader = loader;
    this.textareaEl = textareaEl;
  }

  CkeditorUploadAdapter.prototype.upload = function () {
    var textareaEl = this.textareaEl;
    return this.loader.file.then(function (file) {
      return new Promise(function (resolve, reject) {
        var form = textareaEl.form;
        var tokenField = form ? form.querySelector('input[name="csrf_token"]') : null;
        var formData = new FormData();
        formData.append('upload', file);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'upload_image.php', true);
        xhr.responseType = 'json';
        if (tokenField) {
          xhr.setRequestHeader('X-CSRF-Token', tokenField.value);
        }
        xhr.addEventListener('load', function () {
          var response = xhr.response;
          if (!response || response.error) {
            reject((response && response.error && response.error.message) || 'Upload mislukt.');
            return;
          }
          resolve({ default: response.url });
        });
        xhr.addEventListener('error', function () {
          reject('Upload mislukt (netwerkfout).');
        });
        xhr.addEventListener('abort', function () {
          reject();
        });
        xhr.send(formData);
      });
    });
  };

  CkeditorUploadAdapter.prototype.abort = function () {};

  /**
   * Vult een tekstveld met het pad van een bestaand geüpload bestand,
   * zodat je dat pad niet zelf hoeft over te typen. Gekoppeld via
   * <select class="file-picker" data-target="id-van-tekstveld">.
   */
  function initFilePickers() {
    document.querySelectorAll('select.file-picker').forEach(function (select) {
      select.addEventListener('change', function () {
        if (!this.value) return;
        var target = document.getElementById(this.getAttribute('data-target'));
        if (target) {
          target.value = this.value;
          target.dispatchEvent(new Event('input'));
        }
        this.selectedIndex = 0;
      });
    });
  }
})();
